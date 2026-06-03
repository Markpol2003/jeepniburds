<?php
require_once __DIR__ . '/../db_config.php';
header('Content-Type: application/json');
$data = json_decode(file_get_contents('php://input'), true) ?? [];

$storageDir = __DIR__ . '/../data';
$file = $storageDir . '/fare_compliance.json';
if (!is_dir($storageDir)) { @mkdir($storageDir, 0777, true); }
if (!file_exists($file)) { @file_put_contents($file, json_encode([])); }

function fc_read($file){ $raw = @file_get_contents($file); $j = json_decode($raw, true); return is_array($j)? $j : []; }
function fc_write($file, $rows){ @file_put_contents($file, json_encode($rows, JSON_PRETTY_PRINT)); }

$action = $data['action'] ?? '';

if ($action === 'save') {
    $required = ['plate','route','date','start','end','sample','total','compliant'];
    foreach ($required as $r) { if (!isset($data[$r])) { echo json_encode(['success'=>false,'message'=>"Missing $r"]); exit; } }
    $sample = max(0, (int)$data['sample']);
    $total = max(0, (int)$data['total']);
    $compliant = max(0, (int)$data['compliant']);
    $weight = ($sample>0 && $total>0) ? ($total/$sample) : null;
    $weighted = ($weight!==null) ? ($compliant*$weight) : null;
    $rate = ($weighted!==null && $total>0) ? (($weighted/$total)*100) : null;
    $rows = fc_read($file);
    $rows[] = [
        'plate' => $data['plate'],
        'route' => $data['route'],
        'date' => $data['date'],
        'start' => $data['start'],
        'end' => $data['end'],
        'sample' => $sample,
        'total' => $total,
        'compliant' => $compliant,
        'weight' => $weight,
        'weighted' => $weighted,
        'rate' => $rate
    ];
    fc_write($file, $rows);
    echo json_encode(['success'=>true]);
    exit;
}

if ($action === 'list') {
    $rows = fc_read($file);
    echo json_encode(['success'=>true, 'rows'=>$rows]);
    exit;
}

echo json_encode(['success'=>false,'message'=>'Invalid action']);


