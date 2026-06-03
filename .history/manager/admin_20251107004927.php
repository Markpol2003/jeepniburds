<?php
session_start();
require_once __DIR__ . '/../db_config.php';

// Admin-only access
if (!isset($_SESSION['user_id']) || strtolower($_SESSION['user_type'] ?? '') !== 'admin') {
    header('Location: ../shared/index.php');
    exit();
}

// Ensure applications table exists
$conn->query("CREATE TABLE IF NOT EXISTS cooperative_applications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    cooperative_name VARCHAR(255) NOT NULL,
    registration_number VARCHAR(255) NOT NULL,
    certificate VARCHAR(255) NOT NULL,
    contact_email VARCHAR(255) NOT NULL,
    status VARCHAR(50) NOT NULL DEFAULT 'Pending',
    submitted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['application_id'], $_POST['action'])) {
    $applicationId = (int)$_POST['application_id'];
    $action = $_POST['action'];

    $get = $conn->prepare('SELECT * FROM cooperative_applications WHERE id = ?');
    $get->bind_param('i', $applicationId);
    $get->execute();
    $app = $get->get_result()->fetch_assoc();
    $get->close();

    if ($app) {
        if ($action === 'Approve') {
            $status = 'Approved';
            $upd = $conn->prepare('UPDATE cooperative_applications SET status = ? WHERE id = ?');
            $upd->bind_param('si', $status, $applicationId);
            $upd->execute();
            $upd->close();

            // Optionally promote contact email to manager
            if (!empty($app['contact_email'])) {
                $promote = $conn->prepare("UPDATE users SET userType = 'manager' WHERE email = ?");
                $promote->bind_param('s', $app['contact_email']);
                $promote->execute();
                $promote->close();
            }
            $_SESSION['message'] = 'Application approved.';
        } elseif ($action === 'Reject') {
            $status = 'Rejected';
            $upd = $conn->prepare('UPDATE cooperative_applications SET status = ? WHERE id = ?');
            $upd->bind_param('si', $status, $applicationId);
            $upd->execute();
            $upd->close();
            $_SESSION['message'] = 'Application rejected.';
        } elseif ($action === 'Delete') {
            $del = $conn->prepare('DELETE FROM cooperative_applications WHERE id = ?');
            $del->bind_param('i', $applicationId);
            $del->execute();
            $del->close();
            $_SESSION['message'] = 'Application deleted.';
        }
    } else {
        $_SESSION['message'] = 'Application not found.';
    }

    header('Location: admin.php');
    exit();
}

// Bulk actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_action']) && isset($_POST['ids']) && is_array($_POST['ids'])) {
    $ids = array_map('intval', $_POST['ids']);
    $ids = array_values(array_filter($ids));
    if (!empty($ids)) {
        $in = implode(',', array_fill(0, count($ids), '?'));
        $types = str_repeat('i', count($ids));
        $bulk = $_POST['bulk_action'];
        if ($bulk === 'Approve' || $bulk === 'Reject') {
            $status = ($bulk === 'Approve') ? 'Approved' : 'Rejected';
            $stmt = $conn->prepare("UPDATE cooperative_applications SET status = '$status' WHERE id IN ($in)");
            $stmt->bind_param($types, ...$ids);
            $stmt->execute();
            $stmt->close();
            if ($bulk === 'Approve') {
                // Promote contacts for approved ids
                $sel = $conn->prepare("SELECT contact_email FROM cooperative_applications WHERE id IN ($in)");
                $sel->bind_param($types, ...$ids);
                $sel->execute();
                $res = $sel->get_result();
                while ($r = $res->fetch_assoc()) {
                    if (!empty($r['contact_email'])) {
                        $prom = $conn->prepare("UPDATE users SET userType='manager' WHERE email=?");
                        $prom->bind_param('s', $r['contact_email']);
                        $prom->execute();
                        $prom->close();
                    }
                }
                $sel->close();
            }
            $_SESSION['message'] = "Bulk $status complete.";
        }
    }
    header('Location: admin.php?section=applications');
    exit();
}

// CSV exports
if (isset($_GET['export'])) {
    $what = strtolower($_GET['export']);
    if ($what === 'applications') {
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="applications.csv"');
        $out = fopen('php://output', 'w');
        fputcsv($out, ['ID','Cooperative','Registration','Contact','Status','Submitted']);
        $res = $conn->query("SELECT id, cooperative_name, registration_number, contact_email, status, submitted_at FROM cooperative_applications ORDER BY submitted_at DESC");
        while ($r = $res->fetch_assoc()) {
            fputcsv($out, [$r['id'],$r['cooperative_name'],$r['registration_number'],$r['contact_email'],$r['status'],$r['submitted_at']]);
        }
        fclose($out); exit();
    }
    if ($what === 'users') {
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="users.csv"');
        $out = fopen('php://output', 'w');
        fputcsv($out, ['ID','First Name','Last Name','Email','Role','Created']);
        $res = $conn->query("SELECT id, firstName, lastName, email, userType, created_at FROM users ORDER BY id DESC");
        while ($r = $res->fetch_assoc()) {
            fputcsv($out, [$r['id'],$r['firstName'],$r['lastName'],$r['email'],$r['userType'],$r['created_at']]);
        }
        fclose($out); exit();
    }
}

$applications = $conn->query('SELECT * FROM cooperative_applications ORDER BY submitted_at DESC');

// Dashboard stats
$section = $_GET['section'] ?? 'dashboard';

// Total users
$totalUsers = 0;
if ($res = $conn->query("SELECT COUNT(*) AS c FROM users")) {
    $totalUsers = (int)($res->fetch_assoc()['c'] ?? 0);
}

// Users per role
$roleCounts = [
    'passenger' => 0,
    'driver' => 0,
    'operator' => 0,
    'manager' => 0,
    'treasurer' => 0,
    'admin' => 0,
];
if ($res = $conn->query("SELECT LOWER(userType) AS role, COUNT(*) AS c FROM users GROUP BY userType")) {
    while ($row = $res->fetch_assoc()) {
        $r = $row['role'] ?? '';
        if (isset($roleCounts[$r])) $roleCounts[$r] = (int)$row['c'];
    }
}

// Cooperative application status counts
$appCounts = [ 'Pending' => 0, 'Approved' => 0, 'Rejected' => 0 ];
if ($res = $conn->query("SELECT status, COUNT(*) AS c FROM cooperative_applications GROUP BY status")) {
    while ($row = $res->fetch_assoc()) {
        $s = $row['status'] ?? '';
        if (isset($appCounts[$s])) $appCounts[$s] = (int)$row['c'];
    }
}

// Recent users
$recentUsers = [];
if ($res = $conn->query("SELECT id, firstName, lastName, email, userType, created_at FROM users ORDER BY id DESC LIMIT 8")) {
    while ($row = $res->fetch_assoc()) { $recentUsers[] = $row; }
}

// Build last 7 days labels and series
$days = [];
for ($i = 6; $i >= 0; $i--) {
    $days[] = date('Y-m-d', strtotime("-$i days"));
}
$userDaily = array_fill_keys($days, 0);
$appDaily = array_fill_keys($days, 0);

// Users per day (last 7 days)
if ($res = $conn->query("SELECT DATE(created_at) AS d, COUNT(*) AS c FROM users WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY) GROUP BY DATE(created_at)")) {
    while ($row = $res->fetch_assoc()) {
        $d = $row['d'];
        if (isset($userDaily[$d])) { $userDaily[$d] = (int)$row['c']; }
    }
}
// Applications per day (last 7 days)
if ($res = $conn->query("SELECT DATE(submitted_at) AS d, COUNT(*) AS c FROM cooperative_applications WHERE submitted_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY) GROUP BY DATE(submitted_at)")) {
    while ($row = $res->fetch_assoc()) {
        $d = $row['d'];
        if (isset($appDaily[$d])) { $appDaily[$d] = (int)$row['c']; }
    }
}

$chartLabels = array_map(function($d){ return date('M d', strtotime($d)); }, array_keys($userDaily));
$userSeries  = array_values($userDaily);
$appSeries   = array_values($appDaily);

// Users table data on demand
$usersData = [];
if ($section === 'users') {
    if ($res = $conn->query("SELECT id, firstName, lastName, email, userType, created_at FROM users ORDER BY id DESC LIMIT 200")) {
        while ($row = $res->fetch_assoc()) { $usersData[] = $row; }
    }
}

// Calculate queue metrics for performance monitoring
function calculateQueueMetrics($conn, $date = null) {
    $dateFilter = $date ? "AND DATE(r.boarded_at) = '$date'" : "AND DATE(r.boarded_at) = CURDATE()";
    
    $lambdaQuery = "SELECT COUNT(*) as total FROM reservations r WHERE r.status = 'boarded' $dateFilter";
    $lambdaResult = $conn->query($lambdaQuery);
    $lambda = $lambdaResult ? $lambdaResult->fetch_assoc()['total'] : 0;
    
    $serviceQuery = "SELECT AVG(TIMESTAMPDIFF(MINUTE, r.here_at, r.boarded_at)) as avg_service_time 
                     FROM reservations r 
                     WHERE r.status = 'boarded' AND r.here_at IS NOT NULL AND r.boarded_at IS NOT NULL $dateFilter";
    $serviceResult = $conn->query($serviceQuery);
    $avgServiceTime = $serviceResult ? $serviceResult->fetch_assoc()['avg_service_time'] : 1;
    $mu = $avgServiceTime > 0 ? (60 / $avgServiceTime) : 5;
    
    $rho = $mu > 0 ? ($lambda / $mu) : 0;
    if ($rho > 1) $rho = 1;
    
    $wq = ($mu > 0 && $rho < 1) ? ($rho / ($mu * (1 - $rho))) * 60 : 0;
    
    return [
        'lambda' => round($lambda, 2),
        'mu' => round($mu, 2),
        'rho' => round($rho * 100, 2),
        'wq' => round($wq, 3),
        'avg_service_time' => round($avgServiceTime, 2)
    ];
}

function getWaitingTimesByTraffic($conn) {
    $query = "SELECT 
                CASE 
                    WHEN TIMESTAMPDIFF(MINUTE, r.here_at, r.boarded_at) <= 5 THEN 'Light Traffic'
                    WHEN TIMESTAMPDIFF(MINUTE, r.here_at, r.boarded_at) <= 10 THEN 'Moderate Traffic'
                    WHEN TIMESTAMPDIFF(MINUTE, r.here_at, r.boarded_at) <= 20 THEN 'Heavy Traffic'
                    ELSE 'Extreme Traffic'
                END as traffic_condition,
                AVG(TIMESTAMPDIFF(MINUTE, r.here_at, r.boarded_at)) as avg_wait_time,
                COUNT(*) as trip_count
              FROM reservations r
              WHERE r.status = 'boarded' 
                AND r.here_at IS NOT NULL 
                AND r.boarded_at IS NOT NULL
                AND DATE(r.boarded_at) >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
              GROUP BY traffic_condition
              ORDER BY 
                CASE traffic_condition
                    WHEN 'Light Traffic' THEN 1
                    WHEN 'Moderate Traffic' THEN 2
                    WHEN 'Heavy Traffic' THEN 3
                    WHEN 'Extreme Traffic' THEN 4
                END";
    
    $result = $conn->query($query);
    $data = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $data[$row['traffic_condition']] = [
                'with_jeepnigo' => round($row['avg_wait_time'], 1),
                'trip_count' => $row['trip_count']
            ];
        }
    }
    
    $estimatedWithout = [
        'Light Traffic' => isset($data['Light Traffic']) ? $data['Light Traffic']['with_jeepnigo'] * 3 : 15,
        'Moderate Traffic' => isset($data['Moderate Traffic']) ? $data['Moderate Traffic']['with_jeepnigo'] * 3.5 : 22.5,
        'Heavy Traffic' => isset($data['Heavy Traffic']) ? $data['Heavy Traffic']['with_jeepnigo'] * 4.7 : 40,
        'Extreme Traffic' => isset($data['Extreme Traffic']) ? $data['Extreme Traffic']['with_jeepnigo'] * 7.5 : 75
    ];
    
    return ['with_jeepnigo' => $data, 'without_jeepnigo' => $estimatedWithout];
}

$todayMetrics = null;
$trafficData = null;
if ($section === 'performance') {
    $todayMetrics = calculateQueueMetrics($conn);
    $trafficData = getWaitingTimesByTraffic($conn);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin · Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <style>
      :root { --gradA:#4f46e5; --gradB:#06b6d4; --gradC:#7c3aed; }
      @keyframes gradientShift {
        0% { background-position: 0% 50%; }
        50% { background-position: 100% 50%; }
        100% { background-position: 0% 50%; }
      }
      .animated-gradient {
        background: linear-gradient(90deg, var(--gradA), var(--gradB), var(--gradC));
        background-size: 200% 200%;
        animation: gradientShift 10s ease infinite;
      }
      .card-fx { transition: transform .25s ease, box-shadow .25s ease; }
      .card-fx:hover { transform: translateY(-4px); box-shadow: 0 15px 35px rgba(2,6,23,.12); }
      .reveal { opacity: 0; transform: translateY(8px); transition: opacity .5s ease, transform .5s ease; }
      .reveal.in { opacity: 1; transform: translateY(0); }
      .bar { transition: height .8s ease; }
      /* Comprehensive dark mode overrides */
      .dark { background-color: #0b1220; }
      .dark .bg-slate-50 { background-color: #0b1220; }
      .dark .bg-white { background-color: #0f172a; }
      .dark .bg-slate-100 { background-color: #111827; }
      .dark .bg-white\/10 { background-color: rgba(255,255,255,0.08); }
      .dark .hover\:bg-white\/20:hover { background-color: rgba(255,255,255,0.16); }
      
      /* Text colors - make everything white/light in dark mode */
      .dark .text-slate-800,
      .dark .text-slate-900,
      .dark .text-slate-700,
      .dark .text-slate-600,
      .dark .text-slate-500,
      .dark .text-gray-800,
      .dark .text-gray-900 { color: #e5e7eb !important; }
      
      .dark .text-slate-400 { color: #cbd5e1 !important; }
      
      /* Ensure all table text is visible */
      .dark table,
      .dark table thead,
      .dark table tbody,
      .dark table th,
      .dark table td { color: #e5e7eb !important; }
      
      /* Ensure all headings are visible */
      .dark h1, .dark h2, .dark h3, .dark h4, .dark h5, .dark h6 { color: #f3f4f6 !important; }
      
      /* Ensure all paragraphs and divs with text are visible */
      .dark p, .dark div, .dark span { color: #e5e7eb; }
      
      /* Border colors */
      .dark .border { border-color: #1f2937 !important; }
      .dark .divide-y > * { border-color: #1f2937 !important; }
      
      /* Keep badges readable */
      .dark .bg-yellow-100 { background-color: #fbbf24; color: #78350f !important; }
      .dark .bg-green-100 { background-color: #22c55e; color: #14532d !important; }
      .dark .bg-red-100 { background-color: #ef4444; color: #7f1d1d !important; }
      .dark .bg-slate-100 { background-color: #374151; color: #e5e7eb !important; }
      /* Tooltip */
      .tooltip-floating { position: fixed; z-index: 50; pointer-events: none; padding: 6px 10px; border-radius: 8px; background: rgba(15,23,42,0.9); color: #fff; font-size: 12px; box-shadow: 0 6px 18px rgba(2,6,23,.25); transform: translate(-50%, -120%); }
      
      /* Responsive Mobile Styles */
      .mobile-navbar { display: none; }
      .desktop-navbar { display: block; }
      
      @media (max-width: 768px) {
        .mobile-navbar { display: block !important; }
        .desktop-navbar { display: none !important; }
        
        /* Mobile menu */
        #mobileMenu { 
          max-height: 0;
          overflow: hidden;
          transition: max-height 0.3s ease-in-out;
        }
        #mobileMenu.active { 
          max-height: 500px;
          display: block !important;
        }
        
        /* Mobile grid adjustments */
        .grid { grid-template-columns: 1fr !important; }
        .lg\:col-span-2 { grid-column: span 1 !important; }
        
        /* Mobile padding */
        .px-4.sm\:px-6.lg\:px-8 { padding-left: 1rem !important; padding-right: 1rem !important; }
        
        /* Mobile table scroll */
        .overflow-x-auto { 
          -webkit-overflow-scrolling: touch;
        }
        
        /* Mobile cards */
        .card-fx { margin-bottom: 1rem; }
        
        /* Mobile text sizes */
        .text-4xl { font-size: 2rem !important; }
        .text-3xl { font-size: 1.5rem !important; }
        .text-2xl { font-size: 1.25rem !important; }
        .text-xl { font-size: 1.125rem !important; }
        
        /* Hide on mobile */
        .md\:flex { flex-direction: column !important; align-items: flex-start !important; }
      }
      
      @media (max-width: 640px) {
        /* Extra small devices */
        .gap-4 { gap: 0.75rem !important; }
        .gap-6 { gap: 1rem !important; }
        .p-5 { padding: 1rem !important; }
        .px-4 { padding-left: 0.75rem !important; padding-right: 0.75rem !important; }
      }
    </style>
    </head>
<body class="bg-slate-50">

<!-- Mobile Top Navbar (visible only on mobile) -->
<nav class="mobile-navbar sticky top-0 z-50 text-white shadow animated-gradient">
  <div class="h-16 flex items-center justify-between px-4">
    <div class="flex items-center gap-3">
      <img src="../img/logo12.png" class="w-9 h-9" alt="Logo" />
      <span class="text-xl font-bold tracking-wide">Admin</span>
    </div>
    <button id="mobileMenuToggle" class="text-white p-2 rounded-md hover:bg-white/10">
      <i class="bi bi-list text-2xl"></i>
    </button>
  </div>
  <!-- Mobile Menu -->
  <div id="mobileMenu" class="hidden bg-gradient-to-r from-indigo-600 to-purple-600 border-t border-white/10">
    <div class="px-4 py-2 space-y-1">
      <a href="?section=dashboard" class="block px-4 py-3 rounded-md hover:bg-white/10 text-white text-sm font-semibold flex items-center gap-3">
        <i class="bi bi-speedometer2 text-lg"></i> Overview
      </a>
      <a href="?section=applications" class="block px-4 py-3 rounded-md hover:bg-white/10 text-white text-sm font-semibold flex items-center gap-3">
        <i class="bi bi-file-earmark-text text-lg"></i> Applications
      </a>
      <a href="?section=users" class="block px-4 py-3 rounded-md hover:bg-white/10 text-white text-sm font-semibold flex items-center gap-3">
        <i class="bi bi-people text-lg"></i> Users
      </a>
      <button id="mobileThemeToggle" class="w-full text-left px-4 py-3 rounded-md hover:bg-white/10 text-white text-sm font-semibold flex items-center gap-3">
        <i class="bi bi-moon-stars text-lg"></i> Theme
      </button>
      <a href="../logout.php" class="block px-4 py-3 rounded-md hover:bg-white/10 text-red-300 text-sm font-semibold flex items-center gap-3 border-t border-white/10 mt-2">
        <i class="bi bi-box-arrow-right text-lg"></i> Logout
      </a>
    </div>
  </div>
</nav>

<!-- Desktop Navbar (visible only on desktop) -->
<nav class="desktop-navbar sticky top-0 z-40 text-white shadow animated-gradient">
  <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
    <div class="h-16 flex items-center justify-between">
      <div class="flex items-center gap-3">
        <img src="../img/logo12.png" class="w-9 h-9" alt="Logo" />
        <span class="text-2xl font-bold tracking-wide">Admin</span>
      </div>
      <div class="flex items-center gap-2">
        <a href="?section=dashboard" class="px-3 py-1.5 rounded-md hover:bg-indigo-500 text-white text-sm font-semibold flex items-center gap-2"><i class="bi bi-speedometer2 text-lg"></i> Overview</a>
        <a href="?section=applications" class="px-3 py-1.5 rounded-md hover:bg-indigo-500 text-white text-sm font-semibold flex items-center gap-2"><i class="bi bi-file-earmark-text text-lg"></i> Applications</a>
        <a href="?section=users" class="px-3 py-1.5 rounded-md hover:bg-indigo-500 text-white text-sm font-semibold flex items-center gap-2"><i class="bi bi-people text-lg"></i> Users</a>
        <button id="themeToggle" class="px-3 py-1.5 rounded-md bg-white/10 hover:bg-white/20 text-white text-sm font-semibold flex items-center gap-2" title="Toggle theme"><i class="bi bi-moon-stars text-lg"></i> Theme</button>
        <a href="../logout.php" class="px-3 py-1.5 rounded-md bg-white/10 hover:bg-white/20 text-white text-sm font-semibold flex items-center gap-2"><i class="bi bi-box-arrow-right text-lg"></i> Logout</a>
      </div>
    </div>
  </div>
</nav>

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
    <?php if (isset($_SESSION['message'])): ?>
        <div class="alert alert-info text-center"><?= htmlspecialchars($_SESSION['message']); ?></div>
        <?php unset($_SESSION['message']); ?>
    <?php endif; ?>

    <?php if ($section === 'dashboard'): ?>
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-6 gap-4 mb-6 reveal">
        <div class="bg-white rounded-2xl border p-5 shadow card-fx"><div class="text-slate-500">Total Users</div><div class="mt-1 text-4xl font-extrabold text-slate-800 flex items-center gap-2"><i class="bi bi-people text-indigo-600 text-3xl"></i><span class="countup" data-target="<?= $totalUsers ?>">0</span></div></div>
        <div class="bg-white rounded-2xl border p-5 shadow card-fx"><div class="text-slate-500">Passengers</div><div class="mt-1 text-3xl font-bold text-slate-800 flex items-center gap-2"><i class="bi bi-person-walking text-blue-600 text-2xl"></i><span class="countup" data-target="<?= $roleCounts['passenger'] ?>">0</span></div></div>
        <div class="bg-white rounded-2xl border p-5 shadow card-fx"><div class="text-slate-500">Drivers</div><div class="mt-1 text-3xl font-bold text-slate-800 flex items-center gap-2"><i class="bi bi-truck-front text-amber-600 text-2xl"></i><span class="countup" data-target="<?= $roleCounts['driver'] ?>">0</span></div></div>
        <div class="bg-white rounded-2xl border p-5 shadow card-fx"><div class="text-slate-500">Operators</div><div class="mt-1 text-3xl font-bold text-slate-800 flex items-center gap-2"><i class="bi bi-gear-wide-connected text-emerald-600 text-2xl"></i><span class="countup" data-target="<?= $roleCounts['operator'] ?>">0</span></div></div>
        <div class="bg-white rounded-2xl border p-5 shadow card-fx"><div class="text-slate-500">Managers</div><div class="mt-1 text-3xl font-bold text-slate-800 flex items-center gap-2"><i class="bi bi-briefcase text-purple-600 text-2xl"></i><span class="countup" data-target="<?= $roleCounts['manager'] ?>">0</span></div></div>
        <div class="bg-white rounded-2xl border p-5 shadow card-fx"><div class="text-slate-500">Treasurers</div><div class="mt-1 text-3xl font-bold text-slate-800 flex items-center gap-2"><i class="bi bi-cash-coin text-teal-600 text-2xl"></i><span class="countup" data-target="<?= $roleCounts['treasurer'] ?>">0</span></div></div>
        </div>

      <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="bg-white rounded-2xl border p-5 shadow lg:col-span-2">
          <div class="flex items-center justify-between mb-4">
            <h2 class="text-xl font-semibold text-slate-800 flex items-center gap-2"><i class="bi bi-file-earmark-text text-indigo-600"></i> Cooperative Applications</h2>
            <div class="flex gap-2">
              <span class="px-3 py-1 rounded-full bg-yellow-100 text-yellow-800 text-sm">Pending: <?= $appCounts['Pending'] ?></span>
              <span class="px-3 py-1 rounded-full bg-green-100 text-green-800 text-sm">Approved: <?= $appCounts['Approved'] ?></span>
              <span class="px-3 py-1 rounded-full bg-red-100 text-red-800 text-sm">Rejected: <?= $appCounts['Rejected'] ?></span>
            </div>
            </div>
          <div class="mb-5 reveal">
            <h3 class="text-slate-700 font-semibold mb-2 flex items-center gap-2"><i class="bi bi-graph-up text-emerald-600"></i> Last 7 Days</h3>
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
              <div class="p-4 rounded-xl border bg-white">
                <div class="text-slate-500 text-sm mb-2">New Users</div>
                <div class="flex items-end gap-1">
                  <?php foreach ($userSeries as $v): $h = max(8, $v*10); ?>
                    <div class="w-6 bg-indigo-500/20 rounded-t-md bar" style="height: 0" data-h="<?= $h ?>"></div>
                  <?php endforeach; ?>
            </div>
                <div class="mt-2 text-xs text-slate-500 flex justify-between">
                  <?php foreach ($chartLabels as $lab): ?>
                    <span><?= htmlspecialchars($lab) ?></span>
                  <?php endforeach; ?>
            </div>
            </div>
              <div class="p-4 rounded-xl border bg-white">
                <div class="text-slate-500 text-sm mb-2">Applications</div>
                <div class="flex items-end gap-1">
                  <?php foreach ($appSeries as $v): $h = max(8, $v*10); ?>
                    <div class="w-6 bg-emerald-500/20 rounded-t-md bar" style="height: 0" data-h="<?= $h ?>"></div>
                  <?php endforeach; ?>
                </div>
                <div class="mt-2 text-xs text-slate-500 flex justify-between">
                  <?php foreach ($chartLabels as $lab): ?>
                    <span><?= htmlspecialchars($lab) ?></span>
                  <?php endforeach; ?>
                </div>
                </div>
            </div>
          </div>
          <div class="overflow-x-auto">
            <table class="min-w-full text-left text-sm">
              <thead class="bg-slate-50 text-slate-600 font-semibold">
                <tr>
                  <th class="px-4 py-3">Cooperative</th>
                  <th class="px-4 py-3">Reg. No.</th>
                  <th class="px-4 py-3">Contact</th>
                  <th class="px-4 py-3">Status</th>
                  <th class="px-4 py-3">Actions</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-100 bg-white">
                <?php if ($applications && $applications->num_rows): while ($row = $applications->fetch_assoc()): ?>
                  <tr>
                    <td class="px-4 py-3 font-medium text-slate-900"><?= htmlspecialchars($row['cooperative_name']) ?></td>
                    <td class="px-4 py-3 text-slate-700"><?= htmlspecialchars($row['registration_number']) ?></td>
                    <td class="px-4 py-3 text-slate-700"><?= htmlspecialchars($row['contact_email']) ?></td>
                    <td class="px-4 py-3">
                      <?php if ($row['status'] === 'Approved'): ?>
                        <span class="px-2.5 py-1 rounded-full bg-green-100 text-green-800 text-xs font-semibold">Approved</span>
                      <?php elseif ($row['status'] === 'Rejected'): ?>
                        <span class="px-2.5 py-1 rounded-full bg-red-100 text-red-800 text-xs font-semibold">Rejected</span>
                      <?php else: ?>
                        <span class="px-2.5 py-1 rounded-full bg-yellow-100 text-yellow-800 text-xs font-semibold">Pending</span>
                      <?php endif; ?>
                    </td>
                    <td class="px-4 py-3">
                      <form method="post" action="admin.php" class="flex items-center gap-2">
                        <input type="hidden" name="application_id" value="<?= (int)$row['id'] ?>">
                        <?php if ($row['status'] === 'Pending'): ?>
                          <button name="action" value="Approve" class="px-3 py-1.5 rounded-md bg-green-600 hover:bg-green-700 text-white text-sm"><i class="bi bi-check-lg"></i> Approve</button>
                          <button name="action" value="Reject" class="px-3 py-1.5 rounded-md bg-red-600 hover:bg-red-700 text-white text-sm"><i class="bi bi-x-lg"></i> Reject</button>
                        <?php endif; ?>
                        <a href="<?= htmlspecialchars($row['certificate']) ?>" target="_blank" class="px-3 py-1.5 rounded-md border text-slate-700 text-sm hover:bg-slate-50"><i class="bi bi-file-earmark-text"></i> View</a>
                        <button name="action" value="Delete" class="px-3 py-1.5 rounded-md border border-red-300 text-red-700 text-sm hover:bg-red-50"><i class="bi bi-trash"></i> Delete</button>
                      </form>
                    </td>
                  </tr>
                <?php endwhile; else: ?>
                  <tr><td colspan="5" class="px-4 py-6 text-center text-slate-500">No applications found.</td></tr>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>
        <div class="bg-white rounded-2xl border p-5 shadow">
          <h2 class="text-xl font-semibold text-slate-800 mb-4 flex items-center gap-2"><i class="bi bi-person-plus text-indigo-600"></i> Recent Users</h2>
          <div class="space-y-3">
            <?php foreach ($recentUsers as $u): ?>
              <div class="flex items-center justify-between">
                <div class="text-slate-800 font-medium">
                  <?= htmlspecialchars(trim(($u['firstName'] ?? '') . ' ' . ($u['lastName'] ?? ''))) ?>
                  <div class="text-slate-500 text-xs"><?= htmlspecialchars($u['email'] ?? '') ?></div>
                </div>
                <span class="px-2.5 py-1 rounded-full bg-slate-100 text-slate-700 text-xs font-semibold"><?= htmlspecialchars($u['userType'] ?? '') ?></span>
              </div>
            <?php endforeach; ?>
          </div>
        </div>
      </div>
    <?php elseif ($section === 'applications'): ?>
      <div class="bg-white rounded-2xl border p-5 shadow">
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3 mb-4">
          <h2 class="text-xl font-semibold text-slate-800 flex items-center gap-2"><i class="bi bi-file-earmark-text text-indigo-600"></i> Cooperative Applications</h2>
          <div class="flex items-center gap-2">
            <input id="appsSearch" class="px-3 py-2 rounded-md border text-sm" placeholder="Search cooperative or contact" />
            <select id="appsStatus" class="px-3 py-2 rounded-md border text-sm">
              <option value="">All Status</option>
              <option value="Pending">Pending</option>
              <option value="Approved">Approved</option>
              <option value="Rejected">Rejected</option>
            </select>
            <span class="px-3 py-1 rounded-full bg-slate-100 text-slate-700 text-sm">Total: <?= $applications ? $applications->num_rows : 0; ?></span>
          </div>
        </div>
        <div class="overflow-x-auto">
          <table class="min-w-full text-left text-sm">
            <thead class="bg-slate-50 text-slate-600 font-semibold">
              <tr>
                <th class="px-4 py-3">#</th>
                <th class="px-4 py-3">Cooperative</th>
                <th class="px-4 py-3">Reg. No.</th>
                <th class="px-4 py-3">Certificate</th>
                <th class="px-4 py-3">Contact</th>
                <th class="px-4 py-3">Status</th>
                <th class="px-4 py-3">Actions</th>
              </tr>
            </thead>
            <tbody id="appsTbody" class="divide-y divide-slate-100 bg-white">
              <?php if ($applications && $applications->num_rows > 0): $i=1; while ($row = $applications->fetch_assoc()): $rowTxt = strtolower(($row['cooperative_name']??'').' '.($row['registration_number']??'').' '.($row['contact_email']??'')); ?>
              <tr data-status="<?= htmlspecialchars($row['status']) ?>" data-text="<?= htmlspecialchars($rowTxt) ?>">
                <td class="px-4 py-3"><?= $i++ ?></td>
                <td class="px-4 py-3 font-medium text-slate-900"><?= htmlspecialchars($row['cooperative_name']) ?></td>
                <td class="px-4 py-3 text-slate-700"><?= htmlspecialchars($row['registration_number']) ?></td>
                <td class="px-4 py-3"><a href="<?= htmlspecialchars($row['certificate']) ?>" target="_blank" class="px-3 py-1.5 rounded-md border text-slate-700 text-sm hover:bg-slate-50"><i class="bi bi-file-earmark-text"></i> View</a></td>
                <td class="px-4 py-3 text-slate-700"><?= htmlspecialchars($row['contact_email']) ?></td>
                <td class="px-4 py-3">
                  <?php if ($row['status'] === 'Approved'): ?>
                    <span class="px-2.5 py-1 rounded-full bg-green-100 text-green-800 text-xs font-semibold">Approved</span>
                  <?php elseif ($row['status'] === 'Rejected'): ?>
                    <span class="px-2.5 py-1 rounded-full bg-red-100 text-red-800 text-xs font-semibold">Rejected</span>
                  <?php else: ?>
                    <span class="px-2.5 py-1 rounded-full bg-yellow-100 text-yellow-800 text-xs font-semibold">Pending</span>
                  <?php endif; ?>
                </td>
                <td class="px-4 py-3">
                  <form method="post" action="admin.php" class="flex items-center gap-2">
                    <input type="hidden" name="application_id" value="<?= (int)$row['id'] ?>">
                    <?php if ($row['status'] === 'Pending'): ?>
                      <button name="action" value="Approve" class="px-3 py-1.5 rounded-md bg-green-600 hover:bg-green-700 text-white text-sm"><i class="bi bi-check-lg"></i> Approve</button>
                      <button name="action" value="Reject" class="px-3 py-1.5 rounded-md bg-red-600 hover:bg-red-700 text-white text-sm"><i class="bi bi-x-lg"></i> Reject</button>
                    <?php endif; ?>
                    <button name="action" value="Delete" class="px-3 py-1.5 rounded-md border border-red-300 text-red-700 text-sm hover:bg-red-50"><i class="bi bi-trash"></i> Delete</button>
                  </form>
                </td>
              </tr>
              <?php endwhile; else: ?>
              <tr><td colspan="7" class="px-4 py-6 text-center text-slate-500">No applications found.</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    <?php elseif ($section === 'users'): ?>
      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 mb-6 reveal">
        <?php foreach ($roleCounts as $name => $count): ?>
          <div class="bg-white rounded-2xl border p-6 shadow">
            <div class="text-slate-500 text-lg capitalize"><?= htmlspecialchars($name) ?></div>
            <div class="mt-1 text-4xl font-extrabold text-slate-800"><span class="countup" data-target="<?= $count ?>">0</span></div>
          </div>
        <?php endforeach; ?>
      </div>
      <div class="bg-white rounded-2xl border p-5 shadow reveal">
        <div class="flex items-center justify-between mb-4">
          <h2 class="text-xl font-semibold text-slate-800 flex items-center gap-2"><i class="bi bi-people text-indigo-600"></i> Users</h2>
          <input id="userSearch" class="px-3 py-2 rounded-md border text-sm" placeholder="Search name or email" />
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full text-left text-sm">
            <thead class="bg-slate-50 text-slate-600 font-semibold">
                        <tr>
                            <th class="px-4 py-3">ID</th>
                <th class="px-4 py-3">Name</th>
                <th class="px-4 py-3">Email</th>
                            <th class="px-4 py-3">Role</th>
                <th class="px-4 py-3">Created</th>
                        </tr>
                    </thead>
            <tbody id="usersTbody" class="divide-y divide-slate-100 bg-white">
              <?php foreach ($usersData as $u): ?>
                <tr>
                  <td class="px-4 py-3"><?= (int)$u['id'] ?></td>
                  <td class="px-4 py-3 font-medium text-slate-900"><?= htmlspecialchars(trim(($u['firstName'] ?? '').' '.($u['lastName'] ?? ''))) ?></td>
                  <td class="px-4 py-3 text-slate-700"><?= htmlspecialchars($u['email'] ?? '') ?></td>
                  <td class="px-4 py-3 text-slate-700 capitalize"><?= htmlspecialchars(strtolower($u['userType'] ?? '')) ?></td>
                  <td class="px-4 py-3 text-slate-500"><?= htmlspecialchars($u['created_at'] ?? '') ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
                </table>
            </div>
      </div>
      <script>
        const userSearch = document.getElementById('userSearch');
        const usersTbody = document.getElementById('usersTbody');
        if (userSearch && usersTbody) {
          userSearch.addEventListener('input', function(){
            const q = this.value.toLowerCase();
            usersTbody.querySelectorAll('tr').forEach(tr => {
              tr.style.display = tr.textContent.toLowerCase().includes(q) ? '' : 'none';
            });
          });
        }
      </script>
    <?php endif; ?>
    <script>
      // Reveal on load
      document.addEventListener('DOMContentLoaded', () => {
        document.querySelectorAll('.reveal').forEach(el => {
          requestAnimationFrame(() => el.classList.add('in'));
        });
        // Count up numbers
        document.querySelectorAll('.countup').forEach(el => {
          const target = parseInt(el.getAttribute('data-target')||'0',10) || 0;
          let value = 0; const step = Math.ceil(target/30);
          const tick = () => { value += step; if (value >= target) { value = target; } el.textContent = value; if (value < target) requestAnimationFrame(tick); };
          requestAnimationFrame(tick);
        });
        // Animate bars
        document.querySelectorAll('.bar').forEach(el => {
          const h = parseInt(el.getAttribute('data-h')||'0',10) || 0;
          setTimeout(()=>{ el.style.height = h + 'px'; }, 100);
        });
        // Chart tooltips
        let tt;
        const showTT = (e, text) => {
          if (!tt) { tt = document.createElement('div'); tt.className='tooltip-floating'; document.body.appendChild(tt); }
          tt.textContent = text; tt.style.left = (e.clientX)+'px'; tt.style.top = (e.clientY-10)+'px'; tt.style.display='block';
        };
        const hideTT = () => { if (tt) tt.style.display='none'; };
        document.querySelectorAll('.bar').forEach((bar, idx) => {
          bar.addEventListener('mouseenter', (ev)=> showTT(ev, bar.parentElement.previousElementSibling?.textContent?.includes('Users') ? 'Users: '+bar.getAttribute('data-h')/10 : 'Apps: '+bar.getAttribute('data-h')/10));
          bar.addEventListener('mousemove', (ev)=> showTT(ev, tt?.textContent||''));
          bar.addEventListener('mouseleave', hideTT);
        });
        // Theme toggle
        const body = document.body; const key='adminTheme';
        const applyTheme = (t)=>{ if(t==='dark'){ body.classList.add('dark'); } else { body.classList.remove('dark'); } };
        applyTheme(localStorage.getItem(key)||'');
        const btn = document.getElementById('themeToggle'); if (btn){ btn.addEventListener('click', ()=>{ const cur = body.classList.contains('dark')?'dark':'light'; const next = cur==='dark'?'light':'dark'; localStorage.setItem(key,next); applyTheme(next); }); }
        // Applications filters
        const appsSearch = document.getElementById('appsSearch');
        const appsStatus = document.getElementById('appsStatus');
        const appsTbody = document.getElementById('appsTbody');
        const filterApps = ()=>{
          const q = (appsSearch?.value||'').toLowerCase(); const s = appsStatus?.value||'';
          if (!appsTbody) return; appsTbody.querySelectorAll('tr').forEach(tr=>{
            const txt = (tr.getAttribute('data-text')||'').toLowerCase(); const st = tr.getAttribute('data-status')||'';
            const okTxt = !q || txt.includes(q); const okSt = !s || st===s; tr.style.display = (okTxt && okSt)?'':'none';
          });
        };
        if (appsSearch) appsSearch.addEventListener('input', filterApps);
        if (appsStatus) appsStatus.addEventListener('change', filterApps);
        filterApps();
      });
    </script>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>

<!-- Mobile Responsive JavaScript -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Mobile menu toggle
    const mobileMenuToggle = document.getElementById('mobileMenuToggle');
    const mobileMenu = document.getElementById('mobileMenu');
    const toggleIcon = mobileMenuToggle.querySelector('i');
    
    if (mobileMenuToggle && mobileMenu) {
        mobileMenuToggle.addEventListener('click', function() {
            mobileMenu.classList.toggle('active');
            mobileMenu.classList.toggle('hidden');
            
            // Toggle icon
            if (mobileMenu.classList.contains('active')) {
                toggleIcon.className = 'bi bi-x text-2xl';
            } else {
                toggleIcon.className = 'bi bi-list text-2xl';
            }
        });
        
        // Close menu when clicking a link
        const mobileMenuLinks = mobileMenu.querySelectorAll('a');
        mobileMenuLinks.forEach(link => {
            link.addEventListener('click', function() {
                mobileMenu.classList.remove('active');
                mobileMenu.classList.add('hidden');
                toggleIcon.className = 'bi bi-list text-2xl';
            });
        });
        
        // Close menu when clicking outside
        document.addEventListener('click', function(event) {
            if (!mobileMenuToggle.contains(event.target) && !mobileMenu.contains(event.target)) {
                mobileMenu.classList.remove('active');
                mobileMenu.classList.add('hidden');
                toggleIcon.className = 'bi bi-list text-2xl';
            }
        });
    }
    
    // Sync mobile and desktop theme toggles
    const themeToggle = document.getElementById('themeToggle');
    const mobileThemeToggle = document.getElementById('mobileThemeToggle');
    
    const toggleTheme = () => {
        document.documentElement.classList.toggle('dark');
        const isDark = document.documentElement.classList.contains('dark');
        localStorage.setItem('theme', isDark ? 'dark' : 'light');
    };
    
    if (themeToggle) {
        themeToggle.addEventListener('click', toggleTheme);
    }
    
    if (mobileThemeToggle) {
        mobileThemeToggle.addEventListener('click', toggleTheme);
    }
    
    // Load saved theme
    if (localStorage.getItem('theme') === 'dark') {
        document.documentElement.classList.add('dark');
    }
});
</script>

</body>
</html>

