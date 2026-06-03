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
    header('Content-Type: application/json');
    
    try {
        $paymentId = intval($_POST['payment_id']);
        
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

        // Send email with receipt details
        $to = $payment['email'];
        $subject = 'Your TEBZ Payment Receipt - ' . $payment['receipt_number'];
        
        $message = "
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { text-align: center; color: #4f46e5; }
                .details { margin: 20px 0; }
                .footer { text-align: center; margin-top: 40px; color: #666; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>TEBZ</h1>
                    <h2>Payment Receipt</h2>
                </div>
                <div class='details'>
                    <p><strong>Receipt Number:</strong> {$payment['receipt_number']}</p>
                    <p><strong>Date:</strong> " . date('F j, Y', strtotime($payment['payment_date'])) . "</p>
                    <p><strong>Amount:</strong> ₱" . number_format($payment['amount'], 2) . "</p>
                    <p><strong>Payment Method:</strong> " . ucfirst($payment['method']) . "</p>
                </div>
                <div class='footer'>
                    <p>Thank you for your payment!</p>
                    <p>This receipt serves as proof of your payment to TEBZ.</p>
                </div>
            </div>
        </body>
        </html>";

        $headers = "MIME-Version: 1.0" . "\r\n";
        $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
        $headers .= "From: TEBZ <noreply@tebz.com>" . "\r\n";

        if (mail($to, $subject, $message, $headers)) {
            echo json_encode(['success' => true]);
        } else {
            throw new Exception('Failed to send email');
        }
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

        // Generate PDF receipt
        require_once 'vendor/autoload.php';
        
        // Create new PDF document
        $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);

        // Set document information
        $pdf->SetCreator('JeepniGo');
        $pdf->SetAuthor('JeepniGo');
        $pdf->SetTitle('Payment Receipt - ' . $payment['receipt_number']);

        // Remove default header/footer
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);

        // Set margins
        $pdf->SetMargins(15, 15, 15);

        // Add a page
        $pdf->AddPage();

        // Set font
        $pdf->SetFont('helvetica', '', 10);

        // Add logo
        $logo = 'img/jeepnigo-logo.png'; // Make sure this path is correct
        if (file_exists($logo)) {
            $pdf->Image($logo, 15, 15, 40);
        }

        // Add content with advanced styling
        $html = '
        <div style="text-align: right; margin-bottom: 20px;">
            <h1 style="color: #1e40af; font-size: 24px; font-weight: bold; margin: 0;">JeepniGo</h1>
            <p style="color: #4b5563; margin: 5px 0;">Your Trusted Transportation Partner</p>
        </div>

        <div style="margin-bottom: 20px; border-top: 2px solid #e5e7eb; border-bottom: 2px solid #e5e7eb; padding: 15px 0;">
            <h2 style="color: #1e40af; font-size: 18px; margin: 0 0 10px 0;">PAYMENT RECEIPT</h2>
            <table style="width: 100%;">
                <tr>
                    <td style="width: 50%;">
                        <p style="margin: 5px 0;"><strong>Receipt No:</strong><br>' . htmlspecialchars($payment['receipt_number']) . '</p>
                    </td>
                    <td style="width: 50%; text-align: right;">
                        <p style="margin: 5px 0;"><strong>Date:</strong><br>' . date('F j, Y', strtotime($payment['payment_date'])) . '</p>
                    </td>
                </tr>
            </table>
        </div>

        <div style="margin-bottom: 20px;">
            <h3 style="color: #1e40af; font-size: 14px; margin: 0 0 10px 0; padding-bottom: 5px; border-bottom: 1px solid #e5e7eb;">MEMBER INFORMATION</h3>
            <table style="width: 100%;">
                <tr>
                    <td style="width: 30%;"><strong>Name:</strong></td>
                    <td style="width: 70%;">' . htmlspecialchars($payment['firstName'] . ' ' . $payment['lastName']) . '</td>
                </tr>
                <tr>
                    <td><strong>Email:</strong></td>
                    <td>' . htmlspecialchars($payment['email']) . '</td>
                </tr>
            </table>
        </div>

        <div style="margin-bottom: 20px;">
            <h3 style="color: #1e40af; font-size: 14px; margin: 0 0 10px 0; padding-bottom: 5px; border-bottom: 1px solid #e5e7eb;">PAYMENT DETAILS</h3>
            <table style="width: 100%;">
                <tr>
                    <td style="width: 30%;"><strong>Amount:</strong></td>
                    <td style="width: 70%;">₱' . number_format($payment['amount'], 2) . '</td>
                </tr>
                <tr>
                    <td><strong>Payment Method:</strong></td>
                    <td>' . ucfirst($payment['method']) . '</td>
                </tr>
                <tr>
                    <td><strong>Status:</strong></td>
                    <td><span style="color: #059669; font-weight: bold;">' . ucfirst($payment['status']) . '</span></td>
                </tr>
            </table>
        </div>

        <div style="margin: 30px 0; padding: 20px; background-color: #f3f4f6; border-radius: 8px;">
            <p style="margin: 5px 0; font-weight: bold; color: #1e40af;">Thank you for your payment!</p>
            <p style="margin: 5px 0; color: #4b5563;">This receipt serves as proof of your payment to JeepniGo.</p>
        </div>

        <div style="margin-top: 40px; text-align: center; font-size: 9px; color: #6b7280;">
            <p style="margin: 2px 0;">JeepniGo - Your Trusted Transportation Partner</p>
            <p style="margin: 2px 0;">This is a computer-generated receipt. No signature required.</p>
            <p style="margin: 2px 0;">For any inquiries, please contact our support team.</p>
        </div>';

        $pdf->writeHTML($html, true, false, true, false, '');

        // Output the PDF
        $pdf->Output('JeepniGo_Receipt_' . $payment['receipt_number'] . '.pdf', 'I');
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
                                                        <div class="flex items-center gap-2">
                                                            <i class="bi bi-check2-circle text-green-500 text-xl"></i>
                                                            <?php if (!empty($row['receipt_number'])): ?>
                                                                <span class="px-2 py-1 bg-gray-100 rounded text-sm font-mono">
                                                                    <?= htmlspecialchars($row['receipt_number']); ?>
                                                                </span>
                                                                <button onclick="viewReceipt(<?= $row['id']; ?>)" 
                                                                        class="inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                                                    <i class="bi bi-eye mr-2"></i>
                                                                    View Receipt
                                                                </button>
                                                                <button onclick="sendReceipt(<?= $row['id']; ?>)" 
                                                                        class="inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
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
                                            <td class="px-6 py-4 text-center text-gray-500"></td>
                                            <td class="px-6 py-4 text-center text-gray-500"></td>
                                            <td class="px-6 py-4 text-center text-gray-500"></td>
                                            <td class="px-6 py-4 text-center text-gray-500"></td>
                                            <td class="px-6 py-4 text-center text-gray-500"></td>
                                            <td class="px-6 py-4 text-center text-gray-500"></td>
                                            <td class="px-6 py-4 text-center text-gray-500">
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
                    emptyTable: "No payments found. New payments will appear here when members submit their registration fees.",
                    zeroRecords: "No matching payments found."
                },
                autoWidth: false,
                responsive: true,
                destroy: true,
                retrieve: true,
                dom: '<"flex justify-between items-center mb-4"lf>rt<"flex justify-between items-center mt-4"ip>',
                lengthMenu: [[10, 25, 50, -1], [10, 25, 50, "All"]]
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
                                title: 'Receipt Sent!',
                                text: 'The receipt has been sent to the member\'s email.',
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
