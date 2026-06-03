<?php
// Prevent any output before JSON response
error_reporting(0);
ini_set('display_errors', 0);

session_start();
require_once __DIR__ . '/../db_config.php';

// Set content type to JSON
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
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
    
    if ($result->num_rows > 0) {
        $receipt = $result->fetch_assoc();
        echo json_encode([
            'success' => true,
            'receipt' => [
                'receipt_number' => $receipt['receipt_number'],
                'date' => $receipt['payment_date'],
                'amount' => $receipt['amount'],
                'method' => $receipt['method'],
                'name' => $receipt['firstName'] . ' ' . $receipt['lastName']
            ]
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'No receipt found']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error retrieving receipt: ' . $e->getMessage()]);
}

exit();
?>