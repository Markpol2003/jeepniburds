<?php
session_start();
require_once __DIR__ . '/../db_config.php';

// Check if user is logged in and is an operator
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'operator') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

if (!isset($_GET['assignment_id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Assignment ID is required']);
    exit;
}

try {
    $assignmentId = $_GET['assignment_id'];
    
    // Get assignment data
    $query = "SELECT ja.*, u.firstName, u.lastName 
              FROM jeepney_assignments ja 
              JOIN users u ON ja.driver_id = u.id 
              WHERE ja.id = ? AND ja.status = 'Active'";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $assignmentId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        throw new Exception('Assignment not found');
    }
    
    $assignment = $result->fetch_assoc();
    
    echo json_encode([
        'success' => true,
        'data' => [
            'assignment_id' => $assignment['id'],
            'driver_id' => $assignment['driver_id'],
            'driver_name' => $assignment['firstName'] . ' ' . $assignment['lastName'],
            'plate_number' => $assignment['plate_number'],
            'body_number' => $assignment['body_number'],
            'route' => $assignment['route'],
            'notes' => $assignment['notes']
        ]
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

$conn->close();
?> 