<?php
require_once 'db_config.php';
session_start();

echo "<h2>Boundary Payment Debug</h2>";

// Get current user info
$userId = $_SESSION['user_id'] ?? 'Not logged in';
$userType = $_SESSION['user_type'] ?? 'Unknown';
echo "Current User ID: " . $userId . " (Type: " . $userType . ")<br><br>";

// Check if user is a driver
if ($userType !== 'driver') {
    echo "❌ This script is for drivers only. Current user type: " . $userType . "<br>";
    exit;
}

// Fetch assigned jeepney details
$jeepneyQuery = "
    SELECT ja.*, u.firstName, u.lastName, o.id as operator_id, o.firstName as operator_firstName, o.lastName as operator_lastName
    FROM jeepney_assignments ja
    JOIN users u ON ja.driver_id = u.id
    JOIN users o ON ja.operator_id = o.id
    WHERE ja.driver_id = ? AND ja.status = 'Active'
    ORDER BY ja.assigned_date DESC
    LIMIT 1
";

$stmt = $conn->prepare($jeepneyQuery);
$stmt->bind_param("i", $userId);
$stmt->execute();
$jeepneyResult = $stmt->get_result();
$assignedJeepney = $jeepneyResult->fetch_assoc();

echo "<h3>Driver Assignment Details:</h3>";
if ($assignedJeepney) {
    echo "✅ Driver has assigned jeepney<br>";
    echo "- Assignment ID: " . $assignedJeepney['id'] . "<br>";
    echo "- Driver ID: " . $assignedJeepney['driver_id'] . "<br>";
    echo "- Operator ID: " . $assignedJeepney['operator_id'] . "<br>";
    echo "- Operator Name: " . $assignedJeepney['operator_firstName'] . " " . $assignedJeepney['operator_lastName'] . "<br>";
    echo "- Jeepney Plate: " . $assignedJeepney['plate_number'] . "<br>";
    echo "- Route: " . $assignedJeepney['route'] . "<br>";
} else {
    echo "❌ Driver has no assigned jeepney<br>";
    exit;
}

// Check existing boundary payments for this driver
echo "<h3>Existing Boundary Payments for Driver:</h3>";
$boundaryQuery = "
    SELECT bp.*, u.firstName, u.lastName, o.firstName as operator_firstName, o.lastName as operator_lastName
    FROM boundary_payments bp
    JOIN users u ON bp.driver_id = u.id
    JOIN users o ON bp.operator_id = o.id
    WHERE bp.driver_id = ?
    ORDER BY bp.paid_at DESC
";

$stmt = $conn->prepare($boundaryQuery);
$stmt->bind_param("i", $userId);
$stmt->execute();
$boundaryResult = $stmt->get_result();

if ($boundaryResult->num_rows > 0) {
    echo "Found " . $boundaryResult->num_rows . " boundary payments:<br>";
    echo "<table border='1' style='border-collapse: collapse; margin-top: 10px;'>";
    echo "<tr><th>ID</th><th>Amount</th><th>Method</th><th>Status</th><th>Operator</th><th>Date</th><th>Reference</th></tr>";
    
    while ($payment = $boundaryResult->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . $payment['id'] . "</td>";
        echo "<td>₱" . $payment['amount'] . "</td>";
        echo "<td>" . $payment['payment_method'] . "</td>";
        echo "<td>" . $payment['status'] . "</td>";
        echo "<td>" . $payment['operator_firstName'] . " " . $payment['operator_lastName'] . "</td>";
        echo "<td>" . $payment['paid_at'] . "</td>";
        echo "<td>" . $payment['reference_number'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "No boundary payments found for this driver.<br>";
}

// Test creating a boundary payment
echo "<h3>Test Boundary Payment Creation:</h3>";
$testData = [
    'driver_id' => $userId,
    'operator_id' => $assignedJeepney['operator_id'],
    'jeepney_id' => $assignedJeepney['id'],
    'amount' => 500,
    'payment_method' => 'Cash',
    'notes' => 'Test payment from debug script'
];

echo "Test data:<br>";
echo "- Driver ID: " . $testData['driver_id'] . "<br>";
echo "- Operator ID: " . $testData['operator_id'] . "<br>";
echo "- Jeepney ID: " . $testData['jeepney_id'] . "<br>";
echo "- Amount: ₱" . $testData['amount'] . "<br>";
echo "- Method: " . $testData['payment_method'] . "<br>";

// Generate reference number
$referenceNumber = 'BND-' . date('Ymd') . '-' . str_pad($userId, 4, '0', STR_PAD_LEFT);

try {
    $stmt = $conn->prepare("INSERT INTO boundary_payments (driver_id, operator_id, jeepney_id, amount, payment_method, status, reference_number, notes) VALUES (?, ?, ?, ?, ?, 'Pending', ?, ?)");
    $stmt->bind_param('iiidsss', $testData['driver_id'], $testData['operator_id'], $testData['jeepney_id'], $testData['amount'], $testData['payment_method'], $referenceNumber, $testData['notes']);
    
    if ($stmt->execute()) {
        echo "✅ Test boundary payment created successfully!<br>";
        echo "Reference Number: " . $referenceNumber . "<br>";
        echo "Payment ID: " . $conn->insert_id . "<br>";
    } else {
        echo "❌ Failed to create test payment: " . $stmt->error . "<br>";
    }
} catch (Exception $e) {
    echo "❌ Error creating test payment: " . $e->getMessage() . "<br>";
}

// Check if the payment appears for the operator
echo "<h3>Checking Operator Dashboard:</h3>";
$operator_id = $assignedJeepney['operator_id'];

// Test the API endpoint
$listData = [
    'action' => 'list',
    'operator_id' => $operator_id
];

echo "Testing API for operator_id: " . $operator_id . "<br>";

// Direct database query to check
$operatorPaymentsQuery = "
    SELECT bp.*, u.firstName, u.lastName
    FROM boundary_payments bp 
    JOIN users u ON bp.driver_id = u.id 
    WHERE bp.operator_id = ? 
    ORDER BY bp.paid_at DESC
";

$stmt = $conn->prepare($operatorPaymentsQuery);
$stmt->bind_param('i', $operator_id);
$stmt->execute();
$operatorPayments = $stmt->get_result();

echo "Payments for operator " . $operator_id . ": " . $operatorPayments->num_rows . "<br>";

if ($operatorPayments->num_rows > 0) {
    echo "✅ Operator can see boundary payments<br>";
    while ($payment = $operatorPayments->fetch_assoc()) {
        echo "- Payment ID: " . $payment['id'] . " | Driver: " . $payment['firstName'] . " " . $payment['lastName'] . " | Amount: ₱" . $payment['amount'] . " | Status: " . $payment['status'] . "<br>";
    }
} else {
    echo "❌ Operator cannot see any boundary payments<br>";
}

echo "<br><strong>Debug completed!</strong>";
?> 