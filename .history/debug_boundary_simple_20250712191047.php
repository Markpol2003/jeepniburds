<?php
require_once 'db_config.php';

echo "<h2>Simple Boundary Debug</h2>";

// Check boundary payments
$result = $conn->query("SELECT * FROM boundary_payments");
echo "Total boundary payments: " . $result->num_rows . "<br>";

if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        echo "Payment ID: " . $row['id'] . 
             " | Driver: " . $row['driver_id'] . 
             " | Operator: " . $row['operator_id'] . 
             " | Jeepney ID: " . $row['jeepney_id'] . 
             " | Amount: ₱" . $row['amount'] . 
             " | Method: " . $row['payment_method'] . 
             " | Status: " . $row['status'] . "<br>";
    }
}

// Check jeepney assignments
$result = $conn->query("SELECT * FROM jeepney_assignments WHERE status = 'Active'");
echo "<br>Active jeepney assignments: " . $result->num_rows . "<br>";

if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        echo "Assignment ID: " . $row['id'] . 
             " | Driver: " . $row['driver_id'] . 
             " | Operator: " . $row['operator_id'] . 
             " | Plate: " . $row['plate_number'] . 
             " | Route: " . $row['route'] . "<br>";
    }
}

// Test the exact query from pay_boundary.php
echo "<br><h3>Testing the exact query:</h3>";
$operator_id = 1; // Test with operator 1
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

echo "Query results for operator " . $operator_id . ": " . $result->num_rows . "<br>";

if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        echo "Found: " . $row['firstName'] . " " . $row['lastName'] . 
             " | Jeepney: " . $row['plate_number'] . 
             " | Route: " . $row['route'] . 
             " | Amount: ₱" . $row['amount'] . "<br>";
    }
} else {
    echo "No results found for operator " . $operator_id . "<br>";
}
?> 