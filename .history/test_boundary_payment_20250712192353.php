<?php
require_once 'db_config.php';

echo "<h2>Test Boundary Payment</h2>";

// Get current operator ID from session
session_start();
$operator_id = $_SESSION['user_id'] ?? 'Not logged in';
echo "Current Operator ID: " . $operator_id . "<br>";

// Test the boundary payment API
$testData = [
    'driver_id' => 1, // Assuming driver ID 1
    'operator_id' => $operator_id,
    'jeepney_id' => 1, // Assuming assignment ID 1
    'amount' => 500,
    'payment_method' => 'GCash'
];

echo "<br>Testing boundary payment with data:<br>";
echo "- Driver ID: " . $testData['driver_id'] . "<br>";
echo "- Operator ID: " . $testData['operator_id'] . "<br>";
echo "- Jeepney ID: " . $testData['jeepney_id'] . "<br>";
echo "- Amount: ₱" . $testData['amount'] . "<br>";
echo "- Method: " . $testData['payment_method'] . "<br>";

// Simulate the API call
$stmt = $conn->prepare("INSERT INTO boundary_payments (driver_id, operator_id, jeepney_id, amount, payment_method, status) VALUES (?, ?, ?, ?, ?, 'Pending')");
$stmt->bind_param('iiids', $testData['driver_id'], $testData['operator_id'], $testData['jeepney_id'], $testData['amount'], $testData['payment_method']);

if ($stmt->execute()) {
    echo "<br>✅ Test boundary payment added successfully!<br>";
    
    // Now test if it appears in the operator's list
    $listData = [
        'action' => 'list',
        'operator_id' => $operator_id
    ];
    
    echo "<br>Testing if payment appears in operator's list...<br>";
    
    $stmt = $conn->prepare("
        SELECT bp.*, u.firstName, u.lastName, ja.plate_number, ja.body_number, ja.route 
        FROM boundary_payments bp 
        JOIN users u ON bp.driver_id = u.id 
        JOIN jeepney_assignments ja ON bp.jeepney_id = ja.id 
        WHERE bp.operator_id = ? 
        ORDER BY bp.paid_at DESC
    ");
    $stmt->bind_param('i', $operator_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    echo "Found " . $result->num_rows . " boundary payments for operator " . $operator_id . "<br>";
    
    if ($result->num_rows > 0) {
        echo "<table border='1' style='border-collapse: collapse; margin-top: 10px;'>";
        echo "<tr><th>Driver</th><th>Jeepney</th><th>Route</th><th>Amount</th><th>Method</th><th>Status</th></tr>";
        while ($row = $result->fetch_assoc()) {
            echo "<tr>";
            echo "<td>" . $row['firstName'] . " " . $row['lastName'] . "</td>";
            echo "<td>" . $row['plate_number'] . " (" . $row['body_number'] . ")</td>";
            echo "<td>" . $row['route'] . "</td>";
            echo "<td>₱" . $row['amount'] . "</td>";
            echo "<td>" . $row['payment_method'] . "</td>";
            echo "<td>" . $row['status'] . "</td>";
            echo "</tr>";
        }
        echo "</table>";
        echo "<br>✅ Boundary payment system is working!<br>";
    } else {
        echo "<br>❌ No boundary payments found for operator " . $operator_id . "<br>";
        echo "This means there's an issue with the query or data.<br>";
    }
    
} else {
    echo "<br>❌ Failed to add test boundary payment: " . $stmt->error . "<br>";
}

echo "<br><a href='operator_dashboard.php?page=collect_boundaries'>Go to Operator Dashboard - Collect Boundaries</a>";
?> 