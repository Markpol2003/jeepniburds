<?php
session_start();
require_once __DIR__ . '/../db_config.php';

// Set timezone to match server location (Asia/Manila)
date_default_timezone_set('Asia/Manila');

// Set MySQL timezone to match PHP timezone
try {
    $timezone = date_default_timezone_get();
    $offset = date('P'); // Gets offset like +08:00
    $conn->query("SET time_zone = '$offset'");
} catch (Exception $e) {
    // If timezone setting fails, continue (MySQL might not support it)
}

header('Content-Type: application/json');
$data = json_decode(file_get_contents('php://input'), true);
$action = $data['action'] ?? 'pay';

if ($action === 'pay') {
    if (!isset($data['passenger_id'], $data['route'], $data['amount'], $data['payment_method'])) {
        echo json_encode(['success' => false, 'message' => 'Missing required fields.']);
        exit;
    }
    $passenger_id = $data['passenger_id'];
    $route = $data['route'];
    $amount = $data['amount'];
    $payment_method = $data['payment_method'];
    $receipt_number = 'FARE-' . rand(10000,99999);
    $paid_at = date('Y-m-d H:i:s');
    $status = 'Paid';
    $stmt = $conn->prepare("INSERT INTO fare_payments (passenger_id, route, amount, payment_method, receipt_number, paid_at, status) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param('isdssss', $passenger_id, $route, $amount, $payment_method, $receipt_number, $paid_at, $status);
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'receipt' => [
            'receipt_number' => $receipt_number,
            'route' => $route,
            'amount' => $amount,
            'payment_method' => $payment_method,
            'date' => date('Y-m-d H:i:s'),
            'passenger_id' => $passenger_id
        ]]);
    } else {
        echo json_encode(['success' => false, 'message' => $stmt->error]);
    }
    exit;
}

if ($action === 'list') {
    // List ALL fare payments for drivers (not just their assigned route)
    // This allows drivers to see all fare payments regardless of route
    $stmt = $conn->prepare("SELECT fp.*, u.firstName, u.lastName FROM fare_payments fp JOIN users u ON fp.passenger_id = u.id ORDER BY fp.paid_at DESC");
    $stmt->execute();
    $result = $stmt->get_result();
    $fares = [];
    while ($row = $result->fetch_assoc()) {
        $fares[] = [
            'passenger' => $row['firstName'] . ' ' . $row['lastName'],
            'amount' => $row['amount'],
            'payment_method' => $row['payment_method'],
            'status' => $row['status'] ?? 'Paid',
            'paid_at' => $row['paid_at'],
            'receipt_number' => $row['receipt_number'],
            'route' => $row['route'] // Include route information for drivers
        ];
    }
    echo json_encode(['success' => true, 'fares' => $fares]);
    exit;
}

if ($action === 'count') {
    $route = trim($data['route'] ?? '');
    $start = trim($data['start_time'] ?? '');
    $end = trim($data['end_time'] ?? '');
    if ($route === '' || $start === '' || $end === '') {
        echo json_encode(['success' => false, 'message' => 'Missing required params']);
        exit;
    }
    
    // Parse the date and time strings in Asia/Manila timezone
    $manilaTz = new DateTimeZone('Asia/Manila');
    try {
        $startDt = new DateTime($start, $manilaTz);
        $endDt = new DateTime($end, $manilaTz);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Invalid date/time format']);
        exit;
    }
    
    // Create a wider query range to account for timezone differences
    // Query from 1 day before start to 1 day after end to catch all records
    $queryStart = clone $startDt;
    $queryStart->modify('-1 day');
    $queryStart->setTime(0, 0, 0);
    
    $queryEnd = clone $endDt;
    $queryEnd->modify('+1 day');
    $queryEnd->setTime(23, 59, 59);
    
    $queryStartStr = $queryStart->format('Y-m-d H:i:s');
    $queryEndStr = $queryEnd->format('Y-m-d H:i:s');
    $startStr = $startDt->format('Y-m-d H:i:s');
    $endStr = $endDt->format('Y-m-d H:i:s');
    
    // Function to normalize route for comparison - more flexible matching
    $normalizeRoute = function($r) {
        if (empty($r)) return '';
        $r = trim($r);
        // Replace various arrow and dash formats with a standard separator
        $r = str_replace([' → ', ' -> ', ' →', '→ ', ' - ', ' -', '- ', '→', '→'], ' ', $r);
        // Remove extra whitespace
        $r = preg_replace('/\s+/', ' ', $r);
        // Convert to lowercase for case-insensitive comparison
        return strtolower($r);
    };
    
    // Also try to extract origin and destination from route for matching
    // Route format might be "Origin → Destination" or "Origin - Destination"
    $routeParts = preg_split('/[→\->]/', $route);
    $originPart = !empty($routeParts[0]) ? trim(strtolower($routeParts[0])) : '';
    $destPart = !empty($routeParts[1]) ? trim(strtolower($routeParts[1])) : '';
    
    $routeNormalized = $normalizeRoute($route);
    
    // Get all trips (boarded reservations) in the wider range
    // We'll filter by route in PHP after fetching (more flexible)
    $stmt = $conn->prepare("SELECT boarded_at, route, origin_landmark, dest_landmark FROM reservations 
                            WHERE status = 'boarded' 
                            AND boarded_at IS NOT NULL
                            AND boarded_at >= ? 
                            AND boarded_at <= ?
                            ORDER BY boarded_at");
    $stmt->bind_param('ss', $queryStartStr, $queryEndStr);
    $stmt->execute();
    $tripsResult = $stmt->get_result();
    
    // Get all fare payments in the wider range
    // We'll filter by route in PHP after fetching
    $stmt2 = $conn->prepare("SELECT paid_at, status, route FROM fare_payments 
                             WHERE paid_at IS NOT NULL
                             AND paid_at >= ? 
                             AND paid_at <= ?
                             ORDER BY paid_at");
    $stmt2->bind_param('ss', $queryStartStr, $queryEndStr);
    $stmt2->execute();
    $faresResult = $stmt2->get_result();
    
    // Filter trips by route and actual time slot in Manila timezone
    $totalTrips = 0;
    while ($tripsResult && $trip = $tripsResult->fetch_assoc()) {
        if (!$trip['boarded_at']) {
            continue;
        }
        
        // Check route matching - try multiple methods
        $routeMatches = false;
        
        // Method 1: Direct route field match (normalized)
        if (!empty($trip['route'])) {
            $tripRouteNormalized = $normalizeRoute($trip['route']);
            if ($tripRouteNormalized === $routeNormalized) {
                $routeMatches = true;
            }
        }
        
        // Method 2: Match by origin and destination landmarks
        if (!$routeMatches && !empty($originPart) && !empty($destPart)) {
            $tripOrigin = !empty($trip['origin_landmark']) ? strtolower(trim($trip['origin_landmark'])) : '';
            $tripDest = !empty($trip['dest_landmark']) ? strtolower(trim($trip['dest_landmark'])) : '';
            
            // Check if origin and destination contain the route parts
            if (!empty($tripOrigin) && !empty($tripDest)) {
                if ((strpos($tripOrigin, $originPart) !== false || strpos($originPart, $tripOrigin) !== false) &&
                    (strpos($tripDest, $destPart) !== false || strpos($destPart, $tripDest) !== false)) {
                    $routeMatches = true;
                }
            }
        }
        
        // Method 3: Check if route field contains the key parts
        if (!$routeMatches && !empty($trip['route']) && !empty($originPart) && !empty($destPart)) {
            $tripRouteLower = strtolower($trip['route']);
            if (strpos($tripRouteLower, $originPart) !== false && strpos($tripRouteLower, $destPart) !== false) {
                $routeMatches = true;
            }
        }
        
        if (!$routeMatches) {
            continue; // Route doesn't match, skip
        }
        
        try {
            // Try parsing as UTC first (most common)
            try {
                $tripDt = new DateTime($trip['boarded_at'], new DateTimeZone('UTC'));
            } catch (Exception $e) {
                // If that fails, try as local time (might be stored in session timezone)
                $tripDt = new DateTime($trip['boarded_at']);
            }
            $tripDt->setTimezone($manilaTz);
            $tripTime = $tripDt->format('Y-m-d H:i:s');
            
            // Check if within the time slot
            if ($tripTime >= $startStr && $tripTime <= $endStr) {
                $totalTrips++;
            }
        } catch (Exception $e) {
            // Skip records that can't be parsed
            continue;
        }
    }
    
    // Filter collected fares by route and actual time slot in Manila timezone
    $collectedFares = 0;
    while ($faresResult && $fare = $faresResult->fetch_assoc()) {
        if ($fare['paid_at'] && $fare['route'] && ($fare['status'] === 'Collected' || $fare['status'] === 'Paid')) {
            // Check if route matches (after normalization)
            $fareRouteNormalized = $normalizeRoute($fare['route']);
            if ($fareRouteNormalized !== $routeNormalized) {
                continue; // Route doesn't match, skip
            }
            
            try {
                // Try parsing as UTC first (most common)
                try {
                    $fareDt = new DateTime($fare['paid_at'], new DateTimeZone('UTC'));
                } catch (Exception $e) {
                    // If that fails, try as local time (might be stored in session timezone)
                    $fareDt = new DateTime($fare['paid_at']);
                }
                $fareDt->setTimezone($manilaTz);
                $fareTime = $fareDt->format('Y-m-d H:i:s');
                
                // Count as collected only if status is 'Collected' (confirmed by driver)
                if ($fareTime >= $startStr && $fareTime <= $endStr && $fare['status'] === 'Collected') {
                    $collectedFares++;
                }
            } catch (Exception $e) {
                // Skip records that can't be parsed
                continue;
            }
        }
    }
    
    $stmt->close();
    $stmt2->close();
    
    echo json_encode([
        'success' => true,
        'total' => $totalTrips,
        'compliant' => $collectedFares
    ]);
    exit;
}

if ($action === 'confirm') {
    if (!isset($data['receipt_number'])) {
        echo json_encode(['success' => false, 'message' => 'Missing receipt number.']);
        exit;
    }
    $receipt_number = $data['receipt_number'];
    // Check if already collected
    $check = $conn->prepare("SELECT status FROM fare_payments WHERE receipt_number = ?");
    $check->bind_param('s', $receipt_number);
    $check->execute();
    $result = $check->get_result();
    if ($row = $result->fetch_assoc()) {
        if ($row['status'] === 'Collected') {
            echo json_encode(['success' => true, 'message' => 'Already collected.']);
            exit;
        }
    }
    $stmt = $conn->prepare("UPDATE fare_payments SET status = 'Collected' WHERE receipt_number = ?");
    $stmt->bind_param('s', $receipt_number);
    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => $stmt->error]);
    }
    exit;
} 