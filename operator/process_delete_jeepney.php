<?php
// Prevent any output before JSON response
error_reporting(0);
ini_set('display_errors', 0);

session_start();
require_once __DIR__ . '/../db_config.php';

// Set JSON header
header('Content-Type: application/json');

// Debug session variables
error_log('Session variables: ' . print_r($_SESSION, true));

// Check if user is logged in and is an operator
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
    if (empty($_POST['assignment_id'])) {
        throw new Exception('Missing assignment ID');
    }

    $assignmentId = $_POST['assignment_id'];

    // Check if assignment exists and is active
    $checkQuery = "SELECT id FROM jeepney_assignments WHERE id = ? AND status = 'Active'";
    $checkStmt = $conn->prepare($checkQuery);
    $checkStmt->bind_param("i", $assignmentId);
    $checkStmt->execute();
    $result = $checkStmt->get_result();

    if ($result->num_rows === 0) {
        throw new Exception('Assignment not found or already inactive');
    }

    // Soft delete by updating status to 'Inactive'
    $updateQuery = "UPDATE jeepney_assignments SET status = 'Inactive' WHERE id = ?";
    $updateStmt = $conn->prepare($updateQuery);
    $updateStmt->bind_param("i", $assignmentId);

    if (!$updateStmt->execute()) {
        throw new Exception('Failed to delete jeepney assignment');
    }

    echo json_encode([
        'success' => true,
        'message' => 'Jeepney assignment deleted successfully'
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
} 