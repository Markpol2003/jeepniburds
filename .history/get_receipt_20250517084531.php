<?php
// Prevent any output before JSON response
error_reporting(0);
ini_set('display_errors', 0);

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
    $query = "SELECT p.*, u.firstName, u.lastName 
              FROM membership_payments p 
              JOIN users u ON p.user_id = u.id 
              WHERE p.user_id = ? AND p.status = 'Confirmed' 
              ORDER BY p.payment_date DESC 
              LIMIT 1";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param('i', $_SESSION['user_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $payment = $result->fetch_assoc();
        echo json_encode([
            'success' => true,
            'receipt' => [
                'receipt_number' => $payment['receipt_number'],
                'payment_date' => $payment['payment_date'],
                'amount' => $payment['amount'],
                'method' => $payment['method'],
                'name' => $payment['firstName'] . ' ' . $payment['lastName']
            ]
        ]);
    } else {
        echo json_encode(['success' => true, 'receipt' => null]);
    }
} catch (Exception $e) {
    error_log("Error in get_receipt.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error fetching receipt details']);
}

exit();
?> 