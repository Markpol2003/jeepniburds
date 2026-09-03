<?php
session_start();
require_once __DIR__ . '/../db_config.php';
require_once __DIR__ . '/../includes/security.php';
$userId = jeepnigo_require_role(['passenger']);
jeepnigo_require_csrf();

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $role = strtolower((string)($_POST['role'] ?? ''));
    if (!in_array($role, ['driver', 'operator'], true)) {
        jeepnigo_json_error('Invalid application role.', 422);
    }

    // File uploads (assuming all files are required)
    $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'application/pdf' => 'pdf'];
    $targetDir = __DIR__ . '/../uploads/requirements';
    try {
        $driverLicense = 'requirements/' . jeepnigo_safe_upload($_FILES['driver_license'], $targetDir, $allowed, 5 * 1024 * 1024);
        $cetosCert = 'requirements/' . jeepnigo_safe_upload($_FILES['cetos_certification'], $targetDir, $allowed, 5 * 1024 * 1024);
        $provAuth = 'requirements/' . jeepnigo_safe_upload($_FILES['provisional_authorization'], $targetDir, $allowed, 5 * 1024 * 1024);
        $puvId = 'requirements/' . jeepnigo_safe_upload($_FILES['puv_id'], $targetDir, $allowed, 5 * 1024 * 1024);
    } catch (RuntimeException $e) {
        jeepnigo_json_error($e->getMessage(), 422);
    }

    // Insert into submitted_requirements
    $query = "INSERT INTO submitted_requirements (user_id, driver_license, cetos_certification, provisional_authorization, puv_id, role, status, submitted_at)
              VALUES (?, ?, ?, ?, ?, ?, 'Pending', NOW())";

    $stmt = $conn->prepare($query);
    $stmt->bind_param("isssss", $userId, $driverLicense, $cetosCert, $provAuth, $puvId, $role);
    
    if ($stmt->execute()) {
        $_SESSION['message'] = "Application submitted successfully! Your application is now pending for review.";
        header("Location: passenger_dashboard.php");
    } else {
        $_SESSION['message'] = "Application submission failed.";
        header("Location: passenger_dashboard.php");
    }
    
    exit();
}
?>
