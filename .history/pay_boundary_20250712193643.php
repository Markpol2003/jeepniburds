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
    status VARCHAR(20) DEFAULT 'Pending'
)";

try {
    $conn->query($createBoundaryTable);
    
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
            
            $testStmt = $conn->prepare("INSERT INTO boundary_payments (driver_id, operator_id, jeepney_id, amount, payment_method, status) VALUES (?, ?, ?, ?, ?, 'Pending')");
            $testStmt->bind_param('iiids', $driver['id'], $operator['id'], 1, 500, 'GCash');
            $testStmt->execute();
        }
    }
} catch (Exception $e) {
    // Table might already exist, continue
}

$data = json_decode(file_get_contents('php://input'), true);

// Handle boundary payment submission
if (isset($data['driver_id'], $data['operator_id'], $data['jeepney_id'], $data['amount'], $data['payment_method'])) {
    try {
        $stmt = $conn->prepare("INSERT INTO boundary_payments (driver_id, operator_id, jeepney_id, amount, payment_method, status) VALUES (?, ?, ?, ?, ?, 'Pending')");
        $stmt->bind_param('iiids', $data['driver_id'], $data['operator_id'], $data['jeepney_id'], $data['amount'], $data['payment_method']);
        
        if ($stmt->execute()) {
            echo json_encode([
                'success' => true, 
                'receipt' => [
                    'amount' => $data['amount'],
                    'payment_method' => $data['payment_method'],
                    'date' => date('Y-m-d H:i:s')
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
        error_log("Fetching boundaries for operator_id: " . $operator_id); // Debug log
        error_log("Received data: " . json_encode($data)); // Debug log
        $stmt = $conn->prepare("
            SELECT bp.*, u.firstName, u.lastName 
            FROM boundary_payments bp 
            JOIN users u ON bp.driver_id = u.id 
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
                'jeepney' => 'Assignment #' . $row['jeepney_id'],
                'route' => 'N/A',
                'amount' => $row['amount'],
                'payment_method' => $row['payment_method'],
                'paid_at' => $row['paid_at'],
                'status' => $row['status']
            ];
        }
        
        error_log("Found " . count($boundaries) . " boundaries for operator_id: " . $operator_id); // Debug log
        error_log("Boundaries data: " . json_encode($boundaries)); // Debug log
        echo json_encode(['success' => true, 'boundaries' => $boundaries]);
    } catch (Exception $e) {
        error_log("Error fetching boundaries: " . $e->getMessage()); // Debug log
        error_log("SQL Error: " . $stmt->error); // Debug log
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

// If no valid action is provided
echo json_encode(['success' => false, 'message' => 'Invalid request']);
?> 