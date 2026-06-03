<?php
require_once 'config/database.php';

try {
    // Read the SQL file
    $sql = file_get_contents('create_payment_details.sql');
    
    // Execute the SQL
    $result = $conn->exec($sql);
    
    if ($result !== false) {
        echo "Table payment_details created successfully";
    } else {
        throw new Exception("Error creating table");
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
