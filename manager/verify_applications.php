<?php
session_start();
require_once __DIR__ . '/../db_config.php';

// Ensure manager is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'manager') {
    header("Location: ../index.php");
    exit();
}

// Fetch pending submissions
$query = "SELECT sr.id, sr.driver_license, sr.cetos_certification, sr.provisional_authorization, sr.puv_id, sr.role, sr.status, u.firstName, u.lastName, u.id AS user_id
          FROM submitted_requirements sr
          JOIN users u ON sr.user_id = u.id
          WHERE sr.status = 'Pending'
          ORDER BY sr.submitted_at ASC";
$result = $conn->query($query);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $submissionId = intval($_POST['submission_id']);
    $status = $_POST['status']; // Verified or Rejected

    // Fetch user_id and role from the submission
    $fetchQuery = "SELECT user_id, role FROM submitted_requirements WHERE id = ?";
    $stmt = $conn->prepare($fetchQuery);
    $stmt->bind_param("i", $submissionId);
    $stmt->execute();
    $result = $stmt->get_result();
    $submissionData = $result->fetch_assoc();

    if ($submissionData) {
        $userId = $submissionData['user_id'];
        $newRole = $submissionData['role']; // Role: Driver or Operator

        // Update submission status
        $updateSubmissionQuery = "UPDATE submitted_requirements SET status = ? WHERE id = ?";
        $stmt = $conn->prepare($updateSubmissionQuery);
        $stmt->bind_param("si", $status, $submissionId);
        
        if ($stmt->execute()) {
            // If Verified, update userType in users table
            if ($status === 'Verified' && !empty($newRole)) {
                $normalizedRole = strtolower($newRole); // Converts "Driver" to "driver"
        
                $updateUserTypeQuery = "UPDATE users SET userType = ? WHERE id = ?";
                $updateStmt = $conn->prepare($updateUserTypeQuery);
                $updateStmt->bind_param("si", $normalizedRole, $userId);
                $updateStmt->execute();
            }
        
            // Only set message if NOT verified
            if ($status !== 'Verified') {
                $_SESSION['message'] = "Submission ID $submissionId has been successfully updated to $status.";
            }
        } else {
            $_SESSION['message'] = "Failed to update submission ID $submissionId.";
        }
        
    } else {
        $_SESSION['message'] = "Invalid submission ID.";
    }

    header("Location: verify_applications.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify Applications | Manager Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <style>
        body {
            background-color: #f4f6f9;
            font-family: 'Poppins', sans-serif;
        }
        .container {
            max-width: 1100px;
            padding: 30px;
            background: #fff;
            margin: 40px auto;
            border-radius: 16px;
            box-shadow: 0 15px 25px rgba(0, 0, 0, 0.05);
        }
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
        }
        .btn-back {
            font-weight: 600;
            border-radius: 8px;
        }
        h2 {
            font-weight: 700;
            color: #2c3e50;
        }
        .table th {
            background-color: #007bff;
            color: #fff;
            text-align: center;
        }
        .table td {
            text-align: center;
            vertical-align: middle;
        }
        .btn-sm {
            font-size: 0.85rem;
            padding: 5px 12px;
        }
        .btn-success {
            background-color: #28a745;
            border: none;
        }
        .btn-danger {
            background-color: #dc3545;
            border: none;
        }
        footer {
            text-align: center;
            margin-top: 30px;
            color: #888;
            font-size: 0.9rem;
        }
    </style>
</head>
<body>

<div class="container">
    <div class="header">
        <h2>📄 Verify Submitted Applications</h2>
        <a href="manager_dashboard.php" class="btn btn-outline-primary btn-back">← Back to Dashboard</a>
    </div>

    <?php if (isset($_SESSION['message'])): ?>
        <script>
            Swal.fire({
                icon: 'success',
                title: 'Updated!',
                text: '<?= addslashes($_SESSION['message']); ?>',
                confirmButtonColor: '#3085d6'
            });
        </script>
        <?php unset($_SESSION['message']); ?>
    <?php endif; ?>

    <div class="table-responsive">
        <?php if ($result && $result->num_rows > 0): ?>
            <table class="table table-hover table-bordered rounded">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Name</th>
                        <th>Driver’s License</th>
                        <th>CETOS Certification</th>
                        <th>Provisional Authorization</th>
                        <th>PUV ID</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                <?php $counter = 1; while ($row = $result->fetch_assoc()): ?>
    <tr>
        <td><?= $counter++; ?></td>
        <td><?= htmlspecialchars($row['firstName'] . ' ' . $row['lastName']); ?></td>

        <!-- View Buttons triggering modals -->
        <td><button class="btn btn-link text-primary" data-bs-toggle="modal" data-bs-target="#modal_license_<?= $row['id']; ?>">View</button></td>
        <td><button class="btn btn-link text-primary" data-bs-toggle="modal" data-bs-target="#modal_cetos_<?= $row['id']; ?>">View</button></td>
        <td><button class="btn btn-link text-primary" data-bs-toggle="modal" data-bs-target="#modal_prov_<?= $row['id']; ?>">View</button></td>
        <td><button class="btn btn-link text-primary" data-bs-toggle="modal" data-bs-target="#modal_puv_<?= $row['id']; ?>">View</button></td>

        <td>
            <form action="verify_applications.php" method="POST">
                <input type="hidden" name="submission_id" value="<?= $row['id']; ?>">
                <button type="submit" name="status" value="Verified" class="btn btn-success btn-sm">✅ Verify</button>
                <button type="submit" name="status" value="Rejected" class="btn btn-danger btn-sm">❌ Reject</button>
            </form>
        </td>
    </tr>

    <!-- File Modals -->
    <?php
        $files = [
            'license' => 'Driver’s License',
            'cetos' => 'CETOS Certification',
            'prov' => 'Provisional Authorization',
            'puv' => 'PUV ID'
        ];
        foreach ($files as $key => $label):
            $fileField = [
                'license' => 'driver_license',
                'cetos' => 'cetos_certification',
                'prov' => 'provisional_authorization',
                'puv' => 'puv_id'
            ][$key];
            $filePath = 'uploads/' . htmlspecialchars($row[$fileField]);
            $modalId = "modal_{$key}_{$row['id']}";
    ?>
        <div class="modal fade" id="<?= $modalId; ?>" tabindex="-1" aria-labelledby="<?= $modalId; ?>Label" aria-hidden="true">
            <div class="modal-dialog modal-lg modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title"><?= $label; ?> Preview</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body text-center">
                        <?php if (preg_match('/\.(jpg|jpeg|png|gif)$/i', $filePath)): ?>
                            <img src="<?= $filePath; ?>" alt="<?= $label; ?>" class="img-fluid rounded shadow-sm">
                        <?php elseif (preg_match('/\.pdf$/i', $filePath)): ?>
                            <iframe src="<?= $filePath; ?>" width="100%" height="500px"></iframe>
                        <?php else: ?>
                            <p>Cannot preview this file type.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
<?php endwhile; ?>

                </tbody>
            </table>
        <?php else: ?>
            <div class="alert alert-info text-center">No pending submissions.</div>
        <?php endif; ?>
    </div>

    <footer>&copy; <?= date('Y'); ?> JeepniGo. All rights reserved.</footer>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>
