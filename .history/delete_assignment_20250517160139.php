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

$assignmentId = $_POST['assignment_id'] ?? null;

if (!$assignmentId) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Missing assignment ID']);
    exit();
}

$query = "UPDATE jeepney_assignments SET status = 'Inactive' WHERE id = ? AND status = 'Active'";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $assignmentId);

if ($stmt->execute()) {
    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'message' => 'Assignment deleted successfully']);
} else {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Failed to delete assignment']);
} 