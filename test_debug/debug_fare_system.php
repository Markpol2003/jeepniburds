<?php
require_once 'db_config.php';

echo "<h2>Fare System Debug</h2>";

// Check database structure
echo "<h3>1. Database Structure Check</h3>";
$structureQuery = "DESCRIBE fare_payments";
$structureResult = $conn->query($structureQuery);

if ($structureResult) {
    echo "<table border='1' style='border-collapse: collapse;'>";
    echo "<tr><th>Column</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th></tr>";
    while ($row = $structureResult->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . $row['Field'] . "</td>";
        echo "<td>" . $row['Type'] . "</td>";
        echo "<td>" . $row['Null'] . "</td>";
        echo "<td>" . $row['Key'] . "</td>";
        echo "<td>" . $row['Default'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "Error checking table structure: " . $conn->error;
}

// Check all fare payments
echo "<h3>2. All Fare Payments</h3>";
$allFaresQuery = "SELECT fp.*, u.firstName, u.lastName FROM fare_payments fp JOIN users u ON fp.passenger_id = u.id ORDER BY fp.paid_at DESC LIMIT 10";
$allFaresResult = $conn->query($allFaresQuery);

if ($allFaresResult && $allFaresResult->num_rows > 0) {
    echo "<table border='1' style='border-collapse: collapse;'>";
    echo "<tr><th>ID</th><th>Passenger</th><th>Route</th><th>Amount</th><th>Method</th><th>Status</th><th>Paid At</th><th>Receipt</th></tr>";
    while ($row = $allFaresResult->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . $row['id'] . "</td>";
        echo "<td>" . $row['firstName'] . " " . $row['lastName'] . "</td>";
        echo "<td>" . $row['route'] . "</td>";
        echo "<td>₱" . $row['amount'] . "</td>";
        echo "<td>" . $row['payment_method'] . "</td>";
        echo "<td>" . ($row['status'] ?? 'N/A') . "</td>";
        echo "<td>" . $row['paid_at'] . "</td>";
        echo "<td>" . $row['receipt_number'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "No fare payments found or error: " . $conn->error;
}

// Check jeepney assignments
echo "<h3>3. Jeepney Assignments</h3>";
$assignmentsQuery = "SELECT ja.*, u.firstName, u.lastName FROM jeepney_assignments ja JOIN users u ON ja.driver_id = u.id WHERE ja.status = 'Active'";
$assignmentsResult = $conn->query($assignmentsQuery);

if ($assignmentsResult && $assignmentsResult->num_rows > 0) {
    echo "<table border='1' style='border-collapse: collapse;'>";
    echo "<tr><th>Driver</th><th>Route</th><th>Plate Number</th><th>Status</th><th>Assigned Date</th></tr>";
    while ($row = $assignmentsResult->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . $row['firstName'] . " " . $row['lastName'] . "</td>";
        echo "<td>" . $row['route'] . "</td>";
        echo "<td>" . $row['plate_number'] . "</td>";
        echo "<td>" . $row['status'] . "</td>";
        echo "<td>" . $row['assigned_date'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "No active jeepney assignments found.";
}

// Test route matching
echo "<h3>4. Route Matching Test</h3>";
$routesQuery = "SELECT DISTINCT route FROM fare_payments";
$routesResult = $conn->query($routesQuery);

if ($routesResult && $routesResult->num_rows > 0) {
    echo "<p>Routes with fare payments:</p>";
    echo "<ul>";
    while ($row = $routesResult->fetch_assoc()) {
        echo "<li>" . $row['route'] . "</li>";
    }
    echo "</ul>";
    
    // Check if any routes match between assignments and payments
    $matchingQuery = "
        SELECT DISTINCT ja.route 
        FROM jeepney_assignments ja 
        WHERE ja.status = 'Active' 
        AND ja.route IN (SELECT DISTINCT route FROM fare_payments)
    ";
    $matchingResult = $conn->query($matchingQuery);
    
    if ($matchingResult && $matchingResult->num_rows > 0) {
        echo "<p>Routes with both assignments and payments:</p>";
        echo "<ul>";
        while ($row = $matchingResult->fetch_assoc()) {
            echo "<li>" . $row['route'] . "</li>";
        }
        echo "</ul>";
    } else {
        echo "<p style='color: red;'>⚠️ No matching routes found between assignments and payments!</p>";
    }
} else {
    echo "No routes found in fare payments.";
}

echo "<h3>5. Test API Endpoints</h3>";
echo "<p>Test the pay_fare.php list endpoint:</p>";
echo "<form method='post'>";
echo "<input type='text' name='test_route' placeholder='Enter route to test' value='Route 1'>";
echo "<button type='submit'>Test List Endpoint</button>";
echo "</form>";

if ($_POST['test_route']) {
    $testRoute = $_POST['test_route'];
    echo "<h4>Testing route: " . htmlspecialchars($testRoute) . "</h4>";
    
    // Simulate the API call
    $stmt = $conn->prepare("SELECT fp.*, u.firstName, u.lastName FROM fare_payments fp JOIN users u ON fp.passenger_id = u.id WHERE fp.route = ? ORDER BY fp.paid_at DESC");
    $stmt->bind_param('s', $testRoute);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        echo "<p>Found " . $result->num_rows . " fare payments for route: " . htmlspecialchars($testRoute) . "</p>";
        echo "<table border='1' style='border-collapse: collapse;'>";
        echo "<tr><th>Passenger</th><th>Amount</th><th>Method</th><th>Status</th><th>Paid At</th></tr>";
        while ($row = $result->fetch_assoc()) {
            echo "<tr>";
            echo "<td>" . $row['firstName'] . " " . $row['lastName'] . "</td>";
            echo "<td>₱" . $row['amount'] . "</td>";
            echo "<td>" . $row['payment_method'] . "</td>";
            echo "<td>" . ($row['status'] ?? 'N/A') . "</td>";
            echo "<td>" . $row['paid_at'] . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p style='color: red;'>No fare payments found for route: " . htmlspecialchars($testRoute) . "</p>";
    }
}

echo "<h3>6. Recommendations</h3>";
echo "<ul>";
echo "<li>Make sure the driver has an active jeepney assignment</li>";
echo "<li>Ensure the route names match exactly between passenger payments and driver assignments</li>";
echo "<li>Check that the fare_payments table has the required columns (status, paid_at)</li>";
echo "<li>Verify that passengers are paying for the correct route</li>";
echo "</ul>";

echo "<p><a href='add_status_column.php'>Run Database Structure Fix</a></p>";
?> 