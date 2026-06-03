<?php
session_start();
require_once __DIR__ . '/../db_config.php';

header('Content-Type: application/json');

try {
    if (!isset($_SESSION['user_id'])) {
        throw new Exception('User not logged in');
    }

    // Get the latest payment for the current user
    $stmt = $conn->prepare("
        SELECT id 
        FROM membership_payments 
        WHERE user_id = ? 
        AND status = 'Confirmed' 
        ORDER BY payment_date DESC 
        LIMIT 1
    ");
    
    $stmt->bind_param('i', $_SESSION['user_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    $payment = $result->fetch_assoc();

    if ($payment) {
        echo json_encode([
            'success' => true,
            'payment_id' => $payment['id']
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'No confirmed payments found'
        ]);
    }
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?> 