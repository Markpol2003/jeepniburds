<?php
session_start();
require_once __DIR__ . '/../db_config.php';

// Check if user is logged in and is an operator
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'operator') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

// Get POST data
$data = json_decode(file_get_contents('php://input'), true);
$assignment_id = $data['assignment_id'] ?? null;

if (!$assignment_id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Assignment ID is required']);
    exit;
}

// Start transaction
$conn->begin_transaction();

try {
    // Get the plate number before updating
    $get_plate = $conn->prepare("SELECT plate_number FROM jeepney_assignments WHERE id = ?");
    $get_plate->bind_param("i", $assignment_id);
    $get_plate->execute();
    $result = $get_plate->get_result();
    $assignment = $result->fetch_assoc();

    if (!$assignment) {
        throw new Exception('Assignment not found');
    }

    // Update jeepney status back to Available
    $update_jeepney = $conn->prepare("UPDATE jeepneys SET status = 'Available' WHERE plate_number = ?");
    $update_jeepney->bind_param("s", $assignment['plate_number']);
    $update_jeepney->execute();

    // Update assignment status to Inactive
    $update_assignment = $conn->prepare("UPDATE jeepney_assignments SET status = 'Inactive' WHERE id = ?");
    $update_assignment->bind_param("i", $assignment_id);
    $update_assignment->execute();

    $conn->commit();
    echo json_encode(['success' => true, 'message' => 'Jeepney unassigned successfully']);
} catch (Exception $e) {
    $conn->rollback();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
} 