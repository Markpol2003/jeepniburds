<?php
session_start();
require_once 'db_config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get form data
    $cooperativeName = $_POST['cooperative_name'];
    $registrationNumber = $_POST['registration_number'];
    $contactInfo = $_POST['contact_info'];
    $userId = $_SESSION['user_id'];
    
    // Handle file upload
    $uploadDir = 'uploads/certificates/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }

    $certificateFile = $uploadDir . basename($_FILES['certificate']['name']);
    if (move_uploaded_file($_FILES['certificate']['tmp_name'], $certificateFile)) {
        // Save data to database
        $stmt = $conn->prepare("
            INSERT INTO cooperative_applications (user_id, cooperative_name, registration_number, certificate, contact_info) 
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->bind_param("issss", $userId, $cooperativeName, $registrationNumber, $certificateFile, $contactInfo);

        if ($stmt->execute()) {
            $_SESSION['message'] = "Application submitted successfully!";
        } else {
            $_SESSION['message'] = "Failed to submit application.";
        }
    } else {
        $_SESSION['message'] = "Failed to upload certificate.";
    }

    header("Location: ../passenger/passenger_dashboard.php?page=apply_cooperative");
    exit();
}
?>
