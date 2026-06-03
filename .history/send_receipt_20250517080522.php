<?php
require_once 'config/database.php';
require_once 'includes/functions.php';
require_once 'vendor/autoload.php';

// Check if user is logged in
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

// Get payment ID from POST data
$data = json_decode(file_get_contents('php://input'), true);
$payment_id = isset($data['payment_id']) ? (int)$data['payment_id'] : 0;

if (!$payment_id) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Invalid payment ID']);
    exit();
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
        throw new Exception('Payment not found');
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

    // Save PDF to temporary file
    $temp_file = tempnam(sys_get_temp_dir(), 'receipt_');
    $pdf->Output($temp_file, 'F');

    // Send email with PDF attachment
    $mail = new PHPMailer\PHPMailer\PHPMailer(true);

    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com'; // Replace with your SMTP server
        $mail->SMTPAuth = true;
        $mail->Username = 'your-email@gmail.com'; // Replace with your email
        $mail->Password = 'your-app-password'; // Replace with your app password
        $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;

        // Recipients
        $mail->setFrom('your-email@gmail.com', 'TEBZ');
        $mail->addAddress($payment['email'], $payment['first_name'] . ' ' . $payment['last_name']);

        // Content
        $mail->isHTML(true);
        $mail->Subject = 'Your TEBZ Payment Receipt - ' . $payment['receipt_number'];
        $mail->Body = '
            <div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;">
                <h2 style="color: #4f46e5;">Payment Receipt</h2>
                <p>Dear ' . htmlspecialchars($payment['first_name']) . ',</p>
                <p>Thank you for your payment to TEBZ. Please find your receipt attached to this email.</p>
                <p>Payment Details:</p>
                <ul>
                    <li>Receipt Number: ' . htmlspecialchars($payment['receipt_number']) . '</li>
                    <li>Amount: ETB ' . number_format($payment['amount'], 2) . '</li>
                    <li>Date: ' . date('F j, Y', strtotime($payment['payment_date'])) . '</li>
                </ul>
                <p>If you have any questions, please don\'t hesitate to contact us.</p>
                <p>Best regards,<br>TEBZ Team</p>
            </div>';

        // Attach PDF
        $mail->addAttachment($temp_file, 'receipt_' . $payment['receipt_number'] . '.pdf');

        $mail->send();

        // Delete temporary file
        unlink($temp_file);

        header('Content-Type: application/json');
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        // Delete temporary file
        if (file_exists($temp_file)) {
            unlink($temp_file);
        }
        throw new Exception('Failed to send email: ' . $mail->ErrorInfo);
    }
} catch (Exception $e) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
} 