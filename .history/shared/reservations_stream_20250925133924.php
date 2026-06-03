<?php
session_start();
require_once __DIR__ . '/../db_config.php';

// Server-Sent Events stream for reservations updates
header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('Connection: keep-alive');
header('X-Accel-Buffering: no');

ignore_user_abort(true);
@ob_end_flush();
@ob_implicit_flush(true);

$channel = $_GET['channel'] ?? 'passenger';
$routeFilter = $conn->real_escape_string($_GET['route'] ?? '');

function send_event($data) {
    echo 'data: ' . json_encode($data) . "\n\n";
}

function list_for_passenger($conn, $pid) {
    $sql = "SELECT id, route, origin_landmark, dest_landmark, distance_km, fare_regular, fare_discounted, status, eta_time, requested_at, here_at, boarded_at
            FROM reservations WHERE passenger_id=$pid AND status IN ('requested','here','eta_sent')
            ORDER BY requested_at DESC LIMIT 10";
    $res = $conn->query($sql);
    $rows = [];
    while ($res && $r = $res->fetch_assoc()) { $rows[] = $r; }
    return $rows;
}

function list_for_driver($conn, $routeFilter) {
    $whereRoute = $routeFilter !== '' ? "AND (route='" . $routeFilter . "' OR route IS NULL)" : '';
    $sql = "SELECT r.id, r.passenger_id, u.firstName, u.lastName, r.origin_landmark, r.dest_landmark, r.distance_km, r.status, r.eta_time, r.requested_at, r.here_at
            FROM reservations r LEFT JOIN users u ON r.passenger_id=u.id
            WHERE r.status IN ('requested','here','eta_sent') $whereRoute
            ORDER BY r.requested_at DESC LIMIT 50";
    $res = $conn->query($sql);
    $rows = [];
    while ($res && $r = $res->fetch_assoc()) { $rows[] = $r; }
    return $rows;
}

function stats_for_driver($conn) {
    $sql = "SELECT COUNT(*) AS boarded_today, AVG(TIMESTAMPDIFF(SECOND, here_at, boarded_at)) AS avg_wait
            FROM reservations WHERE DATE(boarded_at)=CURDATE()";
    $res = $conn->query($sql);
    $row = $res ? $res->fetch_assoc() : ['boarded_today' => 0, 'avg_wait' => 0];
    return [
        'boarded_today' => (int)($row['boarded_today'] ?? 0),
        'avg_wait_seconds' => (int)($row['avg_wait'] ?? 0),
    ];
}

$ticks = 0; $maxTicks = 60; // ~3 minutes if 3s sleep each
while (!connection_aborted() && $ticks < $maxTicks) {
    $payload = ['success' => true, 'channel' => $channel, 'ts' => time()];
    if ($channel === 'passenger') {
        if (!isset($_SESSION['user_id'])) { send_event(['success'=>false,'message'=>'unauthorized']); break; }
        $pid = (int)$_SESSION['user_id'];
        $payload['reservations'] = list_for_passenger($conn, $pid);
    } else if ($channel === 'driver') {
        if (!isset($_SESSION['user_id'])) { send_event(['success'=>false,'message'=>'unauthorized']); break; }
        $payload['reservations'] = list_for_driver($conn, $routeFilter);
        $payload['stats'] = stats_for_driver($conn);
    } else {
        send_event(['success'=>false,'message'=>'unknown channel']);
        break;
    }
    send_event($payload);
    @ob_flush(); flush();
    $ticks++;
    sleep(3);
}

// End of stream (client will auto-reconnect)
send_event(['success'=>true,'eos'=>true]);
?>


