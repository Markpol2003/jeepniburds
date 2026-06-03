<?php
require_once 'db_config.php';

echo "<h2>🧪 Create Test Boundary Payment</h2>";

// Get a driver and operator
$driver = $conn->query("SELECT id, firstName, lastName FROM users WHERE userType = 'driver' LIMIT 1")->fetch_assoc();
$operator = $conn->query("SELECT id, firstName, lastName FROM users WHERE userType = 'operator' LIMIT 1")->fetch_assoc();

if (!$driver || !$operator) {
    echo "❌ No driver or operator found. Please create users first.<br>";
    exit;
}

echo "Using driver: {$driver['firstName']} {$driver['lastName']} (ID: {$driver['id']})<br>";
echo "Using operator: {$operator['firstName']} {$operator['lastName']} (ID: {$operator['id']})<br>";

// Check if driver has assignment
$assignment = $conn->query("SELECT * FROM jeepney_assignments WHERE driver_id = {$driver['id']} AND status = 'Active'")->fetch_assoc();

if (!$assignment) {
    echo "⚠️ Driver has no assignment. Creating one...<br>";
    
    $createAssignment = "INSERT INTO jeepney_assignments (driver_id, operator_id, plate_number, body_number, route, status) 
                        VALUES ({$driver['id']}, {$operator['id']}, 'TEST-001', 'BODY-001', 'Test Route', 'Active')";
    
    if ($conn->query($createAssignment)) {
        echo "✅ Created assignment<br>";
        $assignment = $conn->query("SELECT * FROM jeepney_assignments WHERE driver_id = {$driver['id']} AND status = 'Active'")->fetch_assoc();
    } else {
        echo "❌ Failed to create assignment: " . $conn->error . "<br>";
    }
} else {
    echo "✅ Driver has assignment<br>";
}

// Create boundary payment
$reference = 'TEST-' . date('YmdHis');
$stmt = $conn->prepare("INSERT INTO boundary_payments (driver_id, operator_id, jeepney_id, amount, payment_method, status, reference_number) VALUES (?, ?, ?, ?, ?, 'Pending', ?)");
$stmt->bind_param('iiidss', $driver['id'], $operator['id'], 1, 500, 'GCash', $reference);

if ($stmt->execute()) {
    echo "✅ Created boundary payment successfully!<br>";
    echo "Reference: $reference<br>";
    echo "Amount: ₱500<br>";
    echo "Status: Pending<br>";
    
    // Verify it was created
    $paymentId = $conn->insert_id;
    $verify = $conn->query("SELECT * FROM boundary_payments WHERE id = $paymentId")->fetch_assoc();
    if ($verify) {
        echo "✅ Payment verified in database<br>";
    }
    
    // Test if it appears for the operator
    echo "<h3>Testing Operator View</h3>";
    $operatorQuery = "
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
        WHERE bp.operator_id = {$operator['id']} 
        ORDER BY bp.paid_at DESC
    ";
    
    $result = $conn->query($operatorQuery);
    echo "Payments found for operator {$operator['id']}: " . $result->num_rows . "<br>";
    
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            echo "- Driver: {$row['firstName']} {$row['lastName']}, Amount: ₱{$row['amount']}, Status: {$row['status']}<br>";
        }
        echo "✅ Payment appears in operator view!<br>";
    } else {
        echo "❌ Payment does not appear in operator view<br>";
    }
    
    // Test the API
    echo "<h3>Testing API</h3>";
    echo "<div id='apiResult'></div>";
    echo "<script>";
    echo "fetch('pay_boundary.php', {";
    echo "    method: 'POST',";
    echo "    headers: { 'Content-Type': 'application/json' },";
    echo "    body: JSON.stringify({ action: 'list', operator_id: {$operator['id']} })";
    echo "})";
    echo ".then(res => res.json())";
    echo ".then(data => {";
    echo "    console.log('API Response:', data);";
    echo "    document.getElementById('apiResult').innerHTML = '<strong>API Response:</strong><br><pre>' + JSON.stringify(data, null, 2) + '</pre>';";
    echo "})";
    echo ".catch(err => {";
    echo "    console.error('API Error:', err);";
    echo "    document.getElementById('apiResult').innerHTML = '<strong>API Error:</strong><br>' + err.message;";
    echo "});";
    echo "</script>";
    
} else {
    echo "❌ Failed to create boundary payment: " . $stmt->error . "<br>";
}

echo "<h3>Test Complete</h3>";
echo "<p><a href='operator_dashboard.php?page=collect_boundaries'>Go to Operator Dashboard</a></p>";
echo "<p><a href='debug_boundary_simple.php'>Run Debug</a></p>";
?> 