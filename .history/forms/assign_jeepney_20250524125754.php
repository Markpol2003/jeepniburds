<?php
session_start();
require_once '../db_config.php';

// Check if user is logged in and is an operator
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'operator') {
    header('Location: ../login.php');
    exit;
}

// Get operator's information
$operatorId = $_SESSION['user_id'];
$operatorQuery = "SELECT * FROM users WHERE id = ? AND user_type = 'operator'";
$operatorStmt = $conn->prepare($operatorQuery);
$operatorStmt->bind_param("i", $operatorId);
$operatorStmt->execute();
$operator = $operatorStmt->get_result()->fetch_assoc();

// Check if operator already has a jeepney
$checkAssignmentQuery = "SELECT * FROM jeepney_assignments WHERE driver_id = ? AND status = 'Active'";
$checkAssignmentStmt = $conn->prepare($checkAssignmentQuery);
$checkAssignmentStmt->bind_param("i", $operatorId);
$checkAssignmentStmt->execute();
$existingAssignment = $checkAssignmentStmt->get_result()->fetch_assoc();

if ($existingAssignment) {
    header('Location: ../operator_dashboard.php');
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
    <style>
        .form-container {
            max-width: 800px;
            margin: 0 auto;
        }
        .card {
            border: none;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
        }
        .card-header {
            background: linear-gradient(135deg, #0d6efd 0%, #0a58ca 100%);
            color: white;
            border: none;
        }
        .form-label {
            font-weight: 500;
            color: #444;
        }
        .required-field::after {
            content: " *";
            color: red;
        }
        .terms-box {
            background-color: #f8f9fa;
            border-radius: 8px;
            padding: 15px;
            margin: 20px 0;
        }
    </style>
</head>
<body class="bg-light">
    <div class="container py-5">
        <div class="form-container">
            <div class="card animate__animated animate__fadeIn">
                <div class="card-header">
                    <h4 class="mb-0"><i class="bi bi-truck"></i> Assign Jeepney</h4>
                </div>
                <div class="card-body p-4">
                    <form id="assignJeepneyForm" class="needs-validation" novalidate>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="plate_number" class="form-label required-field">Plate Number</label>
                                <input type="text" class="form-control" id="plate_number" name="plate_number" required
                                       pattern="[A-Z0-9-]+" placeholder="e.g., ABC-123">
                                <div class="invalid-feedback">
                                    Please enter a valid plate number.
                                </div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="body_number" class="form-label required-field">Body Number</label>
                                <input type="text" class="form-control" id="body_number" name="body_number" required
                                       pattern="[A-Z0-9-]+" placeholder="e.g., B123">
                                <div class="invalid-feedback">
                                    Please enter a valid body number.
                                </div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="route" class="form-label required-field">Route</label>
                            <input type="text" class="form-control" id="route" name="route" required
                                   placeholder="e.g., SM North - Monumento">
                            <div class="invalid-feedback">
                                Please enter your jeepney route.
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="notes" class="form-label">Additional Notes</label>
                            <textarea class="form-control" id="notes" name="notes" rows="3"
                                      placeholder="Any additional information about your jeepney..."></textarea>
                        </div>

                        <div class="terms-box">
                            <h5 class="mb-3"><i class="bi bi-shield-check"></i> Terms and Conditions</h5>
                            <div class="form-check mb-2">
                                <input class="form-check-input" type="checkbox" id="terms1" required>
                                <label class="form-check-label" for="terms1">
                                    I will maintain the jeepney in good condition and follow all maintenance schedules.
                                </label>
                            </div>
                            <div class="form-check mb-2">
                                <input class="form-check-input" type="checkbox" id="terms2" required>
                                <label class="form-check-label" for="terms2">
                                    I will follow all traffic rules and regulations.
                                </label>
                            </div>
                            <div class="form-check mb-2">
                                <input class="form-check-input" type="checkbox" id="terms3" required>
                                <label class="form-check-label" for="terms3">
                                    I will report any issues or damages immediately.
                                </label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="terms4" required>
                                <label class="form-check-label" for="terms4">
                                    I understand that I am responsible for the jeepney's condition and operation.
                                </label>
                            </div>
                        </div>

                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-primary btn-lg">
                                <i class="bi bi-check-circle"></i> Submit Assignment
                            </button>
                            <a href="../operator_dashboard.php" class="btn btn-outline-secondary">
                                <i class="bi bi-arrow-left"></i> Back to Dashboard
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        document.getElementById('assignJeepneyForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            if (!this.checkValidity()) {
                e.stopPropagation();
                this.classList.add('was-validated');
                return;
            }

            try {
                const result = await Swal.fire({
                    title: 'Confirm Assignment',
                    text: 'Are you sure you want to submit this jeepney assignment?',
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonText: 'Yes, Submit',
                    cancelButtonText: 'Cancel',
                    confirmButtonColor: '#0d6efd'
                });

                if (result.isConfirmed) {
                    const formData = new FormData(this);
                    formData.append('driver_id', <?= $operatorId ?>);

                    const response = await fetch('../process_assign_jeepney.php', {
                        method: 'POST',
                        body: formData
                    });

                    const data = await response.json();

                    if (data.success) {
                        await Swal.fire({
                            icon: 'success',
                            title: 'Success!',
                            text: 'Jeepney assigned successfully.',
                            confirmButtonColor: '#198754'
                        });
                        window.location.href = '../operator_dashboard.php';
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