<?php
require_once 'db_config.php';

echo "<h2>🧪 Boundary Payment Flow Test</h2>";

// Step 1: Check database structure
echo "<h3>Step 1: Database Structure Check</h3>";
$tables = ['boundary_payments', 'jeepney_assignments', 'users'];
foreach ($tables as $table) {
    $result = $conn->query("SHOW TABLES LIKE '$table'");
    echo $result->num_rows > 0 ? "✅ $table exists" : "❌ $table missing";
    echo "<br>";
}

// Step 2: Get test users
echo "<h3>Step 2: User Setup</h3>";
$driver = $conn->query("SELECT id, firstName, lastName FROM users WHERE userType = 'driver' LIMIT 1")->fetch_assoc();
$operator = $conn->query("SELECT id, firstName, lastName FROM users WHERE userType = 'operator' LIMIT 1")->fetch_assoc();

if (!$driver || !$operator) {
    echo "❌ Need at least one driver and one operator<br>";
    exit;
}

echo "✅ Driver: {$driver['firstName']} {$driver['lastName']} (ID: {$driver['id']})<br>";
echo "✅ Operator: {$operator['firstName']} {$operator['lastName']} (ID: {$operator['id']})<br>";

// Step 3: Check jeepney assignment
echo "<h3>Step 3: Jeepney Assignment Check</h3>";
$assignment = $conn->query("SELECT * FROM jeepney_assignments WHERE driver_id = {$driver['id']} AND operator_id = {$operator['id']} AND status = 'Active'")->fetch_assoc();

if (!$assignment) {
    echo "⚠️ No active assignment found. Creating test assignment...<br>";
    
    // Create test assignment
    $conn->query("INSERT INTO jeepney_assignments (driver_id, operator_id, plate_number, body_number, route, status) VALUES ({$driver['id']}, {$operator['id']}, 'TEST-123', 'BODY-001', 'Test Route', 'Active')");
    echo "✅ Created test assignment<br>";
    
    $assignment = $conn->query("SELECT * FROM jeepney_assignments WHERE driver_id = {$driver['id']} AND operator_id = {$operator['id']} AND status = 'Active'")->fetch_assoc();
} else {
    echo "✅ Found active assignment: {$assignment['plate_number']} ({$assignment['body_number']})<br>";
}

// Step 4: Test boundary payment submission
echo "<h3>Step 4: Test Boundary Payment Submission</h3>";

$testPayment = [
    'driver_id' => $driver['id'],
    'operator_id' => $operator['id'],
    'jeepney_id' => $assignment['id'],
    'amount' => 500,
    'payment_method' => 'GCash',
    'notes' => 'Test payment from automated script'
];

echo "Submitting test payment...<br>";

// Simulate the API call
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'http://localhost/tebzcopy/pay_boundary.php');
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($testPayment));
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP Response Code: $httpCode<br>";
echo "Response: $response<br>";

$responseData = json_decode($response, true);
if ($responseData && $responseData['success']) {
    echo "✅ Payment submitted successfully!<br>";
} else {
    echo "❌ Payment submission failed<br>";
    if ($responseData) {
        echo "Error: " . ($responseData['message'] ?? 'Unknown error') . "<br>";
    }
}

// Step 5: Test boundary listing for operator
echo "<h3>Step 5: Test Boundary Listing for Operator</h3>";

$listData = [
    'action' => 'list',
    'operator_id' => $operator['id']
];

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'http://localhost/tebzcopy/pay_boundary.php');
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($listData));
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

$listResponse = curl_exec($ch);
$listHttpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "List HTTP Response Code: $listHttpCode<br>";
echo "List Response: $listResponse<br>";

$listData = json_decode($listResponse, true);
if ($listData && $listData['success']) {
    echo "✅ Boundary listing successful!<br>";
    echo "Found " . count($listData['boundaries']) . " boundary payments<br>";
    
    foreach ($listData['boundaries'] as $boundary) {
        echo "- Driver: {$boundary['driver']}, Amount: ₱{$boundary['amount']}, Status: {$boundary['status']}<br>";
    }
} else {
    echo "❌ Boundary listing failed<br>";
    if ($listData) {
        echo "Error: " . ($listData['message'] ?? 'Unknown error') . "<br>";
    }
}

// Step 6: Test statistics
echo "<h3>Step 6: Test Statistics</h3>";

$statsData = [
    'action' => 'stats',
    'operator_id' => $operator['id']
];

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'http://localhost/tebzcopy/pay_boundary.php');
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($statsData));
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

$statsResponse = curl_exec($ch);
$statsHttpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "Stats HTTP Response Code: $statsHttpCode<br>";
echo "Stats Response: $statsResponse<br>";

$statsData = json_decode($statsResponse, true);
if ($statsData && $statsData['success']) {
    echo "✅ Statistics successful!<br>";
    echo "Pending: {$statsData['stats']['pending']['count']} payments, ₱{$statsData['stats']['pending']['total']}<br>";
    echo "Collected: {$statsData['stats']['collected']['count']} payments, ₱{$statsData['stats']['collected']['total']}<br>";
} else {
    echo "❌ Statistics failed<br>";
    if ($statsData) {
        echo "Error: " . ($statsData['message'] ?? 'Unknown error') . "<br>";
    }
}

// Step 7: Direct database check
echo "<h3>Step 7: Direct Database Check</h3>";
$payments = $conn->query("SELECT * FROM boundary_payments WHERE operator_id = {$operator['id']} ORDER BY paid_at DESC");
echo "Total boundary payments for operator: " . $payments->num_rows . "<br>";

while ($payment = $payments->fetch_assoc()) {
    echo "- ID: {$payment['id']}, Driver: {$payment['driver_id']}, Amount: ₱{$payment['amount']}, Status: {$payment['status']}, Date: {$payment['paid_at']}<br>";
}

echo "<h3>🎯 Summary</h3>";
echo "The boundary payment system should now be working correctly. If you see boundary payments in the database and API responses, then the system is functioning properly.<br>";
echo "To test the full flow:<br>";
echo "1. Login as a driver and submit a boundary payment<br>";
echo "2. Login as an operator and check the 'Collect Boundaries' section<br>";
echo "3. The payment should appear in the operator's collection interface<br>";
?> 