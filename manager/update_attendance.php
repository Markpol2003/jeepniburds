<?php
session_start();
require_once __DIR__ . '/../db_config.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(401); // Unauthorized
    exit();
}

$userId = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $orientationId = intval($_POST['orientation_id']);
    $newMode = $_POST['new_mode']; // 'online' or 'in-person'

    $updateQuery = "UPDATE orientation_attendees 
                    SET attended_mode = ?, attended_at = NOW()
                    WHERE user_id = ? AND orientation_id = ?";
    $stmt = $conn->prepare($updateQuery);
    $stmt->bind_param("sii", $newMode, $userId, $orientationId);
    $stmt->execute();
    $stmt->close();
}
?>
