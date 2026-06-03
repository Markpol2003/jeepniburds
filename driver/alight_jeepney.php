<?php
require_once __DIR__ . '/../db_config.php';
header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true) ?? [];
$storageDir = __DIR__ . '/../data';
$alightFile = $storageDir . '/alighting_events.json';

if (!is_dir($storageDir)) {
    @mkdir($storageDir, 0777, true);
}
if (!file_exists($alightFile)) {
    @file_put_contents($alightFile, json_encode([]));
}

function readAlightEvents($file)
{
    $raw = @file_get_contents($file);
    $json = json_decode($raw, true);
    return is_array($json) ? $json : [];
}

function writeAlightEvents($file, $events)
{
    @file_put_contents($file, json_encode($events, JSON_PRETTY_PRINT));
}

// List alighting events per route
if (isset($data['action']) && $data['action'] === 'list' && isset($data['route'])) {
    $route = $data['route'];
    $events = array_values(array_filter(readAlightEvents($alightFile), function ($ev) use ($route) {
        return isset($ev['route']) && $ev['route'] === $route;
    }));
    // Sort desc by time
    usort($events, function ($a, $b) {
        return strcmp($b['alighted_at'] ?? '', $a['alighted_at'] ?? '');
    });
    echo json_encode(['success' => true, 'events' => $events]);
    exit;
}

// Record new alighting event
if (!isset($data['passenger_id'], $data['route'])) {
    echo json_encode(['success' => false, 'message' => 'Missing required fields.']);
    exit;
}

$passengerId = intval($data['passenger_id']);
$route = $data['route'];
$now = date('Y-m-d H:i:s');

$events = readAlightEvents($alightFile);
$events[] = [
    'passenger_id' => $passengerId,
    'route' => $route,
    'alighted_at' => $now
];
writeAlightEvents($alightFile, $events);

echo json_encode(['success' => true]);


