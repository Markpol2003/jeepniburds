<?php
require_once __DIR__ . '/../includes/security.php';
jeepnigo_require_role(['driver']);
jeepnigo_require_csrf();
require_once __DIR__ . '/../db_config.php';
header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);
$action = $data['action'] ?? '';

if ($action === 'payment_confirmed') {
    $receiptNumber = $data['receipt_number'] ?? '';
    $driverId = $data['driver_id'] ?? '';
    $driverName = $data['driver_name'] ?? '';
    
    // Get passenger details from the fare payment
    $stmt = $conn->prepare("SELECT fp.passenger_id, fp.amount, fp.payment_method, u.firstName, u.lastName 
                           FROM fare_payments fp 
                           JOIN users u ON fp.passenger_id = u.id 
                           WHERE fp.receipt_number = ?");
    $stmt->bind_param('s', $receiptNumber);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $fareData = $result->fetch_assoc();
        $passengerId = $fareData['passenger_id'];
        $passengerName = $fareData['firstName'] . ' ' . $fareData['lastName'];
        $amount = $fareData['amount'];
        $paymentMethod = $fareData['payment_method'];
        
        // Store notification in database
        $notificationStmt = $conn->prepare("INSERT INTO passenger_notifications 
            (passenger_id, driver_id, receipt_number, message, notification_type, created_at) 
            VALUES (?, ?, ?, ?, 'payment_confirmed', NOW())");
        
        $message = "Your fare payment of ₱{$amount} via {$paymentMethod} has been confirmed by driver {$driverName}.";
        $notificationStmt->bind_param('iiss', $passengerId, $driverId, $receiptNumber, $message);
        
        if ($notificationStmt->execute()) {
            echo json_encode([
                'success' => true,
                'message' => 'Passenger notification stored successfully',
                'passenger_id' => $passengerId,
                'passenger_name' => $passengerName
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'Failed to store notification'
            ]);
        }
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Fare payment not found'
        ]);
    }
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid action'
    ]);
}
?> 
