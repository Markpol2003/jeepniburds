<?php
session_start();
require_once __DIR__ . '/../db_config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'driver') {
    header("Location: ../shared/index.php");
    exit();
}

$userId = $_SESSION['user_id'];
$userType = ucfirst($_SESSION['user_type']);
$userFirstName = $_SESSION['user_firstName'] ?? '';
$userLastName = $_SESSION['user_lastName'] ?? '';
$userEmail = $_SESSION['user_email'] ?? '';
$profileImage = '../uploads/profile_' . $userId . '.jpg';

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
    $uploadDir = '../uploads/';
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
    $profileImage = '../uploads/default_profile.png';
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
    <!-- Favicon -->
    <link rel="icon" type="image/png" href="../img/logo12.png">
    <link rel="apple-touch-icon" href="../img/logo12.png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css"/>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link href="../assets/css/driveroperator.css" rel="stylesheet">
    <!-- Add OpenStreetMap and Leaflet -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <!-- Google Fonts - Inter -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
</head>
<body>

<!-- Mobile Top Navbar (visible only on mobile) -->
<nav class="navbar navbar-inverse visible-xs" style="display: none;">
  <div class="container-fluid" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); margin: 0; padding: 0;">
    <div class="navbar-header" style="padding: 10px 15px; display: flex; justify-content: space-between; align-items: center; width: 100%;">
      <a class="navbar-brand" href="?page=dashboard" style="color: white; font-weight: bold; margin: 0; font-size: 1.3rem; text-decoration: none;">
        <i class="bi bi-geo-alt-fill"></i> JeepniGo
      </a>
      <button type="button" class="navbar-toggle" data-toggle="collapse" data-target="#myNavbar" style="background: none; border: 2px solid white; border-radius: 8px; padding: 5px 10px; cursor: pointer;">
        <span class="icon-bar" style="display: block; width: 22px; height: 2px; background-color: white; margin: 4px 0;"></span>
        <span class="icon-bar" style="display: block; width: 22px; height: 2px; background-color: white; margin: 4px 0;"></span>
        <span class="icon-bar" style="display: block; width: 22px; height: 2px; background-color: white; margin: 4px 0;"></span>                        
      </button>
    </div>
    <div class="collapse navbar-collapse" id="myNavbar" style="background: rgba(0,0,0,0.15); max-height: 0; overflow: hidden; display: none;">
      <ul class="nav navbar-nav" style="margin: 0; padding: 10px 0;">
        <li class="<?= $page === 'dashboard' ? 'active' : '' ?>">
          <a href="?page=dashboard" style="color: white; padding: 14px 20px; display: flex; align-items: center; text-decoration: none; transition: all 0.3s ease;">
            <i class="bi bi-house-door-fill" style="font-size: 1.2rem; margin-right: 12px; width: 24px;"></i>
            <span style="font-size: 1rem; font-weight: 500;">Dashboard</span>
          </a>
        </li>
        <li class="<?= $page === 'collect_fares' ? 'active' : '' ?>">
          <a href="?page=collect_fares" style="color: white; padding: 14px 20px; display: flex; align-items: center; text-decoration: none; transition: all 0.3s ease;">
            <i class="bi bi-cash-coin" style="font-size: 1.2rem; margin-right: 12px; width: 24px;"></i>
            <span style="font-size: 1rem; font-weight: 500;">All Fare Payments</span>
          </a>
        </li>
        <li class="<?= $page === 'profile' ? 'active' : '' ?>">
          <a href="?page=profile" style="color: white; padding: 14px 20px; display: flex; align-items: center; text-decoration: none; transition: all 0.3s ease;">
            <i class="bi bi-person-circle" style="font-size: 1.2rem; margin-right: 12px; width: 24px;"></i>
            <span style="font-size: 1rem; font-weight: 500;">Profile</span>
          </a>
        </li>
        <li class="<?= $page === 'payment' ? 'active' : '' ?>">
          <a href="?page=payment" style="color: white; padding: 14px 20px; display: flex; align-items: center; text-decoration: none; transition: all 0.3s ease;">
            <i class="bi bi-credit-card-fill" style="font-size: 1.2rem; margin-right: 12px; width: 24px;"></i>
            <span style="font-size: 1rem; font-weight: 500;">Pay Membership</span>
          </a>
        </li>
        <?php if ($hasPaid ?? false): ?>
        <li class="<?= $page === 'assignjeepney' ? 'active' : '' ?>">
          <a href="?page=assignjeepney" style="color: white; padding: 14px 20px; display: flex; align-items: center; text-decoration: none; transition: all 0.3s ease;">
            <i class="bi bi-truck-front-fill" style="font-size: 1.2rem; margin-right: 12px; width: 24px;"></i>
            <span style="font-size: 1rem; font-weight: 500;">Assigned Jeepney</span>
          </a>
        </li>
        <?php endif; ?>
        <li class="<?= $page === 'pay_boundary' ? 'active' : '' ?>">
          <a href="?page=pay_boundary" style="color: white; padding: 14px 20px; display: flex; align-items: center; text-decoration: none; transition: all 0.3s ease;">
            <i class="bi bi-wallet2" style="font-size: 1.2rem; margin-right: 12px; width: 24px;"></i>
            <span style="font-size: 1rem; font-weight: 500;">Pay Boundary</span>
          </a>
        </li>
        <li class="<?= $page === 'boarding_dashboard' ? 'active' : '' ?>">
          <a href="?page=boarding_dashboard" style="color: white; padding: 14px 20px; display: flex; align-items: center; text-decoration: none; transition: all 0.3s ease;">
            <i class="bi bi-people-fill" style="font-size: 1.2rem; margin-right: 12px; width: 24px;"></i>
            <span style="font-size: 1rem; font-weight: 500;">Boarding Dashboard</span>
          </a>
        </li>
        <li style="margin-top: 8px; border-top: 1px solid rgba(255,255,255,0.2); padding-top: 8px;">
          <a href="../logout.php" style="color: #ff6b6b; padding: 14px 20px; display: flex; align-items: center; text-decoration: none; transition: all 0.3s ease;">
            <i class="bi bi-box-arrow-right" style="font-size: 1.2rem; margin-right: 12px; width: 24px;"></i>
            <span style="font-size: 1rem; font-weight: 600;">Logout</span>
          </a>
        </li>
      </ul>
    </div>
  </div>
</nav>

<!-- Sidebar Toggle Button -->
<button class="sidebar-toggle" id="sidebarToggle" aria-label="Toggle sidebar">
    <i class="bi bi-list"></i>
</button>

<!-- Main Layout -->
<div class="d-flex">
    <!-- Sidebar -->
    <div class="sidebar bg-gradient-dark text-white p-0" id="sidebar">
        <!-- Sidebar Header -->
        <div class="sidebar-header p-4 text-center" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
            <div class="profile-section mb-4">
                <div class="position-relative d-inline-block">
                    <img src="<?= htmlspecialchars($profileImage); ?>" 
                         alt="Profile Picture" 
                         class="rounded-circle border border-3 border-white shadow-lg"
                         style="width: 100px; height: 100px; object-fit: cover;">
                    <div class="position-absolute bottom-0 end-0">
                        <div class="bg-success rounded-circle border border-2 border-white" style="width: 25px; height: 25px;">
                            <i class="bi bi-check-circle-fill text-white" style="font-size: 12px; position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%);"></i>
                        </div>
                    </div>
                </div>
                                    <h5 class="mt-3 mb-1 fw-bold text-white"><?= htmlspecialchars($userFirstName . ' ' . $userLastName); ?></h5>
                    <span class="badge bg-white bg-opacity-20 text-dark px-3 py-2 rounded-pill">
                        <i class="bi bi-shield-check me-1"></i><?= htmlspecialchars($userType); ?>
                    </span>
            </div>
        </div>

        <!-- Navigation Menu -->
        <div class="sidebar-nav p-3">
            <ul class="nav flex-column gap-2">
                <!-- Dashboard Link -->
                <li class="nav-item">
                    <a class="nav-link sidebar-link <?= $page === 'dashboard' ? 'active' : '' ?>" href="?page=dashboard">
                        <div class="d-flex align-items-center">
                            <div class="sidebar-icon">
                                <i class="bi bi-house-door-fill"></i>
                            </div>
                            <span class="sidebar-text">Dashboard</span>
                            <?php if ($page === 'dashboard'): ?>
                                <div class="sidebar-indicator"></div>
                            <?php endif; ?>
                        </div>
                    </a>
                </li>

                <!-- Collect Fares Link -->
                <li class="nav-item">
                    <a class="nav-link sidebar-link <?= $page === 'collect_fares' ? 'active' : '' ?>" href="?page=collect_fares">
                        <div class="d-flex align-items-center">
                            <div class="sidebar-icon">
                                <i class="bi bi-cash-coin"></i>
                            </div>
                            <span class="sidebar-text">All Fare Payments</span>
                            <span class="badge bg-danger ms-auto" id="faresBadge" style="display: none;">0</span>
                            <?php if (
                                $page === 'collect_fares'): ?>
                                <div class="sidebar-indicator"></div>
                            <?php endif; ?>
                        </div>
                    </a>
                </li>

                

                <!-- Profile Link -->
                <li class="nav-item">
                    <a class="nav-link sidebar-link <?= $page === 'profile' ? 'active' : '' ?>" href="?page=profile">
                        <div class="d-flex align-items-center">
                            <div class="sidebar-icon">
                                <i class="bi bi-person-circle"></i>
                            </div>
                            <span class="sidebar-text">Profile</span>
                            <?php if ($page === 'profile'): ?>
                                <div class="sidebar-indicator"></div>
                            <?php endif; ?>
                        </div>
                    </a>
                </li>

                <!-- Pay Membership Link -->
                <li class="nav-item">
                    <a class="nav-link sidebar-link <?= $page === 'payment' ? 'active' : '' ?>" href="?page=payment">
                        <div class="d-flex align-items-center">
                            <div class="sidebar-icon">
                                <i class="bi bi-credit-card-fill"></i>
                            </div>
                            <span class="sidebar-text">Pay Membership</span>
                            <?php if ($page === 'payment'): ?>
                                <div class="sidebar-indicator"></div>
                            <?php endif; ?>
                        </div>
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

                <li class="nav-item">
                    <a class="nav-link sidebar-link <?= $page === 'assignjeepney' ? 'active' : '' ?> <?= !$hasPaid ? 'disabled-link' : '' ?>"
                       href="<?= $hasPaid ? '?page=assignjeepney' : '#' ?>"
                       onclick="<?= !$hasPaid ? "Swal.fire({icon: 'info', title: 'Access Denied', text: 'Complete your payment first.'}); return false;" : '' ?>">
                        <div class="d-flex align-items-center">
                            <div class="sidebar-icon">
                                <i class="bi bi-truck-front-fill"></i>
                            </div>
                            <span class="sidebar-text">Assigned Jeepney</span>
                            <?php if ($page === 'assignjeepney'): ?>
                                <div class="sidebar-indicator"></div>
                            <?php endif; ?>
                        </div>
                    </a>
                </li>

                <!-- Pay Boundary Link -->
                <li class="nav-item">
                    <a class="nav-link sidebar-link <?= $page === 'pay_boundary' ? 'active' : '' ?>" href="?page=pay_boundary">
                        <div class="d-flex align-items-center">
                            <div class="sidebar-icon">
                                <i class="bi bi-wallet2"></i>
                            </div>
                            <span class="sidebar-text">Pay Boundary</span>
                            <span class="badge bg-warning ms-auto" id="boundaryBadge" style="display: none;">0</span>
                            <?php if ($page === 'pay_boundary'): ?>
                                <div class="sidebar-indicator"></div>
                            <?php endif; ?>
                        </div>
                    </a>
                </li>

                <!-- Boarding Dashboard Link -->
                <li class="nav-item">
                    <a class="nav-link sidebar-link <?= $page === 'boarding_dashboard' ? 'active' : '' ?>" href="?page=boarding_dashboard">
                        <div class="d-flex align-items-center">
                            <div class="sidebar-icon">
                                <i class="bi bi-people-fill"></i>
                            </div>
                            <span class="sidebar-text">Boarding Dashboard</span>
                            <?php if ($page === 'boarding_dashboard'): ?>
                                <div class="sidebar-indicator"></div>
                            <?php endif; ?>
                        </div>
                    </a>
                </li>

                <!-- Divider -->
                <li class="nav-item">
                    <hr class="sidebar-divider">
                </li>

                <!-- Logout Link -->
                <li class="nav-item">
                    <a class="nav-link sidebar-link logout-link" href="../logout.php">
                        <div class="d-flex align-items-center">
                            <div class="sidebar-icon">
                                <i class="bi bi-box-arrow-right"></i>
                            </div>
                            <span class="sidebar-text">Logout</span>
                        </div>
                    </a>
                </li>
            </ul>
        </div>

        <!-- Sidebar Footer -->
        <div class="sidebar-footer p-3 text-center">
            <small class="text-muted">
                <i class="bi bi-clock me-1"></i>
                Last login: <?= date('M d, Y g:i A') ?>
            </small>
        </div>
    </div>

    <!-- Sidebar Styles -->
    <style>
    /* Header */
    .app-header {
        position: sticky;
        top: 0;
        z-index: 1030;
        height: 64px;
        margin-left: 280px;
        padding: 0 24px;
        background: transparent;
        border-bottom: 1px solid #e5e7eb;
        display: none;
        gap: 12px;
    }
    .app-logo {
        height: 36px;
    }
    .app-title {
        font-weight: 700;
        color: #111827;
        letter-spacing: 0.2px;
    }
    :root {
        --brand-primary: #4f46e5; /* Indigo 600 */
        --brand-primary-2: #7c3aed; /* Violet 600 */
        --brand-accent: #06b6d4; /* Cyan 500 */
        --brand-muted: #64748b; /* Slate 500 */
        --bg-soft: #f7f9fc;
        --text-strong: #1f2937; /* Gray 800 */
        --text-soft: #334155; /* Slate 700 */
        --card-bg: rgba(255,255,255,0.96);
        --shadow-sm: 0 6px 16px rgba(2, 6, 23, 0.08);
        --shadow-md: 0 12px 28px rgba(2, 6, 23, 0.12);
        --radius: 16px;
    }
    
    body {
        font-family: 'Inter', system-ui, -apple-system, Segoe UI, Roboto, 'Helvetica Neue', Arial, 'Noto Sans', 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol', sans-serif;
        background: var(--bg-soft);
        color: var(--text-strong);
    }
    .bg-gradient-dark {
        background: linear-gradient(180deg, #263042 0%, #1e2532 100%);
    }
    
    /* Sidebar Enhancement */
    .sidebar { 
        background: linear-gradient(180deg, #263042 0%, #1e2532 100%) !important;
        box-shadow: 4px 0 16px rgba(0,0,0,0.12);
        position: fixed !important;
        left: 0 !important;
        top: 0 !important;
        width: 280px !important;
        height: 100vh !important;
        overflow-y: auto !important;
        overflow-x: hidden !important;
        display: flex !important;
        flex-direction: column !important;
        z-index: 1040 !important;
    }
    .sidebar-link.active { 
        box-shadow: 0 10px 24px rgba(79,70,229,0.18); 
        background: linear-gradient(135deg, #2563eb 0%, #7c3aed 100%) !important; 
    }
    .sidebar-icon { 
        box-shadow: inset 0 0 0 1px rgba(255,255,255,0.10); 
    }
    .content-wrapper {
        margin-left: 280px;
        width: calc(100% - 280px);
        min-height: 100vh;
        background: var(--bg-soft);
        display: flex;
        flex-direction: column;
    }
    .content-container {
        display: flex !important;
        justify-content: center !important;
        align-items: flex-start !important;
        padding: 1.5rem !important;
        width: 100%;
    }
    .content-container > * {
        max-width: 1200px;
        width: 100%;
        margin: 0 auto;
    }
    .content-header {
        background: #ffffff;
        padding: 1rem 1.5rem;
        border-bottom: 1px solid rgba(0,0,0,0.05);
        box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        display: flex;
        align-items: center;
        gap: 1rem;
    }
    /* Cards and surfaces */
    .card,
    .compact-card {
        background: var(--card-bg);
        border: 1px solid rgba(2,6,23,0.06);
        border-radius: var(--radius);
        box-shadow: var(--shadow-sm);
        transition: all 0.3s ease;
        margin-bottom: 1.5rem;
        margin-left: auto;
        margin-right: auto;
    }
    .card:hover,
    .compact-card:hover { 
        box-shadow: var(--shadow-md);
        transform: translateY(-2px);
    }
    
    /* Center all cards and major containers */
    .dashboard-section,
    .payment-section {
        margin-left: auto !important;
        margin-right: auto !important;
    }
    
    /* Buttons with gradient styles */
    .btn { 
        border-radius: 12px; 
        font-weight: 600;
        transition: all 0.3s ease;
    }
    .btn-primary { 
        background-image: linear-gradient(135deg, var(--brand-primary), var(--brand-primary-2)); 
        border: none; 
    }
    .btn-primary:hover {
        filter: brightness(1.1);
        transform: translateY(-1px);
    }
    .btn-success { 
        background-image: linear-gradient(135deg, #16a34a, #22c55e); 
        border: none; 
    }
    .btn-success:hover {
        filter: brightness(1.1);
        transform: translateY(-1px);
    }
    .btn-warning { 
        background-image: linear-gradient(135deg, #f59e0b, #f97316); 
        border: none; 
        color: #fff; 
    }
    .btn-outline-primary { 
        border-color: var(--brand-primary); 
        color: var(--brand-primary); 
    }
    .btn-outline-primary:hover { 
        background: var(--brand-primary); 
        color: #fff; 
    }
    
    /* Badges */
    .badge { 
        border-radius: 10px; 
        font-weight: 600;
    }
    .badge.bg-primary { 
        background-image: linear-gradient(135deg, var(--brand-primary), var(--brand-primary-2)); 
    }
    
    /* Status Cards Enhancement */
    .status-card,
    .map-card { 
        border: 1px solid rgba(2,6,23,0.06); 
        border-radius: var(--radius);
        box-shadow: var(--shadow-sm);
        transition: all 0.3s ease;
    }
    .status-card:hover,
    .map-card:hover {
        box-shadow: var(--shadow-md);
        transform: translateY(-2px);
    }
    
    /* Headers Enhancement */
    .dashboard-header,
    .header-content { 
        border: 1px solid rgba(2,6,23,0.06); 
        border-radius: var(--radius);
        box-shadow: var(--shadow-sm);
    }
    
    /* Form Elements */
    .form-control,
    .form-select {
        border-radius: 10px;
        border: 2px solid #e5e7eb;
        transition: border-color .2s, box-shadow .2s;
    }
    .form-control:focus,
    .form-select:focus {
        border-color: var(--brand-primary);
        box-shadow: 0 0 0 3px rgba(79,70,229,0.15);
    }
    
    /* Payment Cards */
    .payment-method-card:hover {
        border-color: var(--brand-primary);
        background-color: #f8f9ff;
        transform: translateY(-2px);
    }
    
    /* Animations */
    .fade-slide-in { 
        animation: fadeSlideIn .45s ease both; 
    }
    @keyframes fadeSlideIn { 
        from { opacity: 0; transform: translateY(6px); } 
        to { opacity: 1; transform: translateY(0); } 
    }
    
    /* Scrollbar Styling */
    .content-container::-webkit-scrollbar {
        width: 8px;
    }
    .content-container::-webkit-scrollbar-track {
        background: #f1f1f1;
        border-radius: 10px;
    }
    .content-container::-webkit-scrollbar-thumb {
        background: linear-gradient(135deg, var(--brand-primary), var(--brand-primary-2));
        border-radius: 10px;
    }
    .content-container::-webkit-scrollbar-thumb:hover {
        background: linear-gradient(135deg, var(--brand-primary-2), var(--brand-primary));
    }
    
    /* Action Icons */
    .action-icon {
        background: linear-gradient(135deg, var(--brand-primary), var(--brand-primary-2));
        transition: all 0.3s ease;
    }
    .action-card:hover .action-icon {
        transform: scale(1.1);
    }
    
    /* Payment Icons */
    .payment-icon {
        background: linear-gradient(135deg, var(--brand-primary), var(--brand-primary-2));
    }
    
    /* Gradient Header */
    .gradient-header { 
        background: linear-gradient(135deg, var(--brand-primary), var(--brand-primary-2)); 
        box-shadow: 0 8px 20px rgba(79,70,229,0.25); 
    }
    
    /* Focus Field Styles */
    .focus-field .form-control { 
        border-radius: 10px; 
        border: 2px solid #e5e7eb; 
        transition: border-color .2s, box-shadow .2s; 
    }
    .focus-field .form-control:focus { 
        border-color: var(--brand-primary); 
        box-shadow: 0 0 0 3px rgba(79,70,229,0.15); 
    }
    
    /* Modal Fixes - Allow clicks on modal and close button properly */
    body.modal-open {
        overflow: hidden !important;
        padding-right: 0 !important;
    }

    .modal-backdrop {
        position: fixed !important;
        top: 0;
        left: 0;
        width: 100vw;
        height: 100vh;
        background-color: rgba(0,0,0,0.45) !important;
        z-index: 19999 !important;
    }

    .modal {
        position: fixed !important;
        z-index: 20000 !important;
        pointer-events: auto !important;
    }

    body.modal-open .sidebar,
    body.modal-open .content-wrapper,
    body.modal-open .content-container {
        z-index: 1 !important;
    }

    .modal-dialog {
        pointer-events: auto !important;
        z-index: 20001 !important;
        position: relative !important;
    }
    
    .modal-content {
        pointer-events: auto !important;
        z-index: 20002 !important;
        background-color: white !important;
        border: none !important;
        box-shadow: 0 20px 60px rgba(0,0,0,0.3) !important;
        position: relative !important;
    }
    
    .modal input,
    .modal select,
    .modal button,
    .modal textarea,
    .modal .form-control,
    .modal .form-select,
    .modal .btn,
    .modal label {
        pointer-events: auto !important;
        position: relative !important;
        z-index: 1 !important;
    }
    
    .modal-header .btn-close {
        opacity: 1 !important;
        pointer-events: auto !important;
        z-index: 10 !important;
    }
    
    .modal-body {
        pointer-events: auto !important;
    }
    
    .modal-footer {
        pointer-events: auto !important;
    }
    
    .compact-card-header {
        padding: 1rem 1.25rem;
        border-bottom: 1px solid rgba(0,0,0,0.05);
        background: #f8f9fa;
        border-radius: var(--radius) var(--radius) 0 0;
    }
    .compact-card-body { padding: 1.25rem; }
    .payment-method-card {
        border: 2px solid #e9ecef;
        border-radius: var(--radius);
        padding: 1rem;
        margin-bottom: 1rem;
        transition: all 0.3s ease;
        cursor: pointer;
    }
    .payment-method-card:hover { border-color: var(--brand-primary); background-color: #f8f9ff; }
    .payment-method-card.selected { border-color: var(--brand-primary); background-color: #f0f4ff; }
    .payment-icon {
        width: 40px; height: 40px; border-radius: 8px; display: flex; align-items: center; justify-content: center;
        background: linear-gradient(135deg, var(--brand-primary), var(--brand-primary-2)); color: white; font-size: 1.25rem;
    }
    .amount-display { font-size: 2rem; font-weight: 700; color: var(--brand-primary); text-align: center; margin: 1rem 0; }
    .quick-actions { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; margin-top: 1.5rem; }
    .action-card { background: white; border-radius: var(--radius); padding: 1.5rem 1rem; text-align: center; box-shadow: 0 2px 8px rgba(0,0,0,0.05); transition: all 0.3s ease; border: 1px solid rgba(0,0,0,0.05); }
    .action-card:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(0,0,0,0.1); }
    .action-icon { width: 60px; height: 60px; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 1rem; background: linear-gradient(135deg, var(--brand-primary), var(--brand-primary-2)); color: white; font-size: 1.5rem; }
    
    .sidebar {
        box-shadow: 4px 0 16px rgba(0,0,0,0.12);
        transition: box-shadow 0.2s ease;
    }
    
    .sidebar-header {
        border-bottom: 1px solid rgba(255,255,255,0.1);
        flex-shrink: 0;
    }
    
    .sidebar-nav {
        flex: 1;
        overflow-y: auto;
        overflow-x: hidden;
    }
    
    .sidebar-footer {
        flex-shrink: 0;
    }
    
    .sidebar-link {
        color: rgba(255,255,255,0.85) !important;
        padding: 10px 14px;
        border-radius: 10px;
        margin-bottom: 6px;
        transition: background-color 0.2s ease, color 0.2s ease, transform 0.2s ease;
        text-decoration: none;
        position: relative;
    }
    
    .sidebar-link:hover {
        color: #ffffff !important;
        background: rgba(255,255,255,0.08);
        transform: translateX(2px);
    }
    
    .sidebar-link.active {
        color: #ffffff !important;
        background: linear-gradient(135deg, #2563eb 0%, #7c3aed 100%);
        box-shadow: 0 6px 18px rgba(37, 99, 235, 0.25);
    }
    
    .sidebar-icon {
        width: 36px;
        height: 36px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 10px;
        margin-right: 12px;
        background: rgba(255,255,255,0.08);
        transition: background-color 0.2s ease, transform 0.2s ease;
    }
    
    .sidebar-link:hover .sidebar-icon {
        background: rgba(255,255,255,0.16);
        transform: scale(1.05);
    }
    
    .sidebar-link.active .sidebar-icon {
        background: rgba(255,255,255,0.2);
    }
    
    .sidebar-text {
        font-weight: 500;
        font-size: 0.94rem;
        letter-spacing: 0.2px;
    }
    
    .sidebar-indicator {
        position: absolute;
        right: 14px;
        top: 50%;
        transform: translateY(-50%);
        width: 6px;
        height: 6px;
        background: #ffffff;
        opacity: 0.9;
        border-radius: 50%;
        box-shadow: 0 0 8px rgba(255,255,255,0.45);
    }
    
    .sidebar-divider {
        border-color: rgba(255,255,255,0.1);
        margin: 1rem 0;
    }
    
    .logout-link {
        color: #ef4444 !important;
    }
    
    .logout-link:hover {
        background: rgba(239, 68, 68, 0.12) !important;
        color: #ef4444 !important;
    }
    
    .logout-link .sidebar-icon {
        background: rgba(239, 68, 68, 0.18);
    }
    
    .sidebar-footer {
        border-top: 1px solid rgba(255,255,255,0.1);
        background: rgba(0,0,0,0.1);
    }
    
    .sidebar-footer .text-muted {
        color: rgba(255,255,255,0.85) !important;
    }
    
    .disabled-link {
        opacity: 0.55 !important;
        pointer-events: none !important;
        cursor: not-allowed !important;
    }
    
    .disabled-link:hover {
        transform: none !important;
        background: rgba(255,255,255,0.05) !important;
    }
    
    /* Badge Animation */
    .badge {
        animation: none;
        font-weight: 600;
        letter-spacing: 0.2px;
    }
    
    /* Remove playful pulse animation for a calmer UI */
    @keyframes pulse { }
    
    
    /* Scrollbar Styling */
    /* Sidebar Scrollbar */
    .sidebar::-webkit-scrollbar,
    .sidebar-nav::-webkit-scrollbar {
        width: 6px;
    }
    
    .sidebar::-webkit-scrollbar-track,
    .sidebar-nav::-webkit-scrollbar-track {
        background: rgba(255,255,255,0.05);
    }
    
    .sidebar::-webkit-scrollbar-thumb,
    .sidebar-nav::-webkit-scrollbar-thumb {
        background: rgba(255,255,255,0.25);
        border-radius: 3px;
    }
    
    .sidebar::-webkit-scrollbar-thumb:hover,
    .sidebar-nav::-webkit-scrollbar-thumb:hover {
        background: rgba(255,255,255,0.4);
    }
    
    /* Sidebar Toggle Button - visible on mobile, hidden on desktop */
    .sidebar-toggle {
        position: fixed;
        top: 15px;
        left: 15px;
        z-index: 1000;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        border: none;
        border-radius: 12px;
        width: 45px;
        height: 45px;
        display: none; /* Hidden by default on desktop */
        align-items: center;
        justify-content: center;
        color: white;
        font-size: 1.5rem;
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        cursor: pointer;
        transition: all 0.3s ease;
    }
    
    .sidebar-toggle:hover {
        transform: scale(1.05);
        box-shadow: 0 6px 16px rgba(0,0,0,0.2);
    }
    
    /* Show sidebar toggle only on mobile */
    @media (max-width: 767px) {
        .sidebar-toggle {
            display: flex !important;
        }
    }
    
    /* Card polish */
    .card {
        border-radius: 12px;
    }
    .card .card-header {
        border-top-left-radius: 12px;
        border-top-right-radius: 12px;
    }
    
    /* Tables */
    .modern-fare-table thead th {
        font-size: 0.85rem;
        color: #f8f9fa;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.04em;
        border-bottom: 1px solid #e5e7eb;
    }
    .modern-fare-table tbody td {
        vertical-align: middle;
        border-top-color: #f3f4f6;
    }
    
    /* Buttons */
    .btn {
        border-radius: 8px;
    }
    .btn-success {
        background: #16a34a;
        border-color: #16a34a;
    }
    .btn-success:hover {
        background: #15803d;
        border-color: #15803d;
    }
    .btn-light {
        border-color: #e5e7eb;
    }
    
    /* Enhanced Page Container Styles */
    .content.dashboard-page,
    .content.payment-page {
        background: transparent;
        min-height: 100%;
        padding: 1rem;
        border-radius: 16px;
        box-shadow: none;
        border: none;
        margin: 0 auto;
        text-align: left;
    }
    
    /* Card Header Gradients for Different Pages */
    .dashboard-page .card-header {
        background: linear-gradient(135deg, #1976d2, #42a5f5);
        color: white;
        border-radius: 15px 15px 0 0;
        border: none;
    }
    
    .payment-page .card-header {
        background: linear-gradient(135deg, #388e3c, #66bb6a);
        color: white;
        border-radius: 15px 15px 0 0;
        border: none;
    }
    
    /* Enhanced Card Styling */
    .card {
        background: rgba(255, 255, 255, 0.95);
        border-radius: 15px;
        box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
        border: 1px solid rgba(255, 255, 255, 0.2);
    }
    
    .card-header {
        border-radius: 15px 15px 0 0;
        border: none;
    }
    
    /* Alert Styling */
    .alert {
        background: rgba(255, 255, 255, 0.95);
        border-radius: 15px;
        box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
    }
    
    /* Table Enhancements */
    .table {
        background: rgba(255, 255, 255, 0.95);
        border-radius: 10px;
        overflow: hidden;
    }
    
    .table thead {
        background: linear-gradient(135deg, var(--brand-primary), var(--brand-primary-2));
        color: white;
    }
    
    .table-hover tbody tr:hover {
        background-color: rgba(79, 70, 229, 0.05);
        transform: scale(1.01);
        transition: all 0.3s ease;
    }
    
    /* Input Groups */
    .input-group-text {
        background: linear-gradient(135deg, var(--brand-primary), var(--brand-primary-2));
        color: white;
        border: none;
        border-radius: 10px 0 0 10px;
    }
    
    /* Progress Bars */
    .progress {
        border-radius: 10px;
        background: rgba(0, 0, 0, 0.05);
    }
    
    .progress-bar {
        background: linear-gradient(135deg, var(--brand-primary), var(--brand-primary-2));
        border-radius: 10px;
    }
    
    /* List Groups */
    .list-group-item {
        border: 1px solid rgba(2,6,23,0.06);
        border-radius: 10px !important;
        margin-bottom: 0.5rem;
        transition: all 0.3s ease;
    }
    
    .list-group-item:hover {
        background-color: rgba(79, 70, 229, 0.05);
        transform: translateX(5px);
    }
    
    /* Dropdown Menus */
    .dropdown-menu {
        border-radius: 12px;
        box-shadow: 0 10px 25px rgba(0, 0, 0, 0.15);
        border: 1px solid rgba(2,6,23,0.06);
    }
    
    .dropdown-item:hover {
        background: linear-gradient(135deg, rgba(79, 70, 229, 0.1), rgba(124, 58, 237, 0.1));
    }
    
    /* Tooltips */
    .tooltip-inner {
        background: linear-gradient(135deg, var(--brand-primary), var(--brand-primary-2));
        border-radius: 8px;
    }
    
    /* Breadcrumbs */
    .breadcrumb {
        background: rgba(255, 255, 255, 0.95);
        border-radius: 10px;
        padding: 0.75rem 1rem;
    }
    
    .breadcrumb-item.active {
        color: var(--brand-primary);
        font-weight: 600;
    }
    
    /* Pagination */
    .pagination .page-link {
        border-radius: 8px;
        margin: 0 2px;
        border: 1px solid rgba(2,6,23,0.06);
    }
    
    .pagination .page-item.active .page-link {
        background: linear-gradient(135deg, var(--brand-primary), var(--brand-primary-2));
        border: none;
    }
    
    /* Spinners */
    .spinner-border {
        border-color: var(--brand-primary);
        border-right-color: transparent;
    }
    
    /* Responsive Image */
    img {
        max-width: 100%;
        height: auto;
        border-radius: 8px;
    }
    
    /* Sidebar Toggle Button - Hide on Desktop, Show on Mobile */
    .sidebar-toggle {
        display: none; /* Hidden by default on desktop */
        position: fixed;
        top: 20px;
        left: 20px;
        z-index: 1050;
        background: linear-gradient(135deg, var(--brand-primary), var(--brand-primary-2));
        color: white;
        border: none;
        border-radius: 12px;
        width: 45px;
        height: 45px;
        align-items: center;
        justify-content: center;
        font-size: 1.5rem;
        cursor: pointer;
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        transition: all 0.3s ease;
    }
    
    .sidebar-toggle:hover {
        transform: scale(1.1);
        box-shadow: 0 6px 16px rgba(0,0,0,0.2);
    }
    
    /* Show sidebar toggle only on tablets and mobile */
    @media (max-width: 992px) {
        .sidebar-toggle {
            display: flex !important;
        }
    }
    
    /* Responsive adjustments for mobile */
    @media (max-width: 768px) {
        .sidebar-toggle {
            display: flex !important;
            top: 15px;
            left: 15px;
            width: 40px;
            height: 40px;
            font-size: 1.3rem;
        }
    }
    </style>
    <script>
        // Payment method selection
        document.querySelectorAll('.payment-method-card').forEach(function(card) {
            card.addEventListener('click', function() {
                document.querySelectorAll('.payment-method-card').forEach(function(c) { c.classList.remove('selected'); });
                this.classList.add('selected');
                var method = this.getAttribute('data-method');
                var radio = document.getElementById(method + 'Method');
                if (radio) radio.checked = true;
            });
        });
        var submitBtn = document.getElementById('submitPayment');
        if (submitBtn) {
            submitBtn.addEventListener('click', function() {
                var selected = document.querySelector('input[name="paymentMethod"]:checked');
                if (!selected) { alert('Please select a payment method first.'); return; }
                var self = this;
                var original = self.innerHTML;
                self.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Processing...';
                self.disabled = true;
                setTimeout(function(){ self.innerHTML = original; self.disabled = false; alert('Payment submitted successfully! Your membership will be activated after verification.'); }, 1500);
            });
        }
    });
    </script>

    <!-- Main Content: content-wrapper + content-header + content-container -->
    <div class="content-wrapper">
        <div class="content-header">
            <div class="d-flex align-items-center gap-3">
                <img src="../img/logo12.png" alt="JeepniGo Logo" style="height: 32px;">
                <h4 class="mb-0 fw-bold">Driver Dashboard</h4>
            </div>
        </div>

        <!-- Content Container -->
        <div class="content-container">
            <div class="content <?= $page === 'dashboard' ? 'dashboard-page' : ($page === 'collect_fares' ? 'fares-page' : ($page === 'profile' ? 'profile-page' : ($page === 'payment' ? 'payment-page' : ($page === 'assignjeepney' ? 'assignment-page' : ($page === 'pay_boundary' ? 'boundary-page' : ($page === 'boarding_dashboard' ? 'boarding-page' : 'dashboard-page')))))); ?>">
        
        <?php if ($page === 'dashboard'): ?>
            <!-- Dashboard -->
            <!-- Welcome Hero Section -->
            <div class="welcome-hero mb-4 animate__animated animate__fadeIn">
                <div class="row g-0 align-items-center">
                    <div class="col-md-3 text-center">
                        <div class="hero-avatar position-relative">
                            <img src="<?= htmlspecialchars($profileImage); ?>" alt="Profile Picture" class="rounded-circle shadow-lg" width="140" height="140" style="object-fit: cover; border: 5px solid #fff;">
                            <div class="status-badge">
                                <i class="bi bi-circle-fill text-success"></i>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-9">
                        <div class="hero-content">
                            <h1 class="display-6 fw-bold mb-2">Welcome back, <?= htmlspecialchars($userFirstName); ?>! 👋</h1>
                            <p class="text-muted mb-3">
                                <i class="bi bi-shield-check text-success me-2"></i>
                                <span class="badge bg-gradient-primary px-3 py-2 me-2"><?= htmlspecialchars($userType); ?></span>
                                <i class="bi bi-clock me-1"></i>
                                <?= date('l, F d, Y') ?>
                            </p>
                            <div class="quick-stats">
                                <div class="stat-item">
                                    <i class="bi bi-trophy-fill text-warning"></i>
                                    <span>Active Driver</span>
                                </div>
                                <div class="stat-item">
                                    <i class="bi bi-geo-alt-fill text-primary"></i>
                                    <span><?= isset($assignedJeepney['route']) ? htmlspecialchars($assignedJeepney['route']) : 'No Route' ?></span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Quick Actions Dashboard -->
            <div class="row g-3 mb-4">
                <div class="col-md-3 col-sm-6">
                    <a href="?page=collect_fares" class="quick-action-card">
                        <div class="icon-wrapper bg-gradient-success">
                            <i class="bi bi-cash-coin"></i>
                        </div>
                        <h6>Fare Payments</h6>
                        <p>View & Confirm</p>
                        <div class="arrow">
                            <i class="bi bi-arrow-right-circle"></i>
                        </div>
                    </a>
                </div>
                <div class="col-md-3 col-sm-6">
                    <a href="?page=pay_boundary" class="quick-action-card">
                        <div class="icon-wrapper bg-gradient-warning">
                            <i class="bi bi-wallet2"></i>
                        </div>
                        <h6>Pay Boundary</h6>
                        <p>Submit Payment</p>
                        <div class="arrow">
                            <i class="bi bi-arrow-right-circle"></i>
                        </div>
                    </a>
                </div>
                <div class="col-md-3 col-sm-6">
                    <a href="?page=assignjeepney" class="quick-action-card">
                        <div class="icon-wrapper bg-gradient-info">
                            <i class="bi bi-truck-front"></i>
                        </div>
                        <h6>My Jeepney</h6>
                        <p>View Details</p>
                        <div class="arrow">
                            <i class="bi bi-arrow-right-circle"></i>
                        </div>
                    </a>
                </div>
                <div class="col-md-3 col-sm-6">
                    <a href="?page=boarding_dashboard" class="quick-action-card">
                        <div class="icon-wrapper bg-gradient-danger">
                            <i class="bi bi-people-fill"></i>
                        </div>
                        <h6>Boarding</h6>
                        <p>Manage Passengers</p>
                        <div class="arrow">
                            <i class="bi bi-arrow-right-circle"></i>
                        </div>
                    </a>
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

            <!-- Fare Compliance by Time Slot -->
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-secondary text-white d-flex justify-content-between align-items-center">
                    <div><i class="bi bi-table"></i> Compliance by Time Slot</div>
                    <input type="date" class="form-control form-control-sm" id="fcDate" value="<?= date('Y-m-d') ?>" style="max-width:160px">
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-striped table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Time Slot</th><th>Plate</th><th>Route</th>
                                    <th class="text-end">Total App Trips</th><th class="text-end">Collected</th><th class="text-end">Rate %</th>
                                </tr>
                            </thead>
                            <tbody id="fcRows"><tr><td colspan="6" class="text-center text-muted">Loading...</td></tr></tbody>
                        </table>
                    </div>
                </div>
            </div>

            <script>
            (function(){
                const plateNumber = <?= json_encode($assignedJeepney['plate_number'] ?? '') ?>;
                const route = <?= json_encode($assignedJeepney['route'] ?? '') ?>;
                
                // Make fcLoadSlots globally accessible so it can be called from other functions
                window.fcLoadSlots = function(){
                    const date = document.getElementById('fcDate')?.value || '<?= date('Y-m-d') ?>';
                    const slots = [
                        {start:'07:00', end:'09:00', label:'7–9 AM'},
                        {start:'08:00', end:'10:00', label:'8–10 AM'},
                        {start:'17:00', end:'19:00', label:'5–7 AM'}
                    ];
                    const tb = document.getElementById('fcRows');
                    if (!tb) return; // Exit if table doesn't exist
                    
                    tb.innerHTML = '<tr><td colspan="6" class="text-center text-muted">Loading...</td></tr>';
                    Promise.all(slots.map(s=>{
                        const start = date+' '+s.start+':00'; const end = date+' '+s.end+':00';
                        return fetch('pay_fare.php', {method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({action:'count', route, start_time:start, end_time:end})})
                            .then(r=>r.json()).then(d=>({slot:s, total:(d&&d.success)?(d.total||0):0, compliant:(d&&d.success)?(d.compliant||0):0}));
                    })).then(rows=>{
                        const html = rows.map(r=>{
                            const rate = r.total>0 ? (r.compliant/r.total*100) : 0;
                            return `<tr>
                                <td>${r.slot.label}</td><td>${plateNumber||'-'}</td><td>${route||'-'}</td>
                                <td class='text-end'>${r.total}</td><td class='text-end'>${r.compliant}</td><td class='text-end'>${rate.toFixed(1)}%</td>
                            </tr>`;
                        }).join('');
                        tb.innerHTML = html || '<tr><td colspan="6" class="text-center text-muted">No data</td></tr>';
                    }).catch(err => {
                        console.error('Error loading compliance data:', err);
                        tb.innerHTML = '<tr><td colspan="6" class="text-center text-danger">Error loading data</td></tr>';
                    });
                };
                
                const fcDateEl = document.getElementById('fcDate');
                if (fcDateEl) {
                    fcDateEl.addEventListener('change', window.fcLoadSlots);
                }
                document.addEventListener('DOMContentLoaded', window.fcLoadSlots);
            })();
            </script>
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
                            
                            // Refresh compliance table when new fares arrive (in case they're already collected)
                            // Note: Compliance only counts "Collected" fares, but we refresh to catch any edge cases
                            if (typeof window.fcLoadSlots === 'function') {
                                setTimeout(() => {
                                    window.fcLoadSlots();
                                }, 1000); // Small delay to allow for any status updates
                            }
                        }
                        
                        // Update last fare receipts
                        lastFareReceipts = currentReceipts;
                        lastFareCount = data.fares.length;
                        
                        // Ensure fares are sorted by paid_at (newest first) - additional client-side sort as safety
                        data.fares.sort((a, b) => {
                            const timeA = new Date(a.paid_at || '1970-01-01').getTime();
                            const timeB = new Date(b.paid_at || '1970-01-01').getTime();
                            return timeB - timeA; // Descending order (newest first)
                        });
                        
                        let html = `<div class='table-responsive'><table class='table table-hover table-borderless align-middle modern-fare-table'><thead><tr><th>Passenger</th><th>Route</th><th>Amount</th><th>Method</th><th>Date</th><th>Time</th><th>Receipt</th><th>Status</th><th>Action</th></tr></thead><tbody>`;
                        data.fares.forEach(fare => {
                            const isCollected = fare.status === 'Collected';
                            const isNew = newFares.some(newFare => newFare.receipt_number === fare.receipt_number);
                            const routeMatch = fare.route === route ? 'bg-success text-white' : 'bg-info text-white';
                            // Split date and time
                            const paidAt = fare.paid_at || '';
                            const dateTime = paidAt.split(' ');
                            const datePart = dateTime[0] || '';
                            const timePart = dateTime[1] || '';
                            // Format date as YYYY-MM-DD (keep original format)
                            const formattedDate = datePart;
                            // Format time as HH:MM (remove seconds)
                            const formattedTime = timePart ? timePart.substring(0, 5) : '';
                            // Normalize route separator: replace any ' ? ' with right arrow entity
                            const routeDisplay = (fare.route||'').split(' ? ').join(' &rarr; ');
                            html += `<tr class='fare-row animate__animated ${isNew ? 'animate__pulse' : 'animate__fadeIn'}' style='${isNew ? 'background-color: #fff3cd;' : ''}'>
                                <td><span class='fw-semibold'><i class="bi bi-person-circle me-1 text-primary"></i>${fare.passenger}</span></td>
                                <td><span class='badge ${routeMatch} px-2 py-1 text-uppercase small'>${routeDisplay}</span></td>
                                <td><span class='text-success fw-bold'>₱${fare.amount}</span></td>
                                <td><span class='badge bg-primary bg-gradient px-3 py-2 text-uppercase'>${fare.payment_method}</span></td>
                                <td><span class='text-muted'>${formattedDate || datePart}</span></td>
                                <td><span class='text-muted'>${formattedTime || timePart}</span></td>
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
                .then(res => {
                    if (!res.ok) throw new Error('Network response was not ok');
                    return res.json();
                })
                .then(data => {
                    if (data.success) {
                        // Defensive: check for nulls before updating UI
                        const tr = btn.closest('tr');
                        let statusCell = null;
                        if (tr) statusCell = tr.querySelector('td:nth-child(8) span');
                        if (statusCell) {
                            statusCell.className = 'badge rounded-pill bg-success px-3 py-2 fs-6';
                            statusCell.textContent = 'Collected';
                        }
                        const confirmIcon = document.createElement('span');
                        confirmIcon.className = 'text-success';
                        confirmIcon.innerHTML = '<i class="bi bi-check-circle-fill"></i> Confirmed';
                        btn.replaceWith(confirmIcon);
                        if (tr) {
                            tr.style.backgroundColor = '#d4edda';
                            tr.style.transition = 'background-color 0.5s ease';
                            setTimeout(() => { tr.style.backgroundColor = ''; }, 3000);
                        }
                        
                        // Show success message
                        Swal.fire({
                            icon: 'success',
                            title: '🎉 Fare Collected Successfully!',
                            text: `Receipt #${receiptNumber} has been confirmed and marked as collected.`,
                            confirmButtonText: 'Great!',
                            confirmButtonColor: '#28a745',
                            timer: 3000,
                            timerProgressBar: true,
                            showClass: { popup: 'animate__animated animate__bounceIn' }
                        });
                        
                        // Refresh the compliance table immediately to reflect the new collected fare
                        if (typeof window.fcLoadSlots === 'function') {
                            setTimeout(() => {
                                window.fcLoadSlots();
                            }, 500); // Small delay to ensure database update is complete
                        }
                        
                        // Also refresh the fare list to update the status display
                        setTimeout(() => {
                            fetchFares();
                        }, 500);
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
                .catch((err) => {
                    btn.disabled = false;
                    btn.innerHTML = '<i class="bi bi-check-circle me-1"></i>Confirm';
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: err.message || 'Failed to confirm payment. Please try again.',
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
            
            // Initial load (manual refresh only)
            fetchFares();
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
                                        require_once __DIR__ . '/../treasurer/get_payment_instructions.php';
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
                                                <img src="../img/logo12.png" alt="TEBZ Logo" class="receipt-logo mb-3" style="height: 60px;">
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
                                            <img src="../img/logo12.png" alt="TEBZ Logo" style="height: 60px;">
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
            <div class="assignment-container w-100">
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

        <?php if ($page === 'boarding_dashboard'): ?>
            <div class="boarding-container w-100">
            <div class="row g-3">
                <div class="col-lg-4">
                    <div class="card shadow-sm">
                        <div class="card-header bg-primary text-white"><i class="bi bi-graph-up"></i> Today's Stats</div>
                        <div class="card-body">
                            <div class="d-flex justify-content-between"><span>Boarded Passengers</span><strong id="bdBoarded">0</strong></div>
                            <div class="d-flex justify-content-between"><span>Avg Wait (min)</span><strong id="bdAvgWait">0</strong></div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-8">
                    <div class="card shadow-sm">
                        <div class="card-header bg-success text-white"><i class="bi bi-people"></i> Active Reservations</div>
                        <div class="card-body p-2">
                            <div class="d-flex gap-2 mb-2">
                                <input type="text" class="form-control form-control-sm" id="bdRouteFilter" placeholder="Filter by route (optional)" style="max-width:220px">
                                <button class="btn btn-outline-secondary btn-sm" onclick="bdApplyFilter()"><i class="bi bi-filter"></i></button>
                            </div>
                            <div id="bdList" class="small text-muted">Loading...</div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Boarding History Table -->
            <div class="row g-3 mt-3">
                <div class="col-12">
                    <div class="card shadow-sm">
                        <div class="card-header bg-info text-white d-flex justify-content-between align-items-center">
                            <div>
                                <i class="bi bi-clock-history"></i> Boarding History
                                <span id="historyHeaderDate" class="ms-2 small opacity-75"></span>
                            </div>
                            <div class="d-flex gap-2 align-items-center">
                                <input type="date" class="form-control form-control-sm" id="historyDateFilter" 
                                       value="<?= date('Y-m-d') ?>" style="max-width:160px">
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="checkbox" id="historyShowAll" 
                                           onchange="loadBoardingHistory()">
                                    <label class="form-check-label text-white" for="historyShowAll" style="font-size:0.875rem;">
                                        Show All
                                    </label>
                                </div>
                                <button class="btn btn-light btn-sm" onclick="loadBoardingHistory()">
                                    <i class="bi bi-arrow-repeat"></i> Refresh
                                </button>
                                <button class="btn btn-light btn-sm" onclick="exportHistory()">
                                    <i class="bi bi-download"></i> Export
                                </button>
                            </div>
                        </div>
                        <div class="card-body p-0">
                            <div id="historyLoading" class="text-center text-muted py-4" style="display:none;">
                                <div class="spinner-border text-info" role="status"></div>
                                <div>Loading history...</div>
                            </div>
                            <div class="table-responsive">
                                <table class="table table-hover table-striped mb-0" id="boardingHistoryTable" style="border-radius: 0;">
                                    <thead class="table-light" style="border-radius: 0;">
                                        <tr>
                                            <th style="border-top-left-radius: 0; border-top-right-radius: 0;">#</th>
                                            <th>Passenger Name</th>
                                            <th>From → To</th>
                                            <th>Distance</th>
                                            <th>Started Waiting</th>
                                            <th>Boarded Time</th>
                                            <th>Wait Time</th>
                                            <th style="border-top-right-radius: 0;">Date</th>
                                        </tr>
                                    </thead>
                                    <tbody id="historyTableBody">
                                        <tr>
                                            <td colspan="8" class="text-center text-muted">No boarding records found for today</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        <div class="card-footer bg-light">
                            <div class="d-flex justify-content-between align-items-center">
                                <div class="small text-muted" id="historyCount">Total: 0 passengers</div>
                                <div class="small text-muted" id="historyAvgWait">Average Wait: 0 min</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- App Boarding Counts by Time Period -->
            <div class="row g-3 mt-3">
                <div class="col-12">
                    <div class="card shadow-sm">
                        <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                            <div>
                                <i class="bi bi-people-fill"></i> Boarded Passengers by Time Period
                                <span id="vmHeaderDate" class="ms-2 small opacity-75"></span>
                            </div>
                            <div class="d-flex gap-2 align-items-center">
                                <input type="date" id="vmDate" class="form-control form-control-sm" value="<?= date('Y-m-d') ?>" style="max-width:160px">
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="checkbox" id="vmShowAll" 
                                           onchange="vmLoadAll()">
                                    <label class="form-check-label text-white" for="vmShowAll" style="font-size:0.875rem;">
                                        Show All
                                    </label>
                                </div>
                                <button class="btn btn-sm btn-light" type="button" id="vmRefresh"><i class="bi bi-arrow-repeat"></i> Refresh</button>
                            </div>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-striped table-hover mb-0" style="border-radius: 0;">
                                    <thead class="table-light" style="border-radius: 0;">
                                        <tr>
                                            <th>Time Period</th>
                                            <th>Plate Number</th>
                                            <th>Route</th>
                                            <th class="text-end">App Boarding Count</th>
                                            <th class="text-end">Visibility %</th>
                                        </tr>
                                    </thead>
                                    <tbody id="vmRows">
                                        <tr>
                                            <td colspan="5" class="text-center text-muted">Loading...</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        <div class="card-footer bg-light">
                            <div class="d-flex justify-content-between align-items-center">
                                <div class="small text-muted" id="vmTotal">Total Boarded: 0</div>
                                <div class="btn-group btn-group-sm">
                                    <button class="btn btn-outline-primary" type="button" onclick="vmLoadPeriod('07:00','08:00')">7–8 AM</button>
                                    <button class="btn btn-outline-primary" type="button" onclick="vmLoadPeriod('08:00','09:00')">8–9 AM</button>
                                    <button class="btn btn-outline-primary" type="button" onclick="vmLoadPeriod('09:00','10:00')">9–10 AM</button>
                                    <button class="btn btn-outline-primary" type="button" onclick="vmLoadAll()">All Periods</button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <script>
            (function(){
                function fetchStats(){
                    fetch('../shared/reservations.php', {method:'POST', body:new URLSearchParams({action:'driver_stats'})})
                    .then(r=>r.json()).then(d=>{
                        if(!d.success) return;
                        document.getElementById('bdBoarded').textContent = d.boarded_today||0;
                        const mins = d.avg_wait_seconds? Math.round(d.avg_wait_seconds/60):0;
                        document.getElementById('bdAvgWait').textContent = mins;
                    });
                }
                function fmtWait(sec){ if(!sec) return '-'; const m=Math.floor(sec/60), s=sec%60; return m+'m '+s+'s'; }
                
                // Parse MySQL datetime string to local Date object (handles timezone correctly)
                function parseMySQLDateTime(dateStr) {
                    if (!dateStr) return null;
                    try {
                        // MySQL datetime format: "YYYY-MM-DD HH:MM:SS" or "YYYY-MM-DD HH:MM:SS.000000"
                        const cleanStr = dateStr.trim().split('.')[0]; // Remove microseconds if present
                        const parts = cleanStr.match(/(\d{4})-(\d{2})-(\d{2})\s+(\d{2}):(\d{2}):(\d{2})/);
                        if (!parts) {
                            // Try ISO format with T separator
                            if (dateStr.includes('T')) {
                                return new Date(dateStr);
                            }
                            return null;
                        }
                        
                        // Create Date object using local time (not UTC)
                        const year = parseInt(parts[1], 10);
                        const month = parseInt(parts[2], 10) - 1; // Month is 0-indexed
                        const day = parseInt(parts[3], 10);
                        const hour = parseInt(parts[4], 10);
                        const minute = parseInt(parts[5], 10);
                        const second = parseInt(parts[6], 10);
                        
                        const date = new Date(year, month, day, hour, minute, second);
                        if (isNaN(date.getTime())) {
                            return null;
                        }
                        return date;
                    } catch (e) {
                        console.error('Error parsing date:', e, dateStr);
                        return null;
                    }
                }
                
                function fmtTime(dateStr){ 
                    if(!dateStr) return '-'; 
                    try {
                        // Parse MySQL datetime properly - treat as local time (server timezone should match device timezone)
                        const d = parseMySQLDateTime(dateStr);
                        if (!d) {
                            // Fallback: try parsing with Date constructor
                            // If dateStr is in format "YYYY-MM-DD HH:MM:SS", create local date
                            const fallback = new Date(dateStr.replace(' ', 'T'));
                            if (isNaN(fallback.getTime())) {
                                console.error('Invalid date string:', dateStr);
                                return '-';
                            }
                            // Use the fallback date
                            const hours = fallback.getHours();
                            const minutes = fallback.getMinutes();
                            const seconds = fallback.getSeconds();
                            const ampm = hours >= 12 ? 'PM' : 'AM';
                            const displayHours = hours % 12 || 12;
                            return `${displayHours.toString().padStart(2, '0')}:${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')} ${ampm}`;
                        }
                        
                        // Format with explicit AM/PM - this should match device time if server timezone matches
                        const hours = d.getHours();
                        const minutes = d.getMinutes();
                        const seconds = d.getSeconds();
                        const ampm = hours >= 12 ? 'PM' : 'AM';
                        const displayHours = hours % 12 || 12; // Convert to 12-hour format (12:00 AM for midnight, 12:00 PM for noon)
                        
                        // Return formatted time: HH:MM:SS AM/PM
                        return `${displayHours.toString().padStart(2, '0')}:${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')} ${ampm}`;
                    } catch (e) {
                        console.error('Error formatting time:', e, dateStr);
                        return '-';
                    }
                }
                function fmtDate(dateStr){ 
                    if(!dateStr) return '-'; 
                    try {
                        const d = parseMySQLDateTime(dateStr);
                        if (!d) {
                            const fallback = new Date(dateStr);
                            if (isNaN(fallback.getTime())) return '-';
                            return fallback.toLocaleDateString('en-US', {year:'numeric', month:'short', day:'numeric'});
                        }
                        return d.toLocaleDateString('en-US', {year:'numeric', month:'short', day:'numeric'});
                    } catch (e) {
                        console.error('Error formatting date:', e, dateStr);
                        return '-';
                    }
                }
                function fetchList(){
                    const route = document.getElementById('bdRouteFilter')? document.getElementById('bdRouteFilter').value : '';
                    const params = new URLSearchParams({action:'list_for_driver'});
                    if(route) params.append('route', route);
                    fetch('../shared/reservations.php', {method:'POST', body: params})
                    .then(r=>r.json()).then(d=>{
                        const box = document.getElementById('bdList');
                        if(!d.success || !d.reservations || d.reservations.length===0){ box.textContent='No active reservations'; return; }
                        box.innerHTML = d.reservations.map(r=>{
                            const name = (r.firstName||'')+' '+(r.lastName||'');
                            const waitSec = r.here_at? Math.max(0, Math.floor((Date.now()-new Date(r.here_at).getTime())/1000)) : 0;
                            return `<div class='p-2 border rounded mb-2'>
                                <div class='d-flex justify-content-between'><strong>${name}</strong><span class='badge bg-secondary'>${r.status}</span></div>
                                <div class='small text-muted'>${r.origin_landmark||'-'} → ${r.dest_landmark||'-'}</div>
                                <div class='d-flex gap-2 mt-2'>
                                    <input type='number' class='form-control form-control-sm' style='max-width:120px' placeholder='ETA (min)' id='eta_${r.id}'>
                                    <button class='btn btn-outline-primary btn-sm' onclick='setEta(${r.id})'><i class="bi bi-clock"></i> Send ETA</button>
                                    <button class='btn btn-success btn-sm' onclick='confirmBoarded(${r.id})'><i class="bi bi-check2-circle"></i> Confirm Boarded</button>
                                    <span class='ms-auto small'>Wait: ${fmtWait(waitSec)}</span>
                                </div>
                            </div>`;
                        }).join('');
                    });
                }
                window.setEta = function(rid){
                    const v = document.getElementById('eta_'+rid).value||''; const m = parseInt(v||'0');
                    if(!m||m<=0){ Swal.fire({icon:'info', title:'Enter ETA minutes'}); return; }
                    fetch('../shared/reservations.php', {method:'POST', body:new URLSearchParams({action:'driver_set_eta', reservation_id:rid, eta_minutes:m})})
                    .then(r=>r.json()).then(d=>{
                        if(d && d.success){
                            Swal.fire({icon:'success', title:'ETA Sent', text:`ETA of ${m} minute(s) sent to passenger.`, timer: 2000, showConfirmButton: false});
                            fetchList();
                        } else {
                            Swal.fire({icon:'error', title:'Failed', text:(d&&d.message)||'Could not send ETA'});
                        }
                    }).catch(()=>{
                        Swal.fire({icon:'error', title:'Network Error', text:'Please try again.'});
                    });
                }
                window.bdApplyFilter = function(){ fetchList(); }
                window.confirmBoarded = function(rid){
                    fetch('../shared/reservations.php', {method:'POST', body:new URLSearchParams({action:'confirm_boarded', reservation_id:rid})})
                    .then(r=>r.json()).then(d=>{
                        Swal.fire({icon:'success', title:'Boarded', text: 'Wait time: '+ (d.wait_seconds? Math.round(d.wait_seconds/60)+' min':'-')});
                        fetchList(); 
                        fetchStats();
                        loadBoardingHistory(); // Refresh history table
                    });
                }
                
                // Boarding History Functions
                window.loadBoardingHistory = function(){
                    const showAll = document.getElementById('historyShowAll').checked;
                    const dateFilter = showAll ? 'all' : document.getElementById('historyDateFilter').value;
                    document.getElementById('historyLoading').style.display = 'block';
                    
                    // Disable date input when showing all
                    document.getElementById('historyDateFilter').disabled = showAll;
                    
                    // Update header to show current filter
                    const headerDateEl = document.getElementById('historyHeaderDate');
                    if (headerDateEl) {
                        if (showAll) {
                            headerDateEl.textContent = '(All Historical Records)';
                            headerDateEl.style.fontWeight = 'bold';
                        } else {
                            const dateObj = new Date(dateFilter);
                            const formattedDate = dateObj.toLocaleDateString('en-US', {year: 'numeric', month: 'short', day: 'numeric'});
                            headerDateEl.textContent = `(${formattedDate})`;
                            headerDateEl.style.fontWeight = 'normal';
                        }
                    }
                    
                    fetch('../shared/reservations.php', {
                        method:'POST', 
                        body:new URLSearchParams({action:'boarding_history', date: dateFilter})
                    })
                    .then(r=>r.json()).then(d=>{
                        document.getElementById('historyLoading').style.display = 'none';
                        const tbody = document.getElementById('historyTableBody');
                        
                        if(!d.success || !d.history || d.history.length===0){
                            const message = showAll ? 'No boarding records found in history' : 'No boarding records found for this date';
                            tbody.innerHTML = `<tr><td colspan="8" class="text-center text-muted">${message}</td></tr>`;
                            document.getElementById('historyCount').textContent = 'Total: 0 passengers';
                            document.getElementById('historyAvgWait').textContent = 'Average Wait: 0 min';
                            return;
                        }
                        
                        let totalWait = 0;
                        let validWaitCount = 0;
                        
                        tbody.innerHTML = d.history.map((h, idx)=>{
                            const name = (h.firstName||'') + ' ' + (h.lastName||'');
                            const waitSec = h.wait_seconds || 0;
                            if(waitSec > 0) {
                                totalWait += waitSec;
                                validWaitCount++;
                            }
                            const waitMin = Math.round(waitSec / 60);
                            const waitClass = waitSec > 600 ? 'text-danger' : (waitSec > 300 ? 'text-warning' : 'text-success');
                            
                            return `<tr>
                                <td>${idx + 1}</td>
                                <td><strong>${name}</strong><br><small class="text-muted">${h.email||'-'}</small></td>
                                <td class="small">${h.origin_landmark||'-'} <i class="bi bi-arrow-right"></i> ${h.dest_landmark||'-'}</td>
                                <td>${h.distance_km||'-'} km</td>
                                <td>${fmtTime(h.here_at)}</td>
                                <td>${fmtTime(h.boarded_at)}</td>
                                <td class="${waitClass}"><strong>${fmtWait(waitSec)}</strong></td>
                                <td>${fmtDate(h.boarding_date)}</td>
                            </tr>`;
                        }).join('');
                        
                        document.getElementById('historyCount').textContent = `Total: ${d.history.length} passenger${d.history.length !== 1 ? 's' : ''}`;
                        const avgWait = validWaitCount > 0 ? Math.round(totalWait / validWaitCount / 60) : 0;
                        document.getElementById('historyAvgWait').textContent = `Average Wait: ${avgWait} min`;
                    })
                    .catch(err => {
                        console.error('Error loading history:', err);
                        document.getElementById('historyLoading').style.display = 'none';
                    });
                }
                
                // Export history to CSV
                window.exportHistory = function(){
                    const showAll = document.getElementById('historyShowAll').checked;
                    const dateFilter = showAll ? 'all' : document.getElementById('historyDateFilter').value;
                    fetch('../shared/reservations.php', {
                        method:'POST', 
                        body:new URLSearchParams({action:'boarding_history', date: dateFilter})
                    })
                    .then(r=>r.json()).then(d=>{
                        if(!d.success || !d.history || d.history.length===0){
                            Swal.fire({icon:'info', title:'No Data', text:'No boarding records to export for this date'});
                            return;
                        }
                        
                        // Create CSV content
                        let csv = 'No,Passenger Name,Email,From,To,Distance (km),Started Waiting,Boarded Time,Wait Time (min),Date\n';
                        d.history.forEach((h, idx) => {
                            const name = (h.firstName||'') + ' ' + (h.lastName||'');
                            const waitMin = Math.round((h.wait_seconds || 0) / 60);
                            csv += `${idx+1},"${name}","${h.email||''}","${h.origin_landmark||''}","${h.dest_landmark||''}",${h.distance_km||''},"${fmtTime(h.here_at)}","${fmtTime(h.boarded_at)}",${waitMin},"${fmtDate(h.boarding_date)}"\n`;
                        });
                        
                        // Download CSV
                        const blob = new Blob([csv], { type: 'text/csv' });
                        const url = window.URL.createObjectURL(blob);
                        const a = document.createElement('a');
                        a.href = url;
                        const filename = showAll ? 'boarding_history_all.csv' : `boarding_history_${dateFilter}.csv`;
                        a.download = filename;
                        a.click();
                        window.URL.revokeObjectURL(url);
                        
                        Swal.fire({icon:'success', title:'Exported!', text:'Boarding history downloaded successfully'});
                    });
                }
                
                document.addEventListener('DOMContentLoaded', function(){ 
                    fetchStats(); 
                    fetchList(); 
                    // Set initial header date display
                    const initialDate = document.getElementById('historyDateFilter').value;
                    if (initialDate) {
                        const dateObj = new Date(initialDate + 'T00:00:00'); // Add time to avoid timezone issues
                        const formattedDate = dateObj.toLocaleDateString('en-US', {year: 'numeric', month: 'short', day: 'numeric'});
                        const headerDateEl = document.getElementById('historyHeaderDate');
                        if (headerDateEl) {
                            headerDateEl.textContent = `(${formattedDate})`;
                        }
                    }
                    
                    loadBoardingHistory(); // Load history on page load
                    setInterval(fetchStats, 12000); 
                    setInterval(fetchList, 6000);
                    
                    // Reload history when date changes (only if not showing all)
                    const dateFilterEl = document.getElementById('historyDateFilter');
                    if(dateFilterEl) {
                        dateFilterEl.addEventListener('change', function() {
                            if (!document.getElementById('historyShowAll').checked) {
                                loadBoardingHistory();
                            }
                        });
                    }
                });
                
                // App Boarding Counts by Time Period
                const plateNumber = <?= json_encode($assignedJeepney['plate_number'] ?? '') ?>;
                const route = <?= json_encode($assignedJeepney['route'] ?? '') ?>;
                
                function buildRange(start, end){
                    const d = document.getElementById('vmDate').value || '<?= date('Y-m-d') ?>';
                    return {
                        start: d + ' ' + start + ':00',
                        end: d + ' ' + end + ':00'
                    };
                }
                
                function vmLoadPeriod(start, end){
                    const showAll = document.getElementById('vmShowAll')?.checked || false;
                    if (showAll) {
                        // Load all periods with show_all flag
                        fetch('../shared/reservations.php', {
                            method:'POST', 
                            body: new URLSearchParams({action:'app_count', show_all: 'true'})
                        })
                        .then(r=>r.json()).then(d=>{
                            const tb = document.getElementById('vmRows');
                            if(d && d.success){
                                const count = d.count || 0;
                                // For "All Time", visibility is N/A or could be calculated if we have total data
                                tb.innerHTML = `<tr>
                                    <td><strong>All Time</strong></td>
                                    <td>${plateNumber || '-'}</td>
                                    <td>${route || '-'}</td>
                                    <td class="text-end"><strong class="text-primary">${count}</strong></td>
                                    <td class="text-end"><strong class="text-muted">N/A</strong></td>
                                </tr>`;
                                document.getElementById('vmTotal').textContent = `Total Boarded: ${count}`;
                            } else {
                                tb.innerHTML = '<tr><td colspan="5" class="text-center text-muted">No boarding records</td></tr>';
                                document.getElementById('vmTotal').textContent = 'Total Boarded: 0';
                            }
                        });
                        return;
                    }
                    
                    const range = buildRange(start, end);
                    fetch('../shared/reservations.php', {method:'POST', body: new URLSearchParams({action:'app_count', start_time: range.start, end_time: range.end})})
                        .then(r=>r.json()).then(async d=>{
                            const tb = document.getElementById('vmRows');
                            if(d && d.success){
                                const count = d.count || 0;
                                
                                // Fetch compliance data to calculate visibility
                                const date = document.getElementById('vmDate').value || '<?= date('Y-m-d') ?>';
                                try {
                                    const complianceRes = await fetch('pay_fare.php', {
                                        method:'POST',
                                        headers:{'Content-Type':'application/json'}, 
                                        body: JSON.stringify({
                                            action:'count', 
                                            route: route, 
                                            start_time: range.start, 
                                            end_time: range.end
                                        })
                                    });
                                    const complianceData = await complianceRes.json();
                                    
                                    const totalTrips = complianceData && complianceData.success ? (complianceData.total || 0) : 0;
                                    let visibilityPercent = 'N/A';
                                    
                                    if (totalTrips > 0) {
                                        // Calculate percentage and cap at 100%
                                        const percent = (count / totalTrips) * 100;
                                        visibilityPercent = Math.min(percent, 100).toFixed(1) + '%';
                                    } else if (count > 0) {
                                        visibilityPercent = '100.0%';
                                    } else {
                                        visibilityPercent = '0.0%';
                                    }
                                    
                                    const visibilityClass = visibilityPercent !== 'N/A' && parseFloat(visibilityPercent) >= 80 ? 'text-success' : 
                                                           visibilityPercent !== 'N/A' && parseFloat(visibilityPercent) >= 50 ? 'text-warning' : 
                                                           visibilityPercent === 'N/A' ? 'text-muted' : 'text-danger';
                                    
                                    tb.innerHTML = `<tr>
                                        <td><strong>${start} - ${end}</strong></td>
                                        <td>${plateNumber || '-'}</td>
                                        <td>${route || '-'}</td>
                                        <td class="text-end"><strong class="text-primary">${count}</strong></td>
                                        <td class="text-end"><strong class="${visibilityClass}">${visibilityPercent}</strong></td>
                                    </tr>`;
                                } catch (err) {
                                    // If compliance fetch fails, show count without visibility
                                    tb.innerHTML = `<tr>
                                        <td><strong>${start} - ${end}</strong></td>
                                        <td>${plateNumber || '-'}</td>
                                        <td>${route || '-'}</td>
                                        <td class="text-end"><strong class="text-primary">${count}</strong></td>
                                        <td class="text-end"><strong class="text-muted">N/A</strong></td>
                                    </tr>`;
                                }
                                document.getElementById('vmTotal').textContent = `Total Boarded: ${count}`;
                            } else {
                                tb.innerHTML = '<tr><td colspan="5" class="text-center text-muted">No boarding records for this period</td></tr>';
                                document.getElementById('vmTotal').textContent = 'Total Boarded: 0';
                            }
                        });
                }
                
                function vmLoadAll(){
                    const showAll = document.getElementById('vmShowAll')?.checked || false;
                    
                    // Disable date input when showing all
                    if (document.getElementById('vmDate')) {
                        document.getElementById('vmDate').disabled = showAll;
                    }
                    
                    // Update header to show current filter
                    const headerDateEl = document.getElementById('vmHeaderDate');
                    if (headerDateEl) {
                        if (showAll) {
                            headerDateEl.textContent = '(All Historical Records)';
                            headerDateEl.style.fontWeight = 'bold';
                        } else {
                            const dateVal = document.getElementById('vmDate').value;
                            if (dateVal) {
                                const dateObj = new Date(dateVal);
                                const formattedDate = dateObj.toLocaleDateString('en-US', {year: 'numeric', month: 'short', day: 'numeric'});
                                headerDateEl.textContent = `(${formattedDate})`;
                                headerDateEl.style.fontWeight = 'normal';
                            } else {
                                headerDateEl.textContent = '';
                            }
                        }
                    }
                    
                    if (showAll) {
                        // Load all time periods combined - show total count of all historical records
                        fetch('../shared/reservations.php', {
                            method:'POST', 
                            body: new URLSearchParams({action:'app_count', show_all: 'true'})
                        })
                        .then(r=>r.json()).then(d=>{
                            const tb = document.getElementById('vmRows');
                            if(d && d.success){
                                const count = d.count || 0;
                                tb.innerHTML = `<tr>
                                    <td><strong>All Historical Records</strong></td>
                                    <td>${plateNumber || '-'}</td>
                                    <td>${route || '-'}</td>
                                    <td class="text-end"><strong class="text-primary">${count}</strong></td>
                                    <td class="text-end"><strong class="text-muted">N/A</strong></td>
                                </tr>`;
                                document.getElementById('vmTotal').textContent = `Total Boarded (All Time): ${count}`;
                            } else {
                                tb.innerHTML = '<tr><td colspan="5" class="text-center text-muted">No boarding records found</td></tr>';
                                document.getElementById('vmTotal').textContent = 'Total Boarded: 0';
                            }
                        });
                        return;
                    }
                    
                    const periods = [
                        {start: '07:00', end: '08:00', label: '7–8 AM'},
                        {start: '08:00', end: '10:00', label: '8–10 AM'},
                        {start: '17:00', end: '19:00', label: '5–7 PM'}
                    ];
                    
                    // Fetch both app counts and compliance data for each period to calculate visibility
                    const promises = periods.map(p => {
                        const range = buildRange(p.start, p.end);
                        const date = document.getElementById('vmDate').value || '<?= date('Y-m-d') ?>';
                        
                        // Fetch app count
                        const appCountPromise = fetch('../shared/reservations.php', {
                            method:'POST', 
                            body: new URLSearchParams({action:'app_count', start_time: range.start, end_time: range.end})
                        }).then(r=>r.json()).then(d=> (d && d.success) ? (d.count || 0) : 0);
                        
                        // Fetch compliance data (total trips and collected fares) for visibility calculation
                        const compliancePromise = fetch('pay_fare.php', {
                            method:'POST',
                            headers:{'Content-Type':'application/json'}, 
                            body: JSON.stringify({
                                action:'count', 
                                route: route, 
                                start_time: range.start, 
                                end_time: range.end
                            })
                        }).then(r=>r.json()).then(d=> {
                            if(d && d.success) {
                                return {
                                    total: d.total || 0,
                                    collected: d.compliant || 0,
                                    rate: d.rate || 0
                                };
                            }
                            return {total: 0, collected: 0, rate: 0};
                        });
                        
                        // Wait for both requests
                        return Promise.all([appCountPromise, compliancePromise]).then(([appCount, compliance]) => ({
                            period: p,
                            count: appCount,
                            total: compliance.total,
                            collected: compliance.collected,
                            complianceRate: compliance.rate
                        }));
                    });
                    
                    Promise.all(promises).then(results => {
                        const tb = document.getElementById('vmRows');
                        let total = 0;
                        let totalAppTrips = 0;
                        
                        const rows = results.map(r => {
                            total += r.count;
                            totalAppTrips += r.total;
                            
                            // Calculate visibility percent: (App Boardings / Total App Trips) * 100
                            // Cap at 100% maximum - visibility cannot exceed 100%
                            // If total app trips is 0 but we have app boardings, visibility is 100% (all visible)
                            // If total app trips is 0 and no app boardings, visibility is 0%
                            let visibilityPercent = null;
                            if (r.total > 0) {
                                // Calculate percentage and cap at 100%
                                const percent = (r.count / r.total) * 100;
                                visibilityPercent = Math.min(percent, 100).toFixed(1);
                            } else if (r.count > 0) {
                                // If we have app boardings but no total trips recorded, assume 100% visibility
                                visibilityPercent = '100.0';
                            } else {
                                visibilityPercent = '0.0';
                            }
                            
                            // Only show rows with data (app boardings > 0 or total trips > 0)
                            if(r.count === 0 && r.total === 0) return '';
                            
                            const visibilityClass = visibilityPercent && parseFloat(visibilityPercent) >= 80 ? 'text-success' : 
                                                   visibilityPercent && parseFloat(visibilityPercent) >= 50 ? 'text-warning' : 'text-danger';
                            
                            return `<tr>
                                <td><strong>${r.period.label}</strong></td>
                                <td>${plateNumber || '-'}</td>
                                <td>${route || '-'}</td>
                                <td class="text-end"><strong class="text-primary">${r.count}</strong></td>
                                <td class="text-end"><strong class="${visibilityClass}">${visibilityPercent !== null ? visibilityPercent + '%' : 'N/A'}</strong></td>
                            </tr>`;
                        }).filter(r => r !== '').join('');
                        
                        if(rows === ''){
                            tb.innerHTML = '<tr><td colspan="5" class="text-center text-muted">No boarding records for today</td></tr>';
                        } else {
                            tb.innerHTML = rows;
                        }
                        document.getElementById('vmTotal').textContent = `Total Boarded: ${total}`;
                    }).catch(err => {
                        console.error('Error loading time period data:', err);
                        document.getElementById('vmRows').innerHTML = '<tr><td colspan="5" class="text-center text-danger">Error loading data</td></tr>';
                    });
                }
                
                const btnRefresh = document.getElementById('vmRefresh');
                if(btnRefresh) btnRefresh.addEventListener('click', vmLoadAll);
                
                // Load all periods on page load
                const vmDateEl = document.getElementById('vmDate');
                if(vmDateEl) {
                    vmDateEl.addEventListener('change', function() {
                        if (!document.getElementById('vmShowAll')?.checked) {
                            vmLoadAll();
                        }
                    });
                    vmLoadAll(); // Initial load
                }
                
                window.vmLoadPeriod = vmLoadPeriod;
                window.vmLoadAll = vmLoadAll;
            })();
            </script>
            </div>
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
                                <p class="mb-1"><strong>Operator:</strong> <?= isset($assignedJeepney['operator_name']) && $assignedJeepney['operator_name'] ? htmlspecialchars($assignedJeepney['operator_name']) : 'Unknown' ?></p>
                                <p class="mb-1"><strong>Jeepney:</strong> <?= htmlspecialchars($assignedJeepney['plate_number'] ?? 'N/A') ?></p>
                                <p class="mb-0"><strong>Route:</strong> <?= htmlspecialchars($assignedJeepney['route'] ?? 'N/A') ?></p>
                            </div>
                            <!-- Pending Boundary Payments Table -->
                            <div id="pendingBoundariesContainer"></div>
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
                                        <option value="Bank">Bank</option>
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
                            <div id="boundaryReceipt" class="mt-4"></div>
                        </div>
                    </div>
                </div>
            </div>
            <script>
            function fetchPendingBoundaries() {
                fetch('pay_boundary.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'list', operator_id: <?= json_encode($userId) ?> })
                })
                .then(res => res.json())
                .then(data => {
                    if (data.success && data.boundaries) {
                        const pending = data.boundaries.filter(b => b.status === 'Pending');
                        let html = '';
                        if (pending.length > 0) {
                            html += `<div class='table-responsive'><table class='table table-sm table-bordered'><thead><tr><th>Date</th><th>Amount</th><th>Method</th><th>Status</th></tr></thead><tbody>`;
                            pending.forEach(b => {
                                html += `<tr><td>${b.paid_at}</td><td>₱${b.amount}</td><td>${b.payment_method}</td><td><span class='badge bg-warning text-dark'>Pending</span></td></tr>`;
                            });
                            html += '</tbody></table></div>';
                        }
                        document.getElementById('pendingBoundariesContainer').innerHTML = html;
                    } else {
                        document.getElementById('pendingBoundariesContainer').innerHTML = '';
                    }
                })
                .catch(() => {
                    document.getElementById('pendingBoundariesContainer').innerHTML = `<div class='alert alert-danger text-center mb-0'>Failed to load pending boundaries.</div>`;
                });
            }
            document.addEventListener('DOMContentLoaded', fetchPendingBoundaries);
            if (typeof setInterval !== 'undefined') setInterval(fetchPendingBoundaries, 30000);

            document.getElementById('boundaryForm').addEventListener('submit', function(e) {
                e.preventDefault();
                const amount = document.getElementById('boundaryAmount').value;
                const payment_method = document.getElementById('boundaryMethod').value;
                const notes = document.getElementById('boundaryNotes').value;
                const driver_id = <?= json_encode($userId) ?>;
                const operator_id = <?= json_encode($assignedJeepney['operator_id'] ?? '') ?>;
                const jeepney_id = <?= json_encode($assignedJeepney['id'] ?? '') ?>;
                // Defensive check for operator_id and jeepney_id
                if (!operator_id || operator_id === '' || operator_id === 'null' || !jeepney_id || jeepney_id === '' || jeepney_id === 'null') {
                    console.error('Missing operator_id or jeepney_id:', { operator_id, jeepney_id, assignedJeepney: <?= json_encode($assignedJeepney) ?> });
                    Swal.fire({
                        icon: 'error',
                        title: 'No Assignment',
                        text: 'You must be assigned to a jeepney and operator before paying boundary. Please contact your operator.',
                        confirmButtonColor: '#d33'
                    });
                    document.getElementById('boundaryReceipt').innerHTML = `
                        <div class='alert alert-danger'>
                            <i class="bi bi-exclamation-triangle me-2"></i>
                            <strong>Error:</strong> No operator or jeepney assigned. Please contact your operator to get assigned to a jeepney.<br>
                            <small class='text-danger'>Debug: operator_id=${operator_id}, jeepney_id=${jeepney_id}</small>
                        </div>
                    `;
                    return;
                }
                const submitBtn = this.querySelector('button[type="submit"]');
                const originalText = submitBtn.innerHTML;
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Processing...';
                fetch('pay_boundary.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ driver_id, operator_id, jeepney_id, amount, payment_method, notes })
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
                                <small class="text-muted">✅ Your payment has been submitted successfully! The operator has been notified and will confirm your payment soon.</small>
                            </div>
                        `;
                        document.getElementById('boundaryReceipt').innerHTML = receiptHtml;
                        Swal.fire({
                            icon: 'success',
                            title: 'Payment Submitted!',
                            text: 'Your boundary payment has been successfully submitted and the operator has been notified.',
                            confirmButtonColor: '#198754'
                        });
                        this.reset();
                        document.getElementById('boundaryAmount').value = '500';
                        fetchPendingBoundaries();
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
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = originalText;
                });
            });

            document.getElementById('boundaryForm').addEventListener('submit', function(e) {
                const ref = document.getElementById('boundaryReference').value.trim();
                if (!ref) {
                    e.preventDefault();
                    Swal.fire({
                        icon: 'error',
                        title: 'Reference Required',
                        text: 'Please enter a valid reference number.'
                    });
                    return false;
                }
            });
            </script>
        <?php endif; ?>

            </div><!-- End .content -->
        </div><!-- End .content-container -->
    </div><!-- End .content-wrapper -->

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
        backdrop: `            rgba(0,0,0,0.7)
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

<!-- Enhanced Styles for Driver Dashboard -->
<style>
/* Content wrapper and container overrides - match passenger dashboard */
.content-wrapper { 
    background: transparent; 
    position: relative; 
    z-index: 1; 
}

/* Header centering to match content below */
.content-header {
    display: flex !important;
    justify-content: center !important;
}

.content-header > div {
    max-width: 1200px;
    width: 100%;
}

.content-container { 
    background: transparent; 
    position: relative; 
    z-index: 1;
    display: flex !important;
    justify-content: center !important;
    align-items: flex-start !important;
    padding: 20px !important;
    width: 100%;
}

.content { 
    max-width: 1200px; 
    width: 100% !important;
    margin: 0 auto !important; 
    position: relative; 
    z-index: 1;
    display: flex;
    flex-direction: column;
    align-items: center;
}

/* Page containers - single, clean background for all sections */
.content.dashboard-page,
.content.fares-page,
.content.boarding-page,
.content.profile-page,
.content.payment-page,
.content.assignment-page,
.content.boundary-page {
    background: transparent !important;
    min-height: 100%;
    padding: 1rem;
    border-radius: 16px;
    box-shadow: none;
    border: none;
    margin: 0 auto !important;
    text-align: left;
    display: flex !important;
    flex-direction: column !important;
    align-items: center !important;
}

/* Center all cards and containers */
.card,
.profile-card,
.compact-card,
.dashboard-section,
.payment-section,
.content > .card,
.content > div > .card,
.content .card-container,
.content > .row,
.content > .container-fluid {
    margin-left: auto !important;
    margin-right: auto !important;
    width: 100%;
    max-width: 1200px;
}

/* Ensure all direct children of content are properly sized and centered */
.content > * {
    width: 100%;
    max-width: 1200px;
    margin-left: auto;
    margin-right: auto;
}

/* Specific centering for dashboard elements */
.welcome-hero,
.quick-action-card,
.content > .row {
    width: 100%;
    max-width: 1200px;
}

/* Center form containers */
.payment-form,
.profile-card,
form {
    margin-left: auto;
    margin-right: auto;
}

/* Center assignment page and boarding page containers */
.assignment-container,
.boarding-container,
.container-fluid.px-4 {
    max-width: 1200px;
    margin-left: auto !important;
    margin-right: auto !important;
    padding-left: 1rem;
    padding-right: 1rem;
}

.boarding-container .row {
    margin-left: 0;
    margin-right: 0;
}

/* Content container scrollbar styling */
.content-container::-webkit-scrollbar {
    width: 8px;
}

.content-container::-webkit-scrollbar-track {
    background: rgba(255, 255, 255, 0.1);
    border-radius: 10px;
}

.content-container::-webkit-scrollbar-thumb {
    background: linear-gradient(135deg, #667eea, #764ba2);
    border-radius: 10px;
}

.content-container::-webkit-scrollbar-thumb:hover {
    background: linear-gradient(135deg, #5a6fd8, #6a4190);
}

/* Desktop Layout */
@media (min-width: 768px) {
    .content-wrapper {
        margin-left: 280px !important;
        width: calc(100% - 280px) !important;
        min-height: 100vh !important;
        background: #f7f9fc !important;
    }
    
    .content-header {
        background: #ffffff !important;
        border-bottom: 1px solid rgba(0,0,0,0.05) !important;
        box-shadow: 0 2px 8px rgba(0,0,0,0.05) !important;
        display: flex !important;
        justify-content: center !important;
        padding: 0 !important;
    }
    
    .content-header > div {
        max-width: 1200px !important;
        width: 100% !important;
        padding: 1rem 1.5rem !important;
    }
    
    .content-container {
        height: calc(100vh - 80px) !important;
        overflow-y: auto !important;
        padding: 1.5rem !important;
    }
}

/* ============================================
   UNIQUE DRIVER DASHBOARD STYLES
   ============================================ */

/* Welcome Hero Section */
.welcome-hero {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border-radius: 20px;
    padding: 2rem;
    color: white;
    box-shadow: 0 10px 30px rgba(102, 126, 234, 0.3);
    position: relative;
    overflow: hidden;
}

.welcome-hero::before {
    content: '';
    position: absolute;
    top: -50%;
    right: -10%;
    width: 300px;
    height: 300px;
    background: rgba(255, 255, 255, 0.1);
    border-radius: 50%;
}

.welcome-hero::after {
    content: '';
    position: absolute;
    bottom: -30%;
    left: -5%;
    width: 200px;
    height: 200px;
    background: rgba(255, 255, 255, 0.05);
    border-radius: 50%;
}

.hero-avatar {
    position: relative;
    z-index: 2;
}

.status-badge {
    position: absolute;
    bottom: 10px;
    right: 10px;
    background: white;
    width: 30px;
    height: 30px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.2);
}

.status-badge i {
    font-size: 12px;
}

.hero-content {
    position: relative;
    z-index: 2;
}

.hero-content h1 {
    color: white;
    text-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
}

.quick-stats {
    display: flex;
    gap: 2rem;
    flex-wrap: wrap;
}

.stat-item {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    background: rgba(255, 255, 255, 0.15);
    padding: 0.5rem 1rem;
    border-radius: 10px;
    backdrop-filter: blur(10px);
}

.stat-item i {
    font-size: 1.2rem;
}

.stat-item span {
    font-weight: 500;
}

.bg-gradient-primary {
    background: linear-gradient(135deg, #4f46e5, #7c3aed) !important;
}

/* Quick Action Cards */
.quick-action-card {
    display: block;
    background: white;
    border-radius: 16px;
    padding: 1.5rem;
    text-decoration: none;
    color: inherit;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    position: relative;
    overflow: hidden;
    border: 1px solid rgba(0, 0, 0, 0.05);
}

.quick-action-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 4px;
    background: linear-gradient(90deg, #667eea, #764ba2);
    transform: scaleX(0);
    transition: transform 0.3s ease;
}

.quick-action-card:hover::before {
    transform: scaleX(1);
}

.quick-action-card:hover {
    transform: translateY(-8px);
    box-shadow: 0 12px 28px rgba(0, 0, 0, 0.15);
    color: inherit;
}

.quick-action-card .icon-wrapper {
    width: 60px;
    height: 60px;
    border-radius: 14px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-bottom: 1rem;
    transition: transform 0.3s ease;
}

.quick-action-card:hover .icon-wrapper {
    transform: scale(1.1) rotate(5deg);
}

.quick-action-card .icon-wrapper i {
    font-size: 1.8rem;
    color: white;
}

.bg-gradient-success {
    background: linear-gradient(135deg, #16a34a, #22c55e);
}

.bg-gradient-warning {
    background: linear-gradient(135deg, #f59e0b, #f97316);
}

.bg-gradient-info {
    background: linear-gradient(135deg, #06b6d4, #0ea5e9);
}

.bg-gradient-danger {
    background: linear-gradient(135deg, #dc2626, #ef4444);
}

.quick-action-card h6 {
    font-size: 1.1rem;
    font-weight: 700;
    margin-bottom: 0.25rem;
    color: #1f2937;
}

.quick-action-card p {
    font-size: 0.875rem;
    color: #6b7280;
    margin-bottom: 0;
}

.quick-action-card .arrow {
    position: absolute;
    bottom: 1rem;
    right: 1rem;
    opacity: 0;
    transform: translateX(-10px);
    transition: all 0.3s ease;
}

.quick-action-card:hover .arrow {
    opacity: 1;
    transform: translateX(0);
}

.quick-action-card .arrow i {
    font-size: 1.5rem;
    color: #9ca3af;
}

/* Enhanced Schedule Cards */
.card.shadow-sm {
    border: none;
    border-radius: 16px;
    overflow: hidden;
    transition: all 0.3s ease;
}

.card.shadow-sm:hover {
    transform: translateY(-4px);
    box-shadow: 0 12px 24px rgba(0, 0, 0, 0.12) !important;
}

.card-header.bg-primary,
.card-header.bg-success {
    border: none;
    font-weight: 600;
    padding: 1.25rem 1.5rem;
}

.card-header.bg-primary {
    background: linear-gradient(135deg, #2563eb, #3b82f6) !important;
}

.card-header.bg-success {
    background: linear-gradient(135deg, #16a34a, #22c55e) !important;
}

/* Responsive Design */
@media (max-width: 991px) {
    .welcome-hero {
        padding: 1.5rem;
    }
    
    .hero-content h1 {
        font-size: 1.75rem;
    }
    
    .quick-stats {
        gap: 1rem;
    }
}

@media (max-width: 767px) {
    .welcome-hero {
        padding: 1.25rem;
        text-align: center;
    }
    
    .welcome-hero .row {
        flex-direction: column;
    }
    
    .hero-avatar {
        margin-bottom: 1.5rem;
    }
    
    .hero-content h1 {
        font-size: 1.5rem;
    }
    
    .quick-stats {
        justify-content: center;
        gap: 0.75rem;
    }
    
    .stat-item {
        padding: 0.4rem 0.8rem;
        font-size: 0.875rem;
    }
    
    .quick-action-card {
        padding: 1.25rem;
    }
    
    .quick-action-card .icon-wrapper {
        width: 50px;
        height: 50px;
    }
    
    .quick-action-card .icon-wrapper i {
        font-size: 1.5rem;
    }
    
    .quick-action-card h6 {
        font-size: 1rem;
    }
    
    .quick-action-card p {
        font-size: 0.8rem;
    }
}

@media (max-width: 575px) {
    .welcome-hero {
        padding: 1rem;
    }
    
    .hero-avatar img {
        width: 100px !important;
        height: 100px !important;
    }
    
    .hero-content h1 {
        font-size: 1.25rem;
    }
    
    .quick-stats {
        flex-direction: column;
        gap: 0.5rem;
    }
    
    .stat-item {
        width: 100%;
        justify-content: center;
    }
    
    .row.g-3 {
        --bs-gutter-x: 0.75rem;
        --bs-gutter-y: 0.75rem;
    }
}

/* Additional responsive centering */
@media (max-width: 1400px) {
    .content,
    .content > * {
        max-width: 1140px;
    }
}

@media (max-width: 1200px) {
    .content,
    .content > * {
        max-width: 960px;
    }
    
    .content-container {
        padding: 15px !important;
    }
}

@media (max-width: 991px) {
    .content,
    .content > * {
        max-width: 720px;
    }
    
    .content-container {
        padding: 12px !important;
    }
}

@media (max-width: 767px) {
    .content,
    .content > *,
    .card,
    .content > .row,
    .content > .container-fluid {
        max-width: 100%;
        padding-left: 10px;
        padding-right: 10px;
    }
    
    .content-container {
        padding: 10px !important;
    }
    
    .content.dashboard-page,
    .content.fares-page,
    .content.boarding-page,
    .content.profile-page,
    .content.payment-page,
    .content.assignment-page,
    .content.boundary-page {
        padding: 0.5rem;
    }
}

@media (max-width: 575px) {
    .content-container {
        padding: 8px !important;
    }
    
    .content {
        padding: 0.25rem !important;
    }
}
</style>

<!-- Sidebar Toggle Script -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    const sidebar = document.getElementById('sidebar');
    const sidebarToggle = document.getElementById('sidebarToggle');
    const contentWrapper = document.querySelector('.content-wrapper');
    const content = document.querySelector('.content');
    const toggleIcon = sidebarToggle.querySelector('i');
    
    // Load sidebar state from localStorage
    const sidebarHidden = localStorage.getItem('sidebarHidden') === 'true';
    
    if (sidebarHidden) {
        sidebar.classList.add('hidden');
        sidebarToggle.classList.add('sidebar-hidden');
        if (contentWrapper) contentWrapper.classList.add('sidebar-hidden');
        if (content) content.classList.add('sidebar-hidden');
        toggleIcon.className = 'bi bi-list';
    }
    
    // Toggle sidebar
    sidebarToggle.addEventListener('click', function() {
        const isHidden = sidebar.classList.toggle('hidden');
        sidebarToggle.classList.toggle('sidebar-hidden');
        if (contentWrapper) contentWrapper.classList.toggle('sidebar-hidden');
        if (content) content.classList.toggle('sidebar-hidden');
        
        // Change icon
        if (isHidden) {
            toggleIcon.className = 'bi bi-list';
        } else {
            toggleIcon.className = 'bi bi-x-lg';
        }
        
        // Save state to localStorage
        localStorage.setItem('sidebarHidden', isHidden);
    });
});
</script>

<!-- Mobile Responsive CSS and Script -->
<style>
/* Mobile: Hide sidebar, show top navbar */
@media (max-width: 767px) {
    .navbar.visible-xs {
        display: block !important;
        position: fixed !important;
        top: 0 !important;
        left: 0 !important;
        right: 0 !important;
        z-index: 1030 !important;
        box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    }
    
    .sidebar {
        display: none !important;
    }
    
    .sidebar-toggle {
        display: none !important;
    }
    
    .content-wrapper {
        margin-left: 0 !important;
        width: 100% !important;
        padding-top: 60px !important;
    }
    
    .content {
        margin-left: 0 !important;
        width: 100% !important;
        padding: 15px !important;
    }
    
    .content-header {
        margin-top: 60px;
        padding: 0 !important;
    }
    
    .content-header > div {
        padding: 1rem 1.5rem !important;
    }
    
    .content-header img {
        height: 32px !important;
    }
    
    .content-header h4 {
        font-size: 1.2rem;
    }
    
    .content-container {
        padding: 15px !important;
    }
    
    /* Ensure content centering on mobile */
    .content {
        padding: 0.5rem !important;
    }
    
    .content > * {
        width: 100% !important;
    }
    
    .card {
        margin-bottom: 1rem !important;
    }
    
    .navbar-collapse {
        max-height: 0;
        overflow: hidden;
        transition: max-height 0.3s ease;
        display: none;
    }
    
    .navbar-collapse.in {
        max-height: 500px !important;
        overflow-y: auto !important;
        display: block !important;
    }
    
    .navbar-nav li {
        border-bottom: 1px solid rgba(255,255,255,0.08);
        list-style: none;
    }
    
    .navbar-nav li:last-child {
        border-bottom: none;
    }
    
    .navbar-nav li a {
        display: flex !important;
        align-items: center !important;
        transition: all 0.3s ease !important;
        position: relative;
    }
    
    .navbar-nav li a:hover {
        background: rgba(255,255,255,0.15) !important;
        padding-left: 25px !important;
    }
    
    .navbar-nav li.active a {
        background: rgba(255,255,255,0.2) !important;
        font-weight: 600 !important;
        border-left: 4px solid white;
    }
    
    .navbar-nav li.active a::before {
        content: '';
        position: absolute;
        left: 0;
        top: 0;
        bottom: 0;
        width: 4px;
        background: white;
    }
    
    .navbar-nav li a i {
        opacity: 0.9;
    }
    
    .navbar-nav li.active a i,
    .navbar-nav li a:hover i {
        opacity: 1;
        transform: scale(1.1);
    }
}

/* Desktop: Show sidebar, hide mobile navbar */
@media (min-width: 768px) {
    .navbar.visible-xs {
        display: none !important;
    }
    
    .sidebar {
        display: block !important;
    }
    
    .content-header {
        padding: 0 !important;
    }
    
    .content-header > div {
        padding: 1rem 1.5rem;
    }
    
    /* Ensure proper content centering on tablets and up */
    .content {
        padding: 1rem !important;
    }
    
    .content > * {
        width: 100%;
    }
}

/* Tablet and larger - ensure proper header padding */
@media (min-width: 992px) {
    .content-header > div {
        padding: 1rem 2rem;
    }
    
    /* Better spacing on larger screens */
    .content {
        padding: 1.5rem !important;
    }
    
    .card {
        margin-bottom: 1.5rem;
    }
}
</style>

<script>
// Mobile navbar toggle (vanilla JavaScript - no jQuery needed)
document.addEventListener('DOMContentLoaded', function() {
    console.log('🔍 Searching for mobile navbar elements...');
    
    const navbarToggle = document.querySelector('.navbar-toggle');
    const navbarCollapse = document.getElementById('myNavbar');
    const navLinks = document.querySelectorAll('.navbar-nav li a');
    
    console.log('📱 Navbar toggle button:', navbarToggle);
    console.log('📋 Navbar collapse div:', navbarCollapse);
    console.log('🔗 Nav links found:', navLinks.length);
    
    // Toggle menu when clicking hamburger button
    if (navbarToggle && navbarCollapse) {
        navbarToggle.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            console.log('🍔 Hamburger clicked!');
            console.log('Current state - has "in" class:', navbarCollapse.classList.contains('in'));
            
            if (navbarCollapse.classList.contains('in')) {
                console.log('❌ Closing menu...');
                navbarCollapse.classList.remove('in');
                navbarCollapse.style.maxHeight = '0';
                navbarCollapse.style.display = 'none';
            } else {
                console.log('✅ Opening menu...');
                navbarCollapse.classList.add('in');
                navbarCollapse.style.maxHeight = '500px';
                navbarCollapse.style.display = 'block';
            }
            
            console.log('After click - maxHeight:', navbarCollapse.style.maxHeight);
            console.log('After click - display:', navbarCollapse.style.display);
        });
        
        console.log('✅ Click listener attached to hamburger button');
    } else {
        console.error('❌ Could not find navbar elements!');
        console.error('Toggle button:', navbarToggle);
        console.error('Collapse div:', navbarCollapse);
    }
    
    // Close menu when clicking a link
    navLinks.forEach(link => {
        link.addEventListener('click', function() {
            console.log('🔗 Link clicked, closing menu');
            if (navbarCollapse) {
                navbarCollapse.classList.remove('in');
                navbarCollapse.style.maxHeight = '0';
                navbarCollapse.style.display = 'none';
            }
        });
    });
    
    // Close menu when clicking outside
    document.addEventListener('click', function(e) {
        if (!e.target.closest('.navbar') && navbarCollapse && navbarCollapse.classList.contains('in')) {
            console.log('👆 Clicked outside, closing menu');
            navbarCollapse.classList.remove('in');
            navbarCollapse.style.maxHeight = '0';
            navbarCollapse.style.display = 'none';
        }
    });
    
    console.log('✅ Mobile navbar script initialized!');
});
</script>

</body>
</html>

