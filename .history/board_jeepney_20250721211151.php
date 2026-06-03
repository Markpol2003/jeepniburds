<?php
require_once 'db_config.php';
header('Content-Type: application/json');
$data = json_decode(file_get_contents('php://input'), true);

// New: List boarding events for a route
if (isset($data['action']) && $data['action'] === 'list' && isset($data['route'])) {
    $route = $data['route'];
    $stmt = $conn->prepare("SELECT b.passenger_id, u.firstName, u.lastName, b.boarded_at AS event_time, 'Boarded' AS event_type FROM boarding_events b JOIN users u ON b.passenger_id = u.id WHERE b.route = ? ORDER BY b.boarded_at DESC");
    $stmt->bind_param('s', $route);
    $stmt->execute();
    $result = $stmt->get_result();
    $events = [];
    while ($row = $result->fetch_assoc()) {
        $events[] = [
            'passenger' => $row['firstName'] . ' ' . $row['lastName'],
            'event_type' => $row['event_type'],
            'event_time' => $row['event_time']
        ];
    }
    echo json_encode(['success' => true, 'events' => $events]);
    exit;
}

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