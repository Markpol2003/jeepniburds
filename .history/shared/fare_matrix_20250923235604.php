<?php
header('Content-Type: application/json');

$storageDir = __DIR__ . '/../data';
$file = $storageDir . '/fare_matrix.json';

if (!is_dir($storageDir)) {
    @mkdir($storageDir, 0777, true);
}

if (!file_exists($file)) {
    $seed = [
        ["landmark" => "Baracatan Brgy. Hall", "km" => 0,  "old_reg" => null,  "old_disc" => null,  "new_reg" => null,  "new_disc" => null],
        ["landmark" => "Electric Post # 0558071", "km" => 1,  "old_reg" => 11.00, "old_disc" => 8.75,  "new_reg" => 12.00, "new_disc" => 9.50],
        ["landmark" => "Electric Post # 0567474", "km" => 2,  "old_reg" => 11.00, "old_disc" => 8.75,  "new_reg" => 12.00, "new_disc" => 9.50],
        ["landmark" => "Madre Maria Pia Notari School", "km" => 3,  "old_reg" => 11.00, "old_disc" => 8.75,  "new_reg" => 12.00, "new_disc" => 9.50],
        ["landmark" => "Electric Post # 0362416", "km" => 4,  "old_reg" => 11.00, "old_disc" => 8.75,  "new_reg" => 12.00, "new_disc" => 9.50],
        ["landmark" => "Prk.9 Baracatan", "km" => 5,  "old_reg" => 12.50, "old_disc" => 10.00, "new_reg" => 13.75, "new_disc" => 12.50],
        ["landmark" => "Caltex", "km" => 6,  "old_reg" => 14.00, "old_disc" => 11.25, "new_reg" => 15.50, "new_disc" => 12.50],
        ["landmark" => "Cargill Headquarters", "km" => 7,  "old_reg" => 14.00, "old_disc" => 11.25, "new_reg" => 16.00, "new_disc" => 13.50],
        ["landmark" => "Binugao Brgy. Hall", "km" => 8,  "old_reg" => 17.00, "old_disc" => 13.50, "new_reg" => 19.25, "new_disc" => 15.25],
        ["landmark" => "Magnolia Dressing Plant", "km" => 9,  "old_reg" => 18.50, "old_disc" => 14.75, "new_reg" => 21.00, "new_disc" => 16.75],
        ["landmark" => "JCT Catigan (TF Davao)", "km" => 10, "old_reg" => 20.00, "old_disc" => 16.00, "new_reg" => 22.75, "new_disc" => 18.25],
        ["landmark" => "Lipadas Bridge", "km" => 11, "old_reg" => 23.00, "old_disc" => 17.25, "new_reg" => 24.50, "new_disc" => 19.75],
        ["landmark" => "Gaisano Grand Toril", "km" => 12, "old_reg" => 23.00, "old_disc" => 18.50, "new_reg" => 26.50, "new_disc" => 21.00]
    ];
    @file_put_contents($file, json_encode($seed, JSON_PRETTY_PRINT));
}

$rows = json_decode(@file_get_contents($file), true);
if (!is_array($rows)) $rows = [];

echo json_encode(['success' => true, 'matrix' => $rows]);


