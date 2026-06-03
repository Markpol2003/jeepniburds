<?php
session_start();
require_once __DIR__ . '/../db_config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../shared/index.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['payment_id'])) {
    $paymentId = intval($_GET['payment_id']);
    
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
        die("Payment not found");
    }

    // Generate receipt number if not exists
    if (empty($payment['receipt_number'])) {
        $receiptNumber = 'TEBZ-' . date('Ymd') . '-' . str_pad($paymentId, 4, '0', STR_PAD_LEFT);
        $stmt = $conn->prepare("UPDATE membership_payments SET receipt_number = ? WHERE id = ?");
        $stmt->bind_param('si', $receiptNumber, $paymentId);
        $stmt->execute();
        $payment['receipt_number'] = $receiptNumber;
    }

    // Generate HTML receipt
    $html = "
    <!DOCTYPE html>
    <html>
    <head>
        <title>Receipt - {$payment['receipt_number']}</title>
        <link href='https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css' rel='stylesheet'>
        <style>
            @media print {
                .no-print {
                    display: none;
                }
            }
        </style>
    </head>
    <body class='bg-gray-50 min-h-screen p-8'>
        <div class='max-w-2xl mx-auto bg-white rounded-lg shadow-lg p-8'>
            <!-- Header -->
            <div class='text-center mb-8'>
                <div class='flex justify-center mb-4'>
                    <img src='../img/logo12.png' alt='JeepniGo Logo' class='h-20 w-auto'>
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
                <div class='flex justify-between py-2 border-b'>
                    <span class='text-gray-600'>Member Name</span>
                    <span class='font-medium'>{$payment['firstName']} {$payment['lastName']}</span>
                </div>
                <div class='flex justify-between py-2 border-b'>
                    <span class='text-gray-600'>Amount Paid</span>
                    <span class='font-medium'>₱" . number_format($payment['amount'], 2) . "</span>
                </div>
                <div class='flex justify-between py-2 border-b'>
                    <span class='text-gray-600'>Payment Method</span>
                    <span class='font-medium'>" . ucfirst($payment['method']) . "</span>
                </div>
                <div class='flex justify-between py-2 border-b'>
                    <span class='text-gray-600'>Payment For</span>
                    <span class='font-medium'>Membership Fee</span>
                </div>
                <div class='flex justify-between py-2 border-b'>
                    <span class='text-gray-600'>Status</span>
                    <span class='font-medium text-green-600'>" . ucfirst($payment['status']) . "</span>
                </div>
            </div>

            <!-- Footer -->
            <div class='mt-8 pt-6 border-t'>
                <div class='grid grid-cols-2 gap-4'>
                    <div>
                        <p class='text-sm text-gray-600 mb-1'>Received By:</p>
                        <div class='border-b border-gray-300 w-48'></div>
                        <p class='text-xs text-gray-500 mt-1'>Treasurer / Cashier</p>
                    </div>
                    <div>
                        <p class='text-sm text-gray-600 mb-1'>Signature:</p>
                        <div class='border-b border-gray-300 w-48'></div>
                        <p class='text-xs text-gray-500 mt-1'>Member Signature</p>
                    </div>
                </div>
            </div>

            <!-- Print Button -->
            <div class='mt-8 text-center no-print'>
                <button onclick='window.print()' class='bg-blue-600 text-white px-6 py-2 rounded-lg hover:bg-blue-700 transition-colors'>
                    Print Receipt
                </button>
            </div>
        </div>
    </body>
    </html>";

    echo $html;
    exit();
} else {
    header("Location: ../shared/index.php");
    exit();
}
?> 