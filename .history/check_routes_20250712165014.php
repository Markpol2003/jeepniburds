<?php
require_once 'db_config.php';

echo "=== ROUTES IN SYSTEM ===\n\n";

// Check jeepney_locations table
echo "Routes in jeepney_locations table:\n";
$locationsQuery = "SELECT DISTINCT route FROM jeepney_locations WHERE route IS NOT NULL AND route != ''";
$locationsResult = $conn->query($locationsQuery);
if ($locationsResult && $locationsResult->num_rows > 0) {
    while ($row = $locationsResult->fetch_assoc()) {
        echo "- '" . $row['route'] . "'\n";
    }
} else {
    echo "No routes found in jeepney_locations\n";
}

echo "\nRoutes in jeepney_assignments table:\n";
$assignmentsQuery = "SELECT DISTINCT route FROM jeepney_assignments WHERE route IS NOT NULL AND route != ''";
$assignmentsResult = $conn->query($assignmentsQuery);
if ($assignmentsResult && $assignmentsResult->num_rows > 0) {
    while ($row = $assignmentsResult->fetch_assoc()) {
        echo "- '" . $row['route'] . "'\n";
    }
} else {
    echo "No routes found in jeepney_assignments\n";
}

echo "\nRoutes in fare_payments table:\n";
$faresQuery = "SELECT DISTINCT route FROM fare_payments WHERE route IS NOT NULL AND route != ''";
$faresResult = $conn->query($faresQuery);
if ($faresResult && $faresResult->num_rows > 0) {
    while ($row = $faresResult->fetch_assoc()) {
        echo "- '" . $row['route'] . "'\n";
    }
} else {
    echo "No routes found in fare_payments\n";
}

echo "\n=== SAMPLE DATA ===\n";
echo "Sample jeepney_locations:\n";
$sampleLocations = $conn->query("SELECT * FROM jeepney_locations LIMIT 3");
if ($sampleLocations && $sampleLocations->num_rows > 0) {
    while ($row = $sampleLocations->fetch_assoc()) {
        echo "- Route: '" . $row['route'] . "', Lat: " . $row['lat'] . ", Lng: " . $row['lng'] . "\n";
    }
}

echo "\nSample jeepney_assignments:\n";
$sampleAssignments = $conn->query("SELECT * FROM jeepney_assignments LIMIT 3");
if ($sampleAssignments && $sampleAssignments->num_rows > 0) {
    while ($row = $sampleAssignments->fetch_assoc()) {
        echo "- Driver ID: " . $row['driver_id'] . ", Route: '" . $row['route'] . "', Plate: " . $row['plate_number'] . "\n";
    }
}
?> 