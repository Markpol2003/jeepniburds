<?php
require_once 'db_config.php';
session_start();
$operatorId = $_SESSION['user_id'] ?? null;
if (!$operatorId) { echo 'Not logged in as operator.'; exit; }
echo "<h2>Operator ID: $operatorId</h2>";
$result = $conn->query("SELECT * FROM boundary_payments WHERE operator_id = $operatorId ORDER BY paid_at DESC");
echo "<h3>Found " . $result->num_rows . " boundary payments for this operator.</h3>";
while ($row = $result->fetch_assoc()) {
    echo "- ID: {$row['id']}, Driver: {$row['driver_id']}, Amount: {$row['amount']}, Status: {$row['status']}, Date: {$row['paid_at']}<br>";
}
?> 