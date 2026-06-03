<?php
require_once 'db_config.php';

echo "<h2>Boundary Payment System Debug</h2>";

// Check if boundary_payments table exists
$result = $conn->query("SHOW TABLES LIKE 'boundary_payments'");
if ($result->num_rows > 0) {
    echo "✅ boundary_payments table exists<br>";
} else {
    echo "❌ boundary_payments table does not exist<br>";
}

// Check all boundary payments
echo "<h3>All Boundary Payments:</h3>";
$result = $conn->query("SELECT * FROM boundary_payments ORDER BY paid_at DESC");
if ($result->num_rows > 0) {
    echo "✅ Found " . $result->num_rows . " boundary payments:<br>";
    while ($row = $result->fetch_assoc()) {
        echo "- ID: " . $row['id'] . 
             " | Driver: " . $row['driver_id'] . 
             " | Operator: " . $row['operator_id'] . 
             " | Jeepney ID: " . $row['jeepney_id'] . 
             " | Amount: ₱" . $row['amount'] . 
             " | Method: " . $row['payment_method'] . 
             " | Status: " . $row['status'] . 
             " | Date: " . $row['paid_at'] . "<br>";
    }
} else {
    echo "❌ No boundary payments found<br>";
}

// Check jeepney assignments
echo "<h3>Jeepney Assignments:</h3>";
$result = $conn->query("SELECT * FROM jeepney_assignments WHERE status = 'Active' ORDER BY assigned_date DESC");
if ($result->num_rows > 0) {
    echo "✅ Found " . $result->num_rows . " active assignments:<br>";
    while ($row = $result->fetch_assoc()) {
        echo "- ID: " . $row['id'] . 
             " | Driver: " . $row['driver_id'] . 
             " | Operator: " . $row['operator_id'] . 
             " | Plate: " . $row['plate_number'] . 
             " | Body: " . $row['body_number'] . 
             " | Route: " . $row['route'] . "<br>";
    }
} else {
    echo "❌ No active jeepney assignments found<br>";
}

// Test the query that the operator dashboard uses
echo "<h3>Testing Operator Query:</h3>";
$operator_id = 1; // Test with operator ID 1
$stmt = $conn->prepare("
    SELECT bp.*, u.firstName, u.lastName, ja.plate_number, ja.body_number, ja.route 
    FROM boundary_payments bp 
    JOIN users u ON bp.driver_id = u.id 
    JOIN jeepney_assignments ja ON bp.jeepney_id = ja.id 
    WHERE bp.operator_id = ? 
    ORDER BY bp.paid_at DESC
");
$stmt->bind_param('i', $operator_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    echo "✅ Query returned " . $result->num_rows . " boundaries for operator " . $operator_id . ":<br>";
    while ($row = $result->fetch_assoc()) {
        echo "- Driver: " . $row['firstName'] . " " . $row['lastName'] . 
             " | Jeepney: " . $row['plate_number'] . " (" . $row['body_number'] . ")" .
             " | Route: " . $row['route'] . 
             " | Amount: ₱" . $row['amount'] . 
             " | Method: " . $row['payment_method'] . 
             " | Status: " . $row['status'] . "<br>";
    }
} else {
    echo "❌ Query returned no boundaries for operator " . $operator_id . "<br>";
}

// Check if there are any boundary payments for any operator
echo "<h3>Boundary Payments by Operator:</h3>";
$result = $conn->query("
    SELECT bp.operator_id, COUNT(*) as count 
    FROM boundary_payments bp 
    GROUP BY bp.operator_id
");
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        echo "- Operator " . $row['operator_id'] . ": " . $row['count'] . " payments<br>";
    }
} else {
    echo "❌ No boundary payments found for any operator<br>";
}
?> 