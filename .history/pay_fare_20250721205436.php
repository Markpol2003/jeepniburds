<?php
ob_start();
require_once 'db_config.php';
header('Content-Type: application/json');
$data = json_decode(file_get_contents('php://input'), true);
$action = $data['action'] ?? 'pay';

if ($action === 'pay') {
    if (!isset($data['passenger_id'], $data['route'], $data['amount'], $data['payment_method'])) {
        echo json_encode(['success' => false, 'message' => 'Missing required fields.']);
        file_put_contents('pay_fare_debug.log', ob_get_contents()); ob_end_clean();
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
    file_put_contents('pay_fare_debug.log', ob_get_contents()); ob_end_clean();
    exit;
}

if ($action === 'list') {
    $stmt = $conn->prepare("SELECT fp.*, u.firstName, u.lastName FROM fare_payments fp JOIN users u ON fp.passenger_id = u.id ORDER BY fp.paid_at DESC");
    $stmt->execute();
    $result = $stmt->get_result();
    $fares = [];
    while ($row = $result->fetch_assoc()) {
        $fares[] = [
            'passenger' => $row['firstName'] . ' ' . $row['lastName'],
            'amount' => $row['amount'],
            'payment_method' => $row['payment_method'],
            'status' => $row['status'] ?? 'Paid',
            'paid_at' => $row['paid_at'],
            'receipt_number' => $row['receipt_number'],
            'route' => $row['route']
        ];
    }
    echo json_encode(['success' => true, 'fares' => $fares]);
    file_put_contents('pay_fare_debug.log', ob_get_contents()); ob_end_clean();
    exit;
}

if ($action === 'confirm') {
    if (!isset($data['receipt_number'])) {
        echo json_encode(['success' => false, 'message' => 'Missing receipt number.']);
        file_put_contents('pay_fare_debug.log', ob_get_contents()); ob_end_clean();
        exit;
    }
    $receipt_number = $data['receipt_number'];
    $check = $conn->prepare("SELECT status FROM fare_payments WHERE receipt_number = ?");
    $check->bind_param('s', $receipt_number);
    $check->execute();
    $result = $check->get_result();
    if ($row = $result->fetch_assoc()) {
        if ($row['status'] === 'Collected') {
            echo json_encode(['success' => true, 'message' => 'Already collected.']);
            file_put_contents('pay_fare_debug.log', ob_get_contents()); ob_end_clean();
            exit;
        }
    }
    $stmt = $conn->prepare("UPDATE fare_payments SET status = 'Collected' WHERE receipt_number = ?");
    $stmt->bind_param('s', $receipt_number);
    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => $stmt->error]);
    }
    file_put_contents('pay_fare_debug.log', ob_get_contents()); ob_end_clean();
    exit;
}
file_put_contents('pay_fare_debug.log', ob_get_contents()); ob_end_clean(); 