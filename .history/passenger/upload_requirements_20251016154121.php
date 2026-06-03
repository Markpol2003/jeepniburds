<?php
session_start();
require_once __DIR__ . '/../db_config.php';

// Ensure user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../shared/index.php");
    exit();
}

$userId = $_SESSION['user_id'];
$role = $_POST['role'] ?? null;

if (!$role || !in_array($role, ['Driver', 'Operator'])) {
    $errors[] = "Invalid role selected.";
}

$errors = [];
$successMessage = "";

// Configuration
$uploadDir = '../uploads/requirements/';
$allowedFileTypes = ['image/jpeg', 'image/png', 'application/pdf'];
$maxFileSize = 2 * 1024 * 1024; // 2MB

// Ensure upload directory exists and is secure
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0777, true);
    if (!file_exists($uploadDir . '.htaccess')) {
        file_put_contents($uploadDir . '.htaccess', "Options -Indexes\nDeny from all");
    }
}

// Function to upload and validate files
function uploadFile($file, $name, $uploadDir, $allowedFileTypes, $maxFileSize, &$errors) {
    if (!isset($file) || $file['error'] !== 0) {
        $errors[] = "$name: File upload error.";
        return null;
    }

    // Validate MIME type
    $fileMime = mime_content_type($file['tmp_name']);
    if (!in_array($fileMime, $allowedFileTypes)) {
        $errors[] = "$name: Invalid file type. Only PDF, PNG, or JPG allowed.";
        return null;
    }

    // Validate file size
    if ($file['size'] > $maxFileSize) {
        $errors[] = "$name: File exceeds the maximum size of 2MB.";
        return null;
    }

    // Generate secure unique file name
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $fileName = uniqid("req_", true) . "." . $extension;
    $filePath = $uploadDir . $fileName;

    // Attempt file upload
    if (move_uploaded_file($file['tmp_name'], $filePath)) {
        return $filePath;
    } else {
        $errors[] = "Failed to upload $name.";
        return null;
    }
}

// Process file uploads
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $uploads = [];
    $uploads['driver_license'] = uploadFile($_FILES['driver_license'], "Driver's License", $uploadDir, $allowedFileTypes, $maxFileSize, $errors);
    $uploads['cetos_certification'] = uploadFile($_FILES['cetos_certification'], "CETOS Certification", $uploadDir, $allowedFileTypes, $maxFileSize, $errors);
    $uploads['provisional_authorization'] = uploadFile($_FILES['provisional_authorization'], "Provisional Authorization", $uploadDir, $allowedFileTypes, $maxFileSize, $errors);
    $uploads['puv_id'] = uploadFile($_FILES['puv_id'], "PUV ID", $uploadDir, $allowedFileTypes, $maxFileSize, $errors);

    // If all uploads are successful
    if (empty($errors) && !in_array(null, $uploads, true)) {
        // Insert into database using prepared statement
        $stmt = $conn->prepare("INSERT INTO submitted_requirements 
        (user_id, role, driver_license, cetos_certification, provisional_authorization, puv_id, status, submitted_at) 
        VALUES (?, ?, ?, ?, ?, ?, 'Pending', NOW())");
        
        $stmt->bind_param("ssssss", $userId, $role, $uploads['driver_license'], $uploads['cetos_certification'], $uploads['provisional_authorization'], $uploads['puv_id']);
        
        if ($stmt->execute()) {
            $successMessage = "Your requirements have been submitted successfully! They are now pending verification by the manager.";
        } else {
            $errors[] = "Database error: " . $stmt->error;
        }
        $stmt->close();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Upload Status</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
            font-family: Arial, sans-serif;
        }
        .container {
            margin-top: 50px;
        }
    </style>
</head>
<body>
    <div class="container mt-5">
        <h2>Submission Status</h2>

        <!-- Display Success Message -->
        <?php if (!empty($successMessage)): ?>
            <div class="alert alert-success"><?= htmlspecialchars($successMessage); ?></div>
        <?php endif; ?>

        <!-- Display Errors -->
        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger">
                <ul>
                    <?php foreach ($errors as $error): ?>
                        <li><?= htmlspecialchars($error); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <a href="passenger_dashboard.php" class="btn btn-primary mt-3">Back to Dashboard</a>
    </div>
</body>
</html>
