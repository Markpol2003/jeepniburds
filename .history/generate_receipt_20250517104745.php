<?php
session_start();
require_once 'db_config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'treasurer') {
    header("Location: landing.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['payment_id'])) {
    try {
        // CSRF Protection
        if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
            throw new Exception('Invalid request');
        }

        $paymentId = intval($_POST['payment_id']);
        
        // Get payment details with proper error handling
        $query = "SELECT p.*, u.firstName, u.lastName, u.email 
                  FROM membership_payments p 
                  JOIN users u ON p.user_id = u.id 
                  WHERE p.id = ?";
        $stmt = $conn->prepare($query);
        if (!$stmt) {
            throw new Exception('Database prepare error: ' . $conn->error);
        }
        
        $stmt->bind_param('i', $paymentId);
        if (!$stmt->execute()) {
            throw new Exception('Database execute error: ' . $stmt->error);
        }
        
        $result = $stmt->get_result();
        $payment = $result->fetch_assoc();

        if (!$payment) {
            throw new Exception('Payment not found');
        }

        // Generate receipt number (format: REC-YYYYMMDD-XXXX)
        $receiptNumber = 'REC-' . date('Ymd') . '-' . str_pad($paymentId, 4, '0', STR_PAD_LEFT);
        
        // Update payment with receipt number
        $updateQuery = "UPDATE membership_payments SET receipt_number = ? WHERE id = ?";
        $updateStmt = $conn->prepare($updateQuery);
        if (!$updateStmt) {
            throw new Exception('Database prepare error: ' . $conn->error);
        }
        
        $updateStmt->bind_param('si', $receiptNumber, $paymentId);
        if (!$updateStmt->execute()) {
            throw new Exception('Database execute error: ' . $updateStmt->error);
        }

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

        // Get absolute path for logo
        $logoPath = $_SERVER['DOCUMENT_ROOT'] . '/tebz/img/logo12.png';
        if (!file_exists($logoPath)) {
            throw new Exception('Logo file not found');
        }

        // Add content
        $html = '
        <div style="text-align: center; margin-bottom: 20px;">
            <img src="' . $logoPath . '" style="width: 100px; height: auto; margin-bottom: 10px;">
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
                    <td style="padding: 5px;">' . htmlspecialchars($receiptNumber) . '</td>
                    <td style="padding: 5px;"><strong>Date:</strong></td>
                    <td style="padding: 5px;">' . date('F d, Y', strtotime($payment['payment_date'])) . '</td>
                </tr>
                <tr>
                    <td style="padding: 5px;"><strong>Member Name:</strong></td>
                    <td style="padding: 5px;">' . htmlspecialchars($payment['firstName'] . ' ' . $payment['lastName']) . '</td>
                    <td style="padding: 5px;"></td>
                    <td style="padding: 5px;"></td>
                </tr>
                <tr>
                    <td style="padding: 5px;"><strong>Amount:</strong></td>
                    <td style="padding: 5px;">₱' . number_format($payment['amount'], 2) . '</td>
                    <td style="padding: 5px;"><strong>Payment Method:</strong></td>
                    <td style="padding: 5px;">' . htmlspecialchars(ucfirst($payment['method'])) . '</td>
                </tr>
                <tr>
                    <td style="padding: 5px;"><strong>Reference Number:</strong></td>
                    <td style="padding: 5px;">' . htmlspecialchars($payment['reference_number'] ?? 'N/A') . '</td>
                    <td style="padding: 5px;"></td>
                    <td style="padding: 5px;"></td>
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
        
        // Load email credentials from environment variables or config file
        $mail->Username = getenv('SMTP_USERNAME') ?: 'your-email@gmail.com';
        $mail->Password = getenv('SMTP_PASSWORD') ?: 'your-password';
        
        $mail->SMTPSecure = 'tls';
        $mail->Port = 587;

        $mail->setFrom('noreply@jeepnigo.com', 'JeepniGo');
        $mail->addAddress($payment['email'], $payment['firstName'] . ' ' . $payment['lastName']);
        $mail->Subject = 'JeepniGo - Payment Receipt';
        $mail->Body = 'Dear ' . htmlspecialchars($payment['firstName'] . ' ' . $payment['lastName']) . ",\n\n" .
                     'Thank you for your payment. Please find attached your receipt.\n\n' .
                     'Best regards,\nJeepniGo Team';
        $mail->addStringAttachment($pdfContent, 'receipt.pdf');

        if (!$mail->send()) {
            throw new Exception('Failed to send email: ' . $mail->ErrorInfo);
        }

        $_SESSION['message'] = 'Receipt generated and sent successfully!';
        
    } catch (Exception $e) {
        $_SESSION['error'] = 'Error: ' . $e->getMessage();
        error_log('Receipt generation error: ' . $e->getMessage());
    }
}

header("Location: treasurer_dashboard.php");
exit();
?> 