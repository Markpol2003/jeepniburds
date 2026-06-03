<?php
session_start();
require_once __DIR__ . '/../db_config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'passenger') {
    header('Location: login.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['fare_amount'])) {
    $passengerId = $_SESSION['user_id'];
    $amount = floatval($_POST['fare_amount']);
    $paymentMethod = 'Cash'; // Default, or extend to support more
    $status = 'Paid';
    $route = $_SESSION['assigned_route'] ?? null; // Set this in session when assigning jeepney
    $paidAt = date('Y-m-d H:i:s');

    if (!$route) {
        // Try to fetch route from DB if not in session
        $stmt = $conn->prepare('SELECT ja.route FROM jeepney_assignments ja WHERE ja.passenger_id = ? AND ja.status = "Active" ORDER BY ja.assigned_date DESC LIMIT 1');
        $stmt->bind_param('i', $passengerId);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $route = $row['route'] ?? null;
    }

    if ($route && $amount > 0) {
        $receipt_number = 'FARE-' . rand(10000,99999);
        $stmt = $conn->prepare('INSERT INTO fare_payments (passenger_id, amount, payment_method, route, paid_at, status, receipt_number) VALUES (?, ?, ?, ?, ?, ?, ?)');
        $stmt->bind_param('idsssss', $passengerId, $amount, $paymentMethod, $route, $paidAt, $status, $receipt_number);
        if ($stmt->execute()) {
            // Success: redirect or show message
            $_SESSION['fare_success'] = 'Fare payment confirmed!';
            header('Location: ../passenger/passenger_dashboard.php?page=board_fare');
            exit();
        } else {
            $error = 'Failed to record fare payment.';
        }
    } else {
        $error = 'Missing route or invalid amount.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Confirm Fare</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="card shadow-lg p-4">
                <h3 class="mb-3">Confirm Fare Payment</h3>
                <?php if (!empty($error)): ?>
                    <div class="alert alert-danger"> <?= htmlspecialchars($error) ?> </div>
                <?php endif; ?>
                <a href="../passenger/passenger_dashboard.php?page=board_fare" class="btn btn-outline-primary mt-3">Back</a>
            </div>
        </div>
    </div>
</div>
</body>
</html> 