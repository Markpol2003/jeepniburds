<?php
require_once 'db_config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $data = json_decode(file_get_contents('php://input'), true);
    $action = $data['action'] ?? '';
    if ($action === 'contribute') {
        if (!isset($data['member_id'], $data['amount'], $data['payment_method'])) {
            echo json_encode(['success' => false, 'message' => 'Missing required fields.']);
            exit;
        }
        $stmt = $conn->prepare("INSERT INTO cooperative_fund_payments (member_id, amount, payment_method) VALUES (?, ?, ?)");
        $stmt->bind_param('ids', $data['member_id'], $data['amount'], $data['payment_method']);
        if ($stmt->execute()) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'message' => $stmt->error]);
        }
        exit;
    }
    if ($action === 'list') {
        // List all cooperative fund payments for manager
        $stmt = $conn->prepare("SELECT cfp.*, u.firstName, u.lastName FROM cooperative_fund_payments cfp JOIN users u ON cfp.user_id = u.id ORDER BY cfp.payment_date DESC");
        $stmt->execute();
        $result = $stmt->get_result();
        $funds = [];
        while ($row = $result->fetch_assoc()) {
            $funds[] = [
                'id' => $row['id'],
                'member' => $row['firstName'] . ' ' . $row['lastName'],
                'amount' => $row['amount'],
                'payment_method' => $row['method'],
                'paid_at' => $row['payment_date'],
                'status' => $row['status']
            ];
        }
        echo json_encode(['success' => true, 'funds' => $funds]);
        exit;
    }
    if ($action === 'confirm') {
        if (!isset($data['id'])) {
            echo json_encode(['success' => false, 'message' => 'Missing payment id.']);
            exit;
        }
        $id = $data['id'];
        $stmt = $conn->prepare("UPDATE cooperative_fund_payments SET status = 'Confirmed' WHERE id = ?");
        $stmt->bind_param('i', $id);
        if ($stmt->execute()) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'message' => $stmt->error]);
        }
        exit;
    }
    exit;
}

// If GET request, show a simple HTML table of all cooperative fund payments
$stmt = $conn->prepare("SELECT cfp.*, u.firstName, u.lastName FROM cooperative_fund_payments cfp JOIN users u ON cfp.user_id = u.id ORDER BY cfp.payment_date DESC");
$stmt->execute();
$result = $stmt->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Cooperative Fund Payments</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container py-5">
    <h2 class="mb-4 text-info">Cooperative Fund Payments</h2>
    <div class="card shadow-sm">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered table-striped align-middle">
                    <thead class="table-info">
                        <tr>
                            <th>ID</th>
                            <th>Member</th>
                            <th>Amount</th>
                            <th>Method</th>
                            <th>Date</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php while ($row = $result->fetch_assoc()): ?>
                        <tr>
                            <td><?= htmlspecialchars($row['id']) ?></td>
                            <td><?= htmlspecialchars($row['firstName'] . ' ' . $row['lastName']) ?></td>
                            <td>₱<?= htmlspecialchars($row['amount']) ?></td>
                            <td><?= htmlspecialchars($row['method']) ?></td>
                            <td><?= htmlspecialchars($row['payment_date']) ?></td>
                            <td>
                                <?php if ($row['status'] === 'Confirmed'): ?>
                                    <span class="badge bg-success">Confirmed</span>
                                <?php else: ?>
                                    <span class="badge bg-warning text-dark">Pending</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
</body>
</html> 