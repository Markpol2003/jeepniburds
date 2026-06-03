<?php
session_start();
require_once 'db_config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: landing.php');
    exit();
}

try {
    $userId = $_SESSION['user_id'];
    
    // Get the latest receipt for the user
    $stmt = $conn->prepare("
        SELECT r.*, p.reference_number, p.proof_of_payment
        FROM user_receipts r
        JOIN membership_payments p ON r.payment_id = p.id
        WHERE r.user_id = ? AND r.status = 'Confirmed'
        ORDER BY r.created_at DESC
        LIMIT 1
    ");
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $receipt = $result->fetch_assoc();
        
        // Generate PDF content
        require_once 'vendor/autoload.php'; // Make sure you have TCPDF installed
        
        $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
        
        // Set document information
        $pdf->SetCreator('TEBZ');
        $pdf->SetAuthor('TEBZ');
        $pdf->SetTitle('Payment Receipt');
        
        // Remove default header/footer
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        
        // Add a page
        $pdf->AddPage();
        
        // Set font
        $pdf->SetFont('helvetica', '', 12);
        
        // Add content
        $html = '
        <div style="text-align: center;">
            <h1 style="color: #28a745;">TEBZ</h1>
            <h2>Payment Receipt</h2>
        </div>
        <hr>
        <table style="width: 100%;">
            <tr>
                <td style="width: 40%;"><strong>Receipt Number:</strong></td>
                <td>' . htmlspecialchars($receipt['receipt_number']) . '</td>
            </tr>
            <tr>
                <td><strong>Date:</strong></td>
                <td>' . date('F d, Y', strtotime($receipt['payment_date'])) . '</td>
            </tr>
            <tr>
                <td><strong>Amount:</strong></td>
                <td>₱' . number_format($receipt['amount'], 2) . '</td>
            </tr>
            <tr>
                <td><strong>Payment Method:</strong></td>
                <td>' . ucfirst($receipt['payment_method']) . '</td>
            </tr>
            <tr>
                <td><strong>Paid By:</strong></td>
                <td>' . htmlspecialchars($_SESSION['user_firstName'] . ' ' . $_SESSION['user_lastName']) . '</td>
            </tr>
            <tr>
                <td><strong>Status:</strong></td>
                <td><span style="color: #28a745;">Confirmed</span></td>
            </tr>
        </table>
        <hr>
        <p style="text-align: center; color: #6c757d;">
            Thank you for your payment!<br>
            This receipt serves as proof of your payment to TEBZ.
        </p>
        ';
        
        $pdf->writeHTML($html, true, false, true, false, '');
        
        // Output PDF
        $pdf->Output('TEBZ_Receipt_' . $receipt['receipt_number'] . '.pdf', 'D');
    } else {
        throw new Exception('No receipt found');
    }
} catch (Exception $e) {
    // Redirect back to dashboard with error message
    $_SESSION['error'] = 'Failed to download receipt: ' . $e->getMessage();
    header('Location: driver_dashboard.php?page=payment');
    exit();
}
?> 