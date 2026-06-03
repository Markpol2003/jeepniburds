<?php
session_start();
require_once __DIR__ . '/../db_config.php';

// Check if user is logged in and is a manager
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'manager') {
    header("Location: ../shared/index.php");
    exit();
}

$userId = $_SESSION['user_id'];
$response = ['success' => false, 'message' => ''];

try {
    // Handle profile image upload
    if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = __DIR__ . "/../uploads/";
        
        // Create uploads directory if it doesn't exist
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        
        $targetFile = $uploadDir . "profile_" . $userId . ".jpg";
        
        // Validate file type
        $allowedTypes = ['image/jpeg', 'image/png', 'image/jpg'];
        $fileType = $_FILES['profile_image']['type'];
        
        if (!in_array($fileType, $allowedTypes)) {
            throw new Exception("Invalid file type. Only JPG, JPEG, and PNG files are allowed.");
        }
        
        // Validate file size (2MB max)
        if ($_FILES['profile_image']['size'] > 2 * 1024 * 1024) {
            throw new Exception("File size too large. Maximum allowed size is 2MB.");
        }
        
        // Move uploaded file
        if (move_uploaded_file($_FILES['profile_image']['tmp_name'], $targetFile)) {
            $_SESSION['message'] = 'Profile picture updated successfully!';
        } else {
            throw new Exception("Failed to upload image.");
        }
    }
    
    // Handle profile details update
    if (isset($_POST['firstName']) && isset($_POST['lastName']) && isset($_POST['email'])) {
        $firstName = trim($_POST['firstName']);
        $middleName = trim($_POST['middleName'] ?? '');
        $lastName = trim($_POST['lastName']);
        $email = trim($_POST['email']);
        
        // Validate required fields
        if (empty($firstName) || empty($lastName) || empty($email)) {
            throw new Exception("First name, last name, and email are required.");
        }
        
        // Validate email
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception("Invalid email format.");
        }
        
        // Check if email is already taken by another user
        $emailCheckStmt = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $emailCheckStmt->bind_param("si", $email, $userId);
        $emailCheckStmt->execute();
        $emailCheckResult = $emailCheckStmt->get_result();
        
        if ($emailCheckResult->num_rows > 0) {
            throw new Exception("This email is already registered to another account.");
        }
        $emailCheckStmt->close();
        
        // Update user details
        $stmt = $conn->prepare("UPDATE users SET firstName = ?, middleName = ?, lastName = ?, email = ? WHERE id = ?");
        $stmt->bind_param("ssssi", $firstName, $middleName, $lastName, $email, $userId);
        
        if ($stmt->execute()) {
            // Update session variables
            $_SESSION['user_firstName'] = $firstName;
            $_SESSION['user_middleName'] = $middleName;
            $_SESSION['user_lastName'] = $lastName;
            $_SESSION['user_email'] = $email;
            
            $_SESSION['message'] = 'Profile updated successfully!';
        } else {
            throw new Exception("Failed to update profile details.");
        }
        
        $stmt->close();
    }
    
    // Redirect back to dashboard
    header("Location: manager_dashboard.php");
    exit();
    
} catch (Exception $e) {
    $_SESSION['error'] = $e->getMessage();
    header("Location: manager_dashboard.php");
    exit();
}

$conn->close();
?>

