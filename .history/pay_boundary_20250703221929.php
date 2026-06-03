<?php
require_once 'db_config.php';
header('Content-Type: application/json');
$data = json_decode(file_get_contents('php://input'), true);
if (!isset($data['driver_id'], $data['operator_id'], $data['jeepney_id'], $data['amount'], $data['payment_method'])) {
    echo json_encode(['success' => false, 'message' => 'Missing required fields.']);
    exit;
}
$stmt = $conn->prepare("INSERT INTO boundary_payments (driver_id, operator_id, jeepney_id, amount, payment_method) VALUES (?, ?, ?, ?, ?)");
$stmt->bind_param('iiids', $data['driver_id'], $data['operator_id'], $data['jeepney_id'], $data['amount'], $data['payment_method']);
if ($stmt->execute()) {
    echo json_encode(['success' => true, 'receipt' => [
        'amount' => $data['amount'],
        'payment_method' => $data['payment_method'],
        'date' => date('Y-m-d H:i:s')
    ]]);
} else {
    echo json_encode(['success' => false, 'message' => $stmt->error]);
} 