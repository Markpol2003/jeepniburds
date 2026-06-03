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
    notes TEXT,
    INDEX idx_operator_id (operator_id),
    INDEX idx_driver_id (driver_id),
    INDEX idx_status (status)
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
    
    // Add indexes for better performance
    $conn->query("CREATE INDEX IF NOT EXISTS idx_operator_id ON boundary_payments (operator_id)");
    $conn->query("CREATE INDEX IF NOT EXISTS idx_driver_id ON boundary_payments (driver_id)");
    $conn->query("CREATE INDEX IF NOT EXISTS idx_status ON boundary_payments (status)");
    
} catch (Exception $e) {
    error_log("Error setting up boundary_payments table: " . $e->getMessage());
}

$data = json_decode(file_get_contents('php://input'), true);

// Handle boundary payment submission
if (isset($data['driver_id'], $data['amount'], $data['payment_method'])) {
    try {
        // Defensive: Always get the latest active assignment for the driver
        $assignmentStmt = $conn->prepare("SELECT operator_id, id as jeepney_id, assigned_date FROM jeepney_assignments WHERE driver_id = ? AND status = 'Active' ORDER BY assigned_date DESC LIMIT 1");
        $assignmentStmt->bind_param('i', $data['driver_id']);
        $assignmentStmt->execute();
        $assignmentResult = $assignmentStmt->get_result();
        $assignment = $assignmentResult->fetch_assoc();
        if (!$assignment) {
            throw new Exception('No active jeepney assignment found for this driver. Please contact your operator.');
        }
        // Auto-inactivate all other assignments for this driver except the most recent
        $fixStmt = $conn->prepare("UPDATE jeepney_assignments SET status = 'Inactive' WHERE driver_id = ? AND id != ? AND status = 'Active'");
        $fixStmt->bind_param('ii', $data['driver_id'], $assignment['jeepney_id']);
        $fixStmt->execute();
        $operator_id = $assignment['operator_id'];
        $jeepney_id = $assignment['jeepney_id'];
        // Debug logging
        error_log("Boundary payment received: " . json_encode($data));
        
        // Validate required fields
        if (empty($data['driver_id']) || empty($operator_id) || empty($data['amount']) || empty($data['payment_method'])) {
            throw new Exception('Missing required fields');
        }
        
        // Validate amount
        if ($data['amount'] <= 0) {
            throw new Exception('Amount must be greater than 0');
        }
        // Use driver's provided reference number, or fallback to generated if not set
        $referenceNumber = isset($data['reference_number']) && trim($data['reference_number']) !== '' ? trim($data['reference_number']) : ('BND-' . date('Ymd') . '-' . str_pad($data['driver_id'], 4, '0', STR_PAD_LEFT) . '-' . date('His'));
        // Get driver and operator details for logging
        $driverStmt = $conn->prepare("SELECT firstName, lastName FROM users WHERE id = ?");
        $driverStmt->bind_param('i', $data['driver_id']);
        $driverStmt->execute();
        $driverResult = $driverStmt->get_result();
        $driver = $driverResult->fetch_assoc();
        $operatorStmt = $conn->prepare("SELECT firstName, lastName FROM users WHERE id = ?");
        $operatorStmt->bind_param('i', $operator_id);
        $operatorStmt->execute();
        $operatorResult = $operatorStmt->get_result();
        $operator = $operatorResult->fetch_assoc();
        $stmt = $conn->prepare("INSERT INTO boundary_payments (driver_id, operator_id, jeepney_id, amount, payment_method, status, reference_number, notes) VALUES (?, ?, ?, ?, ?, 'Pending', ?, ?)");
        $notes = $data['notes'] ?? '';
        $stmt->bind_param('iiidsss', $data['driver_id'], $operator_id, $jeepney_id, $data['amount'], $data['payment_method'], $referenceNumber, $notes);
        if ($stmt->execute()) {
            $paymentId = $conn->insert_id;
            // Extra debug log for inserted payment
            error_log("Inserted boundary_payment: " . json_encode([
                'id' => $paymentId,
                'driver_id' => $data['driver_id'],
                'operator_id' => $operator_id,
                'jeepney_id' => $jeepney_id,
                'amount' => $data['amount'],
                'payment_method' => $data['payment_method'],
                'reference_number' => $referenceNumber,
                'notes' => $notes,
                'status' => 'Pending',
            ]));
            // Log successful payment
            error_log("Boundary payment created successfully: ID=$paymentId, Driver=" . ($driver['firstName'] ?? 'Unknown') . " " . ($driver['lastName'] ?? 'Unknown') . ", Operator=" . ($operator['firstName'] ?? 'Unknown') . " " . ($operator['lastName'] ?? '') . ", Amount=" . $data['amount']);
            // Send notification to operator
            echo json_encode([
                'success' => true, 
                'message' => 'Boundary payment submitted successfully',
                'payment_id' => $paymentId,
                'receipt' => [
                    'amount' => $data['amount'],
                    'payment_method' => $data['payment_method'],
                    'date' => date('Y-m-d H:i:s'),
                    'reference_number' => $referenceNumber,
                    'driver_name' => ($driver['firstName'] ?? '') . ' ' . ($driver['lastName'] ?? ''),
                    'operator_name' => ($operator['firstName'] ?? '') . ' ' . ($operator['lastName'] ?? '')
                ]
            ]);
        } else {
            throw new Exception('Failed to save payment: ' . $stmt->error);
        }
    } catch (Exception $e) {
        error_log("Boundary payment error: " . $e->getMessage());
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
    $operator_id = intval($data['operator_id']);
    // Fetch all pending and collected boundary payments for this operator
    $sql = "
        SELECT bp.id, bp.driver_id, bp.operator_id, bp.jeepney_id, bp.amount, bp.payment_method, bp.reference_number, bp.status, bp.paid_at,
               u.firstName AS driver_first, u.lastName AS driver_last,
               ja.plate_number, ja.body_number, ja.route, ja.assigned_date
        FROM boundary_payments bp
        LEFT JOIN users u ON bp.driver_id = u.id
        LEFT JOIN jeepney_assignments ja ON bp.driver_id = ja.driver_id AND ja.status = 'Active'
        WHERE bp.operator_id = ? AND (bp.status = 'Pending' OR bp.status = 'Collected')
        ORDER BY bp.paid_at DESC
    ";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $operator_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $boundaries = [];
    while ($row = $result->fetch_assoc()) {
        $boundaries[] = [
            'id' => $row['id'],
            'driver_id' => $row['driver_id'],
            'operator_id' => $row['operator_id'],
            'jeepney_id' => $row['jeepney_id'],
            'amount' => $row['amount'],
            'payment_method' => $row['payment_method'],
            'reference_number' => $row['reference_number'],
            'status' => $row['status'],
            'paid_at' => $row['paid_at'],
            'driver' => trim($row['driver_first'] . ' ' . $row['driver_last']),
            'jeepney' => $row['plate_number'],
            'body_number' => $row['body_number'],
            'route' => $row['route'],
            'assigned_date' => $row['assigned_date']
        ];
    }
    echo json_encode(['success' => true, 'boundaries' => $boundaries]);
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
        
        // Get payment details before updating
        $getPaymentStmt = $conn->prepare("
            SELECT bp.*, u.firstName, u.lastName 
            FROM boundary_payments bp 
            JOIN users u ON bp.driver_id = u.id 
            WHERE bp.id = ?
        ");
        $getPaymentStmt->bind_param('i', $id);
        $getPaymentStmt->execute();
        $paymentResult = $getPaymentStmt->get_result();
        $payment = $paymentResult->fetch_assoc();
        
        if (!$payment) {
            throw new Exception('Payment not found');
        }
        
        $stmt = $conn->prepare("UPDATE boundary_payments SET status = 'Collected' WHERE id = ?");
        $stmt->bind_param('i', $id);
        
        if ($stmt->execute()) {
            error_log("Boundary payment confirmed: ID=$id, Driver=" . $payment['firstName'] . " " . $payment['lastName'] . ", Amount=" . $payment['amount']);
            
            // Send confirmation notification
            try {
                $notificationData = [
                    'action' => 'payment_confirmed',
                    'operator_id' => $payment['operator_id'],
                    'payment_id' => $id
                ];
                
                // Make internal notification call
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, 'http://localhost/tebzcopy/notify_operator.php');
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($notificationData));
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
                curl_setopt($ch, CURLOPT_TIMEOUT, 5);
                
                $notificationResponse = curl_exec($ch);
                curl_close($ch);
                
                if ($notificationResponse) {
                    $notificationResult = json_decode($notificationResponse, true);
                    if ($notificationResult && $notificationResult['success']) {
                        error_log("Payment confirmation notification sent successfully for payment ID: $id");
                    }
                }
            } catch (Exception $e) {
                error_log("Failed to send confirmation notification: " . $e->getMessage());
            }
            
            echo json_encode(['success' => true, 'message' => 'Payment confirmed successfully']);
        } else {
            throw new Exception('Failed to confirm payment: ' . $stmt->error);
        }
    } catch (Exception $e) {
        error_log("Error confirming boundary payment: " . $e->getMessage());
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
        error_log("Error fetching boundary stats: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

// If no valid action is provided
echo json_encode(['success' => false, 'message' => 'Invalid request']);
?> 