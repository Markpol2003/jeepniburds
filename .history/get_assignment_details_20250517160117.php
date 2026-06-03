<?php
session_start();
require_once 'db_config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'operator') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

if (!isset($_GET['id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Assignment ID is required']);
    exit();
}

$assignmentId = $_GET['id'];

$query = "SELECT * FROM jeepney_assignments WHERE id = ? AND status = 'Active'";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $assignmentId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $assignment = $result->fetch_assoc();
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'assignment' => $assignment
    ]);
} else {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'Assignment not found'
    ]);
} 