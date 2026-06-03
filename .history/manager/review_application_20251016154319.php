<?php
session_start();
require_once __DIR__ . '/../db_config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Ensure the user is logged in and is a manager
    if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'manager') {
        header("Location: ../index.php?error=Unauthorized access.");
        exit();
    }

    $managerId = $_SESSION['user_id'];
    $applicationId = intval($_POST['application_id']);
    $action = $_POST['action']; // 'Approve' or 'Reject'

    if (!in_array($action, ['Approve', 'Reject'])) {
        header("Location: manager_dashboard.php?error=Invalid action.");
        exit();
    }

    // Fetch application details
    $stmt = $conn->prepare("SELECT user_id, role FROM applications WHERE id = ?");
    $stmt->bind_param("i", $applicationId);
    $stmt->execute();
    $result = $stmt->get_result();
    $application = $result->fetch_assoc();

    if (!$application) {
        header("Location: manager_dashboard.php?error=Application not found.");
        exit();
    }

    $userId = $application['user_id'];
    $role = $application['role']; // 'driver' or 'operator'

    // Approve or reject the application
    if ($action === 'Approve') {
        // Update the user's role in the users table
        $updateUserRole = $conn->prepare("UPDATE users SET userType = ? WHERE id = ?");
        $updateUserRole->bind_param("si", $role, $userId);

        if ($updateUserRole->execute()) {
            // Update application status
            $updateApplication = $conn->prepare("UPDATE applications SET status = 'Approved', manager_id = ?, reviewed_date = NOW() WHERE id = ?");
            $updateApplication->bind_param("ii", $managerId, $applicationId);
            $updateApplication->execute();

            header("Location: manager_dashboard.php?success=Application approved successfully.");
        } else {
            header("Location: manager_dashboard.php?error=Error updating user role.");
        }
    } elseif ($action === 'Reject') {
        // Reject the application
        $updateApplication = $conn->prepare("UPDATE applications SET status = 'Rejected', manager_id = ?, reviewed_date = NOW() WHERE id = ?");
        $updateApplication->bind_param("ii", $managerId, $applicationId);
        if ($updateApplication->execute()) {
            header("Location: manager_dashboard.php?success=Application rejected successfully.");
        } else {
            header("Location: manager_dashboard.php?error=Error rejecting application.");
        }
    }
}
?>
