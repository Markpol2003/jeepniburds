<?php
session_start();
require_once 'db_config.php';
require_once 'vendor/autoload.php'; // Make sure you have TCPDF installed via Composer

// Check if user is logged in and is either a driver or operator
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_type'], ['driver', 'operator'])) {
    header('HTTP/1.1 401 Unauthorized');
    exit('Unauthorized access');
}

// Get user ID from session
$userId = $_SESSION['user_id'];

try {
    // Get the latest confirmed payment for the user
    $query = "SELECT mp.*, u.email, u.firstName, u.lastName 
              FROM membership_payments mp 
              JOIN users u ON mp.user_id = u.id 
              WHERE mp.user_id = ? AND mp.status = 'Confirmed' 
              ORDER BY mp.payment_date DESC 
              LIMIT 1";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        header('HTTP/1.1 404 Not Found');
        exit('Receipt not found');
    }
    
    $payment = $result->fetch_assoc();
    
    // Create new PDF document
    $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
    
    // Set document information
    $pdf->SetCreator('TEBZ');
    $pdf->SetAuthor('TEBZ System');
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
    <div style="text-align: center;">
        <h1 style="color: #4f46e5;">TEBZ</h1>
        <h2>Payment Receipt</h2>
    </div>
    <br><br>
    <div style="margin: 20px 0;">
        <p><strong>Receipt Number:</strong> ' . htmlspecialchars($payment['receipt_number']) . '</p>
        <p><strong>Date:</strong> ' . date('F j, Y', strtotime($payment['payment_date'])) . '</p>
        <p><strong>Amount:</strong> ₱' . number_format($payment['amount'], 2) . '</p>
        <p><strong>Payment Method:</strong> ' . ucfirst($payment['method']) . '</p>
        <p><strong>Paid By:</strong> ' . htmlspecialchars($payment['firstName'] . ' ' . $payment['lastName']) . '</p>
    </div>
    <br><br>
    <div style="text-align: center; color: #666;">
        <p>Thank you for your payment!</p>
        <p>This receipt serves as proof of your payment to TEBZ.</p>
    </div>';
    
    $pdf->writeHTML($html, true, false, true, false, '');
    
    // Output PDF
    $pdf->Output('payment_receipt.pdf', 'D');
    
} catch (Exception $e) {
    error_log("Receipt Download Error: " . $e->getMessage());
    header('HTTP/1.1 500 Internal Server Error');
    exit('Failed to generate receipt');
}
?> 