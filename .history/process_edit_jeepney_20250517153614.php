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
        // Validate required fields
        $requiredFields = ['driver_id', 'plate_number', 'body_number', 'route'];
        foreach ($requiredFields as $field) {
            if (empty($_POST[$field])) {
                throw new Exception("Missing required field: $field");
            }
        }

        $driverId = $_POST['driver_id'];
        $plateNumber = strtoupper($_POST['plate_number']);
        $bodyNumber = $_POST['body_number'];
        $route = $_POST['route'];
        $notes = $_POST['notes'] ?? '';

        // Update the jeepney assignment
        $stmt = $conn->prepare("
            UPDATE jeepney_assignments 
            SET plate_number = ?, 
                body_number = ?, 
                route = ?, 
                notes = ?,
                assigned_date = CURRENT_TIMESTAMP
            WHERE driver_id = ? AND status = 'Active'
        ");

        $stmt->bind_param("ssssi", $plateNumber, $bodyNumber, $route, $notes, $driverId);
        
        if (!$stmt->execute()) {
            throw new Exception('Failed to update jeepney assignment');
        }

        // Redirect back with success message
        $_SESSION['success_message'] = 'Jeepney assignment updated successfully';
        header('Location: operator_dashboard.php?page=assignjeepney');
        exit();

    } catch (Exception $e) {
        $_SESSION['error_message'] = $e->getMessage();
        header('Location: operator_dashboard.php?page=assignjeepney');
        exit();
    }
} else {
    header('Location: operator_dashboard.php?page=assignjeepney');
    exit();
} 