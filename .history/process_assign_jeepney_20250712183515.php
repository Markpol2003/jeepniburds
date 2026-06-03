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
    $requiredFields = ['driver_id', 'plate_number', 'body_number', 'route'];
    foreach ($requiredFields as $field) {
        if (empty($_POST[$field])) {
            throw new Exception("Missing required field: $field");
        }
    }

    $driverId = $_POST['driver_id'];
    $plateNumber = strtoupper($_POST['plate_number']);
    $bodyNumber = strtoupper($_POST['body_number']);
    $route = $_POST['route'];
    $notes = $_POST['notes'] ?? '';

    // Start transaction
    $conn->begin_transaction();

    // Check if driver already has an active assignment
    $checkQuery = "SELECT id FROM jeepney_assignments WHERE driver_id = ? AND status = 'Active'";
    $checkStmt = $conn->prepare($checkQuery);
    $checkStmt->bind_param("i", $driverId);
    $checkStmt->execute();
    $result = $checkStmt->get_result();

    if ($result->num_rows > 0) {
        throw new Exception('You already have an active jeepney assignment');
    }

    // Check if plate number is already assigned
    $checkPlateQuery = "SELECT id FROM jeepney_assignments WHERE plate_number = ? AND status = 'Active'";
    $checkPlateStmt = $conn->prepare($checkPlateQuery);
    $checkPlateStmt->bind_param("s", $plateNumber);
    $checkPlateStmt->execute();
    $plateResult = $checkPlateStmt->get_result();

    if ($plateResult->num_rows > 0) {
        throw new Exception('This plate number is already assigned to another driver');
    }

    // Check if body number is already assigned
    $checkBodyQuery = "SELECT id FROM jeepney_assignments WHERE body_number = ? AND status = 'Active'";
    $checkBodyStmt = $conn->prepare($checkBodyQuery);
    $checkBodyStmt->bind_param("s", $bodyNumber);
    $checkBodyStmt->execute();
    $bodyResult = $checkBodyStmt->get_result();

    if ($bodyResult->num_rows > 0) {
        throw new Exception('This body number is already assigned to another driver');
    }

    // Insert new assignment
    $insertQuery = "INSERT INTO jeepney_assignments (driver_id, operator_id, plate_number, body_number, route, notes, status) 
                   VALUES (?, ?, ?, ?, ?, ?, 'Active')";
    $insertStmt = $conn->prepare($insertQuery);
    $insertStmt->bind_param("iissss", $driverId, $_SESSION['user_id'], $plateNumber, $bodyNumber, $route, $notes);
    
    if (!$insertStmt->execute()) {
        throw new Exception('Failed to create jeepney assignment');
    }

    // Commit transaction
    $conn->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Jeepney assigned successfully'
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