<?php
session_start();
require_once 'db_config.php';

echo "<h2>🔍 Operator Session Check</h2>";

// Check session data
echo "<h3>1. Session Data</h3>";
echo "Session user_id: " . ($_SESSION['user_id'] ?? 'NOT SET') . "<br>";
echo "Session user_type: " . ($_SESSION['user_type'] ?? 'NOT SET') . "<br>";
echo "Session user_firstName: " . ($_SESSION['user_firstName'] ?? 'NOT SET') . "<br>";

// Check if user is operator
if (isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'operator') {
    echo "✅ User is operator<br>";
    
    $operator_id = $_SESSION['user_id'];
    echo "Operator ID from session: $operator_id<br>";
    
    // Verify operator exists in database
    $operatorCheck = $conn->prepare("SELECT id, firstName, lastName FROM users WHERE id = ? AND userType = 'operator'");
    $operatorCheck->bind_param("i", $operator_id);
    $operatorCheck->execute();
    $operatorResult = $operatorCheck->get_result();
    
    if ($operatorResult->num_rows > 0) {
        $operator = $operatorResult->fetch_assoc();
        echo "✅ Operator found in database: {$operator['firstName']} {$operator['lastName']}<br>";
        
        // Check boundary payments for this operator
        $payments = $conn->prepare("SELECT COUNT(*) as count FROM boundary_payments WHERE operator_id = ?");
        $payments->bind_param("i", $operator_id);
        $payments->execute();
        $paymentCount = $payments->get_result()->fetch_assoc()['count'];
        
        echo "Boundary payments for operator $operator_id: $paymentCount<br>";
        
        if ($paymentCount > 0) {
            echo "<h3>2. Boundary Payments for This Operator</h3>";
            $paymentDetails = $conn->prepare("
                SELECT bp.*, u.firstName, u.lastName 
                FROM boundary_payments bp 
                JOIN users u ON bp.driver_id = u.id 
                WHERE bp.operator_id = ? 
                ORDER BY bp.paid_at DESC
            ");
            $paymentDetails->bind_param("i", $operator_id);
            $paymentDetails->execute();
            $result = $paymentDetails->get_result();
            
            while ($payment = $result->fetch_assoc()) {
                echo "- Driver: {$payment['firstName']} {$payment['lastName']}, Amount: ₱{$payment['amount']}, Status: {$payment['status']}<br>";
            }
        }
        
        // Test the API call
        echo "<h3>3. Testing API Call</h3>";
        echo "<div id='apiTest'></div>";
        echo "<script>";
        echo "const operator_id = $operator_id;";
        echo "console.log('Testing API with operator_id:', operator_id);";
        echo "fetch('pay_boundary.php', {";
        echo "    method: 'POST',";
        echo "    headers: { 'Content-Type': 'application/json' },";
        echo "    body: JSON.stringify({ action: 'list', operator_id })";
        echo "})";
        echo ".then(res => res.json())";
        echo ".then(data => {";
        echo "    console.log('API Response:', data);";
        echo "    document.getElementById('apiTest').innerHTML = '<strong>API Response:</strong><br><pre>' + JSON.stringify(data, null, 2) + '</pre>';";
        echo "})";
        echo ".catch(err => {";
        echo "    console.error('API Error:', err);";
        echo "    document.getElementById('apiTest').innerHTML = '<strong>API Error:</strong><br>' + err.message;";
        echo "});";
        echo "</script>";
        
    } else {
        echo "❌ Operator not found in database<br>";
    }
} else {
    echo "❌ User is not operator or session is invalid<br>";
}

// Check all operators
echo "<h3>4. All Operators in System</h3>";
$allOperators = $conn->query("SELECT id, firstName, lastName FROM users WHERE userType = 'operator'");
while ($op = $allOperators->fetch_assoc()) {
    echo "- ID: {$op['id']}, Name: {$op['firstName']} {$op['lastName']}<br>";
}

echo "<h3>5. All Boundary Payments</h3>";
$allPayments = $conn->query("SELECT bp.*, u.firstName, u.lastName FROM boundary_payments bp JOIN users u ON bp.driver_id = u.id ORDER BY bp.paid_at DESC");
while ($payment = $allPayments->fetch_assoc()) {
    echo "- Driver: {$payment['firstName']} {$payment['lastName']}, Operator: {$payment['operator_id']}, Amount: ₱{$payment['amount']}, Status: {$payment['status']}<br>";
}

echo "<h3>Check Complete</h3>";
echo "<p><a href='operator_dashboard.php?page=collect_boundaries'>Go to Operator Dashboard</a></p>";
?> 