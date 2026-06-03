<?php
// Prevent any output before JSON response
error_reporting(E_ALL);
ini_set('display_errors', 0);
ob_start(); // Start output buffering

session_start();
require_once 'db_config.php';

// Set JSON header
header('Content-Type: application/json');

// Basic response function
function jsonResponse($success, $message, $data = null) {
    ob_clean(); // Clear any output buffer
    $response = [
        'success' => $success,
        'message' => $message
    ];
    if ($data !== null) {
        $response['data'] = $data;
    }
    echo json_encode($response);
    exit;
}

// Check authentication
if (!isset($_SESSION['user_id'])) {
    jsonResponse(false, 'User not authenticated');
}

// Check request method
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['payment_method'])) {
    jsonResponse(false, 'Invalid request');
}

try {
    $userId = $_SESSION['user_id'];
    $userFirstName = $_SESSION['user_firstName'] ?? '';
    $userLastName = $_SESSION['user_lastName'] ?? '';
    $userType = $_SESSION['user_type'] ?? '';
    
    $paymentMethod = $_POST['payment_method'];
    
    // Simple validation
    if (!in_array($paymentMethod, ['gcash', 'bank', 'cash'])) {
        jsonResponse(false, 'Invalid payment method');
    }
    
    // Get reference number
    $referenceNumber = '';
    switch ($paymentMethod) {
        case 'gcash':
            if (empty($_POST['gcash_number'])) {
                jsonResponse(false, 'GCash number is required');
            }
            $referenceNumber = $_POST['gcash_number'];
            break;
        case 'bank':
            if (empty($_POST['bank_account'])) {
                jsonResponse(false, 'Bank account is required');
            }
            $referenceNumber = $_POST['bank_account'];
            break;
        case 'cash':
            if (empty($_POST['reference_number'])) {
                jsonResponse(false, 'Reference number is required');
            }
            $referenceNumber = $_POST['reference_number'];
            break;
    }

    // Generate receipt number
    $receiptNumber = 'TEBZ-' . date('Ymd') . '-' . str_pad($userId, 4, '0', STR_PAD_LEFT);
    $amount = 1000; // Fixed amount

    // Insert payment
    $stmt = $conn->prepare("INSERT INTO membership_payments (user_id, amount, method, reference_number, receipt_number, status, firstName, lastName, userType, payment_date) VALUES (?, ?, ?, ?, ?, 'Pending', ?, ?, ?, NOW())");
    $stmt->bind_param("idsssssss", $userId, $amount, $paymentMethod, $referenceNumber, $receiptNumber, $userFirstName, $userLastName, $userType);
    
    if (!$stmt->execute()) {
        jsonResponse(false, 'Failed to save payment record');
    }

    // Success response
    $paymentDetails = [
        'receipt_number' => $receiptNumber,
        'amount' => $amount,
        'method' => $paymentMethod,
        'date' => date('Y-m-d H:i:s'),
        'name' => $userFirstName . ' ' . $userLastName,
        'status' => 'Pending'
    ];

    jsonResponse(true, 'Payment submitted successfully', $paymentDetails);

} catch (Exception $e) {
    jsonResponse(false, $e->getMessage());
}
?> 