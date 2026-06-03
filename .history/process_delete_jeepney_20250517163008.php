<?php
// Prevent any output before JSON response
error_reporting(0);
ini_set('display_errors', 0);

session_start();
require_once 'db_config.php';

// Set JSON header
header('Content-Type: application/json');

// Debug session variables
error_log('Session variables: ' . print_r($_SESSION, true));

// Check if user is logged in and is an operator
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'operator') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

// Check if assignment_id is present
if (!isset($_POST['assignment_id'])) {
    echo json_encode(['success' => false, 'message' => 'Missing assignment ID']);
    exit;
}

try {
    // Sanitize input
    $assignment_id = (int)$_POST['assignment_id'];

    // Update the jeepney assignment status to Inactive
    $query = "UPDATE jeepney_assignments SET status = 'Inactive' WHERE id = ? AND status = 'Active'";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $assignment_id);
    
    if ($stmt->execute()) {
        if ($stmt->affected_rows > 0) {
            echo json_encode(['success' => true, 'message' => 'Jeepney assignment deleted successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'No assignment found or already deleted']);
        }
    } else {
        throw new Exception('Failed to delete jeepney assignment');
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
} 