<?php
require_once 'config/database.php';
require_once 'includes/functions.php';
require_once 'vendor/autoload.php';

// Check if user is logged in
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Get payment ID from URL
$payment_id = isset($_GET['payment_id']) ? (int)$_GET['payment_id'] : 0;

if (!$payment_id) {
    die('Invalid payment ID');
}

try {
    // Get payment details
    $stmt = $pdo->prepare("
        SELECT p.*, m.first_name, m.last_name, m.email, m.phone
        FROM payments p
        JOIN members m ON p.member_id = m.id
        WHERE p.id = ?
    ");
    $stmt->execute([$payment_id]);
    $payment = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$payment) {
        die('Payment not found');
    }

    // Generate receipt PDF
    $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);

    // Set document information
    $pdf->SetCreator(PDF_CREATOR);
    $pdf->SetAuthor('TEBZ');
    $pdf->SetTitle('Payment Receipt - ' . $payment['receipt_number']);

    // Remove default header/footer
    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(false);

    // Add a page
    $pdf->AddPage();

    // Set font
    $pdf->SetFont('helvetica', '', 12);

    // Add content
    $html = '
    <div style="text-align: center; margin-bottom: 20px;">
        <h1 style="color: #4f46e5;">TEBZ</h1>
        <h2>Payment Receipt</h2>
    </div>
    <div style="margin-bottom: 20px;">
        <p><strong>Receipt Number:</strong> ' . htmlspecialchars($payment['receipt_number']) . '</p>
        <p><strong>Date:</strong> ' . date('F j, Y', strtotime($payment['payment_date'])) . '</p>
    </div>
    <div style="margin-bottom: 20px;">
        <h3>Member Information</h3>
        <p><strong>Name:</strong> ' . htmlspecialchars($payment['first_name'] . ' ' . $payment['last_name']) . '</p>
        <p><strong>Email:</strong> ' . htmlspecialchars($payment['email']) . '</p>
        <p><strong>Phone:</strong> ' . htmlspecialchars($payment['phone']) . '</p>
    </div>
    <div style="margin-bottom: 20px;">
        <h3>Payment Details</h3>
        <p><strong>Amount:</strong> ETB ' . number_format($payment['amount'], 2) . '</p>
        <p><strong>Payment Method:</strong> ' . htmlspecialchars($payment['payment_method']) . '</p>
        <p><strong>Status:</strong> ' . htmlspecialchars($payment['status']) . '</p>
    </div>
    <div style="text-align: center; margin-top: 40px;">
        <p>Thank you for your payment!</p>
        <p>This receipt serves as proof of your payment to TEBZ.</p>
    </div>';

    $pdf->writeHTML($html, true, false, true, false, '');

    // Output the PDF
    $pdf->Output('receipt_' . $payment['receipt_number'] . '.pdf', 'I');
} catch (Exception $e) {
    die('Error generating receipt: ' . $e->getMessage());
} 