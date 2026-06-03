<?php
require_once 'db_config.php';

// Test fare payment data for Route 2
$testData = [
    'passenger_id' => 1, // Assuming passenger ID 1 exists
    'route' => 'Route 2', // The route that should now appear to drivers
    'amount' => 30.00,
    'payment_method' => 'GCash',
    'receipt_number' => 'FARE-' . rand(10000, 99999),
    'paid_at' => date('Y-m-d H:i:s'),
    'status' => 'Paid'
];

// Insert test fare payment
$stmt = $conn->prepare("INSERT INTO fare_payments (passenger_id, route, amount, payment_method, receipt_number, paid_at, status) VALUES (?, ?, ?, ?, ?, ?, ?)");
$stmt->bind_param('isdssss', 
    $testData['passenger_id'], 
    $testData['route'], 
    $testData['amount'], 
    $testData['payment_method'], 
    $testData['receipt_number'], 
    $testData['paid_at'], 
    $testData['status']
);

if ($stmt->execute()) {
    echo "✅ Test fare payment for Route 2 added successfully!\n";
    echo "📄 Receipt Number: " . $testData['receipt_number'] . "\n";
    echo "🛣️  Route: " . $testData['route'] . "\n";
    echo "💰 Amount: ₱" . $testData['amount'] . "\n";
    echo "💳 Payment Method: " . $testData['payment_method'] . "\n";
    echo "📅 Status: " . $testData['status'] . "\n";
    echo "\n🎯 Now go to the driver dashboard and check the 'All Fare Payments' section.\n";
    echo "📊 You should now see ALL fare payments regardless of route!\n";
} else {
    echo "❌ Error adding test fare payment: " . $stmt->error . "\n";
}

// Show all current fare payments
echo "\n📋 Current fare payments in the system:\n";
$stmt = $conn->prepare("SELECT fp.*, u.firstName, u.lastName FROM fare_payments fp JOIN users u ON fp.passenger_id = u.id ORDER BY fp.paid_at DESC");
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    echo "┌─────────────────┬─────────────┬─────────┬─────────────┬─────────────────────┬─────────────┐\n";
    echo "│ Passenger       │ Route       │ Amount  │ Method      │ Receipt Number      │ Status      │\n";
    echo "├─────────────────┼─────────────┼─────────┼─────────────┼─────────────────────┼─────────────┤\n";
    
    while ($row = $result->fetch_assoc()) {
        $passenger = $row['firstName'] . ' ' . $row['lastName'];
        $route = $row['route'];
        $amount = '₱' . $row['amount'];
        $method = $row['payment_method'];
        $receipt = $row['receipt_number'];
        $status = $row['status'];
        
        printf("│ %-15s │ %-11s │ %-7s │ %-11s │ %-19s │ %-11s │\n", 
               substr($passenger, 0, 15), 
               substr($route, 0, 11), 
               $amount, 
               substr($method, 0, 11), 
               substr($receipt, 0, 19), 
               $status);
    }
    echo "└─────────────────┴─────────────┴─────────┴─────────────┴─────────────────────┴─────────────┘\n";
} else {
    echo "No fare payments found in the system.\n";
}
?> 