<?php
session_start();
require_once 'db_config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'treasurer') {
    header("Location: landing.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_payment'])) {
    $paymentId = intval($_POST['payment_id']);
    
    // Start transaction
    $conn->begin_transaction();
    
    try {
        // Update payment status
        $stmt = $conn->prepare("UPDATE membership_payments SET status = 'Confirmed' WHERE id = ?");
        $stmt->bind_param('i', $paymentId);
        $stmt->execute();

        // Generate and send receipt
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, "generate_receipt.php");
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, ['payment_id' => $paymentId]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($ch);
        curl_close($ch);

        // Commit transaction
        $conn->commit();
        
        // Set success message
        $_SESSION['payment_confirmed'] = true;
        $_SESSION['message'] = 'Payment confirmed and receipt generated successfully!';
    } catch (Exception $e) {
        // Rollback transaction on error
        $conn->rollback();
        $_SESSION['error'] = 'Error processing payment: ' . $e->getMessage();
    }

    header("Location: treasurer_dashboard.php");
    exit();
}

// Get all payments with user details
$query = "SELECT p.*, u.firstName, u.lastName, u.email, u.userType 
          FROM membership_payments p 
          JOIN users u ON p.user_id = u.id 
          ORDER BY p.payment_date DESC";
$result = $conn->query($query);

$userFirstName = $_SESSION['user_firstName'] ?? '';
$userLastName = $_SESSION['user_lastName'] ?? '';

?>
<?php if (isset($_SESSION['payment_confirmed'])): ?>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
Swal.fire({
    title: '🎉 Congratulations!',
    text: 'This member is now officially registered!',
    icon: 'success',
    confirmButtonText: 'Awesome!',
    confirmButtonColor: '#28a745',
    backdrop: `
        rgba(0,0,0,0.7)
        url("/tebz/img/confetti.gif")
        center top
        no-repeat
    `
});
</script>
<?php unset($_SESSION['payment_confirmed']); ?>
<?php endif; ?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Treasurer Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <style>
body {
    background-color: #f8f9fc;
}

.sidebar {
    min-height: 100vh;
    background: linear-gradient(180deg, #4e73df 0%, #224abe 100%);
    padding: 1.5rem 1rem;
    width: 240px;
}

.sidebar .nav-link {
    color: #f8f9fc;
    font-weight: 500;
    padding: 10px 15px;
    border-radius: 0.375rem;
    transition: all 0.3s ease;
}

.sidebar .nav-link:hover {
    background-color: rgba(255, 255, 255, 0.15);
    transform: translateX(4px);
}

.sidebar .nav-link.active {
    background-color: #fff;
    color: #224abe;
    font-weight: 600;
    box-shadow: 0 2px 6px rgba(0, 0, 0, 0.1);
}

.sidebar .nav-link.active i {
    color: #224abe;
}

.sidebar a.btn {
    margin-top: 1rem;
    background-color: #f8f9fc;
    color: #224abe;
    font-weight: 500;
}

.sidebar a.btn:hover {
    background-color: #e2e6ea;
    color: #1d3557;
}

.sidebar .fs-5 {
    font-size: 1.25rem;
    font-weight: 600;
}

.sidebar .fs-3 {
    font-size: 1.75rem;
}

.topbar {
    background: #fff;
    box-shadow: 0 1px 3px rgba(0,0,0,.1);
    padding: 1rem 2rem;
}

.payment-card {
    transition: transform 0.2s;
}

.payment-card:hover {
    transform: translateY(-2px);
}

.receipt-number {
    font-family: monospace;
    background: #f8f9fa;
    padding: 2px 6px;
    border-radius: 4px;
    font-size: 0.9em;
}
    </style>
</head>
<body>

<div class="d-flex">
    <!-- Sidebar -->
    <nav class="sidebar">
    <a href="#" class="text-white d-flex align-items-center mb-4">
    <i class="bi bi-cash-coin fs-3 me-2"></i>
    <span class="fs-5"><?= htmlspecialchars($userFirstName . ' ' . $userLastName); ?></span>
</a>

    <ul class="nav flex-column">
        <li class="nav-item">
            <a class="nav-link active" href="treasurer_dashboard.php">
                <i class="bi bi-wallet2 me-2"></i> Payments
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link" href="treasurer_dashboard.php?page=payment_details">
                <i class="bi bi-credit-card me-2"></i> Payment Details
            </a>
        </li>
        <li class="nav-item">
            <a class="btn w-100 text-start" href="logout.php">
                <i class="bi bi-box-arrow-left me-2"></i> Logout
            </a>
        </li>
    </ul>
</nav>


    <!-- Main content -->
    <div class="flex-grow-1">
        <!-- Topbar -->
        <div class="topbar d-flex justify-content-between align-items-center px-4 py-2">
            <h5 class="mb-0 fw-bold">Treasurer Dashboard</h5>
            <span class="text-muted">Logged in as Treasurer</span>
        </div>

        <div class="container-fluid mt-4">
            <?php if (isset($_SESSION['message'])): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <?= $_SESSION['message']; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                <?php unset($_SESSION['message']); ?>
            <?php endif; ?>

            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <?= $_SESSION['error']; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                <?php unset($_SESSION['error']); ?>
            <?php endif; ?>

            <?php
            $page = $_GET['page'] ?? 'payments';
            
            if ($page === 'payments'):
            ?>
            <div class="card shadow-sm">
                <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                    <h6 class="mb-0"><i class="bi bi-currency-exchange me-2"></i>Membership Payments</h6>
                    <div class="btn-group">
                        <button type="button" class="btn btn-sm btn-light" onclick="exportToExcel()">
                            <i class="bi bi-file-excel me-1"></i> Export
                        </button>
                    </div>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table id="paymentsTable" class="table table-striped table-bordered">
                            <thead>
                                <tr>
                                    <th>Member</th>
                                    <th>Type</th>
                                    <th>Amount</th>
                                    <th>Method</th>
                                    <th>Date</th>
                                    <th>Status</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($result && $result->num_rows > 0): ?>
                                    <?php while ($row = $result->fetch_assoc()): ?>
                                        <tr>
                                            <td>
                                                <?= htmlspecialchars($row['firstName'] . ' ' . $row['lastName']); ?>
                                                <br>
                                                <small class="text-muted"><?= htmlspecialchars($row['email']); ?></small>
                                            </td>
                                            <td><?= ucfirst($row['userType']); ?></td>
                                            <td>₱<?= number_format($row['amount'], 2); ?></td>
                                            <td>
                                                <span class="badge bg-<?= $row['method'] === 'gcash' ? 'success' : 'secondary'; ?>">
                                                    <?= ucfirst($row['method']); ?>
                                                </span>
                                            </td>
                                            <td><?= date('M d, Y h:i A', strtotime($row['payment_date'])); ?></td>
                                            <td>
                                                <?php if ($row['status'] === 'Confirmed'): ?>
                                                    <span class="badge bg-success">Confirmed</span>
                                                <?php else: ?>
                                                    <span class="badge bg-warning text-dark">Pending</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($row['status'] !== 'Confirmed'): ?>
                                                    <form method="POST" class="d-inline">
                                                        <input type="hidden" name="payment_id" value="<?= $row['id']; ?>">
                                                        <button type="submit" name="confirm_payment" 
                                                                class="btn btn-sm btn-success" 
                                                                onclick="return confirmPayment()">
                                                            <i class="bi bi-check-circle me-1"></i>Confirm & Generate Receipt
                                                        </button>
                                                    </form>
                                                <?php else: ?>
                                                    <div>
                                                        <i class="bi bi-check2-circle text-success fs-5"></i>
                                                        <?php if (!empty($row['receipt_number'])): ?>
                                                            <span class="receipt-number ms-2">
                                                                <?= htmlspecialchars($row['receipt_number']); ?>
                                                            </span>
                                                        <?php endif; ?>
                                                    </div>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr><td colspan="7" class="text-center text-muted">No payments found.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <?php elseif ($page === 'payment_details'): ?>
            <div class="card shadow-sm">
                <div class="card-header bg-primary text-white">
                    <h6 class="mb-0"><i class="bi bi-credit-card me-2"></i>Payment Details Management</h6>
                </div>
                <div class="card-body">
                    <?php
                    // Fetch existing payment details
                    $detailsQuery = "SELECT * FROM treasurer_payment_details WHERE treasurer_id = ?";
                    $detailsStmt = $conn->prepare($detailsQuery);
                    $detailsStmt->bind_param("i", $_SESSION['user_id']);
                    $detailsStmt->execute();
                    $detailsResult = $detailsStmt->get_result();
                    $details = $detailsResult->fetch_assoc();
                    ?>
                    <form action="update_payment_details.php" method="POST" class="needs-validation" novalidate>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="gcash_number" class="form-label">GCash Number</label>
                                <input type="text" class="form-control" id="gcash_number" name="gcash_number" 
                                       value="<?= htmlspecialchars($details['gcash_number'] ?? ''); ?>" 
                                       pattern="[0-9]{11}" required>
                                <div class="form-text">Enter your 11-digit GCash number</div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="gcash_name" class="form-label">GCash Account Name</label>
                                <input type="text" class="form-control" id="gcash_name" name="gcash_name" 
                                       value="<?= htmlspecialchars($details['gcash_name'] ?? ''); ?>" required>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="bank_name" class="form-label">Bank Name</label>
                                <input type="text" class="form-control" id="bank_name" name="bank_name" 
                                       value="<?= htmlspecialchars($details['bank_name'] ?? ''); ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="bank_account" class="form-label">Bank Account Number</label>
                                <input type="text" class="form-control" id="bank_account" name="bank_account" 
                                       value="<?= htmlspecialchars($details['bank_account'] ?? ''); ?>">
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="bank_account_name" class="form-label">Bank Account Name</label>
                                <input type="text" class="form-control" id="bank_account_name" name="bank_account_name" 
                                       value="<?= htmlspecialchars($details['bank_account_name'] ?? ''); ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="office_address" class="form-label">Office Address (for Cash Payments)</label>
                                <textarea class="form-control" id="office_address" name="office_address" rows="2"><?= htmlspecialchars($details['office_address'] ?? ''); ?></textarea>
                            </div>
                        </div>

                        <div class="text-end">
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-save me-2"></i>Save Payment Details
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <!-- Footer -->
        <footer class="text-center mt-5 mb-3 small text-muted">
            &copy; <?= date('Y'); ?> JeepniGo Treasurer Panel
        </footer>
    </div>
</div>

<!-- Scripts -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap5.min.js"></script>
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script>
    $(document).ready(function () {
        $('#paymentsTable').DataTable({
            order: [[4, 'desc']], // Sort by date column by default
            pageLength: 10,
            language: {
                search: "Search payments:"
            }
        });
    });

    function confirmPayment() {
        return Swal.fire({
            title: 'Confirm Payment?',
            text: 'This will generate a receipt and send it to the member.',
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Yes, confirm payment',
            cancelButtonText: 'Cancel',
            confirmButtonColor: '#198754'
        }).then((result) => {
            return result.isConfirmed;
        });
    }

    function exportToExcel() {
        // Implement Excel export functionality
        alert('Export to Excel functionality will be implemented here.');
    }
</script>
</body>
</html>
