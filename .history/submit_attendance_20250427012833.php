<?php
session_start();
require_once 'db_config.php';

header('Content-Type: application/json');

// Log POST parameters for debugging
error_log("submit_attendance.php - POST parameters: " . json_encode($_POST));

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['orientation_id']) && isset($_POST['user_id']) && isset($_POST['attended_mode'])) {
    $orientationId = $_POST['orientation_id'];
    $userId = $_POST['user_id'];
    $attendedMode = $_POST['attended_mode'];

    // Log the received data
    error_log("Received data: orientationId=$orientationId, userId=$userId, attendedMode=$attendedMode");

    // Check if the user already has a record for this orientation
    $checkQuery = "SELECT * FROM orientation_attendees WHERE orientation_id = ? AND user_id = ?";
    $checkStmt = $conn->prepare($checkQuery);
    $checkStmt->bind_param("ii", $orientationId, $userId);
    $checkStmt->execute();
    $checkResult = $checkStmt->get_result();

    if ($checkResult->num_rows > 0) {
        // Update existing record
        $updateQuery = "UPDATE orientation_attendees SET attended_mode = ? WHERE orientation_id = ? AND user_id = ?";
        $updateStmt = $conn->prepare($updateQuery);
        $updateStmt->bind_param("sii", $attendedMode, $orientationId, $userId);

        if ($updateStmt->execute()) {
            // Log success message
            error_log("Attendance updated successfully.");
            echo json_encode(["success" => true, "message" => "Attendance updated successfully."]);
        } else {
            // Log error message
            error_log("Failed to update attendance: " . $updateStmt->error);
            echo json_encode(["success" => false, "message" => "Failed to update attendance."]);
        }
    } else {
        // Insert new record
        $insertQuery = "INSERT INTO orientation_attendees (orientation_id, user_id, attended_mode) VALUES (?, ?, ?)";
        $insertStmt = $conn->prepare($insertQuery);
        $insertStmt->bind_param("iis", $orientationId, $userId, $attendedMode);

        if ($insertStmt->execute()) {
            // Log success message
            error_log("Attendance recorded successfully.");
            echo json_encode(["success" => true, "message" => "Attendance recorded successfully."]);
        } else {
            // Log error message
            error_log("Failed to record attendance: " . $insertStmt->error);
            echo json_encode(["success" => false, "message" => "Failed to record attendance."]);
        }
    }
    $conn->close();
} else {
    // Log invalid request message
    error_log("Invalid request - Missing parameters.");
    echo json_encode(["success" => false, "message" => "Invalid request."]);
}
?>
