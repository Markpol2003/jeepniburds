<?php
session_start();
require_once 'db_config.php';

// Check if user is logged in and is an operator
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'operator') {
    header('Location: login.php');
    exit;
}

// Get available jeepneys
$jeepneyQuery = "SELECT id, plate_number, model, capacity FROM jeepneys WHERE status = 'Available'";
$jeepneyResult = $conn->query($jeepneyQuery);

// Get operator's information
$operatorId = $_SESSION['user_id'];
$operatorQuery = "SELECT * FROM users WHERE id = ? AND user_type = 'operator'";
$operatorStmt = $conn->prepare($operatorQuery);
$operatorStmt->bind_param("i", $operatorId);
$operatorStmt->execute();
$operator = $operatorStmt->get_result()->fetch_assoc();

// Check if operator already has a jeepney
if ($operator['has_jeepney']) {
    header('Location: operator_dashboard.php');
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Assign Jeepney - Operator Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card shadow-sm animate__animated animate__fadeIn">
                    <div class="card-header bg-primary text-white">
                        <h4 class="mb-0"><i class="bi bi-truck"></i> Assign Jeepney</h4>
                    </div>
                    <div class="card-body">
                        <?php if ($jeepneyResult->num_rows > 0): ?>
                            <form id="assignJeepneyForm" class="needs-validation" novalidate>
                                <div class="mb-3">
                                    <label for="jeepney" class="form-label">Select Jeepney</label>
                                    <select class="form-select" id="jeepney" name="jeepney_id" required>
                                        <option value="">Choose a jeepney...</option>
                                        <?php while ($jeepney = $jeepneyResult->fetch_assoc()): ?>
                                            <option value="<?= $jeepney['id'] ?>">
                                                <?= htmlspecialchars($jeepney['plate_number']) ?> - 
                                                <?= htmlspecialchars($jeepney['model']) ?> 
                                                (Capacity: <?= $jeepney['capacity'] ?>)
                                            </option>
                                        <?php endwhile; ?>
                                    </select>
                                    <div class="invalid-feedback">
                                        Please select a jeepney.
                                    </div>
                                </div>

                                <div class="alert alert-info">
                                    <i class="bi bi-info-circle-fill"></i>
                                    By assigning a jeepney, you agree to:
                                    <ul class="mb-0 mt-2">
                                        <li>Maintain the jeepney in good condition</li>
                                        <li>Follow all traffic rules and regulations</li>
                                        <li>Report any issues or damages immediately</li>
                                    </ul>
                                </div>

                                <div class="d-grid gap-2">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="bi bi-check-circle"></i> Assign Jeepney
                                    </button>
                                    <a href="operator_dashboard.php" class="btn btn-outline-secondary">
                                        <i class="bi bi-arrow-left"></i> Back to Dashboard
                                    </a>
                                </div>
                            </form>
                        <?php else: ?>
                            <div class="text-center py-4">
                                <i class="bi bi-exclamation-circle text-warning" style="font-size: 3rem;"></i>
                                <h5 class="mt-3">No Available Jeepneys</h5>
                                <p class="text-muted">There are currently no jeepneys available for assignment.</p>
                                <a href="operator_dashboard.php" class="btn btn-primary mt-3">
                                    <i class="bi bi-arrow-left"></i> Back to Dashboard
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        document.getElementById('assignJeepneyForm')?.addEventListener('submit', async function(e) {
            e.preventDefault();
            
            if (!this.checkValidity()) {
                e.stopPropagation();
                this.classList.add('was-validated');
                return;
            }

            const jeepneyId = document.getElementById('jeepney').value;

            try {
                const result = await Swal.fire({
                    title: 'Confirm Assignment',
                    text: 'Are you sure you want to assign this jeepney?',
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonText: 'Yes, Assign',
                    cancelButtonText: 'Cancel',
                    confirmButtonColor: '#0d6efd'
                });

                if (result.isConfirmed) {
                    const response = await fetch('process_assign_jeepney.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify({
                            jeepney_id: jeepneyId,
                            driver_id: <?= $operatorId ?>
                        })
                    });

                    const data = await response.json();

                    if (data.success) {
                        await Swal.fire({
                            icon: 'success',
                            title: 'Success!',
                            text: 'Jeepney assigned successfully.',
                            confirmButtonColor: '#198754'
                        });
                        window.location.href = 'operator_dashboard.php';
                    } else {
                        throw new Error(data.message || 'Failed to assign jeepney');
                    }
                }
            } catch (error) {
                console.error('Error:', error);
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: error.message || 'Failed to assign jeepney. Please try again.',
                    confirmButtonColor: '#dc3545'
                });
            }
        });
    </script>
</body>
</html> 