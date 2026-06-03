<?php
require_once 'db_config.php';

echo "<h2>Testing Boundary Payment System</h2>";

// Test 1: Check if boundary_payments table exists
echo "<h3>Test 1: Checking boundary_payments table</h3>";
$result = $conn->query("SHOW TABLES LIKE 'boundary_payments'");
if ($result->num_rows > 0) {
    echo "✅ boundary_payments table exists<br>";
} else {
    echo "❌ boundary_payments table does not exist<br>";
}

// Test 2: Check if jeepney_assignments has operator_id column
echo "<h3>Test 2: Checking jeepney_assignments table structure</h3>";
$result = $conn->query("SHOW COLUMNS FROM jeepney_assignments LIKE 'operator_id'");
if ($result->num_rows > 0) {
    echo "✅ operator_id column exists in jeepney_assignments<br>";
} else {
    echo "❌ operator_id column does not exist in jeepney_assignments<br>";
}

// Test 3: Check existing jeepney assignments
echo "<h3>Test 3: Checking existing jeepney assignments</h3>";
$result = $conn->query("SELECT ja.*, u.firstName, u.lastName, o.firstName as operator_name 
                       FROM jeepney_assignments ja 
                       JOIN users u ON ja.driver_id = u.id 
                       JOIN users o ON ja.operator_id = o.id 
                       WHERE ja.status = 'Active'");
if ($result->num_rows > 0) {
    echo "✅ Found " . $result->num_rows . " active jeepney assignments:<br>";
    while ($row = $result->fetch_assoc()) {
        echo "- Driver: " . $row['firstName'] . " " . $row['lastName'] . 
             " | Operator: " . $row['operator_name'] . 
             " | Jeepney: " . $row['plate_number'] . " (" . $row['body_number'] . ")<br>";
    }
} else {
    echo "❌ No active jeepney assignments found<br>";
}

// Test 4: Check existing boundary payments
echo "<h3>Test 4: Checking existing boundary payments</h3>";
$result = $conn->query("SELECT bp.*, u.firstName, u.lastName, o.firstName as operator_name 
                       FROM boundary_payments bp 
                       JOIN users u ON bp.driver_id = u.id 
                       JOIN users o ON bp.operator_id = o.id 
                       ORDER BY bp.paid_at DESC");
if ($result->num_rows > 0) {
    echo "✅ Found " . $result->num_rows . " boundary payments:<br>";
    while ($row = $result->fetch_assoc()) {
        echo "- Driver: " . $row['firstName'] . " " . $row['lastName'] . 
             " | Operator: " . $row['operator_name'] . 
             " | Amount: ₱" . $row['amount'] . 
             " | Status: " . $row['status'] . "<br>";
    }
} else {
    echo "❌ No boundary payments found<br>";
}

// Test 5: Simulate a boundary payment
echo "<h3>Test 5: Simulating a boundary payment</h3>";
$testData = [
    'driver_id' => 1,
    'operator_id' => 1,
    'jeepney_id' => 1,
    'amount' => 500,
    'payment_method' => 'Cash'
];

$stmt = $conn->prepare("INSERT INTO boundary_payments (driver_id, operator_id, jeepney_id, amount, payment_method, status) VALUES (?, ?, ?, ?, ?, 'Pending')");
$stmt->bind_param('iiids', $testData['driver_id'], $testData['operator_id'], $testData['jeepney_id'], $testData['amount'], $testData['payment_method']);

if ($stmt->execute()) {
    echo "✅ Test boundary payment created successfully<br>";
} else {
    echo "❌ Failed to create test boundary payment: " . $stmt->error . "<br>";
}

echo "<h3>System Status Summary</h3>";
echo "The boundary payment system should now be working correctly. When a driver pays boundaries, it will appear in the operator's 'Collect Boundaries' section.<br>";
echo "<br><strong>To test:</strong><br>";
echo "1. Go to driver dashboard and make a boundary payment<br>";
echo "2. Go to operator dashboard and check the 'Collect Boundaries' section<br>";
echo "3. The payment should appear with driver details, jeepney info, and route<br>";
?> 