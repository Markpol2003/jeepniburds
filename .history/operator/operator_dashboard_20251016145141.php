<?php
session_start();
require_once __DIR__ . '/../db_config.php';

// Get available jeepneys
$available_jeepneys_query = "SELECT * FROM jeepneys WHERE status = 'Available' ORDER BY plate_number";
$available_jeepneys = $conn->query($available_jeepneys_query);

// Create jeepneys table if it doesn't exist
$sql = "CREATE TABLE IF NOT EXISTS jeepneys (
    id INT PRIMARY KEY AUTO_INCREMENT,
    plate_number VARCHAR(20) NOT NULL UNIQUE,
    body_number VARCHAR(20) NOT NULL UNIQUE,
    route VARCHAR(100) NOT NULL,
    status ENUM('Available', 'Assigned', 'Maintenance') DEFAULT 'Available',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";

$conn->query($sql);

// Create jeepney_assignments table if it doesn't exist
$sql = "CREATE TABLE IF NOT EXISTS jeepney_assignments (
    id INT PRIMARY KEY AUTO_INCREMENT,
    driver_id INT NOT NULL,
    operator_id INT NOT NULL,
    plate_number VARCHAR(20) NOT NULL,
    body_number VARCHAR(20) NOT NULL,
    route VARCHAR(100) NOT NULL,
    notes TEXT,
    assigned_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    status ENUM('Active', 'Inactive') DEFAULT 'Active',
    FOREIGN KEY (driver_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (operator_id) REFERENCES users(id) ON DELETE CASCADE
)";

$conn->query($sql);

// Add new cooperative fund payment table if not exists
$sql = "CREATE TABLE IF NOT EXISTS cooperative_fund_payments (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    method VARCHAR(20) NOT NULL,
    reference_number VARCHAR(100),
    receipt_number VARCHAR(50),
    status ENUM('Pending', 'Confirmed', 'Rejected') DEFAULT 'Pending',
    payment_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    firstName VARCHAR(100),
    lastName VARCHAR(100),
    userType VARCHAR(50),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
)";
$conn->query($sql);

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'operator') {
    header("Location: ../shared/landing.php");
    exit();
}

$userId = $_SESSION['user_id'];
$userType = ucfirst($_SESSION['user_type']);
$userFirstName = $_SESSION['user_firstName'] ?? '';
$userLastName = $_SESSION['user_lastName'] ?? '';
$userEmail = $_SESSION['user_email'] ?? '';
$profileImage = 'uploads/profile_' . $userId . '.jpg';

// --- Fix blank profile for new operator ---
if (empty($userFirstName) || empty($userLastName) || empty($userEmail)) {
    $userQuery = $conn->prepare("SELECT firstName, lastName, email FROM users WHERE id = ? LIMIT 1");
    $userQuery->bind_param("i", $userId);
    $userQuery->execute();
    $userResult = $userQuery->get_result();
    if ($userResult && $user = $userResult->fetch_assoc()) {
        $userFirstName = $user['firstName'];
        $userLastName = $user['lastName'];
        $userEmail = $user['email'];
        // Update session variables as well
        $_SESSION['user_firstName'] = $userFirstName;
        $_SESSION['user_lastName'] = $userLastName;
        $_SESSION['user_email'] = $userEmail;
    }
}

// Get the current page
$page = isset($_GET['page']) ? $_GET['page'] : 'dashboard';

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

        // Get reference number based on payment method
        $referenceNumber = '';
        switch ($paymentMethod) {
            case 'gcash':
                $referenceNumber = $_POST['gcash_number'];
                break;
            case 'bank':
                $referenceNumber = $_POST['bank_account'];
                break;
            case 'cash':
                $referenceNumber = $_POST['reference_number'];
                break;
        }

        // Insert payment record with Pending status
        $stmt = $conn->prepare("INSERT INTO membership_payments (user_id, amount, method, reference_number, receipt_number, status, firstName, lastName, userType, payment_date) VALUES (?, ?, ?, ?, ?, 'Pending', ?, ?, ?, CURRENT_TIMESTAMP)");
        $amount = 1000; // Fixed amount
        $stmt->bind_param("idsssssss", $userId, $amount, $paymentMethod, $referenceNumber, $receiptNumber, $userFirstName, $userLastName, $userType);
        
        if (!$stmt->execute()) {
            throw new Exception('Failed to save payment record');
        }

        // Store payment details for response
        $paymentDetails = [
            'receipt_number' => $receiptNumber,
            'amount' => $amount,
            'method' => $paymentMethod,
            'date' => date('Y-m-d H:i:s'),
            'name' => $userFirstName . ' ' . $userLastName,
            'status' => 'Pending',
            'user_type' => $userType,
            'reference_number' => $referenceNumber
        ];

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

    // Check for errors
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

// Check if user has requested orientation
$checkRequestQuery = "SELECT id FROM orientation_requests WHERE user_id = ? AND status = 'Pending'";
$checkStmt = $conn->prepare($checkRequestQuery);
$checkStmt->bind_param("i", $userId);
$checkStmt->execute();
$requestResult = $checkStmt->get_result();
$hasRequestedOrientation = $requestResult->num_rows > 0;

// Get upcoming orientation if available
$upcomingQuery = "
    SELECT os.*, oa.is_completed 
    FROM orientation_schedule os
    LEFT JOIN orientation_attendees oa ON oa.orientation_id = os.id AND oa.user_id = ?
    WHERE os.orientation_date >= CURDATE()
    ORDER BY os.orientation_date ASC
    LIMIT 1
";
$upcomingStmt = $conn->prepare($upcomingQuery);
$upcomingStmt->bind_param("i", $userId);
$upcomingStmt->execute();
$upcomingResult = $upcomingStmt->get_result();
$upcomingAvailable = ($upcomingResult && $upcomingResult->num_rows > 0);

// Handle Jeepney Assignment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['assign_jeepney'])) {
    $driver_id = $_POST['driver_id'];
    $plate_number = $_POST['plate_number'];
    $body_number = $_POST['body_number'];
    $route = $_POST['route'];
    $notes = $_POST['notes'] ?? '';

    // Start transaction
    $conn->begin_transaction();

    try {
        // Update jeepney status to Assigned
        $update_jeepney = $conn->prepare("UPDATE jeepneys SET status = 'Assigned' WHERE plate_number = ?");
        $update_jeepney->bind_param("s", $plate_number);
        $update_jeepney->execute();

        // Insert assignment record
        $insert_assignment = $conn->prepare("INSERT INTO jeepney_assignments (driver_id, operator_id, plate_number, body_number, route, notes) VALUES (?, ?, ?, ?, ?, ?)");
        $insert_assignment->bind_param("iissss", $driver_id, $userId, $plate_number, $body_number, $route, $notes);
        $insert_assignment->execute();

        $conn->commit();
        $successMessage = "Jeepney assigned successfully!";
    } catch (Exception $e) {
        $conn->rollback();
        $errorMessage = "Failed to assign jeepney: " . $e->getMessage();
    }
}

// Get available jeepneys
$available_jeepneys = $conn->query("SELECT * FROM jeepneys WHERE status = 'Available'");

// Get available drivers (users with driver type)
$available_drivers = $conn->query("SELECT id, firstName, lastName FROM users WHERE userType = 'driver' AND id NOT IN (SELECT driver_id FROM jeepney_assignments WHERE status = 'Active')");

// Get current assignments
$current_assignments = $conn->query("
    SELECT ja.*, u.firstName, u.lastName 
    FROM jeepney_assignments ja 
    JOIN users u ON ja.driver_id = u.id 
    WHERE ja.status = 'Active'
    ORDER BY ja.assigned_date DESC
");

// Handle Orientation Attendance Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['orientation_id'], $_POST['user_id'], $_POST['attended_mode'])) {
    header('Content-Type: application/json');
    try {
        $orientationId = intval($_POST['orientation_id']);
        $userId = intval($_POST['user_id']);
        $attendedMode = $_POST['attended_mode'];

        // Check if already attended
        $checkStmt = $conn->prepare("SELECT id FROM orientation_attendees WHERE orientation_id = ? AND user_id = ?");
        $checkStmt->bind_param("ii", $orientationId, $userId);
        $checkStmt->execute();
        $checkResult = $checkStmt->get_result();

        if ($checkResult->num_rows > 0) {
            throw new Exception('Attendance already recorded for this orientation.');
        }

        // Insert attendance record
        $insertStmt = $conn->prepare("INSERT INTO orientation_attendees (orientation_id, user_id, attended_mode, is_completed) VALUES (?, ?, ?, 0)");
        $insertStmt->bind_param("iis", $orientationId, $userId, $attendedMode);

        if (!$insertStmt->execute()) {
            throw new Exception('Failed to record attendance.');
        }

        echo json_encode([
            'success' => true,
            'message' => 'Your attendance has been recorded.'
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

// Handle Cooperative Fund Payment Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cooperative_payment_method'])) {
    header('Content-Type: application/json');
    try {
        $paymentMethod = $_POST['cooperative_payment_method'];
        $requiredFields = [
            'gcash' => ['gcash_number', 'gcash_name'],
            'bank' => ['bank_name', 'bank_account', 'bank_account_name'],
            'cash' => ['reference_number']
        ];
        if (!isset($requiredFields[$paymentMethod])) {
            throw new Exception('Invalid payment method');
        }
        foreach ($requiredFields[$paymentMethod] as $field) {
            if (empty($_POST[$field])) {
                throw new Exception("Missing required field: $field");
            }
        }
        $receiptNumber = 'COOP-' . date('Ymd') . '-' . str_pad($userId, 4, '0', STR_PAD_LEFT);
        $referenceNumber = '';
        switch ($paymentMethod) {
            case 'gcash':
                $referenceNumber = $_POST['gcash_number'];
                break;
            case 'bank':
                $referenceNumber = $_POST['bank_account'];
                break;
            case 'cash':
                $referenceNumber = $_POST['reference_number'];
                break;
        }
        $amount = 500; // Example fixed amount for cooperative fund
        $stmt = $conn->prepare("INSERT INTO cooperative_fund_payments (user_id, amount, method, reference_number, receipt_number, status, firstName, lastName, userType) VALUES (?, ?, ?, ?, ?, 'Pending', ?, ?, ?)");
        $stmt->bind_param("idssssss", $userId, $amount, $paymentMethod, $referenceNumber, $receiptNumber, $userFirstName, $userLastName, $userType);
        if (!$stmt->execute()) {
            throw new Exception('Failed to save cooperative fund payment');
        }
        $paymentDetails = [
            'receipt_number' => $receiptNumber,
            'amount' => $amount,
            'method' => $paymentMethod,
            'date' => date('Y-m-d H:i:s'),
            'name' => $userFirstName . ' ' . $userLastName,
            'status' => 'Pending',
            'user_type' => $userType,
            'reference_number' => $referenceNumber
        ];
        echo json_encode([
            'success' => true,
            'message' => 'Cooperative fund payment submitted successfully',
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

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Operator Dashboard - TEBZ</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="../css/style.css">
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <!-- Bootstrap Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
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
        <li class="<?= $page === 'manage_jeepneys' ? 'active' : '' ?>">
          <a href="?page=manage_jeepneys" style="color: white; padding: 14px 20px; display: flex; align-items: center; text-decoration: none; transition: all 0.3s ease;">
            <i class="bi bi-truck-front-fill" style="font-size: 1.2rem; margin-right: 12px; width: 24px;"></i>
            <span style="font-size: 1rem; font-weight: 500;">Manage Jeepneys</span>
          </a>
        </li>
        <li class="<?= $page === 'assign_jeepney' ? 'active' : '' ?>">
          <a href="?page=assign_jeepney" style="color: white; padding: 14px 20px; display: flex; align-items: center; text-decoration: none; transition: all 0.3s ease;">
            <i class="bi bi-person-badge-fill" style="font-size: 1.2rem; margin-right: 12px; width: 24px;"></i>
            <span style="font-size: 1rem; font-weight: 500;">Assign Jeepney</span>
          </a>
        </li>
        <li class="<?= $page === 'boundaries' ? 'active' : '' ?>">
          <a href="?page=boundaries" style="color: white; padding: 14px 20px; display: flex; align-items: center; text-decoration: none; transition: all 0.3s ease;">
            <i class="bi bi-wallet2" style="font-size: 1.2rem; margin-right: 12px; width: 24px;"></i>
            <span style="font-size: 1rem; font-weight: 500;">Boundaries</span>
          </a>
        </li>
        <li class="<?= $page === 'cooperative_fund' ? 'active' : '' ?>">
          <a href="?page=cooperative_fund" style="color: white; padding: 14px 20px; display: flex; align-items: center; text-decoration: none; transition: all 0.3s ease;">
            <i class="bi bi-piggy-bank-fill" style="font-size: 1.2rem; margin-right: 12px; width: 24px;"></i>
            <span style="font-size: 1rem; font-weight: 500;">Cooperative Fund</span>
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

    <?php include 'assign_jeepney_modal.php'; ?>
    
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
                    <li class="nav-item">
                        <a class="nav-link sidebar-link <?= $page === 'assignjeepney' ? 'active' : '' ?>" href="?page=assignjeepney">
                            <div class="d-flex align-items-center">
                                <div class="sidebar-icon">
                                    <i class="bi bi-truck-front-fill"></i>
                                </div>
                                <span class="sidebar-text">Assign Jeepney</span>
                                <?php if ($page === 'assignjeepney'): ?>
                                    <div class="sidebar-indicator"></div>
                                <?php endif; ?>
                            </div>
                        </a>
                    </li>

                    <!-- Collect Boundaries Link -->
                    <li class="nav-item">
                        <a class="nav-link sidebar-link <?= $page === 'collect_boundaries' ? 'active' : '' ?>" href="?page=collect_boundaries">
                            <div class="d-flex align-items-center">
                                <div class="sidebar-icon">
                                    <i class="bi bi-cash-coin"></i>
                                </div>
                                <span class="sidebar-text">Collect Boundaries</span>
                                <span class="badge bg-danger ms-auto" id="boundaryBadge" style="display: none;">0</span>
                                <?php if ($page === 'collect_boundaries'): ?>
                                    <div class="sidebar-indicator"></div>
                                <?php endif; ?>
                            </div>
                        </a>
                    </li>

                    <!-- Pay Cooperative Funds Link -->
                    <li class="nav-item">
                        <a class="nav-link sidebar-link <?= $page === 'pay_cooperative' ? 'active' : '' ?>" href="?page=pay_cooperative">
                            <div class="d-flex align-items-center">
                                <div class="sidebar-icon">
                                    <i class="bi bi-bank2"></i>
                                </div>
                                <span class="sidebar-text">Pay Cooperative Funds</span>
                                <?php if ($page === 'pay_cooperative'): ?>
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
        :root {
            --brand-primary: #4f46e5;
            --brand-primary-2: #7c3aed;
            --bg-soft: #f7f9fc;
            --card-bg: rgba(255,255,255,0.98);
            --radius: 8px;
        }
        body { background: var(--bg-soft); }
        .bg-gradient-dark { background: linear-gradient(180deg, #263042 0%, #1e2532 100%); }
        .content-wrapper { margin-left: 280px; width: calc(100% - 280px); min-height: 100vh; background: var(--bg-soft); }
        .content-header { background: #ffffff; padding: 1rem 1.5rem; border-bottom: 1px solid rgba(0,0,0,0.05); box-shadow: 0 2px 8px rgba(0,0,0,0.05); display: flex; align-items: center; gap: 1rem; }
        .content-container { padding: 1.5rem; }
        
        .sidebar {
            box-shadow: 4px 0 10px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
        }
        
        .sidebar-header {
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }
        
        .sidebar-nav {
            flex: 1;
        }
        
        .sidebar-link {
            color: rgba(255,255,255,0.8) !important;
            padding: 12px 16px;
            border-radius: 12px;
            margin-bottom: 4px;
            transition: all 0.3s ease;
            text-decoration: none;
            position: relative;
        }
        
        .sidebar-link:hover {
            color: white !important;
            background: rgba(255,255,255,0.1);
            transform: translateX(5px);
        }
        
        .sidebar-link.active {
            color: white !important;
            background: linear-gradient(135deg, #2563eb 0%, #7c3aed 100%);
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);
        }
        
        .sidebar-icon {
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 10px;
            margin-right: 12px;
            background: rgba(255,255,255,0.1);
            transition: all 0.3s ease;
        }
        
        .sidebar-link:hover .sidebar-icon {
            background: rgba(255,255,255,0.2);
            transform: scale(1.1);
        }
        
        .sidebar-link.active .sidebar-icon {
            background: rgba(255,255,255,0.2);
        }
        
        .sidebar-text {
            font-weight: 500;
            font-size: 0.95rem;
        }
        
        .sidebar-indicator {
            position: absolute;
            right: 16px;
            top: 50%;
            transform: translateY(-50%);
            width: 6px;
            height: 6px;
            background: white;
            border-radius: 50%;
            box-shadow: 0 0 10px rgba(255,255,255,0.5);
        }
        
        .sidebar-divider {
            border-color: rgba(255,255,255,0.1);
            margin: 1rem 0;
        }
        
        .logout-link {
            color: #e74c3c !important;
        }
        
        .logout-link:hover {
            background: rgba(231, 76, 60, 0.1) !important;
            color: #e74c3c !important;
        }
        
        .logout-link .sidebar-icon {
            background: rgba(231, 76, 60, 0.2);
        }
        
        .sidebar-footer {
            border-top: 1px solid rgba(255,255,255,0.1);
            background: rgba(0,0,0,0.1);
        }
        
        /* Badge Animation */
        .badge {
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.1); }
            100% { transform: scale(1); }
        }
        
        
        /* Scrollbar Styling */
        .sidebar::-webkit-scrollbar {
            width: 6px;
        }
        
        .sidebar::-webkit-scrollbar-track {
            background: rgba(255,255,255,0.1);
        }
        
        .sidebar::-webkit-scrollbar-thumb {
            background: rgba(255,255,255,0.3);
            border-radius: 3px;
        }
        
        .sidebar::-webkit-scrollbar-thumb:hover {
            background: rgba(255,255,255,0.5);
        }
        </style>

        <!-- Main Content -->
        <div class="content-wrapper" style="margin-left: 280px; width: calc(100% - 280px); min-height: 100vh; background: #f7f9fc;">
            <div class="content-header" style="background: #ffffff; border-bottom: 1px solid rgba(0,0,0,0.05); box-shadow: 0 2px 8px rgba(0,0,0,0.05); display: flex; justify-content: center;">
                <div class="d-flex align-items-center gap-3" style="max-width: 1200px; width: 100%; padding: 1rem 1.5rem;">
                    <img src="../img/logo12.png" alt="JeepniGo Logo" style="height: 32px;">
                    <h4 class="mb-0 fw-bold">Operator Dashboard</h4>
                </div>
            </div>

            <!-- Content Container -->
            <div class="content-container" style="height: calc(100vh - 80px); overflow-y: auto; padding: 20px; display: flex; justify-content: center;">
                <div class="content <?= $page === 'dashboard' ? 'dashboard-page' : ($page === 'profile' ? 'profile-page' : ($page === 'payment' ? 'payment-page' : ($page === 'manage_jeepneys' ? 'manage-page' : ($page === 'assign_jeepney' ? 'assign-page' : ($page === 'boundaries' ? 'boundaries-page' : ($page === 'cooperative_fund' ? 'cooperative-page' : 'dashboard-page')))))); ?>" style="max-width: 1200px; width: 100%;">
                
                <?php if ($page === 'dashboard'): ?>
                    <!-- Dashboard content -->
                    <div class="card profile-card shadow-lg border-0 mb-4">
                        <div class="card-body text-center">
                            <div class="profile-avatar mb-3 mx-auto">
                                <img src="<?= htmlspecialchars($profileImage); ?>" alt="Profile Picture" class="rounded-circle shadow" width="110" height="110">
                            </div>
                            <h3 class="fw-bold mb-1"><?= htmlspecialchars($userFirstName . ' ' . $userLastName); ?></h3>
                            <span class="badge bg-secondary px-3 py-2 text-uppercase"><?= htmlspecialchars($userType); ?></span>
                        </div>
                    </div>

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
                                                    document.getElementById("countdownTimer").innerHTML = "✅ Orientation attendance completed!";
                                                    document.getElementById("countdownTimer").className = "text-success fw-semibold mt-2";
                                                }
                                            }
                                        </script>

                                        <!-- Action Buttons -->
                                        <?php if (!$hasAttended): ?>
                                            <div class="text-center mt-3">
                                                <p class="text-muted">You haven't confirmed your attendance yet.</p>
                                                <button onclick="submitAttendance(<?= $sched['orientation_id']; ?>, 'online')" class="btn btn-outline-primary btn-sm">
                                                    <i class="bi bi-camera-video-fill"></i> Attend Online
                                                </button>
                                                <button onclick="submitAttendance(<?= $sched['orientation_id']; ?>, 'in-person')" class="btn btn-outline-secondary btn-sm">
                                                    <i class="bi bi-geo-alt-fill"></i> Attend In-Person
                                                </button>
                                            </div>
                                        <?php elseif ($sched['is_completed']): ?>
                                            <div class="alert alert-success text-center mt-3 animate__animated animate__tada">
                                                <i class="bi bi-check-circle-fill"></i> Orientation Completed <br>
                                                <a href="?page=payment" class="btn btn-sm btn-success mt-2">
                                                    <i class="bi bi-credit-card"></i> Pay Membership Fee Now
                                                </a>
                                                <button type="button" class="btn btn-sm btn-primary mt-2 ms-2" data-bs-toggle="modal" data-bs-target="#assignJeepneyModal">
                                                    <i class="bi bi-truck"></i> Assign Jeepney Now
                                                </button>
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
                                    <p><strong>Date:</strong> <?= htmlspecialchars($schedule['orientation_date'] ?? 'N/A'); ?></p>
                                    <p><strong>Time:</strong> <?= htmlspecialchars($schedule['orientation_time'] ?? 'N/A'); ?></p>

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
                                        <button class="btn btn-success btn-lg px-5 py-3" 
                                                style="box-shadow: 0 4px 8px rgba(14, 77, 14, 0.4); font-weight: 600; border-radius: 12px;"
                                                onclick="requestOrientation()">
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

                    <!-- Add this JavaScript at the bottom of your file -->
                    <script>
                    // Single, unified showOrientation function
                    function showOrientation(mode) {
                        const onlineDetails = document.getElementById('onlineDetails');
                        const inpersonDetails = document.getElementById('inpersonDetails');
                        
                        // Hide both sections first
                        if (onlineDetails) onlineDetails.classList.add('d-none');
                        if (inpersonDetails) inpersonDetails.classList.add('d-none');
                        
                        // Show the selected section
                        if (mode === 'online' && onlineDetails) {
                            onlineDetails.classList.remove('d-none');
                        } else if (mode === 'inperson' && inpersonDetails) {
                            inpersonDetails.classList.remove('d-none');
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
                                    allowEscapeKey: false
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

                    async function submitAttendance(orientationId, mode) {
                        try {
                            // Show confirmation dialog
                            const result = await Swal.fire({
                                title: 'Confirm Attendance',
                                text: `Are you sure you want to attend ${mode === 'online' ? 'online' : 'in-person'}?`,
                                icon: 'question',
                                showCancelButton: true,
                                confirmButtonText: 'Yes, Confirm',
                                cancelButtonText: 'Cancel',
                                confirmButtonColor: '#198754'
                            });

                            if (!result.isConfirmed) {
                                return;
                            }

                            // Show loading state
                            Swal.fire({
                                title: 'Submitting...',
                                didOpen: () => {
                                    Swal.showLoading();
                                },
                                allowOutsideClick: false,
                                allowEscapeKey: false
                            });

                            // Create form data
                            const formData = new FormData();
                            formData.append('orientation_id', orientationId);
                            formData.append('user_id', <?= $userId ?>);
                            formData.append('attended_mode', mode);

                            // Send the request
                            const response = await fetch('operator_dashboard.php', {
                                method: 'POST',
                                body: formData
                            });

                            const text = await response.text();
                            console.log(text); // See what is actually returned
                            const data = JSON.parse(text);

                            if (data.success) {
                                stopCountdown(); // Stop the countdown when attendance is submitted
                                await Swal.fire({
                                    icon: 'success',
                                    title: 'Success!',
                                    text: 'Your attendance has been recorded.',
                                    confirmButtonColor: '#198754'
                                });
                                location.reload();
                            } else {
                                throw new Error(data.message || 'Failed to submit attendance');
                            }
                        } catch (error) {
                            console.error('Error:', error);
                            Swal.fire({
                                icon: 'error',
                                title: 'Error',
                                text: error.message || 'Failed to submit attendance. Please try again.',
                                confirmButtonColor: '#dc3545'
                            });
                        }
                    }

                    // Search Driver functionality
                    document.getElementById('searchDriver')?.addEventListener('keyup', function() {
                        var searchValue = this.value.toLowerCase();
                        var rows = document.querySelectorAll("#driverTable tbody tr");
                        rows.forEach(function(row) {
                            var name = row.cells[0].textContent.toLowerCase();
                            row.style.display = name.includes(searchValue) ? '' : 'none';
                        });
                    });

                    // Modal handling
                    function openAssignModal(driverId, driverName, driverEmail) {
                        // Set the modal content
                        document.getElementById('driverName').textContent = driverName;
                        document.getElementById('driverEmail').textContent = driverEmail;
                        document.getElementById('driverId').value = driverId;
                        
                        // Reset the form
                        const form = document.getElementById('assignJeepneyForm');
                        if (form) {
                            form.reset();
                            form.classList.remove('was-validated');
                        }
                        
                        // Show the modal
                        const modalElement = document.getElementById('assignJeepneyModal');
                        const modal = new bootstrap.Modal(modalElement);
                        modal.show();
                    }

                    // Form submission confirmation
                    function confirmAssign(e) {
                        e.preventDefault();
                        Swal.fire({
                            title: 'Confirm Assign?',
                            text: "Are you sure you want to assign this jeepney to the driver?",
                            icon: 'question',
                            showCancelButton: true,
                            confirmButtonText: 'Yes, Assign',
                            cancelButtonText: 'Cancel',
                            confirmButtonColor: '#198754'
                        }).then((result) => {
                            if (result.isConfirmed) {
                                e.target.submit();
                            }
                        });
                    }

                    function checkPaymentStatus() {
                        fetch('check_payment_status.php')
                            .then(response => response.json())
                            .then(data => {
                                if (data.success) {
                                    const paymentStatus = document.getElementById('paymentStatus');
                                    const receiptSection = document.getElementById('receiptSection');
                                    const paymentForm = document.getElementById('paymentForm');
                                    
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
                                                // Update receipt details
                                                document.getElementById('receiptNumber').textContent = data.receipt.receipt_number;
                                                document.getElementById('receiptAmount').textContent = data.receipt.amount;
                                                document.getElementById('receiptMethod').textContent = data.receipt.method;
                                                document.getElementById('receiptDate').textContent = new Date(data.receipt.date).toLocaleDateString();
                                                document.getElementById('receiptName').textContent = data.receipt.name;
                                                document.getElementById('receiptStatus').textContent = data.receipt.status;
                                                document.getElementById('receiptUserType').textContent = data.receipt.user_type;

                                                // Update receipt content based on user type
                                                updateReceiptContent(data.receipt.user_type, data.receipt);

                                                // Update payment details based on payment method
                                                if (data.receipt.method) {
                                                    updatePaymentDetails(data.receipt.method, data.receipt);
                                                }
                                            }
                                            if (paymentForm) {
                                                paymentForm.style.display = 'none';
                                            }
                                        } else {
                                            if (receiptSection) {
                                                receiptSection.style.display = 'none';
                                            }
                                            if (paymentForm) {
                                                paymentForm.style.display = 'block';
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

                    function viewReceipt(paymentId) {
                        window.open(`view_receipt.php?payment_id=${paymentId}`, '_blank', 'width=800,height=600');
                    }

                    function updateReceiptContent(userType, paymentData) {
                        // Update receipt title and subtitle based on user type
                        const receiptTitle = document.getElementById('receiptTitle');
                        const receiptSubtitle = document.getElementById('receiptSubtitle');
                        const receiptFooterText = document.getElementById('receiptFooterText');
                        const roleSpecificDetails = document.getElementById('roleSpecificDetails');

                        switch(userType.toLowerCase()) {
                            case 'driver':
                                receiptTitle.textContent = 'TEBZ Driver Payment Receipt';
                                receiptSubtitle.textContent = 'Driver Membership Payment Confirmation';
                                receiptFooterText.textContent = 'This receipt serves as proof of your driver membership payment to TEBZ.';
                                roleSpecificDetails.innerHTML = `
                                    <div class="info-group">
                                        <label class="text-muted small">Membership Type</label>
                                        <p class="mb-0">Driver Membership</p>
                                    </div>
                                    <div class="info-group">
                                        <label class="text-muted small">Validity Period</label>
                                        <p class="mb-0">1 Year from payment date</p>
                                    </div>
                                `;
                                break;
                            case 'operator':
                                receiptTitle.textContent = 'TEBZ Operator Payment Receipt';
                                receiptSubtitle.textContent = 'Operator Membership Payment Confirmation';
                                receiptFooterText.textContent = 'This receipt serves as proof of your operator membership payment to TEBZ.';
                                roleSpecificDetails.innerHTML = `
                                    <div class="info-group">
                                        <label class="text-muted small">Membership Type</label>
                                        <p class="mb-0">Operator Membership</p>
                                    </div>
                                    <div class="info-group">
                                        <label class="text-muted small">Validity Period</label>
                                        <p class="mb-0">1 Year from payment date</p>
                                    </div>
                                `;
                                break;
                            case 'treasurer':
                                receiptTitle.textContent = 'TEBZ Treasurer Payment Receipt';
                                receiptSubtitle.textContent = 'Treasurer Membership Payment Confirmation';
                                receiptFooterText.textContent = 'This receipt serves as proof of your treasurer membership payment to TEBZ.';
                                roleSpecificDetails.innerHTML = `
                                    <div class="info-group">
                                        <label class="text-muted small">Membership Type</label>
                                        <p class="mb-0">Treasurer Membership</p>
                                    </div>
                                    <div class="info-group">
                                        <label class="text-muted small">Validity Period</label>
                                        <p class="mb-0">1 Year from payment date</p>
                                    </div>
                                `;
                                break;
                        }

                        // Update user type display
                        document.getElementById('receiptUserType').textContent = userType.charAt(0).toUpperCase() + userType.slice(1);
                    }

                    function printReceipt() {
                        const printContent = document.getElementById('receiptDetails').innerHTML;
                        const originalContent = document.body.innerHTML;
                        document.body.innerHTML = `<div class='container mt-4'>${printContent}</div>`;
                        window.print();
                        document.body.innerHTML = originalContent;
                    }

                    function downloadReceipt() {
                        const { jsPDF } = window.jspdf;
                        const doc = new jsPDF();
                        
                        // Get receipt content
                        const title = document.getElementById('receiptTitle').textContent;
                        const subtitle = document.getElementById('receiptSubtitle').textContent;
                        const receiptNumber = document.getElementById('receiptNumber').textContent;
                        const receiptDate = document.getElementById('receiptDate').textContent;
                        const receiptAmount = document.getElementById('receiptAmount').textContent;
                        const receiptMethod = document.getElementById('receiptMethod').textContent;
                        const receiptName = document.getElementById('receiptName').textContent;
                        const receiptUserType = document.getElementById('receiptUserType').textContent;
                        const receiptStatus = document.getElementById('receiptStatus').textContent;
                        const footerText = document.getElementById('receiptFooterText').textContent;

                        // Add content to PDF
                        doc.setFontSize(20);
                        doc.text(title, 105, 20, { align: 'center' });
                        doc.setFontSize(12);
                        doc.text(subtitle, 105, 30, { align: 'center' });
                        doc.setFontSize(10);
                        doc.text(`Receipt Number: ${receiptNumber}`, 20, 45);
                        doc.text(`Date: ${receiptDate}`, 20, 55);
                        doc.text(`Amount: ${receiptAmount}`, 20, 65);
                        doc.text(`Payment Method: ${receiptMethod}`, 20, 75);
                        doc.text(`Paid By: ${receiptName}`, 20, 85);
                        doc.text(`User Type: ${receiptUserType}`, 20, 95);
                        doc.text(`Status: ${receiptStatus}`, 20, 105);
                        
                        // Add role-specific details if available
                        const roleSpecificDetails = document.getElementById('roleSpecificDetails');
                        if (roleSpecificDetails && roleSpecificDetails.innerHTML.trim()) {
                            const details = roleSpecificDetails.getElementsByClassName('info-group');
                            let yPos = 120;
                            for (let detail of details) {
                                const label = detail.getElementsByTagName('label')[0].textContent;
                                const value = detail.getElementsByTagName('p')[0].textContent;
                                doc.text(`${label}: ${value}`, 20, yPos);
                                yPos += 10;
                            }
                        }

                        // Add footer
                        doc.setFontSize(8);
                        doc.text(footerText, 105, 180, { align: 'center' });
                        doc.text('Thank you for your payment!', 105, 190, { align: 'center' });

                        doc.save('TEBZ_Payment_Receipt.pdf');
                    }

                    function updatePaymentDetails(paymentMethod, details) {
                        const paymentDetailsDiv = document.getElementById('paymentDetails');
                        let detailsHtml = '';
                        switch(paymentMethod) {
                            case 'gcash':
                                detailsHtml = `<div class='info-group'><label class='text-muted small'>GCash Number</label><p class='mb-0'>${details.gcash_number}</p></div><div class='info-group'><label class='text-muted small'>GCash Account Name</label><p class='mb-0'>${details.gcash_name}</p></div>`;
                                break;
                            case 'bank':
                                detailsHtml = `<div class='info-group'><label class='text-muted small'>Bank Name</label><p class='mb-0'>${details.bank_name}</p></div><div class='info-group'><label class='text-muted small'>Bank Account Number</label><p class='mb-0'>${details.bank_account}</p></div><div class='info-group'><label class='text-muted small'>Bank Account Name</label><p class='mb-0'>${details.bank_account_name}</p></div>`;
                                break;
                            case 'cash':
                                detailsHtml = `<div class='info-group'><label class='text-muted small'>Reference Number</label><p class='mb-0'>${details.reference_number}</p></div>`;
                                break;
                        }
                        paymentDetailsDiv.innerHTML = detailsHtml;
                    }
                    </script>
                    <style>
                    .receipt-container { max-width: 800px; margin: 0 auto; padding: 20px; background: #fff; }
                    .receipt-logo { max-height: 60px; margin-bottom: 1rem; }
                    .receipt-header { border-bottom: 2px solid #e9ecef; padding-bottom: 1.5rem; }
                    .receipt-info { padding: 1.5rem 0; }
                    .info-group { margin-bottom: 1rem; }
                    .info-group label { display: block; margin-bottom: 0.25rem; color: #6c757d; font-size: 0.875rem; }
                    .info-group p { font-size: 1rem; color: #212529; }
                    .receipt-footer { padding-top: 1.5rem; border-top: 2px solid #e9ecef; }
                    @media print { body * { visibility: hidden; } #receiptDetails, #receiptDetails * { visibility: visible; } #receiptDetails { position: absolute; left: 0; top: 0; width: 100%; } .receipt-actions { display: none; } }
                    </style>

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
                                                        <h5 class="mb-0">�� Payment Receipt</h5>
                                                        <div class="receipt-actions">
                                                            <button class="btn btn-light btn-sm me-2" onclick="downloadReceipt()">
                                                                <i class="bi bi-download"></i> Download
                                                            </button>
                                                            <button class="btn btn-light btn-sm" onclick="printReceipt()">
                                                                <i class="bi bi-printer"></i> Print
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
                                                                            <label class="text-muted small">User Type</label>
                                                                            <p class="mb-0" id="receiptUserType"></p>
                                                                        </div>
                                                                        <div class="info-group mb-3">
                                                                            <label class="text-muted small">Status</label>
                                                                            <p class="mb-0"><span id="receiptStatus" class="badge bg-success">Confirmed</span></p>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                                <div id="paymentDetails" class="mt-4"><!-- Additional payment details will be dynamically added here --></div>
                                                                <div id="roleSpecificDetails" class="mt-4"><!-- Role-specific details will be added here --></div>
                                                            </div>
                                                            <hr class="my-4">
                                                            <div class="receipt-footer text-center">
                                                                <p class="text-muted mb-2" id="receiptFooterText">This receipt serves as proof of your payment to TEBZ.</p>
                                                                <p class="text-muted mb-0">Thank you for your payment!</p>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

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
                                                        <label for="gcash_number">GCash Number</label>
                                                        <input type="text" 
                                                               id="gcash_number" 
                                                               name="gcash_number" 
                                                               pattern="[0-9]{11}" 
                                                               placeholder="Enter 11-digit GCash number">
                                                    </div>
                                                    <div class="form-group">
                                                        <label for="gcash_name">GCash Account Name</label>
                                                        <input type="text" 
                                                               id="gcash_name" 
                                                               name="gcash_name" 
                                                               placeholder="Enter GCash account name">
                                                    </div>
                                                </div>

                                                <!-- Bank Transfer Fields -->
                                                <div id="bankFields" class="payment-fields d-none">
                                                    <div class="form-group">
                                                        <label for="bank_name">Bank Name</label>
                                                        <input type="text" 
                                                               id="bank_name" 
                                                               name="bank_name" 
                                                               placeholder="Enter bank name">
                                                    </div>
                                                    <div class="form-group">
                                                        <label for="bank_account">Bank Account Number</label>
                                                        <input type="text" 
                                                               id="bank_account" 
                                                               name="bank_account" 
                                                               placeholder="Enter bank account number">
                                                    </div>
                                                    <div class="form-group">
                                                        <label for="bank_account_name">Bank Account Name</label>
                                                        <input type="text" 
                                                               id="bank_account_name" 
                                                               name="bank_account_name" 
                                                               placeholder="Enter bank account name">
                                                    </div>
                                                </div>

                                                <!-- Cash Payment Fields -->
                                                <div id="cashFields" class="payment-fields d-none">
                                                    <div class="form-group">
                                                        <label for="reference_number">Receipt Number</label>
                                                        <input type="text" 
                                                               id="reference_number" 
                                                               name="reference_number" 
                                                               placeholder="Enter receipt number">
                                                    </div>
                                                </div>

                                                <button type="submit" class="submit-payment">
                                                    <i class="bi bi-credit-card"></i>
                                                    Submit Payment
                                                </button>
                                            </form>

                                            <!-- View Receipt Button -->
                                            <div id="viewReceiptSection" class="mt-4 text-center d-none">
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

                                            // Initialize payment fields on page load
                                            document.addEventListener('DOMContentLoaded', function() {
                                                const paymentMethod = document.getElementById('payment_method').value;
                                                if (paymentMethod) {
                                                    showPaymentFields();
                                                }
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

                                            <script>
                                            // Function to show receipt
                                            function showReceipt(receipt) {
                                                // Update receipt details
                                                document.getElementById('receiptNumber').textContent = receipt.receipt_number;
                                                document.getElementById('receiptAmount').textContent = receipt.amount;
                                                document.getElementById('receiptMethod').textContent = receipt.method;
                                                document.getElementById('receiptDate').textContent = new Date(receipt.date).toLocaleDateString();
                                                document.getElementById('receiptName').textContent = receipt.name;
                                                document.getElementById('receiptStatus').textContent = receipt.status;
                                                document.getElementById('receiptUserType').textContent = receipt.user_type;

                                                // Update receipt content based on user type
                                                updateReceiptContent(receipt.user_type, receipt);

                                                // Update payment details based on payment method
                                                if (receipt.method) {
                                                    updatePaymentDetails(receipt.method, receipt);
                                                }

                                                // Show receipt section
                                                const receiptSection = document.getElementById('receiptSection');
                                                if (receiptSection) {
                                                    receiptSection.style.display = 'block';
                                                }
                                            }

                                            // Add this function to check payment status and show/hide receipt button
                                            function checkPaymentStatus() {
                                                fetch('check_payment_status.php')
                                                    .then(response => response.json())
                                                    .then(data => {
                                                        if (data.success) {
                                                            const paymentForm = document.getElementById('paymentForm');
                                                            const viewReceiptSection = document.getElementById('viewReceiptSection');
                                                            
                                                            if (data.has_payment) {
                                                                // Hide payment form and show receipt button if payment exists
                                                                if (paymentForm) {
                                                                    paymentForm.style.display = 'none';
                                                                }
                                                                if (viewReceiptSection) {
                                                                    viewReceiptSection.classList.remove('d-none');
                                                                }
                                                            } else {
                                                                // Show payment form and hide receipt button if no payment
                                                                if (paymentForm) {
                                                                    paymentForm.style.display = 'block';
                                                                }
                                                                if (viewReceiptSection) {
                                                                    viewReceiptSection.classList.add('d-none');
                                                                }
                                                            }
                                                        }
                                                    })
                                                    .catch(error => {
                                                        console.error('Error checking payment status:', error);
                                                    });
                                            }

                                            // Function to view the latest receipt
                                            function viewLatestReceipt() {
                                                fetch('get_latest_payment.php')
                                                    .then(response => response.json())
                                                    .then(data => {
                                                        if (data.success) {
                                                            showReceipt(data.receipt);
                                                        } else {
                                                            Swal.fire({
                                                                icon: 'error',
                                                                title: 'Error',
                                                                text: 'Could not retrieve payment receipt.',
                                                                confirmButtonColor: '#dc3545'
                                                            });
                                                        }
                                                    })
                                                    .catch(error => {
                                                        console.error('Error fetching receipt:', error);
                                                        Swal.fire({
                                                            icon: 'error',
                                                            title: 'Error',
                                                            text: 'Failed to retrieve receipt. Please try again.',
                                                            confirmButtonColor: '#dc3545'
                                                        });
                                                    });
                                            }

                                            // Call checkPaymentStatus on page load and every 30 seconds
                                            document.addEventListener('DOMContentLoaded', function() {
                                                checkPaymentStatus();
                                                setInterval(checkPaymentStatus, 30000);
                                            });

                                            // Update the payment form submission handler
                                            document.getElementById('paymentForm').addEventListener('submit', function(e) {
                                                e.preventDefault();
                                                
                                                // Show loading state
                                                const submitButton = this.querySelector('button[type="submit"]');
                                                const originalButtonText = submitButton.innerHTML;
                                                submitButton.disabled = true;
                                                submitButton.innerHTML = '<i class="bi bi-arrow-repeat spin"></i> Processing...';
                                                
                                                // Get form data
                                                const formData = new FormData(this);
                                                
                                                // Send payment request
                                                fetch('operator_dashboard.php', {
                                                    method: 'POST',
                                                    body: formData
                                                })
                                                .then(response => response.json())
                                                .then(data => {
                                                    if (data.success) {
                                                        // Show success message
                                                        Swal.fire({
                                                            icon: 'success',
                                                            title: 'Payment Submitted!',
                                                            text: 'Your payment is being processed.',
                                                            confirmButtonColor: '#198754'
                                                        }).then(() => {
                                                            // Hide payment form and show receipt button
                                                            this.style.display = 'none';
                                                            document.getElementById('viewReceiptSection').classList.remove('d-none');
                                                            
                                                            // Show receipt
                                                            showReceipt(data.receipt);
                                                        });
                                                    } else {
                                                        throw new Error(data.message || 'Failed to process payment');
                                                    }
                                                })
                                                .catch(error => {
                                                    console.error('Payment error:', error);
                                                    Swal.fire({
                                                        icon: 'error',
                                                        title: 'Payment Failed',
                                                        text: error.message || 'Please try again later.',
                                                        confirmButtonColor: '#dc3545'
                                                    });
                                                })
                                                .finally(() => {
                                                    submitButton.disabled = false;
                                                    submitButton.innerHTML = originalButtonText;
                                                });
                                            });
                                            </script>

                                            <style>
                                            /* Add these styles for the receipt button */
                                            #viewReceiptSection .btn {
                                                padding: 15px 30px;
                                                font-size: 1.1rem;
                                                font-weight: 600;
                                                border-radius: 10px;
                                                box-shadow: 0 4px 15px rgba(40, 167, 69, 0.2);
                                                transition: all 0.3s ease;
                                            }

                                            #viewReceiptSection .btn:hover {
                                                transform: translateY(-2px);
                                                box-shadow: 0 6px 20px rgba(40, 167, 69, 0.3);
                                            }

                                            #viewReceiptSection .btn i {
                                                font-size: 1.2rem;
                                                margin-right: 8px;
                                            }

                                            /* Animation for the receipt button */
                                            @keyframes fadeInUp {
                                                from {
                                                    opacity: 0;
                                                    transform: translateY(20px);
                                                }
                                                to {
                                                    opacity: 1;
                                                    transform: translateY(0);
                                                }
                                            }

                                            #viewReceiptSection {
                                                animation: fadeInUp 0.5s ease-out;
                                            }
                                            </style>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Add Receipt Modal -->
                            <!-- <div class="modal fade" id="receiptModal" tabindex="-1" aria-labelledby="receiptModalLabel" aria-hidden="true">
                                <div class="modal-dialog modal-lg">
                                    <div class="modal-content">
                                        <div class="modal-header bg-success text-white">
                                            <h5 class="modal-title" id="receiptModalLabel">Payment Receipt</h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                        </div>
                                        <div class="modal-body">
                                            <div class="text-center mb-4">
                                                <img src="../img/logo12.png" alt="TEBZ Logo" style="height: 60px;">
                                                <h4 class="mt-3">TEBZ Payment Receipt</h4>
                                            </div>
                                            <div class="receipt-details">
                                                <div class="row mb-3">
                                                    <div class="col-md-6">
                                                        <p><strong>Receipt Number:</strong> <span id="modalReceiptNumber"></span></p>
                                                        <p><strong>Date:</strong> <span id="modalReceiptDate"></span></p>
                                                        <p><strong>Amount:</strong> <span id="modalReceiptAmount"></span></p>
                                                    </div>
                                                    <div class="col-md-6">
                                                        <p><strong>Payment Method:</strong> <span id="modalReceiptMethod"></span></p>
                                                        <p><strong>Paid By:</strong> <span id="modalReceiptName"></span></p>
                                                        <p><strong>Status:</strong> <span id="modalReceiptStatus"></span></p>
                                                    </div>
                                                </div>
                                                <hr>
                                                <div class="text-center mt-4">
                                                    <p class="text-muted">This receipt serves as proof of your payment to TEBZ.</p>
                                                    <p class="text-muted">Thank you for your payment!</p>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                            <button type="button" class="btn btn-success" onclick="printReceipt()">
                                                <i class="bi bi-printer"></i> Print Receipt
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div> -->

                            <script>
                            function viewReceipt(paymentId) {
                                window.open(`view_receipt.php?payment_id=${paymentId}`, '_blank', 'width=800,height=600');
                            }

                            function updateReceiptContent(userType, paymentData) {
                                // Update receipt title and subtitle based on user type
                                const receiptTitle = document.getElementById('receiptTitle');
                                const receiptSubtitle = document.getElementById('receiptSubtitle');
                                const receiptFooterText = document.getElementById('receiptFooterText');
                                const roleSpecificDetails = document.getElementById('roleSpecificDetails');

                                switch(userType.toLowerCase()) {
                                    case 'driver':
                                        receiptTitle.textContent = 'TEBZ Driver Payment Receipt';
                                        receiptSubtitle.textContent = 'Driver Membership Payment Confirmation';
                                        receiptFooterText.textContent = 'This receipt serves as proof of your driver membership payment to TEBZ.';
                                        roleSpecificDetails.innerHTML = `
                                            <div class="info-group">
                                                <label class="text-muted small">Membership Type</label>
                                                <p class="mb-0">Driver Membership</p>
                                    </div>
                                        <div class="info-group">
                                            <label class="text-muted small">Validity Period</label>
                                            <p class="mb-0">1 Year from payment date</p>
                            </div>
                        `;
                                        break;
                                    case 'operator':
                                        receiptTitle.textContent = 'TEBZ Operator Payment Receipt';
                                        receiptSubtitle.textContent = 'Operator Membership Payment Confirmation';
                                        receiptFooterText.textContent = 'This receipt serves as proof of your operator membership payment to TEBZ.';
                                        roleSpecificDetails.innerHTML = `
                                            <div class="info-group">
                                                <label class="text-muted small">Membership Type</label>
                                                <p class="mb-0">Operator Membership</p>
                                        </div>
                                        <div class="info-group">
                                            <label class="text-muted small">Validity Period</label>
                                            <p class="mb-0">1 Year from payment date</p>
                                        </div>
                                    `;
                                        break;
                                    case 'treasurer':
                                        receiptTitle.textContent = 'TEBZ Treasurer Payment Receipt';
                                        receiptSubtitle.textContent = 'Treasurer Membership Payment Confirmation';
                                        receiptFooterText.textContent = 'This receipt serves as proof of your treasurer membership payment to TEBZ.';
                                        roleSpecificDetails.innerHTML = `
                                            <div class="info-group">
                                                <label class="text-muted small">Membership Type</label>
                                                <p class="mb-0">Treasurer Membership</p>
                                        </div>
                                        <div class="info-group">
                                            <label class="text-muted small">Validity Period</label>
                                            <p class="mb-0">1 Year from payment date</p>
                                        </div>
                                    `;
                                        break;
                                }

                                // Update user type display
                                document.getElementById('receiptUserType').textContent = userType.charAt(0).toUpperCase() + userType.slice(1);
                            }

                            function printReceipt() {
                                const printContent = document.getElementById('receiptDetails').innerHTML;
                                const originalContent = document.body.innerHTML;
                                document.body.innerHTML = `<div class='container mt-4'>${printContent}</div>`;
                                window.print();
                                document.body.innerHTML = originalContent;
                            }

                            function downloadReceipt() {
                                const { jsPDF } = window.jspdf;
                                const doc = new jsPDF();
                                
                                // Get receipt content
                                const title = document.getElementById('receiptTitle').textContent;
                                const subtitle = document.getElementById('receiptSubtitle').textContent;
                                const receiptNumber = document.getElementById('receiptNumber').textContent;
                                const receiptDate = document.getElementById('receiptDate').textContent;
                                const receiptAmount = document.getElementById('receiptAmount').textContent;
                                const receiptMethod = document.getElementById('receiptMethod').textContent;
                                const receiptName = document.getElementById('receiptName').textContent;
                                const receiptUserType = document.getElementById('receiptUserType').textContent;
                                const receiptStatus = document.getElementById('receiptStatus').textContent;
                                const footerText = document.getElementById('receiptFooterText').textContent;

                                // Add content to PDF
                                doc.setFontSize(20);
                                doc.text(title, 105, 20, { align: 'center' });
                                doc.setFontSize(12);
                                doc.text(subtitle, 105, 30, { align: 'center' });
                                doc.setFontSize(10);
                                doc.text(`Receipt Number: ${receiptNumber}`, 20, 45);
                                doc.text(`Date: ${receiptDate}`, 20, 55);
                                doc.text(`Amount: ${receiptAmount}`, 20, 65);
                                doc.text(`Payment Method: ${receiptMethod}`, 20, 75);
                                doc.text(`Paid By: ${receiptName}`, 20, 85);
                                doc.text(`User Type: ${receiptUserType}`, 20, 95);
                                doc.text(`Status: ${receiptStatus}`, 20, 105);
                                
                                // Add role-specific details if available
                                const roleSpecificDetails = document.getElementById('roleSpecificDetails');
                                if (roleSpecificDetails && roleSpecificDetails.innerHTML.trim()) {
                                    const details = roleSpecificDetails.getElementsByClassName('info-group');
                                    let yPos = 120;
                                    for (let detail of details) {
                                        const label = detail.getElementsByTagName('label')[0].textContent;
                                        const value = detail.getElementsByTagName('p')[0].textContent;
                                        doc.text(`${label}: ${value}`, 20, yPos);
                                        yPos += 10;
                                    }
                                }

                                // Add footer
                                doc.setFontSize(8);
                                doc.text(footerText, 105, 180, { align: 'center' });
                                doc.text('Thank you for your payment!', 105, 190, { align: 'center' });

                                doc.save('TEBZ_Payment_Receipt.pdf');
                            }

                            function updatePaymentDetails(paymentMethod, details) {
                                const paymentDetailsDiv = document.getElementById('paymentDetails');
                                let detailsHtml = '';
                                switch(paymentMethod) {
                                    case 'gcash':
                                        detailsHtml = `<div class='info-group'><label class='text-muted small'>GCash Number</label><p class='mb-0'>${details.gcash_number}</p></div><div class='info-group'><label class='text-muted small'>GCash Account Name</label><p class='mb-0'>${details.gcash_name}</p></div>`;
                                        break;
                                    case 'bank':
                                        detailsHtml = `<div class='info-group'><label class='text-muted small'>Bank Name</label><p class='mb-0'>${details.bank_name}</p></div><div class='info-group'><label class='text-muted small'>Bank Account Number</label><p class='mb-0'>${details.bank_account}</p></div><div class='info-group'><label class='text-muted small'>Bank Account Name</label><p class='mb-0'>${details.bank_account_name}</p></div>`;
                                        break;
                                    case 'cash':
                        detailsHtml = `<div class='info-group'><label class='text-muted small'>Reference Number</label><p class='mb-0'>${details.reference_number}</p></div>`;
                        break;
                }
                paymentDetailsDiv.innerHTML = detailsHtml;
            }
            </script>
            <style>
            .receipt-container { max-width: 800px; margin: 0 auto; padding: 20px; background: #fff; }
            .receipt-logo { max-height: 60px; margin-bottom: 1rem; }
            .receipt-header { border-bottom: 2px solid #e9ecef; padding-bottom: 1.5rem; }
            .receipt-info { padding: 1.5rem 0; }
            .info-group { margin-bottom: 1rem; }
            .info-group label { display: block; margin-bottom: 0.25rem; color: #6c757d; font-size: 0.875rem; }
            .info-group p { font-size: 1rem; color: #212529; }
            .receipt-footer { padding-top: 1.5rem; border-top: 2px solid #e9ecef; }
            @media print { body * { visibility: hidden; } #receiptDetails, #receiptDetails * { visibility: visible; } #receiptDetails { position: absolute; left: 0; top: 0; width: 100%; } .receipt-actions { display: none; } }
            </style>

            <script>
            // Search Driver functionality
            document.getElementById('searchDriver')?.addEventListener('keyup', function() {
                var searchValue = this.value.toLowerCase();
                var rows = document.querySelectorAll("#driverTable tbody tr");
                rows.forEach(function(row) {
                    var name = row.cells[0].textContent.toLowerCase();
                    var email = row.cells[1].textContent.toLowerCase();
                    row.style.display = name.includes(searchValue) || email.includes(searchValue) ? '' : 'none';
                });
            });

            // Modal handling
            function openAssignModal(driverId, driverName, driverEmail) {
                // Set the modal content
                document.getElementById('driverName').textContent = driverName;
                document.getElementById('driverEmail').textContent = driverEmail;
                document.getElementById('driverId').value = driverId;
                
                // Reset the form
                const form = document.getElementById('assignJeepneyForm');
                if (form) {
                    form.reset();
                    form.classList.remove('was-validated');
                }
                
                // Show the modal
                const modalElement = document.getElementById('assignJeepneyModal');
                const modal = new bootstrap.Modal(modalElement);
                modal.show();
            }

            // Form submission confirmation
            function confirmAssign(e) {
                e.preventDefault();
                Swal.fire({
                    title: 'Confirm Assign?',
                    text: "Are you sure you want to assign this jeepney to the driver?",
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonText: 'Yes, Assign',
                    cancelButtonText: 'Cancel',
                    confirmButtonColor: '#198754'
                }).then((result) => {
                    if (result.isConfirmed) {
                        e.target.submit();
                    }
                });
            }

            function checkPaymentStatus() {
                fetch('check_payment_status.php')
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            const paymentStatus = document.getElementById('paymentStatus');
                            const receiptSection = document.getElementById('receiptSection');
                            const paymentForm = document.getElementById('paymentForm');
                            
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
                                        // Update receipt details
                                        document.getElementById('receiptNumber').textContent = data.receipt.receipt_number;
                                        document.getElementById('receiptAmount').textContent = data.receipt.amount;
                                        document.getElementById('receiptMethod').textContent = data.receipt.method;
                                        document.getElementById('receiptDate').textContent = new Date(data.receipt.date).toLocaleDateString();
                                        document.getElementById('receiptName').textContent = data.receipt.name;
                                        document.getElementById('receiptStatus').textContent = data.receipt.status;
                                        document.getElementById('receiptUserType').textContent = data.receipt.user_type;

                                        // Update receipt content based on user type
                                        updateReceiptContent(data.receipt.user_type, data.receipt);

                                        // Update payment details based on payment method
                                        if (data.receipt.method) {
                                            updatePaymentDetails(data.receipt.method, data.receipt);
                                        }
                                    }
                                    if (paymentForm) {
                                        paymentForm.style.display = 'none';
                                    }
                                } else {
                                    if (receiptSection) {
                                        receiptSection.style.display = 'none';
                                    }
                                    if (paymentForm) {
                                        paymentForm.style.display = 'block';
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
            .jeepney-details {
                font-size: 0.85rem;
                line-height: 1.4;
            }
            .jeepney-details small {
                color: #6c757d;
            }
            </style>

    
            <!-- Jeepney Assignment Modal -->
            <div class="modal fade" id="assignJeepneyModal" tabindex="-1" aria-labelledby="assignJeepneyModalLabel" aria-hidden="true">
                <div class="modal-dialog modal-lg modal-dialog-centered">
                    <div class="modal-content card shadow-sm">
                        <div class="modal-header bg-success text-white text-center">
                            <h5 class="modal-title w-100" id="assignJeepneyModalLabel">Assign Jeepney to <span id="driverName"></span></h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <p class="mb-4"><strong>Email:</strong> <span id="driverEmail"></span></p>

                            <form id="assignJeepneyForm" action="process_assign_jeepney.php" method="POST" class="mx-auto" style="max-width: 500px;">
                                <input type="hidden" name="driver_id" id="driverId">
                                <input type="hidden" name="assign_jeepney" value="1">

                                <div class="form-floating mb-3">
                                    <select name="plate_number" id="plateNumber" class="form-select" required>
                                        <option value="">Select Plate Number</option>
                                        <?php while($jeepney = $available_jeepneys->fetch_assoc()): ?>
                                            <option value="<?= $jeepney['plate_number'] ?>" data-body="<?= $jeepney['body_number'] ?>" data-route="<?= $jeepney['route'] ?>">
                                                <?= $jeepney['plate_number'] ?>
                                            </option>
                                        <?php endwhile; ?>
                                    </select>
                                    <label>Plate Number</label>
                                </div>

                                <div class="form-floating mb-3">
                                    <input type="text" name="body_number" id="bodyNumber" class="form-control" placeholder="Body Number" readonly required>
                                    <label>Body Number</label>
                                </div>

                                <div class="form-floating mb-3">
                                    <input type="text" name="route" id="route" class="form-control" placeholder="Assigned Route" readonly required>
                                    <label>Assigned Route</label>
                                </div>

                                <div class="form-floating mb-3">
                                    <textarea name="notes" id="notes" class="form-control" placeholder="Notes" style="height: 100px"></textarea>
                                    <label>Notes (Optional)</label>
                                </div>

                                <button type="submit" class="btn btn-success w-100">
                                    <i class="bi bi-truck-front me-1"></i> Assign Jeepney
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            <script>
            // ... existing code ...

            // Update jeepney details when plate number is selected
            document.getElementById('plateNumber')?.addEventListener('change', function() {
                const selectedOption = this.options[this.selectedIndex];
                const bodyNumber = selectedOption.getAttribute('data-body');
                const route = selectedOption.getAttribute('data-route');
                
                document.getElementById('bodyNumber').value = bodyNumber || '';
                document.getElementById('route').value = route || '';
            });

            // Form submission handling
            document.getElementById('assignJeepneyForm')?.addEventListener('submit', function(e) {
                e.preventDefault();
                
                Swal.fire({
                    title: 'Confirm Assignment',
                    text: "Are you sure you want to assign this jeepney to the driver?",
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonText: 'Yes, Assign',
                    cancelButtonText: 'Cancel',
                    confirmButtonColor: '#198754'
                }).then((result) => {
                    if (result.isConfirmed) {
                        // Get form data
                        const formData = new FormData(this);
                        
                        // Send AJAX request
                        fetch('process_assign_jeepney.php', {
                            method: 'POST',
                            body: formData
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                Swal.fire({
                                    icon: 'success',
                                    title: 'Success!',
                                    text: data.message,
                                    showConfirmButton: false,
                                    timer: 1500
                                }).then(() => {
                                    // Reload the page to show updated data
                                    location.reload();
                                });
                            } else {
                                throw new Error(data.message);
                            }
                        })
                        .catch(error => {
                            Swal.fire({
                                icon: 'error',
                                title: 'Error!',
                                text: error.message || 'Failed to assign jeepney. Please try again.'
                            });
                        });
                    }
                });
            });

            // ... existing code ...
            </script>

            <!-- Assignment History Modal -->
            <div class="modal fade" id="assignmentHistoryModal" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog modal-lg modal-dialog-centered">
                    <div class="modal-content border-0 shadow">
                        <div class="modal-header bg-primary text-white py-4">
                            <h5 class="modal-title d-flex align-items-center mb-0">
                                <i class="bi bi-clock-history me-2"></i>
                                Assignment History
                            </h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body p-4">
                            <div class="table-responsive">
                                <table class="table table-hover mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Driver</th>
                                            <th>Jeepney</th>
                                            <th>Route</th>
                                            <th>Assigned Date</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody id="historyTableBody">
                                        <!-- History data will be loaded here -->
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <style>
            /* Advanced Design Styles */
            .card {
                border: none;
                border-radius: 15px;
                box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
                margin-bottom: 1.5rem;
            }

            .card-header {
                border-radius: 15px 15px 0 0 !important;
                border-bottom: none;
            }

            /* Table Styling */
            .table {
                margin: 0;
            }

            .table th {
                background: #f8f9fa;
                font-weight: 600;
                padding: 1rem;
                border-bottom: 2px solid #e9ecef;
            }

            .table td {
                padding: 1.25rem 1rem;
                vertical-align: middle;
            }

            /* Button Styling */
            .btn {
                border-radius: 8px;
                padding: 0.6rem 1.2rem;
                transition: all 0.3s ease;
                font-weight: 500;
            }

            .btn:hover {
                transform: translateY(-2px);
                box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            }

            /* Badge Styling */
            .badge {
                font-weight: 500;
                padding: 0.5em 0.8em;
                border-radius: 6px;
                font-size: 0.75rem;
                letter-spacing: 0.3px;
                text-transform: uppercase;
            }

            /* Form Control Styling */
            .form-control, .form-select {
                border-width: 2px;
                padding: 0.75rem 1rem;
                font-size: 0.95rem;
            }

            .form-control:focus, .form-select:focus {
                box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.1);
                border-color: #0d6efd;
            }

            .form-floating > label {
                padding: 1rem;
            }

            /* Modal Styling */
            .modal-content {
                border-radius: 15px;
                overflow: hidden;
            }

            .modal-header {
                border-bottom: none;
            }

            .modal-body {
                padding: 1.5rem;
            }

            /* Responsive Adjustments */
            @media (max-width: 768px) {
                .card-body {
                    padding: 1rem;
                }
                
                .table td {
                    padding: 1rem 0.75rem;
                }
                
                .btn {
                    padding: 0.5rem 1rem;
                }
            }
            </style>

            <script>
            // Add animation classes to elements
            document.addEventListener('DOMContentLoaded', function() {
                const cards = document.querySelectorAll('.card');
                cards.forEach((card, index) => {
                    card.classList.add('fade-in');
                    card.style.animationDelay = `${index * 0.1}s`;
                });
                
                const tableRows = document.querySelectorAll('tbody tr');
                tableRows.forEach((row, index) => {
                    row.classList.add('slide-up');
                    row.style.animationDelay = `${index * 0.05}s`;
                });
            });
            </script>

            <script>
            // Form validation
            (function () {
                'use strict'
                var forms = document.querySelectorAll('.needs-validation')
                Array.prototype.slice.call(forms).forEach(function (form) {
                    form.addEventListener('submit', function (event) {
                        if (!form.checkValidity()) {
                            event.preventDefault()
                            event.stopPropagation()
                        }
                        form.classList.add('was-validated')
                    }, false)
                })
            })()

            // Search functionality
            document.getElementById('searchDriver').addEventListener('keyup', function() {
                const searchValue = this.value.toLowerCase();
                const rows = document.querySelectorAll("#driversTable tbody tr");
                
                rows.forEach(row => {
                    const name = row.querySelector('h6').textContent.toLowerCase();
                    const email = row.cells[1].textContent.toLowerCase();
                    row.style.display = name.includes(searchValue) || email.includes(searchValue) ? '' : 'none';
                });
            });

            // Modal handling
            function openAssignModal(driverId, driverName, driverEmail, memberSince) {
                document.getElementById('driverName').textContent = driverName;
                document.getElementById('driverEmail').textContent = driverEmail;
                document.getElementById('memberSince').textContent = memberSince;
                document.getElementById('driverId').value = driverId;
                
                const modal = new bootstrap.Modal(document.getElementById('assignJeepneyModal'));
                modal.show();
            }

            // Form submission with confirmation
            document.getElementById('assignJeepneyForm').addEventListener('submit', function(e) {
                e.preventDefault();
                
                if (this.checkValidity()) {
                    // Show loading state
                    const submitBtn = this.querySelector('button[type="submit"]');
                    const originalBtnText = submitBtn.innerHTML;
                    submitBtn.disabled = true;
                    submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Assigning...';

                    // Get form data
                    const formData = new FormData(this);

                    // Send request
                    fetch('process_assign_jeepney.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            // Show success message
                            Swal.fire({
                                title: 'Success!',
                                text: data.message,
                                icon: 'success',
                                confirmButtonColor: '#198754'
                            }).then(() => {
                                // Close modal and refresh page
                                const modal = bootstrap.Modal.getInstance(document.getElementById('assignJeepneyModal'));
                                modal.hide();
                                location.reload();
                            });
                        } else {
                            // Show error message
                            Swal.fire({
                                title: 'Error!',
                                text: data.message,
                                icon: 'error',
                                confirmButtonColor: '#dc3545'
                            });
                        }
                    })
                    .catch(error => {
                        // Show error message
                        Swal.fire({
                            title: 'Error!',
                            text: 'An error occurred while processing your request.',
                            icon: 'error',
                            confirmButtonColor: '#dc3545'
                        });
                    })
                    .finally(() => {
                        // Reset button state
                        submitBtn.disabled = false;
                        submitBtn.innerHTML = originalBtnText;
                    });
                } else {
                    this.classList.add('was-validated');
                }
            });
        });
    </script>

    <style>
    .search-box .input-group-text {
        border-radius: 20px 0 0 20px;
    }

    .search-box .form-control {
        border-radius: 0 20px 20px 0;
    }

    .table th {
        font-weight: 600;
        text-transform: uppercase;
        font-size: 0.8rem;
    }

    .table td {
        vertical-align: middle;
    }

    .badge {
        padding: 0.5em 0.8em;
        font-weight: 500;
    }

    .btn-success {
        padding: 0.5rem 1rem;
        font-weight: 500;
    }

    .card {
        border: none;
        border-radius: 15px;
    }

    .card-header {
        border-radius: 15px 15px 0 0 !important;
    }

    .form-floating > .form-control,
    .form-floating > .form-select {
        height: calc(3.5rem + 2px);
        line-height: 1.25;
    }

    .form-floating > textarea.form-control {
        height: 100px;
    }

    .form-floating > label {
        padding: 1rem 0.75rem;
    }

    .invalid-feedback {
        font-size: 0.875em;
    }
    </style>
                <script>
                function editJeepney(jeepney) {
                    document.getElementById('edit_jeepney_id').value = jeepney.id;
                    document.getElementById('edit_plate_number').value = jeepney.plate_number;
                    document.getElementById('edit_body_number').value = jeepney.body_number;
                    document.getElementById('edit_route').value = jeepney.route;
                    document.getElementById('edit_status').value = jeepney.status;
                    
                    new bootstrap.Modal(document.getElementById('editJeepneyModal')).show();
                }

                function deleteJeepney(jeepneyId) {
                    if (confirm('Are you sure you want to delete this jeepney?')) {
                        fetch('delete_jeepney.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                            },
                            body: JSON.stringify({ jeepney_id: jeepneyId })
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                location.reload();
                            } else {
                                alert('Failed to delete jeepney: ' + data.message);
                            }
                        })
                        .catch(error => {
                            console.error('Error:', error);
                            alert('An error occurred while deleting the jeepney');
                        });
                    }
                }
                </script>
            <?php endif; ?>
        </div>


<?php if(isset($_GET['driver_id'])):
$driver_id = $_GET['driver_id'];
$driver = $conn->query("SELECT * FROM users WHERE id='$driver_id'")->fetch_assoc();
?>



        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0" id="driversTable">
                    <thead class="bg-light">
                        <tr>
                            <th class="border-0">Driver Name</th>
                            <th class="border-0">Email</th>
                            <th class="border-0">Member Since</th>
                            <th class="border-0">Status</th>
                                    <th class="border-0">Assigned Jeepney</th>
                            <th class="border-0 text-center">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                                // Fetching drivers who have paid and their jeepney assignments
                        $driversQuery = "
                            SELECT u.id, u.firstName, u.lastName, u.email, p.payment_date,
                                           CASE 
                                               WHEN j.id IS NOT NULL AND j.status = 'Active' THEN 'Assigned'
                                               ELSE 'Available'
                                           END as jeepney_status,
                                           j.plate_number, j.body_number, j.route, j.assigned_date,
                                           j.id as assignment_id
                            FROM users u 
                            JOIN (
                                SELECT user_id, MAX(payment_date) AS latest_payment
                                FROM membership_payments
                                WHERE status = 'Confirmed'
                                GROUP BY user_id
                            ) latest_p ON u.id = latest_p.user_id
                            JOIN membership_payments p ON u.id = p.user_id AND p.payment_date = latest_p.latest_payment AND p.status = 'Confirmed'
                            LEFT JOIN jeepney_assignments j ON u.id = j.driver_id AND j.status = 'Active'
                            WHERE u.userType = 'driver'
                            ORDER BY p.payment_date DESC
                        ";
                        $driversResult = $conn->query($driversQuery);
                        
                        while($row = $driversResult->fetch_assoc()): 
                            $profileImage = file_exists("uploads/profile_{$row['id']}.jpg") 
                                ? "uploads/profile_{$row['id']}.jpg" 
                                : "uploads/default_profile.png";
                        ?>
                        <tr>
                            <td>
                                <div class="d-flex align-items-center">
                                    <img src="<?= htmlspecialchars($profileImage) ?>" 
                                                 class="rounded-circle me-2" 
                                                 width="32" 
                                                 height="32"
                                             alt="Profile">
                                    <div>
                                        <h6 class="mb-0"><?= htmlspecialchars($row['firstName'] . ' ' . $row['lastName']) ?></h6>
                                    </div>
                                </div>
                            </td>
                            <td><?= htmlspecialchars($row['email']) ?></td>
                            <td><?= date('M d, Y', strtotime($row['payment_date'])) ?></td>
                            <td>
                                        <span class="badge bg-<?= $row['jeepney_status'] == 'Assigned' ? 'success' : 'warning' ?>">
                                    <?= $row['jeepney_status'] ?>
                                </span>
                            </td>
                                    <td>
                                        <?php if($row['jeepney_status'] == 'Assigned'): ?>
                                            <div class="small">
                                                <div><strong>Plate:</strong> <?= htmlspecialchars($row['plate_number']) ?></div>
                                                <div><strong>Body:</strong> <?= htmlspecialchars($row['body_number']) ?></div>
                                                <div><strong>Route:</strong> <?= htmlspecialchars($row['route']) ?></div>
                                            </div>
                                        <?php else: ?>
                                            <span class="text-muted">Not assigned</span>
                                        <?php endif; ?>
                            </td>
                            <td class="text-center">
                                        <?php if($row['jeepney_status'] == 'Available'): ?>
                                            <button class="btn btn-sm btn-success" 
                                                    onclick="openAssignModal(<?= $row['id'] ?>, '<?= htmlspecialchars($row['firstName'] . ' ' . $row['lastName']) ?>', '<?= htmlspecialchars($row['email']) ?>')">
                                                <i class="bi bi-truck-front"></i> Assign
                                </button>
                                <?php else: ?>
                                            <div class="btn-group">
                                                <button class="btn btn-sm btn-primary" 
                                                        onclick="editAssignment(<?= $row['assignment_id'] ?>, '<?= htmlspecialchars($row['firstName'] . ' ' . $row['lastName']) ?>', '<?= htmlspecialchars($row['plate_number']) ?>', '<?= htmlspecialchars($row['body_number']) ?>', '<?= htmlspecialchars($row['route']) ?>')">
                                                    <i class="bi bi-pencil"></i> Edit
                                                </button>
                                                <button class="btn btn-sm btn-danger" 
                                                        onclick="deleteAssignment(<?= $row['assignment_id'] ?>, '<?= htmlspecialchars($row['firstName'] . ' ' . $row['lastName']) ?>')">
                                                    <i class="bi bi-trash"></i> Delete
                                                </button>
                                            </div>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- Header + Content Wrapper -->
    <div class="content-wrapper">
        <div class="content-header">
            <img src="../img/logo12.png" alt="JeepniGo Logo" style="height: 32px;">
            <h4 class="mb-0 fw-bold">Operator Dashboard</h4>
        </div>
        <div class="content-container">
        </div>
    </div>

<script>
// Form validation
(function () {
    'use strict'
    var forms = document.querySelectorAll('.needs-validation')
    Array.prototype.slice.call(forms).forEach(function (form) {
        form.addEventListener('submit', function (event) {
            if (!form.checkValidity()) {
                event.preventDefault()
                event.stopPropagation()
            }
            form.classList.add('was-validated')
        }, false)
    })
})()

// Search functionality
document.getElementById('searchDriver').addEventListener('keyup', function() {
    const searchValue = this.value.toLowerCase();
    const rows = document.querySelectorAll("#driversTable tbody tr");
    
    rows.forEach(row => {
        const name = row.querySelector('h6').textContent.toLowerCase();
        const email = row.cells[1].textContent.toLowerCase();
        row.style.display = name.includes(searchValue) || email.includes(searchValue) ? '' : 'none';
    });
});

// Modal handling
function openAssignModal(driverId, driverName, driverEmail) {
    // Set the modal content
    document.getElementById('driverName').textContent = driverName;
    document.getElementById('driverEmail').textContent = driverEmail;
    document.getElementById('driverId').value = driverId;
    
    // Reset the form
    const form = document.getElementById('assignJeepneyForm');
    if (form) {
        form.reset();
        form.classList.remove('was-validated');
    }
    
    // Show the modal
    const modalElement = document.getElementById('assignJeepneyModal');
    const modal = new bootstrap.Modal(modalElement);
    modal.show();
}

// Function to handle form submission
document.getElementById('assignJeepneyForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    if (this.checkValidity()) {
        // Show loading state
        const submitBtn = this.querySelector('button[type="submit"]');
        const originalBtnText = submitBtn.innerHTML;
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Assigning...';

        // Get form data
        const formData = new FormData(this);

        // Send request
        fetch('process_assign_jeepney.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Show success message
                Swal.fire({
                    title: 'Success!',
                    text: data.message,
                    icon: 'success',
                    confirmButtonColor: '#198754'
                }).then(() => {
                    // Close modal and refresh page
                    const modal = bootstrap.Modal.getInstance(document.getElementById('assignJeepneyModal'));
                    modal.hide();
                    location.reload();
                });
            } else {
                // Show error message
                Swal.fire({
                    title: 'Error!',
                    text: data.message,
                    icon: 'error',
                    confirmButtonColor: '#dc3545'
                });
            }
        })
        .catch(error => {
            // Show error message
            Swal.fire({
                title: 'Error!',
                text: 'An error occurred while processing your request.',
                icon: 'error',
                confirmButtonColor: '#dc3545'
            });
        })
        .finally(() => {
            // Reset button state
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalBtnText;
        });
    } else {
        this.classList.add('was-validated');
    }
});

// Function to edit assignment
function editAssignment(assignmentId, driverName, plateNumber, bodyNumber, route) {
    Swal.fire({
        title: `Edit Assignment for ${driverName}`,
        html: `
            <form id="editAssignmentForm" class="text-start">
                <input type="hidden" name="assignment_id" value="${assignmentId}">
                <div class="mb-3">
                    <label class="form-label">Plate Number</label>
                    <input type="text" name="plate_number" class="form-control" value="${plateNumber}" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Body Number</label>
                    <input type="text" name="body_number" class="form-control" value="${bodyNumber}" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Route</label>
                    <select name="route" class="form-select" required>
                        <option value="">Select Route</option>
                        <option value="Route 1" ${route === 'Route 1' ? 'selected' : ''}>Route 1</option>
                        <option value="Route 2" ${route === 'Route 2' ? 'selected' : ''}>Route 2</option>
                        <option value="Route 3" ${route === 'Route 3' ? 'selected' : ''}>Route 3</option>
                    </select>
                </div>
            </form>
        `,
        showCancelButton: true,
        confirmButtonText: 'Save Changes',
        cancelButtonText: 'Cancel',
        confirmButtonColor: '#3085d6',
        preConfirm: () => {
            const form = document.getElementById('editAssignmentForm');
            if (!form.checkValidity()) {
                form.reportValidity();
                return false;
            }
            
            // Create form data
            const formData = new FormData(form);
            
            // Submit the form
            return fetch('process_edit_jeepney.php', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                window.location.href = '?page=assignjeepney';
            })
            .catch(error => {
                Swal.showValidationMessage(`Request failed: ${error}`);
            });
        }
    }).then((result) => {
        if (result.isConfirmed) {
            Swal.fire({
                icon: 'success',
                title: 'Success!',
                text: 'Jeepney assignment has been updated successfully.',
                confirmButtonColor: '#3085d6'
            }).then(() => {
                location.reload();
            });
        }
    });
}

// Function to delete assignment
function deleteAssignment(assignmentId, driverName) {
    Swal.fire({
        title: 'Delete Assignment',
        text: `Are you sure you want to delete the jeepney assignment for ${driverName}?`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Yes, Delete',
        cancelButtonText: 'Cancel',
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6'
    }).then((result) => {
        if (result.isConfirmed) {
            // Show loading state
            Swal.fire({
                title: 'Processing...',
                text: 'Please wait while we remove the assignment.',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });

            // Send delete request
            fetch('process_delete_jeepney.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    assignment_id: assignmentId
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Deleted!',
                        text: 'The jeepney assignment has been removed successfully.',
                        confirmButtonColor: '#3085d6'
                    }).then(() => {
                        // Reload the page to show updated status
                        window.location.href = '?page=assignjeepney';
                    });
                } else {
                    throw new Error(data.message || 'Failed to delete assignment');
                }
            })
            .catch(error => {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: error.message || 'Failed to delete assignment. Please try again.',
                    confirmButtonColor: '#d33'
                });
            });
        }
    });
}
</script>

<style>
/* Add these styles for better button interactions */
.btn-group {
    display: flex;
    gap: 0.5rem;
}

.btn-group .btn {
    flex: 1;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
}

.btn-success, .btn-primary, .btn-danger {
    transition: all 0.3s ease;
}

.btn-success:hover, .btn-primary:hover, .btn-danger:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
}

/* Modal styles */
.modal-content {
    border: none;
    border-radius: 15px;
    box-shadow: 0 10px 30px rgba(0,0,0,0.1);
}

.modal-header {
    border-bottom: none;
    padding: 1.5rem;
}

.modal-body {
    padding: 1.5rem;
}

/* Form styles */
.form-control, .form-select {
    border: 2px solid #dee2e6;
    border-radius: 8px;
    padding: 0.75rem 1rem;
    transition: all 0.3s ease;
}

.form-control:focus, .form-select:focus {
    border-color: #0d6efd;
    box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.1);
}

/* Table styles */
.table td {
    vertical-align: middle;
}

.table th {
    font-weight: 600;
    text-transform: uppercase;
    font-size: 0.8rem;
    letter-spacing: 0.5px;
}

/* Responsive adjustments */
@media (max-width: 768px) {
    .btn-group {
        flex-direction: column;
    }
    
    .modal-dialog {
        margin: 1rem;
    }
}
</style>

<?php endif; ?>

<!-- Scripts for SweetAlert confirmation -->
<script>
function showOrientation(type) {
    const online = document.getElementById('onlineDetails');
    const inperson = document.getElementById('inpersonDetails');

    if (type === 'online') {
        online.classList.remove('d-none');
        inperson?.classList.add('d-none');
    } else if (type === 'inperson') {
        inperson.classList.remove('d-none');
        online?.classList.add('d-none');
    }
}
</script>

<!-- Scripts -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>

<?php if (isset($_SESSION['payment_confirmed']) && isset($_SESSION['confirmed_user'])): ?>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
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

<!-- Add this new modal for editing jeepney assignments -->
<div class="modal fade" id="editJeepneyModal" tabindex="-1" aria-labelledby="editJeepneyModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="editJeepneyModalLabel">Edit Jeepney Assignment for <span id="editDriverName"></span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="editJeepneyForm" action="process_edit_jeepney.php" method="POST" class="needs-validation" novalidate>
                    <input type="hidden" name="driver_id" id="editDriverId">
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-floating mb-3">
                                <input type="text" 
                                       name="plate_number" 
                                       id="editPlateNumber"
                                       class="form-control" 
                                       placeholder="Plate Number" 
                                       pattern="[A-Z0-9-]+" 
                                       required
                                       oninput="this.value = this.value.toUpperCase()">
                                <label>Plate Number</label>
                                <div class="invalid-feedback">Please enter a valid plate number</div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-floating mb-3">
                                <input type="text" 
                                       name="body_number" 
                                       id="editBodyNumber"
                                       class="form-control" 
                                       placeholder="Body Number" 
                                       required>
                                <label>Body Number</label>
                                <div class="invalid-feedback">Please enter the body number</div>
                            </div>
                        </div>
                    </div>

                    <div class="form-floating mb-3">
                        <select name="route" id="editRoute" class="form-select" required>
                            <option value="">Select Route</option>
                            <option value="Route 1">Route 1</option>
                            <option value="Route 2">Route 2</option>
                            <option value="Route 3">Route 3</option>
                        </select>
                        <label>Assigned Route</label>
                        <div class="invalid-feedback">Please select a route</div>
                    </div>

                    <div class="form-floating mb-3">
                        <textarea name="notes" 
                                  id="editNotes"
                                  class="form-control" 
                                  placeholder="Additional Notes" 
                                  style="height: 100px"></textarea>
                        <label>Additional Notes</label>
                    </div>

                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-primary btn-lg">
                            <i class="bi bi-save me-2"></i> Save Changes
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Add this JavaScript for the new functionality -->
<script>
// ... existing code ...

function openEditModal(driverId, driverName, plateNumber, bodyNumber, route, notes) {
    document.getElementById('editDriverName').textContent = driverName;
    document.getElementById('editDriverId').value = driverId;
    document.getElementById('editPlateNumber').value = plateNumber;
    document.getElementById('editBodyNumber').value = bodyNumber;
    document.getElementById('editRoute').value = route;
    document.getElementById('editNotes').value = notes;
    
    const modal = new bootstrap.Modal(document.getElementById('editJeepneyModal'));
    modal.show();
}

function confirmDelete(driverId, driverName) {
    Swal.fire({
        title: 'Delete Assignment?',
        text: `Are you sure you want to remove the jeepney assignment for ${driverName}?`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Yes, Delete',
        cancelButtonText: 'Cancel',
        confirmButtonColor: '#dc3545'
    }).then((result) => {
        if (result.isConfirmed) {
            // Show loading state
            Swal.fire({
                title: 'Processing...',
                text: 'Please wait while we remove the assignment.',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });
            
            // Send delete request
            fetch('process_delete_jeepney.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    driver_id: driverId
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Assignment Deleted',
                        text: 'The jeepney assignment has been removed successfully.',
                        confirmButtonColor: '#198754'
                    }).then(() => {
                        location.reload();
                    });
                } else {
                    throw new Error(data.message || 'Failed to delete assignment');
                }
            })
            .catch(error => {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: error.message || 'Failed to delete assignment. Please try again.',
                    confirmButtonColor: '#dc3545'
                });
            });
        }
    });
}

// Add form submission handler for edit form
document.getElementById('editJeepneyForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    if (this.checkValidity()) {
        Swal.fire({
            title: 'Save Changes?',
            text: 'Are you sure you want to update the jeepney assignment?',
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Yes, Save Changes',
            cancelButtonText: 'Cancel',
            confirmButtonColor: '#0d6efd'
        }).then((result) => {
            if (result.isConfirmed) {
                // Show loading state
                Swal.fire({
                    title: 'Saving Changes...',
                    text: 'Please wait while we update the assignment.',
                    allowOutsideClick: false,
                    didOpen: () => {
                        Swal.showLoading();
                    }
                });
                
                // Submit the form
                this.submit();
            }
        });
    }
});
</script>

<style>
/* Add these styles */
.btn-group {
    display: flex;
    gap: 0.5rem;
}

.btn-group .btn {
    flex: 1;
}

.modal-header {
    border-radius: 15px 15px 0 0;
}

.modal-content {
    border-radius: 15px;
}

.form-floating > .form-control:focus,
.form-floating > .form-select:focus {
    border-color: #0d6efd;
    box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.25);
}
</style>

<script>
function editAssignment(assignmentId, driverName) {
    // Fetch assignment data
    fetch(`get_assignment_data.php?assignment_id=${assignmentId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const assignment = data.data;
                
                // Show edit modal with data
                Swal.fire({
                    title: `Edit Assignment for ${driverName}`,
                    html: `
                        <form id="editAssignmentForm" class="text-start">
                            <input type="hidden" name="assignment_id" value="${assignment.assignment_id}">
                            <div class="mb-3">
                                <label class="form-label">Plate Number</label>
                                <input type="text" name="plate_number" class="form-control" value="${assignment.plate_number}" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Body Number</label>
                                <input type="text" name="body_number" class="form-control" value="${assignment.body_number}" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Route</label>
                                <input type="text" name="route" class="form-control" value="${assignment.route}" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Notes</label>
                                <textarea name="notes" class="form-control">${assignment.notes || ''}</textarea>
                            </div>
                        </form>
                    `,
                    showCancelButton: true,
                    confirmButtonText: 'Save Changes',
                    cancelButtonText: 'Cancel',
                    confirmButtonColor: '#198754',
                    showLoaderOnConfirm: true,
                    preConfirm: () => {
                        const form = document.getElementById('editAssignmentForm');
                        const formData = new FormData(form);
                        
                        return fetch('update_assignment.php', {
                            method: 'POST',
                            body: formData
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (!data.success) {
                                throw new Error(data.message || 'Failed to update assignment');
                            }
                            return data;
                        })
                        .catch(error => {
                            Swal.showValidationMessage(`Request failed: ${error.message}`);
                        });
                    }
                }).then((result) => {
                    if (result.isConfirmed) {
                        Swal.fire({
                            icon: 'success',
                            title: 'Success!',
                            text: 'Assignment updated successfully',
                            confirmButtonColor: '#198754'
                        }).then(() => {
                            location.reload();
                        });
                    }
                });
            } else {
                throw new Error(data.message || 'Failed to fetch assignment data');
            }
        })
        .catch(error => {
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: error.message || 'Failed to fetch assignment data',
                confirmButtonColor: '#dc3545'
            });
        });
}

function deleteAssignment(assignmentId, driverName) {
    Swal.fire({
        title: 'Delete Assignment',
        text: `Are you sure you want to delete the jeepney assignment for ${driverName}?`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Yes, Delete',
        cancelButtonText: 'Cancel',
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6'
    }).then((result) => {
        if (result.isConfirmed) {
            // Show loading state
            Swal.fire({
                title: 'Processing...',
                text: 'Please wait while we remove the assignment.',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });

            // Create form data
            const formData = new FormData();
            formData.append('assignment_id', assignmentId);

            // Send delete request
            fetch('process_delete_jeepney.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Deleted!',
                        text: 'The jeepney assignment has been removed successfully.',
                        confirmButtonColor: '#3085d6'
                    }).then(() => {
                        // Reload the page to show updated status
                        window.location.href = '?page=assignjeepney';
                    });
                } else {
                    throw new Error(data.message || 'Failed to delete assignment');
                }
            })
            .catch(error => {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: error.message || 'Failed to delete assignment. Please try again.',
                    confirmButtonColor: '#d33'
                });
            });
        }
    });
}
</script>

<?php if ($page === 'assignjeepney'): ?>
    <!-- Assign Jeepney Page -->
    <div class="container-fluid">
        <div class="row justify-content-center">
            <div class="col-12 col-lg-10">
                <div class="text-center mb-4">
                    <h4 class="text-success mb-2">Jeepney Assignment Management</h4>
                    <p class="text-muted mb-0">Assign and manage jeepney assignments for drivers</p>
                </div>
                <div class="card shadow-sm">
                    <div class="card-header bg-success text-white py-3">
                        <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
                            <h6 class="mb-0">
                                <i class="bi bi-people-fill me-2"></i>
                                Drivers Ready for Jeepney Assignment
                            </h6>
                            <div class="d-flex gap-3 flex-wrap">
                                <div class="search-box position-relative">
                                    <div class="input-group">
                                        <span class="input-group-text bg-white border-end-0">
                                            <i class="bi bi-search text-muted"></i>
                                        </span>
                                        <input type="text" 
                                               id="searchDriver" 
                                               class="form-control form-control-sm border-start-0" 
                                               placeholder="Search driver by name or email..."
                                               style="min-width: 250px;">
                                        <button class="btn btn-outline-secondary btn-sm" type="button" id="clearSearch">
                                            <i class="bi bi-x-lg"></i>
                                        </button>
                                    </div>
                                </div>
                                <div class="filter-group d-flex gap-2">
                                    <select class="form-select form-select-sm" id="filterStatus" style="min-width: 180px;">
                                        <option value="all">All Status</option>
                                        <option value="ready">Ready for Assignment</option>
                                        <option value="assigned">Currently Assigned</option>
                                    </select>
                                    <select class="form-select form-select-sm" id="filterRoute" style="min-width: 180px;">
                                        <option value="all">All Routes</option>
                                        <option value="Route 1">Route 1</option>
                                        <option value="Route 2">Route 2</option>
                                        <option value="Route 3">Route 3</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0" id="driversTable">
                                <thead class="bg-light">
                                    <tr>
                                        <th class="border-0">Driver Name</th>
                                        <th class="border-0">Email</th>
                                        <th class="border-0">Member Since</th>
                                        <th class="border-0">Status</th>
                                        <th class="border-0">Assigned Jeepney</th>
                                        <th class="border-0 text-center">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    // Fetching drivers who have paid and their jeepney assignments
                                    $driversQuery = "
                                        SELECT u.id, u.firstName, u.lastName, u.email, p.payment_date,
                                               CASE 
                                                   WHEN j.id IS NOT NULL AND j.status = 'Active' THEN 'Assigned'
                                                   ELSE 'Available'
                                               END as jeepney_status,
                                               j.plate_number, j.body_number, j.route, j.assigned_date,
                                               j.id as assignment_id
                                        FROM users u 
                                        JOIN (
                                            SELECT user_id, MAX(payment_date) AS latest_payment
                                            FROM membership_payments
                                            WHERE status = 'Confirmed'
                                            GROUP BY user_id
                                        ) latest_p ON u.id = latest_p.user_id
                                        JOIN membership_payments p ON u.id = p.user_id AND p.payment_date = latest_p.latest_payment AND p.status = 'Confirmed'
                                        LEFT JOIN jeepney_assignments j ON u.id = j.driver_id AND j.status = 'Active'
                                        WHERE u.userType = 'driver'
                                        ORDER BY p.payment_date DESC
                                    ";
                                    $driversResult = $conn->query($driversQuery);
                                    
                                    while($row = $driversResult->fetch_assoc()): 
                                        $profileImage = file_exists("uploads/profile_{$row['id']}.jpg") 
                                            ? "uploads/profile_{$row['id']}.jpg" 
                                            : "uploads/default_profile.png";
                                    ?>
                                    <tr>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <img src="<?= htmlspecialchars($profileImage) ?>" 
                                                     class="rounded-circle me-2" 
                                                     width="32" 
                                                     height="32"
                                             alt="Profile">
                                                <div>
                                                    <h6 class="mb-0"><?= htmlspecialchars($row['firstName'] . ' ' . $row['lastName']) ?></h6>
                                                </div>
                                            </div>
                                        </td>
                                        <td><?= htmlspecialchars($row['email']) ?></td>
                                        <td><?= date('M d, Y', strtotime($row['payment_date'])) ?></td>
                                        <td>
                                                    <span class="badge bg-<?= $row['jeepney_status'] == 'Assigned' ? 'success' : 'warning' ?>">
                                        <?= $row['jeepney_status'] ?>
                                    </span>
                                        </td>
                                        <td>
                                            <?php if($row['jeepney_status'] == 'Assigned'): ?>
                                                <div class="small">
                                                    <div><strong>Plate:</strong> <?= htmlspecialchars($row['plate_number']) ?></div>
                                                    <div><strong>Body:</strong> <?= htmlspecialchars($row['body_number']) ?></div>
                                                    <div><strong>Route:</strong> <?= htmlspecialchars($row['route']) ?></div>
                                                </div>
                                            <?php else: ?>
                                                <span class="text-muted">Not assigned</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-center">
                                                    <?php if($row['jeepney_status'] == 'Available'): ?>
                                                <button class="btn btn-sm btn-success" 
                                                        onclick="openAssignModal(<?= $row['id'] ?>, '<?= htmlspecialchars($row['firstName'] . ' ' . $row['lastName']) ?>', '<?= htmlspecialchars($row['email']) ?>')">
                                                    <i class="bi bi-truck-front"></i> Assign
                                </button>
                                <?php else: ?>
                                            <div class="btn-group">
                                                <button class="btn btn-sm btn-primary" 
                                                        onclick="editAssignment(<?= $row['assignment_id'] ?>, '<?= htmlspecialchars($row['firstName'] . ' ' . $row['lastName']) ?>', '<?= htmlspecialchars($row['plate_number']) ?>', '<?= htmlspecialchars($row['body_number']) ?>', '<?= htmlspecialchars($row['route']) ?>')">
                                                    <i class="bi bi-pencil"></i> Edit
                                                </button>
                                                <button class="btn btn-sm btn-danger" 
                                                        onclick="deleteAssignment(<?= $row['assignment_id'] ?>, '<?= htmlspecialchars($row['firstName'] . ' ' . $row['lastName']) ?>')">
                                                    <i class="bi bi-trash"></i> Delete
                                                </button>
                                            </div>
                                <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php if ($page === 'collect_boundaries'): ?>
    <!-- Collect Boundaries Section -->
    <div class="row mb-4">
        <!-- Statistics Cards -->
        <div class="col-md-3">
            <div class="card bg-primary text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h6 class="card-title">Pending Payments</h6>
                            <h3 id="pendingCount">0</h3>
                        </div>
                        <div class="align-self-center">
                            <i class="bi bi-clock-history fs-1"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-warning text-dark">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h6 class="card-title">Pending Amount</h6>
                            <h3 id="pendingAmount">₱0</h3>
                        </div>
                        <div class="align-self-center">
                            <i class="bi bi-cash-coin fs-1"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-success text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h6 class="card-title">Collected Payments</h6>
                            <h3 id="collectedCount">0</h3>
                        </div>
                        <div class="align-self-center">
                            <i class="bi bi-check-circle fs-1"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-info text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h6 class="card-title">Total Collected</h6>
                            <h3 id="collectedAmount">₱0</h3>
                        </div>
                        <div class="align-self-center">
                            <i class="bi bi-bank fs-1"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm mb-4">
        <div class="card-header bg-info text-white d-flex justify-content-between align-items-center">
            <h5 class="mb-0"><i class="bi bi-cash-coin me-2"></i>Boundary Payments Collection</h5>
            <div>
                <button class="btn btn-light btn-sm me-2" id="refreshStatsBtn">
                    <i class="bi bi-graph-up"></i> Refresh Stats
                </button>
                <button class="btn btn-light btn-sm me-2" id="fetchBoundariesBtn">
                    <i class="bi bi-arrow-repeat"></i> Refresh List
                </button>
            </div>
        </div>
        <div class="card-body">
            <div id="boundariesLoading" class="text-center text-muted mb-3" style="display:none;">
                <div class="spinner-border text-info" role="status"></div>
                <div>Loading boundary payments...</div>
            </div>
            <div id="boundariesTableContainer"></div>
        </div>
    </div>

    <script>
    const operator_id = <?= json_encode($_SESSION['user_id']) ?>;
    
    let lastBoundaryCount = 0;
    
    function fetchBoundaries() {
        document.getElementById('boundariesLoading').style.display = 'block';
        console.log('Fetching boundaries for operator_id:', operator_id);
        if (!operator_id || isNaN(operator_id)) {
            alert('ERROR: operator_id is missing or invalid! (' + operator_id + ')');
            document.getElementById('boundariesLoading').style.display = 'none';
            return;
        }
        fetch('pay_boundary.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'list', operator_id })
        })
        .then(res => res.json())
        .then(data => {
            document.getElementById('boundariesLoading').style.display = 'none';
            console.log('Boundary data received:', data);
            
            // Check for new payments
            if (data.success && data.boundaries) {
                const pendingCount = data.boundaries.filter(b => b.status === 'Pending').length;
                if (pendingCount > lastBoundaryCount && lastBoundaryCount > 0) {
                    const newPayments = pendingCount - lastBoundaryCount;
                    
                    // Show notification for new payments
                    Swal.fire({
                        icon: 'success',
                        title: '💰 New Boundary Payment!',
                        text: `You have ${newPayments} new boundary payment(s) to collect.`,
                        toast: true,
                        position: 'top-end',
                        showConfirmButton: false,
                        timer: 5000,
                        timerProgressBar: true,
                        background: '#d4edda',
                        color: '#155724'
                    });
                    
                    // Browser notification
                    if (Notification.permission === 'granted') {
                        new Notification('New Boundary Payment', {
                            body: `You have ${newPayments} new boundary payment(s) to collect`,
                            icon: '/tebz/img/logo12.png',
                            badge: '/tebz/img/logo12.png'
                        });
                    }
                }
                lastBoundaryCount = pendingCount;
            }
            
            if (data.success && data.boundaries.length > 0) {
                // Sort boundaries by paid_at descending
                data.boundaries.sort((a, b) => new Date(b.paid_at) - new Date(a.paid_at));
                // DEBUG: Show raw data
                document.getElementById('boundariesTableContainer').innerHTML = `<pre style='background:#f8f9fa;color:#333;padding:10px;border-radius:6px;'>DEBUG: ` + JSON.stringify(data.boundaries, null, 2) + `</pre>`;
                // Add a debug table for all boundaries
                let debugTable = `<div class='table-responsive'><table class='table table-bordered table-sm'><thead><tr>`;
                if (data.boundaries.length > 0) {
                    Object.keys(data.boundaries[0]).forEach(key => {
                        debugTable += `<th>${key}</th>`;
                    });
                    debugTable += `</tr></thead><tbody>`;
                    data.boundaries.forEach(row => {
                        debugTable += `<tr>`;
                        Object.values(row).forEach(val => {
                            debugTable += `<td>${val === null ? '' : val}</td>`;
                        });
                        debugTable += `</tr>`;
                    });
                    debugTable += `</tbody></table></div>`;
                }
                document.getElementById('boundariesTableContainer').innerHTML += debugTable;
                let html = `
                    <div class='table-responsive'>
                        <table class='table table-hover table-striped'>
                            <thead class='table-dark'>
                                <tr>
                                    <th>Driver</th>
                                    <th>Jeepney Details</th>
                                    <th>Route</th>
                                    <th>Amount</th>
                                    <th>Payment Method</th>
                                    <th>Reference</th>
                                    <th>Date</th>
                                    <th>Status</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                `;
                
                data.boundaries.forEach(b => {
                    // Only show boundaries with a valid reference_number (trimmed, not empty/null/N/A)
                    // const ref = (b.reference_number || '').trim().toLowerCase();
                    // if (!ref || ref === 'n/a' || ref === 'null') return;
                    const isCollected = b.status === 'Collected';
                    const statusClass = isCollected ? 'bg-success' : 'bg-warning text-dark';
                    const statusText = isCollected ? 'Collected' : 'Pending';
                    html += `
                        <tr>
                            <td>
                                <div class=\"d-flex align-items-center\">\n                                    <div class=\"avatar-sm bg-primary rounded-circle d-flex align-items-center justify-content-center me-2\">\n                                        <span class=\"text-white fw-bold\">${b.driver && b.driver.charAt(0) ? b.driver.charAt(0) : '?'}<\/span>\n                                    <\/div>\n                                    <div>\n                                        <h6 class=\"mb-0\">${b.driver || 'Unknown'}<\/h6>\n                                        <small class=\"text-muted\">Driver ID: ${b.driver_id}<\/small>\n                                    <\/div>\n                                <\/div>\n                            <\/td>\n                            <td>\n                                <div class=\"small\">\n                                    <div><strong>Plate:<\/strong> ${b.jeepney || 'N/A'}<\/div>\n                                    <div><strong>Body:<\/strong> ${b.body_number || 'N/A'}<\/div>\n                                    ${b.assigned_date ? `<div><strong>Assigned:<\/strong> ${new Date(b.assigned_date).toLocaleDateString()}<\/div>` : ''}
                                <\/div>\n                            <\/td>\n                            <td>\n                                <span class='badge bg-info'>${b.route || 'N/A'}<\/span>\n                            <\/td>\n                            <td>\n                                <span class=\"fw-bold text-success\">₱${parseFloat(b.amount).toLocaleString()}<\/span>\n                            <\/td>\n                            <td>\n                                <span class=\"badge bg-secondary\">${b.payment_method || 'N/A'}<\/span>\n                            <\/td>\n                            <td>\n                                <small class=\"text-muted\">${b.reference_number}<\/small>\n                            <\/td>\n                            <td>\n                                <div class=\"small\">\n                                    <div>${b.paid_at ? new Date(b.paid_at).toLocaleDateString() : 'N/A'}<\/div>\n                                    <div class=\"text-muted\">${b.paid_at ? new Date(b.paid_at).toLocaleTimeString() : ''}<\/div>\n                                <\/div>\n                            <\/td>\n                            <td>\n                                <span class='badge ${statusClass}'>${statusText}<\/span>\n                            <\/td>\n                            <td>\n                    `;
                    if (!isCollected) {
                        html += `
                            <button class='btn btn-sm btn-success' onclick=\"confirmBoundary(${b.id}, this)\">\n                                <i class=\"bi bi-check-circle me-1\"></i>Confirm\n                            </button>\n                        `;
                    } else {
                        html += `
                            <span class=\"text-muted\">\n                                <i class=\"bi bi-check-circle text-success\"></i> Collected\n                            </span>\n                        `;
                    }
                    html += `
                            </td>\n                        </tr>\n                    `;
                });
                
                html += '</tbody></table></div>';
                document.getElementById('boundariesTableContainer').innerHTML = html;
            } else {
                document.getElementById('boundariesTableContainer').innerHTML = `
                    <div class="alert alert-info text-center">
                        <i class="bi bi-info-circle fs-1 d-block mb-3"></i>
                        <h5>No Boundary Payments Found</h5>
                        <p class="mb-0">There are currently no boundary payments to collect.</p>
                    </div>
                `;
            }
        })
        .catch(() => {
            document.getElementById('boundariesLoading').style.display = 'none';
            document.getElementById('boundariesTableContainer').innerHTML = `
                <div class="alert alert-danger text-center">
                    <i class="bi bi-exclamation-triangle fs-1 d-block mb-3"></i>
                    <h5>Failed to Load Data</h5>
                    <p class="mb-0">Unable to load boundary payments. Please try again.</p>
                </div>
            `;
        });
    }

    function fetchStats() {
        fetch('pay_boundary.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'stats', operator_id })
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                document.getElementById('pendingCount').textContent = data.stats.pending.count;
                document.getElementById('pendingAmount').textContent = '₱' + parseFloat(data.stats.pending.total || 0).toLocaleString();
                document.getElementById('collectedCount').textContent = data.stats.collected.count;
                document.getElementById('collectedAmount').textContent = '₱' + parseFloat(data.stats.collected.total || 0).toLocaleString();
            }
        })
        .catch(error => {
            console.error('Error fetching stats:', error);
        });
    }

    function confirmBoundary(id, btn) {
        console.log('[DEBUG] Confirm button clicked for boundary ID:', id);
        Swal.fire({
            title: 'Confirm Collection?',
            text: 'Are you sure you want to mark this boundary payment as collected?',
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Yes, Confirm',
            cancelButtonText: 'Cancel',
            confirmButtonColor: '#198754'
        }).then((result) => {
            if (result.isConfirmed) {
                btn.disabled = true;
                const originalHTML = btn.innerHTML;
                btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';
                // Debug: show payload
                const payload = { action: 'confirm', id };
                console.log('[DEBUG] Sending fetch to pay_boundary.php with payload:', payload);
                fetch('pay_boundary.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload)
                })
                .then(res => {
                    console.log('[DEBUG] Received response status:', res.status);
                    return res.json().then(data => ({ status: res.status, data }));
                })
                .then(({ status, data }) => {
                    console.log('[DEBUG] Response JSON:', data);
                    if (data.success) {
                        Swal.fire({
                            icon: 'success',
                            title: '🎉 Payment Collected!',
                            text: 'The boundary payment has been marked as collected successfully.',
                            confirmButtonColor: '#198754',
                            timer: 3000,
                            timerProgressBar: true,
                            showConfirmButton: false
                        }).then(() => {
                            fetchBoundaries();
                            fetchStats();
                        });
                    } else {
                        throw new Error(data.message || 'Failed to confirm payment');
                    }
                })
                .catch(error => {
                    console.error('[DEBUG] Error during confirmation:', error);
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: error.message || 'Failed to confirm payment. Please try again.',
                        confirmButtonColor: '#dc3545'
                    });
                    btn.disabled = false;
                    btn.innerHTML = originalHTML;

                });
            }
        });
    }
    
    // Event listeners
    document.getElementById('refreshStatsBtn').addEventListener('click', fetchStats);
    document.getElementById('fetchBoundariesBtn').addEventListener('click', fetchBoundaries);
    
    // Request notification permission
    if (Notification.permission !== 'granted' && Notification.permission !== 'denied') {
        Notification.requestPermission();
    }
    
    // Initial load with error handling
    console.log('Starting boundary system with operator_id:', operator_id);
    
    // Force load immediately and then again after 1 second
    fetchBoundaries();
    fetchStats();
    
    setTimeout(() => {
        fetchBoundaries();
        fetchStats();
    }, 1000);
    
    // Auto-refresh every 10 seconds for better responsiveness
    setInterval(() => {
        fetchBoundaries();
        fetchStats();
    }, 10000);
    

    </script>

    <style>
    .avatar-sm {
        width: 32px;
        height: 32px;
        font-size: 14px;
    }
    
    .table th {
        font-weight: 600;
        text-transform: uppercase;
        font-size: 0.8rem;
        letter-spacing: 0.5px;
    }
    
    .card {
        border: none;
        border-radius: 15px;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
    }
    
    .card-header {
        border-radius: 15px 15px 0 0 !important;
        border-bottom: none;
    }
    
    .btn {
        border-radius: 8px;
        font-weight: 500;
    }
    
    .badge {
        font-weight: 500;
        padding: 0.5em 0.8em;
    }
    </style>

    <!-- After fetching boundaries, add a debug section to show all pending payments grouped by driver -->
    <script>
    if (data.success && data.boundaries.length > 0) {
        // Group pending payments by driver
        const pendingByDriver = {};
        data.boundaries.forEach(b => {
            const ref = (b.reference_number || '').trim().toLowerCase();
            if (b.status === 'Pending' && ref && ref !== 'n/a' && ref !== 'null') {
                if (!pendingByDriver[b.driver_id]) pendingByDriver[b.driver_id] = [];
                pendingByDriver[b.driver_id].push(b);
            }
        });
        let debugHtml = '<div class="alert alert-info"><b>Debug: Pending Payments by Driver</b><ul>';
        Object.keys(pendingByDriver).forEach(driverId => {
            const payments = pendingByDriver[driverId];
            debugHtml += `<li><b>${payments[0].driver || 'Unknown'} (ID: ${driverId})</b>: ${payments.length} pending payment(s)`;
            debugHtml += '<ul>';
            payments.forEach(p => {
                debugHtml += `<li>Ref: ${p.reference_number}, Amount: ₱${p.amount}, Date: ${p.paid_at}</li>`;
            });
            debugHtml += '</ul></li>';
        });
        debugHtml += '</ul></div>';
        document.getElementById('boundariesTableContainer').innerHTML = debugHtml + document.getElementById('boundariesTableContainer').innerHTML;
    }
    </script>
<?php endif; ?>

<?php if ($page === 'pay_cooperative'): ?>
<!-- Pay Cooperative Funds Page -->
<div class="card shadow-sm border-0" style="border-radius: 18px; overflow: hidden;">
    <div class="card-header bg-info text-white d-flex align-items-center" style="border-radius: 0;">
        <i class="bi bi-bank2 fs-3 me-3"></i>
        <h5 class="mb-0">Pay Cooperative Funds</h5>
    </div>
    <div class="card-body bg-light" style="padding: 2.5rem 2rem;">
        <div class="row g-4 align-items-stretch">
            <div class="col-md-6">
                <div class="p-4 h-100 bg-white rounded-4 shadow-sm border position-relative" style="min-height: 420px;">
                    <div class="mb-4 pb-2 border-bottom">
                        <h6 class="fw-bold text-info mb-1"><i class="bi bi-info-circle-fill me-2"></i>Instructions</h6>
                        <p class="mb-0 text-muted small">Please pay your cooperative fund using one of the methods below. Your payment will be reviewed and confirmed by the manager.</p>
                    </div>
                    <div class="mb-4">
                        <div class="d-flex align-items-center mb-2">
                            <span class="fw-bold text-dark me-2">Amount:</span>
                            <span class="fs-5 fw-bold text-info">₱500.00</span>
                        </div>
                        <div class="mb-2">
                            <span class="fw-bold text-dark">Payment Methods:</span>
                        </div>
                        <?php
                        require_once 'get_payment_instructions.php';
                        $instructions = getPaymentInstructions();
                        ?>
                        <div class="row g-2">
                            <div class="col-12">
                                <div class="bg-light border rounded-3 p-3 mb-2">
                                    <div class="d-flex align-items-center mb-2">
                                        <i class="bi bi-phone-vibrate fs-4 text-info me-2"></i>
                                        <span class="fw-semibold">GCash</span>
                                    </div>
                                    <div class="ms-4">
                                        <div class="small text-muted">GCash Number:</div>
                                        <div class="fw-bold mb-1 text-dark"><?= htmlspecialchars($instructions['gcash']['number']); ?></div>
                                        <div class="small text-muted">Account Name:</div>
                                        <div class="fw-bold text-dark"><?= htmlspecialchars($instructions['gcash']['name']); ?></div>
                                    </div>
                                </div>
                            </div>
                            <?php if (!empty($instructions['bank']['name'])): ?>
                            <div class="col-12">
                                <div class="bg-light border rounded-3 p-3 mb-2">
                                    <div class="d-flex align-items-center mb-2">
                                        <i class="bi bi-bank fs-4 text-info me-2"></i>
                                        <span class="fw-semibold">Bank Transfer</span>
                                    </div>
                                    <div class="ms-4">
                                        <div class="small text-muted">Bank Name:</div>
                                        <div class="fw-bold mb-1 text-dark"><?= htmlspecialchars($instructions['bank']['name']); ?></div>
                                        <div class="small text-muted">Account Number:</div>
                                        <div class="fw-bold mb-1 text-dark"><?= htmlspecialchars($instructions['bank']['account']); ?></div>
                                        <div class="small text-muted">Account Name:</div>
                                        <div class="fw-bold text-dark"><?= htmlspecialchars($instructions['bank']['account_name']); ?></div>
                                    </div>
                                </div>
                            </div>
                            <?php endif; ?>
                            <?php if (!empty($instructions['office'])): ?>
                            <div class="col-12">
                                <div class="bg-light border rounded-3 p-3 mb-2">
                                    <div class="d-flex align-items-center mb-2">
                                        <i class="bi bi-cash-stack fs-4 text-info me-2"></i>
                                        <span class="fw-semibold">Cash Payment</span>
                                    </div>
                                    <div class="ms-4">
                                        <div class="small text-muted">Office Address:</div>
                                        <div class="fw-bold text-dark"><?= htmlspecialchars($instructions['office']); ?></div>
                                    </div>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="p-4 h-100 bg-white rounded-4 shadow-sm border position-relative">
                    <div class="mb-4 pb-2 border-bottom">
                        <h6 class="fw-bold text-info mb-1"><i class="bi bi-credit-card-2-front me-2"></i>Submit Payment</h6>
                        <p class="mb-0 text-muted small">Fill out the form below to submit your cooperative fund payment.</p>
                    </div>
                    <form id="cooperativePaymentForm" class="payment-form" novalidate autocomplete="off">
                        <div class="form-group mb-3">
                            <label for="coop_amount" class="form-label fw-semibold">Amount</label>
                            <div class="input-group">
                                <span class="input-group-text bg-info text-white fw-bold">₱</span>
                                <input type="number" id="coop_amount" name="coop_amount" value="500" readonly class="form-control bg-light border-0 fw-bold text-dark">
                            </div>
                        </div>
                        <div class="form-group mb-3">
                            <label for="cooperative_payment_method" class="form-label fw-semibold">Payment Method</label>
                            <select id="cooperative_payment_method" name="cooperative_payment_method" required class="form-select">
                                <option value="">Choose payment method</option>
                                <option value="gcash">GCash</option>
                                <option value="bank">Bank Transfer</option>
                                <option value="cash">Cash Payment</option>
                            </select>
                        </div>
                        <div id="coopGcashFields" class="payment-fields d-none">
                            <div class="form-group mb-3">
                                <label for="gcash_number" class="form-label">GCash Number</label>
                                <input type="text" id="gcash_number" name="gcash_number" pattern="[0-9]{11}" placeholder="Enter 11-digit GCash number" class="form-control">
                            </div>
                            <div class="form-group mb-3">
                                <label for="gcash_name" class="form-label">GCash Account Name</label>
                                <input type="text" id="gcash_name" name="gcash_name" placeholder="Enter GCash account name" class="form-control">
                            </div>
                        </div>
                        <div id="coopBankFields" class="payment-fields d-none">
                            <div class="form-group mb-3">
                                <label for="bank_name" class="form-label">Bank Name</label>
                                <input type="text" id="bank_name" name="bank_name" placeholder="Enter bank name" class="form-control">
                            </div>
                            <div class="form-group mb-3">
                                <label for="bank_account" class="form-label">Bank Account Number</label>
                                <input type="text" id="bank_account" name="bank_account" placeholder="Enter bank account number" class="form-control">
                            </div>
                            <div class="form-group mb-3">
                                <label for="bank_account_name" class="form-label">Bank Account Name</label>
                                <input type="text" id="bank_account_name" name="bank_account_name" placeholder="Enter bank account name" class="form-control">
                            </div>
                        </div>
                        <div id="coopCashFields" class="payment-fields d-none">
                            <div class="form-group mb-3">
                                <label for="reference_number" class="form-label">Receipt Number</label>
                                <input type="text" id="reference_number" name="reference_number" placeholder="Enter receipt number" class="form-control">
                            </div>
                        </div>
                        <button type="submit" class="btn btn-info w-100 fw-bold py-2 mt-2" style="font-size: 1.1rem;">
                            <i class="bi bi-bank2 me-2"></i>Submit Cooperative Fund Payment
                        </button>
                    </form>
                    <div id="coopFundsList" class="mt-5"></div>
                </div>
            </div>
        </div>
    </div>
</div>
<style>
#coopFundsList table {
    background: #f8fafc;
    border-radius: 12px;
    overflow: hidden;
}
#coopFundsList th {
    background: #e3f0fa;
    color: #0d6efd;
    font-weight: 600;
    border: none;
}
#coopFundsList td {
    background: #fff;
    border: none;
    vertical-align: middle;
}
#coopFundsList .badge {
    font-size: 0.95em;
    padding: 0.5em 1em;
    border-radius: 8px;
}
.payment-form .form-label {
    color: #0d6efd;
    font-weight: 600;
}
.payment-form .form-control:focus, .payment-form .form-select:focus {
    border-color: #0d6efd;
    box-shadow: 0 0 0 0.15rem rgba(13,110,253,.15);
}
.payment-form .btn-info {
    background: linear-gradient(90deg, #0dcaf0 0%, #0d6efd 100%);
    border: none;
    transition: background 0.2s;
}
.payment-form .btn-info:hover {
    background: linear-gradient(90deg, #0d6efd 0%, #0dcaf0 100%);
}
.bg-light {
    background-color: #f8fafc !important;
}
</style>
<script>
// Show/hide payment fields based on method
function showCoopPaymentFields() {
    const method = document.getElementById('cooperative_payment_method').value;
    document.getElementById('coopGcashFields').classList.toggle('d-none', method !== 'gcash');
    document.getElementById('coopBankFields').classList.toggle('d-none', method !== 'bank');
    document.getElementById('coopCashFields').classList.toggle('d-none', method !== 'cash');
}
document.getElementById('cooperative_payment_method').addEventListener('change', showCoopPaymentFields);

// Submit cooperative fund payment via AJAX
const coopForm = document.getElementById('cooperativePaymentForm');
coopForm.addEventListener('submit', function(e) {
    e.preventDefault();
    if (!coopForm.checkValidity()) {
        coopForm.classList.add('was-validated');
        return;
    }
    const formData = new FormData(coopForm);
    const method = formData.get('cooperative_payment_method');
    const payload = { action: 'contribute', member_id: <?= json_encode($userId) ?>, amount: 500, payment_method: method };
    if (method === 'gcash') {
        payload.gcash_number = formData.get('gcash_number');
        payload.gcash_name = formData.get('gcash_name');
    } else if (method === 'bank') {
        payload.bank_name = formData.get('bank_name');
        payload.bank_account = formData.get('bank_account');
        payload.bank_account_name = formData.get('bank_account_name');
    } else if (method === 'cash') {
        payload.reference_number = formData.get('reference_number');
    }
    fetch('cooperative_fund.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            Swal.fire({
                icon: 'success',
                title: 'Payment Submitted!',
                text: data.message || 'Your cooperative fund payment has been submitted.',
                confirmButtonColor: '#0d6efd'
            });
            coopForm.reset();
            showCoopPaymentFields();
            fetchCoopFundsList();
        } else {
            throw new Error(data.message || 'Failed to submit payment');
        }
    })
    .catch(error => {
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: error.message || 'Failed to submit payment. Please try again.',
            confirmButtonColor: '#dc3545'
        });
    });
});

// Fetch and display cooperative fund payments for this user
function fetchCoopFundsList() {
    fetch('cooperative_fund.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'list', member_id: <?= json_encode($userId) ?> })
    })
    .then(res => res.json())
    .then(data => {
        if (data.success && data.funds && data.funds.length > 0) {
            let html = `<div class='table-responsive'><table class='table table-bordered'><thead><tr><th>Receipt #</th><th>Amount</th><th>Method</th><th>Date</th><th>Status</th></tr></thead><tbody>`;
            data.funds.forEach(f => {
                html += `<tr><td>${f.receipt_number || ''}</td><td>₱${parseFloat(f.amount).toLocaleString()}</td><td>${f.payment_method || ''}</td><td>${f.paid_at ? new Date(f.paid_at).toLocaleDateString() : ''}</td><td><span class='badge bg-${f.status === 'Confirmed' ? 'success' : 'warning'}'>${f.status}</span></td></tr>`;
            });
            html += '</tbody></table></div>';
            document.getElementById('coopFundsList').innerHTML = html;
        } else {
            document.getElementById('coopFundsList').innerHTML = `<div class='alert alert-info text-center mb-0'>No cooperative fund payments found.</div>`;
        }
    });
}
document.addEventListener('DOMContentLoaded', fetchCoopFundsList);
</script>
<?php endif; ?>

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

</body>
</html>
