<?php
session_start();
require_once 'db_config.php';
require('fpdf/fpdf.php');

if (!isset($_GET['receipt_number'])) {
    die('Receipt number not provided');
}

$receiptNumber = $_GET['receipt_number'];

// Fetch payment details
$query = "SELECT mp.*, u.firstName, u.lastName, u.userType, pd.* 
          FROM membership_payments mp 
          JOIN users u ON mp.user_id = u.id 
          LEFT JOIN payment_details pd ON mp.receipt_number = pd.receipt_number 
          WHERE mp.receipt_number = ?";

$stmt = $conn->prepare($query);
$stmt->bind_param("s", $receiptNumber);
$stmt->execute();
$result = $stmt->get_result();
$payment = $result->fetch_assoc();

if (!$payment) {
    die('Receipt not found');
}

// Create PDF
class PDF extends FPDF {
    function Header() {
        // Logo
        $this->Image('img/logo12.png', 10, 10, 30);
        // Title
        $this->SetFont('Arial', 'B', 15);
        $this->Cell(0, 10, 'TEBZ Payment Receipt', 0, 1, 'C');
        $this->Ln(10);
    }
    
    function Footer() {
        $this->SetY(-15);
        $this->SetFont('Arial', 'I', 8);
        $this->Cell(0, 10, 'Page ' . $this->PageNo() . '/{nb}', 0, 0, 'C');
    }
}

$pdf = new PDF();
$pdf->AliasNbPages();
$pdf->AddPage();

// Receipt Details
$pdf->SetFont('Arial', 'B', 12);
$pdf->Cell(0, 10, 'Receipt Details', 0, 1, 'L');
$pdf->SetFont('Arial', '', 10);

$pdf->Cell(40, 7, 'Receipt Number:', 0);
$pdf->Cell(0, 7, $payment['receipt_number'], 0, 1);

$pdf->Cell(40, 7, 'Date:', 0);
$pdf->Cell(0, 7, date('F d, Y', strtotime($payment['payment_date'])), 0, 1);

$pdf->Cell(40, 7, 'Amount:', 0);
$pdf->Cell(0, 7, '₱' . number_format($payment['amount'], 2), 0, 1);

$pdf->Cell(40, 7, 'Payment Method:', 0);
$pdf->Cell(0, 7, ucfirst($payment['method']), 0, 1);

$pdf->Cell(40, 7, 'Status:', 0);
$pdf->Cell(0, 7, $payment['status'], 0, 1);

$pdf->Ln(5);

// Payer Information
$pdf->SetFont('Arial', 'B', 12);
$pdf->Cell(0, 10, 'Payer Information', 0, 1, 'L');
$pdf->SetFont('Arial', '', 10);

$pdf->Cell(40, 7, 'Name:', 0);
$pdf->Cell(0, 7, $payment['firstName'] . ' ' . $payment['lastName'], 0, 1);

$pdf->Cell(40, 7, 'Account Type:', 0);
$pdf->Cell(0, 7, ucfirst($payment['userType']), 0, 1);

$pdf->Ln(5);

// Payment Details
$pdf->SetFont('Arial', 'B', 12);
$pdf->Cell(0, 10, 'Payment Details', 0, 1, 'L');
$pdf->SetFont('Arial', '', 10);

switch ($payment['method']) {
    case 'gcash':
        $pdf->Cell(40, 7, 'GCash Number:', 0);
        $pdf->Cell(0, 7, $payment['gcash_number'], 0, 1);
        $pdf->Cell(40, 7, 'GCash Name:', 0);
        $pdf->Cell(0, 7, $payment['gcash_name'], 0, 1);
        break;
    case 'bank':
        $pdf->Cell(40, 7, 'Bank Name:', 0);
        $pdf->Cell(0, 7, $payment['bank_name'], 0, 1);
        $pdf->Cell(40, 7, 'Account Number:', 0);
        $pdf->Cell(0, 7, $payment['bank_account'], 0, 1);
        $pdf->Cell(40, 7, 'Account Name:', 0);
        $pdf->Cell(0, 7, $payment['bank_account_name'], 0, 1);
        break;
    case 'cash':
        $pdf->Cell(40, 7, 'Reference Number:', 0);
        $pdf->Cell(0, 7, $payment['reference_number'], 0, 1);
        break;
}

$pdf->Ln(10);

// Footer Message
$pdf->SetFont('Arial', 'I', 10);
$pdf->Cell(0, 7, 'This receipt serves as proof of your payment to TEBZ.', 0, 1, 'C');
$pdf->Cell(0, 7, 'Thank you for your payment!', 0, 1, 'C');

// Output PDF
$pdf->Output('I', 'TEBZ_Receipt_' . $receiptNumber . '.pdf'); 