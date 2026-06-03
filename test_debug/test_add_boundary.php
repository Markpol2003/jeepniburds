<?php
require_once 'db_config.php';

echo "<h2>Test Add Boundary Payment</h2>";

// Get first driver and operator
$driverResult = $conn->query("SELECT id FROM users WHERE userType = 'driver' LIMIT 1");
$operatorResult = $conn->query("SELECT id FROM users WHERE userType = 'operator' LIMIT 1");

if ($driverResult->num_rows > 0 && $operatorResult->num_rows > 0) {
    $driver = $driverResult->fetch_assoc();
    $operator = $operatorResult->fetch_assoc();
    
    $driver_id = $driver['id'];
    $operator_id = $operator['id'];
    
    echo "Driver ID: " . $driver_id . "<br>";
    echo "Operator ID: " . $operator_id . "<br>";
    
    // Get first jeepney assignment
    $assignmentResult = $conn->query("SELECT id FROM jeepney_assignments WHERE status = 'Active' LIMIT 1");
    
    if ($assignmentResult->num_rows > 0) {
        $assignment = $assignmentResult->fetch_assoc();
        $jeepney_id = $assignment['id'];
        
        echo "Jeepney Assignment ID: " . $jeepney_id . "<br>";
        
        // Add test boundary payment
        $stmt = $conn->prepare("INSERT INTO boundary_payments (driver_id, operator_id, jeepney_id, amount, payment_method, status) VALUES (?, ?, ?, ?, ?, 'Pending')");
        $amount = 500;
        $payment_method = 'GCash';
        $stmt->bind_param('iiids', $driver_id, $operator_id, $jeepney_id, $amount, $payment_method);
        
        if ($stmt->execute()) {
            echo "<br>✅ Test boundary payment added successfully!<br>";
            echo "Amount: ₱" . $amount . "<br>";
            echo "Method: " . $payment_method . "<br>";
            echo "Date: " . date('Y-m-d H:i:s') . "<br>";
        } else {
            echo "<br>❌ Failed to add test boundary payment: " . $stmt->error . "<br>";
        }
    } else {
        echo "<br>❌ No active jeepney assignments found<br>";
    }
} else {
    echo "<br>❌ No drivers or operators found<br>";
}

echo "<br><a href='check_boundary_payments.php'>Check Boundary Payments</a>";
?> 