<?php
require_once 'db_config.php';

echo "<h2>Boundary Payment System Test</h2>";

// Get current operator ID from session
session_start();
$operator_id = $_SESSION['user_id'] ?? 'Not logged in';
echo "Current Operator ID: " . $operator_id . "<br>";

// Test 1: Check if boundary_payments table exists
echo "<h3>Test 1: Database Table</h3>";
$tableExists = $conn->query("SHOW TABLES LIKE 'boundary_payments'");
if ($tableExists->num_rows > 0) {
    echo "✅ boundary_payments table exists<br>";
} else {
    echo "❌ boundary_payments table does not exist<br>";
}

// Test 2: Check total boundary payments
echo "<h3>Test 2: Total Boundary Payments</h3>";
$totalPayments = $conn->query("SELECT COUNT(*) as count FROM boundary_payments");
$total = $totalPayments->fetch_assoc()['count'];
echo "Total boundary payments: " . $total . "<br>";

// Test 3: Check payments for current operator
echo "<h3>Test 3: Operator's Boundary Payments</h3>";
$operatorPayments = $conn->query("SELECT COUNT(*) as count FROM boundary_payments WHERE operator_id = " . $operator_id);
$operatorCount = $operatorPayments->fetch_assoc()['count'];
echo "Payments for operator " . $operator_id . ": " . $operatorCount . "<br>";

// Test 4: Test the API endpoints
echo "<h3>Test 4: API Endpoints</h3>";

// Test list action
$listData = [
    'action' => 'list',
    'operator_id' => $operator_id
];

$listResponse = file_get_contents('http://localhost/tebzcopy/pay_boundary.php', false, stream_context_create([
    'http' => [
        'method' => 'POST',
        'header' => 'Content-Type: application/json',
        'content' => json_encode($listData)
    ]
]));

echo "List API Response: " . $listResponse . "<br>";

// Test stats action
$statsData = [
    'action' => 'stats',
    'operator_id' => $operator_id
];

$statsResponse = file_get_contents('http://localhost/tebzcopy/pay_boundary.php', false, stream_context_create([
    'http' => [
        'method' => 'POST',
        'header' => 'Content-Type: application/json',
        'content' => json_encode($statsData)
    ]
]));

echo "Stats API Response: " . $statsResponse . "<br>";

// Test 5: Show sample data
echo "<h3>Test 5: Sample Boundary Payments</h3>";
$sampleQuery = "
    SELECT bp.*, u.firstName, u.lastName 
    FROM boundary_payments bp 
    JOIN users u ON bp.driver_id = u.id 
    WHERE bp.operator_id = ? 
    ORDER BY bp.paid_at DESC 
    LIMIT 5
";

$stmt = $conn->prepare($sampleQuery);
$stmt->bind_param('i', $operator_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr><th>ID</th><th>Driver</th><th>Amount</th><th>Method</th><th>Status</th><th>Date</th></tr>";
    
    while ($row = $result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . $row['id'] . "</td>";
        echo "<td>" . $row['firstName'] . " " . $row['lastName'] . "</td>";
        echo "<td>₱" . $row['amount'] . "</td>";
        echo "<td>" . $row['payment_method'] . "</td>";
        echo "<td>" . $row['status'] . "</td>";
        echo "<td>" . $row['paid_at'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "No boundary payments found for this operator.<br>";
}

// Test 6: Create a test payment if none exist
echo "<h3>Test 6: Create Test Payment</h3>";
if ($total == 0) {
    // Get first driver
    $driverResult = $conn->query("SELECT id FROM users WHERE userType = 'driver' LIMIT 1");
    if ($driverResult->num_rows > 0) {
        $driver = $driverResult->fetch_assoc();
        
        $testStmt = $conn->prepare("INSERT INTO boundary_payments (driver_id, operator_id, jeepney_id, amount, payment_method, status, reference_number) VALUES (?, ?, ?, ?, ?, 'Pending', ?)");
        $reference = 'TEST-' . date('Ymd') . '-001';
        $testStmt->bind_param('iiidss', $driver['id'], $operator_id, 1, 500, 'GCash', $reference);
        
        if ($testStmt->execute()) {
            echo "✅ Test boundary payment created successfully<br>";
        } else {
            echo "❌ Failed to create test payment: " . $testStmt->error . "<br>";
        }
    } else {
        echo "❌ No drivers found in database<br>";
    }
} else {
    echo "✅ Boundary payments already exist, skipping test payment creation<br>";
}

echo "<br><strong>Test completed!</strong>";
?> 