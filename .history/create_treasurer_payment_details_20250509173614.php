<?php
require_once 'db_config.php';

try {
    // Create the table
    $sql = "CREATE TABLE IF NOT EXISTS treasurer_payment_details (
        id INT PRIMARY KEY AUTO_INCREMENT,
        treasurer_id INT NOT NULL,
        gcash_number VARCHAR(11) NOT NULL,
        gcash_name VARCHAR(100) NOT NULL,
        bank_name VARCHAR(100),
        bank_account VARCHAR(50),
        bank_account_name VARCHAR(100),
        office_address TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (treasurer_id) REFERENCES users(id) ON DELETE CASCADE
    )";

    if ($conn->query($sql)) {
        echo "Table 'treasurer_payment_details' created successfully!";
    } else {
        throw new Exception("Error creating table: " . $conn->error);
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
} 