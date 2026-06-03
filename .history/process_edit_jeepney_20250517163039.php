<?php
session_start();
require_once 'db_config.php';

// Check if user is logged in and is an operator
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'operator') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

// Check if required fields are present
if (!isset($_POST['assignment_id']) || !isset($_POST['plate_number']) || !isset($_POST['body_number']) || !isset($_POST['route'])) {
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit;
}

try {
    // Sanitize inputs
    $assignment_id = (int)$_POST['assignment_id'];
    $plate_number = $conn->real_escape_string($_POST['plate_number']);
    $body_number = $conn->real_escape_string($_POST['body_number']);
    $route = $conn->real_escape_string($_POST['route']);
    $notes = isset($_POST['notes']) ? $conn->real_escape_string($_POST['notes']) : '';

    // Update the jeepney assignment
    $query = "UPDATE jeepney_assignments 
              SET plate_number = ?, 
                  body_number = ?, 
                  route = ?, 
                  notes = ? 
              WHERE id = ? AND status = 'Active'";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ssssi", $plate_number, $body_number, $route, $notes, $assignment_id);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Jeepney assignment updated successfully']);
    } else {
        throw new Exception('Failed to update jeepney assignment');
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?> 