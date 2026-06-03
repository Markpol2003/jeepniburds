<?php
session_start();
require_once 'db_config.php';

// Check if user is logged in and is an operator
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'operator') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
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
    $plateNumber = strtoupper($_POST['plate_number']);
    $bodyNumber = strtoupper($_POST['body_number']);
    $route = $_POST['route'];
    $notes = $_POST['notes'] ?? '';

    // Start transaction
    $conn->begin_transaction();

    // Check if plate number is already assigned to another jeepney
    $checkPlateQuery = "SELECT id FROM jeepney_assignments WHERE plate_number = ? AND id != ? AND status = 'Active'";
    $checkPlateStmt = $conn->prepare($checkPlateQuery);
    $checkPlateStmt->bind_param("si", $plateNumber, $assignmentId);
    $checkPlateStmt->execute();
    $plateResult = $checkPlateStmt->get_result();

    if ($plateResult->num_rows > 0) {
        throw new Exception('This plate number is already assigned to another jeepney');
    }

    // Check if body number is already assigned to another jeepney
    $checkBodyQuery = "SELECT id FROM jeepney_assignments WHERE body_number = ? AND id != ? AND status = 'Active'";
    $checkBodyStmt = $conn->prepare($checkBodyQuery);
    $checkBodyStmt->bind_param("si", $bodyNumber, $assignmentId);
    $checkBodyStmt->execute();
    $bodyResult = $checkBodyStmt->get_result();

    if ($bodyResult->num_rows > 0) {
        throw new Exception('This body number is already assigned to another jeepney');
    }

    // Update assignment
    $updateQuery = "UPDATE jeepney_assignments 
                   SET plate_number = ?, body_number = ?, route = ?, notes = ? 
                   WHERE id = ? AND status = 'Active'";
    $updateStmt = $conn->prepare($updateQuery);
    $updateStmt->bind_param("ssssi", $plateNumber, $bodyNumber, $route, $notes, $assignmentId);
    
    if (!$updateStmt->execute()) {
        throw new Exception('Failed to update jeepney assignment');
    }

    // Commit transaction
    $conn->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Jeepney assignment updated successfully'
    ]);

} catch (Exception $e) {
    // Rollback transaction on error
    $conn->rollback();
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

$conn->close();
?> 