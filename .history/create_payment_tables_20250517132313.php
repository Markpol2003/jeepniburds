<?php
require_once 'db_config.php';

// Create user_receipts table if it doesn't exist
$createUserReceiptsTable = "CREATE TABLE IF NOT EXISTS user_receipts (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    payment_id INT NOT NULL,
    receipt_number VARCHAR(50) NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    payment_method VARCHAR(50) NOT NULL,
    payment_date DATETIME NOT NULL,
    status VARCHAR(20) NOT NULL,
    receipt_sent TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (payment_id) REFERENCES membership_payments(id) ON DELETE CASCADE,
    UNIQUE KEY unique_payment (payment_id)
)";

try {
    if ($conn->query($createUserReceiptsTable)) {
        echo "User receipts table created successfully\n";
    } else {
        echo "Error creating user receipts table: " . $conn->error . "\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?> 