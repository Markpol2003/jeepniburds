<?php
session_start();
require_once __DIR__ . '/../db_config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'manager') {
    header("Location: ../shared/index.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['attendee_id'])) {
    $attendeeId = intval($_POST['attendee_id']);

    $update = $conn->prepare("UPDATE orientation_attendees SET is_completed = 1 WHERE id = ?");
    $update->bind_param("i", $attendeeId);

    if ($update->execute()) {
        $_SESSION['message'] = "Marked as completed!";
    } else {
        $_SESSION['message'] = "Error updating status.";
    }
}

header("Location: attendees_list.php");
exit();
