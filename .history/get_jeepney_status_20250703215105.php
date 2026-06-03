<?php
header('Content-Type: application/json');

echo json_encode([
    'success' => true,
    'routes' => [
        ['route' => 'Route 1', 'status' => 'Arrived', 'eta' => '5 mins'],
        ['route' => 'Route 2', 'status' => 'On the way', 'eta' => '10 mins'],
        ['route' => 'Route 3', 'status' => 'Delayed', 'eta' => '20 mins'],
    ]
]); 