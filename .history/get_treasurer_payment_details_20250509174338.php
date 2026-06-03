<?php
require_once 'db_config.php';

function getTreasurerPaymentDetails() {
    global $conn;
    
    $query = "SELECT * FROM treasurer_payment_details 
              WHERE treasurer_id IN (SELECT id FROM users WHERE userType = 'treasurer') 
              ORDER BY updated_at DESC LIMIT 1";
    
    $result = $conn->query($query);
    
    if ($result && $result->num_rows > 0) {
        return $result->fetch_assoc();
    }
    
    return null;
} 