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
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_type']) || strtolower($_SESSION['user_type']) !== 'operator') {
    echo json_encode([
        'success' => false, 
        'message' => 'Unauthorized access',
        'debug' => [
            'user_id' => $_SESSION['user_id'] ?? 'not set',
            'user_type' => $_SESSION['user_type'] ?? 'not set'
        ]
    ]);
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

        if (!$stmt) {
            throw new Exception('Database error: ' . $conn->error);
        }

        $stmt->bind_param("i", $driverId);
        
        if (!$stmt->execute()) {
            throw new Exception('Failed to delete jeepney assignment: ' . $stmt->error);
        }

        // Return success response
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
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid request method'
    ]);
} 