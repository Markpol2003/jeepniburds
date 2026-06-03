<?php
session_start();
require_once 'db_config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'driver') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit();
}

$userId = $_SESSION['user_id'];
$amount = $_POST['amount'] ?? 0;
$paymentMethod = $_POST['payment_method'] ?? '';
$referenceNumber = $_POST['reference_number'] ?? '';

if (empty($paymentMethod) || empty($referenceNumber)) {
    echo json_encode(['success' => false, 'message' => 'Please fill in all required fields']);
    exit();
}

try {
    // Insert payment record
    $stmt = $conn->prepare("INSERT INTO membership_payments (user_id, amount, payment_method, reference_number, status, created_at) VALUES (?, ?, ?, ?, 'Pending', NOW())");
    $stmt->bind_param("idss", $userId, $amount, $paymentMethod, $referenceNumber);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Payment submitted successfully']);
    } else {
        throw new Exception('Failed to save payment record');
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error processing payment: ' . $e->getMessage()]);
}
?> 