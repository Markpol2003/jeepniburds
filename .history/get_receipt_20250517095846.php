<?php
// Prevent any output before JSON response
error_reporting(0);
ini_set('display_errors', 0);

session_start();
require_once 'db_config.php';

// Set content type to JSON
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'User not authenticated']);
    exit();
}

try {
    $userId = $_SESSION['user_id'];
    
    // Get the latest receipt for the user
    $stmt = $conn->prepare("
        SELECT r.*, p.reference_number, p.proof_of_payment
        FROM user_receipts r
        JOIN membership_payments p ON r.payment_id = p.id
        WHERE r.user_id = ? AND r.status = 'Confirmed'
        ORDER BY r.created_at DESC
        LIMIT 1
    ");
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $receipt = $result->fetch_assoc();
        echo json_encode([
            'success' => true,
            'receipt' => [
                'receipt_number' => $receipt['receipt_number'],
                'amount' => $receipt['amount'],
                'method' => $receipt['payment_method'],
                'date' => $receipt['payment_date'],
                'name' => $_SESSION['user_firstName'] . ' ' . $_SESSION['user_lastName'],
                'reference_number' => $receipt['reference_number'],
                'proof_of_payment' => $receipt['proof_of_payment']
            ]
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'No receipt found']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

exit();
?> 