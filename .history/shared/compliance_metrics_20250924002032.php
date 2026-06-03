<?php
require_once __DIR__ . '/../db_config.php';
header('Content-Type: application/json');

$action = $_POST['action'] ?? $_GET['action'] ?? '';

switch ($action) {
    case 'get_daily':
        $date = $_POST['date'] ?? date('Y-m-d');
        $stmt = $conn->prepare("
            SELECT
                b.route,
                COUNT(*) as total_boarded,
                COUNT(CASE WHEN EXISTS (
                    SELECT 1 FROM fare_payments fp
                    WHERE fp.passenger_id = b.passenger_id
                    AND fp.route = b.route
                    AND DATE(fp.paid_at) = ?
                ) THEN 1 END) as total_paid,
                ROUND(
                    CASE WHEN COUNT(*) > 0
                    THEN (COUNT(CASE WHEN EXISTS (
                        SELECT 1 FROM fare_payments fp
                        WHERE fp.passenger_id = b.passenger_id
                        AND fp.route = b.route
                        AND DATE(fp.paid_at) = ?
                    ) THEN 1 END) * 100.0 / COUNT(*))
                    ELSE 0 END, 2
                ) as compliance_rate
            FROM boarding_events b
            WHERE DATE(b.boarded_at) = ?
            GROUP BY b.route
            ORDER BY b.route
        ");
        $stmt->bind_param('sss', $date, $date, $date);
        $stmt->execute();
        $result = $stmt->get_result();
        $metrics = [];
        while ($row = $result->fetch_assoc()) {
            $metrics[] = $row;
        }
        echo json_encode(['success' => true, 'date' => $date, 'metrics' => $metrics]);
        break;

    case 'get_summary':
        $date = $_POST['date'] ?? date('Y-m-d');
        $stmt = $conn->prepare("
            SELECT
                COUNT(*) as total_boarded,
                COUNT(CASE WHEN EXISTS (
                    SELECT 1 FROM fare_payments fp
                    WHERE fp.passenger_id = b.passenger_id
                    AND fp.route = b.route
                    AND DATE(fp.paid_at) = ?
                ) THEN 1 END) as total_paid,
                ROUND(
                    CASE WHEN COUNT(*) > 0
                    THEN (COUNT(CASE WHEN EXISTS (
                        SELECT 1 FROM fare_payments fp
                        WHERE fp.passenger_id = b.passenger_id
                        AND fp.route = b.route
                        AND DATE(fp.paid_at) = ?
                    ) THEN 1 END) * 100.0 / COUNT(*))
                    ELSE 0 END, 2
                ) as compliance_rate
            FROM boarding_events b
            WHERE DATE(b.boarded_at) = ?
        ");
        $stmt->bind_param('sss', $date, $date, $date);
        $stmt->execute();
        $result = $stmt->get_result();
        $summary = $result->fetch_assoc();
        echo json_encode(['success' => true, 'date' => $date, 'summary' => $summary]);
        break;

    case 'get_route_compliance':
        $route = $_POST['route'] ?? '';
        $date = $_POST['date'] ?? date('Y-m-d');
        if (empty($route)) {
            echo json_encode(['success' => false, 'message' => 'Route is required']);
            exit;
        }

        $stmt = $conn->prepare("
            SELECT
                b.passenger_id,
                u.firstName,
                u.lastName,
                b.boarded_at,
                CASE WHEN EXISTS (
                    SELECT 1 FROM fare_payments fp
                    WHERE fp.passenger_id = b.passenger_id
                    AND fp.route = b.route
                    AND DATE(fp.paid_at) = ?
                ) THEN 'Paid' ELSE 'Pending' END as payment_status
            FROM boarding_events b
            JOIN users u ON b.passenger_id = u.id
            WHERE b.route = ? AND DATE(b.boarded_at) = ?
            ORDER BY b.boarded_at DESC
        ");
        $stmt->bind_param('sss', $date, $route, $date);
        $stmt->execute();
        $result = $stmt->get_result();
        $passengers = [];
        while ($row = $result->fetch_assoc()) {
            $passengers[] = $row;
        }
        echo json_encode(['success' => true, 'route' => $route, 'date' => $date, 'passengers' => $passengers]);
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
        break;
}

$conn->close();
?>
