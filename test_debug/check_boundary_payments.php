<?php
require_once 'db_config.php';

echo "<h2>Checking Boundary Payments</h2>";

// Check if boundary_payments table exists
$result = $conn->query("SHOW TABLES LIKE 'boundary_payments'");
if ($result->num_rows > 0) {
    echo "✅ boundary_payments table exists<br>";
} else {
    echo "❌ boundary_payments table does not exist<br>";
    exit;
}

// Check all boundary payments
$result = $conn->query("SELECT * FROM boundary_payments");
echo "<br>Total boundary payments: " . $result->num_rows . "<br>";

if ($result->num_rows > 0) {
    echo "<table border='1' style='border-collapse: collapse; margin-top: 10px;'>";
    echo "<tr><th>ID</th><th>Driver ID</th><th>Operator ID</th><th>Jeepney ID</th><th>Amount</th><th>Method</th><th>Status</th><th>Date</th></tr>";
    while ($row = $result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . $row['id'] . "</td>";
        echo "<td>" . $row['driver_id'] . "</td>";
        echo "<td>" . $row['operator_id'] . "</td>";
        echo "<td>" . $row['jeepney_id'] . "</td>";
        echo "<td>₱" . $row['amount'] . "</td>";
        echo "<td>" . $row['payment_method'] . "</td>";
        echo "<td>" . $row['status'] . "</td>";
        echo "<td>" . $row['paid_at'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "❌ No boundary payments found in database<br>";
}

// Check jeepney assignments
echo "<br><h3>Jeepney Assignments:</h3>";
$result = $conn->query("SELECT * FROM jeepney_assignments WHERE status = 'Active'");
echo "Active assignments: " . $result->num_rows . "<br>";

if ($result->num_rows > 0) {
    echo "<table border='1' style='border-collapse: collapse; margin-top: 10px;'>";
    echo "<tr><th>ID</th><th>Driver ID</th><th>Operator ID</th><th>Plate</th><th>Body</th><th>Route</th></tr>";
    while ($row = $result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . $row['id'] . "</td>";
        echo "<td>" . $row['driver_id'] . "</td>";
        echo "<td>" . $row['operator_id'] . "</td>";
        echo "<td>" . $row['plate_number'] . "</td>";
        echo "<td>" . $row['body_number'] . "</td>";
        echo "<td>" . $row['route'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "❌ No active jeepney assignments found<br>";
}

// Check users
echo "<br><h3>Users:</h3>";
$result = $conn->query("SELECT id, firstName, lastName, userType FROM users WHERE userType IN ('driver', 'operator')");
echo "Drivers and Operators: " . $result->num_rows . "<br>";

if ($result->num_rows > 0) {
    echo "<table border='1' style='border-collapse: collapse; margin-top: 10px;'>";
    echo "<tr><th>ID</th><th>Name</th><th>Type</th></tr>";
    while ($row = $result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . $row['id'] . "</td>";
        echo "<td>" . $row['firstName'] . " " . $row['lastName'] . "</td>";
        echo "<td>" . $row['userType'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
}
?> 