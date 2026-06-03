<?php
require_once 'db_config.php';

echo "<h2>Boundary Payment System Test</h2>";

// Test 1: Check if boundary_payments table exists
echo "<h3>1. Checking boundary_payments table</h3>";
$tableCheck = $conn->query("SHOW TABLES LIKE 'boundary_payments'");
if ($tableCheck->num_rows > 0) {
    echo "✅ boundary_payments table exists<br>";
    
    // Check table structure
    $columns = $conn->query("SHOW COLUMNS FROM boundary_payments");
    echo "Table columns:<br>";
    while ($col = $columns->fetch_assoc()) {
        echo "- {$col['Field']} ({$col['Type']})<br>";
    }
} else {
    echo "❌ boundary_payments table does not exist<br>";
}

// Test 2: Check for existing boundary payments
echo "<h3>2. Checking existing boundary payments</h3>";
$payments = $conn->query("SELECT * FROM boundary_payments ORDER BY paid_at DESC LIMIT 5");
if ($payments->num_rows > 0) {
    echo "Found {$payments->num_rows} boundary payments:<br>";
    while ($payment = $payments->fetch_assoc()) {
        echo "- ID: {$payment['id']}, Driver: {$payment['driver_id']}, Operator: {$payment['operator_id']}, Amount: ₱{$payment['amount']}, Status: {$payment['status']}<br>";
    }
} else {
    echo "No boundary payments found<br>";
}

// Test 3: Check jeepney assignments
echo "<h3>3. Checking jeepney assignments</h3>";
$assignments = $conn->query("SELECT ja.*, u.firstName, u.lastName, o.firstName as operator_name 
                            FROM jeepney_assignments ja 
                            JOIN users u ON ja.driver_id = u.id 
                            JOIN users o ON ja.operator_id = o.id 
                            WHERE ja.status = 'Active'");
if ($assignments->num_rows > 0) {
    echo "Found {$assignments->num_rows} active assignments:<br>";
    while ($assignment = $assignments->fetch_assoc()) {
        echo "- Driver: {$assignment['firstName']} {$assignment['lastName']} (ID: {$assignment['driver_id']})<br>";
        echo "  Operator: {$assignment['operator_name']} (ID: {$assignment['operator_id']})<br>";
        echo "  Jeepney: {$assignment['plate_number']} - {$assignment['route']}<br><br>";
    }
} else {
    echo "No active jeepney assignments found<br>";
}

// Test 4: Simulate a boundary payment
echo "<h3>4. Testing boundary payment submission</h3>";
$testDriver = $conn->query("SELECT id FROM users WHERE userType = 'driver' LIMIT 1")->fetch_assoc();
$testOperator = $conn->query("SELECT id FROM users WHERE userType = 'operator' LIMIT 1")->fetch_assoc();

if ($testDriver && $testOperator) {
    echo "Test driver ID: {$testDriver['id']}<br>";
    echo "Test operator ID: {$testOperator['id']}<br>";
    
    // Create test payment
    $testStmt = $conn->prepare("INSERT INTO boundary_payments (driver_id, operator_id, jeepney_id, amount, payment_method, status, reference_number) VALUES (?, ?, ?, ?, ?, 'Pending', ?)");
    $reference = 'TEST-' . date('YmdHis');
    $testStmt->bind_param('iiidss', $testDriver['id'], $testOperator['id'], 1, 500, 'GCash', $reference);
    
    if ($testStmt->execute()) {
        echo "✅ Test boundary payment created successfully<br>";
        echo "Reference: {$reference}<br>";
    } else {
        echo "❌ Failed to create test payment: " . $testStmt->error . "<br>";
    }
} else {
    echo "❌ No driver or operator found for testing<br>";
}

// Test 5: Test boundary listing for operator
echo "<h3>5. Testing boundary listing for operator</h3>";
if ($testOperator) {
    $listStmt = $conn->prepare("
        SELECT 
            bp.*,
            u.firstName, 
            u.lastName,
            ja.plate_number,
            ja.body_number,
            ja.route
        FROM boundary_payments bp 
        JOIN users u ON bp.driver_id = u.id 
        LEFT JOIN jeepney_assignments ja ON bp.driver_id = ja.driver_id AND ja.status = 'Active'
        WHERE bp.operator_id = ? 
        ORDER BY bp.paid_at DESC
    ");
    $listStmt->bind_param('i', $testOperator['id']);
    $listStmt->execute();
    $result = $listStmt->get_result();
    
    if ($result->num_rows > 0) {
        echo "Found {$result->num_rows} boundary payments for operator {$testOperator['id']}:<br>";
        while ($row = $result->fetch_assoc()) {
            echo "- Driver: {$row['firstName']} {$row['lastName']}, Amount: ₱{$row['amount']}, Status: {$row['status']}<br>";
        }
    } else {
        echo "No boundary payments found for operator {$testOperator['id']}<br>";
    }
}

echo "<h3>Test Complete</h3>";
echo "<p><a href='operator_dashboard.php?page=collect_boundaries'>Go to Operator Dashboard</a></p>";
echo "<p><a href='driver_dashboard.php?page=pay_boundary'>Go to Driver Dashboard</a></p>";
?> 