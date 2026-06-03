<?php
session_start();
require_once 'db_config.php';

// Ensure admin is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'admin') {
    header("Location: index.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $applicationId = intval($_POST['application_id']);
    $status = htmlspecialchars($_POST['status']);

    $stmt = $conn->prepare("UPDATE cooperative_applications SET status = ? WHERE id = ?");
    $stmt->bind_param("si", $status, $applicationId);

    if ($stmt->execute()) {
        $_SESSION['message'] = "Application status updated to $status!";
    } else {
        $_SESSION['message'] = "Error updating status: " . $stmt->error;
    }
    header("Location: admin_dashboard.php?page=view_cooperative_applications");
    exit();
}
?>

