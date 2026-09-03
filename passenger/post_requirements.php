<?php
session_start();
require_once __DIR__ . '/../db_config.php';

// Ensure the user is a manager
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'manager') {
    header("Location: ../index.php");
    exit();
}

// Success/Error messages
$successMessage = "";
$errorMessage = "";

// Handle form submission for adding a new requirement
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['title']) && !isset($_POST['delete_id'])) {
    $title = htmlspecialchars($_POST['title']);
    $membershipRequirements = htmlspecialchars($_POST['membership_requirements']);
    $generalRequirements = htmlspecialchars($_POST['general_requirements']);
    $contactInfo = htmlspecialchars($_POST['contact_info']);

    // Insert into the database
    $stmt = $conn->prepare("INSERT INTO membership_requirements (title, membership_requirements, general_requirements, contact_info) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("ssss", $title, $membershipRequirements, $generalRequirements, $contactInfo);

    if ($stmt->execute()) {
        $successMessage = "Membership requirements posted successfully!";
    } else {
        $errorMessage = "Error: Could not post the requirements.";
    }
    $stmt->close();
}

// Handle deletion (only if delete_id is set)
if (isset($_GET['delete_id'])) {
    $deleteId = intval($_GET['delete_id']);
    $deleteStmt = $conn->prepare("DELETE FROM membership_requirements WHERE id = ?");
    $deleteStmt->bind_param("i", $deleteId);
    if ($deleteStmt->execute()) {
        $successMessage = "Requirement deleted successfully!";
    } else {
        $errorMessage = "Error: Could not delete the requirement.";
    }
    $deleteStmt->close();
}

// Fetch all posted membership requirements
$result = $conn->query("SELECT * FROM membership_requirements ORDER BY id DESC");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Post Membership Requirements</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
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
        .btn-primary, .btn-danger, .btn-warning {
            font-weight: 500;
            font-size: 14px;
        }
        table th {
            background-color: #6c5ce7;
            color: #fff;
            text-align: center;
        }
        table td {
            text-align: center;
        }
    </style>
</head>
<body>

<div class="container my-5">
    <a href="manager_dashboard.php" class="btn btn-outline-secondary mb-4">← Back to Dashboard</a>

    <!-- Heading -->
    <h3 class="text-center text-primary mb-4 fw-bold">📝 Post Membership Requirements</h3>

    <!-- Alerts -->
    <?php if (!empty($successMessage)): ?>
<script>
Swal.fire({
    icon: 'success',
    title: 'Success!',
    text: '<?= $successMessage ?>',
    confirmButtonColor: '#198754'
});
</script>
<?php elseif (!empty($errorMessage)): ?>
<script>
Swal.fire({
    icon: 'error',
    title: 'Oops!',
    text: '<?= $errorMessage ?>',
    confirmButtonColor: '#dc3545'
});
</script>
<?php endif; ?>


    <div class="row">
        <!-- Form -->
        <div class="col-lg-6 mb-4">
            <div class="card p-4">
                <h5 class="text-success text-center mb-3">Add New Requirement</h5>
                <form method="POST">
                    <div class="mb-3">
                        <label class="form-label">Title</label>
                        <input type="text" name="title" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Membership Requirements</label>
                        <textarea name="membership_requirements" class="form-control" rows="3" required></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">General Requirements</label>
                        <textarea name="general_requirements" class="form-control" rows="3" required></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Contact Information</label>
                        <input type="text" name="contact_info" class="form-control" required>
                    </div>
                    <button type="submit" class="btn btn-primary w-100">📤 Post Requirement</button>
                </form>
            </div>
        </div>

        <!-- Table -->
        <div class="col-lg-6">
            <div class="card p-4">
                <h5 class="text-info text-center mb-3">📚 Posted Requirements</h5>
                <div class="table-responsive">
                    <table class="table table-bordered align-middle">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Title</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if ($result && $result->num_rows > 0): ?>
                            <?php $count = 1; while ($row = $result->fetch_assoc()): ?>
                                <tr>
                                    <td><?= $count++; ?></td>
                                    <td><?= htmlspecialchars($row['title']); ?></td>
                                    <td>
                                    <button class="btn btn-danger btn-sm" onclick="confirmDelete(<?= $row['id']; ?>)">🗑 Delete</button>
                                    <button class="btn btn-warning btn-sm" data-bs-toggle="modal" data-bs-target="#editModal<?= $row['id']; ?>">✏ Edit</button>
                                    </td>
                                </tr>

                                <!-- Edit Modal -->
                                <div class="modal fade" id="editModal<?= $row['id']; ?>" tabindex="-1" aria-hidden="true">
                                    <div class="modal-dialog">
                                        <div class="modal-content">
                                            <div class="modal-header bg-warning">
                                                <h5 class="modal-title">Edit Requirement</h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                            </div>
                                            <form method="POST" action="edit_requirements.php">
                                                <div class="modal-body">
                                                    <input type="hidden" name="id" value="<?= $row['id']; ?>">
                                                    <label class="form-label">Title</label>
                                                    <input type="text" name="title" class="form-control mb-2" value="<?= htmlspecialchars($row['title']); ?>" required>
                                                    <label class="form-label">Membership Requirements</label>
                                                    <textarea name="membership_requirements" class="form-control mb-2" rows="3"><?= htmlspecialchars($row['membership_requirements']); ?></textarea>
                                                    <label class="form-label">General Requirements</label>
                                                    <textarea name="general_requirements" class="form-control mb-2" rows="3"><?= htmlspecialchars($row['general_requirements']); ?></textarea>
                                                    <label class="form-label">Contact Info</label>
                                                    <input type="text" name="contact_info" class="form-control" value="<?= htmlspecialchars($row['contact_info']); ?>" required>
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="submit" class="btn btn-primary">💾 Save</button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="3" class="text-center text-muted">No requirements posted yet.</td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
<script>
function confirmDelete(id) {
    Swal.fire({
        title: 'Are you sure?',
        text: 'This requirement will be permanently deleted!',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#e74c3c',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Yes, delete it!'
    }).then((result) => {
        if (result.isConfirmed) {
            window.location.href = '?delete_id=' + id;
        }
    });
}
</script>

</body>
</html>
