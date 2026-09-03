<?php
session_start();
require_once __DIR__ . '/../db_config.php';
require_once __DIR__ . '/../includes/security.php';
$userId = jeepnigo_require_role(['passenger', 'driver', 'operator', 'manager', 'admin', 'treasurer']);
jeepnigo_require_csrf();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'User not logged in']);
    exit();
}

// Check if file was uploaded
if (!isset($_FILES['profile_image']) || $_FILES['profile_image']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['success' => false, 'message' => 'No file uploaded or upload error']);
    exit();
}

$file = $_FILES['profile_image'];

// Validate file type
$finfo = new finfo(FILEINFO_MIME_TYPE);
$detectedType = $finfo->file($file['tmp_name']);
$allowedTypes = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif'];
if (!isset($allowedTypes[$detectedType])) {
    echo json_encode(['success' => false, 'message' => 'Invalid file type. Only JPG, PNG, and GIF are allowed.']);
    exit();
}

// Validate file size (max 5MB)
if ($file['size'] > 5 * 1024 * 1024) {
    echo json_encode(['success' => false, 'message' => 'File too large. Maximum size is 5MB.']);
    exit();
}

// Create uploads directory if it doesn't exist
$uploadDir = '../uploads/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

// Generate filename with user ID
$fileExtension = $allowedTypes[$detectedType];
$filename = 'profile_' . $userId . '.' . $fileExtension;
$filepath = $uploadDir . $filename;

// Move uploaded file
if (move_uploaded_file($file['tmp_name'], $filepath)) {
    // Update user's profile image in database (optional)
    $stmt = $conn->prepare("UPDATE users SET profileImage = ? WHERE id = ?");
    $stmt->bind_param("si", $filename, $userId);
    $stmt->execute();
    
    echo json_encode([
        'success' => true, 
        'message' => 'Profile picture uploaded successfully',
        'filename' => $filename
    ]);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to save file']);
}
?>
