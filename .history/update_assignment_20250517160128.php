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
$plateNumber = $_POST['plate_number'] ?? null;
$bodyNumber = $_POST['body_number'] ?? null;
$route = $_POST['route'] ?? null;
$notes = $_POST['notes'] ?? null;

if (!$assignmentId || !$plateNumber || !$bodyNumber || !$route) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit();
}

$query = "UPDATE jeepney_assignments 
          SET plate_number = ?, 
              body_number = ?, 
              route = ?, 
              notes = ? 
          WHERE id = ? AND status = 'Active'";

$stmt = $conn->prepare($query);
$stmt->bind_param("ssssi", $plateNumber, $bodyNumber, $route, $notes, $assignmentId);

if ($stmt->execute()) {
    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'message' => 'Assignment updated successfully']);
} else {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Failed to update assignment']);
} 