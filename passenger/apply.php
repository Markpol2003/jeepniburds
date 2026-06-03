<?php
session_start();
require_once __DIR__ . '/../db_config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../shared/index.php");
    exit();
}

$userId = $_SESSION['user_id'];
$role = isset($_POST['role']) ? htmlspecialchars($_POST['role']) : null;

// Fetch the latest posted membership requirements
$requirementsQuery = "SELECT title, membership_requirements, general_requirements, contact_info 
                      FROM membership_requirements 
                      ORDER BY id DESC LIMIT 1";
$result = $conn->query($requirementsQuery);
$requirements = $result->fetch_assoc();

// Process application form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['role'], $_FILES['driver_license'])) {
    $driverLicense = uploadFile($_FILES['driver_license'], 'Driver License');
    $cetosCertification = uploadFile($_FILES['cetos_certification'], 'CETOS Certification');
    $provisionalAuthorization = uploadFile($_FILES['provisional_authorization'], 'Provisional Authorization');
    $puvId = uploadFile($_FILES['puv_id'], 'PUV ID');

    if ($driverLicense && $cetosCertification && $provisionalAuthorization && $puvId) {
        // Insert into the database
        $stmt = $conn->prepare("INSERT INTO submitted_requirements (user_id, role, driver_license, cetos_certification, provisional_authorization, puv_id, status, submitted_at) VALUES (?, ?, ?, ?, ?, ?, 'Pending', NOW())");
        $stmt->bind_param("isssss", $userId, $role, $driverLicense, $cetosCertification, $provisionalAuthorization, $puvId);

        if ($stmt->execute()) {
            $_SESSION['message'] = "Your application for $role has been submitted successfully!";
        } else {
            $error = "Error submitting application: " . $stmt->error;
        }
        $stmt->close();
    } else {
        $error = "All required files must be uploaded.";
    }
}

function uploadFile($file, $name) {
    $uploadDir = 'uploads/requirements/';
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);

    if ($file['error'] === 0) {
        $fileName = time() . "_" . basename($file['name']);
        $filePath = $uploadDir . $fileName;

        if (move_uploaded_file($file['tmp_name'], $filePath)) {
            return $filePath;
        }
    }
    return null;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Apply as Driver/Operator | JeepniGo</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            font-family: 'Poppins', sans-serif;
            background-color: #f4f6f9;
        }
        .container {
            margin-top: 50px;
            max-width: 900px;
        }
        .card {
            border: none;
            border-radius: 12px;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
        }
        .card-header {
            background: linear-gradient(135deg, #2c3e50, #1abc9c);
            color: #fff;
            font-size: 1.5rem;
            font-weight: bold;
            text-align: center;
            border-radius: 12px 12px 0 0;
        }
        
        .form-label {
            font-weight: bold;
        }
        .btn-submit {
            background-color: #28a745;
            color: white;
            font-weight: bold;
        }
        .btn-submit:hover {
            background-color: #218838;
        }
        .btn-back {
            background-color: #6c757d;
            color: white;
        }
        
    </style>
</head>
<body>
    <div class="container">
        <h2 class="mb-4 text-center text-primary">Apply as Driver/Operator</h2>

        <!-- Display Membership Requirements -->
        <?php if ($requirements): ?>
            <div class="card mb-4">
                <div class="card-header"><?= htmlspecialchars($requirements['title']); ?></div>
                <div class="card-body">
                    <h5><b>Membership Requirements</b></h5>
                    <p><?= nl2br(htmlspecialchars($requirements['membership_requirements'])); ?></p>
                    <h5><b>General Requirements</b></h5>
                    <p><?= nl2br(htmlspecialchars($requirements['general_requirements'])); ?></p>
                    <h5><b>Contact Information</b></h5>
                    <p><?= htmlspecialchars($requirements['contact_info']); ?></p>
                </div>
            </div>
        <?php else: ?>
            <div class="alert alert-warning text-center">
                <strong>No membership requirements have been posted yet. Please check back later.</strong>
            </div>
        <?php endif; ?>

        <!-- Application Form with Submit Requirements -->
        <div class="card">
            <div class="card-header">Submit Application and Requirements</div>
            <div class="card-body">
                <?php if (isset($_SESSION['message'])): ?>
                    <div class="alert alert-success"><?= htmlspecialchars($_SESSION['message']); ?></div>
                    <?php unset($_SESSION['message']); ?>
                <?php elseif (isset($error)): ?>
                    <div class="alert alert-danger"><?= htmlspecialchars($error); ?></div>
                <?php endif; ?>
                <form action="apply.php" method="POST" enctype="multipart/form-data">
                    <div class="mb-3">
                        <label for="role" class="form-label">Choose Role:</label>
                        <select name="role" id="role" class="form-select" required>
                            <option value="driver">Driver</option>
                            <option value="operator">Operator</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="driver_license" class="form-label">Driver's License</label>
                        <input type="file" class="form-control" name="driver_license" id="driver_license" required>
                    </div>
                    <div class="mb-3">
                        <label for="cetos_certification" class="form-label">CETOS Certification</label>
                        <input type="file" class="form-control" name="cetos_certification" id="cetos_certification" required>
                    </div>
                    <div class="mb-3">
                        <label for="provisional_authorization" class="form-label">Provisional Authorization</label>
                        <input type="file" class="form-control" name="provisional_authorization" id="provisional_authorization" required>
                    </div>
                    <div class="mb-3">
                        <label for="puv_id" class="form-label">PUV ID</label>
                        <input type="file" class="form-control" name="puv_id" id="puv_id" required>
                    </div>
                    <button type="submit" class="btn btn-submit w-100">Submit Application</button>
                </form>
            </div>
        </div>

        <div class="text-center mt-4">
            <a href="passenger_dashboard.php" class="btn btn-back">Back to Dashboard</a>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
