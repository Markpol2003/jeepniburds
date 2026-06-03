<?php
session_start();
require_once 'db_config.php';

// Check if user is logged in and is an operator
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'operator') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

// Get POST data
$data = json_decode(file_get_contents('php://input'), true);
$jeepney_id = $data['jeepney_id'] ?? null;

if (!$jeepney_id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Jeepney ID is required']);
    exit;
}

// Start transaction
$conn->begin_transaction();

try {
    // Check if jeepney is assigned
    $check_assigned = $conn->prepare("SELECT id FROM jeepney_assignments WHERE plate_number = (SELECT plate_number FROM jeepneys WHERE id = ?) AND status = 'Active'");
    $check_assigned->bind_param("i", $jeepney_id);
    $check_assigned->execute();
    $result = $check_assigned->get_result();
    
    if ($result->num_rows > 0) {
        throw new Exception('Cannot delete jeepney that is currently assigned to a driver');
    }

    // Delete the jeepney
    $delete_jeepney = $conn->prepare("DELETE FROM jeepneys WHERE id = ?");
    $delete_jeepney->bind_param("i", $jeepney_id);
    $delete_jeepney->execute();

    $conn->commit();
    echo json_encode(['success' => true, 'message' => 'Jeepney deleted successfully']);
} catch (Exception $e) {
    $conn->rollback();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
} 