<?php
session_start();
require_once __DIR__ . '/../db_config.php';

// Ensure admin is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'admin') {
    header("Location: ../landing.php");
    exit();
}

// Fetch cooperative applications
$applicationsQuery = "SELECT * FROM cooperative_applications ORDER BY submitted_at DESC";
$applicationsResult = $conn->query($applicationsQuery);

// Handle approval, rejection, or deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['application_id'])) {
    $applicationId = intval($_POST['application_id']);
    $action = $_POST['action'];

    // Fetch application details
    $fetchAppQuery = "SELECT * FROM cooperative_applications WHERE id = ?";
    $fetchStmt = $conn->prepare($fetchAppQuery);
    $fetchStmt->bind_param("i", $applicationId);
    $fetchStmt->execute();
    $application = $fetchStmt->get_result()->fetch_assoc();
    $fetchStmt->close();

    if ($action === 'Approve') {
        // Update the userType of the contact_email in the users table to 'manager'
        if ($application) {
            $updateUserQuery = "UPDATE users SET userType = 'manager' WHERE email = ?";
            $updateUserStmt = $conn->prepare($updateUserQuery);
            $updateUserStmt->bind_param("s", $application['contact_email']);
            $updateUserStmt->execute();
            $updateUserStmt->close();

            $_SESSION['message'] = "Application approved successfully.";
        }
    } elseif ($action === 'Reject') {
        $_SESSION['message'] = "Application rejected successfully.";
    } elseif ($action === 'Delete') {
        // Delete the application
        $deleteAppQuery = "DELETE FROM cooperative_applications WHERE id = ?";
        $deleteAppStmt = $conn->prepare($deleteAppQuery);
        $deleteAppStmt->bind_param("i", $applicationId);
        $deleteAppStmt->execute();
        $deleteAppStmt->close();

        $_SESSION['message'] = "Application deleted successfully.";
    }

    // Update application status
    if ($action !== 'Delete') {
        $updateAppQuery = "UPDATE cooperative_applications SET status = ? WHERE id = ?";
        $updateAppStmt = $conn->prepare($updateAppQuery);
        $updateAppStmt->bind_param("si", $action, $applicationId);
        $updateAppStmt->execute();
        $updateAppStmt->close();
    }

    header("Location: admin_dashboard.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard | Manage Cooperative Applications</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container-fluid">
            <a class="navbar-brand" href="#">Admin Dashboard</a>
            <ul class="navbar-nav ms-auto">
                <li class="nav-item">
                    <a class="nav-link" href="logout.php">Logout</a>
                </li>
            </ul>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="container mt-5">
        <h2 class="text-center mb-4">Manage Cooperative Applications</h2>

        <?php if (isset($_SESSION['message'])): ?>
            <div class="alert alert-info text-center">
                <?= htmlspecialchars($_SESSION['message']); ?>
                <?php unset($_SESSION['message']); ?>
            </div>
        <?php endif; ?>

        <?php if ($applicationsResult && $applicationsResult->num_rows > 0): ?>
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span>Cooperative Applications</span>
                    <span class="badge bg-info">Total: <?= $applicationsResult->num_rows; ?></span>
                </div>
                <div class="card-body">
                    <table class="table table-striped table-hover align-middle">
                        <thead class="table-dark text-center">
                            <tr>
                                <th>#</th>
                                <th>Cooperative Name</th>
                                <th>Registration Number</th>
                                <th>Certificate</th>
                                <th>Contact</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $counter = 1; ?>
                            <?php while ($application = $applicationsResult->fetch_assoc()): ?>
                                <tr class="text-center">
                                    <td><?= $counter++; ?></td>
                                    <td><?= htmlspecialchars($application['cooperative_name']); ?></td>
                                    <td><?= htmlspecialchars($application['registration_number']); ?></td>
                                    <td>
                                        <a href="<?= htmlspecialchars($application['certificate']); ?>" target="_blank" class="btn btn-outline-primary btn-sm">
                                            <i class="bi bi-file-earmark-text"></i> View
                                        </a>
                                    </td>
                                    <td><?= htmlspecialchars($application['contact_email']); ?></td>
                                    <td>
                                        <?php if ($application['status'] === 'Approved'): ?>
                                            <span class="badge bg-success">Approved</span>
                                        <?php elseif ($application['status'] === 'Rejected'): ?>
                                            <span class="badge bg-danger">Rejected</span>
                                        <?php else: ?>
                                            <span class="badge bg-warning text-dark">Pending</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <form action="admin_dashboard.php" method="POST" class="d-inline">
                                            <input type="hidden" name="application_id" value="<?= $application['id']; ?>">
                                            <?php if ($application['status'] === 'Pending'): ?>
                                                <button type="submit" name="action" value="Approve" class="btn btn-success btn-sm">
                                                    <i class="bi bi-check-circle"></i> Approve
                                                </button>
                                                <button type="submit" name="action" value="Reject" class="btn btn-danger btn-sm">
                                                    <i class="bi bi-x-circle"></i> Reject
                                                </button>
                                            <?php endif; ?>
                                            <button type="submit" name="action" value="Delete" class="btn btn-danger btn-sm">
                                                <i class="bi bi-trash"></i> Delete
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php else: ?>
            <div class="alert alert-info text-center">No cooperative applications found.</div>
        <?php endif; ?>
    </div>

    <footer class="footer text-center mt-5">
        &copy; <?= date('Y'); ?> JeepniGo. All rights reserved.
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
