<?php
require_once 'db_config.php';

// Create payment_details table
$sql = "CREATE TABLE IF NOT EXISTS payment_details (
    id INT AUTO_INCREMENT PRIMARY KEY,
    receipt_number VARCHAR(50) NOT NULL,
    gcash_number VARCHAR(20),
    gcash_name VARCHAR(100),
    bank_name VARCHAR(100),
    bank_account VARCHAR(50),
    bank_account_name VARCHAR(100),
    reference_number VARCHAR(50),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (receipt_number) REFERENCES membership_payments(receipt_number) ON DELETE CASCADE
)";

if ($conn->query($sql) === TRUE) {
    echo "Table payment_details created successfully";
} else {
    echo "Error creating table: " . $conn->error;
}

$conn->close();
?> 