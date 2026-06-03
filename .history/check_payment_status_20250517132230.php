<?php
session_start();
require_once 'db_config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

$userId = $_SESSION['user_id'];

try {
    // Get the latest payment for the user
    $stmt = $conn->prepare("
        SELECT p.*, u.firstName, u.lastName, ur.receipt_sent
        FROM membership_payments p
        JOIN users u ON p.user_id = u.id
        LEFT JOIN user_receipts ur ON p.id = ur.payment_id
        WHERE p.user_id = ?
        ORDER BY p.created_at DESC
        LIMIT 1
    ");
    
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $payment = $result->fetch_assoc();
        
        // Reset receipt_sent flag after sending it
        if ($payment['receipt_sent']) {
            $updateStmt = $conn->prepare("UPDATE user_receipts SET receipt_sent = 0 WHERE payment_id = ?");
            $updateStmt->bind_param('i', $payment['id']);
            $updateStmt->execute();
        }
        
        echo json_encode([
            'success' => true,
            'receipt' => [
                'receipt_number' => $payment['receipt_number'],
                'amount' => $payment['amount'],
                'method' => $payment['payment_method'],
                'date' => $payment['created_at'],
                'name' => $payment['firstName'] . ' ' . $payment['lastName'],
                'status' => $payment['status'],
                'receipt_sent' => $payment['receipt_sent']
            ]
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'No payment found']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?> 