<?php
session_start();
require_once 'db_config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'operator') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit();
}

// Get JSON data
$json = file_get_contents('php://input');
$data = json_decode($json, true);

$assignmentId = $data['assignment_id'] ?? null;

if (!$assignmentId) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Missing assignment ID']);
    exit();
}

try {
    $query = "UPDATE jeepney_assignments SET status = 'Inactive' WHERE id = ? AND status = 'Active'";
    $stmt = $conn->prepare($query);
    
    if (!$stmt) {
        throw new Exception('Database error: ' . $conn->error);
    }
    
    $stmt->bind_param("i", $assignmentId);
    
    if (!$stmt->execute()) {
        throw new Exception('Failed to delete assignment: ' . $stmt->error);
    }
    
    if ($stmt->affected_rows === 0) {
        throw new Exception('No active assignment found with the given ID');
    }
    
    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'message' => 'Assignment deleted successfully']);
    
} catch (Exception $e) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
} 