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
    $manilaTz = new DateTimeZone('Asia/Manila');
    $today = new DateTime('today', $manilaTz);
    $todayDate = $today->format('Y-m-d');
    
    // Query a wider range to account for timezone differences (same approach as boarding_history)
    $queryStartDate = clone $today;
    $queryStartDate->modify('-1 day');
    $queryStartDate->setTime(0, 0, 0);
    
    $queryEndDate = clone $today;
    $queryEndDate->modify('+1 day');
    $queryEndDate->setTime(23, 59, 59);
    
    $queryStart = $queryStartDate->format('Y-m-d 00:00:00');
    $queryEnd = $queryEndDate->format('Y-m-d 23:59:59');
    
    // Get all boarded records in the wider range, then filter by actual Manila date
    $stmt = $conn->prepare("SELECT 
                boarded_at,
                TIMESTAMPDIFF(SECOND, here_at, boarded_at) AS wait_seconds
            FROM reservations 
            WHERE driver_id = ? 
            AND boarded_at >= ?
            AND boarded_at <= ?
            AND status = 'boarded'");
    $stmt->bind_param('iss', $driverId, $queryStart, $queryEnd);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $boardedToday = 0;
    $totalWait = 0;
    $waitCount = 0;
    
    // Filter results to only count records on today's date in Manila timezone
    while ($result && $r = $result->fetch_assoc()) {
        if ($r['boarded_at']) {
            try {
                // Parse as UTC and convert to Manila timezone
                $boardedDt = new DateTime($r['boarded_at'], new DateTimeZone('UTC'));
                $boardedDt->setTimezone($manilaTz);
                $boardedDate = $boardedDt->format('Y-m-d');
                
                if ($boardedDate === $todayDate) {
                    $boardedToday++;
                    if ($r['wait_seconds'] > 0) {
                        $totalWait += $r['wait_seconds'];
                        $waitCount++;
                    }
                }
            } catch (Exception $e) {
                // Skip records that can't be parsed
                continue;
            }
        }
    }
    $stmt->close();
    
    $avgWait = $waitCount > 0 ? (int)($totalWait / $waitCount) : 0;
    
    respond(true, [
        'boarded_today' => $boardedToday,
        'avg_wait_seconds' => $avgWait,
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
    
    // Optional date filter - use PHP date to ensure correct timezone (Asia/Manila)
    $dateFilter = isset($_POST['date']) ? $_POST['date'] : date('Y-m-d');
    
    // Validate date format
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFilter)) {
        $dateFilter = date('Y-m-d');
    }
    
    // Create date range in Asia/Manila timezone
    // The target date in Manila: from 00:00:00 to 23:59:59
    $manilaTz = new DateTimeZone('Asia/Manila');
    $startManila = new DateTime($dateFilter . ' 00:00:00', $manilaTz);
    $endManila = new DateTime($dateFilter . ' 23:59:59', $manilaTz);
    
    // Query a wider range to account for timezone uncertainty
    // Query from 16:00 previous day to 16:00 next day (to cover both UTC and Manila time storage)
    $queryStartDate = clone $startManila;
    $queryStartDate->modify('-1 day');
    $queryStartDate->setTime(0, 0, 0); // Start from midnight previous day
    
    $queryEndDate = clone $endManila;
    $queryEndDate->modify('+1 day');
    $queryEndDate->setTime(23, 59, 59); // End at end of next day
    
    // Use these as-is for MySQL query (MySQL will compare as stored)
    $queryStart = $queryStartDate->format('Y-m-d 00:00:00');
    $queryEnd = $queryEndDate->format('Y-m-d 23:59:59');
    
    // Get all boarding records in this wider range, then filter by actual Manila date
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
                TIMESTAMPDIFF(SECOND, r.here_at, r.boarded_at) AS wait_seconds
            FROM reservations r
            LEFT JOIN users u ON r.passenger_id = u.id
            WHERE r.driver_id = ? 
            AND r.status = 'boarded'
            AND r.boarded_at >= ?
            AND r.boarded_at <= ?
            ORDER BY r.boarded_at DESC
            LIMIT 500");
    $stmt->bind_param('iss', $driverId, $queryStart, $queryEnd);
    $stmt->execute();
    $result = $stmt->get_result();
    $rows = [];
    
    // Filter results to only include records on the target date in Manila timezone
    // Interpret stored DATETIME values as UTC (most common MySQL default) and convert to Manila timezone
    while ($result && $r = $result->fetch_assoc()) {
        if ($r['boarded_at']) {
            try {
                // Parse as UTC (most MySQL servers store DATETIME in UTC when system timezone is UTC)
                $boardedDt = new DateTime($r['boarded_at'], new DateTimeZone('UTC'));
                $boardedDt->setTimezone($manilaTz);
                $boardedDate = $boardedDt->format('Y-m-d');
                
                // Only include if the date matches the target date in Manila timezone
                if ($boardedDate === $dateFilter) {
                    // Add the boarding_date field for display
                    $r['boarding_date'] = $boardedDate;
                    $rows[] = $r;
                }
            } catch (Exception $e) {
                // Skip records that can't be parsed
                continue;
            }
        }
    }
    $stmt->close();
    
    respond(true, ['history' => $rows, 'date' => $dateFilter]);
}

respond(false, ['message' => 'Unknown action'], 400);
?>


