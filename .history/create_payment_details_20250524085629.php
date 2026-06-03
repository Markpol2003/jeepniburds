<?php
require_once 'db_config.php';

try {
    // Read and execute the SQL
    $sql = file_get_contents('create_payment_details.sql');
    if ($conn->query($sql)) {
        echo "Successfully created payment_details table.";
    } else {
        throw new Exception("Error creating table: " . $conn->error);
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?> 