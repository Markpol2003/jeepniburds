<?php
session_start();
require_once __DIR__ . '/../db_config.php';

if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit();
}

$response = ['success' => false, 'message' => ''];

try {
    // Handle profile image upload
    if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = "../uploads/";
        $targetFile = $uploadDir . "profile_" . $_SESSION['user_id'] . ".jpg";
        
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
        
        if (move_uploaded_file($_FILES['profile_image']['tmp_name'], $targetFile)) {
            $response = ['success' => true, 'message' => 'Profile image updated successfully'];
        } else {
            throw new Exception("Failed to upload image.");
        }
    }
    
    // Handle profile details update
    if (isset($_POST['firstName']) || isset($_POST['lastName']) || isset($_POST['email'])) {
        $userId = $_SESSION['user_id'];
        $firstName = $_POST['firstName'] ?? '';
        $lastName = $_POST['lastName'] ?? '';
        $email = $_POST['email'] ?? '';
        
        // Validate email
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception("Invalid email format.");
        }
        
        // Update user details
        $stmt = $conn->prepare("UPDATE users SET firstName = ?, lastName = ?, email = ? WHERE id = ?");
        $stmt->bind_param("sssi", $firstName, $lastName, $email, $userId);
        
        if ($stmt->execute()) {
            // Update session variables
            $_SESSION['user_firstName'] = $firstName;
            $_SESSION['user_lastName'] = $lastName;
            $_SESSION['user_email'] = $email;
            
            $response = ['success' => true, 'message' => 'Profile updated successfully'];
        } else {
            throw new Exception("Failed to update profile details.");
        }
        
        $stmt->close();
    }
    
} catch (Exception $e) {
    $response = ['success' => false, 'message' => $e->getMessage()];
}

header('Content-Type: application/json');
echo json_encode($response);
$conn->close();
?>
