<?php
session_start();
require_once 'db_config.php';
require_once 'vendor/autoload.php'; // Make sure TCPDF is installed via Composer

use TCPDF;

// Get receipt data from database
$userId = $_SESSION['user_id'];
$receiptQuery = "SELECT p.*, u.firstName, u.lastName 
                FROM membership_payments p 
                JOIN users u ON p.user_id = u.id 
                WHERE p.user_id = ? AND p.status = 'Confirmed' 
                ORDER BY p.payment_date DESC LIMIT 1";
$stmt = $conn->prepare($receiptQuery);
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();
$payment = $result->fetch_assoc();

if (!$payment) {
    die("No receipt found");
}

// Create new PDF document
$pdf = new TCPDF('P', 'mm', 'A5', true, 'UTF-8', false);

// Set document information
$pdf->SetCreator('JeepniGo');
$pdf->SetAuthor('JeepniGo System');
$pdf->SetTitle('Payment Receipt');

// Remove default header/footer
$pdf->setPrintHeader(false);
$pdf->setPrintFooter(false);

// Set margins
$pdf->SetMargins(10, 10, 10);

// Add a page
$pdf->AddPage();

// Set font
$pdf->SetFont('helvetica', '', 10);

// Logo
$pdf->Image('img/logo12.png', 10, 10, 30);

// Title
$pdf->SetFont('helvetica', 'B', 16);
$pdf->Cell(0, 10, 'JeepniGo', 0, 1, 'C');
$pdf->SetFont('helvetica', '', 12);
$pdf->Cell(0, 5, 'Official Receipt', 0, 1, 'C');
$pdf->Ln(5);

// Receipt details
$pdf->SetFont('helvetica', '', 10);

// Receipt Number
$pdf->Cell(40, 7, 'Receipt Number:', 0);
$pdf->Cell(0, 7, $payment['receipt_number'], 0, 1);

// Date
$pdf->Cell(40, 7, 'Date:', 0);
$pdf->Cell(0, 7, date('F j, Y', strtotime($payment['payment_date'])), 0, 1);

// Amount
$pdf->Cell(40, 7, 'Amount:', 0);
$pdf->Cell(0, 7, '₱' . number_format($payment['amount'], 2), 0, 1);

// Payment Method
$pdf->Cell(40, 7, 'Payment Method:', 0);
$pdf->Cell(0, 7, ucfirst($payment['method']), 0, 1);

// Member Name
$pdf->Cell(40, 7, 'Paid By:', 0);
$pdf->Cell(0, 7, $payment['firstName'] . ' ' . $payment['lastName'], 0, 1);

// Status
$pdf->Cell(40, 7, 'Status:', 0);
$pdf->Cell(0, 7, ucfirst($payment['status']), 0, 1);

$pdf->Ln(10);

// Signatures
$pdf->Cell(85, 7, 'Treasurer\'s Signature', 0, 0, 'C');
$pdf->Cell(85, 7, 'Member\'s Signature', 0, 1, 'C');

$pdf->Ln(5);

$pdf->Cell(85, 7, '_____________________', 0, 0, 'C');
$pdf->Cell(85, 7, '_____________________', 0, 1, 'C');

$pdf->Cell(85, 7, 'JeepniGo Treasurer', 0, 0, 'C');
$pdf->Cell(85, 7, $payment['firstName'] . ' ' . $payment['lastName'], 0, 1, 'C');

// Output the PDF
$pdf->Output('JeepniGo_Receipt.pdf', 'I');
?> 