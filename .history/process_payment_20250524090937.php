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
    $amount = 1000; // Fixed amount

    // Start transaction
    $conn->begin_transaction();

    try {
        // Insert payment
        $stmt = $conn->prepare("INSERT INTO membership_payments (user_id, amount, payment_method, receipt_number, status, created_at) VALUES (?, ?, ?, ?, 'Pending', NOW())");
        if (!$stmt) {
            throw new Exception('Failed to prepare payment statement: ' . $conn->error);
        }
        
        $stmt->bind_param("idss", $userId, $amount, $paymentMethod, $receiptNumber);
        
        if (!$stmt->execute()) {
            throw new Exception('Failed to save payment record');
        }

        // Store payment details based on method
        $paymentDetails = [
            'receipt_number' => $receiptNumber,
            'amount' => $amount,
            'method' => $paymentMethod,
            'date' => date('Y-m-d H:i:s'),
            'name' => $userFirstName . ' ' . $userLastName,
            'status' => 'Pending'
        ];

        // Get treasurer_id (you might want to modify this based on your logic)
        $treasurerQuery = "SELECT id FROM users WHERE user_type = 'treasurer' LIMIT 1";
        $treasurerResult = $conn->query($treasurerQuery);
        $treasurer = $treasurerResult->fetch_assoc();
        $treasurerId = $treasurer['id'];

        switch ($paymentMethod) {
            case 'gcash':
                $stmt = $conn->prepare("INSERT INTO treasurer_payment_details (treasurer_id, gcash_number, gcash_name) VALUES (?, ?, ?)");
                $stmt->bind_param("iss", $treasurerId, $_POST['gcash_number'], $_POST['gcash_name']);
                break;
            case 'bank':
                $stmt = $conn->prepare("INSERT INTO treasurer_payment_details (treasurer_id, bank_name, bank_account, bank_account_name) VALUES (?, ?, ?, ?)");
                $stmt->bind_param("isss", $treasurerId, $_POST['bank_name'], $_POST['bank_account'], $_POST['bank_account_name']);
                break;
            case 'cash':
                $stmt = $conn->prepare("INSERT INTO treasurer_payment_details (treasurer_id, office_address) VALUES (?, ?)");
                $stmt->bind_param("is", $treasurerId, $_POST['reference_number']);
                break;
        }

        if (!$stmt->execute()) {
            throw new Exception('Failed to save payment details');
        }

        // Commit transaction
        $conn->commit();

        jsonResponse(true, 'Payment submitted successfully', $paymentDetails);

    } catch (Exception $e) {
        // Rollback transaction on error
        $conn->rollback();
        throw $e;
    }

} catch (Exception $e) {
    jsonResponse(false, 'Error: ' . $e->getMessage());
}

$conn->close();
