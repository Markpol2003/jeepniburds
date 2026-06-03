<?php
require_once 'db_config.php';
header('Content-Type: application/json');
$data = json_decode(file_get_contents('php://input'), true);
if (!isset($data['passenger_id'], $data['route'], $data['amount'], $data['payment_method'])) {
    echo json_encode(['success' => false, 'message' => 'Missing required fields.']);
    exit;
}
$passenger_id = $data['passenger_id'];
$route = $data['route'];
$amount = $data['amount'];
$payment_method = $data['payment_method'];
$receipt_number = 'FARE-' . rand(10000,99999);
$stmt = $conn->prepare("INSERT INTO fare_payments (passenger_id, route, amount, payment_method, receipt_number) VALUES (?, ?, ?, ?, ?)");
$stmt->bind_param('isdss', $passenger_id, $route, $amount, $payment_method, $receipt_number);
if ($stmt->execute()) {
    $receipt = [
        'receipt_number' => $receipt_number,
        'route' => $route,
        'amount' => $amount,
        'payment_method' => $payment_method,
        'date' => date('Y-m-d H:i:s'),
        'passenger_id' => $passenger_id
    ];
    echo json_encode(['success' => true, 'receipt' => $receipt]);
} else {
    echo json_encode(['success' => false, 'message' => $stmt->error]);
} 