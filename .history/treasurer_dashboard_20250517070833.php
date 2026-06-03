<?php
session_start();
require_once 'db_config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'treasurer') {
    header("Location: landing.php");
    exit();
}

// Create treasurer_payment_details table if it doesn't exist
$createTableSQL = "CREATE TABLE IF NOT EXISTS treasurer_payment_details (
    id INT PRIMARY KEY AUTO_INCREMENT,
    treasurer_id INT NOT NULL,
    gcash_number VARCHAR(11) NOT NULL,
    gcash_name VARCHAR(100) NOT NULL,
    bank_name VARCHAR(100),
    bank_account VARCHAR(50),
    bank_account_name VARCHAR(100),
    office_address TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (treasurer_id) REFERENCES users(id) ON DELETE CASCADE
)";

try {
    $conn->query($createTableSQL);
} catch (Exception $e) {
    // Table might already exist or there might be an error, but we'll continue
    // The error will be handled when trying to access the table
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_payment'])) {
    $paymentId = intval($_POST['payment_id']);
    
    // Start transaction
    $conn->begin_transaction();
    
    try {
        // Get user information before updating payment status
        $userQuery = "SELECT u.id, u.firstName, u.lastName, u.userType, u.email 
                     FROM membership_payments p 
                     JOIN users u ON p.user_id = u.id 
                     WHERE p.id = ?";
        $userStmt = $conn->prepare($userQuery);
        $userStmt->bind_param('i', $paymentId);
        $userStmt->execute();
        $userResult = $userStmt->get_result();
        $userInfo = $userResult->fetch_assoc();

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

        // Store user info in session for congratulations message
        $_SESSION['payment_confirmed'] = true;
        $_SESSION['confirmed_user'] = [
            'id' => $userInfo['id'],
            'firstName' => $userInfo['firstName'],
            'lastName' => $userInfo['lastName'],
            'userType' => $userInfo['userType'],
            'email' => $userInfo['email']
        ];
        $_SESSION['message'] = 'Payment confirmed and receipt generated successfully!';

        // Commit transaction
        $conn->commit();
    } catch (Exception $e) {
        // Rollback transaction on error
        $conn->rollback();
        $_SESSION['error'] = 'Error processing payment: ' . $e->getMessage();
    }

    header("Location: treasurer_dashboard.php");
    exit();
}

// Get all payments with user details
$query = "SELECT * FROM membership_payments ORDER BY payment_date DESC";
$result = $conn->query($query);

$userFirstName = $_SESSION['user_firstName'] ?? '';
$userLastName = $_SESSION['user_lastName'] ?? '';

?>
<?php if (isset($_SESSION['payment_confirmed']) && isset($_SESSION['confirmed_user'])): ?>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
Swal.fire({
    title: '🎉 Congratulations!',
    text: '<?= htmlspecialchars($_SESSION['confirmed_user']['firstName'] . ' ' . $_SESSION['confirmed_user']['lastName']) ?> is now officially registered as a <?= htmlspecialchars($_SESSION['confirmed_user']['userType']) ?>!',
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
<?php 
unset($_SESSION['payment_confirmed']);
unset($_SESSION['confirmed_user']);
?>
<?php endif; ?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Treasurer Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap5.min.css" rel="stylesheet">
</head>
<body class="bg-gray-50">
    <div class="flex h-screen">
        <!-- Sidebar -->
        <nav class="w-64 bg-gradient-to-b from-blue-600 to-blue-800 text-white p-6">
            <div class="flex items-center space-x-3 mb-8">
                <i class="bi bi-cash-coin text-2xl"></i>
                <span class="text-lg font-semibold"><?= htmlspecialchars($userFirstName . ' ' . $userLastName); ?></span>
            </div>

            <ul class="space-y-2">
                <li>
                    <a href="treasurer_dashboard.php" class="flex items-center space-x-3 px-4 py-3 rounded-lg <?= ($page === 'payments') ? 'bg-white text-blue-800' : 'hover:bg-blue-700' ?>">
                        <i class="bi bi-wallet2"></i>
                        <span>Payments</span>
                    </a>
                </li>
                <li>
                    <a href="treasurer_dashboard.php?page=payment_details" class="flex items-center space-x-3 px-4 py-3 rounded-lg <?= ($page === 'payment_details') ? 'bg-white text-blue-800' : 'hover:bg-blue-700' ?>">
                        <i class="bi bi-credit-card"></i>
                        <span>Payment Details</span>
                    </a>
                </li>
                <li class="pt-4">
                    <a href="logout.php" class="flex items-center space-x-3 px-4 py-3 rounded-lg bg-white text-blue-800 hover:bg-gray-100">
                        <i class="bi bi-box-arrow-left"></i>
                        <span>Logout</span>
                    </a>
                </li>
            </ul>
        </nav>

        <!-- Main content -->
        <div class="flex-1 overflow-auto">
            <!-- Topbar -->
            <div class="bg-white shadow-sm px-8 py-4 flex justify-between items-center">
                <h1 class="text-xl font-bold text-gray-800">Treasurer Dashboard</h1>
                <span class="text-gray-600">Logged in as Treasurer</span>
            </div>

            <div class="p-8">
                <?php if (isset($_SESSION['message'])): ?>
                    <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6" role="alert">
                        <?= $_SESSION['message']; ?>
                    </div>
                    <?php unset($_SESSION['message']); ?>
                <?php endif; ?>

                <?php if (isset($_SESSION['error'])): ?>
                    <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6" role="alert">
                        <?= $_SESSION['error']; ?>
                    </div>
                    <?php unset($_SESSION['error']); ?>
                <?php endif; ?>

                <?php
                $page = $_GET['page'] ?? 'payments';
                
                if ($page === 'payments'):
                ?>
                <div class="bg-white rounded-lg shadow-sm">
                    <div class="px-6 py-4 border-b border-gray-200 flex justify-between items-center">
                        <h2 class="text-lg font-semibold text-gray-800 flex items-center">
                            <i class="bi bi-currency-exchange mr-2"></i>
                            Membership Payments
                        </h2>
                        <button onclick="exportToExcel()" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 flex items-center">
                            <i class="bi bi-file-excel mr-2"></i>
                            Export
                        </button>
                    </div>
                    <div class="p-6">
                        <div class="overflow-x-auto">
                            <?php
                            // Check if the result has the required columns
                            $requiredColumns = ['firstName', 'lastName', 'userType', 'amount', 'method', 'payment_date', 'status', 'id', 'receipt_number'];
                            $hasAllColumns = true;
                            if ($result && $result->num_rows > 0) {
                                $firstRow = $result->fetch_assoc();
                                foreach ($requiredColumns as $column) {
                                    if (!array_key_exists($column, $firstRow)) {
                                        $hasAllColumns = false;
                                        break;
                                    }
                                }
                                // Reset the result pointer
                                $result->data_seek(0);
                            }
                            ?>
                            <table id="paymentsTable" class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Member</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Type</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Amount</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Method</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Action</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <?php if ($result && $result->num_rows > 0 && $hasAllColumns): ?>
                                        <?php while ($row = $result->fetch_assoc()): ?>
                                            <tr class="hover:bg-gray-50">
                                                <td class="px-6 py-4">
                                                    <div class="text-sm font-medium text-gray-900">
                                                        <?= htmlspecialchars($row['firstName'] . ' ' . $row['lastName']); ?>
                                                    </div>
                                                </td>
                                                <td class="px-6 py-4 text-sm text-gray-500">
                                                    <?= ucfirst($row['userType']); ?>
                                                </td>
                                                <td class="px-6 py-4 text-sm text-gray-900">
                                                    ₱<?= number_format($row['amount'], 2); ?>
                                                </td>
                                                <td class="px-6 py-4">
                                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?= $row['method'] === 'gcash' ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800' ?>">
                                                        <?= ucfirst($row['method']); ?>
                                                    </span>
                                                </td>
                                                <td class="px-6 py-4 text-sm text-gray-500">
                                                    <?= date('M d, Y h:i A', strtotime($row['payment_date'])); ?>
                                                </td>
                                                <td class="px-6 py-4">
                                                    <?php if ($row['status'] === 'Confirmed'): ?>
                                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">
                                                            Confirmed
                                                        </span>
                                                    <?php else: ?>
                                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-yellow-100 text-yellow-800">
                                                            Pending
                                                        </span>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="px-6 py-4 text-sm">
                                                    <?php if ($row['status'] !== 'Confirmed'): ?>
                                                        <form method="POST" class="inline">
                                                            <input type="hidden" name="payment_id" value="<?= $row['id']; ?>">
                                                            <button type="submit" name="confirm_payment" 
                                                                    onclick="return confirmPayment()"
                                                                    class="inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500">
                                                                <i class="bi bi-check-circle mr-2"></i>
                                                                Confirm & Generate Receipt
                                                            </button>
                                                        </form>
                                                    <?php else: ?>
                                                        <div class="flex items-center">
                                                            <i class="bi bi-check2-circle text-green-500 text-xl"></i>
                                                            <?php if (!empty($row['receipt_number'])): ?>
                                                                <span class="ml-2 px-2 py-1 bg-gray-100 rounded text-sm font-mono">
                                                                    <?= htmlspecialchars($row['receipt_number']); ?>
                                                                </span>
                                                            <?php endif; ?>
                                                        </div>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="7" class="px-6 py-4 text-center text-gray-500">
                                                <?php if (!$hasAllColumns): ?>
                                                    Error: Some required data is missing. Please contact the administrator.
                                                <?php else: ?>
                                                    No payments found.
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                <?php elseif ($page === 'payment_details'): ?>
                    <div class="bg-white rounded-lg shadow-sm">
                        <div class="px-6 py-4 border-b border-gray-200">
                            <h2 class="text-lg font-semibold text-gray-800 flex items-center">
                                <i class="bi bi-credit-card mr-2"></i>
                                Payment Details Management
                            </h2>
                        </div>
                        <div class="p-6">
                            <form action="update_payment_details.php" method="POST" class="space-y-6">
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                    <div>
                                        <label for="gcash_number" class="block text-sm font-medium text-gray-700 mb-1">GCash Number</label>
                                        <input type="text" id="gcash_number" name="gcash_number" 
                                               value="<?= htmlspecialchars($details['gcash_number'] ?? ''); ?>"
                                               pattern="[0-9]{11}" required
                                               class="mt-1 block w-full px-4 py-3 rounded-lg border-2 border-gray-300 bg-white text-gray-900 shadow-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-200 transition-colors duration-200">
                                        <p class="mt-2 text-sm text-gray-500">Enter your 11-digit GCash number</p>
                                    </div>

                                    <div>
                                        <label for="gcash_name" class="block text-sm font-medium text-gray-700 mb-1">GCash Account Name</label>
                                        <input type="text" id="gcash_name" name="gcash_name" 
                                               value="<?= htmlspecialchars($details['gcash_name'] ?? ''); ?>" required
                                               class="mt-1 block w-full px-4 py-3 rounded-lg border-2 border-gray-300 bg-white text-gray-900 shadow-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-200 transition-colors duration-200">
                                    </div>

                                    <div>
                                        <label for="bank_name" class="block text-sm font-medium text-gray-700 mb-1">Bank Name</label>
                                        <input type="text" id="bank_name" name="bank_name" 
                                               value="<?= htmlspecialchars($details['bank_name'] ?? ''); ?>"
                                               class="mt-1 block w-full px-4 py-3 rounded-lg border-2 border-gray-300 bg-white text-gray-900 shadow-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-200 transition-colors duration-200">
                                    </div>

                                    <div>
                                        <label for="bank_account" class="block text-sm font-medium text-gray-700 mb-1">Bank Account Number</label>
                                        <input type="text" id="bank_account" name="bank_account" 
                                               value="<?= htmlspecialchars($details['bank_account'] ?? ''); ?>"
                                               class="mt-1 block w-full px-4 py-3 rounded-lg border-2 border-gray-300 bg-white text-gray-900 shadow-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-200 transition-colors duration-200">
                                    </div>

                                    <div>
                                        <label for="bank_account_name" class="block text-sm font-medium text-gray-700 mb-1">Bank Account Name</label>
                                        <input type="text" id="bank_account_name" name="bank_account_name" 
                                               value="<?= htmlspecialchars($details['bank_account_name'] ?? ''); ?>"
                                               class="mt-1 block w-full px-4 py-3 rounded-lg border-2 border-gray-300 bg-white text-gray-900 shadow-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-200 transition-colors duration-200">
                                    </div>

                                    <div>
                                        <label for="office_address" class="block text-sm font-medium text-gray-700 mb-1">Office Address (for Cash Payments)</label>
                                        <textarea id="office_address" name="office_address" rows="2"
                                                  class="mt-1 block w-full px-4 py-3 rounded-lg border-2 border-gray-300 bg-white text-gray-900 shadow-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-200 transition-colors duration-200"><?= htmlspecialchars($details['office_address'] ?? ''); ?></textarea>
                                    </div>
                                </div>

                                <div class="flex justify-end">
                                    <button type="submit" class="inline-flex items-center px-6 py-3 border border-transparent text-base font-medium rounded-lg text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 shadow-sm transition-colors duration-200">
                                        <i class="bi bi-save mr-2"></i>
                                        Save Payment Details
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Footer -->
            <footer class="text-center py-4 text-sm text-gray-500">
                &copy; <?= date('Y'); ?> JeepniGo Treasurer Panel
            </footer>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap5.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        $(document).ready(function () {
            $('#paymentsTable').DataTable({
                order: [[4, 'desc']],
                pageLength: 10,
                language: {
                    search: "Search payments:",
                    emptyTable: "No payments found."
                },
                autoWidth: false,
                responsive: true,
                destroy: true,
                retrieve: true,
                columns: [
                    { data: null, defaultContent: '' },  // Member
                    { data: null, defaultContent: '' },  // Type
                    { data: null, defaultContent: '' },  // Amount
                    { data: null, defaultContent: '' },  // Method
                    { data: null, defaultContent: '' },  // Date
                    { data: null, defaultContent: '' },  // Status
                    { data: null, defaultContent: '' }   // Action
                ]
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
            alert('Export to Excel functionality will be implemented here.');
        }
    </script>
</body>
</html>
