<?php
require_once 'db_config.php';

echo "<h2>🔍 Boundary Payment System Verification</h2>";

// Check 1: Database tables
echo "<h3>1. Database Tables</h3>";
$tables = ['boundary_payments', 'jeepney_assignments', 'users'];
foreach ($tables as $table) {
    $result = $conn->query("SHOW TABLES LIKE '$table'");
    echo $result->num_rows > 0 ? "✅ $table exists" : "❌ $table missing";
    echo "<br>";
}

// Check 2: Boundary payments table structure
echo "<h3>2. Boundary Payments Table Structure</h3>";
$columns = $conn->query("SHOW COLUMNS FROM boundary_payments");
echo "Columns in boundary_payments table:<br>";
while ($col = $columns->fetch_assoc()) {
    echo "- {$col['Field']} ({$col['Type']})<br>";
}

// Check 3: Current boundary payments
echo "<h3>3. Current Boundary Payments</h3>";
$payments = $conn->query("SELECT COUNT(*) as count FROM boundary_payments");
$count = $payments->fetch_assoc()['count'];
echo "Total boundary payments: $count<br>";

if ($count > 0) {
    $recentPayments = $conn->query("SELECT * FROM boundary_payments ORDER BY paid_at DESC LIMIT 5");
    echo "Recent payments:<br>";
    while ($payment = $recentPayments->fetch_assoc()) {
        echo "- ID: {$payment['id']}, Driver: {$payment['driver_id']}, Operator: {$payment['operator_id']}, Amount: ₱{$payment['amount']}, Status: {$payment['status']}<br>";
    }
}

// Check 4: Jeepney assignments
echo "<h3>4. Jeepney Assignments</h3>";
$assignments = $conn->query("SELECT COUNT(*) as count FROM jeepney_assignments WHERE status = 'Active'");
$assignmentCount = $assignments->fetch_assoc()['count'];
echo "Active jeepney assignments: $assignmentCount<br>";

if ($assignmentCount > 0) {
    $activeAssignments = $conn->query("SELECT ja.*, d.firstName as driver_name, o.firstName as operator_name FROM jeepney_assignments ja JOIN users d ON ja.driver_id = d.id JOIN users o ON ja.operator_id = o.id WHERE ja.status = 'Active' LIMIT 5");
    echo "Active assignments:<br>";
    while ($assignment = $activeAssignments->fetch_assoc()) {
        echo "- Driver: {$assignment['driver_name']}, Operator: {$assignment['operator_name']}, Jeepney: {$assignment['plate_number']}<br>";
    }
}

// Check 5: Test API endpoints
echo "<h3>5. API Endpoint Test</h3>";
echo "<button onclick='testAPI()' class='btn btn-primary'>Test Boundary Payment API</button><br><br>";
echo "<div id='apiResults'></div>";

echo "<script>
function testAPI() {
    const resultsDiv = document.getElementById('apiResults');
    resultsDiv.innerHTML = 'Testing API endpoints...<br>';
    
    // Test 1: List boundaries
    fetch('pay_boundary.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'list', operator_id: 1 })
    })
    .then(res => res.json())
    .then(data => {
        resultsDiv.innerHTML += '✅ List API: ' + (data.success ? 'Working' : 'Failed') + '<br>';
        if (data.success) {
            resultsDiv.innerHTML += 'Found ' + data.boundaries.length + ' boundary payments<br>';
        }
    })
    .catch(err => {
        resultsDiv.innerHTML += '❌ List API Error: ' + err.message + '<br>';
    });
    
    // Test 2: Stats API
    fetch('pay_boundary.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'stats', operator_id: 1 })
    })
    .then(res => res.json())
    .then(data => {
        resultsDiv.innerHTML += '✅ Stats API: ' + (data.success ? 'Working' : 'Failed') + '<br>';
        if (data.success) {
            resultsDiv.innerHTML += 'Pending: ' + data.stats.pending.count + ', Collected: ' + data.stats.collected.count + '<br>';
        }
    })
    .catch(err => {
        resultsDiv.innerHTML += '❌ Stats API Error: ' + err.message + '<br>';
    });
}
</script>";

echo "<h3>🎯 System Status</h3>";
if ($count > 0 && $assignmentCount > 0) {
    echo "✅ Boundary payment system appears to be working correctly!<br>";
    echo "✅ Database tables exist and contain data<br>";
    echo "✅ API endpoints are available for testing<br>";
    echo "<br><strong>Next Steps:</strong><br>";
    echo "1. Login as a driver and submit a boundary payment<br>";
    echo "2. Login as an operator and check the 'Collect Boundaries' section<br>";
    echo "3. The payment should appear immediately in the operator's interface<br>";
} else {
    echo "⚠️ System may need setup. Please ensure:<br>";
    echo "- At least one driver and operator exist<br>";
    echo "- At least one jeepney assignment is active<br>";
    echo "- Boundary payments can be submitted<br>";
}

echo "<br><a href='test_boundary_payment_flow.php' class='btn btn-success'>Run Full System Test</a>";
?> 