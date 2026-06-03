<?php
require_once 'db_config.php';

echo "<h2>🔧 FORCE FIX BOUNDARY SYSTEM</h2>";

// Step 1: Clear existing data and start fresh
echo "<h3>Step 1: Clearing existing data</h3>";
$conn->query("DELETE FROM boundary_payments");
$conn->query("DELETE FROM jeepney_assignments");
echo "✅ Cleared existing data<br>";

// Step 2: Get or create users
echo "<h3>Step 2: Setting up users</h3>";
$driver = $conn->query("SELECT id, firstName, lastName FROM users WHERE userType = 'driver' LIMIT 1")->fetch_assoc();
$operator = $conn->query("SELECT id, firstName, lastName FROM users WHERE userType = 'operator' LIMIT 1")->fetch_assoc();

if (!$driver) {
    echo "❌ No driver found. Creating test driver...<br>";
    $conn->query("INSERT INTO users (firstName, lastName, email, password, userType) VALUES ('Test', 'Driver', 'driver@test.com', 'password', 'driver')");
    $driver = $conn->query("SELECT id, firstName, lastName FROM users WHERE userType = 'driver' LIMIT 1")->fetch_assoc();
}

if (!$operator) {
    echo "❌ No operator found. Creating test operator...<br>";
    $conn->query("INSERT INTO users (firstName, lastName, email, password, userType) VALUES ('Test', 'Operator', 'operator@test.com', 'password', 'operator')");
    $operator = $conn->query("SELECT id, firstName, lastName FROM users WHERE userType = 'operator' LIMIT 1")->fetch_assoc();
}

echo "✅ Driver: {$driver['firstName']} {$driver['lastName']} (ID: {$driver['id']})<br>";
echo "✅ Operator: {$operator['firstName']} {$operator['lastName']} (ID: {$operator['id']})<br>";

// Step 3: Create jeepney assignment
echo "<h3>Step 3: Creating jeepney assignment</h3>";
$assignmentQuery = "INSERT INTO jeepney_assignments (driver_id, operator_id, plate_number, body_number, route, status) 
                   VALUES ({$driver['id']}, {$operator['id']}, 'ABC-123', 'BODY-001', 'Test Route', 'Active')";

if ($conn->query($assignmentQuery)) {
    echo "✅ Created jeepney assignment<br>";
} else {
    echo "❌ Failed to create assignment: " . $conn->error . "<br>";
}

// Step 4: Create multiple boundary payments
echo "<h3>Step 4: Creating boundary payments</h3>";
for ($i = 1; $i <= 3; $i++) {
    $reference = 'PAY-' . date('Ymd') . '-' . str_pad($i, 3, '0', STR_PAD_LEFT);
    $amount = 500 + ($i * 100);
    
    $paymentQuery = "INSERT INTO boundary_payments (driver_id, operator_id, jeepney_id, amount, payment_method, status, reference_number) 
                     VALUES ({$driver['id']}, {$operator['id']}, 1, $amount, 'GCash', 'Pending', '$reference')";
    
    if ($conn->query($paymentQuery)) {
        echo "✅ Created payment $i: ₱$amount (Reference: $reference)<br>";
    } else {
        echo "❌ Failed to create payment $i: " . $conn->error . "<br>";
    }
}

// Step 5: Verify data exists
echo "<h3>Step 5: Verifying data</h3>";
$payments = $conn->query("SELECT COUNT(*) as count FROM boundary_payments WHERE operator_id = {$operator['id']}");
$paymentCount = $payments->fetch_assoc()['count'];
echo "Total payments for operator {$operator['id']}: $paymentCount<br>";

$assignments = $conn->query("SELECT COUNT(*) as count FROM jeepney_assignments WHERE operator_id = {$operator['id']}");
$assignmentCount = $assignments->fetch_assoc()['count'];
echo "Total assignments for operator {$operator['id']}: $assignmentCount<br>";

// Step 6: Test the exact query used by operator dashboard
echo "<h3>Step 6: Testing operator dashboard query</h3>";
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
    WHERE bp.operator_id = {$operator['id']} 
    ORDER BY bp.paid_at DESC
";

$result = $conn->query($testQuery);
echo "Query results: " . $result->num_rows . " payments found<br>";

if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        echo "- Driver: {$row['firstName']} {$row['lastName']}, Amount: ₱{$row['amount']}, Status: {$row['status']}<br>";
    }
    echo "✅ Data is correctly structured for operator dashboard!<br>";
} else {
    echo "❌ No results found. There's a problem with the query.<br>";
}

// Step 7: Create a simple test page that mimics operator dashboard
echo "<h3>Step 7: Creating test operator dashboard</h3>";
echo "<div id='testDashboard'>";
echo "<h4>Test Operator Dashboard</h4>";
echo "<p>Operator ID: {$operator['id']}</p>";
echo "<button onclick='loadTestData()' class='btn btn-primary'>Load Boundary Payments</button>";
echo "<div id='testResults'></div>";
echo "</div>";

echo "<script>";
echo "function loadTestData() {";
echo "    fetch('pay_boundary.php', {";
echo "        method: 'POST',";
echo "        headers: { 'Content-Type': 'application/json' },";
echo "        body: JSON.stringify({ action: 'list', operator_id: {$operator['id']} })";
echo "    })";
echo "    .then(res => res.json())";
echo "    .then(data => {";
echo "        console.log('API Response:', data);";
echo "        let html = '<h5>API Response:</h5><pre>' + JSON.stringify(data, null, 2) + '</pre>';";
echo "        if (data.success && data.boundaries.length > 0) {";
echo "            html += '<h5>Boundary Payments:</h5><ul>';";
echo "            data.boundaries.forEach(b => {";
echo "                html += '<li>Driver: ' + b.driver + ', Amount: ₱' + b.amount + ', Status: ' + b.status + '</li>';";
echo "            });";
echo "            html += '</ul>';";
echo "        } else {";
echo "            html += '<p>No boundary payments found</p>';";
echo "        }";
echo "        document.getElementById('testResults').innerHTML = html;";
echo "    })";
echo "    .catch(err => {";
echo "        console.error('API Error:', err);";
echo "        document.getElementById('testResults').innerHTML = '<p>Error: ' + err.message + '</p>';";
echo "    });";
echo "}";
echo "</script>";

echo "<h3>✅ FORCE FIX COMPLETE</h3>";
echo "<p><strong>Next Steps:</strong></p>";
echo "<ol>";
echo "<li>Click 'Load Boundary Payments' button above to test the API</li>";
echo "<li>Go to <a href='operator_dashboard.php?page=collect_boundaries'>Operator Dashboard</a></li>";
echo "<li>Click 'Refresh List' button in the operator dashboard</li>";
echo "<li>The boundary payments should now appear!</li>";
echo "</ol>";

echo "<p><strong>If it still doesn't work:</strong></p>";
echo "<ul>";
echo "<li>Check browser console for JavaScript errors</li>";
echo "<li>Make sure you're logged in as the test operator (ID: {$operator['id']})</li>";
echo "<li>Try logging out and logging back in as the operator</li>";
echo "</ul>";
?> 