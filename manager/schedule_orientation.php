<?php
session_start();
require_once __DIR__ . '/../db_config.php';

// Ensure manager access
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'manager') {
    header("Location: ../index.php");
    exit();
}

// Handle orientation creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_orientation'])) {
    $title = trim($_POST['title']);
    $venue = trim($_POST['venue']) ?: null;
    $link = trim($_POST['link']) ?: null;
    $orientation_date = $_POST['orientation_date'];
    $orientation_time = $_POST['orientation_time'];
    $target_role = 'driver,operator';

    if ($title && $orientation_date && $orientation_time) {
        $stmt = $conn->prepare("INSERT INTO orientation_schedule (title, venue, link, orientation_date, orientation_time, target_role) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("ssssss", $title, $venue, $link, $orientation_date, $orientation_time, $target_role);
        $stmt->execute();
        $stmt->close();

        $_SESSION['orientation_created'] = true;
    }
}

if (isset($_GET['delete_orientation'])) {
    $id = intval($_GET['delete_orientation']);
    $conn->query("DELETE FROM orientation_schedule WHERE id = $id");
    $_SESSION['orientation_deleted'] = true;
    header("Location: schedule_orientation.php");
    exit();
}

// Handle orientation update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_orientation'])) {
    $orientation_id = intval($_POST['orientation_id']);
    $title = trim($_POST['title']);
    $venue = trim($_POST['venue']) ?: null;
    $link = trim($_POST['link']) ?: null;
    $orientation_date = $_POST['orientation_date'];
    $orientation_time = $_POST['orientation_time'];
    $target_role = 'driver,operator';

    if ($title && $orientation_date && $orientation_time) {
        $stmt = $conn->prepare("UPDATE orientation_schedule SET title = ?, venue = ?, link = ?, orientation_date = ?, orientation_time = ?, target_role = ? WHERE id = ?");
        $stmt->bind_param("ssssssi", $title, $venue, $link, $orientation_date, $orientation_time, $target_role, $orientation_id);
        $stmt->execute();
        $stmt->close();

        $_SESSION['orientation_updated'] = true;
        header("Location: schedule_orientation.php");
        exit();
    }
}
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <title>Schedule Orientation</title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">


    <style>
        body {
            background-color: #f8f9fa;
        }
        .card {
            border: none;
            border-radius: 16px;
            box-shadow: 0 0 20px rgba(0, 0, 0, 0.05);
        }
        .form-label {
            font-weight: 500;
        }
        .btn-success {
            font-weight: 500;
            font-size: 16px;
        }
    </style>
</head>
<body>

<div class="container py-5">
    <div class="row g-4">
        <!-- Form Column -->
        <div class="col-lg-6">
            <div class="card p-4">
                <div class="card-body">
                    <h3 class="text-center mb-4 text-success">📅 Schedule Orientation</h3>
                    <form method="POST" novalidate>
                        <div class="mb-3">
                            <label class="form-label">Orientation Title</label>
                            <input type="text" name="title" class="form-control" required placeholder="e.g., Operator Onboarding Session">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Venue (if in-person)</label>
                            <input type="text" name="venue" class="form-control" placeholder="e.g., Room 302, Main Hall">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Meeting Link (if online)</label>
                            <input type="url" name="link" class="form-control" placeholder="e.g., https://meet.example.com/your-room">
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Date</label>
                                <input type="date" name="orientation_date" class="form-control" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Time</label>
                                <input type="time" name="orientation_time" class="form-control" required>
                            </div>
                        </div>
                        <button type="submit" name="create_orientation" class="btn btn-success w-100 mt-2">
                            📤 Create Orientation
                        </button>
                        <div class="d-flex justify-content-between mt-3">
                            <a href="manager_dashboard.php" class="btn btn-outline-secondary">
                                ← Back to Dashboard
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Orientation List Column -->
        <div class="col-lg-6">
            <div class="card p-4">
                <div class="card-body">
                    <h5 class="text-center text-primary mb-3">📋 Posted Orientations</h5>
                    <?php
                    $orientationListQuery = "SELECT * FROM orientation_schedule ORDER BY orientation_date DESC";
                    $listResult = $conn->query($orientationListQuery);

                    if ($listResult && $listResult->num_rows > 0):
                        while ($row = $listResult->fetch_assoc()):
                    ?>
                        <div class="border rounded p-3 mb-3 bg-light shadow-sm">
                            <div class="d-flex justify-content-between align-items-start flex-wrap">
                                <div style="flex: 1 1 auto;">
                                    <h6 class="mb-1"><?= htmlspecialchars($row['title']) ?></h6>
                                    <p class="mb-1 text-muted">
                                        📅 <?= date("F j, Y", strtotime($row['orientation_date'])) ?> |
                                        🕒 <?= date("g:i A", strtotime($row['orientation_time'])) ?>
                                    </p>
                                    <?php if (!empty($row['venue'])): ?>
                                        <p class="mb-1"><strong>🏢 Venue:</strong> <?= htmlspecialchars($row['venue']) ?></p>
                                    <?php endif; ?>
                                    <?php if (!empty($row['link'])): ?>
                                        <p class="mb-1"><strong>🔗 Link:</strong>
                                            <a href="<?= htmlspecialchars($row['link']) ?>" target="_blank">View Meeting</a>
                                        </p>
                                    <?php endif; ?>
                                </div>
                                <div class="d-flex flex-column align-items-end gap-2 ms-3" style="min-width: 140px;">
                                    <!-- View Attendees -->
                                    <a href="attendees_list.php?orientation_id=<?= $row['id'] ?>" class="btn btn-sm btn-info w-100">
                                        <i class="bi bi-people-fill"></i> View Attendees
                                    </a>
<!-- Edit Button -->
<button type="button" class="btn btn-sm btn-outline-primary w-100" data-bs-toggle="modal" data-bs-target="#editModal<?= $row['id'] ?>">
    <i class="bi bi-pencil-square"></i> Edit
</button>

<!-- Edit Modal -->
<div class="modal fade" id="editModal<?= $row['id'] ?>" tabindex="-1" aria-labelledby="editModalLabel<?= $row['id'] ?>" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <form method="POST" class="modal-content">
      <input type="hidden" name="orientation_id" value="<?= $row['id'] ?>">
      <input type="hidden" name="update_orientation" value="1">

      <div class="modal-header">
        <h5 class="modal-title" id="editModalLabel<?= $row['id'] ?>">✏️ Edit Orientation</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>

      <div class="modal-body">
        <div class="mb-3">
          <label class="form-label">Title</label>
          <input type="text" name="title" value="<?= htmlspecialchars($row['title']) ?>" class="form-control" required>
        </div>
        <div class="mb-3">
          <label class="form-label">Venue (if in-person)</label>
          <input type="text" name="venue" value="<?= htmlspecialchars($row['venue']) ?>" class="form-control">
        </div>
        <div class="mb-3">
          <label class="form-label">Meeting Link (if online)</label>
          <input type="url" name="link" value="<?= htmlspecialchars($row['link']) ?>" class="form-control">
        </div>
        <div class="row">
          <div class="col-md-6 mb-3">
            <label class="form-label">Date</label>
            <input type="date" name="orientation_date" value="<?= $row['orientation_date'] ?>" class="form-control" required>
          </div>
          <div class="col-md-6 mb-3">
            <label class="form-label">Time</label>
            <input type="time" name="orientation_time" value="<?= $row['orientation_time'] ?>" class="form-control" required>
          </div>
        </div>
      </div>

      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="submit" class="btn btn-primary">💾 Save Changes</button>
      </div>
    </form>
  </div>
</div>

                                    <!-- Delete -->
                                    <form method="GET" onsubmit="return confirm('Are you sure you want to delete this orientation?');" class="w-100">
                                        <input type="hidden" name="delete_orientation" value="<?= $row['id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-danger w-100">
                                            <i class="bi bi-trash"></i> Delete
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    <?php endwhile; else: ?>
                        <div class="alert alert-info text-center">No orientations have been posted yet.</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>




<!-- SweetAlert2 CDN -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<?php if (isset($_SESSION['orientation_created']) && $_SESSION['orientation_created'] === true): ?>
<script>
    Swal.fire({
        icon: 'success',
        title: 'Orientation Created!',
        text: 'Redirecting to dashboard...',
        showConfirmButton: false,
        timer: 2000
    }).then(() => {
        window.location.href = 'manager_dashboard.php';
    });
</script>
<?php unset($_SESSION['orientation_created']); ?>
<?php endif; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
<?php if (isset($_SESSION['orientation_deleted']) && $_SESSION['orientation_deleted'] === true): ?>
<script>
    Swal.fire({
        icon: 'success',
        title: 'Orientation Deleted!',
        text: 'The selected orientation has been removed.',
        timer: 1800,
        showConfirmButton: false
    });
</script>
<?php unset($_SESSION['orientation_deleted']); ?>
<?php endif; ?>
<?php if (isset($_SESSION['orientation_updated'])): ?>
<script>
    Swal.fire({
        icon: 'success',
        title: 'Orientation Updated!',
        text: 'Changes were saved successfully.',
        timer: 2000,
        showConfirmButton: false
    });
</script>
<?php unset($_SESSION['orientation_updated']); ?>
<?php endif; ?>

</body>
</html>
