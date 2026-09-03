<?php
require_once __DIR__ . '/../db_config.php';
require_once __DIR__ . '/../includes/security.php';
jeepnigo_require_role(['driver']);
jeepnigo_require_csrf();
header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);
if (!isset($data['jeepney_id'], $data['route'], $data['lat'], $data['lng'])) {
    echo json_encode(['success' => false, 'message' => 'Missing required fields.']);
    exit;
}
$jeepney_id = $data['jeepney_id'];
$route = $data['route'];
$lat = $data['lat'];
$lng = $data['lng'];

// Upsert logic
$stmt = $conn->prepare("INSERT INTO jeepney_locations (jeepney_id, route, lat, lng, updated_at) VALUES (?, ?, ?, ?, NOW()) ON DUPLICATE KEY UPDATE lat=VALUES(lat), lng=VALUES(lng), updated_at=NOW(), route=VALUES(route)");
$stmt->bind_param('isdd', $jeepney_id, $route, $lat, $lng);
if ($stmt->execute()) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'message' => $stmt->error]);
} 
