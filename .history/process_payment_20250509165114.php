<?php
session_start();
require_once 'db_config.php';

// Set content type to JSON
header('Content-Type: application/json');

// Check if user is logged in and is a driver
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'driver') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

// Check if request method is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit();
}

// Get user ID from session
$userId = $_SESSION['user_id'];

// Get payment details from POST request
$amount = $_POST['amount'] ?? '';
$paymentMethod = $_POST['payment_method'] ?? '';
$referenceNumber = $_POST['reference_number'] ?? '';

// Validate required fields
if (empty($paymentMethod) || empty($referenceNumber)) {
    echo json_encode(['success' => false, 'message' => 'Please fill in all required fields']);
    exit();
}

try {
    // Insert payment record
    $query = "INSERT INTO membership_payments (user_id, amount, payment_method, reference_number, status, created_at) 
              VALUES (?, ?, ?, ?, 'Pending', NOW())";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("idss", $userId, $amount, $paymentMethod, $referenceNumber);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Payment submitted successfully']);
    } else {
        throw new Exception('Failed to submit payment');
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?> 