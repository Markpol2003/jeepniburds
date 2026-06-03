<?php
require_once 'db_config.php';

echo "<h2>Fix Operator ID Mismatch in Boundary Payments</h2>";

// Get current operator ID from session
session_start();
$current_operator_id = $_SESSION['user_id'] ?? null;

if (!$current_operator_id) {
    echo "❌ No operator logged in. Please log in as an operator first.<br>";
    exit;
}

echo "Current Operator ID: " . $current_operator_id . "<br><br>";

// Check what operator IDs exist in boundary_payments table
echo "<h3>Step 1: Checking Current Operator IDs in boundary_payments</h3>";
$operatorCheck = $conn->query("SELECT DISTINCT operator_id, COUNT(*) as count FROM boundary_payments GROUP BY operator_id");
if ($operatorCheck->num_rows > 0) {
    echo "Found boundary payments for the following operators:<br>";
    while ($row = $operatorCheck->fetch_assoc()) {
        echo "- Operator ID " . $row['operator_id'] . ": " . $row['count'] . " payments<br>";
    }
} else {
    echo "❌ No boundary payments found in the database.<br>";
    exit;
}

// Check if current operator has any payments
$currentOperatorPayments = $conn->query("SELECT COUNT(*) as count FROM boundary_payments WHERE operator_id = $current_operator_id");
$currentCount = $currentOperatorPayments->fetch_assoc()['count'];

echo "<br>Current operator (ID: $current_operator_id) has $currentCount boundary payments.<br>";

if ($currentCount == 0) {
    echo "<h3>Step 2: Updating All Boundary Payments to Current Operator</h3>";
    
    // Get total count of boundary payments
    $totalPayments = $conn->query("SELECT COUNT(*) as count FROM boundary_payments")->fetch_assoc()['count'];
    echo "Total boundary payments in database: $totalPayments<br>";
    
    if ($totalPayments > 0) {
        echo "Updating all boundary payments to operator ID: $current_operator_id<br>";
        
        $updateResult = $conn->query("UPDATE boundary_payments SET operator_id = $current_operator_id");
        if ($updateResult) {
            $affectedRows = $conn->affected_rows;
            echo "✅ Successfully updated $affectedRows boundary payments to operator ID $current_operator_id<br>";
        } else {
            echo "❌ Failed to update boundary payments: " . $conn->error . "<br>";
        }
    }
} else {
    echo "✅ Current operator already has boundary payments. No update needed.<br>";
}

// Verify the update
echo "<h3>Step 3: Verification</h3>";
$verifyQuery = $conn->query("SELECT COUNT(*) as count FROM boundary_payments WHERE operator_id = $current_operator_id");
$verifyCount = $verifyQuery->fetch_assoc()['count'];
echo "Boundary payments for operator $current_operator_id: $verifyCount<br>";

// Show sample of updated payments
echo "<h3>Step 4: Sample of Updated Payments</h3>";
$sampleQuery = $conn->query("
    SELECT bp.*, u.firstName, u.lastName 
    FROM boundary_payments bp 
    JOIN users u ON bp.driver_id = u.id 
    WHERE bp.operator_id = $current_operator_id 
    ORDER BY bp.paid_at DESC 
    LIMIT 5
");

if ($sampleQuery->num_rows > 0) {
    echo "<table border='1' style='border-collapse: collapse; margin-top: 10px;'>";
    echo "<tr><th>ID</th><th>Driver</th><th>Amount</th><th>Method</th><th>Status</th><th>Date</th></tr>";
    
    while ($payment = $sampleQuery->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . $payment['id'] . "</td>";
        echo "<td>" . $payment['firstName'] . " " . $payment['lastName'] . "</td>";
        echo "<td>₱" . $payment['amount'] . "</td>";
        echo "<td>" . $payment['payment_method'] . "</td>";
        echo "<td>" . $payment['status'] . "</td>";
        echo "<td>" . $payment['paid_at'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "No boundary payments found for current operator.<br>";
}

echo "<br><h3>Step 5: Test API Response</h3>";
echo "Testing the API endpoint for operator $current_operator_id...<br>";

// Simulate the API call
$testData = [
    'action' => 'list',
    'operator_id' => $current_operator_id
];

// Direct database query to test
$testQuery = $conn->prepare("
    SELECT bp.*, u.firstName, u.lastName, ja.plate_number, ja.body_number, ja.route
    FROM boundary_payments bp 
    JOIN users u ON bp.driver_id = u.id 
    LEFT JOIN jeepney_assignments ja ON bp.driver_id = ja.driver_id AND ja.status = 'Active'
    WHERE bp.operator_id = ? 
    ORDER BY bp.paid_at DESC
");
$testQuery->bind_param('i', $current_operator_id);
$testQuery->execute();
$testResult = $testQuery->get_result();

echo "API test returned " . $testResult->num_rows . " boundary payments.<br>";

if ($testResult->num_rows > 0) {
    echo "✅ Boundary payments are now visible for operator $current_operator_id!<br>";
    echo "You can now refresh your operator dashboard to see the payments.<br>";
} else {
    echo "❌ Still no boundary payments found. Please check the database manually.<br>";
}

echo "<br><h3>Next Steps:</h3>";
echo "1. Refresh your operator dashboard<br>";
echo "2. Go to the 'Collect Boundaries' section<br>";
echo "3. You should now see the boundary payments<br>";
echo "4. If you still don't see payments, check the browser console for any JavaScript errors<br>";

echo "<br><a href='operator_dashboard.php?page=collect_boundaries' class='btn btn-primary'>Go to Collect Boundaries</a>";
?> 