<?php
header('Content-Type: application/json');

$storageDir = __DIR__ . '/../data';
$stopsFile = $storageDir . '/stops.json';
$etasFile = $storageDir . '/etas.json';

$stops = json_decode(@file_get_contents($stopsFile), true);
if (!is_array($stops)) $stops = [];
$etas = json_decode(@file_get_contents($etasFile), true);
if (!is_array($etas)) $etas = [];

$raw = file_get_contents('php://input');
$data = json_decode($raw, true) ?: [];
$route = isset($data['route']) ? trim((string)$data['route']) : '';

// Build per-stop ETA using driver-set ETAs as a simple proxy (fallback to 5 min)
$result = [];
foreach ($stops as $stop) {
    if ($route && isset($stop['route']) && $stop['route'] !== $route) continue;
    $best = null;
    foreach ($etas as $key => $etaEntry) {
        if (isset($etaEntry['route']) && $etaEntry['route'] === ($route ?: $stop['route'])) {
            $best = $etaEntry['eta_minutes'];
            break;
        }
    }
    $result[] = [
        'stop_id' => $stop['id'],
        'stop_name' => $stop['name'],
        'route' => $stop['route'],
        'eta_minutes' => $best !== null ? (int)$best : 5
    ];
}

echo json_encode(['success' => true, 'etas' => $result]);


