<?php
header('Content-Type: application/json');
$data = json_decode(file_get_contents('php://input'), true);
if (!isset($data['route'])) {
    echo json_encode(['success' => false, 'message' => 'Missing route.']);
    exit;
}
// In the future, log this or notify the driver
// For now, just return success

echo json_encode(['success' => true]); 