<?php
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/json_store.php';
jeepnigo_require_role(['driver']);
jeepnigo_require_csrf();
header('Content-Type: application/json');

$dataDir = __DIR__ . '/../data';
$storeFile = $dataDir . '/etas.json';

if (!is_dir($dataDir)) {
    @mkdir($dataDir, 0777, true);
}
if (!file_exists($storeFile)) {
    @file_put_contents($storeFile, json_encode([]));
}

function readStore($file) {
    return jeepnigo_json_read($file);
}

function writeStore($file, $data) {
    return jeepnigo_json_write($file, $data);
}

$body = json_decode(file_get_contents('php://input'), true) ?? [];
if (($body['action'] ?? '') !== 'set') {
    echo json_encode(['success' => false, 'message' => 'Invalid action']);
    exit;
}

$route = trim($body['route'] ?? '');
$etaMinutes = isset($body['eta_minutes']) ? floatval($body['eta_minutes']) : null;
$lambda = isset($body['lambda']) ? floatval($body['lambda']) : null; // arrivals per minute
$mu = isset($body['mu']) ? floatval($body['mu']) : null;             // services per minute
$varS = isset($body['var_s']) ? floatval($body['var_s']) : 0.0;      // variance of service time

if ($route === '' || $etaMinutes === null || $mu === null || $mu <= 0 || $lambda === null || $lambda < 0) {
    echo json_encode(['success' => false, 'message' => 'Missing/invalid fields.']);
    exit;
}

// P-K computations
$ES = 1.0 / $mu; // mean service time in minutes
$ES2 = $varS + ($ES * $ES);
$rho = $lambda / $mu; // utilization
if ($rho >= 1.0) {
    echo json_encode(['success' => false, 'message' => 'Utilization ρ must be < 1. Choose λ and μ accordingly.']);
    exit;
}
$Wq = ($lambda * $ES2) / (2.0 * (1.0 - $rho)); // average waiting time in queue (minutes)

$store = readStore($storeFile);
$store[$route] = [
    'route' => $route,
    'eta_minutes' => $etaMinutes,
    'lambda' => $lambda,
    'mu' => $mu,
    'var_s' => $varS,
    'E_S' => $ES,
    'E_S2' => $ES2,
    'rho' => $rho,
    'Wq' => $Wq,
    'updated_at' => date('Y-m-d H:i:s')
];
writeStore($storeFile, $store);

echo json_encode(['success' => true, 'eta' => $store[$route]]);


