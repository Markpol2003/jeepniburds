<?php
session_start();
require_once __DIR__ . '/../db_config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'operator') {
    echo json_encode([
        'success' => false,
        'message' => 'Unauthorized access'
    ]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid request method'
    ]);
    exit;
}

try {
    // Validate required fields
    $requiredFields = ['assignment_id', 'plate_number', 'body_number', 'route'];
    foreach ($requiredFields as $field) {
        if (empty($_POST[$field])) {
            throw new Exception("Missing required field: $field");
        }
    }

    $assignmentId = $_POST['assignment_id'];
    $plateNumber = $_POST['plate_number'];
    $bodyNumber = $_POST['body_number'];
    $route = $_POST['route'];

    // Check if assignment exists
    $checkQuery = "SELECT id FROM jeepney_assignments WHERE id = ? AND status = 'Active'";
    $checkStmt = $conn->prepare($checkQuery);
    $checkStmt->bind_param("i", $assignmentId);
    $checkStmt->execute();
    $result = $checkStmt->get_result();

    if ($result->num_rows === 0) {
        throw new Exception('Assignment not found or inactive');
    }

    // Check if plate number is already assigned to another jeepney
    $checkPlateQuery = "SELECT id FROM jeepney_assignments WHERE plate_number = ? AND id != ? AND status = 'Active'";
    $checkPlateStmt = $conn->prepare($checkPlateQuery);
    $checkPlateStmt->bind_param("si", $plateNumber, $assignmentId);
    $checkPlateStmt->execute();
    $plateResult = $checkPlateStmt->get_result();

    if ($plateResult->num_rows > 0) {
        throw new Exception('This plate number is already assigned to another driver');
    }

    // Update assignment
    $updateQuery = "UPDATE jeepney_assignments SET plate_number = ?, body_number = ?, route = ? WHERE id = ?";
    $updateStmt = $conn->prepare($updateQuery);
    $updateStmt->bind_param("sssi", $plateNumber, $bodyNumber, $route, $assignmentId);

    if (!$updateStmt->execute()) {
        throw new Exception('Failed to update jeepney assignment');
    }

    echo json_encode([
        'success' => true,
        'message' => 'Jeepney assignment updated successfully'
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?> 