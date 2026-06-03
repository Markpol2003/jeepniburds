<?php
require_once 'db_config.php';
header('Content-Type: application/json');
$data = json_decode(file_get_contents('php://input'), true);
if (!isset($data['passenger_id'], $data['route'])) {
    echo json_encode(['success' => false, 'message' => 'Missing required fields.']);
    exit;
}
$passenger_id = $data['passenger_id'];
$route = $data['route'];
$stmt = $conn->prepare("INSERT INTO boarding_events (passenger_id, route) VALUES (?, ?)");
$stmt->bind_param('is', $passenger_id, $route);
if ($stmt->execute()) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'message' => $stmt->error]);
} 