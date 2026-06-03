<?php
session_start();
require_once __DIR__ . '/../db_config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'treasurer') {
    header("Location: ../shared/index.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $treasurerId = $_SESSION['user_id'];
    
    // Sanitize and validate input
    $gcashNumber = filter_input(INPUT_POST, 'gcash_number', FILTER_SANITIZE_STRING);
    $gcashName = filter_input(INPUT_POST, 'gcash_name', FILTER_SANITIZE_STRING);
    $bankName = filter_input(INPUT_POST, 'bank_name', FILTER_SANITIZE_STRING);
    $bankAccount = filter_input(INPUT_POST, 'bank_account', FILTER_SANITIZE_STRING);
    $bankAccountName = filter_input(INPUT_POST, 'bank_account_name', FILTER_SANITIZE_STRING);
    $officeAddress = filter_input(INPUT_POST, 'office_address', FILTER_SANITIZE_STRING);

    // Validate GCash number format
    if (!preg_match('/^[0-9]{11}$/', $gcashNumber)) {
        $_SESSION['error'] = "Invalid GCash number format. Please enter 11 digits.";
        header("Location: treasurer_dashboard.php?page=payment_details");
        exit();
    }

    try {
        // Start transaction
        $conn->begin_transaction();

        // Check if record exists
        $checkQuery = "SELECT id FROM treasurer_payment_details WHERE treasurer_id = ?";
        $checkStmt = $conn->prepare($checkQuery);
        $checkStmt->bind_param("i", $treasurerId);
        $checkStmt->execute();
        $result = $checkStmt->get_result();

        if ($result->num_rows > 0) {
            // Update existing record
            $updateQuery = "UPDATE treasurer_payment_details SET 
                gcash_number = ?, 
                gcash_name = ?, 
                bank_name = ?, 
                bank_account = ?, 
                bank_account_name = ?, 
                office_address = ?,
                updated_at = CURRENT_TIMESTAMP
                WHERE treasurer_id = ?";
            
            $stmt = $conn->prepare($updateQuery);
            $stmt->bind_param("ssssssi", 
                $gcashNumber, 
                $gcashName, 
                $bankName, 
                $bankAccount, 
                $bankAccountName, 
                $officeAddress,
                $treasurerId
            );
        } else {
            // Insert new record
            $insertQuery = "INSERT INTO treasurer_payment_details 
                (treasurer_id, gcash_number, gcash_name, bank_name, bank_account, bank_account_name, office_address) 
                VALUES (?, ?, ?, ?, ?, ?, ?)";
            
            $stmt = $conn->prepare($insertQuery);
            $stmt->bind_param("issssss", 
                $treasurerId,
                $gcashNumber, 
                $gcashName, 
                $bankName, 
                $bankAccount, 
                $bankAccountName, 
                $officeAddress
            );
        }

        if ($stmt->execute()) {
            // Update payment instructions in driver and operator dashboards
            $paymentInstructions = [
                'gcash' => [
                    'number' => $gcashNumber,
                    'name' => $gcashName
                ],
                'bank' => [
                    'name' => $bankName,
                    'account' => $bankAccount,
                    'account_name' => $bankAccountName
                ],
                'office' => $officeAddress
            ];

            // Store payment instructions in a JSON file
            $instructionsFile = 'payment_instructions.json';
            file_put_contents($instructionsFile, json_encode($paymentInstructions, JSON_PRETTY_PRINT));

            // Commit transaction
            $conn->commit();
            $_SESSION['message'] = "Payment details updated successfully!";
        } else {
            throw new Exception("Failed to update payment details.");
        }

    } catch (Exception $e) {
        // Rollback transaction on error
        $conn->rollback();
        $_SESSION['error'] = "Error: " . $e->getMessage();
    }

    header("Location: treasurer_dashboard.php?page=payment_details");
    exit();
} 