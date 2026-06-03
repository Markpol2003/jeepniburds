<?php
session_start();
require_once __DIR__ . '/../db_config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submission_id'], $_POST['status'])) {
    $submissionId = intval($_POST['submission_id']);
    $status = $_POST['status'];

    // Fetch user_id and role from the submitted requirements table
    $query = "SELECT user_id, role FROM submitted_requirements WHERE id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $submissionId);
    $stmt->execute();
    $result = $stmt->get_result();
    $submission = $result->fetch_assoc();

    if ($submission) {
        $userId = $submission['user_id'];
        $newRole = $submission['role']; // Fetch the role dynamically from submitted_requirements

        if ($status === 'Verified') {
            // Update the user's role in the users table
            $updateUserQuery = "UPDATE users SET userType = ? WHERE id = ?";
            $updateStmt = $conn->prepare($updateUserQuery);
            $updateStmt->bind_param("si", $newRole, $userId);
            if ($updateStmt->execute()) {
                $_SESSION['message'] = "User role updated to $newRole.";
            } else {
                $_SESSION['message'] = "Failed to update user role.";
            }
        }

        // Update the status in the submitted requirements table
        $updateStatusQuery = "UPDATE submitted_requirements SET status = ? WHERE id = ?";
        $updateStatusStmt = $conn->prepare($updateStatusQuery);
        $updateStatusStmt->bind_param("si", $status, $submissionId);
        $updateStatusStmt->execute();

        $_SESSION['message'] = "Application status updated successfully.";
    } else {
        $_SESSION['message'] = "Submission not found.";
    }

    header("Location: verify_applications.php");
    exit();
}
?>
