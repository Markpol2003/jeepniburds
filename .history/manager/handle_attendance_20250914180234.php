<?php
session_start();
require_once __DIR__ . '/../db_config.php';

header('Content-Type: application/json');

// Make sure the user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Not logged in']);
    exit();
}

$userId = $_SESSION['user_id'];

// Handle attendance submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['attend_orientation_ajax'])) {
    $orientationId = intval($_POST['orientation_id']);
    $mode = $_POST['attend_orientation']; // 'online' or 'in-person'

    // Check if user has already attended
    $checkQuery = "SELECT attended_mode FROM orientation_attendees WHERE user_id = ? AND orientation_id = ?";
    $stmt = $conn->prepare($checkQuery);
    $stmt->bind_param("ii", $userId, $orientationId);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        // Insert attendance
        $insertQuery = "INSERT INTO orientation_attendees (user_id, orientation_id, attended_mode, attended_at)
                        VALUES (?, ?, ?, NOW())";
        $insertStmt = $conn->prepare($insertQuery);
        $insertStmt->bind_param("iis", $userId, $orientationId, $mode);
        $insertStmt->execute();

        echo json_encode([
            'status' => 'success',
            'message' => 'Attendance confirmed for ' . $mode
        ]);
    } else {
        $existing = $result->fetch_assoc();
        echo json_encode([
            'status' => 'already_confirmed',
            'current_mode' => $existing['attended_mode']
        ]);
    }
    exit();
}
?>
