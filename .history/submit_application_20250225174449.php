<?php
session_start();
require_once 'db_config.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $userId = $_SESSION['user_id'];
    $role = $_POST['role']; // Driver or Operator

    // File uploads (assuming all files are required)
    $driverLicense = $_FILES['driver_license']['name'];
    $cetosCert = $_FILES['cetos_certification']['name'];
    $provAuth = $_FILES['provisional_authorization']['name'];
    $puvId = $_FILES['puv_id']['name'];

    // Save files to a directory
    $targetDir = "uploads/";
    move_uploaded_file($_FILES["driver_license"]["tmp_name"], $targetDir.$driverLicense);
    move_uploaded_file($_FILES["cetos_certification"]["tmp_name"], $targetDir.$cetosCert);
    move_uploaded_file($_FILES["provisional_authorization"]["tmp_name"], $targetDir.$provAuth);
    move_uploaded_file($_FILES["puv_id"]["tmp_name"], $targetDir.$puvId);

    // Insert into submitted_requirements
    $query = "INSERT INTO submitted_requirements (user_id, driver_license, cetos_certification, provisional_authorization, puv_id, role, status, submitted_at)
              VALUES (?, ?, ?, ?, ?, ?, 'Pending', NOW())";

    $stmt = $conn->prepare($query);
    $stmt->bind_param("isssss", $userId, $driverLicense, $cetosCert, $provAuth, $puvId, $role);
    
    if ($stmt->execute()) {
        $_SESSION['message'] = "Application submitted successfully!";
    } else {
        $_SESSION['message'] = "Application submission failed.";
    }

    header("Location: dashboard.php");
    exit();
}
?>
