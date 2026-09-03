<?php
session_start();
require_once __DIR__ . '/../db_config.php';
require_once __DIR__ . '/../includes/security.php';
jeepnigo_require_role(['passenger']);
jeepnigo_require_csrf();

// Ensure user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../index.php');
    exit();
}

// Validate request method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: passenger_dashboard.php?page=apply_cooperative');
    exit();
}

$userId = (int)($_SESSION['user_id'] ?? 0);
$cooperativeName = trim($_POST['cooperative_name'] ?? '');
$registrationNumber = trim($_POST['registration_number'] ?? '');
$contactEmail = trim($_POST['contact_info'] ?? ''); // field name from form; must be an email

// Basic validation
if ($cooperativeName === '' || $registrationNumber === '' || $contactEmail === '' || empty($_FILES['certificate'])) {
    $_SESSION['message'] = 'Please complete all fields and attach the certificate.';
    header('Location: passenger_dashboard.php?page=apply_cooperative');
    exit();
}

// Email validation
if (!filter_var($contactEmail, FILTER_VALIDATE_EMAIL)) {
    $_SESSION['message'] = 'Please enter a valid email in Contact Information.';
    header('Location: passenger_dashboard.php?page=apply_cooperative');
    exit();
}

// Ensure table exists
$createTableSql = "CREATE TABLE IF NOT EXISTS cooperative_applications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    cooperative_name VARCHAR(255) NOT NULL,
    registration_number VARCHAR(255) NOT NULL,
    certificate VARCHAR(255) NOT NULL,
    contact_email VARCHAR(255) NOT NULL,
    status VARCHAR(50) NOT NULL DEFAULT 'Pending',
    submitted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
$conn->query($createTableSql);

// Handle file upload
$uploadDir = realpath(__DIR__ . '/../uploads');
if ($uploadDir === false) {
    // Fallback create uploads directory
    $uploadDir = __DIR__ . '/../uploads';
    if (!is_dir($uploadDir)) {
        @mkdir($uploadDir, 0775, true);
    }
}

$originalName = $_FILES['certificate']['name'] ?? '';
$tmpPath = $_FILES['certificate']['tmp_name'] ?? '';
$errorCode = $_FILES['certificate']['error'] ?? UPLOAD_ERR_NO_FILE;

if ($errorCode !== UPLOAD_ERR_OK || !is_uploaded_file($tmpPath)) {
    $_SESSION['message'] = 'Failed to upload certificate.';
    header('Location: passenger_dashboard.php?page=apply_cooperative');
    exit();
}

$ext = pathinfo($originalName, PATHINFO_EXTENSION);
$safeExt = preg_replace('/[^a-zA-Z0-9]/', '', $ext);
$fileName = 'coop_cert_' . $userId . '_' . time() . ($safeExt ? ('.' . $safeExt) : '');
$destFsPath = rtrim((string)$uploadDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $fileName;
$destRelPath = '../uploads/' . $fileName; // path stored in DB for web access from manager/admin

if (!@move_uploaded_file($tmpPath, $destFsPath)) {
    $_SESSION['message'] = 'Could not save uploaded certificate.';
    header('Location: passenger_dashboard.php?page=apply_cooperative');
    exit();
}

// Insert application
$insert = $conn->prepare('INSERT INTO cooperative_applications (user_id, cooperative_name, registration_number, certificate, contact_email) VALUES (?, ?, ?, ?, ?)');
$insert->bind_param('issss', $userId, $cooperativeName, $registrationNumber, $destRelPath, $contactEmail);
if ($insert->execute()) {
    $_SESSION['message'] = 'Cooperative application submitted successfully. You will be notified after review.';
} else {
    $_SESSION['message'] = 'Failed to submit application: ' . $conn->error;
}
$insert->close();

header('Location: passenger_dashboard.php?page=apply_cooperative');
exit();
?>

