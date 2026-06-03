<?php
session_start();
require_once 'db_config.php';

// Check if user is logged in and is an operator
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'operator') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

// Get JSON data from request
$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['driver_id']) || !isset($data['jeepney_id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit;
}

$driverId = $data['driver_id'];
$jeepneyId = $data['jeepney_id'];

try {
    // Start transaction
    $conn->begin_transaction();

    // Check if jeepney is still available
    $checkQuery = "SELECT status FROM jeepneys WHERE id = ? AND status = 'Available'";
    $checkStmt = $conn->prepare($checkQuery);
    $checkStmt->bind_param("i", $jeepneyId);
    $checkStmt->execute();
    $result = $checkStmt->get_result();

    if ($result->num_rows === 0) {
        throw new Exception('Jeepney is no longer available');
    }

    // Update jeepney status and assign to driver
    $updateQuery = "UPDATE jeepneys SET status = 'Assigned', driver_id = ? WHERE id = ?";
    $updateStmt = $conn->prepare($updateQuery);
    $updateStmt->bind_param("ii", $driverId, $jeepneyId);
    
    if (!$updateStmt->execute()) {
        throw new Exception('Failed to assign jeepney');
    }

    // Update driver's status to indicate they have been assigned a jeepney
    $updateDriverQuery = "UPDATE users SET has_jeepney = 1 WHERE id = ?";
    $updateDriverStmt = $conn->prepare($updateDriverQuery);
    $updateDriverStmt->bind_param("i", $driverId);
    
    if (!$updateDriverStmt->execute()) {
        throw new Exception('Failed to update driver status');
    }

    // Commit transaction
    $conn->commit();

    echo json_encode(['success' => true, 'message' => 'Jeepney assigned successfully']);

} catch (Exception $e) {
    // Rollback transaction on error
    $conn->rollback();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?> 