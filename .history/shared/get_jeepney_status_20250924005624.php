<?php
header('Content-Type: application/json');

$dataDir = __DIR__ . '/../data';
$etaFile = $dataDir . '/eta_store.json';

// Fallback demo routes; in real app, replace with DB query
$routes = [
    [ 'route' => 'Route 1', 'status' => 'Arriving', 'eta' => '5 min', 'lat' => 7.073, 'lng' => 125.61, 'location' => 'Sta. Ana' ],
    [ 'route' => 'Route 2', 'status' => 'In Progress', 'eta' => '8 min', 'lat' => 7.079, 'lng' => 125.62, 'location' => 'Toril Rd' ],
];

if (file_exists($etaFile)) {
    $raw = @file_get_contents($etaFile);
    $etas = json_decode($raw, true) ?: [];
    foreach ($routes as &$r) {
        $key = $r['route'];
        if (isset($etas[$key]['eta_minutes'])) {
            $r['eta'] = sprintf('%.0f min', floatval($etas[$key]['eta_minutes']));
        }
    }
}

echo json_encode([ 'success' => true, 'routes' => $routes ]);