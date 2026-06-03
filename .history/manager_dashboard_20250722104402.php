<?php
session_start();
require_once 'db_config.php';

// Handle Orientation Create
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_orientation'])) {
    $title = trim($_POST['title']);
    $venue = trim($_POST['venue']) ?: null;
    $link = trim($_POST['link']) ?: null;
    $orientation_date = $_POST['orientation_date'];
    $orientation_time = $_POST['orientation_time'];

    if (empty($title) || empty($orientation_date) || empty($orientation_time)) {
        $_SESSION['message'] = "Please fill in all required fields.";
        header("Location: manager_dashboard.php");
        exit();
    } else {
        $stmt = $conn->prepare("INSERT INTO orientation_schedule (title, mode, venue, link, orientation_date, orientation_time) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("ssssss", $title, $mode, $venue, $link, $orientation_date, $orientation_time);
        
        $stmt->execute();

        $orientation_id = $conn->insert_id;
        $stmt->close();

        // Store orientation_id in session to show SweetAlert and redirect
        $_SESSION['new_orientation_id'] = $orientation_id;
        $_SESSION['orientation_created'] = true;
        header("Location: manager_dashboard.php");
        exit();
    }
}

// Handle Delete Orientation
if (isset($_GET['delete_orientation'])) {
    $id = intval($_GET['delete_orientation']);
    $conn->query("DELETE FROM orientation_schedule WHERE id = $id");
    $_SESSION['message'] = "Orientation deleted.";
    header("Location: manager_dashboard.php");
    exit();
}

// Check if the user is logged in and is a manager
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'manager') {
    header("Location: landing.php");
    exit();
}

// Fetch session data
$userFirstName = $_SESSION['user_firstName'];
$userLastName = $_SESSION['user_lastName'];

// Fetch the count of pending submissions
$pendingCountQuery = "SELECT COUNT(*) AS pending_count FROM submitted_requirements WHERE status = 'Pending'";
$pendingCountResult = $conn->query($pendingCountQuery);
$pendingCount = $pendingCountResult->fetch_assoc()['pending_count'] ?? 0;
?>

<?php
// Check for pending orientation requests
$notifQuery = "
    SELECT o.id, o.requested_at, u.firstName, u.lastName
    FROM orientation_requests o
    JOIN users u ON o.user_id = u.id
    WHERE o.status = 'Pending'
    ORDER BY o.requested_at DESC
";
$notifResult = $conn->query($notifQuery);
$pendingRequests = $notifResult ? $notifResult->num_rows : 0;


// Check if there's an upcoming orientation already posted
$orientationQuery = "SELECT COUNT(*) AS total FROM orientation_schedule WHERE orientation_date >= CURDATE()";
$orientationResult = $conn->query($orientationQuery);
$hasUpcomingOrientation = ($orientationResult && $orientationResult->num_rows > 0) ? $orientationResult->fetch_assoc()['total'] > 0 : false;

// Show badge only if there are pending requests AND no orientation scheduled
$showOrientationBadge = ($pendingRequests > 0 && !$hasUpcomingOrientation);
// Clear orientation notifications (AJAX handler)
if (isset($_GET['clear_notifications'])) {
    $conn->query("UPDATE orientation_requests SET status = 'Cleared' WHERE status = 'Pending'");
    echo json_encode(['success' => true]);
    exit();
}


?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cooperative Manager Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
    <link href="assets/css/styles.css" rel="stylesheet">

</head>
<body>
<!-- Navbar -->
<nav class="navbar navbar-expand-lg navbar-dark">
    <div class="container-fluid">
        <a class="navbar-brand" href="#">Cooperative Manager Dashboard</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav ms-auto">
                <!-- Home -->
                <li class="nav-item">
                    <a class="nav-link" href="manager_dashboard.php">
                        <i class="bi bi-house-door"></i> Home
                    </a>
                </li>
                <!-- Profile - Triggers Modal -->
                <li class="nav-item">
                    <a class="nav-link" href="#" data-bs-toggle="modal" data-bs-target="#profileModal">
                        <i class="bi bi-person-circle"></i> Profile
                    </a>
                </li>
                <!-- Logout -->
                <li class="nav-item">
                    <a class="nav-link" href="logout.php">
                        <i class="bi bi-box-arrow-right"></i> Logout
                    </a>
                </li>
            </ul>
        </div>
    </div>
</nav>
<!-- Profile Modal -->
<div class="modal fade" id="profileModal" tabindex="-1" aria-labelledby="profileModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="profileModalLabel">Update Profile</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <!-- Profile Picture Preview -->
                <div class="text-center mb-3">
    <img src="<?= htmlspecialchars($profilePath); ?>" 
         alt="Profile Picture" 
         class="rounded-circle" 
         width="120">
</div>
                <!-- Profile Update Form -->
                <form action="update_profile.php" method="POST" enctype="multipart/form-data">
                    <div class="mb-3">
                        <label for="profile_image" class="form-label fw-bold">Upload Profile Picture</label>
                        <input type="file" class="form-control" name="profile_image" accept="image/*">
                    </div>
                    <div class="mb-3">
                        <label for="firstName" class="form-label fw-bold">First Name</label>
                        <input type="text" class="form-control" name="firstName" value="<?= htmlspecialchars($userFirstName); ?>" required>
                    </div>
                    <div class="mb-3">
                        <label for="middleName" class="form-label fw-bold">Middle Name</label>
                        <input type="text" class="form-control" name="middleName" value="<?= htmlspecialchars($_SESSION['user_middleName'] ?? ''); ?>">
                    </div>
                    <div class="mb-3">
                        <label for="lastName" class="form-label fw-bold">Last Name</label>
                        <input type="text" class="form-control" name="lastName" value="<?= htmlspecialchars($userLastName); ?>" required>
                    </div>
                    <div class="mb-3">
                        <label for="email" class="form-label fw-bold">Email</label>
                        <input type="email" class="form-control" name="email" value="<?= htmlspecialchars($_SESSION['user_email'] ?? ''); ?>" required>
                    </div>
                    <div class="text-center">
                        <button type="submit" class="btn btn-success w-50">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>


<!-- Main Content -->
<!-- Hero Section -->
<section id="hero" class="hero section position-relative overflow-hidden">
  <img src="assets/img/bg.jpg" alt="Jeep Background" class="hero-bg">
  <div class="container position-absolute top-50 start-50 translate-middle text-center text-white">
    <div class="row justify-content-center">
      <div class="col-xl-8 col-lg-10">
        <h1 class="display-5 fw-bold">Welcome to the Manager Dashboard</h1>
        <p class="lead">Manage orientations, verify members, and keep things running smoothly.</p>
      </div>
    </div>
  </div>
</section>

<!-- Manager Action Cards -->
<div class="row row-cols-1 row-cols-md-2 row-cols-xl-3 row-cols-xxl-5 g-4 mt-4">
    <!-- Post Membership Requirements -->
    <div class="col">
        <div class="card text-center h-100">
            <div class="card-body">
                <i class="bi bi-file-earmark-plus card-icon"></i>
                <h5 class="card-title">Post Requirements</h5>
                <p class="card-text">Create and publish new membership requirements.</p>
                <a href="post_requirements.php" class="btn btn-primary">Post Now</a>
            </div>
        </div>
    </div>
    <!-- Verify Applications -->
    <div class="col">
        <div class="card text-center h-100 position-relative">
            <div class="card-body">
                <?php if ($pendingCount > 0): ?>
                    <div class="notification-badge"><?= $pendingCount; ?></div>
                <?php endif; ?>
                <i class="bi bi-person-check card-icon"></i>
                <h5 class="card-title">Verify Applications</h5>
                <p class="card-text">Review and manage pending submissions.</p>
                <a href="verify_applications.php" class="btn btn-secondary">Verify</a>
            </div>
        </div>
    </div>
    <!-- Schedule Orientation -->
    <div class="col">
        <div class="card text-center h-100 position-relative">
            <?php if ($showOrientationBadge): ?>
                <span class="position-absolute top-0 end-0 translate-middle badge rounded-pill bg-danger"
                      style="font-size: 0.75rem; z-index: 10;">
                    <?= $pendingRequests; ?>
                    <span class="visually-hidden">unread requests</span>
                </span>
            <?php endif; ?>
            <div class="card-body">
                <i class="bi bi-calendar-event card-icon"></i>
                <h5 class="card-title">Schedule Orientation</h5>
                <p class="card-text">Plan an online or in-person session for drivers or operators.</p>
                <a href="schedule_orientation.php" class="btn btn-success">Schedule</a>
            </div>
        </div>
    </div>
    <!-- View Orientation Lists -->
    <div class="col">
        <div class="card text-center h-100">
            <div class="card-body">
                <i class="bi bi-list-check card-icon"></i>
                <h5 class="card-title">Orientation Lists</h5>
                <p class="card-text">View all scheduled orientations and attendees.</p>
                <a href="attendees_list.php" class="btn btn-info">View Attendees</a>
            </div>
        </div>
    </div>
    <!-- Collect Cooperative Funds -->
    <div class="col">
        <div class="card text-center h-100">
            <div class="card-body">
                <i class="bi bi-piggy-bank card-icon"></i>
                <h5 class="card-title">Collect Cooperative Funds</h5>
                <p class="card-text">View and confirm cooperative fund contributions from members.</p>
                <a href="?page=cooperative_funds" class="btn btn-warning">Collect Funds</a>
            </div>
        </div>
    </div>
</div>


<?php if (isset($_SESSION['orientation_created']) && $_SESSION['orientation_created'] === true): ?>
<script>
Swal.fire({
    icon: 'success',
    title: 'Orientation Created!',
    text: 'Redirecting to attendee list...',
    confirmButtonColor: '#28a745',
    timer: 2000,
    showConfirmButton: false
}).then(() => {
    window.location.href = 'attendees_list.php?orientation_id=<?= $_SESSION['new_orientation_id']; ?>';
});
</script>
<?php
unset($_SESSION['orientation_created'], $_SESSION['new_orientation_id']);
endif;
?>

<?php if (isset($_GET['page']) && $_GET['page'] === 'cooperative_funds'): ?>
    <!-- Collect Cooperative Funds Section -->
    <div class="container mt-4">
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-info text-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Collect Cooperative Funds</h5>
                <button class="btn btn-light btn-sm" id="fetchCoopFundsBtn"><i class="bi bi-arrow-repeat"></i> Refresh</button>
            </div>
            <div class="card-body">
                <div id="coopFundsLoading" class="text-center text-muted mb-2" style="display:none;">
                    <div class="spinner-border text-info" role="status"></div>
                    <div>Loading cooperative funds...</div>
                </div>
                <div id="coopFundsTableContainer"></div>
            </div>
        </div>
    </div>
    <script>
    function fetchCoopFunds() {
        document.getElementById('coopFundsLoading').style.display = 'block';
        fetch('cooperative_fund.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'list' })
        })
        .then(res => res.json())
        .then(data => {
            document.getElementById('coopFundsLoading').style.display = 'none';
            if (data.success && data.funds.length > 0) {
                let html = `<div class='table-responsive'><table class='table table-bordered table-striped'><thead><tr><th>Member</th><th>Amount</th><th>Method</th><th>Date</th><th>Status</th><th>Action</th></tr></thead><tbody>`;
                data.funds.forEach(f => {
                    const isCollected = f.status === 'Confirmed';
                    html += `<tr>
                        <td>${f.member}</td>
                        <td>₱${f.amount}</td>
                        <td>${f.payment_method}</td>
                        <td>${f.paid_at}</td>
                        <td><span class='badge ${isCollected ? 'bg-success' : 'bg-warning text-dark'}'>${isCollected ? 'Confirmed' : 'Pending'}</span></td>
                        <td>`;
                    if (!isCollected) {
                        html += `<button class='btn btn-sm btn-success' onclick=\"confirmCoopFund(${f.id}, this)\">Confirm Collected</button>`;
                    } else {
                        html += '-';
                    }
                    html += `</td></tr>`;
                });
                html += '</tbody></table></div>';
                document.getElementById('coopFundsTableContainer').innerHTML = html;
            } else {
                document.getElementById('coopFundsTableContainer').innerHTML = '<div class="alert alert-info">No cooperative fund payments found.</div>';
            }
        })
        .catch(() => {
            document.getElementById('coopFundsLoading').style.display = 'none';
            document.getElementById('coopFundsTableContainer').innerHTML = '<div class="alert alert-danger">Failed to load cooperative funds.</div>';
        });
    }
    function confirmCoopFund(id, btn) {
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';
        fetch('cooperative_fund.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'confirm', id })
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                btn.closest('tr').querySelector('td:nth-child(5) span').className = 'badge bg-success';
                btn.closest('tr').querySelector('td:nth-child(5) span').textContent = 'Confirmed';
                btn.replaceWith('-');
            } else {
                btn.disabled = false;
                btn.innerHTML = 'Confirm Collected';
                alert('Failed to confirm: ' + (data.message || 'Unknown error'));
            }
        })
        .catch(() => {
            btn.disabled = false;
            btn.innerHTML = 'Confirm Collected';
            alert('Failed to confirm.');
        });
    }
    document.getElementById('fetchCoopFundsBtn').addEventListener('click', fetchCoopFunds);
    fetchCoopFunds();
    </script>
<?php endif; ?>

<!-- Bootstrap Bundle -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>

<!-- SweetAlert2 -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<!-- Image Preview (optional for manager profile) -->
<script>
function previewImage(event) {
    const reader = new FileReader();
    reader.onload = function () {
        const output = document.getElementById('profilePreview');
        if (output) {
            output.src = reader.result;
        }
    };
    reader.readAsDataURL(event.target.files[0]);
}
</script>
<script>
document.addEventListener("DOMContentLoaded", function() {
    const dropdown = document.getElementById("notifDropdown");
    dropdown?.addEventListener("click", () => {
        const badge = document.getElementById("notifBadge");
        if (badge) badge.style.display = "none";
    });
});
</script>
<script>
function clearNotifications(event) {
    event.preventDefault();
    fetch('manager_dashboard.php?clear_notifications=1')
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                // Remove badge
                const badge = document.getElementById("notifBadge");
                if (badge) badge.remove();
                document.querySelector("a.text-danger")?.classList.add("disabled");

                // Replace dropdown content
                const dropdown = document.querySelector("#notifDropdown + .dropdown-menu");
                dropdown.innerHTML = `
                    <li class="dropdown-header fw-bold text-primary">Orientation Requests</li>
                    <li class="dropdown-item text-muted small">All notifications cleared.</li>
                `;
            }
        });
}
</script>

</body>
</html>

