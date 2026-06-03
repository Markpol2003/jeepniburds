<?php
header('Content-Type: application/json');
$data = json_decode(file_get_contents('php://input'), true);
if (!isset($data['passenger_id'], $data['route'])) {
    echo json_encode(['success' => false, 'message' => 'Missing required fields.']);
    exit;
}
// In the future, log this to a table
// For now, just return success

echo json_encode(['success' => true]); 