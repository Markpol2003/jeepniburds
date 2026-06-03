<?php
require_once 'db_config.php';

echo "<h2>🔧 FORCE CREATE BOUNDARY PAYMENT</h2>";

// Step 1: Get current operator (the one logged in)
session_start();
$currentOperatorId = $_SESSION['user_id'] ?? null;

if (!$currentOperatorId) {
    echo "❌ No operator logged in. Please login as an operator first.<br>";
    exit;
}

echo "✅ Current operator ID: $currentOperatorId<br>";

// Step 2: Get or create a driver
$driver = $conn->query("SELECT id, firstName, lastName FROM users WHERE userType = 'driver' LIMIT 1")->fetch_assoc();
if (!$driver) {
    echo "❌ No driver found. Creating test driver...<br>";
    $conn->query("INSERT INTO users (firstName, lastName, email, password, userType) VALUES ('Test', 'Driver', 'testdriver@test.com', 'password123', 'driver')");
    $driver = $conn->query("SELECT id, firstName, lastName FROM users WHERE userType = 'driver' LIMIT 1")->fetch_assoc();
    echo "✅ Created test driver: {$driver['firstName']} {$driver['lastName']} (ID: {$driver['id']})<br>";
} else {
    echo "✅ Using driver: {$driver['firstName']} {$driver['lastName']} (ID: {$driver['id']})<br>";
}

// Step 3: Create jeepney assignment if it doesn't exist
$assignment = $conn->query("SELECT * FROM jeepney_assignments WHERE driver_id = {$driver['id']} AND operator_id = $currentOperatorId AND status = 'Active'")->fetch_assoc();

if (!$assignment) {
    echo "⚠️ No assignment found. Creating test assignment...<br>";
    $conn->query("INSERT INTO jeepney_assignments (driver_id, operator_id, plate_number, body_number, route, status) VALUES ({$driver['id']}, $currentOperatorId, 'TEST-123', 'BODY-001', 'Test Route', 'Active')");
    echo "✅ Created test assignment<br>";
    $assignment = $conn->query("SELECT * FROM jeepney_assignments WHERE driver_id = {$driver['id']} AND operator_id = $currentOperatorId AND status = 'Active'")->fetch_assoc();
} else {
    echo "✅ Found assignment: {$assignment['plate_number']} ({$assignment['body_number']})<br>";
}

// Step 4: Create a boundary payment
echo "<h3>Creating Boundary Payment...</h3>";

$amount = 500;
$payment_method = 'GCash';
$reference_number = 'BND-' . date('Ymd') . '-' . str_pad($driver['id'], 4, '0', STR_PAD_LEFT);
$notes = 'Test payment created by force script';

$stmt = $conn->prepare("INSERT INTO boundary_payments (driver_id, operator_id, jeepney_id, amount, payment_method, status, reference_number, notes) VALUES (?, ?, ?, ?, ?, 'Pending', ?, ?)");
$stmt->bind_param('iiidsss', $driver['id'], $currentOperatorId, $assignment['id'], $amount, $payment_method, $reference_number, $notes);

if ($stmt->execute()) {
    $paymentId = $conn->insert_id;
    echo "✅ Boundary payment created successfully!<br>";
    echo "- Payment ID: $paymentId<br>";
    echo "- Driver: {$driver['firstName']} {$driver['lastName']}<br>";
    echo "- Operator ID: $currentOperatorId<br>";
    echo "- Amount: ₱$amount<br>";
    echo "- Method: $payment_method<br>";
    echo "- Reference: $reference_number<br>";
    echo "- Status: Pending<br>";
} else {
    echo "❌ Failed to create boundary payment: " . $stmt->error . "<br>";
    exit;
}

// Step 5: Verify the payment exists
echo "<h3>Verifying Payment...</h3>";
$verifyPayment = $conn->query("SELECT * FROM boundary_payments WHERE id = $paymentId")->fetch_assoc();
if ($verifyPayment) {
    echo "✅ Payment verified in database<br>";
} else {
    echo "❌ Payment not found in database<br>";
}

// Step 6: Test the API
echo "<h3>Testing API...</h3>";
$testData = [
    'action' => 'list',
    'operator_id' => $currentOperatorId
];

// Simulate API call
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'http://localhost/tebzcopy/pay_boundary.php');
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($testData));
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "API Response Code: $httpCode<br>";
echo "API Response: $response<br>";

$responseData = json_decode($response, true);
if ($responseData && $responseData['success']) {
    echo "✅ API working correctly!<br>";
    echo "Found " . count($responseData['boundaries']) . " boundary payments<br>";
    
    foreach ($responseData['boundaries'] as $boundary) {
        echo "- Driver: {$boundary['driver']}, Amount: ₱{$boundary['amount']}, Status: {$boundary['status']}<br>";
    }
} else {
    echo "❌ API test failed<br>";
    if ($responseData) {
        echo "Error: " . ($responseData['message'] ?? 'Unknown error') . "<br>";
    }
}

// Step 7: Direct database check
echo "<h3>Direct Database Check</h3>";
$allPayments = $conn->query("SELECT * FROM boundary_payments WHERE operator_id = $currentOperatorId ORDER BY paid_at DESC");
echo "Total boundary payments for operator $currentOperatorId: " . $allPayments->num_rows . "<br>";

while ($payment = $allPayments->fetch_assoc()) {
    echo "- ID: {$payment['id']}, Driver: {$payment['driver_id']}, Amount: ₱{$payment['amount']}, Status: {$payment['status']}, Date: {$payment['paid_at']}<br>";
}

echo "<h3>🎯 Next Steps</h3>";
echo "1. Go to your operator dashboard<br>";
echo "2. Click on 'Collect Boundaries'<br>";
echo "3. Click 'Refresh List' button<br>";
echo "4. You should now see the boundary payment!<br>";

echo "<br><a href='operator_dashboard.php?page=collect_boundaries' class='btn btn-success'>Go to Collect Boundaries</a>";
?> 