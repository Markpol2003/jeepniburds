<?php
require_once __DIR__ . '/includes/security.php';
jeepnigo_start_session();
// Enable safe error logging (no display in production) and robust config include
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');

$__configPaths = [
	__DIR__ . '/../db_config.php',
	__DIR__ . '/db_config.php',
];
$__configLoaded = false;
foreach ($__configPaths as $__configPath) {
	if (file_exists($__configPath)) {
		require_once $__configPath;
		$__configLoaded = true;
		break;
	}
}
if (!$__configLoaded) {
	error_log('db_config.php not found. Checked: ' . implode(', ', $__configPaths));
	http_response_code(500);
	echo 'A server configuration error occurred.';
	exit();
}

// If already logged in, redirect to the appropriate dashboard
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_SESSION['user_id'])) {
    $role = strtolower($_SESSION['user_type'] ?? '');
    $dashboardRoutes = [
        'passenger' => 'passenger/passenger_dashboard.php?page=dashboard',
        'driver'    => 'driver/driver_dashboard.php',
        'operator'  => 'operator/operator_dashboard.php',
        'manager'   => 'manager/manager_dashboard.php',
        'admin'     => 'manager/admin.php',
        'treasurer' => 'treasurer/treasurer_dashboard.php'
    ];
    if (isset($dashboardRoutes[$role])) {
        header('Location: ' . $dashboardRoutes[$role]);
        exit();
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    jeepnigo_require_csrf();
    if (isset($_POST['action'])) {
        if ($_POST['action'] == 'signup') {
            // Sanitize inputs
            $firstName = htmlspecialchars($_POST['firstName']);
            $lastName = htmlspecialchars($_POST['lastName']);
            $email = htmlspecialchars($_POST['email']);
            $password = $_POST['password'];
            $confirm_password = $_POST['confirm_password'];
            $userType = 'passenger';  // default userType is passenger

            // Validate inputs
            if (empty($firstName) || empty($lastName) || empty($email) || empty($password)) {
                echo json_encode(['status' => 'error', 'message' => 'Please fill out all required fields']);
                exit();
            }

            // Validate email
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                echo json_encode(['status' => 'error', 'message' => 'Invalid email address']);
                exit();
            }

            // Validate passwords
            if ($password !== $confirm_password) {
                echo json_encode(['status' => 'error', 'message' => 'Passwords do not match']);
                exit();
            }

            try {
                // Prepare the SQL query
                $passwordHash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $conn->prepare("INSERT INTO users (email, password, firstName, lastName, userType) VALUES (?, ?, ?, ?, ?)");
                $stmt->bind_param("sssss", $email, $passwordHash, $firstName, $lastName, $userType);

                if ($stmt->execute()) {
                    echo json_encode(['status' => 'success', 'message' => 'Account created successfully']);
                } else {
                    if ($conn->errno === 1062) {
                        echo json_encode(['status' => 'error', 'message' => 'Email already exists']);
                    } else {
                        echo json_encode(['status' => 'error', 'message' => 'An unexpected error occurred']);
                    }
                }
            } catch (Exception $e) {
                echo json_encode(['status' => 'error', 'message' => 'An unexpected error occurred']);
            }
            exit();
        } elseif ($_POST['action'] == 'login') {
            $email = htmlspecialchars($_POST['email']);
            $password = $_POST['password'];

            if (empty($email) || empty($password)) {
                echo json_encode(['status' => 'error', 'message' => 'Please fill out all fields']);
                exit();
            }

            $stmt = $conn->prepare("SELECT * FROM users WHERE email = ? AND status = 'Active'");
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $result = $stmt->get_result();
            $user = $result->fetch_assoc();

            if ($user) {
                $passwordInfo = password_get_info($user['password']);
                $legacyPlaintext = ($passwordInfo['algo'] === null || $passwordInfo['algo'] === 0);
                $isValid = password_verify($password, $user['password']) || ($legacyPlaintext && hash_equals((string)$user['password'], $password));
                if ($isValid) {
                    if ($legacyPlaintext || password_needs_rehash($user['password'], PASSWORD_DEFAULT)) {
                        $newHash = password_hash($password, PASSWORD_DEFAULT);
                        $upgrade = $conn->prepare('UPDATE users SET password = ? WHERE id = ?');
                        $upgrade->bind_param('si', $newHash, $user['id']);
                        $upgrade->execute();
                        $upgrade->close();
                    }
                    session_regenerate_id(true); // Prevent session fixation attacks

                    // Set session variables (normalize role)
                    $role = strtolower($user['userType'] ?? '');
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['user_email'] = $user['email'];
                    $_SESSION['user_firstName'] = $user['firstName'];
                    $_SESSION['user_lastName'] = $user['lastName'];
                    $_SESSION['user_type'] = $role;

                    // Redirect based on user role
                    $dashboardRoutes = [
                        'passenger' => 'passenger/passenger_dashboard.php?page=dashboard',
                        'driver'    => 'driver/driver_dashboard.php',
                        'operator'  => 'operator/operator_dashboard.php',
                        'manager'   => 'manager/manager_dashboard.php',
                        'admin'     => 'manager/admin.php',
                        'treasurer' => 'treasurer/treasurer_dashboard.php'
                    ];

                    $redirect = $dashboardRoutes[$role] ?? 'passenger/passenger_dashboard.php';

                    echo json_encode([
                        'status' => 'success',
                        'message' => 'Login successful',
                        'redirect' => $redirect
                    ]);
                } else {
                    echo json_encode(['status' => 'error', 'message' => 'Invalid email or password']);
                }
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Invalid email or password']);
            }

            exit();
        }
    }
}

require __DIR__ . '/landing-view.php';
exit;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?= jeepnigo_security_head() ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>JeepniGo - Modern Public Transportation</title>
    
    <!-- Modern Typography Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Bootstrap 5 and Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    
    <!-- SweetAlert2 -->
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11.7.32/dist/sweetalert2.min.css" rel="stylesheet">

    <style>
        /* ==========================================================================
           Modern Light Glassmorphism Design System
           ========================================================================== */
        :root {
            --bg-body: #f8fafc;
            --text-main: #0f172a;
            --text-muted: #475569;
            --text-subtle: #64748b;
            
            /* Glassmorphism Variables */
            --glass-bg: rgba(255, 255, 255, 0.72);
            --glass-bg-hover: rgba(255, 255, 255, 0.88);
            --glass-border: rgba(255, 255, 255, 0.85);
            --glass-border-subtle: rgba(226, 232, 240, 0.7);
            --glass-shadow: 0 20px 40px -15px rgba(148, 163, 184, 0.18), 0 0 20px rgba(255, 255, 255, 0.8) inset;
            --glass-shadow-hover: 0 25px 50px -12px rgba(99, 102, 241, 0.22);
            --glass-blur: blur(16px) saturate(180%);
            
            /* Modern Accent Gradients & Colors */
            --primary-gradient: linear-gradient(135deg, #1D4ED8 0%, #14B8A6 100%);
            --primary-color: #1D4ED8;
            --primary-light: #DBEAFE;
            --secondary-color: #0F172A;
            --accent-pink: #1D4ED8;
            --accent-cyan: #14B8A6;
            --accent-amber: #F59E0B;
            --accent-emerald: #10b981;

            --radius-xl: 28px;
            --radius-lg: 20px;
            --radius-md: 14px;
            --radius-sm: 10px;
        }

        body {
            font-family: 'Plus Jakarta Sans', 'Poppins', sans-serif;
            background-color: var(--bg-body);
            color: var(--text-main);
            overflow-x: hidden;
            line-height: 1.6;
        }

        /* Ambient Glow Spheres */
        .ambient-glow {
            position: absolute;
            border-radius: 50%;
            filter: blur(90px);
            opacity: 0.22;
            pointer-events: none;
            z-index: 0;
        }

        .glow-1 {
            width: 450px;
            height: 450px;
                background: #1D4ED8;
            top: -100px;
            left: -100px;
        }

        .glow-2 {
            width: 400px;
            height: 400px;
                background: #14B8A6;
            top: 25%;
            right: -80px;
        }

        .glow-3 {
            width: 500px;
            height: 500px;
                background: #F59E0B;
            bottom: 10%;
            left: 10%;
        }

        /* ==========================================================================
           Navbar (Light Glass)
           ========================================================================== */
        .navbar-glass {
            background: rgba(255, 255, 255, 0.78) !important;
            backdrop-filter: var(--glass-blur);
            -webkit-backdrop-filter: var(--glass-blur);
            border-bottom: 1px solid var(--glass-border-subtle);
            padding: 0.9rem 0;
            transition: all 0.3s ease;
        }

        .navbar-glass.scrolled {
            padding: 0.65rem 0;
            box-shadow: 0 10px 30px -10px rgba(148, 163, 184, 0.15);
            background: rgba(255, 255, 255, 0.88) !important;
        }

        .navbar-brand-text {
            font-size: 1.5rem;
            font-weight: 800;
            background: var(--primary-gradient);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            letter-spacing: -0.5px;
        }

        .nav-link-glass {
            color: #334155 !important;
            font-weight: 600;
            font-size: 0.95rem;
            padding: 0.5rem 1rem !important;
            transition: all 0.3s ease;
            position: relative;
        }

        .nav-link-glass:hover {
            color: var(--primary-color) !important;
        }

        .nav-link-underline {
            position: absolute;
            bottom: 0;
            left: 50%;
            transform: translateX(-50%);
            width: 0;
            height: 2.5px;
            background: var(--primary-gradient);
            border-radius: 10px;
            transition: width 0.3s ease;
        }

        .nav-link-glass:hover .nav-link-underline {
            width: 70%;
        }

        /* Glass Primary Button */
        .btn-glass-primary {
            background: var(--primary-gradient);
            color: white !important;
            border: none;
            padding: 0.7rem 1.6rem;
            border-radius: 50px;
            font-weight: 700;
            font-size: 0.95rem;
            box-shadow: 0 10px 25px -5px rgba(79, 70, 229, 0.35);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            overflow: hidden;
        }

        .btn-glass-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 15px 30px -5px rgba(79, 70, 229, 0.45);
            color: white;
        }

        /* Glass Secondary / Outline Button */
        .btn-glass-secondary {
            background: rgba(255, 255, 255, 0.85);
            color: #1e293b !important;
            border: 1px solid rgba(203, 213, 225, 0.8);
            padding: 0.75rem 1.8rem;
            border-radius: 50px;
            font-weight: 600;
            font-size: 1rem;
            backdrop-filter: blur(10px);
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.04);
            transition: all 0.3s ease;
        }

        .btn-glass-secondary:hover {
            background: white;
            color: var(--primary-color) !important;
            border-color: var(--primary-color);
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(79, 70, 229, 0.15);
        }

        /* ==========================================================================
           Hero Section
           ========================================================================== */
        .hero-section-light {
            min-height: 100vh;
            background: linear-gradient(135deg, #f8fafc 0%, #e0e7ff 45%, #f3e8ff 100%);
            position: relative;
            padding: 120px 0 70px;
            display: flex;
            align-items: center;
        }

        .glass-pill-badge {
            display: inline-flex;
            align-items: center;
            padding: 8px 20px;
            background: rgba(79, 70, 229, 0.08);
            border: 1px solid rgba(79, 70, 229, 0.2);
            border-radius: 50px;
            color: var(--primary-color);
            font-weight: 700;
            font-size: 0.88rem;
            backdrop-filter: blur(8px);
        }

        .hero-title {
            font-size: 3.5rem;
            font-weight: 800;
            color: var(--text-main);
            line-height: 1.15;
            letter-spacing: -1px;
        }

        .hero-title-gradient {
            background: var(--primary-gradient);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .hero-lead {
            font-size: 1.2rem;
            color: var(--text-muted);
            font-weight: 400;
        }

        /* Glass Stat Item */
        .glass-stat-card {
            background: var(--glass-bg);
            backdrop-filter: var(--glass-blur);
            -webkit-backdrop-filter: var(--glass-blur);
            border: 1px solid var(--glass-border);
            border-radius: var(--radius-md);
            padding: 18px 20px;
            box-shadow: var(--glass-shadow);
            transition: transform 0.3s ease;
        }

        .glass-stat-card:hover {
            transform: translateY(-4px);
        }

        .glass-stat-number {
            font-size: 1.75rem;
            font-weight: 800;
            color: var(--text-main);
            line-height: 1;
        }

        /* Light Phone Mockup with Glass */
        .phone-mockup-glass {
            background: rgba(255, 255, 255, 0.65);
            border: 1px solid var(--glass-border);
            backdrop-filter: blur(20px);
            border-radius: 40px;
            padding: 18px;
            box-shadow: 0 25px 60px -15px rgba(99, 102, 241, 0.22), 0 0 30px rgba(255,255,255,0.9) inset;
            max-width: 440px;
            margin: 0 auto;
            position: relative;
        }

        .phone-screen-light {
            background: linear-gradient(180deg, #ffffff 0%, #f1f5f9 100%);
            border-radius: 28px;
            padding: 26px;
            min-height: 420px;
            border: 1px solid #e2e8f0;
        }

        .glass-card-sm {
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(12px);
            border: 1px solid rgba(226, 232, 240, 0.8);
            border-radius: var(--radius-md);
            padding: 14px;
            box-shadow: 0 6px 20px rgba(148, 163, 184, 0.12);
        }

        /* ==========================================================================
           Feature Cards (Glassmorphism Light)
           ========================================================================== */
        .section-light {
            padding: 100px 0;
            position: relative;
        }

        .glass-card {
            background: var(--glass-bg);
            backdrop-filter: var(--glass-blur);
            -webkit-backdrop-filter: var(--glass-blur);
            border: 1px solid var(--glass-border);
            border-radius: var(--radius-lg);
            padding: 36px 30px;
            height: 100%;
            box-shadow: var(--glass-shadow);
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            overflow: hidden;
        }

        .glass-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 4px;
            background: var(--primary-gradient);
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .glass-card:hover {
            transform: translateY(-8px);
            background: var(--glass-bg-hover);
            box-shadow: var(--glass-shadow-hover);
            border-color: rgba(99, 102, 241, 0.3);
        }

        .glass-card:hover::before {
            opacity: 1;
        }

        .glass-icon-box {
            width: 66px;
            height: 66px;
            border-radius: var(--radius-md);
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 22px;
            box-shadow: 0 10px 20px -5px rgba(79, 70, 229, 0.25);
            transition: transform 0.3s ease;
        }

        .glass-card:hover .glass-icon-box {
            transform: scale(1.08) rotate(3deg);
        }

        /* ==========================================================================
           Form Controls & Inputs (Glass Light)
           ========================================================================== */
        .glass-input {
            width: 100%;
            padding: 14px 18px;
            background: rgba(255, 255, 255, 0.85);
            border: 1.5px solid #e2e8f0;
            border-radius: var(--radius-sm);
            font-size: 0.98rem;
            color: var(--text-main);
            transition: all 0.3s ease;
        }

        .glass-input:focus {
            outline: none;
            background: white;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 4px rgba(79, 70, 229, 0.12);
        }

        /* ==========================================================================
           Modals (Glassmorphism)
           ========================================================================== */
        .modal-content-glass {
            background: rgba(255, 255, 255, 0.88) !important;
            backdrop-filter: blur(24px) saturate(190%);
            -webkit-backdrop-filter: blur(24px) saturate(190%);
            border: 1px solid rgba(255, 255, 255, 0.95) !important;
            border-radius: 28px !important;
            box-shadow: 0 30px 60px -12px rgba(15, 23, 42, 0.2) !important;
        }

        .modal-backdrop.show {
            backdrop-filter: blur(8px);
            opacity: 0.45;
        }

        /* ==========================================================================
           Animations
           ========================================================================== */
        @keyframes floatSlow {
            0%, 100% { transform: translateY(0px) rotate(0deg); }
            50% { transform: translateY(-12px) rotate(3deg); }
        }

        .animate-float {
            animation: floatSlow 6s ease-in-out infinite;
        }

        @media (max-width: 991.98px) {
            .hero-title { font-size: 2.6rem; }
            .hero-section-light { padding: 100px 0 50px; }
            .phone-mockup-glass { max-width: 360px; }
            .navbar-collapse { margin-top: .75rem; padding: 1rem; border-radius: 18px; background: rgba(255,255,255,.96); box-shadow: 0 18px 35px rgba(15,23,42,.1); }
            .navbar-nav { align-items: stretch !important; }
            .nav-link-glass { padding: .75rem 1rem !important; }
        }

        @media (max-width: 575.98px) {
            .hero-title { font-size: clamp(2.1rem, 11vw, 2.7rem); line-height: 1.08; }
            .hero-lead { font-size: 1rem; }
            .hero-section-light { padding-top: 92px; }
            .hero-content .btn { width: 100%; }
            .glass-stat-card { padding: .85rem .35rem; }
            .glass-stat-number { font-size: 1.45rem; }
            .section-light { padding: 64px 0; }
            .glass-card { padding: 1.5rem; }
            .phone-mockup-glass { padding: .65rem; }
        }

        @media (prefers-reduced-motion: reduce) {
            *, *::before, *::after { scroll-behavior: auto !important; animation-duration: .01ms !important; animation-iteration-count: 1 !important; transition-duration: .01ms !important; }
        }
    </style>
</head>
<body>

    <!-- Ambient Glow Backgrounds -->
    <div class="ambient-glow glow-1"></div>
    <div class="ambient-glow glow-2"></div>
    <div class="ambient-glow glow-3"></div>

    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg fixed-top navbar-glass">
        <div class="container" style="max-width: 1200px; padding: 0 1.5rem;">
            <a class="navbar-brand d-flex align-items-center" href="#">
                <img src="img/logo12.png" alt="JeepniGo Logo" class="navbar-logo me-2" style="width: 42px; height: auto;">
                <span class="navbar-brand-text">JeepniGo</span>
            </a>
            <button class="navbar-toggler border-0 shadow-none" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto align-items-center">
                    <li class="nav-item mx-2">
                        <a class="nav-link nav-link-glass" href="#challenges">
                            Challenges
                            <span class="nav-link-underline"></span>
                        </a>
                    </li>
                    <li class="nav-item mx-2">
                        <a class="nav-link nav-link-glass" href="#features">
                            Solutions
                            <span class="nav-link-underline"></span>
                        </a>
                    </li>
                    <li class="nav-item mx-2">
                        <a class="nav-link nav-link-glass" href="#benefits">
                            Benefits
                            <span class="nav-link-underline"></span>
                        </a>
                    </li>
                    <li class="nav-item mx-2">
                        <a class="nav-link nav-link-glass" href="#contact">
                            Contact
                            <span class="nav-link-underline"></span>
                        </a>
                    </li>
                    <li class="nav-item ms-lg-3 mt-3 mt-lg-0">
                        <button onclick="openLoginModal()" class="btn btn-glass-primary">
                            <i class="bi bi-person-fill me-1"></i> Get Started
                        </button>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section class="hero-section-light">
        <div class="container" style="position: relative; z-index: 10;">
            <div class="row align-items-center g-5">
                <div class="col-lg-6">
                    <div class="hero-content">
                        <div class="glass-pill-badge mb-4">
                            <i class="bi bi-bus-front me-2"></i>Smarter everyday jeepney travel
                        </div>
                        <h1 class="hero-title mb-4">
                            A more predictable way to <span class="hero-title-gradient">move around the city</span>
                        </h1>
                        <p class="hero-lead mb-4">
                            JeepniGo connects commuters, drivers, operators, and cooperatives in one practical platform for routes, reservations, fares, and fleet operations.
                        </p>
                        <div class="d-flex flex-wrap gap-3 mb-5">
                            <button onclick="openLoginModal()" class="btn btn-glass-primary btn-lg px-4 py-3">
                                <i class="bi bi-person-check-fill me-2"></i>Sign in or create an account
                            </button>
                            <a href="#features" class="btn btn-glass-secondary btn-lg px-4 py-3">
                                <i class="bi bi-arrow-down-circle me-2"></i>See how it works
                            </a>
                        </div>
                        <!-- Hero Stats Row -->
                        <div class="row g-3">
                            <div class="col-4">
                                <div class="glass-stat-card text-center">
                                    <div class="glass-stat-number"><i class="bi bi-people"></i></div>
                                    <small class="text-muted fw-semibold">Commuters</small>
                                </div>
                            </div>
                            <div class="col-4">
                                <div class="glass-stat-card text-center">
                                    <div class="glass-stat-number"><i class="bi bi-bus-front"></i></div>
                                    <small class="text-muted fw-semibold">Drivers</small>
                                </div>
                            </div>
                            <div class="col-4">
                                <div class="glass-stat-card text-center">
                                    <div class="glass-stat-number"><i class="bi bi-building"></i></div>
                                    <small class="text-muted fw-semibold">Cooperatives</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-lg-6 text-center">
                    <div class="phone-mockup-glass animate-float">
                        <div class="phone-screen-light text-start">
                            <div class="d-flex justify-content-between align-items-center mb-4">
                                <div class="d-flex align-items-center">
                                    <img src="img/logo12.png" width="30" class="me-2" alt="Logo">
                                    <span class="fw-bold fs-5 text-dark">JeepniGo</span>
                                </div>
                                <span class="badge rounded-pill bg-primary bg-opacity-10 text-primary fw-bold px-3 py-2">LIVE</span>
                            </div>

                            <div class="glass-card-sm mb-3">
                                <div class="d-flex align-items-center justify-content-between">
                                    <div>
                                        <small class="text-muted d-block font-medium">Upcoming Arrival</small>
                                        <strong class="fs-5 text-dark">Route 4: Downtown Express</strong>
                                    </div>
                                    <div class="badge bg-success bg-opacity-10 text-success fw-bold px-3 py-2">
                                        3 mins away
                                    </div>
                                </div>
                            </div>

                            <div class="glass-card-sm mb-3">
                                <div class="d-flex align-items-center">
                                    <div class="rounded-3 p-3 text-white me-3" style="background: var(--primary-gradient);">
                                        <i class="bi bi-geo-alt-fill fs-4"></i>
                                    </div>
                                    <div>
                                        <h6 class="mb-0 fw-bold text-dark">Real-Time GPS Tracking</h6>
                                        <small class="text-muted">Interactive live route map updates</small>
                                    </div>
                                </div>
                            </div>

                            <div class="glass-card-sm">
                                <div class="d-flex align-items-center">
                                    <div class="rounded-3 p-3 text-white me-3" style="background: linear-gradient(135deg, #ec4899 0%, #8b5cf6 100%);">
                                        <i class="bi bi-wallet2 fs-4"></i>
                                    </div>
                                    <div>
                                        <h6 class="mb-0 fw-bold text-dark">Cashless E-Wallet Payment</h6>
                                        <small class="text-muted">Tap to pay with instant digital receipt</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Commuter challenges -->
    <section id="challenges" class="section-light">
        <div class="container">
            <div class="text-center mb-5">
                <span class="glass-pill-badge mb-3">THE DAILY CHALLENGE</span>
                <h2 class="display-6 fw-bold text-dark mb-3">Less uncertainty at every stage of the trip</h2>
                <p class="lead text-muted mx-auto" style="max-width: 650px;">Long waits, unclear arrivals, cash-only payments, and disconnected operations make commuting harder than it needs to be.</p>
            </div>

            <div class="row g-4">
                <div class="col-lg-4 col-md-6">
                    <div class="glass-card">
                        <div class="glass-icon-box text-white" style="background: linear-gradient(135deg, #4f46e5 0%, #06b6d4 100%);">
                            <i class="bi bi-calendar-check-fill fs-3"></i>
                        </div>
                        <h4 class="fw-bold text-dark mb-3">Digital Seat Booking</h4>
                        <p class="text-muted mb-0">Reserve seats ahead of time and avoid standing in long terminal queues during peak commuting hours.</p>
                    </div>
                </div>

                <div class="col-lg-4 col-md-6">
                    <div class="glass-card">
                        <div class="glass-icon-box text-white" style="background: linear-gradient(135deg, #ec4899 0%, #8b5cf6 100%);">
                            <i class="bi bi-geo-alt-fill fs-3"></i>
                        </div>
                        <h4 class="fw-bold text-dark mb-3">Live Fleet Tracking</h4>
                        <p class="text-muted mb-0">Follow real-time GPS locations of incoming jeepneys on an interactive map for better time planning.</p>
                    </div>
                </div>

                <div class="col-lg-4 col-md-6">
                    <div class="glass-card">
                        <div class="glass-icon-box text-white" style="background: linear-gradient(135deg, #06b6d4 0%, #3b82f6 100%);">
                            <i class="bi bi-wallet2 fs-3"></i>
                        </div>
                        <h4 class="fw-bold text-dark mb-3">Cashless Fares</h4>
                        <p class="text-muted mb-0">Pay fares seamlessly through secure e-wallets, QR code scanning, and digital transaction receipts.</p>
                    </div>
                </div>

                <div class="col-lg-4 col-md-6">
                    <div class="glass-card">
                        <div class="glass-icon-box text-white" style="background: linear-gradient(135deg, #f59e0b 0%, #ef4444 100%);">
                            <i class="bi bi-signpost-split-fill fs-3"></i>
                        </div>
                        <h4 class="fw-bold text-dark mb-3">Smart Route Optimization</h4>
                        <p class="text-muted mb-0">Helps drivers navigate efficient routes to bypass traffic congestion, reducing fuel usage and transit times.</p>
                    </div>
                </div>

                <div class="col-lg-4 col-md-6">
                    <div class="glass-card">
                        <div class="glass-icon-box text-white" style="background: linear-gradient(135deg, #10b981 0%, #059669 100%);">
                            <i class="bi bi-universal-access fs-3"></i>
                        </div>
                        <h4 class="fw-bold text-dark mb-3">Accessibility Friendly</h4>
                        <p class="text-muted mb-0">Inclusive features designed specifically to assist senior commuters and PWD passengers effortlessly.</p>
                    </div>
                </div>

                <div class="col-lg-4 col-md-6">
                    <div class="glass-card">
                        <div class="glass-icon-box text-white" style="background: linear-gradient(135deg, #8b5cf6 0%, #6366f1 100%);">
                            <i class="bi bi-speedometer2 fs-3"></i>
                        </div>
                        <h4 class="fw-bold text-dark mb-3">Operator Dashboards</h4>
                        <p class="text-muted mb-0">Comprehensive analytics tools for cooperative managers to track daily earnings, driver shifts, and maintenance schedules.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- JeepniGo solution -->
    <section id="features" class="section-light" style="background: linear-gradient(180deg, rgba(219, 234, 254, 0.45) 0%, rgba(248, 250, 252, 0.8) 100%);">
        <div class="container">
            <div class="row align-items-center g-5">
                <div class="col-lg-6">
                    <div class="glass-card p-4 p-md-5">
                        <div class="text-center mb-4">
                            <div class="glass-icon-box mx-auto text-white" style="background: var(--primary-gradient); width: 76px; height: 76px;">
                                <i class="bi bi-bus-front-fill fs-2"></i>
                            </div>
                            <h3 class="fw-bold text-dark mb-2">Innovation in Public Transit</h3>
                            <p class="text-muted fs-6">Empowering Filipino drivers, commuters, and cooperatives through technology.</p>
                        </div>
                        <div class="row text-center border-top pt-4">
                            <div class="col-4">
                                <h4 class="fw-bold text-primary mb-0">2025</h4>
                                <small class="text-muted">Established</small>
                            </div>
                            <div class="col-4">
                                <h4 class="fw-bold text-info mb-0">24/7</h4>
                                <small class="text-muted">System Uptime</small>
                            </div>
                            <div class="col-4">
                                <h4 class="fw-bold text-success mb-0">100%</h4>
                                <small class="text-muted">Encrypted</small>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-lg-6">
                    <span class="glass-pill-badge mb-3">THE JEEPNIGO SOLUTION</span>
                    <h2 class="display-6 fw-bold text-dark mb-4">One connected experience for public transport</h2>
                    <p class="text-muted mb-4 fs-6" style="line-height: 1.8;">
                        JeepniGo is a pioneering digital platform built to elevate traditional jeepney transit in the Philippines. By bridging passengers, drivers, and cooperative operators through modern technology, we bring reliable schedule management, live GPS tracking, and digital payments to everyday rides.
                    </p>

                    <div class="d-flex flex-column gap-3">
                        <div class="d-flex align-items-start">
                            <div class="rounded-circle bg-primary bg-opacity-10 text-primary p-2 me-3 mt-1">
                                <i class="bi bi-check-lg fw-bold"></i>
                            </div>
                            <div>
                                <h6 class="fw-bold text-dark mb-1">Reliable & Predictable Rides</h6>
                                <p class="text-muted small mb-0">No more guessing arrival times — track routes live on your mobile device.</p>
                            </div>
                        </div>
                        <div class="d-flex align-items-start">
                            <div class="rounded-circle bg-primary bg-opacity-10 text-primary p-2 me-3 mt-1">
                                <i class="bi bi-check-lg fw-bold"></i>
                            </div>
                            <div>
                                <h6 class="fw-bold text-dark mb-1">Empowering Local Transport Operators</h6>
                                <p class="text-muted small mb-0">Providing tools for drivers and cooperatives to manage boundary payments and daily revenue efficiently.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Commuter benefits -->
    <section id="benefits" class="section-light">
        <div class="container">
            <div class="text-center mb-5">
                <span class="glass-pill-badge mb-3">COMMUTER BENEFITS</span>
                <h2 class="display-6 fw-bold text-dark mb-3">Built around a calmer, clearer commute</h2>
                <p class="lead text-muted mx-auto" style="max-width: 600px;">Plan with better information, complete common trip actions from one place, and keep a clear record of your journey.</p>
            </div>

            <div class="row g-4">
                <div class="col-lg-4 col-md-6">
                    <div class="glass-card d-flex flex-column justify-content-between">
                        <div>
                            <div class="text-warning mb-3">
                                <i class="bi bi-star-fill"></i>
                                <i class="bi bi-star-fill"></i>
                                <i class="bi bi-star-fill"></i>
                                <i class="bi bi-star-fill"></i>
                                <i class="bi bi-star-fill"></i>
                            </div>
                            <p class="text-muted fst-italic mb-4">"JeepniGo has eliminated the stress of waiting in long lines after work. Being able to track the exact location of my jeepney is a game changer."</p>
                        </div>
                        <div class="d-flex align-items-center">
                            <div class="rounded-circle me-3 text-white fw-bold d-flex align-items-center justify-content-center" style="width: 48px; height: 48px; background: var(--primary-gradient);">
                                SJ
                            </div>
                            <div>
                                <h6 class="mb-0 fw-bold text-dark">Sarah Johnson</h6>
                                <small class="text-muted">Daily Commuter</small>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-lg-4 col-md-6">
                    <div class="glass-card d-flex flex-column justify-content-between">
                        <div>
                            <div class="text-warning mb-3">
                                <i class="bi bi-star-fill"></i>
                                <i class="bi bi-star-fill"></i>
                                <i class="bi bi-star-fill"></i>
                                <i class="bi bi-star-fill"></i>
                                <i class="bi bi-star-fill"></i>
                            </div>
                            <p class="text-muted fst-italic mb-4">"Managing boundary payments and keeping track of passenger fares used to be difficult. JeepniGo's driver dashboard makes everything clear and simple."</p>
                        </div>
                        <div class="d-flex align-items-center">
                            <div class="rounded-circle me-3 text-white fw-bold d-flex align-items-center justify-content-center" style="width: 48px; height: 48px; background: linear-gradient(135deg, #ec4899 0%, #8b5cf6 100%);">
                                MS
                            </div>
                            <div>
                                <h6 class="mb-0 fw-bold text-dark">Michael Santos</h6>
                                <small class="text-muted">Jeepney Driver</small>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-lg-4 col-md-6">
                    <div class="glass-card d-flex flex-column justify-content-between">
                        <div>
                            <div class="text-warning mb-3">
                                <i class="bi bi-star-fill"></i>
                                <i class="bi bi-star-fill"></i>
                                <i class="bi bi-star-fill"></i>
                                <i class="bi bi-star-fill"></i>
                                <i class="bi bi-star-half"></i>
                            </div>
                            <p class="text-muted fst-italic mb-4">"As a student who travels across the city daily, the e-wallet feature is so convenient. No more digging for exact loose change."</p>
                        </div>
                        <div class="d-flex align-items-center">
                            <div class="rounded-circle me-3 text-white fw-bold d-flex align-items-center justify-content-center" style="width: 48px; height: 48px; background: linear-gradient(135deg, #06b6d4 0%, #3b82f6 100%);">
                                MG
                            </div>
                            <div>
                                <h6 class="mb-0 fw-bold text-dark">Maria Garcia</h6>
                                <small class="text-muted">University Student</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Call to Action Section -->
    <section class="section-light">
        <div class="container">
            <div class="glass-card p-5 text-center position-relative overflow-hidden" style="background: linear-gradient(135deg, rgba(224, 231, 255, 0.8) 0%, rgba(243, 232, 255, 0.8) 100%); border-color: rgba(99, 102, 241, 0.3);">
                <div class="py-4 mx-auto" style="max-width: 700px;">
                    <span class="glass-pill-badge mb-3"><i class="bi bi-lightning-charge-fill me-1"></i>GET STARTED TODAY</span>
                    <h2 class="display-5 fw-bold text-dark mb-4">Ready to Upgrade Your Daily Commute?</h2>
                    <p class="lead text-muted mb-4">Join thousands of commuters, drivers, and operators experiencing the future of transportation in the Philippines.</p>
                    <button onclick="openLoginModal()" class="btn btn-glass-primary btn-lg px-5 py-3 fs-6">
                        <i class="bi bi-rocket-takeoff-fill me-2"></i>Create Free Account
                    </button>
                </div>
            </div>
        </div>
    </section>

    <!-- Contact Section -->
    <section id="contact" class="section-light">
        <div class="container">
            <div class="text-center mb-5">
                <span class="glass-pill-badge mb-3">CONTACT US</span>
                <h2 class="display-6 fw-bold text-dark mb-3">Get in Touch With Our Team</h2>
                <p class="lead text-muted mx-auto" style="max-width: 600px;">Have questions or need support? Send us a message and we'll get back to you promptly.</p>
            </div>

            <div class="row justify-content-center">
                <div class="col-lg-8">
                    <div class="glass-card p-4 p-md-5">
                        <form id="contactForm" class="needs-validation" novalidate>
                            <div class="row g-4">
                                <div class="col-md-6">
                                    <label for="name" class="fw-semibold text-dark mb-2">Your Name</label>
                                    <input type="text" class="glass-input" id="name" placeholder="John Doe" required>
                                </div>
                                <div class="col-md-6">
                                    <label for="contact-email" class="fw-semibold text-dark mb-2">Your Email</label>
                                    <input type="email" class="glass-input" id="contact-email" placeholder="john@example.com" required>
                                </div>
                                <div class="col-12">
                                    <label for="subject" class="fw-semibold text-dark mb-2">Subject</label>
                                    <input type="text" class="glass-input" id="subject" placeholder="How can we help?" required>
                                </div>
                                <div class="col-12">
                                    <label for="message" class="fw-semibold text-dark mb-2">Message</label>
                                    <textarea class="glass-input" id="message" rows="4" placeholder="Tell us more..." required></textarea>
                                </div>
                                <div class="col-12 text-center pt-2">
                                    <button type="submit" class="btn btn-glass-primary px-5 py-3">
                                        <i class="bi bi-send-fill me-2"></i>Send Message
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="pt-5 pb-4 border-top" style="background: rgba(255, 255, 255, 0.6); backdrop-filter: var(--glass-blur);">
        <div class="container">
            <div class="row g-4 mb-5">
                <div class="col-lg-4 col-md-6">
                    <div class="d-flex align-items-center mb-3">
                        <img src="img/logo12.png" width="36" class="me-2" alt="JeepniGo">
                        <span class="navbar-brand-text fs-4">JeepniGo</span>
                    </div>
                    <p class="text-muted small mb-4" style="line-height: 1.7;">
                        Making public transportation accessible, efficient, and enjoyable for everyone across the Philippines.
                    </p>
                    <div class="d-flex gap-2">
                        <a href="#" class="btn btn-sm btn-outline-secondary rounded-circle" style="width: 38px; height: 38px; display: flex; align-items: center; justify-content: center;"><i class="bi bi-facebook"></i></a>
                        <a href="#" class="btn btn-sm btn-outline-secondary rounded-circle" style="width: 38px; height: 38px; display: flex; align-items: center; justify-content: center;"><i class="bi bi-twitter-x"></i></a>
                        <a href="#" class="btn btn-sm btn-outline-secondary rounded-circle" style="width: 38px; height: 38px; display: flex; align-items: center; justify-content: center;"><i class="bi bi-instagram"></i></a>
                    </div>
                </div>

                <div class="col-lg-2 col-md-6">
                    <h6 class="fw-bold text-dark mb-3">Quick Links</h6>
                    <ul class="list-unstyled small d-flex flex-column gap-2 mb-0">
                        <li><a href="#" class="text-muted text-decoration-none">Home</a></li>
                        <li><a href="#features" class="text-muted text-decoration-none">Our Solution</a></li>
                        <li><a href="#features" class="text-muted text-decoration-none">Features</a></li>
                        <li><a href="#contact" class="text-muted text-decoration-none">Contact</a></li>
                    </ul>
                </div>

                <div class="col-lg-2 col-md-6">
                    <h6 class="fw-bold text-dark mb-3">Legal</h6>
                    <ul class="list-unstyled small d-flex flex-column gap-2 mb-0">
                        <li><a href="#" class="text-muted text-decoration-none">Privacy Policy</a></li>
                        <li><a href="#" class="text-muted text-decoration-none">Terms of Service</a></li>
                        <li><a href="#" class="text-muted text-decoration-none">Security</a></li>
                    </ul>
                </div>

                <div class="col-lg-4 col-md-6">
                    <h6 class="fw-bold text-dark mb-3">Stay Updated</h6>
                    <p class="text-muted small mb-3">Subscribe to receive system updates and new route notifications.</p>
                    <div class="d-flex gap-2">
                        <input type="email" class="glass-input py-2 px-3 small" placeholder="Enter your email">
                        <button class="btn btn-glass-primary px-3 text-nowrap">Subscribe</button>
                    </div>
                </div>
            </div>

            <div class="text-center border-top pt-4">
                <p class="text-muted small mb-0">&copy; 2025 JeepniGo. All rights reserved. Crafted with care for modern commute.</p>
            </div>
        </div>
    </footer>

    <!-- ==========================================================================
       Modals (Login & Signup)
       ========================================================================== -->
    <!-- Login Modal -->
    <div class="modal fade" id="loginModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content modal-content-glass">
                <div class="modal-header border-0 pb-0 pt-4 px-4 position-relative justify-content-center">
                    <div class="text-center">
                        <img src="img/logo12.png" width="45" class="mb-2" alt="Logo">
                        <h4 class="fw-bold text-dark mb-1">Welcome Back</h4>
                        <p class="text-muted small mb-0">Log in to access your JeepniGo dashboard</p>
                    </div>
                    <button type="button" class="btn-close position-absolute end-0 top-0 m-4" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    <form id="loginForm" method="post">
                        <input type="hidden" name="action" value="login">
                        <div class="mb-3">
                            <label class="form-label fw-semibold text-dark small">Email Address</label>
                            <input type="email" name="email" class="glass-input" placeholder="name@example.com" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold text-dark small">Password</label>
                            <input type="password" name="password" class="glass-input" placeholder="Enter your password" required>
                        </div>
                        <div class="d-flex justify-content-between align-items-center mb-4 small">
                            <div class="form-check">
                                <input type="checkbox" class="form-check-input" id="rememberMe">
                                <label class="form-check-label text-muted" for="rememberMe">Remember me</label>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-glass-primary w-100 py-3 font-semibold">Log In</button>
                    </form>
                    <div class="text-center mt-4">
                        <p class="mb-0 text-muted small">Don't have an account? <a href="#" class="text-primary fw-bold text-decoration-none" data-bs-toggle="modal" data-bs-target="#signupModal" data-bs-dismiss="modal">Sign up</a></p>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Signup Modal -->
    <div class="modal fade" id="signupModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content modal-content-glass">
                <div class="modal-header border-0 pb-0 pt-4 px-4 position-relative justify-content-center">
                    <div class="text-center">
                        <img src="img/logo12.png" width="45" class="mb-2" alt="Logo">
                        <h4 class="fw-bold text-dark mb-1">Create an Account</h4>
                        <p class="text-muted small mb-0">Join JeepniGo for real-time commutes</p>
                    </div>
                    <button type="button" class="btn-close position-absolute end-0 top-0 m-4" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    <form id="signupForm" method="post">
                        <input type="hidden" name="action" value="signup">
                        <div class="row g-2 mb-3">
                            <div class="col-6">
                                <label class="form-label fw-semibold text-dark small">First Name</label>
                                <input type="text" name="firstName" class="glass-input" placeholder="First name" required>
                            </div>
                            <div class="col-6">
                                <label class="form-label fw-semibold text-dark small">Last Name</label>
                                <input type="text" name="lastName" class="glass-input" placeholder="Last name" required>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold text-dark small">Email Address</label>
                            <input type="email" name="email" class="glass-input" placeholder="name@example.com" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold text-dark small">Password</label>
                            <input type="password" name="password" class="glass-input" placeholder="Create password" required>
                        </div>
                        <div class="mb-4">
                            <label class="form-label fw-semibold text-dark small">Confirm Password</label>
                            <input type="password" name="confirm_password" class="glass-input" placeholder="Confirm password" required>
                        </div>
                        <button type="submit" class="btn btn-glass-primary w-100 py-3 font-semibold">Create Account</button>
                    </form>
                    <div class="text-center mt-4">
                        <p class="mb-0 text-muted small">Already have an account? <a href="#" class="text-primary fw-bold text-decoration-none" data-bs-toggle="modal" data-bs-target="#loginModal" data-bs-dismiss="modal">Log in</a></p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.7.32/dist/sweetalert2.all.min.js"></script>
    <script>
    document.addEventListener("DOMContentLoaded", function () {
        // Initialize Bootstrap Modals
        const loginModalEl = document.getElementById('loginModal');
        const signupModalEl = document.getElementById('signupModal');
        const loginModal = new bootstrap.Modal(loginModalEl);
        const signupModal = new bootstrap.Modal(signupModalEl);

        // Global helper to open login modal
        window.openLoginModal = function() {
            loginModal.show();
        };

        // Navbar scroll glass effect
        window.addEventListener('scroll', function() {
            const navbar = document.querySelector('.navbar-glass');
            if (navbar) {
                if (window.scrollY > 40) {
                    navbar.classList.add('scrolled');
                } else {
                    navbar.classList.remove('scrolled');
                }
            }
        });

        // Form validation for contact form
        const forms = document.querySelectorAll('.needs-validation');
        Array.from(forms).forEach(form => {
            form.addEventListener('submit', event => {
                if (!form.checkValidity()) {
                    event.preventDefault();
                    event.stopPropagation();
                } else if (form.id === 'contactForm') {
                    event.preventDefault();
                    Swal.fire({
                        icon: 'success',
                        title: 'Message Sent!',
                        text: 'Thank you for reaching out. We will get back to you shortly.',
                        confirmColor: '#4f46e5'
                    });
                    form.reset();
                    form.classList.remove('was-validated');
                    return;
                }
                form.classList.add('was-validated');
            }, false);
        });

        // AJAX Login Form Handler
        const loginForm = document.getElementById('loginForm');
        if (loginForm) {
            loginForm.addEventListener('submit', function(e) {
                e.preventDefault();
                const formData = new FormData(this);
                
                fetch('', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.status === 'success') {
                        Swal.fire({
                            icon: 'success',
                            title: 'Success!',
                            text: data.message,
                            showConfirmButton: false,
                            timer: 1500
                        }).then(() => {
                            window.location.href = data.redirect;
                        });
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Login Error',
                            text: data.message,
                            confirmColor: '#4f46e5'
                        });
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    Swal.fire({
                        icon: 'error',
                        title: 'Error!',
                        text: 'An unexpected error occurred. Please try again.',
                        confirmColor: '#4f46e5'
                    });
                });
            });
        }

        // AJAX Signup Form Handler
        const signupForm = document.getElementById('signupForm');
        if (signupForm) {
            signupForm.addEventListener('submit', function(e) {
                e.preventDefault();
                const formData = new FormData(this);
                
                fetch('', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.status === 'success') {
                        Swal.fire({
                            icon: 'success',
                            title: 'Account Created!',
                            text: data.message,
                            showConfirmButton: false,
                            timer: 1500
                        }).then(() => {
                            signupModal.hide();
                            loginModal.show();
                        });
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Signup Error',
                            text: data.message,
                            confirmColor: '#4f46e5'
                        });
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    Swal.fire({
                        icon: 'error',
                        title: 'Error!',
                        text: 'An unexpected error occurred. Please try again.',
                        confirmColor: '#4f46e5'
                    });
                });
            });
        }
    });
    </script>
</body>
</html>
