<?php
require_once 'db_config.php';

echo "<h2>🔍 Boundary Payment Debug</h2>";

// Check 1: Database tables
echo "<h3>1. Database Tables Check</h3>";
$tables = ['boundary_payments', 'jeepney_assignments', 'users'];
foreach ($tables as $table) {
    $result = $conn->query("SHOW TABLES LIKE '$table'");
    echo $result->num_rows > 0 ? "✅ $table exists" : "❌ $table missing";
    echo "<br>";
}

// Check 2: Current boundary payments
echo "<h3>2. Current Boundary Payments</h3>";
$payments = $conn->query("SELECT * FROM boundary_payments ORDER BY paid_at DESC");
echo "Total boundary payments: " . $payments->num_rows . "<br>";
if ($payments->num_rows > 0) {
    while ($payment = $payments->fetch_assoc()) {
        echo "- ID: {$payment['id']}, Driver: {$payment['driver_id']}, Operator: {$payment['operator_id']}, Amount: ₱{$payment['amount']}, Status: {$payment['status']}<br>";
    }
}

// Check 3: Jeepney assignments
echo "<h3>3. Jeepney Assignments</h3>";
$assignments = $conn->query("SELECT ja.*, u.firstName, u.lastName, o.firstName as operator_name 
                            FROM jeepney_assignments ja 
                            JOIN users u ON ja.driver_id = u.id 
                            JOIN users o ON ja.operator_id = o.id 
                            WHERE ja.status = 'Active'");
echo "Active assignments: " . $assignments->num_rows . "<br>";
if ($assignments->num_rows > 0) {
    while ($assignment = $assignments->fetch_assoc()) {
        echo "- Driver: {$assignment['firstName']} {$assignment['lastName']} (ID: {$assignment['driver_id']})<br>";
        echo "  Operator: {$assignment['operator_name']} (ID: {$assignment['operator_id']})<br>";
        echo "  Jeepney: {$assignment['plate_number']} - {$assignment['route']}<br><br>";
    }
}

// Check 4: Test API directly
echo "<h3>4. Testing API Directly</h3>";
$operators = $conn->query("SELECT id FROM users WHERE userType = 'operator' LIMIT 1");
if ($operators->num_rows > 0) {
    $operator = $operators->fetch_assoc();
    $operator_id = $operator['id'];
    
    echo "Testing API for operator ID: $operator_id<br>";
    
    // Test the exact query used in pay_boundary.php
    $testQuery = "
        SELECT 
            bp.*,
            u.firstName, 
            u.lastName,
            ja.plate_number,
            ja.body_number,
            ja.route,
            ja.assigned_date
        FROM boundary_payments bp 
        JOIN users u ON bp.driver_id = u.id 
        LEFT JOIN jeepney_assignments ja ON bp.driver_id = ja.driver_id AND ja.status = 'Active'
        WHERE bp.operator_id = $operator_id 
        ORDER BY bp.paid_at DESC
    ";
    
    $result = $conn->query($testQuery);
    echo "API query results: " . $result->num_rows . " payments found<br>";
    
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            echo "- Driver: {$row['firstName']} {$row['lastName']}, Amount: ₱{$row['amount']}, Status: {$row['status']}<br>";
        }
    } else {
        echo "❌ No payments found for operator $operator_id<br>";
        
        // Check if there are any payments at all
        $allPayments = $conn->query("SELECT COUNT(*) as count FROM boundary_payments");
        $totalPayments = $allPayments->fetch_assoc()['count'];
        echo "Total payments in system: $totalPayments<br>";
        
        // Check if operator_id matches
        $operatorPayments = $conn->query("SELECT COUNT(*) as count FROM boundary_payments WHERE operator_id = $operator_id");
        $operatorPaymentCount = $operatorPayments->fetch_assoc()['count'];
        echo "Payments for operator $operator_id: $operatorPaymentCount<br>";
    }
}

// Check 5: Create test payment if none exist
echo "<h3>5. Creating Test Payment</h3>";
$testPaymentCount = $conn->query("SELECT COUNT(*) as count FROM boundary_payments")->fetch_assoc()['count'];
if ($testPaymentCount == 0) {
    echo "No payments exist. Creating test payment...<br>";
    
    $driver = $conn->query("SELECT id FROM users WHERE userType = 'driver' LIMIT 1")->fetch_assoc();
    $operator = $conn->query("SELECT id FROM users WHERE userType = 'operator' LIMIT 1")->fetch_assoc();
    
    if ($driver && $operator) {
        $stmt = $conn->prepare("INSERT INTO boundary_payments (driver_id, operator_id, jeepney_id, amount, payment_method, status, reference_number) VALUES (?, ?, ?, ?, ?, 'Pending', ?)");
        $reference = 'DEBUG-' . date('YmdHis');
        $stmt->bind_param('iiidss', $driver['id'], $operator['id'], 1, 500, 'GCash', $reference);
        
        if ($stmt->execute()) {
            echo "✅ Test payment created successfully<br>";
            echo "Reference: $reference<br>";
        } else {
            echo "❌ Failed to create test payment: " . $stmt->error . "<br>";
        }
    } else {
        echo "❌ No driver or operator found<br>";
    }
} else {
    echo "Payments already exist. Skipping test payment creation.<br>";
}

// Check 6: Test the exact JavaScript that operator dashboard uses
echo "<h3>6. Testing Operator Dashboard JavaScript</h3>";
echo "<div id='testResults'></div>";
echo "<script>";
echo "const operator_id = " . ($operator['id'] ?? 1) . ";";
echo "console.log('Testing with operator_id:', operator_id);";
echo "fetch('pay_boundary.php', {";
echo "    method: 'POST',";
echo "    headers: { 'Content-Type': 'application/json' },";
echo "    body: JSON.stringify({ action: 'list', operator_id })";
echo "})";
echo ".then(res => res.json())";
echo ".then(data => {";
echo "    console.log('API Response:', data);";
echo "    document.getElementById('testResults').innerHTML = '<strong>API Response:</strong><br>' + JSON.stringify(data, null, 2);";
echo "})";
echo ".catch(err => {";
echo "    console.error('API Error:', err);";
echo "    document.getElementById('testResults').innerHTML = '<strong>API Error:</strong><br>' + err.message;";
echo "});";
echo "</script>";

echo "<h3>Debug Complete</h3>";
echo "<p><a href='operator_dashboard.php?page=collect_boundaries'>Go to Operator Dashboard</a></p>";
echo "<p><a href='driver_dashboard.php?page=pay_boundary'>Go to Driver Dashboard</a></p>";
?> 