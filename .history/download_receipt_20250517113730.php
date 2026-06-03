<?php
session_start();
require_once 'db_config.php';
require_once 'vendor/autoload.php'; // Make sure you have TCPDF installed via Composer

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: landing.php");
    exit();
}

if (!isset($_GET['receipt_id'])) {
    die("Receipt ID not provided");
}

$receiptId = intval($_GET['receipt_id']);
$userId = $_SESSION['user_id'];

// Fetch receipt details
$query = "SELECT r.*, p.payment_method, p.created_at as payment_date, u.firstName, u.lastName, u.middleName
          FROM user_receipts r 
          JOIN membership_payments p ON r.payment_id = p.id 
          JOIN users u ON r.user_id = u.id
          WHERE r.id = ? AND r.user_id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("ii", $receiptId, $userId);
$stmt->execute();
$result = $stmt->get_result();
$receipt = $result->fetch_assoc();

if (!$receipt) {
    die("Receipt not found or unauthorized access");
}

// Create new PDF document
$pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);

// Set document information
$pdf->SetCreator(PDF_CREATOR);
$pdf->SetAuthor('TEBZ');
$pdf->SetTitle('Payment Receipt - ' . $receipt['receipt_number']);

// Remove default header/footer
$pdf->setPrintHeader(false);
$pdf->setPrintFooter(false);

// Set margins
$pdf->SetMargins(15, 15, 15);

// Add a page
$pdf->AddPage();

// Set font
$pdf->SetFont('helvetica', '', 12);

// Add content
$html = '
<div style="text-align: center;">
    <h1>TEBZ</h1>
    <h2>Payment Receipt</h2>
</div>
<br><br>
<div style="text-align: right;">
    <p>Date: ' . date('F d, Y', strtotime($receipt['payment_date'])) . '</p>
    <p>Receipt #: ' . htmlspecialchars($receipt['receipt_number']) . '</p>
</div>
<br><br>
<div>
    <p><strong>Payee:</strong> ' . htmlspecialchars($receipt['firstName'] . ' ' . $receipt['middleName'] . ' ' . $receipt['lastName']) . '</p>
    <p><strong>Amount:</strong> ₱' . number_format($receipt['amount'], 2) . '</p>
    <p><strong>Payment Method:</strong> ' . ucfirst(htmlspecialchars($receipt['payment_method'])) . '</p>
    <p><strong>Status:</strong> ' . htmlspecialchars($receipt['status']) . '</p>
</div>
<br><br>
<div style="text-align: center; margin-top: 50px;">
    <p>This receipt serves as proof of your payment to TEBZ.</p>
    <p>Thank you for your payment!</p>
</div>';

$pdf->writeHTML($html, true, false, true, false, '');

// Output the PDF
$pdf->Output('receipt_' . $receipt['receipt_number'] . '.pdf', 'D');
?> 