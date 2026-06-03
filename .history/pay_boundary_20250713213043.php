<?php
require_once 'db_config.php';
header('Content-Type: application/json');

// Create boundary_payments table if it doesn't exist
$createBoundaryTable = "CREATE TABLE IF NOT EXISTS boundary_payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    driver_id INT NOT NULL,
    operator_id INT NOT NULL,
    jeepney_id INT NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    payment_method VARCHAR(50) NOT NULL,
    paid_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    status VARCHAR(20) DEFAULT 'Pending',
    reference_number VARCHAR(100),
    notes TEXT
)";

try {
    $conn->query($createBoundaryTable);
    
    // Check if reference_number column exists, if not add it
    $checkRefColumn = $conn->query("SHOW COLUMNS FROM boundary_payments LIKE 'reference_number'");
    if ($checkRefColumn->num_rows == 0) {
        $conn->query("ALTER TABLE boundary_payments ADD COLUMN reference_number VARCHAR(100) AFTER status");
        error_log("Added reference_number column to boundary_payments table");
    }
    
    // Check if notes column exists, if not add it
    $checkNotesColumn = $conn->query("SHOW COLUMNS FROM boundary_payments LIKE 'notes'");
    if ($checkNotesColumn->num_rows == 0) {
        $conn->query("ALTER TABLE boundary_payments ADD COLUMN notes TEXT AFTER reference_number");
        error_log("Added notes column to boundary_payments table");
    }
    
    // Add a test boundary payment if none exist
    $testResult = $conn->query("SELECT COUNT(*) as count FROM boundary_payments");
    $testRow = $testResult->fetch_assoc();
    if ($testRow['count'] == 0) {
        // Get first driver and operator
        $driverResult = $conn->query("SELECT id FROM users WHERE userType = 'driver' LIMIT 1");
        $operatorResult = $conn->query("SELECT id FROM users WHERE userType = 'operator' LIMIT 1");
        
        if ($driverResult->num_rows > 0 && $operatorResult->num_rows > 0) {
            $driver = $driverResult->fetch_assoc();
            $operator = $operatorResult->fetch_assoc();
            
            $testStmt = $conn->prepare("INSERT INTO boundary_payments (driver_id, operator_id, jeepney_id, amount, payment_method, status, reference_number) VALUES (?, ?, ?, ?, ?, 'Pending', ?)");
            $reference = 'TEST-' . date('Ymd') . '-001';
            $testStmt->bind_param('iiidss', $driver['id'], $operator['id'], 1, 500, 'GCash', $reference);
            $testStmt->execute();
            error_log("Added test boundary payment: driver=" . $driver['id'] . ", operator=" . $operator['id']);
        }
    }
} catch (Exception $e) {
    error_log("Error setting up boundary_payments table: " . $e->getMessage());
}

$data = json_decode(file_get_contents('php://input'), true);

// Handle boundary payment submission
if (isset($data['driver_id'], $data['operator_id'], $data['jeepney_id'], $data['amount'], $data['payment_method'])) {
    try {
        // Generate reference number
        $referenceNumber = 'BND-' . date('Ymd') . '-' . str_pad($data['driver_id'], 4, '0', STR_PAD_LEFT);
        
        $stmt = $conn->prepare("INSERT INTO boundary_payments (driver_id, operator_id, jeepney_id, amount, payment_method, status, reference_number, notes) VALUES (?, ?, ?, ?, ?, 'Pending', ?, ?)");
        $notes = $data['notes'] ?? '';
        $stmt->bind_param('iiidsss', $data['driver_id'], $data['operator_id'], $data['jeepney_id'], $data['amount'], $data['payment_method'], $referenceNumber, $notes);
        
        if ($stmt->execute()) {
            echo json_encode([
                'success' => true, 
                'receipt' => [
                    'amount' => $data['amount'],
                    'payment_method' => $data['payment_method'],
                    'date' => date('Y-m-d H:i:s'),
                    'reference_number' => $referenceNumber
                ]
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => $stmt->error]);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

// Handle listing boundaries for operator
if (isset($data['action']) && $data['action'] === 'list') {
    if (!isset($data['operator_id'])) {
        echo json_encode(['success' => false, 'message' => 'Missing operator_id.']);
        exit;
    }
    
    try {
        $operator_id = $data['operator_id'];
        error_log("Fetching boundaries for operator_id: " . $operator_id);
        
        // Enhanced query to get more detailed information
        $stmt = $conn->prepare("
            SELECT 
                bp.*,
                u.firstName, 
                u.lastName,
                ja.plate_number,
                ja.body_number,
                ja.route,
                ja.assigned_date
            FROM boundary_payments bp 
            JOIN users u ON bp.driver_id = u.id 
            LEFT JOIN jeepney_assignments ja ON bp.driver_id = ja.driver_id AND ja.status = 'Active'
            WHERE bp.operator_id = ? 
            ORDER BY bp.paid_at DESC
        ");
        $stmt->bind_param('i', $operator_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $boundaries = [];
        
        while ($row = $result->fetch_assoc()) {
            $boundaries[] = [
                'id' => $row['id'],
                'driver' => $row['firstName'] . ' ' . $row['lastName'],
                'driver_id' => $row['driver_id'],
                'jeepney' => $row['plate_number'] ? $row['plate_number'] . ' (' . $row['body_number'] . ')' : 'Assignment #' . $row['jeepney_id'],
                'route' => $row['route'] ?? 'N/A',
                'amount' => $row['amount'],
                'payment_method' => $row['payment_method'],
                'paid_at' => $row['paid_at'],
                'status' => $row['status'],
                'reference_number' => $row['reference_number'],
                'notes' => $row['notes'],
                'assigned_date' => $row['assigned_date']
            ];
        }
        
        error_log("Found " . count($boundaries) . " boundaries for operator_id: " . $operator_id);
        echo json_encode(['success' => true, 'boundaries' => $boundaries]);
    } catch (Exception $e) {
        error_log("Error fetching boundaries: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

// Handle confirming boundary collection
if (isset($data['action']) && $data['action'] === 'confirm') {
    if (!isset($data['id'])) {
        echo json_encode(['success' => false, 'message' => 'Missing boundary payment id.']);
        exit;
    }
    
    try {
        $id = $data['id'];
        $stmt = $conn->prepare("UPDATE boundary_payments SET status = 'Collected' WHERE id = ?");
        $stmt->bind_param('i', $id);
        
        if ($stmt->execute()) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'message' => $stmt->error]);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

// Handle getting boundary statistics
if (isset($data['action']) && $data['action'] === 'stats') {
    if (!isset($data['operator_id'])) {
        echo json_encode(['success' => false, 'message' => 'Missing operator_id.']);
        exit;
    }
    
    try {
        $operator_id = $data['operator_id'];
        
        // Get total pending amount
        $pendingStmt = $conn->prepare("
            SELECT COUNT(*) as count, SUM(amount) as total 
            FROM boundary_payments 
            WHERE operator_id = ? AND status = 'Pending'
        ");
        $pendingStmt->bind_param('i', $operator_id);
        $pendingStmt->execute();
        $pendingResult = $pendingStmt->get_result()->fetch_assoc();
        
        // Get total collected amount
        $collectedStmt = $conn->prepare("
            SELECT COUNT(*) as count, SUM(amount) as total 
            FROM boundary_payments 
            WHERE operator_id = ? AND status = 'Collected'
        ");
        $collectedStmt->bind_param('i', $operator_id);
        $collectedStmt->execute();
        $collectedResult = $collectedStmt->get_result()->fetch_assoc();
        
        echo json_encode([
            'success' => true,
            'stats' => [
                'pending' => [
                    'count' => $pendingResult['count'] ?? 0,
                    'total' => $pendingResult['total'] ?? 0
                ],
                'collected' => [
                    'count' => $collectedResult['count'] ?? 0,
                    'total' => $collectedResult['total'] ?? 0
                ]
            ]
        ]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

// If no valid action is provided
echo json_encode(['success' => false, 'message' => 'Invalid request']);
?> 