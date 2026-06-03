<?php
session_start();
require_once 'db_config.php';

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
    header("Location: landing.php");
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
        header("Location: landing.php");
        exit();
    }
}



// Fetch session info
$userId = $_SESSION['user_id'];
$userFirstName = $_SESSION['user_firstName'] ?? "";
$userLastName = $_SESSION['user_lastName'] ?? "";
$userEmail = $_SESSION['user_email'] ?? "";
$profileImage = 'uploads/profile_' . $userId . '.jpg';

// If the profile image doesn't exist, use a placeholder
if (!file_exists($profileImage)) {
    $profileImage = 'uploads/default_profile.png';
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
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/css/styles.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body>

<!-- Sidebar -->
<aside class="sidebar">
    <div class="logo">
        <img src="\tebz\img\logo12.png" alt="JeepniGo Logo" style="max-width: 100%; height: auto;">
        JeepniGo
    </div>
    <ul>
        <li><a href="?page=dashboard" class="<?= $page === 'dashboard' ? 'active' : '' ?>">Dashboard</a></li>
        <li><a href="?page=apply_driver_operator" class="<?= $page === 'apply_driver_operator' ? 'active' : '' ?>">ANNOUNCEMENT</a></li>
        <li><a href="?page=profile" class="<?= $page === 'profile' ? 'active' : '' ?>">My Profile</a></li>
        <li><a href="?page=jeepney_status" class="<?= $page === 'jeepney_status' ? 'active' : '' ?>">Jeepney Status</a></li>
        <li><a href="?page=apply_cooperative" class="<?= $page === 'apply_cooperative' ? 'active' : '' ?>">Apply Cooperative</a></li>

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
                echo '<li><a href="driver_dashboard.php">Go to Driver Dashboard</a></li>';
            } elseif ($appliedRole === 'Operator') {
                echo '<li><a href="operator_dashboard.php">Go to Operator Dashboard</a></li>';
            }
        }
        ?>
        <li><a href="logout.php">Logout</a></li>
    </ul>
</aside>

    <!-- Main Content -->
    <div class="content">
    <?php if ($page === 'dashboard'): ?>
        <!-- Dashboard -->
        <header>
            <h1>Welcome, <?= htmlspecialchars($userFirstName . ' ' . $userLastName); ?>!</h1>
            <p>Your next ride is just a few clicks away. Explore and plan your journey with JeepniGo!</p>
        </header>

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


    <?php elseif ($page === 'jeepney_status'): ?>
<!-- Jeepney Status Modernized -->
<div class="row g-4">
    <div class="col-12 col-lg-6 mb-4">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-primary text-white d-flex align-items-center">
                <i class="bi bi-truck-front me-2 fs-4"></i>
                <h5 class="mb-0">Live Jeepney Location</h5>
            </div>
            <div class="card-body p-0">
                <div id="jeepneyMap" style="height: 300px; border-radius: 10px; overflow: hidden;"></div>
            </div>
        </div>
    </div>
    <div class="col-12 col-lg-6 mb-4">
        <div id="jeepneyArrivingAlert" class="alert alert-success d-none"></div>
        <div id="jeepneyStatusLoading" class="text-center my-3">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Loading...</span>
            </div>
            <div>Loading jeepney status...</div>
        </div>
        <div id="jeepneyStatusCards"></div>
    </div>
</div>
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
let lastStatuses = {};
let jeepneyMap, markers = {};
const passengerId = <?= json_encode($userId) ?>;
function fetchJeepneyStatus() {
    fetch('get_jeepney_status.php')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const cardsContainer = document.getElementById('jeepneyStatusCards');
                cardsContainer.innerHTML = '';
                let arrivingMsg = '';
                let anyMarkers = false;
                data.routes.forEach(route => {
                    const routeKey = route.route.replace(/\s+/g, '_');
                    let actionBtn = '';
                    // Boarding state logic
                    const isHere = sessionStorage.getItem('here_' + routeKey);
                    const isBoarded = sessionStorage.getItem('boarded_' + routeKey);
                    const isPaid = sessionStorage.getItem('paid_' + routeKey);
                    let statusBadge = '';
                    let statusIcon = '';
                    let statusColor = '';
                    if (route.status === 'Arrived') {
                        statusBadge = 'bg-success';
                        statusIcon = 'bi bi-check-circle-fill';
                        statusColor = '#28a745';
                    } else if (route.status === 'Arriving') {
                        statusBadge = 'bg-warning text-dark';
                        statusIcon = 'bi bi-clock-history';
                        statusColor = '#ffc107';
                    } else if (route.status === 'In Progress') {
                        statusBadge = 'bg-info text-dark';
                        statusIcon = 'bi bi-geo-alt-fill';
                        statusColor = '#17a2b8';
                    } else {
                        statusBadge = 'bg-secondary';
                        statusIcon = 'bi bi-circle';
                        statusColor = '#6c757d';
                    }
                    if ((route.status === 'Arriving' || route.status === 'Arrived') && !isHere) {
                        actionBtn = `<button class='btn btn-success w-100 mb-2' onclick='confirmPickup("${route.route}")'><i class="bi bi-person-check"></i> I'm here</button>`;
                    } else if (isHere && !isBoarded) {
                        actionBtn = `<button class='btn btn-primary w-100 mb-2' onclick='boardJeepney("${route.route}")'><i class="bi bi-door-open"></i> Board</button>`;
                    } else if (isBoarded && !isPaid) {
                        actionBtn = `<button class='btn btn-warning w-100 mb-2' onclick='payFare("${route.route}")'><i class="bi bi-cash-coin"></i> Pay Fare</button>`;
                    } else if (isPaid) {
                        actionBtn = `<span class='badge bg-success w-100 py-2'><i class="bi bi-cash-coin"></i> Paid</span>`;
                    }
                    // Card layout
                    cardsContainer.innerHTML += `
                        <div class='card mb-3 shadow-sm border-0'>
                            <div class='card-body'>
                                <div class='d-flex align-items-center mb-2'>
                                    <div class='me-3'>
                                        <i class='${statusIcon}' style='font-size: 2rem; color: ${statusColor}'></i>
                                    </div>
                                    <div>
                                        <h5 class='card-title mb-0'>${route.route}</h5>
                                        <span class='badge ${statusBadge} px-3 py-2'>${route.status}</span>
                                    </div>
                                </div>
                                <div class='mb-2'><i class='bi bi-clock'></i> <b>ETA:</b> ${route.eta}</div>
                                <div class='mb-2'><i class='bi bi-geo-alt'></i> <b>Location:</b> ${route.location || 'Unknown'}</div>
                                ${actionBtn}
                            </div>
                        </div>
                    `;
                    // Map logic
                    if (route.lat && route.lng) {
                        anyMarkers = true;
                        if (!jeepneyMap) {
                            jeepneyMap = L.map('jeepneyMap').setView([route.lat, route.lng], 15);
                            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                                attribution: '© OpenStreetMap contributors'
                            }).addTo(jeepneyMap);
                        }
                        if (markers[route.route]) {
                            markers[route.route].setLatLng([route.lat, route.lng]);
                        } else {
                            markers[route.route] = L.marker([route.lat, route.lng]).addTo(jeepneyMap).bindPopup(route.route);
                        }
                    }
                    // Notification logic
                    if ((route.status === 'Arriving' || route.status === 'Arrived') && lastStatuses[route.route] !== route.status) {
                        arrivingMsg += `Jeepney for <b>${route.route}</b> is <b>${route.status.toLowerCase()}</b> at your pickup location!<br>`;
                        Swal.fire({
                            icon: route.status === 'Arrived' ? 'success' : 'info',
                            title: `Jeepney for ${route.route} ${route.status === 'Arrived' ? 'has arrived!' : 'is arriving soon!'}`,
                            text: `Location: ${route.location || 'Unknown'}`,
                            timer: 4000,
                            showConfirmButton: false
                        });
                        if (window.Notification && Notification.permission === 'granted') {
                            new Notification(`Jeepney for ${route.route} ${route.status === 'Arrived' ? 'has arrived!' : 'is arriving soon!'}`, {
                                body: `Location: ${route.location || 'Unknown'}`
                            });
                        }
                    }
                    lastStatuses[route.route] = route.status;
                });
                const alertDiv = document.getElementById('jeepneyArrivingAlert');
                if (arrivingMsg) {
                    alertDiv.innerHTML = arrivingMsg;
                    alertDiv.classList.remove('d-none');
                } else {
                    alertDiv.classList.add('d-none');
                }
                document.getElementById('jeepneyStatusLoading').style.display = 'none';
                document.getElementById('jeepneyMap').style.display = anyMarkers ? '' : 'none';
            } else {
                document.getElementById('jeepneyStatusLoading').innerHTML = '<div class="alert alert-danger">Failed to load jeepney status.</div>';
            }
        })
        .catch(() => {
            document.getElementById('jeepneyStatusLoading').innerHTML = '<div class="alert alert-danger">Failed to load jeepney status.</div>';
        });
}
function confirmPickup(route) {
    const routeKey = route.replace(/\s+/g, '_');
    fetch('confirm_pickup.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({route: route})
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            sessionStorage.setItem('here_' + routeKey, '1');
            Swal.fire('Confirmed!', 'Your pickup has been confirmed.', 'success');
            fetchJeepneyStatus();
        } else {
            Swal.fire('Error', data.message || 'Failed to confirm pickup.', 'error');
        }
    });
}
function boardJeepney(route) {
    const routeKey = route.replace(/\s+/g, '_');
    fetch('board_jeepney.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({passenger_id: passengerId, route: route})
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            sessionStorage.setItem('boarded_' + routeKey, '1');
            Swal.fire('Boarded!', 'You have boarded the jeepney.', 'success');
            fetchJeepneyStatus();
        } else {
            Swal.fire('Error', data.message || 'Failed to board.', 'error');
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
            
            fetch('pay_fare.php', {
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
document.addEventListener('DOMContentLoaded', function() {
    if (window.Notification && Notification.permission !== 'granted') {
        Notification.requestPermission();
    }
    fetchJeepneyStatus();
    setInterval(fetchJeepneyStatus, 15000);
});
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
<?php endif; ?>


    <?php if ($page === 'board_fare'): ?>
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

<?php else: ?>
    <div class="alert alert-info text-center mt-3">
        <p class="mb-0">⚠️ No Announcement posted yet.</p>
    </div>
<?php endif; ?>

<?php if (!empty($announcements)): ?> 
<!-- Apply Now Button -->
<div class="text-center mt-5">
    <button class="btn apply-now-btn btn-lg shadow-lg px-5 py-3" data-bs-toggle="modal" data-bs-target="#applyModal">
        Apply Now
    </button>
</div>
<?php endif; ?>


<!-- Application Form Modal -->
<div class="modal fade" id="applyModal" tabindex="-1" aria-labelledby="applyModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
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
                        <button type="submit" class="btn btn-success btn-lg w-100">📤 Submit Application</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>


<?php if ($page === 'apply_cooperative'): ?>
    <!-- Apply Cooperative -->
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-lg-8 col-md-10">
                <div class="card shadow-sm">
                    <div class="card-header bg-primary text-white text-center">
                        <h2>Apply Cooperative Membership</h2>
                        <p class="mb-0">Submit your cooperative's details and required documents for approval</p>
                    </div>
                    <div class="card-body">
                        <?php if (isset($_SESSION['message'])): ?>
                            <div class="alert alert-success text-center">
                                <?= htmlspecialchars($_SESSION['message']); ?>
                                <?php unset($_SESSION['message']); ?>
                            </div>
                        <?php endif; ?>
                        <form action="apply_cooperative.php" method="POST" enctype="multipart/form-data">
                            <div class="mb-3">
                                <label for="cooperative_name" class="form-label">Cooperative Name</label>
                                <input type="text" name="cooperative_name" id="cooperative_name" class="form-control" placeholder="Enter Cooperative Name" required>
                            </div>
                            <div class="mb-3">
                                <label for="registration_number" class="form-label">Certificate Registration Number</label>
                                <input type="text" name="registration_number" id="registration_number" class="form-control" placeholder="Enter Registration Number" required>
                            </div>
                            <div class="mb-3">
                                <label for="certificate" class="form-label">Upload Certificate</label>
                                <input type="file" name="certificate" id="certificate" class="form-control" accept="image/*, .pdf" required>
                            </div>
                            <div class="mb-3">
                                <label for="contact_info" class="form-label">Contact Information</label>
                                <input type="text" name="contact_info" id="contact_info" class="form-control" placeholder="Enter Contact Information" required>
                            </div>
                            <button type="submit" class="btn btn-success w-100">Submit Cooperative Application</button>
                        </form>
                    </div>
                    <div class="card-footer text-center">
                        <p class="mb-0 text-muted">Your submission will be reviewed shortly. You will be notified once a decision has been made.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>

</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // Get announcements from PHP
    const announcements = <?= $announcementsJSON; ?>;
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

    // Navigate to older announcements (Previous button)
    document.getElementById("prevAnnouncement").addEventListener("click", () => {
        if (currentIndex < announcements.length - 1) {
            currentIndex++;
            updateAnnouncement();
        }
    });

    // Navigate to newer announcements (Next button)
    document.getElementById("nextAnnouncement").addEventListener("click", () => {
        if (currentIndex > 0) {
            currentIndex--;
            updateAnnouncement();
        }
    });

    // Initialize first announcement
    updateAnnouncement();
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
<!-- JavaScript for Image Preview & AJAX Form Submission -->
<script>
    document.querySelectorAll('.file-input').forEach(input => {
        input.addEventListener('change', function(event) {
            const file = event.target.files[0];
            const previewId = `preview_${event.target.id}`;
            const previewElement = document.getElementById(previewId);

            if (file) {
                if (file.type.startsWith('image/')) {
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
                    previewElement.classList.add('d-none'); // Hide preview for non-images
                }
            }
        });
    });

    document.getElementById("applyForm").addEventListener("submit", function(event) {
        event.preventDefault(); // Prevent page reload

        let formData = new FormData(this);

        fetch("submit_application.php", {
            method: "POST",
            body: formData
        })
        .then(response => response.json()) // Expect JSON response from PHP
        .then(data => {
            if (data.success) {
                var successModal = new bootstrap.Modal(document.getElementById("successModal"));
                successModal.show();

                // Redirect to Dashboard when clicking "Okay"
                document.getElementById("redirectDashboard").addEventListener("click", function() {
                    window.location.href = "passenger_dashboard.php";
                });
            } else {
                alert("Submission failed. Please try again.");
            }
        })
        .catch(error => console.error("Error:", error));
    });
</script>
    <!-- JavaScript to Show Modal and Redirect -->
    <script>
        document.addEventListener("DOMContentLoaded", function () {
            var successModal = new bootstrap.Modal(document.getElementById('successModal'));
            successModal.show(); // Show modal if submission was successful

            document.getElementById('redirectDashboard').addEventListener('click', function () {
                window.location.href = 'passenger_dashboard.php?page=dashboard';
            });
        });
    </script>
<!-- Include Bootstrap and SweetAlert2 -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<script>
document.getElementById("applyForm").addEventListener("submit", function(event) {
    event.preventDefault(); // Prevent actual form submission

    Swal.fire({
        title: "🎉 Success!",
        text: "Your application has been submitted. 🚀 Redirecting you now...",
        icon: "success",
        confirmButtonText: "OK",
        allowOutsideClick: false
    }).then(() => {
        // Close the Bootstrap modal
        let modal = document.getElementById("applyModal");
        let modalInstance = bootstrap.Modal.getInstance(modal);
        if (modalInstance) {
            modalInstance.hide();
        }

        // Redirect to the dashboard after closing
        window.location.href = "http://localhost/tebz/passenger_dashboard.php?page=dashboard";
    });
});
</script>
<script>
document.querySelectorAll('.role-btn').forEach(button => {
    button.addEventListener('click', function() {
        // Set hidden input value
        document.getElementById('selectedRole').value = this.getAttribute('data-role');

        // Remove selection from all buttons
        document.querySelectorAll('.role-btn').forEach(btn => btn.classList.remove('selected'));

        // Add selection to clicked button
        this.classList.add('selected');
    });
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

</body>
</html>