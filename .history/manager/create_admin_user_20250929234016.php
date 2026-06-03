<?php
session_start();
require_once __DIR__ . '/../db_config.php';

header('Content-Type: text/html; charset=utf-8');

// 1) Ensure users.userType column exists
$columnCheckSql = "SELECT COUNT(*) AS cnt
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'users'
    AND COLUMN_NAME = 'userType'";
$result = $conn->query($columnCheckSql);
$row = $result ? $result->fetch_assoc() : null;
if (!$row || (int)$row['cnt'] === 0) {
    // Add userType column with default 'passenger'
    try {
        $conn->query("ALTER TABLE users ADD COLUMN userType VARCHAR(50) NOT NULL DEFAULT 'passenger'");
    } catch (Exception $e) {
        // Try IF NOT EXISTS for MySQL 8+
        try {
            $conn->query("ALTER TABLE users ADD COLUMN IF NOT EXISTS userType VARCHAR(50) NOT NULL DEFAULT 'passenger'");
        } catch (Exception $e2) {
            echo '<p style="color:red">Failed to add userType column: ' . htmlspecialchars($e2->getMessage()) . '</p>';
            exit;
        }
    }
}

// 2) Create or update a sample admin user
$email = 'admin@example.com';
$plainPassword = 'Admin@123';
$hash = password_hash($plainPassword, PASSWORD_BCRYPT);
$firstName = 'Admin';
$lastName = 'User';

// Check if user exists
$existsStmt = $conn->prepare('SELECT id FROM users WHERE email = ?');
$existsStmt->bind_param('s', $email);
$existsStmt->execute();
$existsRes = $existsStmt->get_result();
$existing = $existsRes ? $existsRes->fetch_assoc() : null;
$existsStmt->close();

if ($existing) {
    // Update existing to admin
    $upd = $conn->prepare('UPDATE users SET password = ?, userType = "admin", firstName = ?, lastName = ? WHERE id = ?');
    $upd->bind_param('sssi', $hash, $firstName, $lastName, $existing['id']);
    $ok = $upd->execute();
    $upd->close();
    $created = false;
} else {
    // Try insert with common columns
    $ok = false;
    try {
        $ins = $conn->prepare('INSERT INTO users (firstName, lastName, email, password, userType) VALUES (?, ?, ?, ?, "admin")');
        $ins->bind_param('ssss', $firstName, $lastName, $email, $hash);
        $ok = $ins->execute();
        $ins->close();
    } catch (Exception $e) {
        // Fallback insert including middleName if schema requires it
        try {
            $middleName = '';
            $ins2 = $conn->prepare('INSERT INTO users (firstName, middleName, lastName, email, password, userType) VALUES (?, ?, ?, ?, ?, "admin")');
            $ins2->bind_param('sssss', $firstName, $middleName, $lastName, $email, $hash);
            $ok = $ins2->execute();
            $ins2->close();
        } catch (Exception $e2) {
            echo '<p style="color:red">Failed to create admin user: ' . htmlspecialchars($e2->getMessage()) . '</p>';
            exit;
        }
    }
    $created = true;
}

// 3) Output result
echo '<div style="font-family:system-ui,Segoe UI,Arial; max-width: 720px; margin: 40px auto; padding: 20px; border: 1px solid #eee; border-radius: 10px;">';
echo '<h2>Admin User ' . ($created ? 'Created' : 'Updated') . ' Successfully</h2>';
echo '<p>You can now log in using:</p>';
echo '<ul>';
echo '<li><strong>Email:</strong> ' . htmlspecialchars($email) . '</li>';
echo '<li><strong>Password:</strong> ' . htmlspecialchars($plainPassword) . '</li>';
echo '<li><strong>Role:</strong> admin</li>';
echo '</ul>';
echo '<p><a href="../shared/landing.php">Go to Login</a></p>';
echo '</div>';
?>

