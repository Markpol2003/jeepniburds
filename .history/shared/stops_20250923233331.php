<?php
header('Content-Type: application/json');

$storageDir = __DIR__ . '/../data';
$stopsFile = $storageDir . '/stops.json';

if (!is_dir($storageDir)) {
    @mkdir($storageDir, 0777, true);
}

// Seed minimal stops if missing
if (!file_exists($stopsFile)) {
    $seed = [
        ['id' => 1, 'name' => 'Toril Market', 'route' => 'Route 1', 'lat' => 7.0123, 'lng' => 125.4951],
        ['id' => 2, 'name' => 'Bangkal Crossing', 'route' => 'Route 1', 'lat' => 7.0485, 'lng' => 125.5563],
        ['id' => 3, 'name' => 'Ecoland Terminal', 'route' => 'Route 1', 'lat' => 7.0684, 'lng' => 125.6049],
        ['id' => 4, 'name' => 'Matina Crossing', 'route' => 'Route 2', 'lat' => 7.0606, 'lng' => 125.5887],
        ['id' => 5, 'name' => 'San Pedro', 'route' => 'Route 2', 'lat' => 7.0737, 'lng' => 125.6125],
    ];
    @file_put_contents($stopsFile, json_encode($seed, JSON_PRETTY_PRINT));
}

$stops = json_decode(@file_get_contents($stopsFile), true);
if (!is_array($stops)) $stops = [];

// filter by route if provided
$raw = file_get_contents('php://input');
$data = json_decode($raw, true) ?: [];
$route = isset($data['route']) ? trim((string)$data['route']) : '';
if ($route !== '') {
    $stops = array_values(array_filter($stops, function($s) use ($route) { return isset($s['route']) && $s['route'] === $route; }));
}

echo json_encode(['success' => true, 'stops' => $stops]);


