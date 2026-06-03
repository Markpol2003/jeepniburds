<?php
session_start();
require_once 'db_config.php';

// Security check
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'manager') {
    header("Location: landing.php");
    exit();
}

$orientation_id = $_GET['orientation_id'] ?? null;

$orientationTitle = '';
if ($orientation_id) {
    $getTitle = $conn->query("SELECT title FROM orientation_schedule WHERE id = $orientation_id");
    $orientationTitle = $getTitle && $getTitle->num_rows > 0 ? $getTitle->fetch_assoc()['title'] : '';
}
// Handle individual 'Done' actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['attendee_id'])) {
        $attendeeId = intval($_POST['attendee_id']);
        $conn->query("UPDATE orientation_attendees SET is_completed = 1 WHERE id = $attendeeId");
    }

    if (isset($_POST['orientation_id']) && isset($_POST['mark_all'])) {
        $orientationId = intval($_POST['orientation_id']);
        $conn->query("UPDATE orientation_attendees SET is_completed = 1 WHERE orientation_id = $orientationId");
    }

    // Redirect to same page after POST to avoid resubmission warning
    $redirectId = $_POST['orientation_id'] ?? '';
    header("Location: attendees_list.php" . ($redirectId ? "?orientation_id=$redirectId" : ""));
    exit();
}


// Fetch attendees
$attendeesQuery = "
    SELECT a.id, u.firstName, u.lastName, u.userType,
           o.title, o.orientation_date, a.attended_mode, o.target_role, o.link, o.venue, 
           a.attended_at, a.is_completed
    FROM orientation_attendees a
    JOIN users u ON a.user_id = u.id
    JOIN orientation_schedule o ON a.orientation_id = o.id
    " . ($orientation_id ? "WHERE o.id = $orientation_id" : "") . "
    ORDER BY o.id DESC, a.id DESC";

$attendeesResult = $conn->query($attendeesQuery);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>Attendees List</title>
    <meta charset="UTF-8">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
            font-family: 'Poppins', sans-serif;
        }
        .card {
            border: none;
            border-radius: 16px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.05);
        }
        .table th {
            background-color: #007bff;
            color: white;
        }
        .btn-dashboard {
            background-color: #6c757d;
            color: white;
            font-weight: 600;
        }
        .btn-dashboard:hover {
            background-color: #5a6268;
        }
        .table td, .table th {
            vertical-align: middle;
        }
    </style>
</head>
<body>
<div class="container my-5">
    <div class="card p-4">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h4 class="fw-bold text-primary mb-0">
                👥 Attendees <?= $orientationTitle ? 'for "' . htmlspecialchars($orientationTitle) . '"' : '' ?>
            </h4>
            <a href="manager_dashboard.php" class="btn btn-dashboard">
                <i class="bi bi-arrow-left-circle"></i> Back to Dashboard
            </a>
        </div>

        <?php if ($attendeesResult && $attendeesResult->num_rows > 0): ?>
            <?php if ($orientation_id): ?>
<form method="POST" onsubmit="return confirm('Are you sure you want to mark all attendees as completed?');">
    <input type="hidden" name="orientation_id" value="<?= $orientation_id ?>">
    <button type="submit" name="mark_all" class="btn btn-success mb-3">
        <i class="bi bi-check-all"></i> Mark All as Completed
    </button>
</form>
<?php endif; ?>


        <div class="mb-3 d-flex justify-content-between align-items-center">
            <input type="text" id="searchInput" class="form-control w-50" placeholder="Search by name...">
            <select id="modeFilter" class="form-select w-25 ms-3">
                <option value="">All Modes</option>
                <option value="online">Online</option>
                <option value="in-person">In-Person</option>
            </select>
    <button onclick="exportToPDF()" class="btn btn-outline-danger">
        <i class="bi bi-filetype-pdf"></i> Export PDF
    </button>
        </div>

        <div class="table-responsive">
            <table class="table table-hover table-striped align-middle text-center">
                <thead class="table-primary">
                    <tr>
                        <th>#</th>
                        <th>Name</th>
                        <th>User Role</th>
                        <th>Orientation Title</th>
                        <th>Date</th>
                        <th>Format</th>
                        <th>Venue / Link</th>
                        <th>Confirmed At</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $counter = 1; while ($row = $attendeesResult->fetch_assoc()): ?>
                        <tr>
                            <td><?= $counter++; ?></td>
                            <td><?= htmlspecialchars($row['firstName'] . ' ' . $row['lastName']); ?></td>
                            <td>
                                <span class="badge bg-<?= $row['userType'] === 'driver' ? 'primary' : 'info'; ?>">
                                    <?= ucfirst($row['userType']); ?>
                                </span>
                            </td>
                            <td><?= htmlspecialchars($row['title']); ?></td>
                            <td><?= htmlspecialchars($row['orientation_date']); ?></td>
                            <td>
                                <span class="badge bg-<?= $row['attended_mode'] === 'online' ? 'info' : 'secondary'; ?>">
                                    <?= ucfirst($row['attended_mode']); ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($row['attended_mode'] === 'online'): ?>
                                    <a href="<?= htmlspecialchars($row['link']); ?>" target="_blank" class="btn btn-sm btn-outline-primary">
                                        <i class="bi bi-camera-video-fill"></i> Join
                                    </a>
                                <?php else: ?>
                                    <?= htmlspecialchars($row['venue']); ?>
                                <?php endif; ?>
                            </td>
                            <td><?= htmlspecialchars($row['attended_at']); ?></td>
                            <td>
                                <?php if ($row['is_completed']): ?>
                                    <span class="badge bg-success">Completed</span>
                                <?php else: ?>
                                    <form method="POST" onsubmit="return confirm('Mark as completed?');">
                                    <input type="hidden" name="attendee_id" value="<?= $row['id']; ?>">
                                        <button type="submit" class="btn btn-sm btn-success">
                                            <i class="bi bi-check-circle-fill"></i> Done
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
            <div class="alert alert-info text-center">No attendees found.</div>
        <?php endif; ?>
    </div>
</div>

<!-- Scripts -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>

<script>
// Search + Filter
const searchInput = document.getElementById("searchInput");
const modeFilter = document.getElementById("modeFilter");

function filterTable() {
    const searchValue = searchInput.value.toLowerCase();
    const modeValue = modeFilter.value.toLowerCase();

    document.querySelectorAll("table tbody tr").forEach(row => {
        const name = row.cells[1].textContent.toLowerCase();
        const mode = row.cells[5].textContent.toLowerCase();
        const show = name.includes(searchValue) && (modeValue === "" || mode.includes(modeValue));
        row.style.display = show ? "" : "none";
    });
}

searchInput.addEventListener("keyup", filterTable);
modeFilter.addEventListener("change", filterTable);
</script>

<!-- html2pdf CDN -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.9.2/html2pdf.bundle.min.js"></script>

<script>
function exportToPDF() {
    const element = document.querySelector('.table-responsive');
    const opt = {
        margin: 0.5,
        filename: 'attendees_list.pdf',
        image: { type: 'jpeg', quality: 0.98 },
        html2canvas: { scale: 2 },
        jsPDF: { unit: 'in', format: 'letter', orientation: 'landscape' }
    };

    // Create a new window for the PDF
    const newWindow = window.open('', '_blank');
    
    // Generate PDF in the new window
    html2pdf().set(opt).from(element).outputPdf('datauristring').then(function(pdfAsString) {
        newWindow.document.write(`
            <html>
                <head>
                    <title>Attendees List</title>
                </head>
                <body style="margin:0;padding:0;">
                    <embed width="100%" height="100%" src="${pdfAsString}" type="application/pdf">
                </body>
            </html>
        `);
    });
}
</script>

</body>
</html>
