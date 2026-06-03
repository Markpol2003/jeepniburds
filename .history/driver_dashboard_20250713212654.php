<?php
session_start();
require_once 'db_config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'driver') {
    header("Location: landing.php");
    exit();
}

$userId = $_SESSION['user_id'];
$userType = ucfirst($_SESSION['user_type']);
$userFirstName = $_SESSION['user_firstName'] ?? '';
$userLastName = $_SESSION['user_lastName'] ?? '';
$userEmail = $_SESSION['user_email'] ?? '';
$profileImage = 'uploads/profile_' . $userId . '.jpg';

$page = $_GET['page'] ?? 'dashboard';

// Fetch assigned jeepney details
$jeepneyQuery = "
    SELECT ja.*, u.firstName, u.lastName, o.id as operator_id, o.firstName as operator_firstName, o.lastName as operator_lastName
    FROM jeepney_assignments ja
    JOIN users u ON ja.driver_id = u.id
    JOIN users o ON ja.operator_id = o.id
    WHERE ja.driver_id = ? AND ja.status = 'Active'
    ORDER BY ja.assigned_date DESC
    LIMIT 1
";

$stmt = $conn->prepare($jeepneyQuery);
$stmt->bind_param("i", $userId);
$stmt->execute();
$jeepneyResult = $stmt->get_result();
$assignedJeepney = $jeepneyResult->fetch_assoc();

// Handle Payment Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['payment_method'])) {
    header('Content-Type: application/json');
    
    try {
        // Validate required fields based on payment method
        $paymentMethod = $_POST['payment_method'];
        $requiredFields = [
            'gcash' => ['gcash_number', 'gcash_name'],
            'bank' => ['bank_name', 'bank_account', 'bank_account_name'],
            'cash' => ['reference_number']
        ];

        // Check if all required fields are present
        if (!isset($requiredFields[$paymentMethod])) {
            throw new Exception('Invalid payment method');
        }

        foreach ($requiredFields[$paymentMethod] as $field) {
            if (empty($_POST[$field])) {
                throw new Exception("Missing required field: $field");
            }
        }

        // Generate receipt number
        $receiptNumber = 'TEBZ-' . date('Ymd') . '-' . str_pad($userId, 4, '0', STR_PAD_LEFT);

        // Insert payment record with Pending status
        $stmt = $conn->prepare("INSERT INTO membership_payments (user_id, amount, payment_method, receipt_number, status, created_at) VALUES (?, ?, ?, ?, 'Pending', NOW())");
        $amount = 1000; // Fixed amount
        $stmt->bind_param("idss", $userId, $amount, $paymentMethod, $receiptNumber);
        
        if (!$stmt->execute()) {
            throw new Exception('Failed to save payment record');
        }

        // Store payment details based on method
        $paymentDetails = [
            'receipt_number' => $receiptNumber,
            'amount' => $amount,
            'method' => $paymentMethod,
            'date' => date('Y-m-d H:i:s'),
            'name' => $userFirstName . ' ' . $userLastName,
            'status' => 'Pending'
        ];

        switch ($paymentMethod) {
            case 'gcash':
                $stmt = $conn->prepare("INSERT INTO payment_details (receipt_number, gcash_number, gcash_name) VALUES (?, ?, ?)");
                $stmt->bind_param("sss", $receiptNumber, $_POST['gcash_number'], $_POST['gcash_name']);
                break;
            case 'bank':
                $stmt = $conn->prepare("INSERT INTO payment_details (receipt_number, bank_name, bank_account, bank_account_name) VALUES (?, ?, ?, ?)");
                $stmt->bind_param("ssss", $receiptNumber, $_POST['bank_name'], $_POST['bank_account'], $_POST['bank_account_name']);
                break;
            case 'cash':
                $stmt = $conn->prepare("INSERT INTO payment_details (receipt_number, reference_number) VALUES (?, ?)");
                $stmt->bind_param("ss", $receiptNumber, $_POST['reference_number']);
                break;
        }

        if (!$stmt->execute()) {
            throw new Exception('Failed to save payment details');
        }

        echo json_encode([
            'success' => true,
            'message' => 'Payment submitted successfully',
            'receipt' => $paymentDetails
        ]);
        exit;

    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
        exit;
    }
}

// Handle Profile Image Upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['profile_image'])) {
    $uploadDir = 'uploads/';
    $profilePath = $uploadDir . 'profile_' . $userId . '.jpg';
    $fileName = $_FILES['profile_image']['name'];
    $fileTmp = $_FILES['profile_image']['tmp_name'];
    $fileSize = $_FILES['profile_image']['size'];
    $fileError = $_FILES['profile_image']['error'];
    $fileType = $_FILES['profile_image']['type'];

    // Check for errors...
    if ($fileError !== 0) {
        $errorMessage = "Failed to upload image. Error code: $fileError.";
    } else {
        // Validate file type (JPEG, PNG, or JPG)
        $allowedTypes = ['image/jpeg', 'image/png', 'image/jpg'];
        if (!in_array($fileType, $allowedTypes)) {
            $errorMessage = "Invalid file type. Only JPG, JPEG, and PNG files are allowed.";
        } elseif ($fileSize > 2 * 1024 * 1024) { // 2MB max file size
            $errorMessage = "File size too large. Maximum allowed size is 2MB.";
        } else {
            if (move_uploaded_file($fileTmp, $profilePath)) {
                $profileImage = $profilePath;
                $successMessage = "Profile image uploaded successfully.";
            } else {
                $errorMessage = "Failed to move the uploaded file.";
            }
        }
    }
}

// If the profile image doesn't exist, use a placeholder
if (!file_exists($profileImage)) {
    $profileImage = 'uploads/default_profile.png';
}

// === NEW SECTION ===
// Fetch upcoming orientation schedule (exclude today)
$upcomingQuery = "
    SELECT os.id, os.title, os.orientation_date AS date, os.orientation_time AS time, os.venue, os.link,
        (SELECT is_completed FROM orientation_attendees WHERE orientation_id = os.id AND user_id = ? LIMIT 1) AS is_completed
    FROM orientation_schedule os
    WHERE os.orientation_date >= CURDATE()
    ORDER BY os.orientation_date ASC, os.orientation_time ASC
    LIMIT 1
";

$upcomingStmt = $conn->prepare($upcomingQuery);
$upcomingStmt->bind_param("i", $userId);
$upcomingStmt->execute();
$upcomingResult = $upcomingStmt->get_result();
$upcomingAvailable = ($upcomingResult && $upcomingResult->num_rows > 0);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $userType; ?> Dashboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css"/>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/css/driveroperator.css" rel="stylesheet">
    <!-- Add OpenStreetMap and Leaflet -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
</head>
<body>
<!-- Navbar -->
<div class="d-flex align-items-center mb-4" style="margin-left: 250px; padding: 20px 30px 0;">
    <img src="\tebz\img\logo12.png" alt="JeepniGo Logo" style="height: 40px; margin-right: 15px;">
    <h4 class="mb-0 fw-bold"><?= $userType; ?> Dashboard</h4>
</div>

<!-- Main Layout -->
<div class="d-flex">
    <!-- Sidebar -->
    <div class="sidebar bg-dark text-white p-3" style="min-width: 220px;">
        <div class="text-center mb-4">
            <img src="<?= htmlspecialchars($profileImage); ?>" alt="Profile Picture"
                 class="rounded-circle border border-light shadow-sm"
                 style="width: 90px; height: 90px; object-fit: cover;">
            <h5 class="mt-3 mb-0"><?= htmlspecialchars($userFirstName . ' ' . $userLastName); ?></h5>
            <small class="text-muted text-capitalize"><?= htmlspecialchars($userType); ?></small>
        </div>

        <ul class="nav flex-column">
            <!-- Dashboard Link -->
            <li class="nav-item mb-2">
                <a class="nav-link text-white <?= $page === 'dashboard' ? 'fw-bold' : '' ?>" href="?page=dashboard">
                    <i class="bi bi-house-door me-2"></i> Dashboard
                </a>
            </li>
            <!-- Collect Fares Link -->
            <li class="nav-item mb-2">
                <a class="nav-link text-white <?= $page === 'collect_fares' ? 'fw-bold' : '' ?>" href="?page=collect_fares">
                    <i class="bi bi-cash-coin me-2"></i> All Fare Payments
                </a>
            </li>
            <!-- Profile Link -->
            <li class="nav-item mb-2">
                <a class="nav-link text-white <?= $page === 'profile' ? 'fw-bold' : '' ?>" href="?page=profile">
                    <i class="bi bi-person me-2"></i> Profile
                </a>
            </li>

            <!-- Pay Membership Link -->
            <li class="nav-item mb-2">
                <a class="nav-link text-white <?= $page === 'payment' ? 'fw-bold' : '' ?>" href="?page=payment">
                    <i class="bi bi-credit-card me-2"></i> Pay Membership
                </a>
            </li>

            <!-- Assign Jeepney Link -->
            <?php
            // Check if payment is confirmed
            $paymentCheckQuery = "SELECT * FROM membership_payments WHERE user_id = ? AND status = 'Confirmed' LIMIT 1";
            $paymentCheckStmt = $conn->prepare($paymentCheckQuery);
            $paymentCheckStmt->bind_param("i", $userId);
            $paymentCheckStmt->execute();
            $paymentCheckResult = $paymentCheckStmt->get_result();
            $hasPaid = $paymentCheckResult->num_rows > 0;
            ?>

            <li class="nav-item mb-2">
                <a class="nav-link text-white <?= $page === 'assignjeepney' ? 'fw-bold' : '' ?> <?= !$hasPaid ? 'disabled-link' : '' ?>"
                   href="<?= $hasPaid ? '?page=assignjeepney' : '#' ?>"
                   onclick="<?= !$hasPaid ? "Swal.fire({icon: 'info', title: 'Access Denied', text: 'Complete your payment first.'}); return false;" : '' ?>">
                    <i class="bi bi-truck-front me-2"></i> Assigned Jeepney
                </a>
            </li>

            <style>
                .disabled-link {
                    opacity: 0.6;
                    pointer-events: none;
                    cursor: not-allowed;
                }
            </style>

            <!-- Pay Boundary Link -->
            <li class="nav-item mb-2">
                <a class="nav-link text-white <?= $page === 'pay_boundary' ? 'fw-bold' : '' ?>" href="?page=pay_boundary">
                    <i class="bi bi-wallet2 me-2"></i> Pay Boundary
                </a>
            </li>

            <!-- Logout Link -->
            <li class="nav-item mt-3">
                <a class="btn btn-outline-light w-100" href="logout.php">
                    <i class="bi bi-box-arrow-right"></i> Logout
                </a>
            </li>
        </ul>
    </div>

    <!-- Main Content -->
    <div class="content container" style="margin-left: 250px;">
        <?php if ($page === 'dashboard'): ?>
            <!-- Dashboard -->
            <div class="card profile-card shadow-lg border-0 mb-4">
                <div class="card-body text-center">
                    <div class="profile-avatar mb-3 mx-auto">
                        <img src="<?= htmlspecialchars($profileImage); ?>" alt="Profile Picture" class="rounded-circle shadow" width="110" height="110">
                    </div>
                    <h3 class="fw-bold mb-1"><?= htmlspecialchars($userFirstName . ' ' . $userLastName); ?></h3>
                    <span class="badge bg-secondary px-3 py-2 text-uppercase"><?= htmlspecialchars($userType); ?></span>
                </div>
            </div>






        <?php elseif ($page === 'collect_fares'): ?>
            <?php if ($assignedJeepney): ?>
            <!-- Collect Fares Section (Modernized) -->
            <div class="card shadow-lg border-0 mb-4 animate__animated animate__fadeIn">
                <div class="card-header d-flex justify-content-between align-items-center" style="background: linear-gradient(90deg, #1abc9c 0%, #3498db 100%); border-top-left-radius: 1rem; border-top-right-radius: 1rem;">
                    <h5 class="mb-0 text-white d-flex align-items-center">
                            <i class="bi bi-cash-coin me-2 fs-3"></i> All Fare Payments
                        <span id="newFaresBadge" class="badge bg-danger ms-2 d-none">0</span>
                    </h5>
                    <button class="btn btn-light btn-sm shadow-sm" id="fetchFaresBtn"><i class="bi bi-arrow-repeat"></i> Refresh</button>
                </div>
                <div class="card-body p-0">
                    <div id="faresLoading" class="text-center text-muted my-4" style="display:none;">
                        <div class="spinner-border text-info" role="status"></div>
                        <div>Loading fares...</div>
                    </div>
                    <div id="faresTableContainer" class="p-3"></div>
                </div>
            </div>
            <?php else: ?>
                <!-- No Jeepney Assigned Message -->
                <div class="card shadow-lg border-0 mb-4 animate__animated animate__fadeIn">
                    <div class="card-header bg-warning text-dark">
                        <h5 class="mb-0 d-flex align-items-center">
                            <i class="bi bi-exclamation-triangle me-2"></i> No Jeepney Assigned
                        </h5>
                    </div>
                    <div class="card-body text-center py-5">
                        <div class="mb-4">
                            <i class="bi bi-truck-front text-muted" style="font-size: 4rem;"></i>
                        </div>
                        <h4 class="text-muted mb-3">No Jeepney Assigned Yet</h4>
                        <p class="text-muted mb-4">You need to be assigned a jeepney with a route before you can collect fares.</p>
                        <div class="alert alert-info">
                            <strong>Current Status:</strong><br>
                            - Driver ID: <?= $userId ?><br>
                            - Assigned Jeepney: <?= $assignedJeepney ? 'Yes' : 'No' ?><br>
                            - Route: <?= $assignedJeepney['route'] ?? 'None' ?><br>
                            <br>
                            <small>Please contact the operator to get assigned to a jeepney and route.</small>
                        </div>
                        <div class="alert alert-warning">
                            <strong>Debug Info:</strong><br>
                            - Available routes: Check <a href="check_routes.php" target="_blank">check_routes.php</a><br>
                            - Driver assignment: Check <a href="debug_driver_assignment.php" target="_blank">debug_driver_assignment.php</a><br>
                            - Add toril route: <a href="add_toril_route.php" target="_blank">add_toril_route.php</a>
                        </div>
                        <a href="debug_driver_assignment.php" class="btn btn-info" target="_blank">
                            <i class="bi bi-bug"></i> Debug Assignment
                        </a>
                    </div>
                </div>
            <?php endif; ?>
            <script>
            const route = <?= json_encode($assignedJeepney['route'] ?? '') ?>;
            console.log('Driver assigned route:', route);
            console.log('Driver ID:', <?= $userId ?>);
            let lastFareCount = 0;
            let lastFareReceipts = [];
            
            function fetchFares() {
                document.getElementById('faresLoading').style.display = 'block';
                console.log('Fetching all fares for driver');
                fetch('pay_fare.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'list' })
                })
                .then(res => res.json())
                .then(data => {
                    document.getElementById('faresLoading').style.display = 'none';
                    console.log('Fare fetch response:', data);
                    if (data.success && data.fares.length > 0) {
                        // Check for new fares
                        const currentReceipts = data.fares.map(fare => fare.receipt_number);
                        const newFares = data.fares.filter(fare => !lastFareReceipts.includes(fare.receipt_number));
                        
                        // Show notification for new fares
                        if (newFares.length > 0 && lastFareReceipts.length > 0) {
                            // Update notification badge
                            const badge = document.getElementById('newFaresBadge');
                            if (badge) {
                                badge.textContent = newFares.length;
                                badge.classList.remove('d-none');
                                // Hide badge after 5 seconds
                                setTimeout(() => {
                                    badge.classList.add('d-none');
                                }, 5000);
                            }
                            
                            newFares.forEach(fare => {
                                // Browser notification
                                if (Notification.permission === 'granted') {
                                    new Notification('New Fare Payment', {
                                        body: `${fare.passenger} paid ₱${fare.amount} via ${fare.payment_method} for ${fare.route}`,
                                        icon: '/tebz/img/logo12.png',
                                        badge: '/tebz/img/logo12.png'
                                    });
                                }
                                
                                // SweetAlert notification
                                Swal.fire({
                                    toast: true,
                                    position: 'top-end',
                                    icon: 'success',
                                    title: 'New Fare Payment!',
                                    text: `${fare.passenger} paid ₱${fare.amount} via ${fare.payment_method} for ${fare.route}`,
                                    showConfirmButton: false,
                                    timer: 4000,
                                    timerProgressBar: true
                                });
                            });
                        }
                        
                        // Update last fare receipts
                        lastFareReceipts = currentReceipts;
                        lastFareCount = data.fares.length;
                        
                        let html = `<div class='table-responsive'><table class='table table-hover table-borderless align-middle modern-fare-table'><thead><tr><th>Passenger</th><th>Route</th><th>Amount</th><th>Method</th><th>Date</th><th>Receipt</th><th>Status</th><th>Action</th></tr></thead><tbody>`;
                        data.fares.forEach(fare => {
                            const isCollected = fare.status === 'Collected';
                            const isNew = newFares.some(newFare => newFare.receipt_number === fare.receipt_number);
                            const routeMatch = fare.route === route ? 'bg-success text-white' : 'bg-info text-white';
                            html += `<tr class='fare-row animate__animated ${isNew ? 'animate__pulse' : 'animate__fadeIn'}' style='${isNew ? 'background-color: #fff3cd;' : ''}'>
                                <td><span class='fw-semibold'><i class="bi bi-person-circle me-1 text-primary"></i>${fare.passenger}</span></td>
                                <td><span class='badge ${routeMatch} px-2 py-1 text-uppercase small'>${fare.route}</span></td>
                                <td><span class='text-success fw-bold'>₱${fare.amount}</span></td>
                                <td><span class='badge bg-primary bg-gradient px-3 py-2 text-uppercase'>${fare.payment_method}</span></td>
                                <td><span class='text-muted'>${fare.paid_at}</span></td>
                                <td><span class='badge bg-light text-dark border px-3 py-2'>${fare.receipt_number}</span></td>
                                <td><span class='badge rounded-pill ${isCollected ? 'bg-success' : 'bg-warning text-dark'} px-3 py-2 fs-6'>${isCollected ? 'Collected' : 'Paid'}</span></td>
                                <td>`;
                            if (!isCollected) {
                                html += `<button class='btn btn-success btn-sm shadow-sm px-3' onclick=\"confirmCollected('${fare.receipt_number}', this)\"><i class='bi bi-check-circle me-1'></i>Confirm</button>`;
                            } else {
                                html += '<span class="text-muted">-</span>';
                            }
                            html += `</td></tr>`;
                        });
                        html += '</tbody></table></div>';
                        document.getElementById('faresTableContainer').innerHTML = html;
                        
                        // Remove highlight after 3 seconds
                        setTimeout(() => {
                            const highlightedRows = document.querySelectorAll('.fare-row[style*="background-color: #fff3cd"]');
                            highlightedRows.forEach(row => {
                                row.style.backgroundColor = '';
                                row.classList.remove('animate__pulse');
                            });
                        }, 3000);
                        
                    } else {
                        document.getElementById('faresTableContainer').innerHTML = '<div class="alert alert-info text-center my-4"><i class="bi bi-info-circle"></i> No fare payments available yet.</div>';
                        lastFareReceipts = [];
                        lastFareCount = 0;
                    }
                })
                .catch(() => {
                    document.getElementById('faresLoading').style.display = 'none';
                    document.getElementById('faresTableContainer').innerHTML = '<div class="alert alert-danger text-center my-4">Failed to load fares.</div>';
                });
            }
            
            function confirmCollected(receiptNumber, btn) {
                btn.disabled = true;
                btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';
                
                fetch('pay_fare.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'confirm', receipt_number: receiptNumber })
                })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        // Immediately update the UI
                        const statusCell = btn.closest('tr').querySelector('td:nth-child(6) span');
                        statusCell.className = 'badge rounded-pill bg-success px-3 py-2 fs-6';
                        statusCell.textContent = 'Collected';
                        
                        // Replace button with confirmation icon
                        const confirmIcon = document.createElement('span');
                        confirmIcon.className = 'text-success';
                        confirmIcon.innerHTML = '<i class="bi bi-check-circle-fill"></i> Confirmed';
                        btn.replaceWith(confirmIcon);
                        
                        // Add animation to the row
                        const row = btn.closest('tr');
                        row.style.backgroundColor = '#d4edda';
                        row.style.transition = 'background-color 0.5s ease';
                        
                        // Remove highlight after 3 seconds
                        setTimeout(() => {
                            row.style.backgroundColor = '';
                        }, 3000);
                        
                        // Show enhanced success notification
                        Swal.fire({
                            icon: 'success',
                            title: '🎉 Fare Collected Successfully!',
                            text: `Receipt #${receiptNumber} has been confirmed and marked as collected.`,
                            confirmButtonText: 'Great!',
                            confirmButtonColor: '#28a745',
                            timer: 4000,
                            timerProgressBar: true,
                            showClass: {
                                popup: 'animate__animated animate__bounceIn'
                            }
                        });
                        
                        // Send notification to passenger (if they're online)
                        notifyPassenger(receiptNumber);
                        
                    } else {
                        btn.disabled = false;
                        btn.innerHTML = '<i class="bi bi-check-circle me-1"></i>Confirm';
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: data.message || 'Failed to confirm payment.',
                            confirmButtonColor: '#d33'
                        });
                    }
                })
                .catch(() => {
                    btn.disabled = false;
                    btn.innerHTML = '<i class="bi bi-check-circle me-1"></i>Confirm';
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: 'Failed to confirm payment. Please try again.',
                        confirmButtonColor: '#d33'
                    });
                });
            }
            
            function notifyPassenger(receiptNumber) {
                // Send a notification to the passenger that their payment was confirmed
                fetch('notify_passenger.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ 
                        action: 'payment_confirmed',
                        receipt_number: receiptNumber,
                        driver_id: <?= $userId ?>,
                        driver_name: '<?= $userFirstName . ' ' . $userLastName ?>'
                    })
                })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        console.log('Passenger notification sent successfully');
                    }
                })
                .catch(err => {
                    console.log('Passenger notification failed:', err);
                });
            }
            
            // Request notification permission on page load
            document.addEventListener('DOMContentLoaded', function() {
                if (Notification.permission !== 'granted' && Notification.permission !== 'denied') {
                    Notification.requestPermission();
                }
            });
            
            document.getElementById('fetchFaresBtn').addEventListener('click', fetchFares);
            
            // Initial load
            fetchFares();
            
            // Auto-refresh every 10 seconds
            setInterval(fetchFares, 10000);
            </script>
        <?php endif; ?>

        <?php if ($page === 'dashboard'): ?>
            <div class="row">
                <!-- Today's Schedule -->
                <div class="col-md-6 mb-4">
                    <div class="card shadow-sm">
                        <div class="card-header bg-primary text-white">
                            <h5 class="mb-0">🕒 Today's Schedule</h5>
                        </div>
                        <div class="card-body">
                            <?php
                          $today = date('Y-m-d');
                          $todayQuery = "
                              SELECT os.id AS orientation_id, os.title, os.orientation_date, os.orientation_time, os.venue, os.link,
                                     oa.user_id AS attendee_user_id, oa.attended_mode, oa.is_completed
                              FROM orientation_schedule os
                              LEFT JOIN orientation_attendees oa ON oa.orientation_id = os.id AND oa.user_id = ?
                              WHERE os.orientation_date = ?
                              ORDER BY os.orientation_time ASC
                              LIMIT 1
                          ";
                          $todayStmt = $conn->prepare($todayQuery);
                          $todayStmt->bind_param("is", $userId, $today);
                          $todayStmt->execute();
                          $todayResult = $todayStmt->get_result();
                          $todayAvailable = ($todayResult && $todayResult->num_rows > 0);
                          

                            if ($todayResult->num_rows > 0):
                                $sched = $todayResult->fetch_assoc();
                                $hasAttended = !empty($sched['attendee_user_id']);
                                $mode = strtolower($sched['attended_mode'] ?? '');
                                $isToday = ($sched['orientation_date'] === $today);
                                ?>

                                <p><strong>Title:</strong> <?= htmlspecialchars($sched['title']); ?></p>
                                <p><strong>Date:</strong> <?= htmlspecialchars($sched['orientation_date']); ?>
                                    <?php if (!$isToday): ?>
                                        <span class="badge bg-warning text-dark ms-2">Upcoming</span>
                                    <?php endif; ?>
                                </p>
                                <p><strong>Time:</strong> <?= htmlspecialchars(date("g:i A", strtotime($sched['orientation_time']))); ?></p>

                                <p><strong>Mode:</strong>
                                    <i class="bi <?= $mode === 'online' ? 'bi-wifi' : 'bi-geo-alt-fill' ?>"></i>
                                    <span class="badge <?= $mode === 'online' ? 'bg-primary' : 'bg-secondary' ?>">
                                        <?= strtoupper($mode); ?>
                                    </span>
                                </p>

                                <?php if ($mode === 'online' && !empty($sched['link'])): ?>
                                    <p><strong>Meeting Link:</strong> 
                                        <a href="<?= htmlspecialchars($sched['link']); ?>" target="_blank" class="btn btn-sm btn-outline-primary">
                                            <i class="bi bi-camera-video-fill"></i> Join Meeting
                                        </a>
                                    </p>
                                <?php elseif ($mode === 'in-person' && !empty($sched['venue'])): ?>
                                    <p><strong>Venue:</strong> <?= htmlspecialchars($sched['venue']); ?></p>
                                <?php endif; ?>

                                <!-- Countdown -->
                                <div id="countdownTimer" class="text-danger fw-semibold mt-2"></div>
                                <script>
                                    let countdownInterval;
                                    function startCountdown(datetime) {
                                        const target = new Date(datetime).getTime();
                                        countdownInterval = setInterval(() => {
                                            const now = new Date().getTime();
                                            const diff = target - now;
                                            if (diff <= 0) {
                                                document.getElementById("countdownTimer").innerHTML = "🟢 Orientation has started!";
                                                document.getElementById("countdownTimer").className = "text-success fw-semibold mt-2";
                                                clearInterval(countdownInterval);
                                            } else {
                                                const hrs = Math.floor((diff % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
                                                const mins = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60));
                                                const secs = Math.floor((diff % (1000 * 60)) / 1000);
                                                document.getElementById("countdownTimer").innerHTML = `⏳ Starts in: ${hrs}h ${mins}m ${secs}s`;
                                            }
                                        }, 1000);
                                    }
                                    startCountdown("<?= $sched['orientation_date'] . ' ' . $sched['orientation_time'] ?>");

                                    function stopCountdown() {
                                        if (countdownInterval) {
                                            clearInterval(countdownInterval);
                                            const timer = document.getElementById("countdownTimer");
                                            if (timer) {
                                                timer.innerHTML = "✅ Orientation attendance completed!";
                                                timer.className = "text-success fw-semibold mt-2";
                                            }
                                        }
                                    }
                                </script>

                                <!-- Action Buttons -->
                                <?php if (!$hasAttended): ?>
                                    <div class="text-center mt-3">
                                        <p class="text-muted">You haven't confirmed your attendance yet.</p>
                                        <button onclick="submitAttendance(<?= $sched['orientation_id']; ?>, 'online')" class="btn btn-outline-primary btn-sm">Attend Online</button>
                                        <button onclick="submitAttendance(<?= $sched['orientation_id']; ?>, 'in-person')" class="btn btn-outline-secondary btn-sm">Attend In-Person</button>
                                    </div>
                                <?php elseif ($sched['is_completed']): ?>
                                    <div class="alert alert-success text-center mt-3 animate__animated animate__tada">
                                        <i class="bi bi-check-circle-fill"></i> Orientation Completed <br>
                                        <a href="?page=payment" class="btn btn-sm btn-success mt-2">
                                            <i class="bi bi-credit-card"></i> Pay Membership Fee Now
                                        </a>
                                    </div>
                                    <script>
                                        stopCountdown();
                                    </script>
                                <?php else: ?>
                                    <div class="alert alert-info text-center mt-3">
                                        <i class="bi bi-clock-history"></i> Waiting for manager to mark as completed...
                                    </div>
                                <?php endif; ?>

                            <?php else: ?>
                                <p class="mb-0">No schedules available for today. Stay tuned!</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <?php
                // Check if the user already requested to attend orientation
                $requestQuery = "SELECT * FROM orientation_requests WHERE user_id = ? AND status = 'Pending'";
                $requestStmt = $conn->prepare($requestQuery);
                $requestStmt->bind_param("i", $userId);
                $requestStmt->execute();
                $requestResult = $requestStmt->get_result();
                $hasRequestedOrientation = $requestResult->num_rows > 0;

                // Check if orientation schedule exists
                $scheduleQuery = "SELECT * FROM orientation_schedule WHERE orientation_date >= CURDATE() ORDER BY orientation_date ASC LIMIT 1";
                $scheduleResult = $conn->query($scheduleQuery);
                $scheduleAvailable = ($scheduleResult && $scheduleResult->num_rows > 0);

                ?>

<!-- Upcoming Orientation -->
<div class="col-md-6 mb-4">
    <div class="card shadow-sm">
        <div class="card-header bg-success text-white">
            <h5 class="mb-0">📅 Upcoming Orientation</h5>
        </div>
        <div class="card-body" id="orientationRequestSection">
            <?php if ($upcomingAvailable): ?>
                    <?php $schedule = $upcomingResult->fetch_assoc(); ?>
                    <p><strong>Title:</strong> <?= htmlspecialchars($schedule['title'] ?? 'N/A'); ?></p>
                    <p><strong>Date:</strong> <?= htmlspecialchars($schedule['date'] ?? 'N/A'); ?></p>
                    <p><strong>Time:</strong> <?= htmlspecialchars($schedule['time'] ?? 'N/A'); ?></p>

                    <?php if (!empty($schedule['is_completed'])): ?>
                        <div class="alert alert-success text-center animate__animated animate__fadeIn">
                            <i class="bi bi-check-circle-fill me-2"></i>
                            This orientation has already been completed.
                        </div>
                    <?php else: ?>
                        <div class="d-flex justify-content-center gap-2 mb-3">
                            <?php if (!empty($schedule['link'])): ?>
                                <button type="button" class="btn btn-outline-primary" onclick="showOrientation('online')">📡 View Online</button>
                            <?php endif; ?>
                            <?php if (!empty($schedule['venue'])): ?>
                                <button type="button" class="btn btn-outline-secondary" onclick="showOrientation('inperson')">🏢 View In-Person</button>
                            <?php endif; ?>
                        </div>

                        <?php if (!empty($schedule['link'])): ?>
                            <div id="onlineDetails" class="d-none text-center">
                                <p><strong>Meeting Link:</strong> <a href="<?= htmlspecialchars($schedule['link']); ?>" target="_blank" rel="noopener noreferrer">Join Meeting</a></p>
                                <button class="btn btn-info w-100" onclick="submitAttendance(<?= (int)$schedule['id']; ?>, 'online')">✅ Attend Online</button>
                            </div>
                        <?php endif; ?>

                        <?php if (!empty($schedule['venue'])): ?>
                            <div id="inpersonDetails" class="d-none text-center">
                                <p><strong>Venue:</strong> <?= htmlspecialchars($schedule['venue']); ?></p>
                                <button class="btn btn-secondary w-100" onclick="submitAttendance(<?= (int)$schedule['id']; ?>, 'in-person')">✅ Attend In-Person</button>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                <?php elseif (!$hasRequestedOrientation): ?>
                    <div class="text-center mt-5">
                        <p class="text-muted mb-4">Click the button below to notify the manager that you're ready to attend orientation.</p>
                        <button class="btn btn-success btn-lg px-5 py-3" style="
                            box-shadow: 0 4px 8px rgba(14, 77, 14, 0.4);
                            font-weight: 600;
                            border-radius: 12px;
                        " onclick="requestOrientation()">
                            I'm Ready to Attend Orientation!
                        </button>
                    </div>
                <?php elseif ($hasRequestedOrientation && !$upcomingAvailable): ?>
                    <div class="alert alert-success text-center mb-0">
                        ✅ You've already requested orientation.<br>
                        ⏳ Please wait for the manager to post the schedule.
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
            </div>
        <?php elseif ($page === 'profile'): ?>
            <!-- Profile Page -->
            <div class="card shadow-sm">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="bi bi-person-circle me-2"></i>My Profile</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-4 text-center mb-4">
                            <div class="position-relative d-inline-block">
                                <img src="<?= htmlspecialchars($profileImage); ?>" 
                                     id="profilePreview"
                                     class="rounded-circle shadow-lg mb-3" 
                                     width="180" 
                                     height="180" 
                                     style="object-fit: cover;"
                                     alt="Profile Picture">
                                <div class="position-absolute bottom-0 end-0">
                                    <label for="profile_image" class="btn btn-primary btn-sm rounded-circle shadow-sm" style="width: 40px; height: 40px; padding: 0;">
                                        <i class="bi bi-camera-fill"></i>
                                    </label>
                                </div>
                            </div>
                            <form method="POST" enctype="multipart/form-data" id="profileImageForm" class="d-none">
                                <input type="file" id="profile_image" name="profile_image" accept="image/*" onchange="previewImage(this)">
                            </form>
                            <div id="imagePreviewContainer" class="d-none mt-3">
                                <div class="card">
                                    <div class="card-body p-2">
                                        <img id="imagePreview" class="img-fluid rounded mb-2" alt="Preview">
                                        <div class="d-flex gap-2">
                                            <button type="button" class="btn btn-success btn-sm flex-grow-1" onclick="submitImage()">
                                                <i class="bi bi-check-lg"></i> Save
                                            </button>
                                            <button type="button" class="btn btn-danger btn-sm flex-grow-1" onclick="cancelImageUpload()">
                                                <i class="bi bi-x-lg"></i> Cancel
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php if (!empty($errorMessage)): ?>
                                <div class="alert alert-danger mt-3"><?= htmlspecialchars($errorMessage); ?></div>
                            <?php endif; ?>
                            <?php if (!empty($successMessage)): ?>
                                <div class="alert alert-success mt-3"><?= htmlspecialchars($successMessage); ?></div>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-8">
                            <div class="card border-0 bg-light">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-center mb-4">
                                        <h6 class="card-title mb-0 text-primary">Personal Information</h6>
                                        <button type="button" class="btn btn-outline-primary btn-sm" onclick="toggleEditMode()">
                                            <i class="bi bi-pencil"></i> Edit Details
                                        </button>
                                    </div>
                                    <form id="profileDetailsForm" method="POST" action="update_profile.php">
                                        <div class="row g-3">
                                            <div class="col-md-6">
                                                <label class="form-label text-muted">First Name</label>
                                                <input type="text" name="firstName" class="form-control bg-white border-2 px-4 py-3 rounded-lg" 
                                                       value="<?= htmlspecialchars($userFirstName); ?>" readonly>
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label text-muted">Last Name</label>
                                                <input type="text" name="lastName" class="form-control bg-white border-2 px-4 py-3 rounded-lg" 
                                                       value="<?= htmlspecialchars($userLastName); ?>" readonly>
                                            </div>
                                            <div class="col-12">
                                                <label class="form-label text-muted">Email Address</label>
                                                <input type="email" name="email" class="form-control bg-white border-2 px-4 py-3 rounded-lg" 
                                                       value="<?= htmlspecialchars($userEmail); ?>" readonly>
                                            </div>
                                            <div class="col-12">
                                                <label class="form-label text-muted">Account Type</label>
                                                <div class="form-control bg-white border-2 px-4 py-3 rounded-lg">
                                                    <span class="badge bg-primary"><?= htmlspecialchars($userType); ?></span>
                                                </div>
                                            </div>
                                            <div class="col-12 d-none" id="editButtons">
                                                <div class="d-flex gap-2 justify-content-end">
                                                    <button type="button" class="btn btn-secondary" onclick="cancelEdit()">
                                                        <i class="bi bi-x-lg"></i> Cancel
                                                    </button>
                                                    <button type="submit" class="btn btn-primary">
                                                        <i class="bi bi-check-lg"></i> Save Changes
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <script>
            function previewImage(input) {
                if (input.files && input.files[0]) {
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        document.getElementById('imagePreview').src = e.target.result;
                        document.getElementById('imagePreviewContainer').classList.remove('d-none');
                    }
                    reader.readAsDataURL(input.files[0]);
                }
            }

            function submitImage() {
                document.getElementById('profileImageForm').submit();
            }

            function cancelImageUpload() {
                document.getElementById('profile_image').value = '';
                document.getElementById('imagePreviewContainer').classList.add('d-none');
            }

            function toggleEditMode() {
                const inputs = document.querySelectorAll('#profileDetailsForm input[type="text"], #profileDetailsForm input[type="email"]');
                const editButtons = document.getElementById('editButtons');
                
                inputs.forEach(input => {
                    input.readOnly = !input.readOnly;
                    if (!input.readOnly) {
                        input.classList.add('border-primary');
                    } else {
                        input.classList.remove('border-primary');
                    }
                });
                
                editButtons.classList.toggle('d-none');
            }

            function cancelEdit() {
                const inputs = document.querySelectorAll('#profileDetailsForm input[type="text"], #profileDetailsForm input[type="email"]');
                const editButtons = document.getElementById('editButtons');
                
                inputs.forEach(input => {
                    input.readOnly = true;
                    input.classList.remove('border-primary');
                });
                
                editButtons.classList.add('d-none');
            }

            // Handle profile details form submission
            document.getElementById('profileDetailsForm').addEventListener('submit', function(e) {
                e.preventDefault();
                
                const formData = new FormData(this);
                
                fetch('update_profile.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        Swal.fire({
                            icon: 'success',
                            title: 'Profile Updated!',
                            text: 'Your profile has been updated successfully.',
                            confirmButtonColor: '#3085d6'
                        }).then(() => {
                            location.reload();
                        });
                    } else {
                        throw new Error(data.message || 'Failed to update profile');
                    }
                })
                .catch(error => {
                    Swal.fire({
                        icon: 'error',
                        title: 'Update Failed',
                        text: error.message || 'Please try again later.',
                        confirmButtonColor: '#d33'
                    });
                });
            });
            </script>
        <?php elseif ($page === 'payment'): ?>
            <!-- Payment Page -->
            <div class="card shadow-sm">
                <div class="card-header bg-success text-white">
                    <h5 class="mb-0">💰 Pay Membership Fee</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="payment-instructions">
                                <div class="instructions-header">
                                    <i class="bi bi-info-circle-fill"></i>
                                    <h6>Payment Instructions</h6>
                                </div>
                                <div class="instructions-content">
                                    <div class="amount-info">
                                        <span class="label">Amount:</span>
                                        <span class="value">₱1,000.00</span>
                                    </div>
                                    <div class="payment-methods">
                                        <span class="label">Payment Methods:</span>
                                        <?php
                                        require_once 'get_payment_instructions.php';
                                        $instructions = getPaymentInstructions();
                                        ?>
                                        <div class="method-details">
                                            <div class="method-section">
                                                <h6 class="method-title">GCash</h6>
                                                <div class="method-info">
                                                    <div class="info-item">
                                                        <span class="info-label">GCash Number:</span>
                                                        <span class="info-value"><?= htmlspecialchars($instructions['gcash']['number']); ?></span>
                                                    </div>
                                                    <div class="info-item">
                                                        <span class="info-label">GCash Account Name:</span>
                                                        <span class="info-value"><?= htmlspecialchars($instructions['gcash']['name']); ?></span>
                                                    </div>
                                                </div>
                                            </div>

                                            <?php if (!empty($instructions['bank']['name'])): ?>
                                            <div class="method-section">
                                                <h6 class="method-title">Bank Transfer</h6>
                                                <div class="method-info">
                                                    <div class="info-item">
                                                        <span class="info-label">Bank Name:</span>
                                                        <span class="info-value"><?= htmlspecialchars($instructions['bank']['name']); ?></span>
                                                    </div>
                                                    <div class="info-item">
                                                        <span class="info-label">Bank Account Number:</span>
                                                        <span class="info-value"><?= htmlspecialchars($instructions['bank']['account']); ?></span>
                                                    </div>
                                                    <div class="info-item">
                                                        <span class="info-label">Bank Account Name:</span>
                                                        <span class="info-value"><?= htmlspecialchars($instructions['bank']['account_name']); ?></span>
                                                    </div>
                                                </div>
                                            </div>
                                            <?php endif; ?>

                                            <?php if (!empty($instructions['office'])): ?>
                                            <div class="method-section">
                                                <h6 class="method-title">Cash Payment</h6>
                                                <div class="method-info">
                                                    <div class="info-item">
                                                        <span class="info-label">Office Address:</span>
                                                        <span class="info-value"><?= htmlspecialchars($instructions['office']); ?></span>
                                                    </div>
                                                </div>
                                            </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <style>
                            .payment-instructions {
                                background: rgba(255, 255, 255, 0.95);
                                border-radius: 12px;
                                box-shadow: 0 8px 20px rgba(0, 0, 0, 0.1);
                                overflow: hidden;
                                transition: all 0.3s ease;
                                height: 100%;
                                display: flex;
                                flex-direction: column;
                            }

                            .payment-instructions:hover {
                                box-shadow: 0 12px 30px rgba(0, 0, 0, 0.15);
                            }

                            .instructions-header {
                                background: linear-gradient(135deg, #3498db, #2980b9);
                                color: white;
                                padding: 0.8rem 1rem;
                                display: flex;
                                align-items: center;
                                gap: 8px;
                            }

                            .instructions-header i {
                                font-size: 1.2rem;
                            }

                            .instructions-header h6 {
                                margin: 0;
                                font-size: 1rem;
                                font-weight: 600;
                            }

                            .instructions-content {
                                padding: 1rem;
                                flex: 1;
                                display: flex;
                                flex-direction: column;
                            }

                            .amount-info {
                                background: #f8f9fa;
                                padding: 0.8rem;
                                border-radius: 6px;
                                margin-bottom: 0.8rem;
                                display: flex;
                                justify-content: space-between;
                                align-items: center;
                            }

                            .amount-info .label {
                                color: #2c3e50;
                                font-weight: 600;
                                font-size: 0.9rem;
                            }

                            .amount-info .value {
                                color: #3498db;
                                font-size: 1.1rem;
                                font-weight: 700;
                            }

                            .payment-methods {
                                display: flex;
                                flex-direction: column;
                                gap: 0.8rem;
                                flex: 1;
                            }

                            .payment-methods .label {
                                color: #2c3e50;
                                font-weight: 600;
                                font-size: 0.9rem;
                                margin-bottom: 0.3rem;
                                display: block;
                            }

                            .method-details {
                                display: grid;
                                grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
                                gap: 0.8rem;
                                flex: 1;
                            }

                            .method-section {
                                background: #f8f9fa;
                                padding: 0.8rem;
                                border-radius: 6px;
                                transition: all 0.3s ease;
                                display: flex;
                                flex-direction: column;
                            }

                            .method-section:hover {
                                transform: translateY(-2px);
                                box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
                            }

                            .method-title {
                                color: #2c3e50;
                                font-size: 0.9rem;
                                font-weight: 600;
                                margin-bottom: 0.6rem;
                                padding-bottom: 0.4rem;
                                border-bottom: 1px solid #e9ecef;
                            }

                            .method-info {
                                display: flex;
                                flex-direction: column;
                                gap: 0.5rem;
                                flex: 1;
                            }

                            .info-item {
                                display: flex;
                                flex-direction: column;
                                gap: 0.2rem;
                            }

                            .info-label {
                                color: #6c757d;
                                font-size: 0.8rem;
                            }

                            .info-value {
                                color: #2c3e50;
                                font-weight: 500;
                                font-size: 0.9rem;
                                word-break: break-word;
                            }

                            @media (max-width: 768px) {
                                .payment-instructions {
                                    margin-bottom: 1.5rem;
                                }
                                
                                .method-details {
                                    grid-template-columns: 1fr;
                                }
                            }
                            </style>
                        </div>
                        <div class="col-md-6">
                            <!-- Receipt Display Section -->
                            <div id="receiptSection" class="mb-4 d-none">
                                <div class="card border-success">
                                    <div class="card-header bg-success text-white d-flex justify-content-between align-items-center">
                                        <h5 class="mb-0">📄 Payment Receipt</h5>
                                        <div class="receipt-actions">
                                            <button class="btn btn-light btn-sm" onclick="viewReceipt()">
                                                <i class="bi bi-eye"></i> View Receipt
                                            </button>
                                        </div>
                                    </div>
                                    <div class="card-body">
                                        <div id="receiptDetails" class="receipt-container">
                                            <div class="receipt-header text-center mb-4">
                                                <img src="img/logo12.png" alt="TEBZ Logo" class="receipt-logo mb-3" style="height: 60px;">
                                                <h4 class="text-success mb-1" id="receiptTitle">TEBZ Payment Receipt</h4>
                                                <p class="text-muted mb-0" id="receiptSubtitle">Official Payment Confirmation</p>
                                            </div>
                                            
                                            <div class="receipt-info">
                                                <div class="row">
                                                    <div class="col-md-6">
                                                        <div class="info-group mb-3">
                                                            <label class="text-muted small">Receipt Number</label>
                                                            <p class="mb-0 fw-bold" id="receiptNumber"></p>
                                                        </div>
                                                        <div class="info-group mb-3">
                                                            <label class="text-muted small">Date</label>
                                                            <p class="mb-0" id="receiptDate"></p>
                                                        </div>
                                                        <div class="info-group mb-3">
                                                            <label class="text-muted small">Amount</label>
                                                            <p class="mb-0 fw-bold text-success" id="receiptAmount"></p>
                                                        </div>
                                                    </div>
                                                    <div class="col-md-6">
                                                        <div class="info-group mb-3">
                                                            <label class="text-muted small">Payment Method</label>
                                                            <p class="mb-0" id="receiptMethod"></p>
                                                        </div>
                                                        <div class="info-group mb-3">
                                                            <label class="text-muted small">Paid By</label>
                                                            <p class="mb-0" id="receiptName"></p>
                                                        </div>
                                                        <div class="info-group mb-3">
                                                            <label class="text-muted small">Status</label>
                                                            <p class="mb-0"><span id="receiptStatus" class="badge bg-warning text-dark">Pending</span></p>
                                                        </div>
                                                    </div>
                                                </div>
                                                
                                                <div id="paymentDetails" class="mt-4">
                                                    <!-- Additional payment details will be dynamically added here -->
                                                </div>

                                                <!-- Assignment Status Section -->
                                                <div id="assignmentStatus" class="mt-4 d-none">
                                                    <hr class="my-4">
                                                    <div class="text-center">
                                                        <h5 class="mb-3">Assignment Status</h5>
                                                        <div id="assignmentMessage" class="alert mb-3"></div>
                                                        <div id="assignmentButton" class="d-none">
                                                            <a href="?page=assignjeepney" class="btn btn-primary">
                                                                <i class="bi bi-truck-front me-2"></i>View Assigned Jeepney
                                                            </a>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            
                                            <hr class="my-4">
                                            
                                            <div class="receipt-footer text-center">
                                                <p class="text-muted mb-2">This receipt serves as proof of your payment to TEBZ.</p>
                                                <p class="text-muted mb-0">Thank you for your payment!</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <style>
                            .receipt-container {
                                max-width: 800px;
                                margin: 0 auto;
                                padding: 20px;
                                background: #fff;
                            }

                            .receipt-logo {
                                max-height: 60px;
                                margin-bottom: 1rem;
                            }

                            .receipt-header {
                                border-bottom: 2px solid #e9ecef;
                                padding-bottom: 1.5rem;
                            }

                            .receipt-info {
                                padding: 1.5rem 0;
                            }

                            .info-group {
                                margin-bottom: 1rem;
                            }

                            .info-group label {
                                display: block;
                                margin-bottom: 0.25rem;
                                color: #6c757d;
                                font-size: 0.875rem;
                            }

                            .info-group p {
                                font-size: 1rem;
                                color: #212529;
                            }

                            .receipt-footer {
                                padding-top: 1.5rem;
                                border-top: 2px solid #e9ecef;
                            }

                            @media print {
                                body * {
                                    visibility: hidden;
                                }
                                #receiptDetails, #receiptDetails * {
                                    visibility: visible;
                                }
                                #receiptDetails {
                                    position: absolute;
                                    left: 0;
                                    top: 0;
                                    width: 100%;
                                }
                                .receipt-actions {
                                    display: none;
                                }
                            }
                            </style>

                            <script>
                            function viewReceipt() {
                                // Get the payment ID from the receipt number
                                const receiptNumber = document.getElementById('receiptNumber').textContent;
                                window.open(`view_receipt.php?payment_id=${receiptNumber}`, '_blank', 'width=800,height=600');
                            }

                            function printReceipt() {
                                const printContent = document.getElementById('receiptDetails').innerHTML;
                                const originalContent = document.body.innerHTML;
                                
                                document.body.innerHTML = `
                                    <div class="container mt-4">
                                        ${printContent}
                                    </div>
                                `;
                                
                                window.print();
                                document.body.innerHTML = originalContent;
                                
                                // Reinitialize any necessary event listeners
                                document.addEventListener('DOMContentLoaded', function() {
                                    // Your initialization code here
                                });
                            }

                            function downloadReceipt() {
                                // Create a new jsPDF instance
                                const { jsPDF } = window.jspdf;
                                const doc = new jsPDF();
                                
                                // Add logo
                                const logo = new Image();
                                logo.src = 'img/logo12.png';
                                
                                // Add content to PDF
                                doc.setFontSize(20);
                                doc.text('TEBZ Payment Receipt', 105, 20, { align: 'center' });
                                
                                // Add receipt details
                                doc.setFontSize(12);
                                doc.text(`Receipt Number: ${document.getElementById('receiptNumber').textContent}`, 20, 40);
                                doc.text(`Date: ${document.getElementById('receiptDate').textContent}`, 20, 50);
                                doc.text(`Amount: ${document.getElementById('receiptAmount').textContent}`, 20, 60);
                                doc.text(`Payment Method: ${document.getElementById('receiptMethod').textContent}`, 20, 70);
                                doc.text(`Paid By: ${document.getElementById('receiptName').textContent}`, 20, 80);
                                doc.text(`Status: ${document.getElementById('receiptStatus').textContent}`, 20, 90);
                                
                                // Add footer
                                doc.setFontSize(10);
                                doc.text('This receipt serves as proof of your payment to TEBZ.', 105, 120, { align: 'center' });
                                doc.text('Thank you for your payment!', 105, 130, { align: 'center' });
                                
                                // Save the PDF
                                doc.save('TEBZ_Payment_Receipt.pdf');
                            }

                            // Update payment details based on payment method
                            function updatePaymentDetails(paymentMethod, details) {
                                const paymentDetailsDiv = document.getElementById('paymentDetails');
                                let detailsHtml = '';
                                
                                switch(paymentMethod) {
                                    case 'gcash':
                                        detailsHtml = `
                                            <div class="info-group">
                                                <label class="text-muted small">Your GCash Number</label>
                                                <p class="mb-0">${details.gcash_number}</p>
                                            </div>
                                            <div class="info-group">
                                                <label class="text-muted small">Your GCash Account Name</label>
                                                <p class="mb-0">${details.gcash_name}</p>
                                            </div>
                                            <div class="info-group">
                                                <label class="text-muted small">Payment Sent To</label>
                                                <p class="mb-0">GCash: 09913731309 (gigi)</p>
                                            </div>
                                        `;
                                        break;
                                    case 'bank':
                                        detailsHtml = `
                                            <div class="info-group">
                                                <label class="text-muted small">Your Bank Name</label>
                                                <p class="mb-0">${details.bank_name}</p>
                                            </div>
                                            <div class="info-group">
                                                <label class="text-muted small">Your Bank Account Number</label>
                                                <p class="mb-0">${details.bank_account}</p>
                                            </div>
                                            <div class="info-group">
                                                <label class="text-muted small">Your Bank Account Name</label>
                                                <p class="mb-0">${details.bank_account_name}</p>
                                            </div>
                                            <div class="info-group">
                                                <label class="text-muted small">Payment Sent To</label>
                                                <p class="mb-0">Bank: gigigig<br>Account: 121313546548<br>Name: valdez</p>
                                            </div>
                                        `;
                                        break;
                                    case 'cash':
                                        detailsHtml = `
                                            <div class="info-group">
                                                <label class="text-muted small">Receipt Number</label>
                                                <p class="mb-0">${details.reference_number}</p>
                                            </div>
                                            <div class="info-group">
                                                <label class="text-muted small">Payment Location</label>
                                                <p class="mb-0">Office</p>
                                            </div>
                                        `;
                                        break;
                                }
                                
                                paymentDetailsDiv.innerHTML = detailsHtml;
                            }
                            </script>

                            <!-- Payment Form -->
                            <form id="paymentForm" class="payment-form" novalidate>
                                <div class="form-group">
                                    <label for="amount">Amount</label>
                                    <div class="amount-input">
                                        <span class="currency-symbol">₱</span>
                                        <input type="number" 
                                               id="amount" 
                                               name="amount" 
                                               value="1000" 
                                               readonly>
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label for="payment_method">Payment Method</label>
                                    <select id="payment_method" 
                                            name="payment_method" 
                                            required 
                                            onchange="showPaymentFields()">
                                        <option value="">Choose payment method</option>
                                        <option value="gcash">GCash</option>
                                        <option value="bank">Bank Transfer</option>
                                        <option value="cash">Cash Payment</option>
                                    </select>
                                </div>

                                <!-- GCash Fields -->
                                <div id="gcashFields" class="payment-fields d-none">
                                    <div class="form-group">
                                        <label for="gcash_number">Your GCash Number</label>
                                        <input type="text" 
                                               id="gcash_number" 
                                               name="gcash_number" 
                                               pattern="[0-9]{11}" 
                                               placeholder="Enter your 11-digit GCash number">
                                    </div>
                                    <div class="form-group">
                                        <label for="gcash_name">Your GCash Account Name</label>
                                        <input type="text" 
                                               id="gcash_name" 
                                               name="gcash_name" 
                                               placeholder="Enter your GCash account name">
                                    </div>
                                    <div class="payment-instructions bg-light p-3 rounded mb-3">
                                        <h6 class="mb-2">Send payment to:</h6>
                                        <p class="mb-1"><strong>GCash Number:</strong> 09913731309</p>
                                        <p class="mb-0"><strong>Account Name:</strong> gigi</p>
                                    </div>
                                </div>

                                <!-- Bank Transfer Fields -->
                                <div id="bankFields" class="payment-fields d-none">
                                    <div class="form-group">
                                        <label for="bank_name">Your Bank Name</label>
                                        <input type="text" 
                                               id="bank_name" 
                                               name="bank_name" 
                                               placeholder="Enter your bank name">
                                    </div>
                                    <div class="form-group">
                                        <label for="bank_account">Your Bank Account Number</label>
                                        <input type="text" 
                                               id="bank_account" 
                                               name="bank_account" 
                                               placeholder="Enter your bank account number">
                                    </div>
                                    <div class="form-group">
                                        <label for="bank_account_name">Your Bank Account Name</label>
                                        <input type="text" 
                                               id="bank_account_name" 
                                               name="bank_account_name" 
                                               placeholder="Enter your bank account name">
                                    </div>
                                    <div class="payment-instructions bg-light p-3 rounded mb-3">
                                        <h6 class="mb-2">Send payment to:</h6>
                                        <p class="mb-1"><strong>Bank Name:</strong> gigigig</p>
                                        <p class="mb-1"><strong>Account Number:</strong> 121313546548</p>
                                        <p class="mb-0"><strong>Account Name:</strong> valdez</p>
                                    </div>
                                </div>

                                <!-- Cash Payment Fields -->
                                <div id="cashFields" class="payment-fields d-none">
                                    <div class="form-group">
                                        <label for="cash_reference">Receipt Number</label>
                                        <input type="text" 
                                               id="cash_reference" 
                                               name="reference_number" 
                                               placeholder="Enter receipt number">
                                    </div>
                                    <div class="payment-instructions bg-light p-3 rounded mb-3">
                                        <h6 class="mb-2">Pay at:</h6>
                                        <p class="mb-0"><strong>Office Address:</strong> Office</p>
                                    </div>
                                </div>

                                <button type="submit" class="submit-payment">
                                    <i class="bi bi-credit-card"></i>
                                    Submit Payment
                                </button>
                            </form>

                            <!-- View Receipt Button -->
                            <div class="mt-4 text-center">
                                <button type="button" class="btn btn-primary btn-lg w-100" onclick="viewReceipt()">
                                    <i class="bi bi-receipt me-2"></i>
                                    View Payment Receipt
                                </button>
                            </div>

                            <style>
                            .payment-form {
                                background: rgba(255, 255, 255, 0.95);
                                padding: 2rem;
                                border-radius: 15px;
                                box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
                                transition: all 0.3s ease;
                            }

                            .payment-form:hover {
                                box-shadow: 0 15px 40px rgba(0, 0, 0, 0.15);
                            }

                            .form-group {
                                margin-bottom: 1.5rem;
                                position: relative;
                            }

                            .form-group label {
                                display: block;
                                margin-bottom: 0.5rem;
                                color: #2c3e50;
                                font-weight: 600;
                                font-size: 0.95rem;
                                transition: color 0.3s ease;
                            }

                            .form-group:hover label {
                                color: #3498db;
                            }

                            .form-group input,
                            .form-group select {
                                width: 100%;
                                padding: 12px 15px;
                                border: 2px solid #e0e0e0;
                                border-radius: 8px;
                                font-size: 1rem;
                                transition: all 0.3s ease;
                                background: #fff;
                            }

                            .form-group input:focus,
                            .form-group select:focus {
                                border-color: #3498db;
                                box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.1);
                                outline: none;
                            }

                            .amount-input {
                                position: relative;
                            }

                            .currency-symbol {
                                position: absolute;
                                left: 15px;
                                top: 50%;
                                transform: translateY(-50%);
                                color: #666;
                                font-weight: 600;
                            }

                            .amount-input input {
                                padding-left: 35px;
                            }

                            .payment-fields {
                                display: none;
                            }

                            .payment-fields:not(.d-none) {
                                display: block;
                                animation: fadeIn 0.3s ease-out;
                            }

                            .submit-payment {
                                width: 100%;
                                padding: 15px;
                                background: linear-gradient(135deg, #3498db, #2980b9);
                                color: white;
                                border: none;
                                border-radius: 8px;
                                font-size: 1.1rem;
                                font-weight: 600;
                                cursor: pointer;
                                transition: all 0.3s ease;
                                display: flex;
                                align-items: center;
                                justify-content: center;
                                gap: 10px;
                            }

                            .submit-payment:hover {
                                background: linear-gradient(135deg, #2980b9, #3498db);
                                transform: translateY(-2px);
                                box-shadow: 0 5px 15px rgba(52, 152, 219, 0.3);
                            }

                            .submit-payment:active {
                                transform: translateY(0);
                            }

                            @keyframes fadeIn {
                                from {
                                    opacity: 0;
                                    transform: translateY(-10px);
                                }
                                to {
                                    opacity: 1;
                                    transform: translateY(0);
                                }
                            }

                            /* Custom Select Styling */
                            .form-group select {
                                appearance: none;
                                background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' fill='%23333' viewBox='0 0 16 16'%3E%3Cpath d='M7.247 11.14L2.451 5.658C1.885 5.013 2.345 4 3.204 4h9.592a1 1 0 0 1 .753 1.659l-4.796 5.48a1 1 0 0 1-1.506 0z'/%3E%3C/svg%3E");
                                background-repeat: no-repeat;
                                background-position: right 15px center;
                                padding-right: 40px;
                            }

                            /* Input Placeholder Styling */
                            .form-group input::placeholder {
                                color: #999;
                                font-size: 0.9rem;
                            }

                            /* Error State Styling */
                            .form-group input.is-invalid {
                                border-color: #e74c3c;
                                background-color: #fff5f5;
                            }

                            .form-group input.is-invalid:focus {
                                box-shadow: 0 0 0 3px rgba(231, 76, 60, 0.1);
                            }

                            #viewReceiptButton {
                                transition: all 0.3s ease;
                            }
                            #viewReceiptButton button {
                                font-size: 1.1rem;
                                font-weight: 600;
                                box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
                                transition: all 0.3s ease;
                            }
                            #viewReceiptButton button:hover {
                                transform: translateY(-2px);
                                box-shadow: 0 6px 12px rgba(0, 0, 0, 0.15);
                            }
                            </style>

                            <script>
                            function showPaymentFields() {
                                const paymentMethod = document.getElementById('payment_method').value;
                                const fields = document.querySelectorAll('.payment-fields');
                                
                                fields.forEach(field => {
                                    if (field.id === paymentMethod + 'Fields') {
                                        field.classList.remove('d-none');
                                    } else {
                                        field.classList.add('d-none');
                                    }
                                });
                            }

                            function viewReceipt() {
                                // Get the payment ID from the receipt number
                                const receiptNumber = document.getElementById('receiptNumber').textContent;
                                window.open(`view_receipt.php?payment_id=${receiptNumber}`, '_blank', 'width=800,height=600');
                            }

                            function printReceipt() {
                                const printContent = document.querySelector('.receipt-details').innerHTML;
                                const originalContent = document.body.innerHTML;
                                
                                document.body.innerHTML = `
                                    <div class="container mt-4">
                                        <div class="text-center mb-4">
                                            <img src="img/logo12.png" alt="TEBZ Logo" style="height: 60px;">
                                            <h4 class="mt-3">TEBZ Payment Receipt</h4>
                                        </div>
                                        ${printContent}
                                    </div>
                                `;
                                
                                window.print();
                                document.body.innerHTML = originalContent;
                                
                                // Reinitialize any necessary event listeners
                                document.addEventListener('DOMContentLoaded', function() {
                                    // Your initialization code here
                                });
                            }

                            function downloadReceipt() {
                                // Create a new jsPDF instance
                                const { jsPDF } = window.jspdf;
                                const doc = new jsPDF();
                                
                                // Add content to PDF
                                doc.setFontSize(20);
                                doc.text('TEBZ Payment Receipt', 105, 20, { align: 'center' });
                                
                                doc.setFontSize(12);
                                doc.text(`Receipt Number: ${document.getElementById('receiptNumber').textContent}`, 20, 40);
                                doc.text(`Date: ${document.getElementById('receiptDate').textContent}`, 20, 50);
                                doc.text(`Amount: ${document.getElementById('receiptAmount').textContent}`, 20, 60);
                                doc.text(`Payment Method: ${document.getElementById('receiptMethod').textContent}`, 20, 70);
                                doc.text(`Paid By: ${document.getElementById('receiptName').textContent}`, 20, 80);
                                doc.text(`Status: ${document.getElementById('receiptStatus').textContent}`, 20, 90);
                                
                                // Add footer
                                doc.setFontSize(10);
                                doc.text('This receipt serves as proof of your payment to TEBZ.', 105, 120, { align: 'center' });
                                doc.text('Thank you for your payment!', 105, 130, { align: 'center' });
                                
                                // Save the PDF
                                doc.save('TEBZ_Payment_Receipt.pdf');
                            }

                            // Initialize payment fields on page load
                            document.addEventListener('DOMContentLoaded', function() {
                                const paymentMethod = document.getElementById('payment_method').value;
                                if (paymentMethod) {
                                    showPaymentFields();
                                }

                                // Check if payment is confirmed
                                checkPaymentStatus();

                                // Add payment form submission handler
                                document.getElementById('paymentForm').addEventListener('submit', function(e) {
                                    e.preventDefault();
                                    
                                    // Validate form
                                    const paymentMethod = document.getElementById('payment_method').value;
                                    if (!paymentMethod) {
                                        Swal.fire({
                                            icon: 'error',
                                            title: 'Error',
                                            text: 'Please select a payment method',
                                            confirmButtonColor: '#d33'
                                        });
                                        return;
                                    }

                                    // Validate required fields based on payment method
                                    let isValid = true;
                                    let errorMessage = '';

                                    switch(paymentMethod) {
                                        case 'gcash':
                                            const gcashNumber = document.getElementById('gcash_number').value;
                                            const gcashName = document.getElementById('gcash_name').value;
                                            if (!gcashNumber || !gcashName) {
                                                isValid = false;
                                                errorMessage = 'Please fill in all GCash details';
                                            }
                                            break;
                                        case 'bank':
                                            const bankName = document.getElementById('bank_name').value;
                                            const bankAccount = document.getElementById('bank_account').value;
                                            const bankAccountName = document.getElementById('bank_account_name').value;
                                            if (!bankName || !bankAccount || !bankAccountName) {
                                                isValid = false;
                                                errorMessage = 'Please fill in all bank details';
                                            }
                                            break;
                                        case 'cash':
                                            const referenceNumber = document.getElementById('cash_reference').value;
                                            if (!referenceNumber) {
                                                isValid = false;
                                                errorMessage = 'Please enter the receipt number';
                                            }
                                            break;
                                    }

                                    if (!isValid) {
                                        Swal.fire({
                                            icon: 'error',
                                            title: 'Error',
                                            text: errorMessage,
                                            confirmButtonColor: '#d33'
                                        });
                                        return;
                                    }
                                    
                                    // Show loading state
                                    const submitButton = this.querySelector('button[type="submit"]');
                                    const originalButtonText = submitButton.innerHTML;
                                    submitButton.disabled = true;
                                    submitButton.innerHTML = '<i class="bi bi-arrow-repeat spin"></i> Processing...';
                                    
                                    // Get form data
                                    const formData = new FormData(this);
                                    
                                    // Send payment request
                                    fetch('process_payment.php', {
                                        method: 'POST',
                                        body: formData
                                    })
                                    .then(response => response.json())
                                    .then(data => {
                                        if (data.success) {
                                            // Show success message
                                            Swal.fire({
                                                icon: 'success',
                                                title: 'Payment Submitted',
                                                text: 'Your payment has been submitted and is pending confirmation from the treasurer.',
                                                confirmButtonText: 'OK'
                                            }).then((result) => {
                                                if (result.isConfirmed) {
                                                    // Update UI with receipt details
                                                    document.getElementById('receiptNumber').textContent = data.receipt.receipt_number;
                                                    document.getElementById('receiptDate').textContent = data.receipt.date;
                                                    document.getElementById('receiptAmount').textContent = '₱' + data.receipt.amount;
                                                    document.getElementById('receiptMethod').textContent = data.receipt.method;
                                                    document.getElementById('receiptName').textContent = data.receipt.name || 'N/A';
                                                    
                                                    // Update status badge
                                                    const statusBadge = document.getElementById('receiptStatus');
                                                    statusBadge.textContent = 'Pending Confirmation';
                                                    statusBadge.className = 'badge bg-warning text-dark';
                                                    
                                                    // Show receipt section and hide payment form
                                                    document.getElementById('receiptSection').classList.remove('d-none');
                                                    document.getElementById('paymentForm').closest('.col-md-6').classList.add('d-none');
                                                }
                                            });
                                        } else if (data.message === 'Attendance already recorded for this orientation.') {
                                            Swal.fire({
                                                icon: 'info',
                                                title: 'Already Recorded',
                                                text: 'You have already submitted your attendance for this orientation.',
                                            });
                                        } else {
                                            throw new Error(data.message || 'Failed to submit payment');
                                        }
                                    })
                                    .catch(error => {
                                        Swal.fire({
                                            icon: 'error',
                                            title: 'Error',
                                            text: error.message || 'Failed to submit payment. Please try again.',
                                            confirmButtonColor: '#d33'
                                        });
                                    })
                                    .finally(() => {
                                        // Reset button state
                                        submitButton.disabled = false;
                                        submitButton.innerHTML = originalButtonText;
                                    });
                                });
                            });

                            function checkPaymentStatus() {
                                fetch('check_payment_status.php')
                                    .then(response => response.json())
                                    .then(data => {
                                        if (data.success) {
                                            const paymentStatus = document.getElementById('paymentStatus');
                                            const receiptSection = document.getElementById('receiptSection');
                                            const paymentForm = document.getElementById('paymentForm');
                                            const viewReceiptButton = document.querySelector('.btn-primary');
                                            
                                            if (data.has_payment) {
                                                // Update payment status
                                                paymentStatus.textContent = data.payment_status;
                                                paymentStatus.className = `px-3 py-1 rounded-full text-sm font-medium ${
                                                    data.payment_status === 'Confirmed' ? 'bg-green-100 text-green-800' :
                                                    data.payment_status === 'Pending' ? 'bg-yellow-100 text-yellow-800' :
                                                    'bg-red-100 text-red-800'
                                                }`;

                                                // Show/hide receipt section based on payment status
                                                if (data.payment_status === 'Confirmed') {
                                                    if (receiptSection) {
                                                        receiptSection.style.display = 'block';
                                                    }
                                                    if (paymentForm) {
                                                        paymentForm.style.display = 'none';
                                                    }
                                                    if (viewReceiptButton) {
                                                        viewReceiptButton.style.display = 'block';
                                                    }
                                                } else {
                                                    if (receiptSection) {
                                                        receiptSection.style.display = 'none';
                                                    }
                                                    if (paymentForm) {
                                                        paymentForm.style.display = 'block';
                                                    }
                                                    if (viewReceiptButton) {
                                                        viewReceiptButton.style.display = 'none';
                                                    }
                                                }
                                            } else {
                                                // No payment found
                                                if (paymentStatus) {
                                                    paymentStatus.textContent = 'No Payment';
                                                    paymentStatus.className = 'px-3 py-1 rounded-full text-sm font-medium bg-gray-100 text-gray-800';
                                                }
                                                if (receiptSection) {
                                                    receiptSection.style.display = 'none';
                                                }
                                                if (paymentForm) {
                                                    paymentForm.style.display = 'block';
                                                }
                                                if (viewReceiptButton) {
                                                    viewReceiptButton.style.display = 'none';
                                                }
                                            }
                                        } else {
                                            console.error('Error checking payment status:', data.message);
                                        }
                                    })
                                    .catch(error => {
                                        console.error('Error checking payment status:', error);
                                    });
                            }

                            // Call checkPaymentStatus on page load and every 30 seconds
                            document.addEventListener('DOMContentLoaded', function() {
                                checkPaymentStatus();
                                setInterval(checkPaymentStatus, 30000);
                            });
                            </script>

                            <style>
                            .spin {
                                animation: spin 1s linear infinite;
                            }
                            @keyframes spin {
                                0% { transform: rotate(0deg); }
                                100% { transform: rotate(360deg); }
                            }
                            </style>
                        </div>
                    </div>
                </div>
            </div>
        <?php elseif ($page === 'assignjeepney'): ?>
            <!-- Assigned Jeepney Page -->
            <div class="container-fluid px-4 py-3">
                <?php if ($assignedJeepney): ?>
                    <!-- Header Section -->
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <div>
                            <h4 class="mb-1 fw-bold">My Assigned Jeepney</h4>
                            <p class="text-muted mb-0">Manage your assigned jeepney and track your route</p>
                        </div>
                        <div class="route-status">
                            <span id="routeStatus" class="badge bg-secondary px-3 py-2">
                                <i class="bi bi-circle-fill me-1"></i>Not Started
                            </span>
                        </div>
                    </div>

                    <div class="row g-4">
                        <!-- Jeepney Details Cards -->
                        <div class="col-md-4">
                            <div class="card border-0 shadow-sm h-100">
                                <div class="card-body p-4">
                                    <div class="d-flex align-items-center mb-4">
                                        <div class="bg-primary bg-opacity-10 p-3 rounded me-3">
                                            <i class="bi bi-truck-front text-primary fs-4"></i>
                                        </div>
                                        <div>
                                            <h6 class="card-subtitle mb-1 text-muted">Vehicle Details</h6>
                                            <h5 class="card-title mb-0">Jeepney Information</h5>
                                        </div>
                                    </div>
                                    <div class="vehicle-info">
                                        <div class="info-item mb-3">
                                            <label class="text-muted small">Plate Number</label>
                                            <h5 class="mb-0"><?= htmlspecialchars($assignedJeepney['plate_number']) ?></h5>
                                        </div>
                                        <div class="info-item mb-3">
                                            <label class="text-muted small">Body Number</label>
                                            <h5 class="mb-0"><?= htmlspecialchars($assignedJeepney['body_number']) ?></h5>
                                        </div>
                                        <div class="info-item">
                                            <label class="text-muted small">Assigned Date</label>
                                            <h5 class="mb-0"><?= date('F d, Y', strtotime($assignedJeepney['assigned_date'])) ?></h5>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Route Information -->
                        <div class="col-md-8">
                            <div class="card border-0 shadow-sm h-100">
                                <div class="card-body p-4">
                                    <div class="d-flex align-items-center mb-4">
                                        <div class="bg-success bg-opacity-10 p-3 rounded me-3">
                                            <i class="bi bi-signpost-split text-success fs-4"></i>
                                        </div>
                                        <div>
                                            <h6 class="card-subtitle mb-1 text-muted">Route Information</h6>
                                            <h5 class="card-title mb-0"><?= htmlspecialchars($assignedJeepney['route']) ?></h5>
                                        </div>
                                    </div>
                                    
                                    <!-- Route Map -->
                                    <div id="routeMap" class="mb-4" style="height: 300px; border-radius: 10px; overflow: hidden; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);">
                                        <!-- Map will be loaded here -->
                                    </div>

                                    <!-- Route Controls -->
                                    <div class="d-flex justify-content-center gap-3 mb-4">
                                        <button id="startRouteBtn" class="btn btn-success px-4 py-2" onclick="startRoute()">
                                            <i class="bi bi-play-fill me-2"></i>Start Route
                                        </button>
                                        <button id="pauseRouteBtn" class="btn btn-warning px-4 py-2 d-none" onclick="pauseRoute()">
                                            <i class="bi bi-pause-fill me-2"></i>Pause Route
                                        </button>
                                        <button id="endRouteBtn" class="btn btn-danger px-4 py-2 d-none" onclick="endRoute()">
                                            <i class="bi bi-stop-fill me-2"></i>End Route
                                        </button>
                                    </div>

                                    <!-- Route Statistics -->
                                    <div class="row g-3">
                                        <div class="col-md-4">
                                            <div class="card bg-light border-0">
                                                <div class="card-body text-center p-3">
                                                    <div class="text-primary mb-2">
                                                        <i class="bi bi-speedometer2 fs-4"></i>
                                                    </div>
                                                    <h6 class="text-muted mb-1">Distance Traveled</h6>
                                                    <h4 id="distanceTraveled" class="mb-0">0 km</h4>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="card bg-light border-0">
                                                <div class="card-body text-center p-3">
                                                    <div class="text-success mb-2">
                                                        <i class="bi bi-clock-history fs-4"></i>
                                                    </div>
                                                    <h6 class="text-muted mb-1">Time Elapsed</h6>
                                                    <h4 id="timeElapsed" class="mb-0">00:00:00</h4>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="card bg-light border-0">
                                                <div class="card-body text-center p-3">
                                                    <div class="text-info mb-2">
                                                        <i class="bi bi-graph-up-arrow fs-4"></i>
                                                    </div>
                                                    <h6 class="text-muted mb-1">Current Speed</h6>
                                                    <h4 id="currentSpeed" class="mb-0">0 km/h</h4>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <?php if (!empty($assignedJeepney['notes'])): ?>
                            <!-- Additional Notes -->
                            <div class="col-12">
                                <div class="card border-0 shadow-sm">
                                    <div class="card-body p-4">
                                        <div class="d-flex align-items-start">
                                            <div class="bg-info bg-opacity-10 p-3 rounded me-3">
                                                <i class="bi bi-sticky text-info fs-4"></i>
                                            </div>
                                            <div>
                                                <h6 class="card-subtitle mb-2 text-muted">Additional Notes</h6>
                                                <p class="card-text mb-0"><?= htmlspecialchars($assignedJeepney['notes']) ?></p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <!-- No Jeepney Assigned -->
                    <div class="text-center py-5">
                        <div class="mb-4">
                            <i class="bi bi-truck-front text-muted" style="font-size: 4rem;"></i>
                        </div>
                        <h4 class="text-muted mb-3">No Jeepney Assigned Yet</h4>
                        <p class="text-muted mb-0">Please wait for the operator to assign you a jeepney.</p>
                    </div>
                <?php endif; ?>
            </div>

            <style>
            .vehicle-info .info-item {
                padding: 0.5rem 0;
                border-bottom: 1px solid rgba(0,0,0,0.05);
            }
            .vehicle-info .info-item:last-child {
                border-bottom: none;
            }
            .route-status .badge {
                font-size: 0.9rem;
                font-weight: 500;
            }
            .route-status .badge i {
                font-size: 0.6rem;
                vertical-align: middle;
            }
            .btn {
                font-weight: 500;
                transition: all 0.3s ease;
            }
            .btn:hover {
                transform: translateY(-2px);
                box-shadow: 0 4px 8px rgba(0,0,0,0.1);
            }
            .card {
                transition: all 0.3s ease;
            }
            .card:hover {
                transform: translateY(-5px);
                box-shadow: 0 8px 15px rgba(0,0,0,0.1) !important;
            }
            #routeMap {
                transition: all 0.3s ease;
            }
            #routeMap:hover {
                box-shadow: 0 6px 12px rgba(0,0,0,0.15) !important;
            }
            </style>

            <script>
            // Initialize map
            let map;
            let routePolyline;
            let currentPosition;
            let watchId;
            let routeStartTime;
            let routeTimer;
            let totalDistance = 0;
            let lastPosition = null;
            let isRouteActive = false;

            function initMap() {
                // Initialize the map with OpenStreetMap
                map = L.map('routeMap').setView([14.5995, 120.9842], 15);
                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    attribution: '© OpenStreetMap contributors'
                }).addTo(map);

                // Initialize route polyline
                routePolyline = L.polyline([], {
                    color: 'red',
                    weight: 3,
                    opacity: 0.8
                }).addTo(map);
            }

            function startRoute() {
                if (!navigator.geolocation) {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: 'Geolocation is not supported by your browser'
                    });
                    return;
                }

                isRouteActive = true;

                // Start tracking position
                watchId = navigator.geolocation.watchPosition(
                    (position) => {
                        if (!isRouteActive) return;

                        currentPosition = {
                            lat: position.coords.latitude,
                            lng: position.coords.longitude
                        };

                        // Update map center
                        map.setView([currentPosition.lat, currentPosition.lng]);

                        // Add point to route
                        const latlng = L.latLng(currentPosition.lat, currentPosition.lng);
                        routePolyline.addLatLng(latlng);

                        // Calculate distance
                        if (lastPosition) {
                            const distance = calculateDistance(
                                lastPosition.lat,
                                lastPosition.lng,
                                currentPosition.lat,
                                currentPosition.lng
                            );
                            totalDistance += distance;
                            document.getElementById('distanceTraveled').textContent = 
                                (totalDistance / 1000).toFixed(2) + ' km';
                        }
                        lastPosition = currentPosition;

                        // Update speed
                        document.getElementById('currentSpeed').textContent = 
                            (position.coords.speed * 3.6).toFixed(1) + ' km/h';
                    },
                    (error) => {
                        console.error('Error getting location:', error);
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: 'Error getting your location'
                        });
                    },
                    {
                        enableHighAccuracy: true,
                        maximumAge: 0,
                        timeout: 5000
                    }
                );

                // Start timer
                routeStartTime = new Date();
                routeTimer = setInterval(updateTimer, 1000);

                // Update UI
                document.getElementById('routeStatus').className = 'badge bg-success';
                document.getElementById('routeStatus').textContent = 'In Progress';
                document.getElementById('startRouteBtn').classList.add('d-none');
                document.getElementById('pauseRouteBtn').classList.remove('d-none');
                document.getElementById('endRouteBtn').classList.remove('d-none');

                // Show confirmation
                Swal.fire({
                    icon: 'success',
                    title: 'Route Started',
                    text: 'Your route tracking has begun',
                    timer: 2000,
                    showConfirmButton: false
                });
            }

            function pauseRoute() {
                isRouteActive = false;
                if (watchId) {
                    navigator.geolocation.clearWatch(watchId);
                    watchId = null;
                }
                if (routeTimer) {
                    clearInterval(routeTimer);
                    routeTimer = null;
                }

                // Update UI
                document.getElementById('routeStatus').className = 'badge bg-warning';
                document.getElementById('routeStatus').textContent = 'Paused';
                document.getElementById('pauseRouteBtn').classList.add('d-none');
                document.getElementById('startRouteBtn').classList.remove('d-none');
                document.getElementById('startRouteBtn').innerHTML = '<i class="bi bi-play-fill me-2"></i>Resume Route';
            }

            function endRoute() {
                Swal.fire({
                    title: 'End Route?',
                    text: 'Are you sure you want to end the current route?',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#d33',
                    cancelButtonColor: '#3085d6',
                    confirmButtonText: 'Yes, end route',
                    cancelButtonText: 'No, continue'
                }).then((result) => {
                    if (result.isConfirmed) {
                        isRouteActive = false;
                        if (watchId) {
                            navigator.geolocation.clearWatch(watchId);
                            watchId = null;
                        }
                        if (routeTimer) {
                            clearInterval(routeTimer);
                            routeTimer = null;
                        }

                        // Save route data
                        saveRouteData();

                        // Reset UI
                        document.getElementById('routeStatus').className = 'badge bg-secondary';
                        document.getElementById('routeStatus').textContent = 'Not Started';
                        document.getElementById('startRouteBtn').classList.remove('d-none');
                        document.getElementById('startRouteBtn').innerHTML = '<i class="bi bi-play-fill me-2"></i>Start Route';
                        document.getElementById('pauseRouteBtn').classList.add('d-none');
                        document.getElementById('endRouteBtn').classList.add('d-none');
                        document.getElementById('distanceTraveled').textContent = '0 km';
                        document.getElementById('timeElapsed').textContent = '00:00:00';
                        document.getElementById('currentSpeed').textContent = '0 km/h';

                        // Clear route
                        routePolyline.setLatLngs([]);
                        totalDistance = 0;
                        lastPosition = null;

                        Swal.fire({
                            icon: 'success',
                            title: 'Route Ended',
                            text: 'Your route has been saved',
                            timer: 2000,
                            showConfirmButton: false
                        });
                    }
                });
            }

            function calculateDistance(lat1, lon1, lat2, lon2) {
                const R = 6371e3; // Earth's radius in meters
                const φ1 = lat1 * Math.PI/180;
                const φ2 = lat2 * Math.PI/180;
                const Δφ = (lat2-lat1) * Math.PI/180;
                const Δλ = (lon2-lon1) * Math.PI/180;

                const a = Math.sin(Δφ/2) * Math.sin(Δφ/2) +
                        Math.cos(φ1) * Math.cos(φ2) *
                        Math.sin(Δλ/2) * Math.sin(Δλ/2);
                const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1-a));

                return R * c; // Distance in meters
            }

            function updateTimer() {
                const now = new Date();
                const diff = now - routeStartTime;
                const hours = Math.floor(diff / 3600000);
                const minutes = Math.floor((diff % 3600000) / 60000);
                const seconds = Math.floor((diff % 60000) / 1000);
                document.getElementById('timeElapsed').textContent = 
                    `${hours.toString().padStart(2, '0')}:${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;
            }

            function saveRouteData() {
                // Get route data
                const routeData = {
                    driver_id: <?= $userId ?>,
                    jeepney_id: <?= $assignedJeepney['id'] ?? 'null' ?>,
                    start_time: routeStartTime,
                    end_time: new Date(),
                    distance: totalDistance,
                    route_path: routePolyline.getLatLngs().map(point => ({
                        lat: point.lat,
                        lng: point.lng
                    }))
                };

                // Send to server
                fetch('save_route.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify(routeData)
                })
                .then(response => response.json())
                .then(data => {
                    if (!data.success) {
                        console.error('Error saving route:', data.message);
                    }
                })
                .catch(error => {
                    console.error('Error saving route:', error);
                });
            }

            // Initialize map when page loads
            document.addEventListener('DOMContentLoaded', function() {
                initMap();
            });
            </script>

            <!-- Add Google Maps API -->
            <script async defer
                src="https://maps.googleapis.com/maps/api/js?key=YOUR_API_KEY&libraries=geometry">
            </script>
        <?php endif; ?>

        <?php if ($page === 'pay_boundary' && $assignedJeepney): ?>
            <!-- Pay Boundary Section -->
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-warning text-dark">
                    <h5 class="mb-0"><i class="bi bi-cash-coin me-2"></i>Pay Boundary to Operator</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="alert alert-info">
                                <h6><i class="bi bi-info-circle me-2"></i>Boundary Payment Information</h6>
                                <p class="mb-1"><strong>Operator:</strong> <?= htmlspecialchars($assignedJeepney['operator_name'] ?? 'N/A') ?></p>
                                <p class="mb-1"><strong>Jeepney:</strong> <?= htmlspecialchars($assignedJeepney['plate_number'] ?? 'N/A') ?></p>
                                <p class="mb-0"><strong>Route:</strong> <?= htmlspecialchars($assignedJeepney['route'] ?? 'N/A') ?></p>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <form id="boundaryForm">
                                <div class="mb-3">
                                    <label class="form-label">Amount (₱)</label>
                                    <input type="number" class="form-control" name="amount" id="boundaryAmount" value="500" min="0" step="0.01" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Payment Method</label>
                                    <select class="form-select" name="payment_method" id="boundaryMethod" required>
                                        <option value="">Select method</option>
                                        <option value="Cash">Cash</option>
                                        <option value="GCash">GCash</option>
                                        <option value="Bank">Bank Transfer</option>
                                        <option value="PayMaya">PayMaya</option>
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Notes (Optional)</label>
                                    <textarea class="form-control" name="notes" id="boundaryNotes" rows="3" placeholder="Add any additional notes about this payment..."></textarea>
                                </div>
                                <button type="submit" class="btn btn-warning w-100">
                                    <i class="bi bi-cash-coin me-2"></i>Pay Boundary
                                </button>
                            </form>
                        </div>
                    </div>
                    <div id="boundaryReceipt" class="mt-4"></div>
                </div>
            </div>
            <script>
            document.getElementById('boundaryForm').addEventListener('submit', function(e) {
                e.preventDefault();
                
                const amount = document.getElementById('boundaryAmount').value;
                const payment_method = document.getElementById('boundaryMethod').value;
                const notes = document.getElementById('boundaryNotes').value;
                const driver_id = <?= json_encode($userId) ?>;
                const operator_id = <?= json_encode($assignedJeepney['operator_id'] ?? '') ?>;
                const jeepney_id = <?= json_encode($assignedJeepney['id'] ?? '') ?>;
                
                // Show loading state
                const submitBtn = this.querySelector('button[type="submit"]');
                const originalText = submitBtn.innerHTML;
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Processing...';
                
                fetch('pay_boundary.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ 
                        driver_id, 
                        operator_id, 
                        jeepney_id, 
                        amount, 
                        payment_method,
                        notes 
                    })
                })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        const receiptHtml = `
                            <div class='alert alert-success'>
                                <h6><i class="bi bi-check-circle me-2"></i>Boundary Payment Successful!</h6>
                                <div class="row">
                                    <div class="col-md-6">
                                        <p class="mb-1"><strong>Amount:</strong> ₱${parseFloat(data.receipt.amount).toLocaleString()}</p>
                                        <p class="mb-1"><strong>Method:</strong> ${data.receipt.payment_method}</p>
                                        <p class="mb-1"><strong>Reference:</strong> ${data.receipt.reference_number}</p>
                                    </div>
                                    <div class="col-md-6">
                                        <p class="mb-1"><strong>Date:</strong> ${data.receipt.date}</p>
                                        <p class="mb-1"><strong>Status:</strong> <span class="badge bg-warning">Pending</span></p>
                                    </div>
                                </div>
                                <hr>
                                <small class="text-muted">Your payment has been submitted and is pending confirmation from the operator.</small>
                            </div>
                        `;
                        document.getElementById('boundaryReceipt').innerHTML = receiptHtml;
                        
                        // Reset form
                        this.reset();
                        document.getElementById('boundaryAmount').value = '500';
                    } else {
                        document.getElementById('boundaryReceipt').innerHTML = `
                            <div class='alert alert-danger'>
                                <i class="bi bi-exclamation-triangle me-2"></i>
                                <strong>Payment Failed:</strong> ${data.message || 'An error occurred while processing your payment.'}
                            </div>
                        `;
                    }
                })
                .catch(() => {
                    document.getElementById('boundaryReceipt').innerHTML = `
                        <div class='alert alert-danger'>
                            <i class="bi bi-exclamation-triangle me-2"></i>
                            <strong>Payment Failed:</strong> Network error. Please try again.
                        </div>
                    `;
                })
                .finally(() => {
                    // Reset button state
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = originalText;
                });
            });
            </script>
        <?php endif; ?>
    </div>

    <!-- Scripts -->
    <!-- SweetAlert -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <!-- jsPDF -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function showOrientation(mode) {
            document.getElementById('onlineDetails').classList.add('d-none');
            document.getElementById('inpersonDetails').classList.add('d-none');
            if (mode === 'online') {
                document.getElementById('onlineDetails').classList.remove('d-none');
            } else if (mode === 'inperson') {
                document.getElementById('inpersonDetails').classList.remove('d-none');
            }
        }

        async function requestOrientation() {
            try {
                const result = await Swal.fire({
                    title: 'Confirm Request?',
                    text: 'Are you sure you want to notify the manager?',
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonText: 'Yes, Request!',
                    cancelButtonText: 'No, Cancel',
                    confirmButtonColor: '#198754',
                });

                if (result.isConfirmed) {
                    // Show loading state
                    Swal.fire({
                        title: 'Sending Request...',
                        didOpen: () => {
                            Swal.showLoading();
                        },
                        allowOutsideClick: false,
                        allowEscapeKey: false,
                        allowEnterKey: false
                    });

                    const response = await fetch('request_orientation.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json'
                        }
                    });
                    
                    const data = await response.json();
                    
                    if (data.success) {
                        await Swal.fire({
                            icon: 'success',
                            title: 'Request Sent!',
                            text: data.message || 'We will notify you when the schedule is released.',
                        });
                        location.reload();
                    } else {
                        throw new Error(data.message || 'Failed to send request');
                    }
                }
            } catch (error) {
                console.error('Error:', error);
                Swal.fire({
                    icon: 'error',
                    title: 'Request Failed',
                    text: error.message || 'Please try again later.',
                });
            }
        }

        async function submitAttendance(orientationId, attendedMode) {
            const result = await Swal.fire({
                title: 'Confirm Attendance?',
                text: `Are you sure you want to confirm your attendance ${attendedMode === 'online' ? 'online' : 'in-person'}?`,
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'Yes, Confirm!',
                cancelButtonText: 'No, Cancel',
                confirmButtonColor: '#3085d6',
                cancelButtonColor: '#d33'
            });

            if (result.isConfirmed) {
                $.ajax({
                    url: 'submit_attendance.php',
                    type: 'POST',
                    dataType: 'json',
                    data: {
                        orientation_id: orientationId,
                        user_id: <?= $userId ?>,
                        attended_mode: attendedMode
                    },
                    success: function (response) {
                        if (response.success) {
                            stopCountdown(); // Stop the countdown when attendance is submitted
                            Swal.fire({
                                icon: 'success',
                                title: 'Success!',
                                text: response.message || 'Your attendance has been recorded successfully.',
                            }).then(() => {
                                location.reload();
                            });
                        } else {
                            Swal.fire({
                                icon: 'error',
                                title: 'Submission Failed',
                                text: response.message || 'Please try again later.',
                            });
                        }
                    },
                    error: function () {
                        Swal.fire({
                            icon: 'error',
                            title: 'Oops...',
                            text: 'Something went wrong!',
                        });
                    }
                });
            }
        }

    </script>
    <?php if (isset($_SESSION['payment_confirmed']) && isset($_SESSION['confirmed_user'])): ?>
    <script>
    Swal.fire({
        title: '🎉 Congratulations!',
        text: 'Your payment has been confirmed! You are now officially registered as a <?= htmlspecialchars($_SESSION['confirmed_user']['userType']) ?>!',
        icon: 'success',
        confirmButtonText: 'Awesome!',
        confirmButtonColor: '#28a745',
        backdrop: `
            rgba(0,0,0,0.7)
            url("/tebz/img/confetti.gif")
            center top
            no-repeat
        `
    });
    </script>
    <?php 
    unset($_SESSION['payment_confirmed']);
    unset($_SESSION['confirmed_user']);
    ?>
    <?php endif; ?>
</body>
</html>
