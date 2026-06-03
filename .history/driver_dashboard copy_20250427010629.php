<?php
session_start();
require_once 'db_config.php';
date_default_timezone_set('Asia/Manila');

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
        <img src="<?= htmlspecialchars($profileImage); ?>" 
             alt="Profile Picture" 
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
        <!-- Assigned Jeepney Link -->
<li class="nav-item mb-2">
    <a class="nav-link text-white <?= $page === 'assignedjeepney' ? 'fw-bold' : '' ?>" href="?page=assignedjeepney">
        <i class="bi bi-truck-front me-2"></i> Assigned Jeepney
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
            WHERE os.orientation_date >= ? AND os.target_role = 'driver'
            ORDER BY os.orientation_date ASC, os.orientation_time ASC
            LIMIT 1
        ";

        $stmt = $conn->prepare($todayQuery);
        $stmt->bind_param("is", $userId, $today);
        $stmt->execute();
        $todayResult = $stmt->get_result();

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

        <!-- Countdown -->
        <div id="countdownTimer" class="text-danger fw-semibold mt-2"></div>
        <script>
        function startCountdown(datetime) {
            const target = new Date(datetime).getTime();
            const timer = setInterval(() => {
                const now = new Date().getTime();
                const diff = target - now;
                if (diff <= 0) {
                    document.getElementById("countdownTimer").innerHTML = "🟢 Orientation starting now!";
                    clearInterval(timer);
                } else {
                    const hrs = Math.floor((diff % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
                    const mins = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60));
                    const secs = Math.floor((diff % (1000 * 60)) / 1000);
                    document.getElementById("countdownTimer").innerHTML = `⏳ Starts in: ${hrs}h ${mins}m ${secs}s`;
                }
            }, 1000);
        }
        startCountdown("<?= $sched['orientation_date'] . ' ' . $sched['orientation_time'] ?>");
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
                <a href="?page=payment" class="btn btn-sm btn-success mt-2">→ Pay Membership Fee Now</a>
            </div>
        <?php else: ?>
            <div class="alert alert-info text-center mt-3">
                <i class="bi bi-clock-history"></i> Waiting for manager to mark as completed...
            </div>
        <?php endif; ?>

        <?php else: ?>
            <p class="mb-0">No schedules available. Stay tuned!</p>
        <?php endif; ?>
        </div>
    </div>
</div>



            <?php
// Check if the user already requested to attend orientation
$requestQuery = "SELECT * FROM orientation_requests WHERE user_id = ? AND status = 'Pending'";
$requestStmt = $conn->prepare($requestQuery);
$requestStmt->bind_param("i", $userId); // Make sure $userId is set from session
$requestStmt->execute();
$requestResult = $requestStmt->get_result();
$hasRequestedOrientation = $requestResult->num_rows > 0;
?>


<!-- Upcoming Orientation -->
<div class="col-md-6 mb-4">
    <div class="card shadow-sm">
        <div class="card-header bg-success text-white">
            <h5 class="mb-0">📅 Upcoming Orientation</h5>
        </div>
        <div class="card-body" id="orientationRequestSection">
        <?php
// Check orientation request status
$scheduleQuery = "SELECT os.id, os.title, os.orientation_date AS date, os.orientation_time AS time, os.venue, os.link, 
    (SELECT is_completed FROM orientation_attendees WHERE orientation_id = os.id AND user_id = $userId LIMIT 1) AS is_completed 
    FROM orientation_schedule os 
    WHERE os.orientation_date >= CURDATE() AND os.target_role = 'driver'
    ORDER BY os.orientation_date ASC
    LIMIT 1";

$scheduleResult = $conn->query($scheduleQuery);
$scheduleAvailable = ($scheduleResult && $scheduleResult->num_rows > 0);
?>
<?php if ($scheduleAvailable): ?>
    <?php $schedule = $scheduleResult->fetch_assoc(); ?>
    <p><strong>Title:</strong> <?= htmlspecialchars($schedule['title']); ?></p>
    <p><strong>Date:</strong> <?= htmlspecialchars($schedule['date']); ?></p>
    <p><strong>Time:</strong> <?= htmlspecialchars($schedule['time']); ?></p>

    <?php if ($schedule['is_completed']): ?>
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
                <p><strong>Meeting Link:</strong> <a href="<?= htmlspecialchars($schedule['link']); ?>" target="_blank">Join Meeting</a></p>
                <button class="btn btn-info w-100" onclick="submitAttendance(<?= $schedule['id']; ?>, 'online')">✅ Attend Online</button>
            </div>
        <?php endif; ?>

        <?php if (!empty($schedule['venue'])): ?>
            <div id="inpersonDetails" class="d-none text-center">
                <p><strong>Venue:</strong> <?= htmlspecialchars($schedule['venue']); ?></p>
                <button class="btn btn-secondary w-100" onclick="submitAttendance(<?= $schedule['id']; ?>, 'in-person')">✅ Attend In-Person</button>
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



<?php elseif ($hasRequestedOrientation && !$scheduleAvailable): ?>
    <div class="alert alert-success text-center mb-0">
        ✅ You’ve already requested orientation.<br>
        ⏳ Please wait for the manager to post the schedule.
    </div>
<?php endif; ?>


        </div>
    </div>
</div>

    <?php elseif ($page === 'profile'): ?>
        <!-- Profile Page -->
        <div class="card shadow-sm">
            <div class="card-header bg-primary text-white">
                <h5>👤 My Profile</h5>
            </div>
            <div class="card-body text-center">
                <img src="<?= htmlspecialchars($profileImage); ?>" class="rounded-circle mb-3" width="120" alt="Profile Picture">
                <form method="POST" enctype="multipart/form-data" class="d-flex flex-column align-items-center">
                    <input type="file" name="profile_image" class="form-control mb-2 w-50" accept="image/*" required>
                    <button type="submit" class="btn btn-outline-primary">Upload Photo</button>
                </form>
                <hr>
                <table class="table table-borderless text-start mt-3">
                    <tr><th>First Name:</th><td><?= htmlspecialchars($userFirstName); ?></td></tr>
                    <tr><th>Last Name:</th><td><?= htmlspecialchars($userLastName); ?></td></tr>
                    <tr><th>Email:</th><td><?= htmlspecialchars($userEmail); ?></td></tr>
                    <tr><th>Role:</th><td><?= $userType; ?></td></tr>
                </table>
            </div>
        </div>

        <?php elseif ($page === 'payment'): ?>

<?php
// Check if user already paid
$paymentCheckQuery = "SELECT * FROM membership_payments WHERE user_id = ? AND status = 'Confirmed' LIMIT 1";
$paymentCheckStmt = $conn->prepare($paymentCheckQuery);
$paymentCheckStmt->bind_param("i", $userId);
$paymentCheckStmt->execute();
$paymentCheckResult = $paymentCheckStmt->get_result();
$hasPaid = $paymentCheckResult->num_rows > 0;
?>

<!-- Payment Section -->
<div class="card shadow-sm">
    <div class="card-header bg-success text-white text-center">
        <h5>Membership Payment</h5>
        <small class="d-block mt-1">Activate your membership by submitting a payment.</small>
    </div>

    <?php if (!$hasPaid): ?>
        <!-- Show Payment Form if NOT PAID -->
        <div class="card-body px-4 py-4">
            <form id="paymentForm">
                <div class="form-floating mb-4">
                    <select name="method" id="method" class="form-select" required>
                        <option value="" disabled selected>Select Payment Method</option>
                        <option value="GCash">GCash</option>
                        <option value="Cash">Cash</option>
                    </select>
                    <label for="method">Payment Method</label>
                </div>

                <div class="form-floating mb-4">
                    <input type="number" name="amount" id="amount" class="form-control" placeholder="Enter amount" required min="1">
                    <label for="amount">Amount (PHP)</label>
                </div>

                <button type="submit" class="btn btn-success w-100 shadow-sm">
                    <i class="bi bi-cash-coin me-2"></i>Submit Payment
                </button>
            </form>
        </div>

    <?php else: ?>
        <!-- Fetch latest confirmed payment -->
        <?php
        $paymentQuery = "SELECT amount, method FROM membership_payments WHERE user_id = ? AND status = 'Confirmed' ORDER BY payment_date DESC LIMIT 1";
        $paymentStmt = $conn->prepare($paymentQuery);
        $paymentStmt->bind_param("i", $userId);
        $paymentStmt->execute();
        $paymentResult = $paymentStmt->get_result();

        if ($paymentResult->num_rows > 0) {
            $paymentData = $paymentResult->fetch_assoc();
            $paymentAmount = $paymentData['amount'];
            $paymentMethod = $paymentData['method'];
        } else {
            $paymentAmount = 0.00;
            $paymentMethod = 'N/A';
        }
        ?>

        <!-- Show Receipt if Already PAID -->
        <div class="card-body px-5 py-4 text-center" id="receiptArea">
            <img src="\tebz\img\logo12.png" alt="Coop Logo" style="height: 60px; margin-bottom: 10px;">
            <h4 class="fw-bold mb-1 text-success">JEEPNIGO</h4>
            <small class="text-muted">Official Receipt</small>

            <hr style="border-top: 2px dashed #198754;">

            <div class="text-start mt-4 mb-3" style="font-size: 16px;">
                <p><strong>Receipt No:</strong> <?= sprintf("%06d", rand(100000,999999)); ?></p>
                <p><strong>Date:</strong> <?= date('F d, Y'); ?></p>
                <p><strong>Received From:</strong> <?= htmlspecialchars($userFirstName . ' ' . $userLastName); ?></p>
                <p><strong>Amount Paid:</strong> ₱<?= number_format($paymentAmount, 2); ?></p>
                <p><strong>Payment For:</strong> Membership Fee</p>
                <p><strong>Payment Method:</strong> <?= htmlspecialchars($paymentMethod); ?></p>
            </div>

            <hr style="border-top: 2px dashed #198754;">

            <div class="d-flex justify-content-between mt-4">
                <div class="text-start">
                    <p class="mb-1 text-muted">Received By:</p>
                    <p style="border-bottom: 1px dotted #333; width: 200px; margin-bottom: 0;">&nbsp;</p>
                    <small class="text-muted">Treasurer / Cashier</small>
                </div>

                <div class="text-end">
                    <p class="mb-1 text-muted">Signature:</p>
                    <p style="border-bottom: 1px dotted #333; width: 200px; margin-bottom: 0;">&nbsp;</p>
                    <small class="text-muted">Member Signature</small>
                </div>
            </div>
        </div>

        <div class="card-footer text-center">
            <button onclick="viewReceiptPDF()" class="btn btn-outline-success">
                <i class="bi bi-eye"></i> View Receipt (PDF)
            </button>
        </div>

        <!-- Congratulations Card -->
        <div class="card my-4 shadow-sm animate__animated animate__fadeInUp" style="border: 2px dashed green;">
            <div class="card-body text-center">
            <h4 class="fw-bold text-success mb-3">🎉 Congratulations!</h4>
<p class="text-muted mb-2">You are now an official member of the Cooperative.</p>
<p class="fw-semibold text-dark mb-4">You can now view your assigned Jeepney.</p>


                <a href="?page=assignjeepney" class="btn btn-success px-4 py-2">
   <i class="bi bi-truck-front me-1"></i> Proceed to View Assigned Jeepney
</a>

            </div>
        </div>
    <?php endif; ?>

</div> 
</div> 
<?php else: ?>
<!-- Other Content -->

<?php endif; ?>


<!-- JS Toggle Logic -->
<script>
function showOrientation(type) {
    document.getElementById('onlineDetails')?.classList.add('d-none');
    document.getElementById('inpersonDetails')?.classList.add('d-none');

    if (type === 'online') {
        document.getElementById('onlineDetails')?.classList.remove('d-none');
    } else if (type === 'inperson') {
        document.getElementById('inpersonDetails')?.classList.remove('d-none');
    }
}
</script>

<!-- Scripts -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
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
<script>
function submitAttendance(orientationId, mode) {
    fetch("handle_attendance.php", {
        method: "POST",
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({
            orientation_id: orientationId,
            attend_orientation: mode,
            attend_orientation_ajax: true
        })
    })
    .then(res => res.json())
    .then(data => {
        if (data.status === "success") {
            Swal.fire({
                icon: 'success',
                title: 'Attendance Confirmed!',
                text: `You are now attending ${mode.toUpperCase()}.`,
                confirmButtonColor: '#28a745'
            }).then(() => {
                location.reload(); // ✅ Reflect changes immediately
            });
        } else if (data.status === "already_confirmed") {
            if (data.current_mode === mode) {
                Swal.fire({
                    icon: 'info',
                    title: 'Already Confirmed',
                    text: `You're already confirmed for "${mode.toUpperCase()}".`,
                    confirmButtonColor: '#0d6efd'
                });
            } else {
                Swal.fire({
                    icon: 'question',
                    title: 'Change Attendance?',
                    text: `You already confirmed attendance as "${data.current_mode}". Do you want to switch to "${mode}"?`,
                    showCancelButton: true,
                    confirmButtonText: 'Yes, change it',
                    cancelButtonText: 'No, keep it',
                    confirmButtonColor: '#0d6efd',
                    cancelButtonColor: '#6c757d'
                }).then(result => {
                    if (result.isConfirmed) {
                        fetch("update_attendance.php", {
                            method: "POST",
                            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                            body: new URLSearchParams({
                                orientation_id: orientationId,
                                new_mode: mode
                            })
                        }).then(() => {
                            Swal.fire({
                                icon: 'success',
                                title: 'Updated!',
                                text: `Attendance changed to ${mode.toUpperCase()}.`,
                                confirmButtonColor: '#198754'
                            }).then(() => {
                                location.reload();
                            });
                        });
                    }
                });
            }
        }
    });
}


</script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
function requestOrientation() {
    Swal.fire({
        title: "Confirm Attendance Request",
        text: "Are you sure you want to notify the manager that you're ready to attend orientation?",
        icon: "question",
        showCancelButton: true,
        confirmButtonText: "Yes, Notify",
        cancelButtonText: "Cancel",
        confirmButtonColor: "#28a745"
    }).then(result => {
        if (result.isConfirmed) {
            fetch('request_orientation.php', {
                method: 'POST',
                body: new FormData()
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Request Sent!',
                        text: 'You’ve successfully notified the manager.',
                        timer: 2000,
                        showConfirmButton: false
                    });

                    document.getElementById("orientationRequestSection").innerHTML = `
                        <div class="alert alert-success text-center mt-3">
                            ✅ You’ve already requested orientation. Please wait for the schedule.
                        </div>`;
                } else {
                    Swal.fire({
                        icon: 'warning',
                        title: 'Already Requested',
                        text: data.error || 'You have already sent a request.'
                    });
                }
            })
            .catch(err => {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'Something went wrong. Try again.'
                });
            });
        }
    });
}

</script>
<!-- Scripts -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>

<script>
    function printReceipt() {
    var printContent = document.getElementById('receiptArea').innerHTML;
    var originalContent = document.body.innerHTML;

    document.body.innerHTML = printContent;
    window.print();
    document.body.innerHTML = originalContent;
}
</script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.9.2/html2pdf.bundle.min.js"></script>
<script>
function viewReceiptPDF() {
    const element = document.getElementById('receiptArea');

    var opt = {
        margin:       0.5,
        image:        { type: 'jpeg', quality: 0.98 },
        html2canvas:  { scale: 2 },
        jsPDF:        { unit: 'in', format: 'letter', orientation: 'portrait' }
    };

    html2pdf().set(opt).from(element).toPdf().get('pdf').then(function (pdf) {
        const blob = pdf.output('blob');
        const url = URL.createObjectURL(blob);
        window.open(url);  // Open PDF in New Tab
    });
}

</script>

</body>
</html>
