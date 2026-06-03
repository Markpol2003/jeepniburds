<?php
require_once 'db_config.php';

// Get the current user ID (assuming you're logged in as a driver)
session_start();
$userId = $_SESSION['user_id'] ?? 1; // Default to user ID 1 if not logged in

echo "=== DRIVER ASSIGNMENT DEBUG ===\n";
echo "Driver ID: $userId\n\n";

// Check driver assignment
$jeepneyQuery = "
    SELECT ja.*, u.firstName, u.lastName 
    FROM jeepney_assignments ja
    JOIN users u ON ja.driver_id = u.id
    WHERE ja.driver_id = ? AND ja.status = 'Active'
    ORDER BY ja.assigned_date DESC
    LIMIT 1
";

$stmt = $conn->prepare($jeepneyQuery);
$stmt->bind_param("i", $userId);
$stmt->execute();
$jeepneyResult = $stmt->get_result();
$assignedJeepney = $jeepneyResult->fetch_assoc();

if ($assignedJeepney) {
    echo "✅ Driver has assigned jeepney:\n";
    echo "- Route: " . $assignedJeepney['route'] . "\n";
    echo "- Plate Number: " . $assignedJeepney['plate_number'] . "\n";
    echo "- Body Number: " . $assignedJeepney['body_number'] . "\n";
    echo "- Assigned Date: " . $assignedJeepney['assigned_date'] . "\n";
} else {
    echo "❌ Driver has NO assigned jeepney\n";
}

echo "\n=== FARE PAYMENTS DEBUG ===\n";

// Check all fare payments
$allFaresQuery = "SELECT fp.*, u.firstName, u.lastName FROM fare_payments fp JOIN users u ON fp.passenger_id = u.id ORDER BY fp.paid_at DESC LIMIT 10";
$allFaresResult = $conn->query($allFaresQuery);

if ($allFaresResult && $allFaresResult->num_rows > 0) {
    echo "Recent fare payments:\n";
    while ($row = $allFaresResult->fetch_assoc()) {
        echo "- " . $row['firstName'] . " " . $row['lastName'] . " paid ₱" . $row['amount'] . " for route '" . $row['route'] . "' via " . $row['payment_method'] . " (Status: " . $row['status'] . ")\n";
    }
} else {
    echo "No fare payments found in database\n";
}

// Check fare payments for the driver's assigned route
if ($assignedJeepney && !empty($assignedJeepney['route'])) {
    echo "\n=== FARE PAYMENTS FOR DRIVER'S ROUTE ===\n";
    echo "Driver's route: '" . $assignedJeepney['route'] . "'\n";
    
    $routeFaresQuery = "SELECT fp.*, u.firstName, u.lastName FROM fare_payments fp JOIN users u ON fp.passenger_id = u.id WHERE fp.route = ? ORDER BY fp.paid_at DESC";
    $routeStmt = $conn->prepare($routeFaresQuery);
    $routeStmt->bind_param('s', $assignedJeepney['route']);
    $routeStmt->execute();
    $routeFaresResult = $routeStmt->get_result();
    
    if ($routeFaresResult && $routeFaresResult->num_rows > 0) {
        echo "Fare payments for route '" . $assignedJeepney['route'] . "':\n";
        while ($row = $routeFaresResult->fetch_assoc()) {
            echo "- " . $row['firstName'] . " " . $row['lastName'] . " paid ₱" . $row['amount'] . " via " . $row['payment_method'] . " (Receipt: " . $row['receipt_number'] . ", Status: " . $row['status'] . ")\n";
        }
    } else {
        echo "No fare payments found for route '" . $assignedJeepney['route'] . "'\n";
    }
}

echo "\n=== ALL ROUTES IN SYSTEM ===\n";
$routesQuery = "SELECT DISTINCT route FROM fare_payments WHERE route IS NOT NULL AND route != ''";
$routesResult = $conn->query($routesQuery);

if ($routesResult && $routesResult->num_rows > 0) {
    echo "Routes with fare payments:\n";
    while ($row = $routesResult->fetch_assoc()) {
        echo "- '" . $row['route'] . "'\n";
    }
} else {
    echo "No routes found in fare payments\n";
}

echo "\n=== DRIVER ASSIGNMENTS ===\n";
$assignmentsQuery = "SELECT ja.*, u.firstName, u.lastName FROM jeepney_assignments ja JOIN users u ON ja.driver_id = u.id WHERE ja.status = 'Active'";
$assignmentsResult = $conn->query($assignmentsQuery);

if ($assignmentsResult && $assignmentsResult->num_rows > 0) {
    echo "Active driver assignments:\n";
    while ($row = $assignmentsResult->fetch_assoc()) {
        echo "- " . $row['firstName'] . " " . $row['lastName'] . " assigned to route '" . $row['route'] . "' (Plate: " . $row['plate_number'] . ")\n";
    }
} else {
    echo "No active driver assignments found\n";
}
?> 