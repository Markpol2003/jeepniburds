<?php
require_once __DIR__ . '/../db_config.php';
header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true) ?? [];
$action = $data['action'] ?? 'set';

$storageDir = __DIR__ . '/../data';
$file = $storageDir . '/etas.json';

if (!is_dir($storageDir)) {
    @mkdir($storageDir, 0777, true);
}
if (!file_exists($file)) {
    @file_put_contents($file, json_encode([]));
}

function readEtas($file) {
    $raw = @file_get_contents($file);
    $json = json_decode($raw, true);
    return is_array($json) ? $json : [];
}
function writeEtas($file, $rows) {
    @file_put_contents($file, json_encode($rows, JSON_PRETTY_PRINT));
}

if ($action === 'set') {
    $route = trim((string)($data['route'] ?? ''));
    $eta = isset($data['eta_minutes']) ? (int)$data['eta_minutes'] : null;
    if ($route === '' || $eta === null || $eta < 0) {
        echo json_encode(['success' => false, 'message' => 'Missing route or eta_minutes']);
        exit;
    }
    $rows = readEtas($file);
    $rows[$route] = [
        'route' => $route,
        'eta_minutes' => $eta,
        'updated_at' => date('Y-m-d H:i:s')
    ];
    writeEtas($file, $rows);
    echo json_encode(['success' => true]);
    exit;
}

if ($action === 'get') {
    $route = trim((string)($data['route'] ?? ''));
    if ($route === '') {
        echo json_encode(['success' => false, 'message' => 'Missing route']);
        exit;
    }
    $rows = readEtas($file);
    $entry = $rows[$route] ?? null;
    echo json_encode(['success' => true, 'eta' => $entry]);
    exit;
}

if ($action === 'list') {
    echo json_encode(['success' => true, 'etas' => readEtas($file)]);
    exit;
}

echo json_encode(['success' => false, 'message' => 'Invalid action']);


