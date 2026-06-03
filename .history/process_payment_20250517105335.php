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

// Get payment-specific details based on payment method
$paymentDetails = [];
switch ($paymentMethod) {
    case 'gcash':
        $paymentDetails = [
            'gcash_number' => $_POST['gcash_number'] ?? '',
            'gcash_name' => $_POST['gcash_name'] ?? ''
        ];
        break;
    case 'bank':
        $paymentDetails = [
            'bank_name' => $_POST['bank_name'] ?? '',
            'bank_account' => $_POST['bank_account'] ?? '',
            'bank_account_name' => $_POST['bank_account_name'] ?? ''
        ];
        break;
    case 'cash':
        $paymentDetails = [
            'reference_number' => $_POST['reference_number'] ?? ''
        ];
        break;
    default:
        echo json_encode(['success' => false, 'message' => 'Invalid payment method']);
        exit();
}

// Validate required fields
$requiredFields = [];
switch ($paymentMethod) {
    case 'gcash':
        $requiredFields = ['gcash_number', 'gcash_name'];
        break;
    case 'bank':
        $requiredFields = ['bank_name', 'bank_account', 'bank_account_name'];
        break;
    case 'cash':
        $requiredFields = ['reference_number'];
        break;
}

foreach ($requiredFields as $field) {
    if (empty($paymentDetails[$field])) {
        echo json_encode(['success' => false, 'message' => 'Please fill in all required fields']);
        exit();
    }
}

try {
    // Start transaction
    $conn->begin_transaction();

    // Get current date and time in MySQL format
    $currentDateTime = date('Y-m-d H:i:s');
    
    // Insert payment record
    $stmt = $conn->prepare("INSERT INTO membership_payments (user_id, amount, method, reference_number, status, payment_date) VALUES (?, ?, ?, ?, 'Pending', ?)");
    
    // Get reference number based on payment method
    $referenceNumber = $paymentMethod === 'cash' ? $paymentDetails['reference_number'] : 
                      ($paymentMethod === 'gcash' ? $paymentDetails['gcash_number'] : 
                      $paymentDetails['bank_account']);
    
    $stmt->bind_param("idsss", $userId, $amount, $paymentMethod, $referenceNumber, $currentDateTime);
    
    if ($stmt->execute()) {
        // Commit transaction
        $conn->commit();
        echo json_encode([
            'success' => true, 
            'message' => 'Payment submitted successfully. Please wait for treasurer confirmation.'
        ]);
    } else {
        throw new Exception('Failed to submit payment');
    }
} catch (Exception $e) {
    // Rollback transaction on error
    $conn->rollback();
    error_log("Payment Error: " . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'message' => 'An error occurred while processing your payment. Please try again later.'
    ]);
}
?> 