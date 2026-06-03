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

<?php
require_once __DIR__ . '/../db_config.php';
header('Content-Type: application/json');

$routes = [];
$res = $conn->query("SELECT route, lat, lng, updated_at FROM jeepney_locations ORDER BY updated_at DESC");
if ($res && $res->num_rows > 0) {
    while ($row = $res->fetch_assoc()) {
        // Mock status/eta logic based on updated_at or random for now
        $status = 'On the way';
        $eta = '10 mins';
        $now = time();
        $updated = strtotime($row['updated_at']);
        if ($now - $updated < 120) {
            $status = 'Arriving';
            $eta = '1 min';
        }
        if ($now - $updated < 30) {
            $status = 'Arrived';
            $eta = '0 min';
        }
        $routes[] = [
            'route' => $row['route'],
            'status' => $status,
            'eta' => $eta,
            'location' => 'Lat: ' . $row['lat'] . ', Lng: ' . $row['lng'],
            'lat' => $row['lat'],
            'lng' => $row['lng']
        ];
    }
    echo json_encode(['success' => true, 'routes' => $routes]);
} else {
    // Fallback mock data - using same route names as driver assignments
    echo json_encode([
        'success' => true,
        'routes' => [
            ['route' => 'toril', 'status' => 'Arrived', 'eta' => '0 min', 'location' => 'Terminal', 'lat' => 14.5995, 'lng' => 120.9842],
            ['route' => 'Route 2', 'status' => 'Arriving', 'eta' => '1 min', 'location' => 'Near Main Gate', 'lat' => 14.6000, 'lng' => 120.9850],
            ['route' => 'Route 3', 'status' => 'Delayed', 'eta' => '20 mins', 'location' => '5th Avenue', 'lat' => 14.6010, 'lng' => 120.9860],
        ]
    ]);
} 