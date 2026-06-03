<?php
header('Content-Type: application/json');
$data = json_decode(file_get_contents('php://input'), true);
if (!isset($data['passenger_id'], $data['route'], $data['amount'], $data['payment_method'])) {
    echo json_encode(['success' => false, 'message' => 'Missing required fields.']);
    exit;
}
// In the future, log this to a table and generate a real receipt
$receipt = [
    'receipt_number' => 'FARE-' . rand(10000,99999),
    'route' => $data['route'],
    'amount' => $data['amount'],
    'payment_method' => $data['payment_method'],
    'date' => date('Y-m-d H:i:s'),
    'passenger_id' => $data['passenger_id']
];
echo json_encode(['success' => true, 'receipt' => $receipt]); 