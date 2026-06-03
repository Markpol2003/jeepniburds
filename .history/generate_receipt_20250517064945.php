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
        <div style="text-align: center; margin-bottom: 20px;">
            <img src="img/logo12.png" style="width: 100px; height: auto; margin-bottom: 10px;">
            <h1 style="color: #1a237e; margin: 0;">JeepniGo</h1>
            <p style="margin: 5px 0;">Official Receipt</p>
            <p style="margin: 5px 0; font-size: 12px;">BIR Permit No. 123456789</p>
            <p style="margin: 5px 0; font-size: 12px;">TIN: 123-456-789-000</p>
            <p style="margin: 5px 0; font-size: 12px;">Business Address: 123 Jeepney Street, Manila, Philippines</p>
        </div>
        <div style="border: 1px solid #ccc; padding: 15px; margin-bottom: 20px;">
            <table style="width: 100%; border-collapse: collapse;">
                <tr>
                    <td style="padding: 5px;"><strong>Receipt No:</strong></td>
                    <td style="padding: 5px;">' . $receiptNumber . '</td>
                    <td style="padding: 5px;"><strong>Date:</strong></td>
                    <td style="padding: 5px;">' . date('F d, Y', strtotime($payment['payment_date'])) . '</td>
                </tr>
                <tr>
                    <td style="padding: 5px;"><strong>Member Name:</strong></td>
                    <td style="padding: 5px;" colspan="3">' . htmlspecialchars($payment['firstName'] . ' ' . $payment['lastName']) . '</td>
                </tr>
                <tr>
                    <td style="padding: 5px;"><strong>Amount:</strong></td>
                    <td style="padding: 5px;">₱' . number_format($payment['amount'], 2) . '</td>
                    <td style="padding: 5px;"><strong>Payment Method:</strong></td>
                    <td style="padding: 5px;">' . ucfirst($payment['method']) . '</td>
                </tr>
                <tr>
                    <td style="padding: 5px;"><strong>Reference Number:</strong></td>
                    <td style="padding: 5px;" colspan="3">' . htmlspecialchars($payment['reference_number'] ?? 'N/A') . '</td>
                </tr>
            </table>
        </div>
        <div style="text-align: center; margin-bottom: 20px;">
            <p style="margin: 5px 0;">This receipt serves as proof of payment for your membership fee.</p>
            <p style="margin: 5px 0;">Thank you for choosing JeepniGo!</p>
        </div>
        <div style="border-top: 1px solid #ccc; padding-top: 20px;">
            <table style="width: 100%;">
                <tr>
                    <td style="width: 50%; text-align: center;">
                        <p style="margin: 0;">_______________________</p>
                        <p style="margin: 0;">Treasurer\'s Signature</p>
                    </td>
                    <td style="width: 50%; text-align: center;">
                        <p style="margin: 0;">_______________________</p>
                        <p style="margin: 0;">Member\'s Signature</p>
                    </td>
                </tr>
            </table>
        </div>
        <div style="text-align: center; margin-top: 20px; font-size: 10px; color: #666;">
            <p style="margin: 0;">This is a computer-generated receipt and does not require a physical signature.</p>
            <p style="margin: 0;">For any concerns, please contact us at support@jeepnigo.com</p>
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