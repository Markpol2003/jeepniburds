<?php
session_start();
require_once 'db_config.php';

// Check if user is logged in and is an operator
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'operator') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Get JSON data
        $json = file_get_contents('php://input');
        $data = json_decode($json, true);

        if (!isset($data['driver_id'])) {
            throw new Exception('Driver ID is required');
        }

        $driverId = $data['driver_id'];

        // Update the jeepney assignment status to Inactive
        $stmt = $conn->prepare("
            UPDATE jeepney_assignments 
            SET status = 'Inactive'
            WHERE driver_id = ? AND status = 'Active'
        ");

        $stmt->bind_param("i", $driverId);
        
        if (!$stmt->execute()) {
            throw new Exception('Failed to delete jeepney assignment');
        }

        // Return success response
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'message' => 'Jeepney assignment deleted successfully'
        ]);

    } catch (Exception $e) {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
    }
} else {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'Invalid request method'
    ]);
} 