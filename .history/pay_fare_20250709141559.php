<?php
require_once 'db_config.php';
header('Content-Type: application/json');
$data = json_decode(file_get_contents('php://input'), true);
$action = $data['action'] ?? 'pay';

if ($action === 'pay') {
    if (!isset($data['passenger_id'], $data['route'], $data['amount'], $data['payment_method'])) {
        echo json_encode(['success' => false, 'message' => 'Missing required fields.']);
        exit;
    }
    $passenger_id = $data['passenger_id'];
    $route = $data['route'];
    $amount = $data['amount'];
    $payment_method = $data['payment_method'];
    $receipt_number = 'FARE-' . rand(10000,99999);
    $paid_at = date('Y-m-d H:i:s');
    $status = 'Paid';
    $stmt = $conn->prepare("INSERT INTO fare_payments (passenger_id, route, amount, payment_method, receipt_number, paid_at, status) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param('isdssss', $passenger_id, $route, $amount, $payment_method, $receipt_number, $paid_at, $status);
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'receipt' => [
            'receipt_number' => $receipt_number,
            'route' => $route,
            'amount' => $amount,
            'payment_method' => $payment_method,
            'date' => date('Y-m-d H:i:s'),
            'passenger_id' => $passenger_id
        ]]);
    } else {
        echo json_encode(['success' => false, 'message' => $stmt->error]);
    }
    exit;
}

if ($action === 'list') {
    // List all fare payments for a given route (driver's assigned route)
    if (!isset($data['route'])) {
        echo json_encode(['success' => false, 'message' => 'Missing route.']);
        exit;
    }
    $route = $data['route'];
    $stmt = $conn->prepare("SELECT fp.*, u.firstName, u.lastName FROM fare_payments fp JOIN users u ON fp.passenger_id = u.id WHERE fp.route = ? ORDER BY fp.paid_at DESC");
    $stmt->bind_param('s', $route);
    $stmt->execute();
    $result = $stmt->get_result();
    $fares = [];
    while ($row = $result->fetch_assoc()) {
        $fares[] = [
            'passenger' => $row['firstName'] . ' ' . $row['lastName'],
            'amount' => $row['amount'],
            'payment_method' => $row['payment_method'],
            'status' => 'Paid',
            'paid_at' => $row['paid_at'],
            'receipt_number' => $row['receipt_number']
        ];
    }
    echo json_encode(['success' => true, 'fares' => $fares]);
    exit;
}

if ($action === 'confirm') {
    if (!isset($data['receipt_number'])) {
        echo json_encode(['success' => false, 'message' => 'Missing receipt number.']);
        exit;
    }
    $receipt_number = $data['receipt_number'];
    $stmt = $conn->prepare("UPDATE fare_payments SET status = 'Collected' WHERE receipt_number = ?");
    $stmt->bind_param('s', $receipt_number);
    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => $stmt->error]);
    }
    exit;
} 