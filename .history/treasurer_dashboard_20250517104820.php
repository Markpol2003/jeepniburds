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

        // Generate receipt number
        $receiptNumber = 'TEBZ-' . date('Ymd') . '-' . str_pad($paymentId, 4, '0', STR_PAD_LEFT);

        // Update payment status and receipt number
        $stmt = $conn->prepare("UPDATE membership_payments SET status = 'Confirmed', receipt_number = ? WHERE id = ?");
        $stmt->bind_param('si', $receiptNumber, $paymentId);
        $stmt->execute();

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

// Handle AJAX request for sending receipt
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'send_receipt') {
    // Prevent any output before JSON response
    error_reporting(0);
    ini_set('display_errors', 0);
    
    header('Content-Type: application/json');
    
    try {
        $paymentId = intval($_POST['payment_id']);
        
        // Get payment details
        $stmt = $conn->prepare("
            SELECT p.*, u.firstName, u.lastName, u.email, u.userType 
            FROM membership_payments p
            JOIN users u ON p.user_id = u.id
            WHERE p.id = ?
        ");
        $stmt->bind_param('i', $paymentId);
        $stmt->execute();
        $result = $stmt->get_result();
        $payment = $result->fetch_assoc();

        if (!$payment) {
            throw new Exception('Payment not found');
        }

        // Generate receipt number if not exists
        if (empty($payment['receipt_number'])) {
            $receiptNumber = 'TEBZ-' . date('Ymd') . '-' . str_pad($paymentId, 4, '0', STR_PAD_LEFT);
            $stmt = $conn->prepare("UPDATE membership_payments SET receipt_number = ? WHERE id = ?");
            $stmt->bind_param('si', $receiptNumber, $paymentId);
            $stmt->execute();
            $payment['receipt_number'] = $receiptNumber;
        }

        // Ensure payment method is set
        $paymentMethod = $payment['method'] ?? 'cash'; // Default to 'cash' if method is not set

        // Store receipt in user's dashboard
        $stmt = $conn->prepare("
            INSERT INTO user_receipts (user_id, payment_id, receipt_number, amount, payment_method, payment_date, status)
            VALUES (?, ?, ?, ?, ?, ?, 'Confirmed')
            ON DUPLICATE KEY UPDATE
            receipt_number = VALUES(receipt_number),
            amount = VALUES(amount),
            payment_method = VALUES(payment_method),
            payment_date = VALUES(payment_date),
            status = VALUES(status)
        ");
        $stmt->bind_param(
            'iissss',
            $payment['user_id'],
            $paymentId,
            $payment['receipt_number'],
            $payment['amount'],
            $paymentMethod,
            $payment['payment_date']
        );
        $stmt->execute();

        // Send email notification
        $to = $payment['email'];
        $subject = "TEBZ Payment Receipt - " . $payment['receipt_number'];
        $message = "
            <html>
            <head>
                <title>Payment Receipt</title>
            </head>
            <body>
                <h2>TEBZ Payment Receipt</h2>
                <p>Dear " . $payment['firstName'] . " " . $payment['lastName'] . ",</p>
                <p>Your payment has been confirmed. Here are your receipt details:</p>
                <ul>
                    <li><strong>Receipt Number:</strong> " . $payment['receipt_number'] . "</li>
                    <li><strong>Amount:</strong> ₱" . number_format($payment['amount'], 2) . "</li>
                    <li><strong>Payment Method:</strong> " . ucfirst($paymentMethod) . "</li>
                    <li><strong>Date:</strong> " . date('F j, Y', strtotime($payment['payment_date'])) . "</li>
                </ul>
                <p>You can view and download your receipt from your dashboard.</p>
                <p>Thank you for your payment!</p>
                <br>
                <p>Best regards,<br>TEBZ Team</p>
            </body>
            </html>
        ";
        $headers = "MIME-Version: 1.0" . "\r\n";
        $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
        $headers .= 'From: TEBZ <noreply@tebz.com>' . "\r\n";

        mail($to, $subject, $message, $headers);

        echo json_encode(['success' => true, 'message' => 'Receipt sent successfully']);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit();
}

// Handle AJAX request for viewing receipt
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'view_receipt') {
    $paymentId = intval($_GET['payment_id']);
    
    try {
        // Get payment details
        $stmt = $conn->prepare("
            SELECT p.*, u.firstName, u.lastName, u.email 
            FROM membership_payments p
            JOIN users u ON p.user_id = u.id
            WHERE p.id = ?
        ");
        $stmt->bind_param('i', $paymentId);
        $stmt->execute();
        $result = $stmt->get_result();
        $payment = $result->fetch_assoc();

        if (!$payment) {
            throw new Exception('Payment not found');
        }

        // Generate HTML receipt
        $html = "
        <!DOCTYPE html>
        <html>
        <head>
            <title>Receipt - {$payment['receipt_number']}</title>
            <script src='https://cdn.tailwindcss.com'></script>
            <style>
                @media print {
                    .no-print { display: none; }
                    body { margin: 0; }
                }
            </style>
        </head>
        <body class='bg-gray-50 min-h-screen p-8'>
            <div class='max-w-2xl mx-auto bg-white rounded-lg shadow-lg p-8'>
                <!-- Header -->
                <div class='text-center mb-8'>
                    <div class='flex justify-center mb-4'>
                        <img src='img/logo12.png' alt='JeepniGo Logo' class='h-20 w-auto'>
                    </div>
                    <h1 class='text-3xl font-bold text-blue-600 mb-2'>JeepniGo</h1>
                    <h2 class='text-xl text-gray-600'>Official Receipt</h2>
                    <div class='mt-2 text-sm text-gray-500'>" . date('F j, Y', strtotime($payment['payment_date'])) . "</div>
                </div>

                <!-- Receipt Number -->
                <div class='mb-6 p-4 bg-gray-50 rounded-lg'>
                    <div class='text-sm text-gray-600'>Receipt Number</div>
                    <div class='text-lg font-mono font-semibold text-gray-800'>{$payment['receipt_number']}</div>
                </div>

                <!-- Payment Details -->
                <div class='space-y-4 mb-8'>
                    <div class='grid grid-cols-2 gap-4'>
                        <div class='p-4 bg-gray-50 rounded-lg'>
                            <div class='text-sm text-gray-600'>Member Name</div>
                            <div class='font-semibold text-gray-800'>{$payment['firstName']} {$payment['lastName']}</div>
                        </div>
                        <div class='p-4 bg-gray-50 rounded-lg'>
                            <div class='text-sm text-gray-600'>Amount</div>
                            <div class='font-semibold text-gray-800'>₱" . number_format($payment['amount'], 2) . "</div>
                        </div>
                    </div>
                    <div class='grid grid-cols-2 gap-4'>
                        <div class='p-4 bg-gray-50 rounded-lg'>
                            <div class='text-sm text-gray-600'>Payment Method</div>
                            <div class='font-semibold text-gray-800'>" . ucfirst($payment['method']) . "</div>
                        </div>
                        <div class='p-4 bg-gray-50 rounded-lg'>
                            <div class='text-sm text-gray-600'>Status</div>
                            <div class='font-semibold text-green-600'>" . ucfirst($payment['status']) . "</div>
                        </div>
                    </div>
                </div>

                <!-- Signature Lines -->
                <div class='grid grid-cols-2 gap-8 mt-8 mb-8'>
                    <div class='text-center'>
                        <div class='border-t border-gray-300 pt-2 mb-1'></div>
                        <div class='text-sm text-gray-600'>Treasurer's Signature</div>
                    </div>
                    <div class='text-center'>
                        <div class='border-t border-gray-300 pt-2 mb-1'></div>
                        <div class='text-sm text-gray-600'>Member's Signature</div>
                    </div>
                </div>

                <!-- Footer -->
                <div class='text-center text-sm text-gray-500 mt-8'>
                    <p>Thank you for your payment!</p>
                    <p class='mt-1'>This receipt serves as proof of your payment to JeepniGo.</p>
                </div>

                <!-- Print Button -->
                <div class='no-print text-center mt-8'>
                    <button onclick='window.print()' class='px-6 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors duration-200'>
                        Print Receipt
                    </button>
                </div>
            </div>
        </body>
        </html>";

        echo $html;
        exit();
    } catch (Exception $e) {
        echo "Error: " . $e->getMessage();
        exit();
    }
}

// Get all payments with user details
$query = "SELECT p.*, u.firstName, u.lastName, u.userType 
          FROM membership_payments p
          JOIN users u ON p.user_id = u.id
          ORDER BY p.payment_date DESC";
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
    <style>
        @keyframes slideIn {
            from { transform: translateX(-100%); }
            to { transform: translateX(0); }
        }
        .animate-slide-in {
            animation: slideIn 0.3s ease-out;
        }
        .hover-scale {
            transition: transform 0.2s ease-in-out;
        }
        .hover-scale:hover {
            transform: scale(1.02);
        }
        .glass-effect {
            background: rgba(255, 255, 255, 0.7);
            backdrop-filter: blur(10px);
        }
    </style>
</head>
<body class="bg-gradient-to-br from-blue-50 to-indigo-50 min-h-screen">
    <div class="flex h-screen">
        <!-- Sidebar -->
        <nav class="w-64 bg-gradient-to-b from-blue-600 to-blue-800 text-white p-6 shadow-xl transform transition-transform duration-300 hover:translate-x-0">
            <div class="flex items-center space-x-3 mb-8 p-4 rounded-lg bg-white/10 hover:bg-white/20 transition-colors duration-200">
                <i class="bi bi-cash-coin text-2xl"></i>
                <span class="text-lg font-semibold"><?= htmlspecialchars($userFirstName . ' ' . $userLastName); ?></span>
            </div>

            <ul class="space-y-2">
                <li>
                    <a href="treasurer_dashboard.php" class="flex items-center space-x-3 px-4 py-3 rounded-lg <?= ($page === 'payments') ? 'bg-white text-blue-800 shadow-lg' : 'hover:bg-white/20' ?> transition-all duration-200">
                        <i class="bi bi-wallet2"></i>
                        <span>Payments</span>
                    </a>
                </li>
                <li>
                    <a href="treasurer_dashboard.php?page=payment_details" class="flex items-center space-x-3 px-4 py-3 rounded-lg <?= ($page === 'payment_details') ? 'bg-white text-blue-800 shadow-lg' : 'hover:bg-white/20' ?> transition-all duration-200">
                        <i class="bi bi-credit-card"></i>
                        <span>Payment Details</span>
                    </a>
                </li>
                <li class="pt-4">
                    <a href="logout.php" class="flex items-center space-x-3 px-4 py-3 rounded-lg bg-white/10 text-white hover:bg-white/20 transition-all duration-200">
                        <i class="bi bi-box-arrow-left"></i>
                        <span>Logout</span>
                    </a>
                </li>
            </ul>
        </nav>

        <!-- Main content -->
        <div class="flex-1 overflow-auto">
            <!-- Topbar -->
            <div class="glass-effect shadow-sm px-8 py-4 flex justify-between items-center sticky top-0 z-10">
                <h1 class="text-xl font-bold text-gray-800 flex items-center">
                    <i class="bi bi-speedometer2 mr-2 text-blue-600"></i>
                    Treasurer Dashboard
                </h1>
                <span class="text-gray-600 bg-white/50 px-4 py-2 rounded-full text-sm">Logged in as Treasurer</span>
            </div>

            <div class="p-8">
                <?php if (isset($_SESSION['message'])): ?>
                    <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6 rounded-lg shadow-sm animate-slide-in" role="alert">
                        <?= $_SESSION['message']; ?>
                    </div>
                    <?php unset($_SESSION['message']); ?>
                <?php endif; ?>

                <?php if (isset($_SESSION['error'])): ?>
                    <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6 rounded-lg shadow-sm animate-slide-in" role="alert">
                        <?= $_SESSION['error']; ?>
                    </div>
                    <?php unset($_SESSION['error']); ?>
                <?php endif; ?>

                <?php
                $page = $_GET['page'] ?? 'payments';
                
                if ($page === 'payments'):
                ?>
                <div class="bg-white rounded-xl shadow-lg hover-scale">
                    <div class="px-6 py-4 border-b border-gray-200 flex justify-between items-center">
                        <h2 class="text-lg font-semibold text-gray-800 flex items-center">
                            <i class="bi bi-currency-exchange mr-2 text-blue-600"></i>
                            Membership Payments
                        </h2>
                        <button onclick="exportToExcel()" class="px-4 py-2 bg-blue-50 text-blue-600 rounded-lg hover:bg-blue-100 transition-colors duration-200 flex items-center">
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
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <?php if ($result && $result->num_rows > 0 && $hasAllColumns): ?>
                                        <?php while ($row = $result->fetch_assoc()): ?>
                                            <tr class="hover:bg-gray-50 transition-colors duration-150">
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
                                                                    class="inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500 transition-all duration-200">
                                                                <i class="bi bi-check-circle mr-2"></i>
                                                                Confirm & Generate Receipt
                                                            </button>
                                                        </form>
                                                    <?php else: ?>
                                                        <div class="flex items-center gap-2">
                                                            <i class="bi bi-check2-circle text-green-500 text-xl"></i>
                                                            <?php if (!empty($row['receipt_number'])): ?>
                                                                <span class="px-2 py-1 bg-gray-100 rounded text-sm font-mono">
                                                                    <?= htmlspecialchars($row['receipt_number']); ?>
                                                                </span>
                                                                <button onclick="viewReceipt(<?= $row['id']; ?>)" 
                                                                        class="inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-all duration-200">
                                                                    <i class="bi bi-eye mr-2"></i>
                                                                    View Receipt
                                                                </button>
                                                                <button onclick="sendReceipt(<?= $row['id']; ?>)" 
                                                                        class="inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition-all duration-200">
                                                                    <i class="bi bi-envelope mr-2"></i>
                                                                    Send Receipt
                                                                </button>
                                                            <?php endif; ?>
                                                        </div>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td class="px-6 py-4 text-center text-gray-500">
                                                <?php if (!$hasAllColumns): ?>
                                                    Error: Some required data is missing. Please contact the administrator.
                                                <?php else: ?>
                                                    No payments found. New payments will appear here when members submit their registration fees.
                                                <?php endif; ?>
                                            </td>
                                            <td class="px-6 py-4"></td>
                                            <td class="px-6 py-4"></td>
                                            <td class="px-6 py-4"></td>
                                            <td class="px-6 py-4"></td>
                                            <td class="px-6 py-4"></td>
                                            <td class="px-6 py-4"></td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <?php elseif ($page === 'payment_details'): ?>
                    <div class="bg-white rounded-xl shadow-lg hover-scale animate-slide-in">
                        <div class="px-6 py-4 border-b border-gray-200">
                            <h2 class="text-lg font-semibold text-gray-800 flex items-center">
                                <i class="bi bi-credit-card mr-2 text-blue-600"></i>
                                Payment Details Management
                            </h2>
                        </div>
                        <div class="p-6">
                            <form action="update_payment_details.php" method="POST" class="space-y-6">
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                    <div class="hover-scale">
                                        <label for="gcash_number" class="block text-sm font-medium text-gray-700 mb-1">GCash Number</label>
                                        <input type="text" id="gcash_number" name="gcash_number" 
                                               value="<?= htmlspecialchars($details['gcash_number'] ?? ''); ?>"
                                               pattern="[0-9]{11}" required
                                               class="mt-1 block w-full px-4 py-3 rounded-lg border-2 border-gray-300 bg-white text-gray-900 shadow-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-200 transition-colors duration-200">
                                        <p class="mt-2 text-sm text-gray-500">Enter your 11-digit GCash number</p>
                                    </div>

                                    <div class="hover-scale">
                                        <label for="gcash_name" class="block text-sm font-medium text-gray-700 mb-1">GCash Account Name</label>
                                        <input type="text" id="gcash_name" name="gcash_name" 
                                               value="<?= htmlspecialchars($details['gcash_name'] ?? ''); ?>" required
                                               class="mt-1 block w-full px-4 py-3 rounded-lg border-2 border-gray-300 bg-white text-gray-900 shadow-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-200 transition-colors duration-200">
                                    </div>

                                    <div class="hover-scale">
                                        <label for="bank_name" class="block text-sm font-medium text-gray-700 mb-1">Bank Name</label>
                                        <input type="text" id="bank_name" name="bank_name" 
                                               value="<?= htmlspecialchars($details['bank_name'] ?? ''); ?>"
                                               class="mt-1 block w-full px-4 py-3 rounded-lg border-2 border-gray-300 bg-white text-gray-900 shadow-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-200 transition-colors duration-200">
                                    </div>

                                    <div class="hover-scale">
                                        <label for="bank_account" class="block text-sm font-medium text-gray-700 mb-1">Bank Account Number</label>
                                        <input type="text" id="bank_account" name="bank_account" 
                                               value="<?= htmlspecialchars($details['bank_account'] ?? ''); ?>"
                                               class="mt-1 block w-full px-4 py-3 rounded-lg border-2 border-gray-300 bg-white text-gray-900 shadow-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-200 transition-colors duration-200">
                                    </div>

                                    <div class="hover-scale">
                                        <label for="bank_account_name" class="block text-sm font-medium text-gray-700 mb-1">Bank Account Name</label>
                                        <input type="text" id="bank_account_name" name="bank_account_name" 
                                               value="<?= htmlspecialchars($details['bank_account_name'] ?? ''); ?>"
                                               class="mt-1 block w-full px-4 py-3 rounded-lg border-2 border-gray-300 bg-white text-gray-900 shadow-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-200 transition-colors duration-200">
                                    </div>

                                    <div class="hover-scale">
                                        <label for="office_address" class="block text-sm font-medium text-gray-700 mb-1">Office Address (for Cash Payments)</label>
                                        <textarea id="office_address" name="office_address" rows="2"
                                                  class="mt-1 block w-full px-4 py-3 rounded-lg border-2 border-gray-300 bg-white text-gray-900 shadow-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-200 transition-colors duration-200"><?= htmlspecialchars($details['office_address'] ?? ''); ?></textarea>
                                    </div>
                                </div>

                                <div class="flex justify-end">
                                    <button type="submit" class="inline-flex items-center px-6 py-3 border border-transparent text-base font-medium rounded-lg text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 shadow-sm transition-all duration-200 hover-scale">
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
            <footer class="text-center py-4 text-sm text-gray-500 glass-effect">
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
                    emptyTable: "No payments found. New payments will appear here when members submit their registration fees.",
                    zeroRecords: "No matching payments found.",
                    info: "Showing _START_ to _END_ of _TOTAL_ payments",
                    infoEmpty: "Showing 0 to 0 of 0 payments",
                    infoFiltered: "(filtered from _MAX_ total payments)",
                    lengthMenu: "Show _MENU_ payments per page",
                    paginate: {
                        first: "First",
                        last: "Last",
                        next: "Next",
                        previous: "Previous"
                    }
                },
                autoWidth: false,
                responsive: true,
                dom: '<"flex justify-between items-center mb-4"lf>rt<"flex justify-between items-center mt-4"ip>',
                lengthMenu: [[10, 25, 50, -1], [10, 25, 50, "All"]],
                processing: true,
                serverSide: false,
                columnDefs: [
                    { targets: 6, orderable: false, searchable: false }
                ],
                columns: [
                    { 
                        data: null,
                        render: function(data, type, row) {
                            if (type === 'display') {
                                const firstName = row.firstName || '';
                                const lastName = row.lastName || '';
                                return '<div class="text-sm font-medium text-gray-900">' + firstName + ' ' + lastName + '</div>';
                            }
                            return (row.firstName || '') + ' ' + (row.lastName || '');
                        }
                    },
                    { 
                        data: 'userType',
                        render: function(data, type, row) {
                            if (!data) return '';
                            if (type === 'display') {
                                return '<div class="text-sm text-gray-500">' + data.charAt(0).toUpperCase() + data.slice(1) + '</div>';
                            }
                            return data;
                        }
                    },
                    { 
                        data: 'amount',
                        render: function(data, type, row) {
                            if (type === 'display') {
                                const amount = parseFloat(data) || 0;
                                return '<div class="text-sm text-gray-900">₱' + amount.toFixed(2) + '</div>';
                            }
                            return parseFloat(data) || 0;
                        }
                    },
                    { 
                        data: 'method',
                        render: function(data, type, row) {
                            if (!data) return '';
                            if (type === 'display') {
                                const isGCash = data.toLowerCase() === 'gcash';
                                return '<span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full ' + 
                                       (isGCash ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800') + '">' +
                                       data.charAt(0).toUpperCase() + data.slice(1) + '</span>';
                            }
                            return data;
                        }
                    },
                    { 
                        data: 'payment_date',
                        render: function(data, type, row) {
                            if (!data) return '';
                            if (type === 'display') {
                                try {
                                    const date = new Date(data);
                                    if (isNaN(date.getTime())) return '';
                                    return '<div class="text-sm text-gray-500">' + date.toLocaleString('en-US', {
                                        month: 'short',
                                        day: 'numeric',
                                        year: 'numeric',
                                        hour: 'numeric',
                                        minute: 'numeric',
                                        hour12: true
                                    }) + '</div>';
                                } catch (e) {
                                    return '';
                                }
                            }
                            return data;
                        }
                    },
                    { 
                        data: 'status',
                        render: function(data, type, row) {
                            if (!data) return '';
                            if (type === 'display') {
                                const isConfirmed = data.toLowerCase() === 'confirmed';
                                return '<span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full ' +
                                       (isConfirmed ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800') + '">' +
                                       data + '</span>';
                            }
                            return data;
                        }
                    },
                    { 
                        data: null,
                        render: function(data, type, row) {
                            if (type === 'display') {
                                if (row.status !== 'Confirmed') {
                                    return '<form method="POST" class="inline">' +
                                           '<input type="hidden" name="payment_id" value="' + row.id + '">' +
                                           '<button type="submit" name="confirm_payment" onclick="return confirmPayment()" ' +
                                           'class="inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500 transition-all duration-200">' +
                                           '<i class="bi bi-check-circle mr-2"></i>Confirm & Generate Receipt</button></form>';
                                } else {
                                    let html = '<div class="flex items-center gap-2">' +
                                             '<i class="bi bi-check2-circle text-green-500 text-xl"></i>';
                                    if (row.receipt_number) {
                                        html += '<span class="px-2 py-1 bg-gray-100 rounded text-sm font-mono">' + row.receipt_number + '</span>' +
                                               '<button onclick="viewReceipt(' + row.id + ')" ' +
                                               'class="inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-all duration-200">' +
                                               '<i class="bi bi-eye mr-2"></i>View Receipt</button>' +
                                               '<button onclick="sendReceipt(' + row.id + ')" ' +
                                               'class="inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition-all duration-200">' +
                                               '<i class="bi bi-envelope mr-2"></i>Send Receipt</button>';
                                    }
                                    html += '</div>';
                                    return html;
                                }
                            }
                            return '';
                        }
                    }
                ],
                initComplete: function() {
                    // Style the search box
                    $('.dataTables_filter input').addClass('px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500');
                    $('.dataTables_filter label').addClass('text-gray-700 font-medium');
                    
                    // Add hover effect to table rows
                    $('.dataTables_wrapper tbody tr').hover(
                        function() { $(this).addClass('bg-gray-50'); },
                        function() { $(this).removeClass('bg-gray-50'); }
                    );

                    // Add custom styling for empty table message
                    $('.dataTables_empty').addClass('text-gray-500 py-8 text-center');
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
            alert('Export to Excel functionality will be implemented here.');
        }

        function viewReceipt(paymentId) {
            window.open(`treasurer_dashboard.php?action=view_receipt&payment_id=${paymentId}`, '_blank');
        }

        function sendReceipt(paymentId) {
            Swal.fire({
                title: 'Send Receipt',
                text: 'Are you sure you want to send the receipt to the member?',
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'Yes, Send',
                cancelButtonText: 'Cancel',
                confirmButtonColor: '#4f46e5'
            }).then((result) => {
                if (result.isConfirmed) {
                    // Show loading state
                    Swal.fire({
                        title: 'Sending Receipt...',
                        didOpen: () => {
                            Swal.showLoading();
                        },
                        allowOutsideClick: false,
                        allowEscapeKey: false,
                        allowEnterKey: false
                    });

                    // Send request to send receipt
                    const formData = new FormData();
                    formData.append('action', 'send_receipt');
                    formData.append('payment_id', paymentId);

                    fetch('treasurer_dashboard.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            Swal.fire({
                                icon: 'success',
                                title: 'Receipt Already Sent!',
                                text: 'The receipt has already been sent to the member.',
                                confirmButtonColor: '#4f46e5'
                            });
                        } else {
                            throw new Error(data.message || 'Failed to send receipt');
                        }
                    })
                    .catch(error => {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: error.message || 'Failed to send receipt. Please try again.',
                            confirmButtonColor: '#dc2626'
                        });
                    });
                }
            });
        }
    </script>
</body>
</html>
