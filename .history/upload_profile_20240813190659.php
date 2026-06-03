<?php
session_start();
include('db_config.php');

if (!isset($_SESSION['userId'])) {
    header("Location: landing.php");
    exit();
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $userId = $_SESSION['userId'];

    if (isset($_FILES['profilePhoto']) && $_FILES['profilePhoto']['error'] == UPLOAD_ERR_OK) {
        $fileTmpPath = $_FILES['profilePhoto']['tmp_name'];
        $fileName = $_FILES['profilePhoto']['name'];
        $fileSize = $_FILES['profilePhoto']['size'];
        $fileType = $_FILES['profilePhoto']['type'];
        $fileNameCmps = explode(".", $fileName);
        $fileExtension = strtolower(end($fileNameCmps));

        // Check if the file is an image
        $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif'];
        if (in_array($fileExtension, $allowedExtensions)) {
            $newFileName = md5(time() . $fileName) . '.' . $fileExtension;
            $uploadFileDir = 'uploads/';
            $destFilePath = $uploadFileDir . $newFileName;

            if (move_uploaded_file($fileTmpPath, $destFilePath)) {
                // Update profile photo in the database
                $stmt = $conn->prepare("UPDATE users SET photo = ? WHERE id = ?");
                $stmt->bind_param("si", $destFilePath, $userId);

                if ($stmt->execute()) {
                    // Upload successful
                    header("Location: admin.php?upload=success");
                    exit();
                } else {
                    // Upload failed
                    echo "Error: " . $stmt->error;
                }

                $stmt->close();
            } else {
                echo "There was an error moving the uploaded file.";
            }
        } else {
            echo "Invalid file type. Only JPG, JPEG, PNG, and GIF are allowed.";
        }
    } else {
        echo "No file uploaded or there was an upload error.";
    }
}

$conn->close();
?>
