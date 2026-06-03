<?php
require_once 'db_config.php';

// Create payment_details table
$createPaymentDetailsTable = "
CREATE TABLE IF NOT EXISTS payment_details (
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

// Create membership_payments table if it doesn't exist
$createMembershipPaymentsTable = "
CREATE TABLE IF NOT EXISTS membership_payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    method ENUM('gcash', 'bank', 'cash') NOT NULL,
    reference_number VARCHAR(50),
    receipt_number VARCHAR(50) NOT NULL UNIQUE,
    payment_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    status ENUM('Pending', 'Confirmed', 'Rejected') DEFAULT 'Pending',
    firstName VARCHAR(100),
    lastName VARCHAR(100),
    userType VARCHAR(50),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
)";

try {
    // Drop existing tables if they exist
    $conn->query("DROP TABLE IF EXISTS payment_details");
    $conn->query("DROP TABLE IF EXISTS membership_payments");

    // Execute the table creation queries
    if ($conn->query($createMembershipPaymentsTable)) {
        echo "Membership payments table created successfully<br>";
    } else {
        throw new Exception("Error creating membership_payments table: " . $conn->error);
    }

    if ($conn->query($createPaymentDetailsTable)) {
        echo "Payment details table created successfully<br>";
    } else {
        throw new Exception("Error creating payment_details table: " . $conn->error);
    }

    echo "All tables created successfully!";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}

$conn->close();
?> 