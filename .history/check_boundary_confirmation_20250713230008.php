<?php
session_start();
require_once 'db_config.php';

header('Content-Type: application/json');

// Check if user is logged in and is a driver
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'driver') {
    echo json_encode([
        'success' => false,
        'message' => 'Unauthorized access'
    ]);
    exit;
}

// Get POST data
$input = json_decode(file_get_contents('php://input'), true);
$driver_id = $input['driver_id'] ?? null;
$operator_id = $input['operator_id'] ?? null;

if (!$driver_id || !$operator_id) {
    echo json_encode([
        'success' => false,
        'message' => 'Missing required parameters'
    ]);
    exit;
}

try {
    // Check if there's a confirmed boundary payment for today
    $today = date('Y-m-d');
    
    $query = "
        SELECT bp.*, 
               DATEDIFF(CURDATE(), bp.paid_at) as days_ago,
               (SELECT COUNT(*) FROM boundary_payments 
                WHERE driver_id = bp.driver_id 
                AND status = 'Confirmed' 
                AND DATE(paid_at) >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)) as payment_streak
        FROM boundary_payments bp
        WHERE bp.driver_id = ? 
        AND bp.operator_id = ? 
        AND bp.status = 'Confirmed'
        AND DATE(bp.paid_at) = ?
        ORDER BY bp.paid_at DESC
        LIMIT 1
    ";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("iis", $driver_id, $operator_id, $today);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $payment = $result->fetch_assoc();
        
        echo json_encode([
            'success' => true,
            'is_confirmed' => true,
            'payment' => [
                'id' => $payment['id'],
                'amount' => $payment['amount'],
                'payment_method' => $payment['payment_method'],
                'status' => $payment['status'],
                'paid_at' => $payment['paid_at'],
                'confirmed_at' => $payment['confirmed_at'] ?? null
            ],
            'streak' => $payment['payment_streak'] ?? 1,
            'message' => 'Boundary payment confirmed for today'
        ]);
    } else {
        // Check if there's a pending payment for today
        $pendingQuery = "
            SELECT COUNT(*) as pending_count
            FROM boundary_payments 
            WHERE driver_id = ? 
            AND operator_id = ? 
            AND status = 'Pending'
            AND DATE(paid_at) = ?
        ";
        
        $pendingStmt = $conn->prepare($pendingQuery);
        $pendingStmt->bind_param("iis", $driver_id, $operator_id, $today);
        $pendingStmt->execute();
        $pendingResult = $pendingStmt->get_result();
        $pendingCount = $pendingResult->fetch_assoc()['pending_count'];
        
        echo json_encode([
            'success' => true,
            'is_confirmed' => false,
            'has_pending' => $pendingCount > 0,
            'message' => $pendingCount > 0 ? 'Payment pending confirmation' : 'No payment found for today'
        ]);
    }
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
?> 