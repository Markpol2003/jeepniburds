<?php
session_start();
include('db_config.php');

if (!isset($_SESSION['userId'])) {
    header("Location: landing.php");
    exit();
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $userId = $_SESSION['userId'];
    $updatedFirstName = $_POST['firstName'];
    $updatedMiddleName = $_POST['middleName'];
    $updatedLastName = $_POST['lastName'];
    $updatedEmail = $_POST['email'];

    // Handle profile image upload
    $uploadDir = "uploads/";
    $targetFile = $uploadDir . "profile_" . $userId . ".jpg";
    if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] === UPLOAD_ERR_OK) {
        move_uploaded_file($_FILES['profile_image']['tmp_name'], $targetFile);
    }

    // Update the user details
    $stmt = $conn->prepare("UPDATE users SET firstName = ?, middleName = ?, lastName = ?, email = ? WHERE id = ?");
    $stmt->bind_param("ssssi", $updatedFirstName, $updatedMiddleName, $updatedLastName, $updatedEmail, $userId);

    if ($stmt->execute()) {
        header("Location: manager_dashboard.php?update=success");
        exit();
    } else {
        echo "Error: " . $stmt->error;
    }

    $stmt->close();
}

$conn->close();
?>
