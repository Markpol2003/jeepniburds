<?php
// Database configuration
$host = 'localhost';  // or '127.0.0.1'
$username = 'root';   // default XAMPP username
$password = '';       // default XAMPP password
$database = 'jeepnigo';   // your database name

// Set default timezone (adjust to your server's location)
// For Philippines, use Asia/Manila
date_default_timezone_set('Asia/Manila');

try {
    // Create connection
    $conn = new mysqli($host, $username, $password, $database);
    
    // Check connection
    if ($conn->connect_error) {
        throw new Exception("Connection failed: " . $conn->connect_error);
    }
    
    // Set MySQL timezone to match PHP timezone
    try {
        $offset = date('P'); // Gets offset like +08:00
        $conn->query("SET time_zone = '$offset'");
    } catch (Exception $e) {
        // If timezone setting fails, continue (some MySQL configurations might not support it)
        // The times will still work, just may not match exactly if server timezone differs
    }
} catch (Exception $e) {
    die("Database connection error: " . $e->getMessage());
}

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
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (payment_id) REFERENCES membership_payments(id) ON DELETE CASCADE,
    UNIQUE KEY unique_payment (payment_id)
)";

try {
    $conn->query($createUserReceiptsTable);
} catch (Exception $e) {
    // Table might already exist or there might be an error, but we'll continue
    // The error will be handled when trying to access the table
}
?>
