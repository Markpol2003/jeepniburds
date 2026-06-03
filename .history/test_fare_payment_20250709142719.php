<?php
require_once 'db_config.php';

// Test fare payment simulation
if ($_POST['test_payment']) {
    $passenger_id = $_POST['passenger_id'];
    $route = $_POST['route'];
    $amount = $_POST['amount'];
    $payment_method = $_POST['payment_method'];
    
    $receipt_number = 'FARE-' . rand(10000,99999);
    $paid_at = date('Y-m-d H:i:s');
    $status = 'Paid';
    
    $stmt = $conn->prepare("INSERT INTO fare_payments (passenger_id, route, amount, payment_method, receipt_number, paid_at, status) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param('isdssss', $passenger_id, $route, $amount, $payment_method, $receipt_number, $paid_at, $status);
    
    if ($stmt->execute()) {
        echo "<div style='background: #d4edda; color: #155724; padding: 10px; margin: 10px 0; border-radius: 5px;'>";
        echo "✅ Test payment successful!<br>";
        echo "Receipt: $receipt_number<br>";
        echo "Route: $route<br>";
        echo "Amount: ₱$amount<br>";
        echo "Method: $payment_method<br>";
        echo "Time: $paid_at";
        echo "</div>";
    } else {
        echo "<div style='background: #f8d7da; color: #721c24; padding: 10px; margin: 10px 0; border-radius: 5px;'>";
        echo "❌ Payment failed: " . $stmt->error;
        echo "</div>";
    }
}

// Get users for testing
$usersQuery = "SELECT id, firstName, lastName, user_type FROM users WHERE user_type IN ('passenger', 'driver') LIMIT 10";
$usersResult = $conn->query($usersQuery);
$users = [];
while ($row = $usersResult->fetch_assoc()) {
    $users[] = $row;
}

// Get existing routes
$routesQuery = "SELECT DISTINCT route FROM fare_payments UNION SELECT DISTINCT route FROM jeepney_assignments WHERE route IS NOT NULL AND route != ''";
$routesResult = $conn->query($routesQuery);
$routes = [];
while ($row = $routesResult->fetch_assoc()) {
    $routes[] = $row['route'];
}

if (empty($routes)) {
    $routes = ['Route 1', 'Route 2', 'Route 3']; // Default routes
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test Fare Payment</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container mt-5">
        <div class="row">
            <div class="col-md-8 mx-auto">
                <div class="card shadow">
                    <div class="card-header bg-primary text-white">
                        <h4 class="mb-0">Test Fare Payment System</h4>
                    </div>
                    <div class="card-body">
                        <form method="post">
                            <div class="mb-3">
                                <label class="form-label">Passenger</label>
                                <select name="passenger_id" class="form-select" required>
                                    <option value="">Select Passenger</option>
                                    <?php foreach ($users as $user): ?>
                                        <?php if ($user['user_type'] === 'passenger'): ?>
                                            <option value="<?= $user['id'] ?>">
                                                <?= $user['firstName'] . ' ' . $user['lastName'] ?> (<?= $user['user_type'] ?>)
                                            </option>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Route</label>
                                <select name="route" class="form-select" required>
                                    <option value="">Select Route</option>
                                    <?php foreach ($routes as $route): ?>
                                        <option value="<?= $route ?>"><?= $route ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Amount</label>
                                <input type="number" name="amount" class="form-control" placeholder="Enter amount" required>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Payment Method</label>
                                <select name="payment_method" class="form-select" required>
                                    <option value="">Select Method</option>
                                    <option value="Cash">Cash</option>
                                    <option value="GCash">GCash</option>
                                    <option value="Bank">Bank</option>
                                </select>
                            </div>
                            
                            <button type="submit" name="test_payment" class="btn btn-primary">Simulate Payment</button>
                        </form>
                        
                        <hr>
                        
                        <h5>Recent Payments</h5>
                        <?php
                        $recentQuery = "SELECT fp.*, u.firstName, u.lastName FROM fare_payments fp JOIN users u ON fp.passenger_id = u.id ORDER BY fp.paid_at DESC LIMIT 5";
                        $recentResult = $conn->query($recentQuery);
                        
                        if ($recentResult && $recentResult->num_rows > 0):
                        ?>
                            <div class="table-responsive">
                                <table class="table table-sm">
                                    <thead>
                                        <tr>
                                            <th>Passenger</th>
                                            <th>Route</th>
                                            <th>Amount</th>
                                            <th>Method</th>
                                            <th>Status</th>
                                            <th>Time</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php while ($row = $recentResult->fetch_assoc()): ?>
                                            <tr>
                                                <td><?= $row['firstName'] . ' ' . $row['lastName'] ?></td>
                                                <td><?= $row['route'] ?></td>
                                                <td>₱<?= $row['amount'] ?></td>
                                                <td><?= $row['payment_method'] ?></td>
                                                <td><span class="badge bg-<?= $row['status'] === 'Collected' ? 'success' : 'warning' ?>"><?= $row['status'] ?></span></td>
                                                <td><?= date('M d, H:i', strtotime($row['paid_at'])) ?></td>
                                            </tr>
                                        <?php endwhile; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <p class="text-muted">No recent payments found.</p>
                        <?php endif; ?>
                        
                        <hr>
                        
                        <h5>Active Driver Assignments</h5>
                        <?php
                        $assignmentsQuery = "SELECT ja.*, u.firstName, u.lastName FROM jeepney_assignments ja JOIN users u ON ja.driver_id = u.id WHERE ja.status = 'Active'";
                        $assignmentsResult = $conn->query($assignmentsQuery);
                        
                        if ($assignmentsResult && $assignmentsResult->num_rows > 0):
                        ?>
                            <div class="table-responsive">
                                <table class="table table-sm">
                                    <thead>
                                        <tr>
                                            <th>Driver</th>
                                            <th>Route</th>
                                            <th>Plate Number</th>
                                            <th>Assigned Date</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php while ($row = $assignmentsResult->fetch_assoc()): ?>
                                            <tr>
                                                <td><?= $row['firstName'] . ' ' . $row['lastName'] ?></td>
                                                <td><?= $row['route'] ?></td>
                                                <td><?= $row['plate_number'] ?></td>
                                                <td><?= date('M d, Y', strtotime($row['assigned_date'])) ?></td>
                                            </tr>
                                        <?php endwhile; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <p class="text-muted">No active driver assignments found.</p>
                        <?php endif; ?>
                        
                        <div class="mt-3">
                            <a href="debug_fare_system.php" class="btn btn-info">Debug System</a>
                            <a href="add_status_column.php" class="btn btn-warning">Fix Database</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html> 