<?php
session_start();
require_once __DIR__ . '/../db_config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

$userId = $_SESSION['user_id'];

try {
    // Get the latest payment for the user
    $stmt = $conn->prepare("
        SELECT p.*, u.firstName, u.lastName, u.userType, pd.reference_number, pd.account_name,
               (SELECT COUNT(*) FROM jeepney_assignments WHERE driver_id = u.id AND status = 'Active') as has_assignment
        FROM membership_payments p
        JOIN users u ON p.user_id = u.id
        LEFT JOIN payment_details pd ON p.receipt_number = pd.receipt_number
        WHERE p.user_id = ?
        ORDER BY p.payment_date DESC
        LIMIT 1
    ");
    
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $payment = $result->fetch_assoc();
        
        echo json_encode([
            'success' => true,
            'has_payment' => true,
            'payment_status' => $payment['status'],
            'receipt' => [
                'receipt_number' => $payment['receipt_number'],
                'amount' => $payment['amount'],
                'method' => $payment['method'],
                'date' => $payment['payment_date'],
                'name' => $payment['firstName'] . ' ' . $payment['lastName'],
                'status' => $payment['status'],
                'user_type' => $payment['userType'],
                'reference_number' => $payment['reference_number'],
                'account_name' => $payment['account_name'],
                'needs_assignment' => $payment['userType'] === 'driver' && $payment['status'] === 'Confirmed' && !$payment['has_assignment']
            ]
        ]);
    } else {
        echo json_encode([
            'success' => true,
            'has_payment' => false,
            'message' => 'No payment found'
        ]);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?> 