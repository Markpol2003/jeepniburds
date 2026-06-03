<?php
require_once 'db_config.php';

echo "<h2>Debug Boundary Payment System</h2>";

// Check current operator ID
session_start();
$operator_id = $_SESSION['user_id'] ?? 'Not logged in';
echo "<h3>Current Operator ID: $operator_id</h3>";

// Check if there are any boundary payments
echo "<h3>All Boundary Payments:</h3>";
$result = $conn->query("SELECT * FROM boundary_payments ORDER BY paid_at DESC");
if ($result->num_rows > 0) {
    echo "✅ Found " . $result->num_rows . " boundary payments:<br>";
    while ($row = $result->fetch_assoc()) {
        echo "- ID: " . $row['id'] . 
             " | Driver: " . $row['driver_id'] . 
             " | Operator: " . $row['operator_id'] . 
             " | Jeepney: " . $row['jeepney_id'] . 
             " | Amount: ₱" . $row['amount'] . 
             " | Status: " . $row['status'] . "<br>";
    }
} else {
    echo "❌ No boundary payments found in database<br>";
}

// Check jeepney assignments
echo "<h3>Jeepney Assignments:</h3>";
$result = $conn->query("SELECT * FROM jeepney_assignments WHERE status = 'Active'");
if ($result->num_rows > 0) {
    echo "✅ Found " . $result->num_rows . " active jeepney assignments:<br>";
    while ($row = $result->fetch_assoc()) {
        echo "- ID: " . $row['id'] . 
             " | Driver: " . $row['driver_id'] . 
             " | Operator: " . $row['operator_id'] . 
             " | Jeepney: " . $row['plate_number'] . " (" . $row['body_number'] . ")<br>";
    }
} else {
    echo "❌ No active jeepney assignments found<br>";
}

// Test the exact query that the operator dashboard uses
echo "<h3>Testing Operator Dashboard Query:</h3>";
$operator_id = $_SESSION['user_id'] ?? 1; // Use session or default to 1
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
    echo "✅ Query returned " . $result->num_rows . " boundary payments for operator $operator_id:<br>";
    while ($row = $result->fetch_assoc()) {
        echo "- Driver: " . $row['firstName'] . " " . $row['lastName'] . 
             " | Jeepney: " . $row['plate_number'] . " (" . $row['body_number'] . ")" .
             " | Route: " . $row['route'] . 
             " | Amount: ₱" . $row['amount'] . 
             " | Status: " . $row['status'] . "<br>";
    }
} else {
    echo "❌ Query returned 0 boundary payments for operator $operator_id<br>";
    
    // Check if there are boundary payments for other operators
    $otherResult = $conn->query("SELECT DISTINCT operator_id FROM boundary_payments");
    if ($otherResult->num_rows > 0) {
        echo "Boundary payments exist for other operators: ";
        while ($row = $otherResult->fetch_assoc()) {
            echo $row['operator_id'] . " ";
        }
        echo "<br>";
    }
}

// Test creating a boundary payment
echo "<h3>Testing Boundary Payment Creation:</h3>";
$testData = [
    'driver_id' => 1,
    'operator_id' => $operator_id,
    'jeepney_id' => 1,
    'amount' => 500,
    'payment_method' => 'Cash'
];

$stmt = $conn->prepare("INSERT INTO boundary_payments (driver_id, operator_id, jeepney_id, amount, payment_method, status) VALUES (?, ?, ?, ?, ?, 'Pending')");
$stmt->bind_param('iiids', $testData['driver_id'], $testData['operator_id'], $testData['jeepney_id'], $testData['amount'], $testData['payment_method']);

if ($stmt->execute()) {
    echo "✅ Test boundary payment created successfully<br>";
    echo "Now refresh the operator dashboard to see if it appears<br>";
} else {
    echo "❌ Failed to create test boundary payment: " . $stmt->error . "<br>";
}

echo "<h3>Next Steps:</h3>";
echo "1. Go to driver dashboard and make a boundary payment<br>";
echo "2. Go to operator dashboard and check 'Collect Boundaries'<br>";
echo "3. If still not working, check browser console for errors<br>";
?> 