<?php
session_start();
require_once 'db_config.php';

// Set content type to JSON
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'User not authenticated'
    ]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['payment_method'])) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Invalid request'
    ]);
    exit;
}

try {
    $userId = $_SESSION['user_id'];
    $userFirstName = $_SESSION['user_firstName'] ?? '';
    $userLastName = $_SESSION['user_lastName'] ?? '';
    $userType = $_SESSION['user_type'] ?? '';
    
    // Validate required fields based on payment method
    $paymentMethod = $_POST['payment_method'];
    $requiredFields = [
        'gcash' => ['gcash_number', 'gcash_name'],
        'bank' => ['bank_name', 'bank_account', 'bank_account_name'],
        'cash' => ['reference_number']
    ];

    // Check if all required fields are present
    if (!isset($requiredFields[$paymentMethod])) {
        throw new Exception('Invalid payment method');
    }

    foreach ($requiredFields[$paymentMethod] as $field) {
        if (empty($_POST[$field])) {
            throw new Exception("Missing required field: $field");
        }
    }

    // Generate receipt number
    $receiptNumber = 'TEBZ-' . date('Ymd') . '-' . str_pad($userId, 4, '0', STR_PAD_LEFT);

    // Get reference number based on payment method
    $referenceNumber = '';
    switch ($paymentMethod) {
        case 'gcash':
            $referenceNumber = $_POST['gcash_number'];
            break;
        case 'bank':
            $referenceNumber = $_POST['bank_account'];
            break;
        case 'cash':
            $referenceNumber = $_POST['reference_number'];
            break;
    }

    // Start transaction
    $conn->begin_transaction();

    // Insert payment record with Pending status
    $stmt = $conn->prepare("INSERT INTO membership_payments (user_id, amount, method, reference_number, receipt_number, status, firstName, lastName, userType, payment_date) VALUES (?, ?, ?, ?, ?, 'Pending', ?, ?, ?, NOW())");
    $amount = 1000; // Fixed amount
    $stmt->bind_param("idsssssss", $userId, $amount, $paymentMethod, $referenceNumber, $receiptNumber, $userFirstName, $userLastName, $userType);
    
    if (!$stmt->execute()) {
        throw new Exception('Failed to save payment record');
    }

    // Store payment details for response
    $paymentDetails = [
        'receipt_number' => $receiptNumber,
        'amount' => $amount,
        'method' => $paymentMethod,
        'date' => date('Y-m-d H:i:s'),
        'name' => $userFirstName . ' ' . $userLastName,
        'status' => 'Pending'
    ];

    // Commit transaction
    $conn->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Payment submitted successfully',
        'receipt' => $paymentDetails
    ]);

} catch (Exception $e) {
    // Rollback transaction on error
    if (isset($conn)) {
        $conn->rollback();
    }
    
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?> 