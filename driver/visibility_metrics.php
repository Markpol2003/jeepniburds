<?php
require_once __DIR__ . '/../db_config.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/json_store.php';
jeepnigo_require_role(['driver']);
jeepnigo_require_csrf();
header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true) ?? [];
$storageDir = __DIR__ . '/../data';
$manualFile = $storageDir . '/manual_counts.json';

if (!is_dir($storageDir)) {
    @mkdir($storageDir, 0777, true);
}
if (!file_exists($manualFile)) {
    @file_put_contents($manualFile, json_encode([]));
}

function readManual($file)
{
    return jeepnigo_json_read($file);
}
function writeManual($file, $rows)
{
    return jeepnigo_json_write($file, $rows);
}

// Save manual counts (Ride_In, Ride_Out) per plate, date, route, start, end
if (isset($data['action']) && $data['action'] === 'save') {
    $required = ['plate_number','date','route','start_time','end_time','ride_in','ride_out','app_in','app_out'];
    foreach ($required as $r) {
        if (!isset($data[$r])) {
            echo json_encode(['success' => false, 'message' => "Missing $r"]);
            exit;
        }
    }
    $rows = readManual($manualFile);
    $rows[] = [
        'plate_number' => $data['plate_number'],
        'date' => $data['date'],
        'route' => $data['route'],
        'start_time' => $data['start_time'],
        'end_time' => $data['end_time'],
        'ride_in' => (int)$data['ride_in'],
        'ride_out' => (int)$data['ride_out'],
        'app_in' => (int)$data['app_in'],
        'app_out' => (int)$data['app_out']
    ];
    writeManual($manualFile, $rows);
    echo json_encode(['success' => true]);
    exit;
}

// List with computed diffs and visibility
if (isset($data['action']) && $data['action'] === 'list') {
    $rows = readManual($manualFile);
    $rows = array_map(function($r){
        $r['diff_in'] = ($r['app_in'] ?? 0) - ($r['ride_in'] ?? 0);
        $r['diff_out'] = ($r['app_out'] ?? 0) - ($r['ride_out'] ?? 0);
        $r['visibility_in'] = ($r['ride_in'] ?? 0) > 0 ? round((($r['app_in'] ?? 0) / max(1,$r['ride_in']))*100, 2) : null;
        $r['visibility_out'] = ($r['ride_out'] ?? 0) > 0 ? round((($r['app_out'] ?? 0) / max(1,$r['ride_out']))*100, 2) : null;
        return $r;
    }, $rows);
    echo json_encode(['success' => true, 'rows' => $rows]);
    exit;
}

echo json_encode(['success' => false, 'message' => 'Invalid action']);


