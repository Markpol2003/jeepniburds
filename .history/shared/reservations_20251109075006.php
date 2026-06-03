<?php
session_start();
require_once __DIR__ . '/../db_config.php';

// Set timezone to match server location (adjust to your server's timezone)
// For Philippines, use Asia/Manila
date_default_timezone_set('Asia/Manila');

// Set MySQL timezone to match PHP timezone
try {
    $timezone = date_default_timezone_get();
    $offset = date('P'); // Gets offset like +08:00
    $conn->query("SET time_zone = '$offset'");
} catch (Exception $e) {
    // If timezone setting fails, continue (MySQL might not support it)
}

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
    
    // Update reservation: set status to boarded, store boarding time (NOW()), and assign driver_id
    $stmt = $conn->prepare("UPDATE reservations SET status='boarded', boarded_at=NOW(), driver_id=IF(driver_id IS NULL, ?, driver_id) WHERE id=?");
    $stmt->bind_param('ii', $driverId, $rid);
    if (!$stmt->execute()) {
        respond(false, ['message' => 'Failed to update boarding status']);
    }
    $stmt->close();
    
    // Get passenger info and wait time
    $stmt = $conn->prepare("SELECT r.passenger_id, u.firstName, u.lastName, TIMESTAMPDIFF(SECOND, r.here_at, r.boarded_at) AS wait_seconds FROM reservations r LEFT JOIN users u ON r.passenger_id = u.id WHERE r.id=?");
    $stmt->bind_param('i', $rid);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result ? $result->fetch_assoc() : null;
    $stmt->close();
    
    respond(true, [
        'wait_seconds' => (int)($row['wait_seconds'] ?? 0),
        'passenger_name' => ($row['firstName'] ?? '') . ' ' . ($row['lastName'] ?? ''),
        'boarded_at' => date('Y-m-d H:i:s')
    ]);
}

if ($action === 'list_for_passenger') {
    if (!isset($_SESSION['user_id'])) respond(false, ['message' => 'Unauthorized'], 401);
    $pid = (int)$_SESSION['user_id'];
    // Calculate per-passenger reservation number (starting from 1 for each passenger)
    // Use requested_at and id as tiebreaker to ensure consistent numbering
    // Also calculate remaining ETA minutes server-side to avoid timezone issues
    $res = $conn->query("SELECT r.id, r.route, r.origin_landmark, r.dest_landmark, r.distance_km, r.fare_regular, r.fare_discounted, r.status, r.eta_time, r.requested_at, r.here_at, r.boarded_at,
        (SELECT COUNT(*) FROM reservations r2 
         WHERE r2.passenger_id = r.passenger_id 
         AND (r2.requested_at < r.requested_at OR (r2.requested_at = r.requested_at AND r2.id <= r.id))) AS reservation_number,
        CASE 
            WHEN r.eta_time IS NOT NULL AND r.eta_time > NOW() THEN GREATEST(1, TIMESTAMPDIFF(MINUTE, NOW(), r.eta_time))
            WHEN r.eta_time IS NOT NULL AND r.eta_time <= NOW() THEN 0
            ELSE NULL
        END AS eta_minutes_remaining
        FROM reservations r 
        WHERE r.passenger_id=$pid AND r.status IN ('requested','here','eta_sent','boarded') 
        ORDER BY r.requested_at DESC, r.id DESC LIMIT 10");
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
    
    // Get today's date in Asia/Manila timezone using PHP
    $timezone = new DateTimeZone('Asia/Manila');
    $today = new DateTime('today', $timezone);
    $tomorrow = new DateTime('tomorrow', $timezone);
    
    $startDateTime = $today->format('Y-m-d H:i:s');
    $endDateTime = $tomorrow->format('Y-m-d H:i:s');
    
    // Get stats only for this driver's boarded passengers
    // Use date range instead of DATE() and CURDATE() to avoid timezone issues
    $stmt = $conn->prepare("SELECT COUNT(*) AS boarded_today, AVG(TIMESTAMPDIFF(SECOND, here_at, boarded_at)) AS avg_wait
            FROM reservations 
            WHERE driver_id = ? 
            AND boarded_at >= ? 
            AND boarded_at < ?
            AND status = 'boarded'");
    $stmt->bind_param('iss', $driverId, $startDateTime, $endDateTime);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result ? $result->fetch_assoc() : ['boarded_today' => 0, 'avg_wait' => 0];
    $stmt->close();
    
    respond(true, [
        'boarded_today' => (int)$row['boarded_today'],
        'avg_wait_seconds' => (int)$row['avg_wait'],
    ]);
}

// Return app boarding count for a time range (current driver)
if ($action === 'app_count') {
    if (!isset($_SESSION['user_id'])) respond(false, ['message' => 'Unauthorized'], 401);
    $driverId = (int)$_SESSION['user_id'];
    $start = trim($_POST['start_time'] ?? '');
    $end = trim($_POST['end_time'] ?? '');
    if ($start === '' || $end === '') respond(false, ['message' => 'Missing start_time or end_time'], 400);

    // Validate datetime format (YYYY-MM-DD HH:MM)
    $startDt = date('Y-m-d H:i:s', strtotime($start));
    $endDt = date('Y-m-d H:i:s', strtotime($end));
    if (!$startDt || !$endDt) respond(false, ['message' => 'Invalid datetime'], 400);

    $stmt = $conn->prepare("SELECT COUNT(*) AS c FROM reservations WHERE driver_id=? AND status='boarded' AND boarded_at BETWEEN ? AND ?");
    $stmt->bind_param('iss', $driverId, $startDt, $endDt);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res ? $res->fetch_assoc() : ['c' => 0];
    $stmt->close();

    respond(true, ['count' => (int)$row['c']]);
}

if ($action === 'boarding_history') {
    if (!isset($_SESSION['user_id'])) respond(false, ['message' => 'Unauthorized'], 401);
    $driverId = (int)$_SESSION['user_id'];
    
    // Optional date filter - use PHP date to ensure correct timezone
    $dateFilter = isset($_POST['date']) ? $_POST['date'] : date('Y-m-d');
    
    // Validate date format
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFilter)) {
        $dateFilter = date('Y-m-d');
    }
    
    // Create date range in Asia/Manila timezone (00:00:00 to 23:59:59)
    // Use PHP's DateTime to handle timezone conversion correctly
    $timezone = new DateTimeZone('Asia/Manila');
    $startDate = new DateTime($dateFilter . ' 00:00:00', $timezone);
    $endDate = new DateTime($dateFilter . ' 23:59:59', $timezone);
    
    // Get the server's timezone offset to determine if we need conversion
    // Check if MySQL timezone is set correctly by testing
    // For DATETIME columns, we need to account for timezone conversion if data was stored in UTC
    // We'll use CONVERT_TZ to handle this, but first try without conversion since timezone should be set
    
    // Since MySQL session timezone is set, DATE() function should work correctly
    // But to be safe, we'll use a date range comparison
    // Convert the date filter to a format MySQL can use
    // MySQL will interpret the datetime in the session timezone context
    $startDateTime = $startDate->format('Y-m-d H:i:s');
    $endDateTime = $endDate->format('Y-m-d H:i:s');
    
    // Get boarding history with passenger details - ONLY for this driver
    // Since MySQL timezone is set to Asia/Manila, DATE() should work correctly
    // But if there are issues, we can use CONVERT_TZ as fallback
    // Try using DATE() first since it's simpler and timezone is set
    $stmt = $conn->prepare("SELECT 
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
                DATE_FORMAT(r.here_at, '%Y-%m-%d %H:%i:%s') AS here_at,
                DATE_FORMAT(r.boarded_at, '%Y-%m-%d %H:%i:%s') AS boarded_at,
                TIMESTAMPDIFF(SECOND, r.here_at, r.boarded_at) AS wait_seconds,
                DATE_FORMAT(r.boarded_at, '%Y-%m-%d') AS boarding_date
            FROM reservations r
            LEFT JOIN users u ON r.passenger_id = u.id
            WHERE r.driver_id = ? 
            AND r.status = 'boarded'
            AND DATE(r.boarded_at) = DATE(?)
            ORDER BY r.boarded_at DESC
            LIMIT 100");
    $stmt->bind_param('is', $driverId, $dateFilter);
    $stmt->execute();
    $result = $stmt->get_result();
    $rows = [];
    while ($result && $r = $result->fetch_assoc()) {
        $rows[] = $r;
    }
    $stmt->close();
    
    // If no results and it's today, the issue might be timezone-related
    // In that case, verify the MySQL timezone is actually set
    if (count($rows) === 0 && $dateFilter === date('Y-m-d')) {
        // Double-check: try a wider date range query to see if data exists
        // This is just for debugging - the main fix is ensuring timezone is set correctly
    }
    
    respond(true, ['history' => $rows, 'date' => $dateFilter]);
}

respond(false, ['message' => 'Unknown action'], 400);
?>


