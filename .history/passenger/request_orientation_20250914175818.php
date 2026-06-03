<?php
session_start();
require_once __DIR__ . '/../db_config.php';

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: application/json');

// Log the request for debugging
error_log("Orientation request received from user: " . (isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 'not logged in'));

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$userId = $_SESSION['user_id'];

try {
    // Check if request already exists
    $checkStmt = $conn->prepare("SELECT id FROM orientation_requests WHERE user_id = ? AND status = 'Pending'");
    if (!$checkStmt) {
        throw new Exception('Failed to prepare check statement: ' . $conn->error);
    }
    
    $checkStmt->bind_param("i", $userId);
    if (!$checkStmt->execute()) {
        throw new Exception('Failed to execute check statement: ' . $checkStmt->error);
    }
    
    $result = $checkStmt->get_result();
    
    if ($result->num_rows > 0) {
        echo json_encode(['success' => true, 'message' => 'Request already exists']);
        exit;
    }

    // Insert new request
    $stmt = $conn->prepare("INSERT INTO orientation_requests (user_id, status, requested_at) VALUES (?, 'Pending', NOW())");
    if (!$stmt) {
        throw new Exception('Failed to prepare insert statement: ' . $conn->error);
    }
    
    $stmt->bind_param("i", $userId);
    if (!$stmt->execute()) {
        throw new Exception('Failed to execute insert statement: ' . $stmt->error);
    }
    
    echo json_encode(['success' => true, 'message' => 'Request submitted successfully']);
    
} catch (Exception $e) {
    error_log("Error in request_orientation.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'An error occurred: ' . $e->getMessage()]);
}
?> 