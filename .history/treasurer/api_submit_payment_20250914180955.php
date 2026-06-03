<?php
session_start();
require_once __DIR__ . '/../db_config.php';

// Accept driver and operator roles
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_type'], ['driver', 'operator'])) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit();
}

if (empty($amount) || empty($method)) {
    echo json_encode(['status' => 'error', 'message' => 'All fields are required.']);
    exit();
}


// Get payment details from the form submission
$userId = $_SESSION['user_id'];
$amount = $_POST['amount'];
$method = $_POST['method']; // Payment method (cash, gcash, etc.)
$paymentDate = date('Y-m-d H:i:s');

// Validate fields
if (empty($amount) || empty($method)) {
    echo json_encode(['success' => false, 'message' => 'All fields are required.']);
    exit();
}

// Insert payment record
$stmt = $conn->prepare("INSERT INTO membership_payments (user_id, amount, method, payment_date, status) VALUES (?, ?, ?, ?, 'Pending')");
$stmt->bind_param("iiss", $userId, $amount, $method, $paymentDate);

if ($stmt->execute()) {
    echo json_encode(['status' => 'success', 'message' => 'Payment submitted successfully.']);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Failed to submit payment.']);
}


$stmt->close();
?>
