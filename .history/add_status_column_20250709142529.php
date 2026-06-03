<?php
require_once 'db_config.php';

// Add status column to fare_payments table if it doesn't exist
$checkColumnQuery = "SHOW COLUMNS FROM fare_payments LIKE 'status'";
$result = $conn->query($checkColumnQuery);

if ($result->num_rows === 0) {
    // Status column doesn't exist, add it
    $addColumnQuery = "ALTER TABLE fare_payments ADD COLUMN status VARCHAR(20) DEFAULT 'Paid' AFTER receipt_number";
    if ($conn->query($addColumnQuery)) {
        echo "Status column added successfully to fare_payments table.<br>";
        
        // Update existing records to have 'Paid' status
        $updateQuery = "UPDATE fare_payments SET status = 'Paid' WHERE status IS NULL";
        if ($conn->query($updateQuery)) {
            echo "Existing fare payments updated with 'Paid' status.<br>";
        } else {
            echo "Error updating existing records: " . $conn->error . "<br>";
        }
    } else {
        echo "Error adding status column: " . $conn->error . "<br>";
    }
} else {
    echo "Status column already exists in fare_payments table.<br>";
}

// Check if paid_at column exists
$checkPaidAtQuery = "SHOW COLUMNS FROM fare_payments LIKE 'paid_at'";
$paidAtResult = $conn->query($checkPaidAtQuery);

if ($paidAtResult->num_rows === 0) {
    // paid_at column doesn't exist, add it
    $addPaidAtQuery = "ALTER TABLE fare_payments ADD COLUMN paid_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP AFTER receipt_number";
    if ($conn->query($addPaidAtQuery)) {
        echo "paid_at column added successfully to fare_payments table.<br>";
    } else {
        echo "Error adding paid_at column: " . $conn->error . "<br>";
    }
} else {
    echo "paid_at column already exists in fare_payments table.<br>";
}

echo "<br>Database structure check completed. You can now delete this file.";
?> 