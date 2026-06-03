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
        $response['receipt'] = $data; // Changed from 'data' to 'receipt' to match frontend
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
    
    // Validate required fields based on payment method
    switch ($paymentMethod) {
        case 'gcash':
            if (empty($_POST['gcash_number']) || empty($_POST['gcash_name'])) {
                jsonResponse(false, 'GCash number and name are required');
            }
            $referenceNumber = $_POST['gcash_number'];
            $accountName = $_POST['gcash_name'];
            break;
        case 'bank':
            if (empty($_POST['bank_account']) || empty($_POST['bank_name']) || empty($_POST['bank_account_name'])) {
                jsonResponse(false, 'All bank details are required');
            }
            $referenceNumber = $_POST['bank_account'];
            $accountName = $_POST['bank_account_name'];
            break;
        case 'cash':
            if (empty($_POST['reference_number'])) {
                jsonResponse(false, 'Reference number is required');
            }
            $referenceNumber = $_POST['reference_number'];
            $accountName = $userFirstName . ' ' . $userLastName;
            break;
    }

    // Generate receipt number
    $receiptNumber = 'TEBZ-' . date('Ymd') . '-' . str_pad($userId, 4, '0', STR_PAD_LEFT);
    $amount = 1000; // Fixed amount

    // Start transaction
    $conn->begin_transaction();

    try {
        // Insert payment
        $stmt = $conn->prepare("INSERT INTO membership_payments (user_id, amount, method, receipt_number, status) VALUES (?, ?, ?, ?, 'Pending')");
        if (!$stmt) {
            throw new Exception('Failed to prepare payment statement: ' . $conn->error);
        }
        
        $stmt->bind_param("idss", $userId, $amount, $paymentMethod, $receiptNumber);
        
        if (!$stmt->execute()) {
            throw new Exception('Failed to save payment record: ' . $stmt->error);
        }

        // Insert payment details
        $stmt = $conn->prepare("INSERT INTO payment_details (receipt_number, reference_number, account_name) VALUES (?, ?, ?)");
        if (!$stmt) {
            throw new Exception('Failed to prepare details statement: ' . $conn->error);
        }
        
        $stmt->bind_param("sss", $receiptNumber, $referenceNumber, $accountName);
        
        if (!$stmt->execute()) {
            throw new Exception('Failed to save payment details: ' . $stmt->error);
        }

        // Commit transaction
        $conn->commit();

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
        // Rollback transaction on error
        $conn->rollback();
        throw $e;
    }

} catch (Exception $e) {
    jsonResponse(false, 'Error: ' . $e->getMessage());
}
