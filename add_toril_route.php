<?php
require_once 'db_config.php';

// Add toril route to jeepney_locations if it doesn't exist
$checkQuery = "SELECT COUNT(*) as count FROM jeepney_locations WHERE route = 'toril'";
$checkResult = $conn->query($checkQuery);
$checkRow = $checkResult->fetch_assoc();

if ($checkRow['count'] == 0) {
    // Insert toril route
    $insertQuery = "INSERT INTO jeepney_locations (route, lat, lng, updated_at) VALUES ('toril', 14.5995, 120.9842, NOW())";
    if ($conn->query($insertQuery)) {
        echo "✅ Added 'toril' route to jeepney_locations table\n";
    } else {
        echo "❌ Error adding toril route: " . $conn->error . "\n";
    }
} else {
    echo "✅ 'toril' route already exists in jeepney_locations table\n";
}

// Also ensure there's a driver assigned to toril route
$driverCheckQuery = "SELECT COUNT(*) as count FROM jeepney_assignments WHERE route = 'toril' AND status = 'Active'";
$driverCheckResult = $conn->query($driverCheckQuery);
$driverCheckRow = $driverCheckResult->fetch_assoc();

if ($driverCheckRow['count'] == 0) {
    echo "⚠️  No active driver assigned to 'toril' route\n";
    echo "Please assign a driver to the 'toril' route in the operator dashboard\n";
} else {
    echo "✅ Driver is assigned to 'toril' route\n";
}

echo "\n=== CURRENT STATUS ===\n";
echo "Routes available for passengers:\n";
$routesQuery = "SELECT DISTINCT route FROM jeepney_locations";
$routesResult = $conn->query($routesQuery);
while ($row = $routesResult->fetch_assoc()) {
    echo "- " . $row['route'] . "\n";
}

echo "\nActive driver assignments:\n";
$assignmentsQuery = "SELECT ja.*, u.firstName, u.lastName FROM jeepney_assignments ja JOIN users u ON ja.driver_id = u.id WHERE ja.status = 'Active'";
$assignmentsResult = $conn->query($assignmentsQuery);
while ($row = $assignmentsResult->fetch_assoc()) {
    echo "- " . $row['firstName'] . " " . $row['lastName'] . " assigned to '" . $row['route'] . "'\n";
}
?> 