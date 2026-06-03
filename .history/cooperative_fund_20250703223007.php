<?php
require_once 'db_config.php';
header('Content-Type: application/json');
$data = json_decode(file_get_contents('php://input'), true);
$action = $data['action'] ?? '';
if ($action === 'contribute') {
    if (!isset($data['member_id'], $data['amount'], $data['payment_method'])) {
        echo json_encode(['success' => false, 'message' => 'Missing required fields.']);
        exit;
    }
    $stmt = $conn->prepare("INSERT INTO cooperative_fund_payments (member_id, amount, payment_method) VALUES (?, ?, ?)");
    $stmt->bind_param('ids', $data['member_id'], $data['amount'], $data['payment_method']);
    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => $stmt->error]);
    }
    exit;
}
if ($action === 'list') {
    $stmt = $conn->prepare("SELECT cfp.*, u.firstName, u.lastName FROM cooperative_fund_payments cfp JOIN users u ON cfp.member_id = u.id ORDER BY cfp.paid_at DESC");
    $stmt->execute();
    $result = $stmt->get_result();
    $funds = [];
    while ($row = $result->fetch_assoc()) {
        $funds[] = [
            'id' => $row['id'],
            'member' => $row['firstName'] . ' ' . $row['lastName'],
            'amount' => $row['amount'],
            'payment_method' => $row['payment_method'],
            'paid_at' => $row['paid_at'],
            'status' => $row['status']
        ];
    }
    echo json_encode(['success' => true, 'funds' => $funds]);
    exit;
}
if ($action === 'confirm') {
    if (!isset($data['id'])) {
        echo json_encode(['success' => false, 'message' => 'Missing payment id.']);
        exit;
    }
    $id = $data['id'];
    $stmt = $conn->prepare("UPDATE cooperative_fund_payments SET status = 'Collected' WHERE id = ?");
    $stmt->bind_param('i', $id);
    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => $stmt->error]);
    }
    exit;
} 