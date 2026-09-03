<?php
require_once __DIR__ . '/../includes/security.php';
jeepnigo_require_role(['treasurer']);
jeepnigo_require_csrf();
require_once __DIR__ . '/../db_config.php';

try {
    // Read and execute the SQL update
    $sql = file_get_contents('update_payment_method_column.sql');
    if ($conn->query($sql)) {
        echo "Successfully updated payment_method column name.";
    } else {
        throw new Exception("Error updating column: " . $conn->error);
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?> 
