<?php
require_once 'db_config.php';

echo "<h2>Boundary Payment Flow Test</h2>";

// Step 1: Check if boundary_payments table exists
echo "<h3>Step 1: Checking Database</h3>";
$tableExists = $conn->query("SHOW TABLES LIKE 'boundary_payments'");
if ($tableExists->num_rows > 0) {
    echo "✅ boundary_payments table exists<br>";
} else {
    echo "❌ boundary_payments table does not exist<br>";
    exit;
}

// Step 2: Get a driver and operator for testing
echo "<h3>Step 2: Finding Test Users</h3>";
$driver = $conn->query("SELECT id, firstName, lastName FROM users WHERE userType = 'driver' LIMIT 1")->fetch_assoc();
$operator = $conn->query("SELECT id, firstName, lastName FROM users WHERE userType = 'operator' LIMIT 1")->fetch_assoc();

if ($driver && $operator) {
    echo "✅ Found driver: {$driver['firstName']} {$driver['lastName']} (ID: {$driver['id']})<br>";
    echo "✅ Found operator: {$operator['firstName']} {$operator['lastName']} (ID: {$operator['id']})<br>";
} else {
    echo "❌ Could not find driver or operator<br>";
    exit;
}

// Step 3: Check if driver has an assignment
echo "<h3>Step 3: Checking Driver Assignment</h3>";
$assignment = $conn->query("SELECT * FROM jeepney_assignments WHERE driver_id = {$driver['id']} AND status = 'Active' LIMIT 1")->fetch_assoc();

if ($assignment) {
    echo "✅ Driver has active assignment: {$assignment['plate_number']} ({$assignment['body_number']})<br>";
} else {
    echo "❌ Driver has no active assignment<br>";
    // Create a test assignment
    $testAssignment = $conn->prepare("INSERT INTO jeepney_assignments (driver_id, operator_id, plate_number, body_number, route, status, assigned_date) VALUES (?, ?, ?, ?, ?, 'Active', NOW())");
    $plate = 'TEST-' . rand(1000, 9999);
    $body = 'B' . rand(100, 999);
    $route = 'Test Route';
    $testAssignment->bind_param('iissss', $driver['id'], $operator['id'], $plate, $body, $route);
    
    if ($testAssignment->execute()) {
        echo "✅ Created test assignment: $plate ($body)<br>";
        $assignment = $conn->query("SELECT * FROM jeepney_assignments WHERE driver_id = {$driver['id']} AND status = 'Active' LIMIT 1")->fetch_assoc();
    } else {
        echo "❌ Failed to create test assignment<br>";
        exit;
    }
}

// Step 4: Simulate a boundary payment
echo "<h3>Step 4: Simulating Boundary Payment</h3>";
$amount = 500;
$payment_method = 'GCash';
$reference_number = 'BND-' . date('Ymd') . '-' . str_pad($driver['id'], 4, '0', STR_PAD_LEFT);
$notes = 'Test payment from boundary flow test';

$stmt = $conn->prepare("INSERT INTO boundary_payments (driver_id, operator_id, jeepney_id, amount, payment_method, status, reference_number, notes) VALUES (?, ?, ?, ?, ?, 'Pending', ?, ?)");
$stmt->bind_param('iiidsss', $driver['id'], $operator['id'], $assignment['id'], $amount, $payment_method, $reference_number, $notes);

if ($stmt->execute()) {
    $paymentId = $conn->insert_id;
    echo "✅ Created test boundary payment:<br>";
    echo "- Payment ID: $paymentId<br>";
    echo "- Driver: {$driver['firstName']} {$driver['lastName']}<br>";
    echo "- Operator: {$operator['firstName']} {$operator['lastName']}<br>";
    echo "- Amount: ₱$amount<br>";
    echo "- Method: $payment_method<br>";
    echo "- Reference: $reference_number<br>";
    echo "- Status: Pending<br>";
} else {
    echo "❌ Failed to create test payment: " . $stmt->error . "<br>";
    exit;
}

// Step 5: Test the API endpoints
echo "<h3>Step 5: Testing API Endpoints</h3>";

// Test listing boundaries for operator
$testData = [
    'action' => 'list',
    'operator_id' => $operator['id']
];

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'http://localhost/tebzcopy/pay_boundary.php');
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($testData));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode === 200) {
    $data = json_decode($response, true);
    if ($data['success']) {
        echo "✅ API list endpoint working - Found " . count($data['boundaries']) . " boundary payments<br>";
    } else {
        echo "❌ API list endpoint failed: " . ($data['message'] ?? 'Unknown error') . "<br>";
    }
} else {
    echo "❌ API list endpoint failed with HTTP code: $httpCode<br>";
}

// Test statistics endpoint
$statsData = [
    'action' => 'stats',
    'operator_id' => $operator['id']
];

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'http://localhost/tebzcopy/pay_boundary.php');
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($statsData));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode === 200) {
    $data = json_decode($response, true);
    if ($data['success']) {
        echo "✅ API stats endpoint working<br>";
        echo "- Pending payments: " . $data['stats']['pending']['count'] . "<br>";
        echo "- Pending amount: ₱" . number_format($data['stats']['pending']['total'], 2) . "<br>";
        echo "- Collected payments: " . $data['stats']['collected']['count'] . "<br>";
        echo "- Collected amount: ₱" . number_format($data['stats']['collected']['total'], 2) . "<br>";
    } else {
        echo "❌ API stats endpoint failed: " . ($data['message'] ?? 'Unknown error') . "<br>";
    }
} else {
    echo "❌ API stats endpoint failed with HTTP code: $httpCode<br>";
}

// Step 6: Test confirmation
echo "<h3>Step 6: Testing Payment Confirmation</h3>";
$confirmData = [
    'action' => 'confirm',
    'id' => $paymentId
];

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'http://localhost/tebzcopy/pay_boundary.php');
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($confirmData));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode === 200) {
    $data = json_decode($response, true);
    if ($data['success']) {
        echo "✅ Payment confirmation working<br>";
        
        // Verify the payment status was updated
        $updatedPayment = $conn->query("SELECT status FROM boundary_payments WHERE id = $paymentId")->fetch_assoc();
        if ($updatedPayment['status'] === 'Collected') {
            echo "✅ Payment status updated to 'Collected'<br>";
        } else {
            echo "❌ Payment status not updated correctly<br>";
        }
    } else {
        echo "❌ Payment confirmation failed: " . ($data['message'] ?? 'Unknown error') . "<br>";
    }
} else {
    echo "❌ Payment confirmation failed with HTTP code: $httpCode<br>";
}

// Step 7: Summary
echo "<h3>Step 7: Test Summary</h3>";
echo "<div style='background: #d4edda; padding: 15px; border-radius: 5px; margin: 10px 0;'>";
echo "<h4>✅ Boundary Payment Flow Test Complete</h4>";
echo "<p><strong>What was tested:</strong></p>";
echo "<ul>";
echo "<li>Database table existence</li>";
echo "<li>Driver and operator availability</li>";
echo "<li>Jeepney assignment</li>";
echo "<li>Boundary payment creation</li>";
echo "<li>API endpoints (list, stats, confirm)</li>";
echo "<li>Payment confirmation</li>";
echo "</ul>";
echo "<p><strong>Next steps:</strong></p>";
echo "<ul>";
echo "<li><a href='driver_dashboard.php?page=pay_boundary' target='_blank'>Test Driver Payment Interface</a></li>";
echo "<li><a href='operator_dashboard.php?page=collect_boundaries' target='_blank'>Test Operator Collection Interface</a></li>";
echo "</ul>";
echo "</div>";

// Clean up test data (optional)
if (isset($_GET['cleanup'])) {
    echo "<h3>Cleanup</h3>";
    $conn->query("DELETE FROM boundary_payments WHERE reference_number = '$reference_number'");
    echo "✅ Test payment cleaned up<br>";
}
?> 