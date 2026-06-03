<?php
session_start();
require_once __DIR__ . '/../db_config.php';

// 🔄 Refresh user_type from database in case it was updated by manager
$stmt = $conn->prepare("SELECT userType FROM users WHERE id = ?");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$result = $stmt->get_result();
if ($row = $result->fetch_assoc()) {
    $_SESSION['user_type'] = $row['userType']; // ✅ CORRECT!
}


// ✅ Check access after refreshing role
if (!isset($_SESSION['user_id'])) {
    header("Location: ../shared/index.php");
    exit();
}

// Only block users who are NOT yet verified and NOT 'passenger'
if ($_SESSION['user_type'] !== 'passenger') {
    // Check if they have verified submission
    $verifiedQuery = "SELECT role FROM submitted_requirements WHERE user_id = ? AND status = 'Verified' ORDER BY submitted_at DESC LIMIT 1";
    $stmt = $conn->prepare($verifiedQuery);
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $verifiedResult = $stmt->get_result();

    if ($verifiedResult->num_rows === 0) {
        header("Location: ../shared/index.php");
        exit();
    }
}



// Fetch session info
$userId = $_SESSION['user_id'];
$userFirstName = $_SESSION['user_firstName'] ?? "";
$userLastName = $_SESSION['user_lastName'] ?? "";
$userEmail = $_SESSION['user_email'] ?? "";
$profileImage = '../uploads/profile_' . $userId . '.jpg';

// If the profile image doesn't exist, use a placeholder
if (!file_exists(__DIR__ . '/' . $profileImage)) {
    $profileImage = '../img/logo12.png';
}

// Fetch latest submission
$submissionQuery = "SELECT * FROM submitted_requirements WHERE user_id = ? ORDER BY submitted_at DESC LIMIT 1";
$submissionStmt = $conn->prepare($submissionQuery);
$submissionStmt->bind_param("i", $userId);
$submissionStmt->execute();
$submissionResult = $submissionStmt->get_result();
$submission = $submissionResult->fetch_assoc();

// Handle which page to load
$page = $_GET['page'] ?? 'dashboard';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Passenger Dashboard | JeepniGo</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link href="../assets/css/styles.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"/>
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <link rel="stylesheet" href="https://unpkg.com/leaflet-routing-machine@3.2.12/dist/leaflet-routing-machine.css" />
    <script src="https://unpkg.com/leaflet-routing-machine@3.2.12/dist/leaflet-routing-machine.min.js"></script>
    <style>
        /* Emphasize exact fare and related info */
        #resPayable { font-size: 1.5rem; font-weight: 700; }
        #resFareMatch { font-size: 1rem; }
        .fare-table { font-size: 1rem; }
        /* Modal example routes select */
        #resExample { font-size: 1rem; }
        /* Optional: header sizing */
        .gradient-header h5 { font-size: 1.25rem; }
    </style>
    <style>
    /* Passenger theme overrides - CSS only (no logic changes) */
    .content-wrapper { background: transparent; position: relative; z-index: 1; }
    .content-container { 
        background: transparent; 
        position: relative; 
        z-index: 1;
        display: flex !important;
        justify-content: center !important;
        align-items: flex-start !important;
        padding: 20px !important;
    }
    .content { 
        max-width: 1160px; 
        width: 100% !important;
        margin: 0 auto !important; 
        position: relative; 
        z-index: 1;
    }

    /* Sidebar */
    .sidebar { background: linear-gradient(180deg, #263042 0%, #1e2532 100%) !important; }
    .sidebar-link.active { box-shadow: 0 10px 24px rgba(79,70,229,0.18); background: linear-gradient(135deg, #2563eb 0%, #7c3aed 100%) !important; }
    .sidebar-icon { box-shadow: inset 0 0 0 1px rgba(255,255,255,0.10); }

    /* Cards and surfaces */
    .card { border: 1px solid rgba(2,6,23,0.06); border-radius: 14px; box-shadow: 0 8px 20px rgba(2,6,23,0.06); }
    .card:hover { box-shadow: 0 14px 32px rgba(2,6,23,0.10); }
    .header-content { border: 1px solid rgba(2,6,23,0.06); border-radius: 16px; }
    .status-card, .map-card { border: 1px solid rgba(2,6,23,0.06); border-radius: 16px; }

    /* Buttons */
    .btn { border-radius: 12px; }
    .btn-primary { background-image: linear-gradient(135deg, var(--brand-primary), var(--brand-primary-2)); border: none; }
    .btn-outline-primary { border-color: var(--brand-primary); color: var(--brand-primary); }
    .btn-outline-primary:hover { background: var(--brand-primary); color: #fff; }
    .btn-success { background-image: linear-gradient(135deg, #16a34a, #22c55e); border: none; }
    .btn-warning { background-image: linear-gradient(135deg, #f59e0b, #f97316); border: none; color: #fff; }

    /* Badges */
    .badge { border-radius: 10px; }
    .badge.bg-primary { background-image: linear-gradient(135deg, var(--brand-primary), var(--brand-primary-2)); }
    </style>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
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

    /* Generic UI polish */
    .card { background: var(--card-bg); border: 1px solid rgba(2,6,23,0.06); border-radius: var(--radius); box-shadow: var(--shadow-sm); }
    .card:hover { box-shadow: var(--shadow-md); }
    .btn-primary { background-image: linear-gradient(135deg, var(--brand-primary), var(--brand-primary-2)); border: none; }
    .btn-success { background-image: linear-gradient(135deg, #16a34a, #22c55e); border: none; }
    .btn-warning { background-image: linear-gradient(135deg, #f59e0b, #f97316); border: none; color: #fff; }
    .btn-outline-primary { border-color: var(--brand-primary); color: var(--brand-primary); }
    .btn-outline-primary:hover { background: var(--brand-primary); color: #fff; }

    /* Accent chips */
    .badge.bg-primary { background-image: linear-gradient(135deg, var(--brand-primary), var(--brand-primary-2)); }

    /* CTA button on announcements */
    .apply-now-btn { 
        background-image: linear-gradient(135deg, var(--brand-primary), var(--brand-accent)); 
        border: none; 
        color: #fff; 
        letter-spacing: .3px;
        position: relative;
        z-index: 100;
        pointer-events: auto !important;
        cursor: pointer;
    }
    .apply-now-btn:hover { filter: brightness(1.05); transform: translateY(-1px); }
    
    /* Simple modal fix - just z-index, let Bootstrap handle everything else */
    body.modal-open {
        overflow: hidden;
    }
    
    /* Ensure modals appear above everything */
    .modal-backdrop {
        z-index: 1040;
        background-color: rgba(0, 0, 0, 0.5);
    }
    .modal {
        z-index: 1050;
    }
    
    /* Enhanced fare matrix modal styling */
    #fareModal .modal-content {
        border: none;
        border-radius: 12px;
        overflow: hidden;
    }
    
    #fareModal .modal-header {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        border-bottom: none;
        padding: 1.25rem 1.5rem;
    }
    
    #fareModal .modal-body {
        background: #ffffff;
    }
    
    #fareModal .fare-table {
        margin-bottom: 0;
    }
    
    #fareModal .fare-table thead th {
        border-bottom: 2px solid #dee2e6;
        padding: 1rem;
        font-size: 0.95rem;
    }
    
    #fareModal .fare-table tbody td {
        padding: 0.875rem 1rem;
        vertical-align: middle;
    }
    
    #fareModal .fare-table tbody tr:hover {
        background-color: #f8f9fa;
    }
    
    #fareModal .modal-footer {
        border-top: 1px solid #dee2e6;
        padding: 1rem 1.5rem;
    }
    .gradient-header { background: linear-gradient(135deg, var(--brand-primary), var(--brand-primary-2)); box-shadow: 0 8px 20px rgba(79,70,229,0.25); }
    .focus-field .form-control { border-radius: 10px; border: 2px solid #e5e7eb; transition: border-color .2s, box-shadow .2s; }
    .focus-field .form-control:focus { border-color: var(--brand-primary); box-shadow: 0 0 0 3px rgba(79,70,229,0.15); }
    .fade-slide-in { animation: fadeSlideIn .45s ease both; }
    @keyframes fadeSlideIn { from { opacity: 0; transform: translateY(6px); } to { opacity: 1; transform: translateY(0); } }
    </style>
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
        <li class="<?= $page === 'apply_driver_operator' ? 'active' : '' ?>">
          <a href="?page=apply_driver_operator" style="color: white; padding: 14px 20px; display: flex; align-items: center; text-decoration: none; transition: all 0.3s ease;">
            <i class="bi bi-megaphone-fill" style="font-size: 1.2rem; margin-right: 12px; width: 24px;"></i>
            <span style="font-size: 1rem; font-weight: 500;">Announcements</span>
          </a>
        </li>
        <li class="<?= $page === 'profile' ? 'active' : '' ?>">
          <a href="?page=profile" style="color: white; padding: 14px 20px; display: flex; align-items: center; text-decoration: none; transition: all 0.3s ease;">
            <i class="bi bi-person-circle" style="font-size: 1.2rem; margin-right: 12px; width: 24px;"></i>
            <span style="font-size: 1rem; font-weight: 500;">My Profile</span>
          </a>
        </li>
        <li class="<?= $page === 'reservations' ? 'active' : '' ?>">
          <a href="?page=reservations" style="color: white; padding: 14px 20px; display: flex; align-items: center; text-decoration: none; transition: all 0.3s ease;">
            <i class="bi bi-bookmark-check-fill" style="font-size: 1.2rem; margin-right: 12px; width: 24px;"></i>
            <span style="font-size: 1rem; font-weight: 500;">Reserve Seat</span>
          </a>
        </li>
        <li class="<?= $page === 'apply_cooperative' ? 'active' : '' ?>">
          <a href="?page=apply_cooperative" style="color: white; padding: 14px 20px; display: flex; align-items: center; text-decoration: none; transition: all 0.3s ease;">
            <i class="bi bi-building-fill" style="font-size: 1.2rem; margin-right: 12px; width: 24px;"></i>
            <span style="font-size: 1rem; font-weight: 500;">Apply Cooperative</span>
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

<!-- Sidebar (hidden on mobile) -->
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
                <i class="bi bi-shield-check me-1"></i>Passenger
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
            
            <!-- Announcement Link -->
            <li class="nav-item">
                <a class="nav-link sidebar-link <?= $page === 'apply_driver_operator' ? 'active' : '' ?>" href="?page=apply_driver_operator">
                    <div class="d-flex align-items-center">
                        <div class="sidebar-icon">
                            <i class="bi bi-megaphone-fill"></i>
                        </div>
                        <span class="sidebar-text">Announcements</span>
                        <?php if ($page === 'apply_driver_operator'): ?>
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
                        <span class="sidebar-text">My Profile</span>
                        <?php if ($page === 'profile'): ?>
                            <div class="sidebar-indicator"></div>
                        <?php endif; ?>
                    </div>
                </a>
            </li>

            <!-- Reservations Link -->
            <li class="nav-item">
                <a class="nav-link sidebar-link <?= $page === 'reservations' ? 'active' : '' ?>" href="?page=reservations">
                    <div class="d-flex align-items-center">
                        <div class="sidebar-icon">
                            <i class="bi bi-bookmark-check-fill"></i>
                        </div>
                        <span class="sidebar-text">Reserve Seat</span>
                        <?php if ($page === 'reservations'): ?>
                            <div class="sidebar-indicator"></div>
                        <?php endif; ?>
                    </div>
                </a>
            </li>

            

            <!-- Apply Cooperative Link -->
            <li class="nav-item">
                <a class="nav-link sidebar-link <?= $page === 'apply_cooperative' ? 'active' : '' ?>" href="?page=apply_cooperative">
                    <div class="d-flex align-items-center">
                        <div class="sidebar-icon">
                            <i class="bi bi-building-fill"></i>
                        </div>
                        <span class="sidebar-text">Apply Cooperative</span>
                        <?php if ($page === 'apply_cooperative'): ?>
                            <div class="sidebar-indicator"></div>
                        <?php endif; ?>
                    </div>
                </a>
            </li>

            <?php
            // Show role-specific dashboard if verified
            if ($submission && $submission['status'] === 'Verified') {
                $roleQuery = "SELECT role FROM submitted_requirements 
                              WHERE user_id = ? AND role IS NOT NULL AND role != '' 
                              ORDER BY submitted_at DESC LIMIT 1";
                $roleStmt = $conn->prepare($roleQuery);
                $roleStmt->bind_param("i", $userId);
                $roleStmt->execute();
                $roleResult = $roleStmt->get_result();
                $roleData = $roleResult->fetch_assoc();
                $appliedRole = $roleData['role'] ?? '';

                if ($appliedRole === 'Driver') {
                    echo '<li class="nav-item">
                        <a class="nav-link sidebar-link" href="driver_dashboard.php">
                            <div class="d-flex align-items-center">
                                <div class="sidebar-icon">
                                    <i class="bi bi-truck-front-fill"></i>
                                </div>
                                <span class="sidebar-text">Go to Driver Dashboard</span>
                            </div>
                        </a>
                    </li>';
                } elseif ($appliedRole === 'Operator') {
                    echo '<li class="nav-item">
                        <a class="nav-link sidebar-link" href="operator_dashboard.php">
                            <div class="d-flex align-items-center">
                                <div class="sidebar-icon">
                                    <i class="bi bi-gear-fill"></i>
                                </div>
                                <span class="sidebar-text">Go to Operator Dashboard</span>
                            </div>
                        </a>
                    </li>';
                }
            }
            ?>

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
.bg-gradient-dark { background: linear-gradient(180deg, #263042 0%, #1e2532 100%); }

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

.sidebar-link.active { color: white !important; background: linear-gradient(135deg, #2563eb 0%, #7c3aed 100%); box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4); }

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

.sidebar-footer .text-muted {
    color: #fff !important;
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

<!-- Professional Overrides -->
<style>
/* Layout & Surface */
.jeepney-location-container {
    background: linear-gradient(180deg, #f8fafc 0%, #eef2ff 100%);
    border: 1px solid #e5e7eb;
}
.header-content {
    background: #fff;
    border: 1px solid #e5e7eb;
    border-radius: 14px;
    box-shadow: 0 8px 24px rgba(2,6,23,.08);
}
.map-card {
    background: #fff;
    border: 1px solid #e5e7eb;
    border-radius: 14px;
    box-shadow: 0 10px 28px rgba(2,6,23,.08);
}
.map-header {
    background: linear-gradient(135deg, #2563eb, #7c3aed);
    padding: 1rem 1.25rem;
}
.status-section {
    background: #fff;
    border: 1px solid #e5e7eb;
    border-radius: 14px;
    box-shadow: 0 10px 28px rgba(2,6,23,.08);
}
.status-cards-container {
    display: grid;
    grid-template-columns: 1fr;
    gap: 1rem;
}
.status-card {
    border: 1px solid #e5e7eb;
    box-shadow: 0 8px 20px rgba(2,6,23,.06);
    border-radius: 12px;
    padding: 1rem 1rem;
}
.status-card:hover {
    transform: translateY(-1px);
}
.card-header-section {
    display: grid;
    grid-template-columns: 1fr auto;
    align-items: center;
}
.route-icon {
    border-radius: 10px;
    background: linear-gradient(135deg, #2563eb, #7c3aed);
    box-shadow: 0 8px 16px rgba(37,99,235,.22);
}
.route-name { font-weight: 700; letter-spacing: .2px; }
.status-badge { border: 1px solid rgba(2,6,23,.06); }

/* Actions */
.route-actions { gap: .5rem; }
.route-actions .btn, .route-actions .badge {
    border-radius: 10px;
    padding: .55rem 1rem;
    font-weight: 600;
}
.route-actions .btn-success { background-image: linear-gradient(135deg,#16a34a,#22c55e); border: none; }
.route-actions .btn-primary { background-image: linear-gradient(135deg,#4f46e5,#7c3aed); border: none; }
.route-actions .btn-warning { background-image: linear-gradient(135deg,#f59e0b,#f97316); border: none; color: #fff; }
.route-actions .btn-outline-secondary { border-color: #cbd5e1; color: #334155; }
.route-actions .btn-outline-secondary:hover { background: #e2e8f0; }

/* Info chips */
.info-item { background: #f8fafc; border: 1px solid #e5e7eb; }
.info-icon { border-radius: 8px; background: linear-gradient(135deg,#2563eb,#7c3aed); }

/* Trip tracker */
.trip-tracker .tracker-card {
    border: 1px solid #e5e7eb;
    background: linear-gradient(180deg, rgba(2,6,23,.02), rgba(2,6,23,.04));
}

/* Responsive */
@media (min-width: 992px) {
  .status-cards-container { grid-template-columns: 1fr; }
}
@media (min-width: 1200px) {
  .status-cards-container { grid-template-columns: 1fr; }
}
</style>

    <!-- Main Content -->
    <div class="content-wrapper" style="margin-left: 280px; width: calc(100% - 280px); min-height: 100vh; background: #f7f9fc;">
        <div class="content-header d-flex align-items-center gap-3" style="background: #ffffff; padding: 1rem 1.5rem; border-bottom: 1px solid rgba(0,0,0,0.05); box-shadow: 0 2px 8px rgba(0,0,0,0.05);">
            <img src="../img/logo12.png" alt="JeepniGo Logo" style="height: 32px;">
            <h4 class="mb-0 fw-bold">Passenger Dashboard</h4>
        </div>

        <!-- Content Container -->
        <div class="content-container" style="height: calc(100vh - 80px); overflow-y: auto; padding: 20px; display: flex; justify-content: center;">
            <div class="content <?= $page === 'dashboard' ? 'dashboard-page' : ($page === 'apply_driver_operator' ? 'announcement-page' : ($page === 'profile' ? 'profile-page' : ($page === 'apply_cooperative' ? 'cooperative-page' : 'dashboard-page'))); ?>" style="max-width: 1200px; width: 100%;">
    <?php if ($page === 'dashboard'): ?>
        <!-- Dashboard -->
                <div class="dashboard-header mb-4">
                    <div class="header-content">
                        <div class="header-icon">
                            <i class="bi bi-house-door"></i>
                        </div>
                        <div class="header-text">
                            <h2 class="header-title">Welcome, <?= htmlspecialchars($userFirstName . ' ' . $userLastName); ?>!</h2>
                            <p class="header-subtitle">Your next ride is just a few clicks away. Explore and plan your journey with JeepniGo!</p>
                        </div>
                    </div>
                </div>

<!-- Membership Submission Status -->
<?php if ($submission): ?>
    <?php
        $status = $submission['status'] ?? 'Pending';
        $role = $submission['role'] ?? null;
        $appliedRole = ($status === 'Verified' && !empty($role)) ? $role : null;
        $buttonLabel = ($appliedRole === 'Operator') ? 'Go to Operator Dashboard' : 'Go to Driver Dashboard';
        $buttonLink = ($appliedRole === 'Operator') ? 'operator_dashboard.php' : 'driver_dashboard.php';
    ?>

    <div class="alert <?= $status === 'Pending' ? 'alert-warning' : ($status === 'Verified' ? 'alert-success' : 'alert-danger'); ?>">
        <h5>Membership Submission Status:</h5>
        <p>Your membership status is currently <strong><?= htmlspecialchars($status); ?></strong>.</p>

        <?php if ($status === 'Pending'): ?>
            <p>Please wait while the manager reviews your submission.</p>

        <?php elseif ($status === 'Verified'): ?>
            <p>✅ Congratulations! Your membership requirements have been verified.</p>

            <?php if ($appliedRole): ?>
                <form action="<?= htmlspecialchars($buttonLink); ?>" method="get" style="display: inline;">
                    <button type="submit" class="btn btn-primary mt-2">
                        <?= htmlspecialchars($buttonLabel); ?>
                    </button>
                </form>
            <?php else: ?>
                <p class="text-danger">⚠️ Verified, but no role assigned yet. Please contact support.</p>
            <?php endif; ?>

        <?php else: ?>
            <p>❌ Your submission was rejected. Please resubmit your documents correctly.</p>
        <?php endif; ?>
    </div>

<?php else: ?>
    <div class="alert alert-info">
        <h5>No Membership Submission Found</h5>
        <p>You haven't submitted any requirements yet. Submit your documents using the form below.</p>
    </div>
<?php endif; ?>


    <?php elseif (false): ?>
<!-- Live Jeepney Location - Enhanced Design -->
<div class="jeepney-location-container">
    <!-- Header Section -->
    <div class="location-header mb-4">
        <div class="header-content">
            <div class="header-icon">
                <i class="bi bi-geo-alt-fill"></i>
            </div>
            <div class="header-text">
                <h2 class="header-title">Live Jeepney Location</h2>
                <p class="header-subtitle">Real-time tracking of available jeepneys</p>
            </div>
        </div>
    </div>

    <!-- Main Content Grid -->
    <div class="location-grid">
        <!-- Map Section -->
        <div class="map-section">
            <div class="map-card">
                <div class="map-header">
                    <div class="map-header-content">
                        <i class="bi bi-map"></i>
                        <span>Interactive Map</span>
            </div>
                    <div class="map-controls">
                        <button class="map-control-btn" onclick="refreshMap()">
                            <i class="bi bi-arrow-clockwise"></i>
                        </button>
        </div>
    </div>
                <div class="map-container">
                    <div id="jeepneyMap"></div>
</div>
            </div>
        </div>

        <!-- Status Section -->
        <div class="status-section">
            <div id="tripTracker" class="trip-tracker d-none mb-2"></div>
            <div class="status-header d-flex align-items-center justify-content-between mb-3">
                <div class="d-flex align-items-center gap-2">
                    <h3 class="mb-0">Jeepney Status</h3>
                    <span class="status-indicator d-flex align-items-center ms-2">
                    <span class="status-dot"></span>
                        <span class="status-text ms-1">Live</span>
                    </span>
                </div>
            </div>
            <div id="jeepneyArrivingAlert" class="arrival-alert d-none"></div>
            <div id="jeepneyStatusLoading" class="loading-container">
                <div class="loading-spinner">
                    <div class="spinner-ring"></div>
                    <div class="spinner-ring"></div>
                    <div class="spinner-ring"></div>
                </div>
                <p>Loading jeepney status...</p>
            </div>
            <div id="jeepneyStatusCards" class="status-cards-container"></div>
        </div>
    </div>
</div>

<!-- Enhanced Styles -->
<style>
.jeepney-location-container {
    background: linear-gradient(135deg, #0ea5e9 0%, #6366f1 50%, #7c3aed 100%);
    min-height: 100%;
    padding: 1rem;
    border-radius: 18px;
    position: relative;
    overflow: hidden;
}
/* Make this page full-width (no Bootstrap container constraints) */
body .container, .content-container, .content-wrapper .container {
    max-width: 100% !important;
    width: 100% !important;
    padding-left: 0 !important;
    padding-right: 0 !important;
}

.jeepney-location-container::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><defs><pattern id="grid" width="10" height="10" patternUnits="userSpaceOnUse"><path d="M 10 0 L 0 0 0 10" fill="none" stroke="rgba(255,255,255,0.1)" stroke-width="0.5"/></pattern></defs><rect width="100" height="100" fill="url(%23grid)"/></svg>');
    pointer-events: none;
}

.location-header {
    text-align: center;
    margin-bottom: 2rem;
    position: relative;
    z-index: 2;
}

.header-content {
    display: inline-flex;
    align-items: center;
    background: rgba(255, 255, 255, 0.92);
    padding: 1.25rem 1.75rem;
    border-radius: 18px;
    box-shadow: 0 16px 32px rgba(2, 6, 23, 0.12);
    backdrop-filter: blur(12px);
}

.header-icon {
    background: linear-gradient(135deg, #667eea, #764ba2);
    width: 60px;
    height: 60px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-right: 1.5rem;
    box-shadow: 0 10px 20px rgba(102, 126, 234, 0.3);
}

.header-icon i {
    font-size: 2rem;
    color: white;
}

.header-title {
    font-size: 2rem;
    font-weight: 800;
    background: linear-gradient(135deg, #667eea, #764ba2);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    margin: 0;
}

.header-subtitle {
    color: #6c757d;
    font-size: 1rem;
    margin: 0.5rem 0 0 0;
}

.location-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1.5rem;
    position: relative;
    z-index: 2;
}

.map-section {
    grid-column: 1;
}

.map-card {
    background: rgba(255, 255, 255, 0.96);
    border-radius: 18px;
    overflow: hidden;
    box-shadow: 0 18px 36px rgba(2, 6, 23, 0.12);
    backdrop-filter: blur(10px);
    height: 420px;
    display: flex;
    flex-direction: column;
}

.map-header {
    background: linear-gradient(135deg, rgba(14,165,233,1), rgba(99,102,241,1));
    color: white;
    padding: 1.25rem 1.5rem;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.map-header-content {
    display: flex;
    align-items: center;
    font-size: 1.2rem;
    font-weight: 600;
}

.map-header-content i {
    margin-right: 0.5rem;
    font-size: 1.5rem;
}

.map-control-btn {
    background: rgba(255, 255, 255, 0.2);
    border: none;
    color: white;
    width: 40px;
    height: 40px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all 0.3s ease;
}

.map-control-btn:hover {
    background: rgba(255, 255, 255, 0.3);
    transform: scale(1.1);
}

.map-container {
    flex: 1;
    position: relative;
}

#jeepneyMap {
    height: 100%;
    width: 100%;
    border-radius: 0 0 20px 20px;
}

.status-section {
    display: flex;
    flex-direction: column;
    gap: 1.25rem;
    background: rgba(255,255,255,0.98);
    border-radius: 18px;
    box-shadow: 0 14px 36px rgba(2,6,23,0.10);
    padding: 1.5rem 1.25rem;
    min-width: 340px;
}
.trip-tracker .tracker-card {
    display: flex;
    align-items: center;
    gap: .5rem;
    background: linear-gradient(135deg, rgba(14,165,233,.12), rgba(99,102,241,.12));
    color: #0f172a;
    border: 1px solid rgba(2,6,23,0.06);
    padding: .6rem .9rem;
    border-radius: 12px;
}
.trip-tracker .tracker-card i { color: #2563eb; }
.status-header {
    background: none;
    box-shadow: none;
    border-bottom: 1px solid #e0e0e0;
    padding: 0 0 1rem 0;
    border-radius: 0;
}
.status-indicator {
    gap: 0.5rem;
    font-size: 1rem;
}
.status-dot {
    width: 12px;
    height: 12px;
    background: #28a745;
    border-radius: 50%;
    animation: pulse 2s infinite;
    display: inline-block;
}
.status-text {
    font-weight: 600;
    color: #28a745;
}
.status-cards-container {
    display: flex;
    flex-direction: column;
    gap: 1.25rem;
    max-height: 350px;
    overflow-y: auto;
    padding-right: 0.5rem;
}
    /* Group by terminal (optional visual grouping) */
    .terminal-group-title { color: #f8fafc; font-weight: 700; margin: .5rem 0; text-shadow: 0 1px 0 rgba(0,0,0,.3); }
.status-card {
    background: #fff;
    border-radius: 16px;
    box-shadow: 0 10px 30px rgba(2, 6, 23, 0.10);
    border: 1px solid rgba(2,6,23,0.06);
    padding: 1.25rem 1.1rem;
    display: flex;
    flex-direction: column;
    gap: 1rem;
    transition: box-shadow 0.2s, transform 0.2s;
    position: relative;
}
.status-card:hover {
    box-shadow: 0 16px 40px rgba(2, 6, 23, 0.16);
    transform: translateY(-2px);
}
.card-header-section {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1.5rem;
}
.route-info {
    display: flex;
    align-items: center;
    gap: 1.25rem;
}
.route-icon {
    width: 48px;
    height: 48px;
    border-radius: 14px;
    background: linear-gradient(135deg, #0ea5e9, #6366f1);
    display: flex;
    align-items: center;
    justify-content: center;
    box-shadow: 0 8px 16px rgba(14,165,233,0.25);
}
.route-icon i {
    font-size: 1.6rem;
    color: #fff;
}
.route-details {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
}
.route-name {
    font-size: 1.2rem;
    font-weight: 700;
    margin: 0;
    color: #2c3e50;
}
.status-badge {
    display: inline-flex;
    align-items: center;
    padding: 0.35rem 1rem;
    border-radius: 20px;
    font-size: 0.95rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-top: 0.25rem;
}
.status-badge.bg-success { background: linear-gradient(135deg, #22c55e, #16a34a); color: #fff; }
.status-badge.bg-warning {
    background: linear-gradient(135deg, #ffc107, #fd7e14);
    color: #2c3e50;
}
.status-badge.bg-info { background: linear-gradient(135deg, #0ea5e9, #06b6d4); color: #fff; }
.status-badge.bg-secondary {
    background: linear-gradient(135deg, #6c757d, #495057);
    color: #fff;
}
.route-actions {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
    min-width: 160px;
}
.route-actions .btn, .route-actions .badge {
    border-radius: 12px;
    font-weight: 600;
    padding: 0.6rem 1rem;
    font-size: 0.95rem;
    letter-spacing: 0.2px;
}
.route-actions .btn { min-height: 46px; }
.route-actions .btn.btn-success { font-size: 1.05rem; padding: 0.75rem 1.1rem; }
.waiting-pill { border-radius: 999px; font-weight: 700; }
.route-actions .btn-success { background-image: linear-gradient(135deg, #16a34a, #22c55e); border: none; }
.route-actions .btn-primary { background-image: linear-gradient(135deg, #4f46e5, #7c3aed); border: none; }
.route-actions .btn-warning { background-image: linear-gradient(135deg, #f59e0b, #f97316); border: none; color: #fff; }
.route-actions .btn-outline-secondary { border-color: #94a3b8; color: #334155; }
.route-actions .btn-outline-secondary:hover { background: #e2e8f0; }
.card-content-section {
    display: flex;
    gap: 2rem;
    border-top: 1px solid #f0f0f0;
    padding-top: 1rem;
}
.info-grid {
    display: flex;
    gap: 2rem;
}
.info-item {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    background: #f8fafc;
    border-radius: 12px;
    padding: 0.75rem 1.25rem;
    min-width: 120px;
    border: 1px solid rgba(2,6,23,0.06);
}
.info-icon {
    width: 32px;
    height: 32px;
    border-radius: 10px;
    background: linear-gradient(135deg, #0ea5e9, #6366f1);
    display: flex;
    align-items: center;
    justify-content: center;
}
.info-icon i {
    color: #fff;
    font-size: 1.1rem;
}
.info-content {
    display: flex;
    flex-direction: column;
    gap: 0.1rem;
}
.info-label {
    font-size: 0.8rem;
    color: #6c757d;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}
.info-value {
    font-size: 1rem;
    font-weight: 700;
    color: #2c3e50;
}
@media (max-width: 1200px) {
    .status-section {
        min-width: unset;
        padding: 1.5rem 1rem;
    }
    .status-cards-container {
        max-height: 300px;
    }
}
@media (max-width: 768px) {
    .status-section {
        padding: 1rem 0.75rem;
    }
    .status-cards-container {
        max-height: 220px;
    }
    .card-header-section {
        flex-direction: column;
        gap: 1rem;
        align-items: flex-start;
    }
    .card-content-section, .info-grid {
        flex-direction: column;
        gap: 1rem;
    }
}
</style>
 
<script>
 

 
 
function pollWaitingConfirmation(route) {
    const routeKey = route.replace(/\s+/g, '_');
    const interval = setInterval(() => {
        fetch('../driver/confirm_pickup.php', {
            method: 'POST', headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ action: 'status', route: route, passenger_id: passengerId })
        }).then(r => r.json()).then(d => {
            if (d.success && d.status === 'confirmed') {
                clearInterval(interval);
                // Auto set boarded state when driver confirms
                sessionStorage.setItem('boarded_' + routeKey, '1');
                Swal.fire({ icon: 'success', title: 'Pickup Confirmed', text: 'Driver confirmed. You are now boarded.' });
                fetchJeepneyStatus();
            } else if (d.success && d.status === 'declined') {
                clearInterval(interval);
                sessionStorage.removeItem('here_' + routeKey);
                Swal.fire({ icon: 'info', title: 'Not Confirmed', text: 'Driver declined this request. Please try another route or time.' });
                fetchJeepneyStatus();
            }
        }).catch(() => {});
    }, 5000);
}
// ETA popup removed in simplified flow
function boardJeepney(route) {
    const routeKey = route.replace(/\s+/g, '_');
    fetch('../driver/board_jeepney.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({passenger_id: passengerId, route: route})
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            sessionStorage.setItem('boarded_' + routeKey, '1');
            sessionStorage.setItem('boarded_time_' + routeKey, new Date().toISOString());
            Swal.fire('Boarded!', 'You have boarded the jeepney.', 'success');
            fetchJeepneyStatus();
            fetchBoardingEvents(route); // <-- Refresh events after boarding
        } else {
            Swal.fire('Error', data.message || 'Failed to board.', 'error');
        }
    });
}
function alightJeepney(route) {
    const routeKey = route.replace(/\s+/g, '_');
    fetch('../driver/alight_jeepney.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({passenger_id: passengerId, route: route})
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            sessionStorage.removeItem('boarded_' + routeKey);
            sessionStorage.removeItem('boarded_time_' + routeKey);
            sessionStorage.setItem('alighted_time_' + routeKey, new Date().toISOString());
            Swal.fire('Alighted!', 'You have alighted from the jeepney.', 'success');
            fetchJeepneyStatus();
        } else {
            Swal.fire('Error', data.message || 'Failed to alight.', 'error');
        }
    });
}
function payFare(route) {
    const routeKey = route.replace(/\s+/g, '_');
    Swal.fire({
        title: 'Pay Fare',
        html: `<input type='number' id='fareAmount' class='swal2-input' placeholder='Enter fare amount'>
               <select id='fareMethod' class='swal2-input'>
                   <option value='Cash'>Cash</option>
                   <option value='GCash'>GCash</option>
                   <option value='Bank'>Bank</option>
               </select>`,
        showCancelButton: true,
        confirmButtonText: 'Pay',
        preConfirm: () => {
            const amount = document.getElementById('fareAmount').value;
            const method = document.getElementById('fareMethod').value;
            if (!amount || isNaN(amount) || amount <= 0) {
                Swal.showValidationMessage('Please enter a valid fare amount.');
                return false;
            }
            return { amount, method };
        }
    }).then(result => {
        if (result.isConfirmed) {
            // Show loading state
            Swal.fire({
                title: 'Processing Payment...',
                html: 'Please wait while we process your payment.',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });
            
            fetch('../driver/pay_fare.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({
                    passenger_id: passengerId,
                    route: route,
                    amount: result.value.amount,
                    payment_method: result.value.method
                })
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    sessionStorage.setItem('paid_' + routeKey, '1');
                    
                    // Show browser notification
                    if (Notification.permission === 'granted') {
                        new Notification('Payment Successful!', {
                            body: `Your fare payment of ₱${data.receipt.amount} has been processed.`,
                            icon: '/tebz/img/logo12.png',
                            badge: '/tebz/img/logo12.png'
                        });
                    }
                    
                    // Show success message with receipt
                    Swal.fire({
                        icon: 'success',
                        title: 'Payment Successful!',
                        html: `<div class="text-center">
                                <div class="mb-3">
                                    <i class="bi bi-check-circle-fill text-success" style="font-size: 3rem;"></i>
                                </div>
                                <div class="receipt-details">
                                    <p><strong>Receipt #:</strong> ${data.receipt.receipt_number}</p>
                                    <p><strong>Route:</strong> ${data.receipt.route}</p>
                                    <p><strong>Amount:</strong> ₱${data.receipt.amount}</p>
                                    <p><strong>Method:</strong> ${data.receipt.payment_method}</p>
                                    <p><strong>Date:</strong> ${data.receipt.date}</p>
                                </div>
                                <div class="mt-3">
                                    <small class="text-muted">The driver will be notified of your payment.</small>
                                </div>
                            </div>`,
                        confirmButtonText: 'OK',
                        confirmButtonColor: '#28a745'
                    });
                    
                    // Refresh jeepney status
                    fetchJeepneyStatus();
                    
                    // Show toast notification
                    setTimeout(() => {
                        Swal.fire({
                            toast: true,
                            position: 'top-end',
                            icon: 'info',
                            title: 'Driver Notified',
                            text: 'The driver has been notified of your payment.',
                            showConfirmButton: false,
                            timer: 3000
                        });
                    }, 2000);
                    
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Payment Failed',
                        text: data.message || 'Failed to process payment. Please try again.',
                        confirmButtonColor: '#d33'
                    });
                }
            })
            .catch(error => {
                console.error('Payment error:', error);
                Swal.fire({
                    icon: 'error',
                    title: 'Payment Error',
                    text: 'Network error occurred. Please check your connection and try again.',
                    confirmButtonColor: '#d33'
                });
            });
        }
    });
}
 

// Add fetchBoardingEvents function in the <script> section
function fetchBoardingEvents(route) {
    const routeKey = route.replace(/\s+/g, '_');
    const eventsDiv = document.getElementById('boardingEvents_' + routeKey);
    if (!eventsDiv) return;
    fetch('../driver/board_jeepney.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({action: 'list', route: route})
    })
    .then(res => res.json())
    .then(data => {
        if (data.success && data.events.length > 0) {
            let html = '<div class="fw-bold mb-1">Recent Boardings:</div>';
            html += '<ul class="list-unstyled mb-0">';
            data.events.slice(0, 5).forEach(ev => {
                html += `<li><span class='bi bi-person-fill text-primary'></span> <span class='fw-semibold'>${ev.passenger}</span> <span class='text-muted small'>[${new Date(ev.event_time).toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'})}]</span></li>`;
            });
            html += '</ul>';
            eventsDiv.innerHTML = html;
        } else {
            eventsDiv.innerHTML = '<div class="text-muted small">No recent boardings.</div>';
        }
    })
    .catch(() => {
        eventsDiv.innerHTML = '<div class="text-danger small">Failed to load boardings.</div>';
    });
}
</script>
<style>
#jeepneyStatusCards .card {
    border-radius: 12px;
    box-shadow: 0 4px 12px rgba(44, 62, 80, 0.08);
    margin-bottom: 1.5rem;
    transition: box-shadow 0.2s;
}
#jeepneyStatusCards .card:hover {
    box-shadow: 0 8px 24px rgba(44, 62, 80, 0.15);
}
#jeepneyStatusCards .badge {
    font-size: 1rem;
    font-weight: 500;
    border-radius: 8px;
}
#jeepneyStatusCards .btn {
    font-size: 1.05rem;
    border-radius: 8px;
    font-weight: 600;
    box-shadow: 0 2px 8px rgba(52, 152, 219, 0.08);
    transition: all 0.2s;
}
#jeepneyStatusCards .btn:active {
    transform: scale(0.98);
}
#jeepneyMap {
    border-radius: 10px;
    box-shadow: 0 4px 12px rgba(44, 62, 80, 0.08);
}
</style>


    <?php elseif ($page === 'board_fare'): ?>
        <!-- Board and Pay Fare -->
        <h2>Board and Pay Fare</h2>
        <p>Please confirm your fare payment when boarding the jeepney.</p>
        <form action="confirm_fare.php" method="POST">
            <div class="mb-3">
                <label for="fare_amount" class="form-label">Fare Amount</label>
                <input type="number" name="fare_amount" id="fare_amount" class="form-control" placeholder="Enter Fare Amount" required>
            </div>
            <button type="submit" class="btn btn-primary">Confirm Payment</button>
        </form>

        <?php elseif ($page === 'profile'): ?>
    <!-- Profile Page -->
    <div class="card shadow-sm">
        <div class="card-header gradient-header text-white">
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
                                            <span class="badge bg-primary">Passenger</span>
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
        const formData = new FormData(document.getElementById('profileImageForm'));
        
        // Show loading state
        Swal.fire({
            title: 'Uploading...',
            html: 'Please wait while we upload your profile picture.',
            allowOutsideClick: false,
            didOpen: () => {
                Swal.showLoading();
            }
        });
        
        fetch('upload_profile.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                Swal.fire({
                    icon: 'success',
                    title: 'Profile Picture Updated!',
                    text: 'Your profile picture has been updated successfully.',
                    confirmButtonColor: '#3085d6'
                }).then(() => {
                    location.reload();
                });
            } else {
                throw new Error(data.message || 'Failed to upload profile picture');
            }
        })
        .catch(error => {
            console.error('Upload error:', error);
            Swal.fire({
                icon: 'error',
                title: 'Upload Failed',
                text: error.message || 'Please try again later.',
                confirmButtonColor: '#d33'
            });
        });
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

    <?php elseif ($page === 'apply_driver_operator'): ?>
    <div class="container mt-4">
        <h2 class="mb-4 text-primary text-center">📢 Announcements </h2>

        <?php
// Fetch all announcements from the database (latest first)
$requirementsQuery = "SELECT id, title, membership_requirements, general_requirements, contact_info, created_at FROM membership_requirements ORDER BY id DESC";
$requirementsResult = $conn->query($requirementsQuery);

$announcements = [];
while ($row = $requirementsResult->fetch_assoc()) {
    $announcements[] = $row;
}

if (!empty($announcements)):
    // Convert PHP array to JSON for JavaScript
    $announcementsJSON = json_encode($announcements);
?>

<!-- Search Bar -->
<div class="mb-4">
    <input type="text" id="searchAnnouncements" class="form-control" placeholder="🔍 Search announcements..." onkeyup="filterAnnouncements()">
</div>

<!-- Announcement Container -->
<div class="mb-4" id="latestAnnouncementContainer">
    <h3 class="text-success">📢 Latest Announcement</h3>
    <div class="card shadow-lg p-4 rounded-4" id="announcementContent">
        <h4 class="fw-bold">📅 <span id="announcementTitle"></span> 
            <small class="text-muted">(Posted on <span id="announcementDate"></span>)</small>
        </h4>
        <p><b>📜 Membership Requirements:</b> <span id="announcementMembership"></span></p>
        <p><b>📌 General Requirements:</b> <span id="announcementGeneral"></span></p>
        <p><b>📞 Contact Information:</b> <span id="announcementContact"></span></p>
    </div>
</div>

<!-- Navigation Buttons -->
<div class="d-flex justify-content-center gap-3 mt-4">
    <button class="btn btn-outline-primary px-4 py-2 fw-semibold" id="prevAnnouncement">⬅ Previous</button>
    <button class="btn btn-outline-primary px-4 py-2 fw-semibold" id="nextAnnouncement">Next ➡</button>
</div>

<!-- Apply Now Button directly below navigation -->
<div class="text-center mt-3">
    <button type="button" 
            id="applyNowBtn1"
            class="btn apply-now-btn btn-lg shadow-lg px-5 py-3" 
            data-bs-toggle="modal" 
            data-bs-target="#applyModal">
        Apply Now
    </button>
    <div class="small text-muted mt-2">Submit your Driver/Operator application anytime.</div>
</div>

<?php else: ?>
    <div class="alert alert-info text-center mt-3">
        <p class="mb-0">⚠️ No Announcement posted yet.</p>
    </div>
    <!-- Apply Now Button shown even if no announcements -->
    <div class="text-center mt-3">
        <button type="button" 
                id="applyNowBtn2"
                class="btn apply-now-btn btn-lg shadow-lg px-5 py-3" 
                data-bs-toggle="modal" 
                data-bs-target="#applyModal">
            Apply Now
        </button>
        <div class="small text-muted mt-2">You can still apply even without announcements.</div>
    </div>
<?php endif; ?>

    <?php elseif ($page === 'reservations'): ?>
    <div class="py-3">
        <div class="card shadow-sm">
            <div class="card-header gradient-header text-white d-flex align-items-center">
                <i class="bi bi-bookmark-check-fill me-2"></i>
                <h5 class="mb-0">Make a Reservation</h5>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <!-- Left Column: Form and Map -->
                    <div class="col-lg-7">
                        <div class="row g-3">
                            <div class="col-12">
                                <div class="mb-3">
                                    <label class="form-label fw-bold">Origin Landmark</label>
                                    <select id="resOrigin" class="form-select"></select>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label fw-bold">Destination Landmark</label>
                                    <select id="resDest" class="form-select"></select>
                                </div>
                                <div class="mb-3">
                                    <div class="alert alert-info mb-2">
                                        <div><strong>Distance:</strong> <span id="resKm">-</span> km</div>
                                        <div class="form-check mt-2">
                                            <input class="form-check-input" type="checkbox" id="resUseDisc">
                                            <label for="resUseDisc" class="form-check-label">I am eligible for discounted fare</label>
                                        </div>
                                        <div class="mt-2"><strong>Exact Amount to Pay:</strong> ₱<span id="resPayable">-</span></div>
                                        <small class="text-muted d-block mt-1">Please prepare exact fare as required by LTFRB compliance.</small>
                                        <div class="small text-muted mt-1" id="resFareMatch"></div>
                                    </div>
                                    <!-- Compliance compact note + modal trigger -->
                                    <div class="compliance-note-compact">
                                        <div class="alert alert-warning d-flex align-items-center">
                                            <i class="bi bi-exclamation-triangle-fill me-2 fs-5"></i>
                                            <div>
                                                <strong>Fare Compliance: </strong>
                                                Exact fare required. Drivers may not have change.
                                                <a href="#" class="alert-link" data-bs-toggle="modal" data-bs-target="#fareModal">View fare matrix</a>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="d-grid gap-2">
                                        <button id="btnReserve" class="btn btn-primary btn-lg"><i class="bi bi-send"></i> Reserve</button>
                                        <button id="btnHere" class="btn btn-outline-primary btn-lg" disabled><i class="bi bi-geo-alt"></i> I'm Here</button>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Map Section -->
                            <div class="col-12">
                                <div class="card border-0 shadow-sm">
                                    <div class="card-header bg-primary text-white">
                                        <i class="bi bi-map"></i> Route Map
                                    </div>
                                    <div class="card-body p-0">
                                        <div id="resMap" style="height: 350px; width: 100%; border-radius: 0 0 8px 8px;"></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Right Column: Active Reservations -->
                    <div class="col-lg-5">
                        <div class="card shadow-sm border-0 h-100">
                            <div class="card-header bg-success text-white d-flex align-items-center justify-content-between">
                                <div>
                                    <i class="bi bi-list-check"></i> My Active Reservations
                                </div>
                                <span class="badge bg-light text-dark" id="resCount">0</span>
                            </div>
                            <div class="card-body" style="max-height: 700px; overflow-y: auto;">
                                <div id="resList" class="text-muted">No active reservations</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- Fare Matrix Modal -->
    <div class="modal fade" id="fareModal" tabindex="-1" aria-labelledby="fareModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content shadow-lg">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="fareModalLabel">
                        <i class="bi bi-table me-2"></i>LTFRB Region XI Official Fare Matrix
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="table-responsive">
                        <table class="table table-bordered table-hover fare-table">
                            <thead class="table-light">
                                <tr>
                                    <th class="fw-bold">Distance</th>
                                    <th class="fw-bold text-primary">Regular Fare</th>
                                    <th class="fw-bold text-success">Discounted Fare*</th>
                                </tr>
                            </thead>
                            <tbody id="fareTableBody">
                                <tr>
                                    <td colspan="3" class="text-center text-muted">Loading fare matrix...</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="mt-3 p-3 bg-light rounded">
                        <small class="text-muted">
                            <i class="bi bi-info-circle me-1"></i>
                            <strong>*Discount applies to:</strong> Students, Seniors (60+), and PWDs with valid ID
                        </small>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="bi bi-x-lg me-1"></i>Close
                    </button>
                </div>
            </div>
        </div>
    </div>
    <script>
    window.PASSENGER_ID = <?= (int)$userId ?>;
    (function(){
        let stops = [];
        let lastEtaKey = null;
        let currentResId = null;
       let map, markers = [], routeLine = null;
        function initMap(){
            if (typeof L === 'undefined') return;
            map = L.map('resMap').setView([7.0667,125.6], 12);
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',{ attribution:'© OpenStreetMap' }).addTo(map);
        }
        function loadStops(){
            fetch('../data/stops.json').then(r=>r.json()).then(data=>{
                stops = data;
                const opts = data.map(s=>`<option value="${s.name}" data-lat="${s.lat}" data-lng="${s.lng}">${s.name}</option>`).join('');
                const extra = ['route1','route2','route3','route4','route5']
                    .map(n=>`<option value="${n}" data-lat="" data-lng="">${n}</option>`).join('');
                document.getElementById('resOrigin').innerHTML = opts + extra;
                document.getElementById('resDest').innerHTML = opts + extra;
                drawMap();
                computeFare();
            });
            // Also populate fare matrix modal table from JSON for compliance
            fetch('../data/fare_matrix.json').then(r=>r.json()).then(rows=>{
                const tbody = document.getElementById('fareTableBody');
                if(!tbody) return;
                tbody.innerHTML = rows.map(row=>{
                    const km = row.km;
                    const reg = Number(row.new_reg||row.regular||0).toFixed(2);
                    const disc = Number(row.new_disc||row.discounted||0).toFixed(2);
                    const distanceLabel = km <= 4 ? '0-4 km' : `${km} km`;
                    return `<tr><td>${distanceLabel}</td><td>₱${reg}</td><td>₱${disc}</td></tr>`;
                }).join('');
                // Build examples for distances >= 5 km using Toril Market as origin (fallback to selected origin)
                try {
                    const exDiv = document.getElementById('fareExamples');
                    if (exDiv && Array.isArray(stops) && stops.length) {
                        const defaultOrigin = 'Toril Market';
                        let originIndex = stops.findIndex(s=>s.name===defaultOrigin);
                        if (originIndex < 0) {
                            const sel = document.getElementById('resOrigin');
                            const selName = sel && sel.value ? sel.value : (stops[0]?.name||'');
                            originIndex = stops.findIndex(s=>s.name===selName);
                        }
                        const kms = Array.from(new Set(rows.map(r=>parseInt(r.km)).filter(k=>k>=5))).sort((a,b)=>a-b);
                        const items = [];
                        kms.forEach(km => {
                            const dest = stops[originIndex + km];
                            if (dest && dest.name) {
                                const originName = stops[originIndex]?.name || defaultOrigin;
                                items.push(`<li>${km} km: ${originName} → ${dest.name}</li>`);
                            }
                        });
                        exDiv.innerHTML = items.length ? `<ul class="mb-0">${items.join('')}</ul>` : 'No examples available from Toril Market.';
                    }
                } catch(_){}
            }).catch(()=>{
                const tbody = document.getElementById('fareTableBody');
                if(tbody) tbody.innerHTML = '<tr><td colspan="3" class="text-muted">Unable to load fare matrix.</td></tr>';
            });
        }
        function getKmBetween(origin, dest){
            // Prefer stops index difference; support example routes (route1..route5)
            const arr = stops||[];
            const i1 = arr.findIndex(s=>s.name===origin);
            const i2 = arr.findIndex(s=>s.name===dest);
            const isExample = (name)=> /^route[1-5]$/i.test(name||'');
            if (i1 === -1 || i2 === -1) {
                if (isExample(origin) || isExample(dest)) {
                    const map = { route1:5, route2:10, route3:6, route4:7, route5:8 };
                    const v1 = map[(origin||'').toLowerCase()] || null;
                    const v2 = map[(dest||'').toLowerCase()] || null;
                    if (v1 && v2) return Math.max(v1, v2);
                    if (v1) return v1;
                    if (v2) return v2;
                    return 5; // default example distance
                }
                return 1;
            }
            const km = Math.abs(i2 - i1);
            return km>0?km:1;
        }
        function computeFare(){
            const o = document.getElementById('resOrigin').value;
            const d = document.getElementById('resDest').value;
            const km = getKmBetween(o,d);
            document.getElementById('resKm').textContent = km;
            // Use fare_matrix.json absolute km mapping
            fetch('../data/fare_matrix.json').then(r=>r.json()).then(rows=>{
                let exact = rows.find(x=>parseInt(x.km)===km);
                if(!exact){
                    let best=null; rows.forEach(x=>{ if(x.km<=km && (!best||x.km>best.km)) best=x; }); exact = best;
                }
                const useDisc = document.getElementById('resUseDisc').checked;
                const payable = useDisc ? (exact&&exact.new_disc||null) : (exact&&exact.new_reg||null);
                document.getElementById('resPayable').textContent = payable? payable.toFixed(2) : '-';
                const matchEl = document.getElementById('resFareMatch');
                if (matchEl) {
                    if (exact) {
                        const reg = (exact.new_reg!=null? Number(exact.new_reg).toFixed(2) : '-');
                        const disc = (exact.new_disc!=null? Number(exact.new_disc).toFixed(2) : '-');
                        matchEl.textContent = `Matrix match: ${exact.km} km • Regular: ₱${reg} • Discounted: ₱${disc}`;
                    } else {
                        matchEl.textContent = '';
                    }
                }
            });
        }
       function drawMap(){
            if(!map) return;
           markers.forEach(m=>map.removeLayer(m)); markers=[];
           if(routeLine){ map.removeLayer(routeLine); routeLine = null; }
            const opts = sel=>{
                const s = sel.options[sel.selectedIndex];
                const latAttr = s.getAttribute('data-lat');
                const lngAttr = s.getAttribute('data-lng');
                const lat = (latAttr!==null && latAttr!=='') ? parseFloat(latAttr) : NaN;
                const lng = (lngAttr!==null && lngAttr!=='') ? parseFloat(lngAttr) : NaN;
                return { name:s.value, lat, lng };
            }
            const oSel = document.getElementById('resOrigin');
            const dSel = document.getElementById('resDest');
            if(oSel.options.length===0||dSel.options.length===0) return;
            const o = opts(oSel), d = opts(dSel);
            if(!isNaN(o.lat) && !isNaN(o.lng)) markers.push(L.marker([o.lat,o.lng]).addTo(map).bindPopup('Origin: '+o.name));
            if(!isNaN(d.lat) && !isNaN(d.lng)) markers.push(L.marker([d.lat,d.lng]).addTo(map).bindPopup('Destination: '+d.name));
           if(!isNaN(o.lat) && !isNaN(o.lng) && !isNaN(d.lat) && !isNaN(d.lng)){
               if (L.Routing && L.Routing.osrmv1) {
                   const router = L.Routing.osrmv1({ serviceUrl: 'https://router.project-osrm.org/route/v1' });
                   router.route([
                       { latLng: L.latLng(o.lat, o.lng) },
                       { latLng: L.latLng(d.lat, d.lng) }
                   ], (err, routes) => {
                       if (!err && routes && routes.length) {
                           const coords = routes[0].coordinates;
                           if(routeLine){ map.removeLayer(routeLine); }
                           routeLine = L.polyline(coords, { color:'#0d6efd', weight:5, opacity:0.85 }).addTo(map);
                       } else {
                           // Fallback to straight line if routing fails
                           routeLine = L.polyline([[o.lat,o.lng],[d.lat,d.lng]], { color:'#0d6efd', weight:4, opacity:0.7, dashArray:'6,6' }).addTo(map);
                       }
                       const layers = [...markers];
                       if(routeLine) layers.push(routeLine);
                       if(layers.length){
                           const g = L.featureGroup(layers); map.fitBounds(g.getBounds().pad(0.3));
                       }
                   });
               } else {
                   // Leaflet Routing not available; draw straight line
                   routeLine = L.polyline([[o.lat,o.lng],[d.lat,d.lng]], { color:'#0d6efd', weight:4, opacity:0.7, dashArray:'6,6' }).addTo(map);
                   const layers = [...markers, routeLine];
                   const g = L.featureGroup(layers); map.fitBounds(g.getBounds().pad(0.3));
               }
           } else {
               // Only markers available
               if(markers.length){
                   const g = L.featureGroup(markers); map.fitBounds(g.getBounds().pad(0.3));
               }
           }
        }
        function listMy(){
            fetch('../shared/reservations.php', {method:'POST', body:new URLSearchParams({action:'list_for_passenger'})})
            .then(r=>r.json()).then(d=>{
                const box = document.getElementById('resList');
                const countBadge = document.getElementById('resCount');
                if(!d.success||!d.reservations||d.reservations.length===0){ 
                    box.textContent='No active reservations'; 
                    if(countBadge) countBadge.textContent = '0';
                    return; 
                }
                // Update count badge
                if(countBadge) countBadge.textContent = d.reservations.length;
                box.innerHTML = d.reservations.map(r=>{
                    const paidKey = 'reservation_paid_' + r.id;
                    const isPaid = localStorage.getItem(paidKey) === 'true';
                    let waitTxt='';
                    if(r.here_at && !r.boarded_at){
                        const sec = Math.max(0, Math.floor((Date.now()-new Date(r.here_at).getTime())/1000));
                        const m=Math.floor(sec/60), s=sec%60; waitTxt = ` • Wait: ${m}m ${s}s`;
                    }
                    let etaTxt = '';
                    if(r.eta_time && !r.boarded_at){
                        const etaMs = new Date(r.eta_time).getTime() - Date.now();
                        const etaMin = Math.max(0, Math.ceil(etaMs/60000));
                        etaTxt = ` • ETA: ${etaMin} min`;
                    }
                    const useDisc = document.getElementById('resUseDisc').checked;
                    const reg = Number(r.fare_regular||0); const disc = Number(r.fare_discounted||0);
                    const amt = useDisc && disc>0 ? disc : (reg>0?reg:disc);
                    const canPay = !isPaid && r.status==='boarded' && amt>0;
                    const routeName = (r.route && r.route.length) ? r.route : ((r.origin_landmark||'') + ' → ' + (r.dest_landmark||''));
                    const payBtn = canPay ? `<div class='mt-2 d-grid'><button class='btn btn-success btn-sm' onclick='openPayModal(${r.id}, ${reg||0}, ${disc||0}, ${JSON.stringify(routeName)})'><i class=\"bi bi-cash-coin\"></i> Pay Exact Fare ₱${amt.toFixed(2)}</button></div>` : '';
                    return `<div class='mb-2 p-2 border rounded'>
                        <div class='d-flex justify-content-between'><div>#${r.id} • ${r.origin_landmark} → ${r.dest_landmark}</div><div><span class='badge bg-secondary'>${r.status}</span>${isPaid ? " <span class='badge bg-success ms-1'>Paid</span>" : ''}</div></div>
                        <div class='small text-muted'>Distance: ${r.distance_km||'-'} km${waitTxt}${etaTxt}</div>
                        ${payBtn}
                    </div>`;
                }).join('');
                // enable I'm Here when latest reservation exists
                const latest = d.reservations[0];
                currentResId = latest.id; document.getElementById('btnHere').disabled = (latest.status!=='requested' && latest.status!=='eta_sent');
                // Notify ETA once when updated
                const etaCandidate = d.reservations.find(x=>x.eta_time && !x.boarded_at);
                if(etaCandidate){
                    const key = `${etaCandidate.id}_${etaCandidate.eta_time}`;
                    if(key !== lastEtaKey){
                        lastEtaKey = key;
                        const etaMs = new Date(etaCandidate.eta_time).getTime() - Date.now();
                        const etaMin = Math.max(0, Math.ceil(etaMs/60000));
                        if(window.Swal){ Swal.fire({icon:'info', title:'Driver ETA', text:`Driver arriving in approximately ${etaMin} minute(s).`}); }
                    }
                }
            });
        }
        function amountFor(reg, disc){
            const useDisc = document.getElementById('resUseDisc').checked;
            return useDisc && disc>0 ? disc : (reg>0?reg:disc);
        }
        window.openPayModal = function(rid, fareReg, fareDisc, route){
            const amt = amountFor(fareReg, fareDisc);
            const html = `
                <div class='text-start'>
                    <div class='mb-2'><strong>Exact Amount:</strong> ₱${amt.toFixed(2)}</div>
                    <div class='mb-2'>Select Payment Method:</div>
                    <div class='d-grid gap-2'>
                        <button class='btn btn-primary' id='pm_cash'><i class="bi bi-cash"></i> Cash</button>
                        <button class='btn btn-outline-primary' id='pm_gcash'><i class="bi bi-phone"></i> GCash</button>
                        <button class='btn btn-outline-secondary' id='pm_bank'><i class="bi bi-bank"></i> Bank</button>
                    </div>
                </div>`;
            Swal.fire({ title: 'Pay Fare', html, showConfirmButton:false, didOpen:()=>{
                const pay = (method)=>{
                    const payload = { passenger_id: (window.PASSENGER_ID||0), route: route||'', amount: amt, payment_method: method };
                    fetch('../driver/pay_fare.php', { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify(payload) })
                    .then(r=>r.json()).then(d=>{
                        if(d && d.success){
                            try { localStorage.setItem('reservation_paid_' + rid, 'true'); } catch(e){}
                            Swal.fire({icon:'success', title:'Payment Submitted', text:'Your exact fare payment has been submitted.'});
                            listMy();
                        }
                        else { Swal.fire({icon:'error', title:'Payment Failed', text:(d&&d.message)||'Please try again.'}); }
                    }).catch(()=> Swal.fire({icon:'error', title:'Network Error', text:'Please try again.'}));
                };
                document.getElementById('pm_cash').addEventListener('click', ()=>pay('Cash'));
                document.getElementById('pm_gcash').addEventListener('click', ()=>pay('GCash'));
                document.getElementById('pm_bank').addEventListener('click', ()=>pay('Bank'));
            }});
        }
        document.addEventListener('DOMContentLoaded', function(){
            initMap();
            loadStops();
            document.getElementById('resOrigin').addEventListener('change', ()=>{ computeFare(); drawMap(); });
            document.getElementById('resDest').addEventListener('change', ()=>{ computeFare(); drawMap(); });
            document.getElementById('resUseDisc').addEventListener('change', computeFare);
            document.getElementById('btnReserve').addEventListener('click', ()=>{
                const route='';
                const origin=document.getElementById('resOrigin').value;
                const dest=document.getElementById('resDest').value;
                const km=parseInt(document.getElementById('resKm').textContent||'1');
                fetch('../shared/reservations.php', {method:'POST', body:new URLSearchParams({action:'create', route, origin, dest, distance_km:km})})
                .then(r=>r.json()).then(d=>{
                    if(d.success){
                        currentResId = d.reservation_id; document.getElementById('btnHere').disabled=false; listMy();
                        Swal.fire({icon:'success', title:'Reserved!', text:'Your reservation was sent to the driver.'});
                    } else {
                        Swal.fire({icon:'error', title:'Failed', text:d.message||'Could not create reservation'});
                    }
                });
            });
            document.getElementById('btnHere').addEventListener('click', ()=>{
                if(!currentResId) return;
                fetch('../shared/reservations.php', {method:'POST', body:new URLSearchParams({action:'im_here', reservation_id: currentResId})})
                .then(r=>r.json()).then(()=>{ document.getElementById('btnHere').disabled=true; listMy(); });
            });
            listMy(); setInterval(listMy, 8000);
        });
    })();
    </script>

<?php if ($page === 'apply_driver_operator'): ?>
<!-- Apply Now Button (Announcements page only) -->
<div class="text-center mt-5">
    <button type="button" 
            id="applyNowBtn3"
            class="btn apply-now-btn btn-lg shadow-lg px-5 py-3" 
            data-bs-toggle="modal" 
            data-bs-target="#applyModal">
        Apply Now
    </button>
    <div class="small text-muted mt-2">Submit your Driver/Operator application anytime.</div>
    <?php if (empty($announcements)): ?>
        <div class="alert alert-info d-inline-block mt-3 mb-0">No announcements posted yet. You can still apply.</div>
    <?php endif; ?>
</div>
<?php endif; ?>

<?php elseif ($page === 'apply_cooperative'): ?>
    <style>
    .coop-apply { background: rgba(255,255,255,0.95); border: 1px solid rgba(2,6,23,0.06); border-radius: 16px; box-shadow: 0 10px 24px rgba(2,6,23,0.06); animation: fadeSlideIn .5s ease both; }
    @keyframes fadeSlideIn { from { opacity: 0; transform: translateY(8px); } to { opacity: 1; transform: translateY(0); } }
    .coop-apply-header { background: linear-gradient(135deg, #4f46e5, #7c3aed); padding: 1rem 1.25rem; border-radius: 12px; box-shadow: 0 8px 20px rgba(79,70,229,0.25); }
    .coop-apply .form-label { font-weight: 600; color: #334155; }
    .coop-apply .form-control { border-radius: 10px; border: 2px solid #e5e7eb; transition: border-color .2s, box-shadow .2s; }
    .coop-apply .form-control:focus { border-color: #4f46e5; box-shadow: 0 0 0 3px rgba(79,70,229,0.15); }
    .coop-apply .btn-success { background-image: linear-gradient(135deg, #16a34a, #22c55e); border: none; }
    .coop-apply .btn-success:hover { filter: brightness(1.05); transform: translateY(-1px); transition: transform .15s ease; }
    </style>
    <!-- Apply Cooperative (fit to content container) -->
    <div class="py-3">
        <div class="coop-apply p-3 p-md-4">
            <div class="coop-apply-header d-flex align-items-center gap-2 mb-3">
                <i class="bi bi-building-fill text-white fs-4"></i>
                <div>
                    <h2 class="mb-1 text-white">Apply Cooperative Membership</h2>
                    <p class="mb-0 text-white-50">Submit your cooperative's details and required documents for approval</p>
                    </div>
            </div>
                        <?php if (isset($_SESSION['message'])): ?>
                            <div class="alert alert-success text-center">
                                <?= htmlspecialchars($_SESSION['message']); ?>
                                <?php unset($_SESSION['message']); ?>
                            </div>
                        <?php endif; ?>
                <form action="apply_cooperative.php" method="POST" enctype="multipart/form-data" class="fade-slide-in">
                    <div class="mb-3 focus-field">
                                <label for="cooperative_name" class="form-label">Cooperative Name</label>
                                <input type="text" name="cooperative_name" id="cooperative_name" class="form-control" placeholder="Enter Cooperative Name" required>
                            </div>
                    <div class="mb-3 focus-field">
                                <label for="registration_number" class="form-label">Certificate Registration Number</label>
                                <input type="text" name="registration_number" id="registration_number" class="form-control" placeholder="Enter Registration Number" required>
                            </div>
                    <div class="mb-3 focus-field">
                                <label for="certificate" class="form-label">Upload Certificate</label>
                                <input type="file" name="certificate" id="certificate" class="form-control" accept="image/*, .pdf" required>
                            </div>
                    <div class="mb-3 focus-field">
                                <label for="contact_info" class="form-label">Contact Information</label>
                                <input type="text" name="contact_info" id="contact_info" class="form-control" placeholder="Enter Contact Information" required>
                            </div>
                            <button type="submit" class="btn btn-success w-100">Submit Cooperative Application</button>
                        </form>
        <div class="text-center mt-3">
                        <p class="mb-0 text-muted">Your submission will be reviewed shortly. You will be notified once a decision has been made.</p>
            </div>
        </div>
    </div>
<?php endif; ?>

</div>

<!-- Application Form Modal (Available on all pages) -->
<div class="modal fade" id="applyModal" tabindex="-1" aria-labelledby="applyModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header gradient-header text-white">
                <h5 class="modal-title">Apply as Driver/Operator</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="applyForm" action="submit_application.php" method="POST" enctype="multipart/form-data">
                    <div class="mb-3">
                        <label for="role" class="form-label">Choose Role</label>
                        <select name="role" id="role" class="form-select" required>
                            <option value="Driver">🚗 Driver</option>
                            <option value="Operator">🛠 Operator</option>
                        </select>
                    </div>

                    <!-- Driver's License -->
                    <div class="mb-3">
                        <label for="driver_license" class="form-label">📜 Driver's License</label>
                        <input type="file" name="driver_license" id="driver_license" class="form-control file-input" accept="image/*, .pdf" required>
                        <div class="preview-container mt-2">
                            <img id="preview_driver_license" class="img-preview d-none" alt="Preview">
                        </div>
                    </div>

                    <!-- CETOS Certification -->
                    <div class="mb-3">
                        <label for="cetos_certification" class="form-label">✅ CETOS Certification</label>
                        <input type="file" name="cetos_certification" id="cetos_certification" class="form-control file-input" accept="image/*, .pdf" required>
                        <div class="preview-container mt-2">
                            <img id="preview_cetos_certification" class="img-preview d-none" alt="Preview">
                        </div>
                    </div>

                    <!-- Provisional Authorization -->
                    <div class="mb-3">
                        <label for="provisional_authorization" class="form-label">📄 Provisional Authorization</label>
                        <input type="file" name="provisional_authorization" id="provisional_authorization" class="form-control file-input" accept="image/*, .pdf" required>
                        <div class="preview-container mt-2">
                            <img id="preview_provisional_authorization" class="img-preview d-none" alt="Preview">
                        </div>
                    </div>

                    <!-- PUV ID -->
                    <div class="mb-3">
                        <label for="puv_id" class="form-label">🆔 PUV ID</label>
                        <input type="file" name="puv_id" id="puv_id" class="form-control file-input" accept="image/*, .pdf" required>
                        <div class="preview-container mt-2">
                            <img id="preview_puv_id" class="img-preview d-none" alt="Preview">
                        </div>
                    </div>

                    <!-- Submit Button -->
                    <div class="text-center">
                        <button id="submitBtn" type="submit" class="btn btn-success btn-lg w-100">📤 Submit Application</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Success Modal -->
<div class="modal fade" id="successModal" tabindex="-1" aria-labelledby="successModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header gradient-header text-white">
                <h5 class="modal-title">Application Submitted Successfully!</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>Your application has been submitted successfully. You will be notified once your application is reviewed.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-success" id="redirectDashboard">Okay</button>
            </div>
        </div>
    </div>
</div>

    <!-- Enhanced Styles for Passenger Dashboard -->
    <style>
    /* Page containers - single, clean background for all sections */
    .content.dashboard-page,
    .content.announcement-page,
    .content.dashboard-page {
        background: transparent;
        min-height: 100%;
        padding: 1rem;
        border-radius: 16px;
        box-shadow: none;
        border: none;
        margin: 0 auto;
        text-align: left;
    }
    .content.announcement-page,
    .content.profile-page,
    .content.cooperative-page {
        background: #ffffff;
        min-height: 100%;
        padding: 1rem;
        border-radius: 16px;
        box-shadow: 0 10px 24px rgba(2,6,23,0.06);
        margin: 0 auto;
        text-align: left;
    }
    
    /* Center all cards and containers */
    .card,
    .dashboard-header,
    .profile-card,
    .coop-apply {
        margin-left: auto !important;
        margin-right: auto !important;
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

    /* Basic card styling for better appearance */
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

    /* Dashboard page card header */
    .dashboard-page .card-header {
        background: linear-gradient(135deg, #1976d2, #42a5f5);
        color: white;
    }

    /* Announcement page card header */
    .announcement-page .card-header {
        background: linear-gradient(135deg, #388e3c, #66bb6a);
        color: white;
    }

    /* Profile page card header */
    .profile-page .card-header {
        background: linear-gradient(135deg, #f57c00, #ff9800);
        color: white;
    }

    /* Cooperative page card header */
    .cooperative-page .card-header {
        background: linear-gradient(135deg, #7b1fa2, #ab47bc);
        color: white;
    }

    

    /* Alert styling */
    .alert {
        background: rgba(255, 255, 255, 0.95);
        border-radius: 15px;
        box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
        backdrop-filter: blur(10px);
        border: 1px solid rgba(255, 255, 255, 0.2);
    }

    /* Button styling */
    .btn {
        border-radius: 10px;
        font-weight: 600;
    }

    .btn-primary {
        background: linear-gradient(135deg, #1976d2, #42a5f5);
        border: none;
        color: white;
    }

    .btn-success {
        background: linear-gradient(135deg, #388e3c, #66bb6a);
        border: none;
        color: white;
    }

    .btn-warning {
        background: linear-gradient(135deg, #f57c00, #ff9800);
        border: none;
        color: white;
    }

    .btn-info {
        background: linear-gradient(135deg, #7b1fa2, #ab47bc);
        border: none;
        color: white;
    }

    /* Enhanced header design */
    .dashboard-header {
        text-align: center;
        margin-bottom: 2rem;
        position: relative;
        z-index: 1; /* Below sidebar (9999) */
    }

    .header-content {
        display: inline-flex;
        align-items: center;
        background: rgba(255, 255, 255, 0.95);
        padding: 1.5rem 2rem;
        border-radius: 20px;
        box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
        backdrop-filter: blur(10px);
        position: relative;
        z-index: 1; /* Below sidebar (9999) */
    }

    .header-icon {
        width: 60px;
        height: 60px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        margin-right: 1.5rem;
        box-shadow: 0 10px 20px rgba(0, 0, 0, 0.15);
    }

    /* Dashboard page icon */
    .dashboard-page .header-icon {
        background: linear-gradient(135deg, #1976d2, #42a5f5);
    }

    /* Announcement page icon */
    .announcement-page .header-icon {
        background: linear-gradient(135deg, #388e3c, #66bb6a);
    }

    /* Profile page icon */
    .profile-page .header-icon {
        background: linear-gradient(135deg, #f57c00, #ff9800);
    }

    /* Cooperative page icon */
    .cooperative-page .header-icon {
        background: linear-gradient(135deg, #7b1fa2, #ab47bc);
    }

    

    .header-icon i {
        font-size: 2rem;
        color: white;
    }

    .header-title {
        font-size: 2rem;
        font-weight: 800;
        margin: 0;
        color: #2c3e50;
    }

    /* Dashboard page title */
    .dashboard-page .header-title {
        background: linear-gradient(135deg, #1976d2, #42a5f5);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
    }

    /* Announcement page title */
    .announcement-page .header-title {
        background: linear-gradient(135deg, #388e3c, #66bb6a);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
    }

    /* Profile page title */
    .profile-page .header-title {
        background: linear-gradient(135deg, #f57c00, #ff9800);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
    }

    /* Cooperative page title */
    .cooperative-page .header-title {
        background: linear-gradient(135deg, #7b1fa2, #ab47bc);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
    }

    

    .header-subtitle {
        color: #5a6c7d;
        font-size: 1rem;
        margin: 0.5rem 0 0 0;
        font-weight: 500;
    }

    /* Enhanced card styling */
    .card {
        background: rgba(255, 255, 255, 0.95);
        border-radius: 20px;
        box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
        backdrop-filter: blur(10px);
        border: 1px solid rgba(255, 255, 255, 0.2);
        transition: all 0.3s ease;
    }

    .card:hover {
        transform: translateY(-3px);
        box-shadow: 0 20px 40px rgba(0, 0, 0, 0.15);
    }

    /* Enhanced alert styling */
    .alert {
        background: rgba(255, 255, 255, 0.95);
        border-radius: 15px;
        box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
        backdrop-filter: blur(10px);
        border: 1px solid rgba(255, 255, 255, 0.2);
        position: relative;
        z-index: 1; /* Below sidebar (9999) */
    }

    /* Enhanced button styling */
    .btn {
        border-radius: 15px;
        font-weight: 600;
        transition: all 0.3s ease;
    }

    .btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 20px rgba(0, 0, 0, 0.15);
    }

    /* Responsive adjustments */
    @media (max-width: 992px) {
        .header-content {
            flex-direction: column;
            padding: 1rem;
        }
        
        .header-icon {
            margin-right: 0;
            margin-bottom: 1rem;
        }
        
        .header-title {
            font-size: 1.5rem;
        }
    }
    </style>

<script>
    // Get announcements from PHP
    const announcements = <?= $announcementsJSON ?? '[]'; ?>;
    let currentIndex = 0; // Start with the latest announcement

    function updateAnnouncement() {
        if (announcements.length > 0) {
            const current = announcements[currentIndex];
            document.getElementById("announcementTitle").innerText = current.title;
            document.getElementById("announcementDate").innerText = new Date(current.created_at).toLocaleDateString('en-US', { month: 'long', day: 'numeric', year: 'numeric' });
            document.getElementById("announcementMembership").innerHTML = current.membership_requirements.replace(/\n/g, '<br>');
            document.getElementById("announcementGeneral").innerHTML = current.general_requirements.replace(/\n/g, '<br>');
            document.getElementById("announcementContact").innerHTML = current.contact_info.replace(/\n/g, '<br>');

            // Disable buttons at limits
            document.getElementById("prevAnnouncement").disabled = (currentIndex === announcements.length - 1);
            document.getElementById("nextAnnouncement").disabled = (currentIndex === 0);
        }
    }

    // Navigate announcement list (guard if buttons not rendered)
    const prevBtn = document.getElementById("prevAnnouncement");
    const nextBtn = document.getElementById("nextAnnouncement");
    if (prevBtn) {
        prevBtn.addEventListener("click", () => {
            if (currentIndex < announcements.length - 1) {
                currentIndex++;
                updateAnnouncement();
            }
        });
    }
    if (nextBtn) {
        nextBtn.addEventListener("click", () => {
            if (currentIndex > 0) {
                currentIndex--;
                updateAnnouncement();
            }
        });
    }

    // Initialize first announcement if list exists
    if (Array.isArray(announcements) && announcements.length) {
        updateAnnouncement();
    }

    // Bootstrap handles modal toggling automatically via data-bs-toggle and data-bs-target attributes
</script>
<!-- JavaScript to Change Button Text -->
<script>
document.getElementById("role").addEventListener("change", function() {
    let selectedRole = this.value;
    let submitBtn = document.getElementById("submitBtn");

    if (selectedRole === "Driver") {
        submitBtn.innerHTML = "📤 Apply as Driver";
    } else {
        submitBtn.innerHTML = "🛠 Apply as Operator";
    }
});
</script>
<!-- Simple form handling -->
<script>
    // Basic image preview functionality
    document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.file-input').forEach(input => {
        input.addEventListener('change', function(event) {
            const file = event.target.files[0];
            const previewId = `preview_${event.target.id}`;
            const previewElement = document.getElementById(previewId);

                if (file && file.type.startsWith('image/')) {
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        previewElement.src = e.target.result;
                        previewElement.classList.remove('d-none');
                        previewElement.classList.add('img-thumbnail');
                        previewElement.style.maxWidth = '200px';
                        previewElement.style.height = 'auto';
                    };
                    reader.readAsDataURL(file);
                } else {
                    previewElement.classList.add('d-none');
            }
        });
    });


        });
    </script>
<!-- Include Bootstrap and SweetAlert2 -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<!-- Bootstrap Verification and Modal Fix Script -->
<script>
// Simple modal interaction fix
document.addEventListener('DOMContentLoaded', function() {
    if (typeof bootstrap !== 'undefined') {
        console.log('✅ Bootstrap loaded successfully');
        
        const applyModal = document.getElementById('applyModal');
        
        if (applyModal) {
            // Simple fix: ensure modal content is always clickable
            applyModal.addEventListener('shown.bs.modal', function (e) {
                console.log('✅ Modal opened');
                
                // Focus first input for better UX
                setTimeout(() => {
                    const firstInput = this.querySelector('select, input');
                    if (firstInput) {
                        firstInput.focus();
                    }
                }, 150);
            });
            
            // Log when modal is hidden
            applyModal.addEventListener('hidden.bs.modal', function (e) {
                console.log('✅ Modal closed');
            });
        }
    } else {
        console.error('❌ Bootstrap NOT loaded!');
    }
});
</script>

<!-- Application Form Submission Handler -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    const applyForm = document.getElementById('applyForm');
    if (applyForm) {
        applyForm.addEventListener('submit', function(event) {
            event.preventDefault();
            
            // Show loading state
            const submitBtn = this.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;
            submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Submitting...';
            submitBtn.disabled = true;
            
            // Create FormData
            const formData = new FormData(this);
            
            // Submit form via fetch
            fetch('submit_application.php', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                // Reset button
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
                
                // Show success modal
                const successModal = new bootstrap.Modal(document.getElementById('successModal'));
                successModal.show();
                
                // Close the apply modal
                const applyModal = document.getElementById('applyModal');
                if (applyModal) {
                    const modalInstance = bootstrap.Modal.getInstance(applyModal);
                    if (modalInstance) {
                        modalInstance.hide();
                    }
                }
            })
            .catch(error => {
                console.error('Error:', error);
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
                
                // Show error alert
                Swal.fire({
                    icon: 'error',
                    title: 'Submission Failed',
                    text: 'There was an error submitting your application. Please try again.',
                    confirmButtonColor: '#dc3545'
                });
            });
        });
    }
    
    // Handle success modal "Okay" button
    const redirectDashboardBtn = document.getElementById('redirectDashboard');
    if (redirectDashboardBtn) {
        redirectDashboardBtn.addEventListener('click', function() {
            window.location.href = 'passenger_dashboard.php';
        });
    }
});
</script>
<?php if (isset($_SESSION['show_verified_alert'])): ?>
<script>
Swal.fire({
    icon: 'success',
    title: 'You\'re Verified!',
    text: 'Your role has been upgraded. You can now access your new dashboard.',
    timer: 3000,
    showConfirmButton: false
});
</script>
<?php unset($_SESSION['show_verified_alert']); endif; ?>

<!-- Responsive CSS and Script -->
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
            navbarCollapse.classList.remove('in');
            navbarCollapse.style.maxHeight = '0';
            navbarCollapse.style.display = 'none';
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