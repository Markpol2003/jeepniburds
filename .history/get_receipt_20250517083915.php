<?php
session_start();
require_once 'db_config.php';

// Set content type to JSON
header('Content-Type: application/json');

// Check if user is logged in and is either a driver or operator
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_type'], ['driver', 'operator'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

// Get user ID from session
$userId = $_SESSION['user_id'];

try {
    // Get the latest confirmed payment for the user
    $query = "SELECT mp.*, u.email 
              FROM membership_payments mp 
              JOIN users u ON mp.user_id = u.id 
              WHERE mp.user_id = ? AND mp.status = 'Confirmed' 
              ORDER BY mp.payment_date DESC 
              LIMIT 1";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $payment = $result->fetch_assoc();
        
        // Format the receipt data
        $receipt = [
            'receipt_number' => $payment['receipt_number'],
            'payment_date' => $payment['payment_date'],
            'amount' => $payment['amount'],
            'method' => $payment['method'],
            'status' => $payment['status']
        ];
        
        echo json_encode([
            'success' => true,
            'receipt' => $receipt
        ]);
    } else {
        echo json_encode([
            'success' => true,
            'receipt' => null
        ]);
    }
} catch (Exception $e) {
    error_log("Receipt Error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred while fetching receipt details.'
    ]);
}
?> 