<?php
session_start();
require_once 'db_config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'treasurer') {
    header("Location: landing.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['payment_id'])) {
    $paymentId = intval($_POST['payment_id']);
    
    // Get payment details
    $query = "SELECT p.*, u.firstName, u.lastName, u.email 
              FROM membership_payments p 
              JOIN users u ON p.user_id = u.id 
              WHERE p.id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param('i', $paymentId);
    $stmt->execute();
    $result = $stmt->get_result();
    $payment = $result->fetch_assoc();

    if ($payment) {
        // Generate receipt number (format: REC-YYYYMMDD-XXXX)
        $receiptNumber = 'REC-' . date('Ymd') . '-' . str_pad($paymentId, 4, '0', STR_PAD_LEFT);
        
        // Update payment with receipt number
        $updateQuery = "UPDATE membership_payments SET receipt_number = ? WHERE id = ?";
        $updateStmt = $conn->prepare($updateQuery);
        $updateStmt->bind_param('si', $receiptNumber, $paymentId);
        $updateStmt->execute();

        // Generate PDF receipt
        require_once('tcpdf/tcpdf.php');

        // Create new PDF document
        $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);

        // Set document information
        $pdf->SetCreator('JeepniGo');
        $pdf->SetAuthor('JeepniGo Treasurer');
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
            <h1>JeepniGo</h1>
            <h2>Payment Receipt</h2>
        </div>
        <br><br>
        <table>
            <tr>
                <td><strong>Receipt No:</strong></td>
                <td>' . $receiptNumber . '</td>
            </tr>
            <tr>
                <td><strong>Date:</strong></td>
                <td>' . date('F d, Y', strtotime($payment['payment_date'])) . '</td>
            </tr>
            <tr>
                <td><strong>Member Name:</strong></td>
                <td>' . htmlspecialchars($payment['firstName'] . ' ' . $payment['lastName']) . '</td>
            </tr>
            <tr>
                <td><strong>Amount:</strong></td>
                <td>₱' . number_format($payment['amount'], 2) . '</td>
            </tr>
            <tr>
                <td><strong>Payment Method:</strong></td>
                <td>' . ucfirst($payment['method']) . '</td>
            </tr>
            <tr>
                <td><strong>Reference Number:</strong></td>
                <td>' . htmlspecialchars($payment['reference_number'] ?? 'N/A') . '</td>
            </tr>
        </table>
        <br><br>
        <div style="text-align: center;">
            <p>This receipt serves as proof of payment for your membership fee.</p>
            <p>Thank you for choosing JeepniGo!</p>
        </div>';

        $pdf->writeHTML($html, true, false, true, false, '');

        // Generate PDF file
        $pdfContent = $pdf->Output('receipt.pdf', 'S');

        // Send email with PDF attachment
        require_once('PHPMailer/PHPMailerAutoload.php');
        $mail = new PHPMailer;
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'your-email@gmail.com'; // Replace with your email
        $mail->Password = 'your-password'; // Replace with your password
        $mail->SMTPSecure = 'tls';
        $mail->Port = 587;

        $mail->setFrom('noreply@jeepnigo.com', 'JeepniGo');
        $mail->addAddress($payment['email'], $payment['firstName'] . ' ' . $payment['lastName']);
        $mail->Subject = 'JeepniGo - Payment Receipt';
        $mail->Body = 'Dear ' . $payment['firstName'] . ' ' . $payment['lastName'] . ",\n\n" .
                     'Thank you for your payment. Please find attached your receipt.\n\n' .
                     'Best regards,\nJeepniGo Team';
        $mail->addStringAttachment($pdfContent, 'receipt.pdf');

        if ($mail->send()) {
            $_SESSION['message'] = 'Receipt generated and sent successfully!';
        } else {
            $_SESSION['message'] = 'Payment confirmed but failed to send receipt. Error: ' . $mail->ErrorInfo;
        }
    }

    header("Location: treasurer_dashboard.php");
    exit();
}
?> 