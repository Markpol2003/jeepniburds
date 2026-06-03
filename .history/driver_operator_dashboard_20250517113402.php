<?php
session_start();
require_once 'db_config.php';

// Check if the user is logged in and is a driver/operator
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'driver_operator') {
    header("Location: landing.php");
    exit();
}

// Fetch session data
$userId = $_SESSION['user_id'];
$userEmail = $_SESSION['user_email'] ?? "";
$userFirstName = $_SESSION['user_firstName'] ?? "";
$userMiddleName = $_SESSION['user_middleName'] ?? "";
$userLastName = $_SESSION['user_lastName'] ?? "";
$userPhoto = $_SESSION['user_photo'] ?? "default.jpg";

// Fetch user's receipts
$receiptsQuery = "SELECT r.*, p.payment_method, p.created_at as payment_date 
                 FROM user_receipts r 
                 JOIN membership_payments p ON r.payment_id = p.id 
                 WHERE r.user_id = ? 
                 ORDER BY r.payment_date DESC";
$stmt = $conn->prepare($receiptsQuery);
$stmt->bind_param("i", $userId);
$stmt->execute();
$receipts = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Driver/Operator Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f4f4f9;
        }
        .sidebar {
            background-color: #34495e;
            color: white;
            min-height: 100vh;
            padding: 20px;
        }
        .sidebar .logo {
            text-align: center;
            margin-bottom: 30px;
        }
        .sidebar ul {
            list-style: none;
            padding: 0;
        }
        .sidebar ul li a {
            color: white;
            text-decoration: none;
            padding: 10px 15px;
            display: block;
            border-radius: 5px;
            margin-bottom: 5px;
        }
        .sidebar ul li a:hover {
            background-color: #2c3e50;
        }
        .content {
            padding: 20px;
        }
        .receipt-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .receipt-header {
            border-bottom: 2px solid #eee;
            padding-bottom: 10px;
            margin-bottom: 15px;
        }
        .receipt-details {
            margin-bottom: 15px;
        }
        .receipt-footer {
            border-top: 2px solid #eee;
            padding-top: 10px;
            margin-top: 15px;
        }
        .status-badge {
            padding: 5px 10px;
            border-radius: 15px;
            font-size: 0.9em;
        }
        .status-confirmed {
            background-color: #d4edda;
            color: #155724;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-3 col-lg-2 sidebar">
                <div class="logo">
                    <h2>TEBZ</h2>
                </div>
                <ul>
                    <li><a href="#profile">Profile</a></li>
                    <li><a href="#receipts">My Receipts</a></li>
                    <li><a href="logout.php">Logout</a></li>
                </ul>
            </div>

            <!-- Main Content -->
            <div class="col-md-9 col-lg-10 content">
                <h1 class="mb-4">Welcome, <?php echo htmlspecialchars($userFirstName . ' ' . $userLastName); ?>!</h1>
                
                <!-- Receipts Section -->
                <div id="receipts" class="mt-4">
                    <h2>My Receipts</h2>
                    <?php if (empty($receipts)): ?>
                        <div class="alert alert-info">No receipts found.</div>
                    <?php else: ?>
                        <?php foreach ($receipts as $receipt): ?>
                            <div class="receipt-card">
                                <div class="receipt-header d-flex justify-content-between align-items-center">
                                    <h3>Receipt #<?php echo htmlspecialchars($receipt['receipt_number']); ?></h3>
                                    <span class="status-badge status-confirmed"><?php echo htmlspecialchars($receipt['status']); ?></span>
                                </div>
                                <div class="receipt-details">
                                    <p><strong>Date:</strong> <?php echo date('F d, Y', strtotime($receipt['payment_date'])); ?></p>
                                    <p><strong>Amount:</strong> ₱<?php echo number_format($receipt['amount'], 2); ?></p>
                                    <p><strong>Payment Method:</strong> <?php echo ucfirst(htmlspecialchars($receipt['payment_method'])); ?></p>
                                </div>
                                <div class="receipt-footer">
                                    <button class="btn btn-primary" onclick="downloadReceipt(<?php echo $receipt['id']; ?>)">Download Receipt</button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function downloadReceipt(receiptId) {
            // Implement receipt download functionality
            window.location.href = `download_receipt.php?receipt_id=${receiptId}`;
        }
    </script>
</body>
</html> 