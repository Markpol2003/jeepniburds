<?php
session_start();
require_once __DIR__ . '/../db_config.php';

header('Content-Type: application/json');

function respond($ok, $data = [], $code = 200) {
    http_response_code($code);
    echo json_encode(array_merge(['success' => $ok], $data));
    exit;
}

// Ensure table exists (idempotent)
$conn->query("CREATE TABLE IF NOT EXISTS reservations (
    id INT PRIMARY KEY AUTO_INCREMENT,
    passenger_id INT NOT NULL,
    driver_id INT DEFAULT NULL,
    route VARCHAR(100) DEFAULT NULL,
    origin_landmark VARCHAR(255) DEFAULT NULL,
    dest_landmark VARCHAR(255) DEFAULT NULL,
    distance_km INT DEFAULT NULL,
    fare_regular DECIMAL(10,2) DEFAULT NULL,
    fare_discounted DECIMAL(10,2) DEFAULT NULL,
    status ENUM('requested','here','eta_sent','boarded','cancelled') DEFAULT 'requested',
    eta_time DATETIME DEFAULT NULL,
    requested_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    here_at DATETIME DEFAULT NULL,
    boarded_at DATETIME DEFAULT NULL,
    INDEX (passenger_id), INDEX (driver_id), INDEX (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$action = $_POST['action'] ?? $_GET['action'] ?? '';

// Helper: compute fare from fare_matrix.json by distance_km
function compute_fare_by_distance($distanceKm) {
    $path = __DIR__ . '/../data/fare_matrix.json';
    if (!file_exists($path)) {
        return [ 'regular' => null, 'discounted' => null ];
    }
    $json = json_decode(file_get_contents($path), true);
    if (!$json || !is_array($json)) {
        return [ 'regular' => null, 'discounted' => null ];
    }
    // Find row where km equals provided distance
    foreach ($json as $row) {
        if ((int)($row['km'] ?? -1) === (int)$distanceKm) {
            return [
                'regular' => isset($row['new_reg']) ? (float)$row['new_reg'] : null,
                'discounted' => isset($row['new_disc']) ? (float)$row['new_disc'] : null,
            ];
        }
    }
    // Fallback: find nearest lower km
    $best = null; $bestDiff = PHP_INT_MAX;
    foreach ($json as $row) {
        $d = (int)($row['km'] ?? -1);
        if ($d <= $distanceKm && $distanceKm - $d < $bestDiff) {
            $best = $row; $bestDiff = $distanceKm - $d;
        }
    }
    if ($best) {
        return [
            'regular' => isset($best['new_reg']) ? (float)$best['new_reg'] : null,
            'discounted' => isset($best['new_disc']) ? (float)$best['new_disc'] : null,
        ];
    }
    return [ 'regular' => null, 'discounted' => null ];
}

if ($action === 'create') {
    if (!isset($_SESSION['user_id'])) respond(false, ['message' => 'Unauthorized'], 401);
    $passengerId = (int)$_SESSION['user_id'];
    $origin = trim($_POST['origin'] ?? '');
    $dest = trim($_POST['dest'] ?? '');
    $distanceKm = (int)($_POST['distance_km'] ?? 0);
    $route = trim($_POST['route'] ?? '');
    $fare = compute_fare_by_distance($distanceKm);

    $stmt = $conn->prepare("INSERT INTO reservations (passenger_id, route, origin_landmark, dest_landmark, distance_km, fare_regular, fare_discounted, status) VALUES (?, ?, ?, ?, ?, ?, ?, 'requested')");
    $fareReg = $fare['regular']; $fareDisc = $fare['discounted'];
    $stmt->bind_param('isssidd', $passengerId, $route, $origin, $dest, $distanceKm, $fareReg, $fareDisc);
    if (!$stmt->execute()) {
        respond(false, ['message' => 'Failed to create reservation']);
    }
    respond(true, [
        'reservation_id' => $stmt->insert_id,
        'fare' => $fare,
    ]);
}

if ($action === 'im_here') {
    if (!isset($_POST['reservation_id'])) respond(false, ['message' => 'Missing reservation_id'], 400);
    $rid = (int)$_POST['reservation_id'];
    $stmt = $conn->prepare("UPDATE reservations SET status='here', here_at=NOW() WHERE id=? AND status IN ('requested','eta_sent')");
    $stmt->bind_param('i', $rid);
    $ok = $stmt->execute();
    respond($ok, ['updated' => $ok]);
}

if ($action === 'driver_set_eta') {
    if (!isset($_SESSION['user_id'])) respond(false, ['message' => 'Unauthorized'], 401);
    $driverId = (int)$_SESSION['user_id'];
    $rid = (int)($_POST['reservation_id'] ?? 0);
    $etaMinutes = (int)($_POST['eta_minutes'] ?? 0);
    if ($rid <= 0 || $etaMinutes <= 0) respond(false, ['message' => 'Invalid input'], 400);
    $stmt = $conn->prepare("UPDATE reservations SET driver_id=?, status='eta_sent', eta_time=DATE_ADD(NOW(), INTERVAL ? MINUTE) WHERE id=?");
    $stmt->bind_param('iii', $driverId, $etaMinutes, $rid);
    $ok = $stmt->execute();
    respond($ok, ['updated' => $ok]);
}

if ($action === 'confirm_boarded') {
    if (!isset($_SESSION['user_id'])) respond(false, ['message' => 'Unauthorized'], 401);
    $driverId = (int)$_SESSION['user_id'];
    $rid = (int)($_POST['reservation_id'] ?? 0);
    if ($rid <= 0) respond(false, ['message' => 'Invalid reservation'], 400);
    // Compute wait seconds if here_at exists
    $conn->query("UPDATE reservations SET status='boarded', boarded_at=NOW(), driver_id=IF(driver_id IS NULL, $driverId, driver_id) WHERE id=$rid");
    // Return updated row
    $res = $conn->query("SELECT TIMESTAMPDIFF(SECOND, here_at, boarded_at) AS wait_seconds FROM reservations WHERE id=$rid");
    $row = $res ? $res->fetch_assoc() : null;
    respond(true, ['wait_seconds' => (int)($row['wait_seconds'] ?? 0)]);
}

if ($action === 'list_for_passenger') {
    if (!isset($_SESSION['user_id'])) respond(false, ['message' => 'Unauthorized'], 401);
    $pid = (int)$_SESSION['user_id'];
    $res = $conn->query("SELECT id, route, origin_landmark, dest_landmark, distance_km, fare_regular, fare_discounted, status, eta_time, requested_at, here_at, boarded_at FROM reservations WHERE passenger_id=$pid AND status IN ('requested','here','eta_sent','boarded') ORDER BY requested_at DESC LIMIT 10");
    $rows = [];
    while ($res && $r = $res->fetch_assoc()) { $rows[] = $r; }
    respond(true, ['reservations' => $rows]);
}

if ($action === 'list_for_driver') {
    if (!isset($_SESSION['user_id'])) respond(false, ['message' => 'Unauthorized'], 401);
    $driverId = (int)$_SESSION['user_id'];
    // Optionally filter by route passed in
    $route = $conn->real_escape_string($_POST['route'] ?? $_GET['route'] ?? '');
    $whereRoute = $route !== '' ? "AND (route='$route' OR route IS NULL)" : '';
    $sql = "SELECT r.id, r.passenger_id, u.firstName, u.lastName, r.origin_landmark, r.dest_landmark, r.distance_km, r.status, r.eta_time, r.requested_at, r.here_at
            FROM reservations r LEFT JOIN users u ON r.passenger_id=u.id
            WHERE r.status IN ('requested','here','eta_sent') $whereRoute
            ORDER BY r.requested_at DESC LIMIT 50";
    $res = $conn->query($sql);
    $rows = [];
    while ($res && $r = $res->fetch_assoc()) { $rows[] = $r; }
    respond(true, ['reservations' => $rows]);
}

if ($action === 'driver_stats') {
    if (!isset($_SESSION['user_id'])) respond(false, ['message' => 'Unauthorized'], 401);
    $driverId = (int)$_SESSION['user_id'];
    $sql = "SELECT COUNT(*) AS boarded_today, AVG(TIMESTAMPDIFF(SECOND, here_at, boarded_at)) AS avg_wait
            FROM reservations WHERE DATE(boarded_at)=CURDATE()";
    $res = $conn->query($sql);
    $row = $res ? $res->fetch_assoc() : ['boarded_today' => 0, 'avg_wait' => 0];
    respond(true, [
        'boarded_today' => (int)$row['boarded_today'],
        'avg_wait_seconds' => (int)$row['avg_wait'],
    ]);
}

if ($action === 'boarding_history') {
    if (!isset($_SESSION['user_id'])) respond(false, ['message' => 'Unauthorized'], 401);
    $driverId = (int)$_SESSION['user_id'];
    
    // Optional date filter
    $dateFilter = isset($_POST['date']) ? $conn->real_escape_string($_POST['date']) : date('Y-m-d');
    
    // Get boarding history with passenger details
    $sql = "SELECT 
                r.id,
                r.passenger_id,
                u.firstName,
                u.lastName,
                u.email,
                r.route,
                r.origin_landmark,
                r.dest_landmark,
                r.distance_km,
                r.fare_regular,
                r.fare_discounted,
                r.here_at,
                r.boarded_at,
                TIMESTAMPDIFF(SECOND, r.here_at, r.boarded_at) AS wait_seconds,
                DATE(r.boarded_at) AS boarding_date
            FROM reservations r
            LEFT JOIN users u ON r.passenger_id = u.id
            WHERE r.status = 'boarded'
            AND DATE(r.boarded_at) = '$dateFilter'
            ORDER BY r.boarded_at DESC
            LIMIT 100";
    
    $res = $conn->query($sql);
    $rows = [];
    while ($res && $r = $res->fetch_assoc()) {
        $rows[] = $r;
    }
    
    respond(true, ['history' => $rows, 'date' => $dateFilter]);
}

respond(false, ['message' => 'Unknown action'], 400);
?>


