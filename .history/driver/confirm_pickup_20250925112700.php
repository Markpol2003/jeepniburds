<?php
header('Content-Type: application/json');

$dataDir = __DIR__ . '/../data';
$storeFile = $dataDir . '/waiting_requests.json';

if (!is_dir($dataDir)) {
    @mkdir($dataDir, 0777, true);
}
if (!file_exists($storeFile)) {
    @file_put_contents($storeFile, json_encode([]));
}

function readStore($file) {
    $raw = @file_get_contents($file);
    $json = json_decode($raw, true);
    return is_array($json) ? $json : [];
}

function writeStore($file, $data) {
    @file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT));
}

$body = json_decode(file_get_contents('php://input'), true) ?? [];
$action = $body['action'] ?? '';

// Normalize route key
if (isset($body['route'])) {
    $body['route'] = trim($body['route']);
}

switch ($action) {
    case 'submit':
        // Passenger indicates they are waiting ("I'm here")
        if (!isset($body['passenger_id'], $body['route'])) {
            echo json_encode(['success' => false, 'message' => 'Missing passenger_id or route']);
            exit;
        }
        $passengerId = (int)$body['passenger_id'];
        $route = $body['route'];
        $store = readStore($storeFile);
        $key = $route . '::' . $passengerId;
        $store[$key] = [
            'passenger_id' => $passengerId,
            'route' => $route,
            'status' => 'waiting',
            'submitted_at' => date('Y-m-d H:i:s'),
            'confirmed_at' => null
        ];
        writeStore($storeFile, $store);
        echo json_encode(['success' => true]);
        exit;

    case 'list':
        // Driver lists waiting passengers (optionally by route)
        $routeFilter = $body['route'] ?? null;
        $store = readStore($storeFile);
        $rows = array_values(array_filter($store, function($r) use ($routeFilter) {
            if ($routeFilter && $r['route'] !== $routeFilter) return false;
            return ($r['status'] ?? '') === 'waiting';
        }));
        echo json_encode(['success' => true, 'waiting' => $rows]);
        exit;

    case 'confirm':
        // Driver confirms pickup for a passenger
        if (!isset($body['passenger_id'], $body['route'])) {
            echo json_encode(['success' => false, 'message' => 'Missing passenger_id or route']);
            exit;
        }
        $passengerId = (int)$body['passenger_id'];
        $route = $body['route'];
        $store = readStore($storeFile);
        $key = $route . '::' . $passengerId;
        if (!isset($store[$key])) {
            echo json_encode(['success' => false, 'message' => 'No waiting request found']);
            exit;
        }
        $store[$key]['status'] = 'confirmed';
        $store[$key]['confirmed_at'] = date('Y-m-d H:i:s');
        writeStore($storeFile, $store);
        echo json_encode(['success' => true]);
        exit;

    case 'decline':
        // Driver declines/cancels a waiting request
        if (!isset($body['passenger_id'], $body['route'])) {
            echo json_encode(['success' => false, 'message' => 'Missing passenger_id or route']);
            exit;
        }
        $passengerId = (int)$body['passenger_id'];
        $route = $body['route'];
        $store = readStore($storeFile);
        $key = $route . '::' . $passengerId;
        if (!isset($store[$key])) {
            echo json_encode(['success' => false, 'message' => 'No waiting request found']);
            exit;
        }
        $store[$key]['status'] = 'declined';
        $store[$key]['confirmed_at'] = date('Y-m-d H:i:s');
        writeStore($storeFile, $store);
        echo json_encode(['success' => true]);
        exit;

    case 'status':
        // Passenger polls for confirmation status
        if (!isset($body['passenger_id'], $body['route'])) {
            echo json_encode(['success' => false, 'message' => 'Missing passenger_id or route']);
            exit;
        }
        $passengerId = (int)$body['passenger_id'];
        $route = $body['route'];
        $store = readStore($storeFile);
        $key = $route . '::' . $passengerId;
        $status = $store[$key]['status'] ?? null;
        echo json_encode(['success' => true, 'status' => $status]);
        exit;

    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
        exit;
}