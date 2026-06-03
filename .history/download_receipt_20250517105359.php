<?php
session_start();
require_once 'db_config.php';
require_once 'vendor/autoload.php'; // Make sure you have TCPDF installed

if (!isset($_SESSION['user_id'])) {
    header("Location: landing.php");
    exit();
}

$userId = $_SESSION['user_id'];

try {
    // Get the latest confirmed payment receipt
    $query = "SELECT p.*, u.firstName, u.lastName 
              FROM membership_payments p
              JOIN users u ON p.user_id = u.id
              WHERE p.user_id = ? AND p.status = 'Confirmed'
              ORDER BY p.payment_date DESC
              LIMIT 1";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        throw new Exception('No receipt found');
    }

    $receipt = $result->fetch_assoc();

    // Create new PDF document
    $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);

    // Set document information
    $pdf->SetCreator('TEBZ');
    $pdf->SetAuthor('TEBZ Treasurer');
    $pdf->SetTitle('Payment Receipt - ' . $receipt['receipt_number']);

    // Remove default header/footer
    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(false);

    // Add a page
    $pdf->AddPage();

    // Set font
    $pdf->SetFont('helvetica', '', 12);

    // Add logo
    $pdf->Image('img/logo12.png', 15, 15, 30);

    // Add title
    $pdf->SetFont('helvetica', 'B', 20);
    $pdf->Cell(0, 20, 'TEBZ', 0, 1, 'C');
    $pdf->SetFont('helvetica', '', 16);
    $pdf->Cell(0, 10, 'Official Receipt', 0, 1, 'C');
    $pdf->Ln(10);

    // Add receipt details
    $pdf->SetFont('helvetica', '', 12);
    $pdf->Cell(40, 10, 'Receipt Number:', 0);
    $pdf->Cell(0, 10, $receipt['receipt_number'], 0, 1);
    
    $pdf->Cell(40, 10, 'Date:', 0);
    $pdf->Cell(0, 10, date('F j, Y', strtotime($receipt['payment_date'])), 0, 1);
    
    $pdf->Cell(40, 10, 'Amount:', 0);
    $pdf->Cell(0, 10, '₱' . number_format($receipt['amount'], 2), 0, 1);
    
    $pdf->Cell(40, 10, 'Payment Method:', 0);
    $pdf->Cell(0, 10, ucfirst($receipt['method']), 0, 1);
    
    $pdf->Cell(40, 10, 'Paid By:', 0);
    $pdf->Cell(0, 10, $receipt['firstName'] . ' ' . $receipt['lastName'], 0, 1);

    // Add signature lines
    $pdf->Ln(20);
    $pdf->Cell(80, 10, '_____________________', 0, 0, 'C');
    $pdf->Cell(80, 10, '_____________________', 0, 1, 'C');
    $pdf->Cell(80, 5, 'Treasurer\'s Signature', 0, 0, 'C');
    $pdf->Cell(80, 5, 'Member\'s Signature', 0, 1, 'C');

    // Add footer
    $pdf->Ln(20);
    $pdf->SetFont('helvetica', 'I', 10);
    $pdf->Cell(0, 10, 'Thank you for your payment!', 0, 1, 'C');
    $pdf->Cell(0, 10, 'This receipt serves as proof of your payment to TEBZ.', 0, 1, 'C');

    // Output PDF
    $pdf->Output('TEBZ_Receipt_' . $receipt['receipt_number'] . '.pdf', 'D');

} catch (Exception $e) {
    // Redirect to dashboard with error message
    $_SESSION['error'] = 'Error downloading receipt: ' . $e->getMessage();
    header("Location: " . ($_SESSION['user_type'] === 'driver' ? 'driver_dashboard.php' : 'operator_dashboard.php'));
    exit();
}
?> 