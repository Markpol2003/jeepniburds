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
if (isset($data['action']) && $data['action'] === 'list') {
    if (!isset($data['operator_id'])) {
        echo json_encode(['success' => false, 'message' => 'Missing operator_id.']);
        exit;
    }
    $operator_id = $data['operator_id'];
    $stmt = $conn->prepare("SELECT bp.*, u.firstName, u.lastName, j.plate_number FROM boundary_payments bp JOIN users u ON bp.driver_id = u.id JOIN jeepney_assignments j ON bp.jeepney_id = j.id WHERE bp.operator_id = ? ORDER BY bp.paid_at DESC");
    $stmt->bind_param('i', $operator_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $boundaries = [];
    while ($row = $result->fetch_assoc()) {
        $boundaries[] = [
            'id' => $row['id'],
            'driver' => $row['firstName'] . ' ' . $row['lastName'],
            'jeepney' => $row['plate_number'],
            'amount' => $row['amount'],
            'payment_method' => $row['payment_method'],
            'paid_at' => $row['paid_at'],
            'status' => $row['status']
        ];
    }
    echo json_encode(['success' => true, 'boundaries' => $boundaries]);
    exit;
}
if (isset($data['action']) && $data['action'] === 'confirm') {
    if (!isset($data['id'])) {
        echo json_encode(['success' => false, 'message' => 'Missing boundary payment id.']);
        exit;
    }
    $id = $data['id'];
    $stmt = $conn->prepare("UPDATE boundary_payments SET status = 'Collected' WHERE id = ?");
    $stmt->bind_param('i', $id);
    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => $stmt->error]);
    }
    exit;
} 