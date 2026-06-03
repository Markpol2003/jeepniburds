<?php
session_start();
require_once __DIR__ . '/../db_config.php';

echo "<h2>🔍 Operator Boundary Check</h2>";

// Check session
echo "<h3>1. Session Check</h3>";
echo "User ID: " . ($_SESSION['user_id'] ?? 'NOT SET') . "<br>";
echo "User Type: " . ($_SESSION['user_type'] ?? 'NOT SET') . "<br>";
echo "User Name: " . ($_SESSION['user_firstName'] ?? 'NOT SET') . " " . ($_SESSION['user_lastName'] ?? 'NOT SET') . "<br>";

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'operator') {
    echo "❌ Not logged in as operator<br>";
    exit;
}

$operatorId = $_SESSION['user_id'];
echo "✅ Logged in as operator (ID: $operatorId)<br>";

// Check boundary payments
echo "<h3>2. Boundary Payments Check</h3>";
$payments = $conn->query("SELECT * FROM boundary_payments WHERE operator_id = $operatorId ORDER BY paid_at DESC");
echo "Total boundary payments for operator $operatorId: " . $payments->num_rows . "<br>";

if ($payments->num_rows > 0) {
    echo "Recent payments:<br>";
    while ($payment = $payments->fetch_assoc()) {
        echo "- ID: {$payment['id']}, Driver: {$payment['driver_id']}, Amount: ₱{$payment['amount']}, Status: {$payment['status']}, Date: {$payment['paid_at']}<br>";
    }
} else {
    echo "❌ No boundary payments found for this operator<br>";
}

// Check jeepney assignments
echo "<h3>3. Jeepney Assignments Check</h3>";
$assignments = $conn->query("SELECT * FROM jeepney_assignments WHERE operator_id = $operatorId AND status = 'Active'");
echo "Active assignments for operator $operatorId: " . $assignments->num_rows . "<br>";

if ($assignments->num_rows > 0) {
    echo "Active assignments:<br>";
    while ($assignment = $assignments->fetch_assoc()) {
        echo "- Driver: {$assignment['driver_id']}, Jeepney: {$assignment['plate_number']}, Route: {$assignment['route']}<br>";
    }
} else {
    echo "❌ No active assignments found for this operator<br>";
}

// Test API directly
echo "<h3>4. API Test</h3>";
$testData = [
    'action' => 'list',
    'operator_id' => $operatorId
];

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
    echo "✅ API working! Found " . count($responseData['boundaries']) . " boundary payments<br>";
} else {
    echo "❌ API failed<br>";
    if ($responseData) {
        echo "Error: " . ($responseData['message'] ?? 'Unknown error') . "<br>";
    }
}

// Test statistics
echo "<h3>5. Statistics Test</h3>";
$statsData = [
    'action' => 'stats',
    'operator_id' => $operatorId
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

echo "Stats Response Code: $statsHttpCode<br>";
echo "Stats Response: $statsResponse<br>";

$statsData = json_decode($statsResponse, true);
if ($statsData && $statsData['success']) {
    echo "✅ Stats working!<br>";
    echo "Pending: {$statsData['stats']['pending']['count']} payments, ₱{$statsData['stats']['pending']['total']}<br>";
    echo "Collected: {$statsData['stats']['collected']['count']} payments, ₱{$statsData['stats']['collected']['total']}<br>";
} else {
    echo "❌ Stats failed<br>";
}

echo "<h3>All Pending Boundary Payments (Raw Dump)</h3>";
$pending = $conn->query("SELECT id, driver_id, operator_id, amount, status, paid_at FROM boundary_payments WHERE status = 'Pending' ORDER BY paid_at DESC");
if ($pending->num_rows > 0) {
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr><th>ID</th><th>Driver ID</th><th>Operator ID</th><th>Amount</th><th>Status</th><th>Paid At</th></tr>";
    while ($row = $pending->fetch_assoc()) {
        echo "<tr>";
        echo "<td>{$row['id']}</td>";
        echo "<td>{$row['driver_id']}</td>";
        echo "<td>{$row['operator_id']}</td>";
        echo "<td>{$row['amount']}</td>";
        echo "<td>{$row['status']}</td>";
        echo "<td>{$row['paid_at']}</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "No pending boundary payments found.<br>";
}

echo "<h3>🎯 Summary</h3>";
if ($payments->num_rows > 0) {
    echo "✅ You have boundary payments in the database<br>";
    echo "✅ The API should be working<br>";
    echo "✅ Check the operator dashboard 'Collect Boundaries' section<br>";
} else {
    echo "❌ No boundary payments found<br>";
    echo "Try running: <a href='force_create_boundary_payment.php'>Force Create Boundary Payment</a><br>";
}

echo "<br><a href='operator_dashboard.php?page=collect_boundaries' class='btn btn-primary'>Go to Collect Boundaries</a>";
?> 