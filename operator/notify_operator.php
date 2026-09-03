<?php
require_once __DIR__ . '/../includes/security.php';
jeepnigo_require_role(['driver', 'manager', 'admin']);
jeepnigo_require_csrf();
require_once __DIR__ . '/../db_config.php';
header('Content-Type: application/json');

// This file handles notifications for operators when new boundary payments are received

$data = json_decode(file_get_contents('php://input'), true);

// Handle new boundary payment notification
if (isset($data['action']) && $data['action'] === 'new_boundary_payment') {
    try {
        $operator_id = $data['operator_id'] ?? null;
        $payment_id = $data['payment_id'] ?? null;
        
        if (!$operator_id || !$payment_id) {
            throw new Exception('Missing required parameters');
        }
        
        // Get payment details
        $stmt = $conn->prepare("
            SELECT bp.*, u.firstName, u.lastName, ja.plate_number, ja.body_number
            FROM boundary_payments bp
            JOIN users u ON bp.driver_id = u.id
            LEFT JOIN jeepney_assignments ja ON bp.driver_id = ja.driver_id AND ja.status = 'Active'
            WHERE bp.id = ? AND bp.operator_id = ?
        ");
        $stmt->bind_param('ii', $payment_id, $operator_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $payment = $result->fetch_assoc();
        
        if (!$payment) {
            throw new Exception('Payment not found');
        }
        
        // Log the notification
        error_log("Operator notification: New boundary payment from {$payment['firstName']} {$payment['lastName']} - ₱{$payment['amount']} via {$payment['payment_method']}");
        
        echo json_encode([
            'success' => true,
            'message' => 'Notification sent successfully',
            'notification' => [
                'title' => 'New Boundary Payment',
                'body' => "{$payment['firstName']} {$payment['lastName']} paid ₱{$payment['amount']} via {$payment['payment_method']}",
                'payment_id' => $payment_id,
                'driver_name' => $payment['firstName'] . ' ' . $payment['lastName'],
                'amount' => $payment['amount'],
                'payment_method' => $payment['payment_method'],
                'jeepney' => $payment['plate_number'] ? $payment['plate_number'] . ' (' . $payment['body_number'] . ')' : 'N/A'
            ]
        ]);
        
    } catch (Exception $e) {
        error_log("Notification error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

// Handle payment confirmation notification
if (isset($data['action']) && $data['action'] === 'payment_confirmed') {
    try {
        $payment_id = $data['payment_id'] ?? null;
        $operator_id = $data['operator_id'] ?? null;
        
        if (!$payment_id || !$operator_id) {
            throw new Exception('Missing required parameters');
        }
        
        // Get payment details
        $stmt = $conn->prepare("
            SELECT bp.*, u.firstName, u.lastName
            FROM boundary_payments bp
            JOIN users u ON bp.driver_id = u.id
            WHERE bp.id = ? AND bp.operator_id = ?
        ");
        $stmt->bind_param('ii', $payment_id, $operator_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $payment = $result->fetch_assoc();
        
        if (!$payment) {
            throw new Exception('Payment not found');
        }
        
        // Log the confirmation
        error_log("Payment confirmed: Boundary payment from {$payment['firstName']} {$payment['lastName']} - ₱{$payment['amount']} confirmed by operator");
        
        echo json_encode([
            'success' => true,
            'message' => 'Payment confirmation logged',
            'confirmation' => [
                'payment_id' => $payment_id,
                'driver_name' => $payment['firstName'] . ' ' . $payment['lastName'],
                'amount' => $payment['amount'],
                'confirmed_at' => date('Y-m-d H:i:s')
            ]
        ]);
        
    } catch (Exception $e) {
        error_log("Confirmation notification error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

// Handle getting notification count for operator
if (isset($data['action']) && $data['action'] === 'get_notification_count') {
    try {
        $operator_id = $data['operator_id'] ?? null;
        
        if (!$operator_id) {
            throw new Exception('Missing operator_id');
        }
        
        // Count pending payments
        $stmt = $conn->prepare("
            SELECT COUNT(*) as count 
            FROM boundary_payments 
            WHERE operator_id = ? AND status = 'Pending'
        ");
        $stmt->bind_param('i', $operator_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $count = $result->fetch_assoc()['count'];
        
        echo json_encode([
            'success' => true,
            'notification_count' => $count,
            'has_notifications' => $count > 0
        ]);
        
    } catch (Exception $e) {
        error_log("Notification count error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

// If no valid action is provided
echo json_encode(['success' => false, 'message' => 'Invalid request']);
?> 
