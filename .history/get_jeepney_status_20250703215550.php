<?php
header('Content-Type: application/json');

echo json_encode([
    'success' => true,
    'routes' => [
        ['route' => 'Route 1', 'status' => 'Arrived', 'eta' => '0 min', 'location' => 'Terminal'],
        ['route' => 'Route 2', 'status' => 'Arriving', 'eta' => '1 min', 'location' => 'Near Main Gate'],
        ['route' => 'Route 3', 'status' => 'Delayed', 'eta' => '20 mins', 'location' => '5th Avenue'],
    ]
]); 