<?php
require_once 'db_config.php';

// Test fare payment data
$testData = [
    'passenger_id' => 1, // Assuming passenger ID 1 exists
    'route' => 'toril', // The route that should match the driver's assigned route
    'amount' => 25.00,
    'payment_method' => 'Cash',
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
    echo "Test fare payment added successfully!\n";
    echo "Receipt Number: " . $testData['receipt_number'] . "\n";
    echo "Route: " . $testData['route'] . "\n";
    echo "Amount: ₱" . $testData['amount'] . "\n";
    echo "Payment Method: " . $testData['payment_method'] . "\n";
    echo "Status: " . $testData['status'] . "\n";
    echo "\nNow go to the driver dashboard and check the 'Collect Fares' section.\n";
} else {
    echo "Error adding test fare payment: " . $stmt->error . "\n";
}

// Also show current fare payments for the route
echo "\nCurrent fare payments for route 'toril':\n";
$stmt = $conn->prepare("SELECT fp.*, u.firstName, u.lastName FROM fare_payments fp JOIN users u ON fp.passenger_id = u.id WHERE fp.route = 'toril' ORDER BY fp.paid_at DESC");
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        echo "- " . $row['firstName'] . " " . $row['lastName'] . " paid ₱" . $row['amount'] . " via " . $row['payment_method'] . " (Receipt: " . $row['receipt_number'] . ", Status: " . $row['status'] . ")\n";
    }
} else {
    echo "No fare payments found for route 'toril'\n";
}
?> 