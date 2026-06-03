<?php
require_once 'db_config.php';

echo "<h2>Fixing Boundary Payment System</h2>";

// Step 1: Check if boundary_payments table exists
$result = $conn->query("SHOW TABLES LIKE 'boundary_payments'");
if ($result->num_rows === 0) {
    echo "Creating boundary_payments table...<br>";
    $createTable = "CREATE TABLE boundary_payments (
        id INT AUTO_INCREMENT PRIMARY KEY,
        driver_id INT NOT NULL,
        operator_id INT NOT NULL,
        jeepney_id INT NOT NULL,
        amount DECIMAL(10,2) NOT NULL,
        payment_method VARCHAR(50) NOT NULL,
        paid_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        status VARCHAR(20) DEFAULT 'Pending'
    )";
    $conn->query($createTable);
    echo "✅ boundary_payments table created<br>";
} else {
    echo "✅ boundary_payments table exists<br>";
}

// Step 2: Get test data
$driverResult = $conn->query("SELECT id FROM users WHERE userType = 'driver' LIMIT 1");
$operatorResult = $conn->query("SELECT id FROM users WHERE userType = 'operator' LIMIT 1");
$assignmentResult = $conn->query("SELECT id FROM jeepney_assignments WHERE status = 'Active' LIMIT 1");

if ($driverResult->num_rows > 0 && $operatorResult->num_rows > 0 && $assignmentResult->num_rows > 0) {
    $driver = $driverResult->fetch_assoc();
    $operator = $operatorResult->fetch_assoc();
    $assignment = $assignmentResult->fetch_assoc();
    
    $driver_id = $driver['id'];
    $operator_id = $operator['id'];
    $jeepney_id = $assignment['id'];
    
    echo "<br>Test Data:<br>";
    echo "- Driver ID: " . $driver_id . "<br>";
    echo "- Operator ID: " . $operator_id . "<br>";
    echo "- Jeepney Assignment ID: " . $jeepney_id . "<br>";
    
    // Step 3: Add test boundary payment
    $stmt = $conn->prepare("INSERT INTO boundary_payments (driver_id, operator_id, jeepney_id, amount, payment_method, status) VALUES (?, ?, ?, ?, ?, 'Pending')");
    $amount = 500;
    $payment_method = 'GCash';
    $stmt->bind_param('iiids', $driver_id, $operator_id, $jeepney_id, $amount, $payment_method);
    
    if ($stmt->execute()) {
        echo "<br>✅ Test boundary payment added successfully!<br>";
        echo "- Amount: ₱" . $amount . "<br>";
        echo "- Method: " . $payment_method . "<br>";
        echo "- Date: " . date('Y-m-d H:i:s') . "<br>";
    } else {
        echo "<br>❌ Failed to add test boundary payment: " . $stmt->error . "<br>";
    }
    
    // Step 4: Test the query that operator dashboard uses
    echo "<br><h3>Testing Operator Query:</h3>";
    $testStmt = $conn->prepare("
        SELECT bp.*, u.firstName, u.lastName, ja.plate_number, ja.body_number, ja.route 
        FROM boundary_payments bp 
        JOIN users u ON bp.driver_id = u.id 
        JOIN jeepney_assignments ja ON bp.jeepney_id = ja.id 
        WHERE bp.operator_id = ? 
        ORDER BY bp.paid_at DESC
    ");
    $testStmt->bind_param('i', $operator_id);
    $testStmt->execute();
    $testResult = $testStmt->get_result();
    
    echo "Query results for operator " . $operator_id . ": " . $testResult->num_rows . "<br>";
    
    if ($testResult->num_rows > 0) {
        echo "<table border='1' style='border-collapse: collapse; margin-top: 10px;'>";
        echo "<tr><th>Driver</th><th>Jeepney</th><th>Route</th><th>Amount</th><th>Method</th><th>Status</th></tr>";
        while ($row = $testResult->fetch_assoc()) {
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
        echo "<br>✅ Boundary payment system is working correctly!<br>";
    } else {
        echo "<br>❌ No results found for operator " . $operator_id . "<br>";
        echo "This means the boundary payments are not appearing in the operator dashboard.<br>";
    }
    
} else {
    echo "<br>❌ Missing required data:<br>";
    echo "- Drivers: " . $driverResult->num_rows . "<br>";
    echo "- Operators: " . $operatorResult->num_rows . "<br>";
    echo "- Active Assignments: " . $assignmentResult->num_rows . "<br>";
}

echo "<br><h3>Next Steps:</h3>";
echo "1. Go to driver dashboard and make a boundary payment<br>";
echo "2. Go to operator dashboard and check 'Collect Boundaries' section<br>";
echo "3. The boundary payment should now appear in the operator's list<br>";
?> 