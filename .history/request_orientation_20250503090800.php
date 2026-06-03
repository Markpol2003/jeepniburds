<?php
session_start();
require_once 'db_config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit;
}

$userId = $_SESSION['user_id'];

try {
    // Check if request already exists
    $checkStmt = $conn->prepare("SELECT id FROM orientation_requests WHERE user_id = ? AND status = 'Pending'");
    $checkStmt->bind_param("i", $userId);
    $checkStmt->execute();
    $result = $checkStmt->get_result();
    
    if ($result->num_rows > 0) {
        echo json_encode(['success' => true, 'message' => 'Request already exists']);
        exit;
    }

    // Insert new request
    $stmt = $conn->prepare("INSERT INTO orientation_requests (user_id, status, request_date) VALUES (?, 'Pending', NOW())");
    $stmt->bind_param("i", $userId);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        throw new Exception('Failed to save request');
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
<?php
session_start();
require_once 'db_config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$userId = $_SESSION['user_id'];

try {
    // Check if request already exists
    $checkStmt = $conn->prepare("SELECT id FROM orientation_requests WHERE user_id = ? AND status = 'Pending'");
    $checkStmt->bind_param("i", $userId);
    $checkStmt->execute();
    $result = $checkStmt->get_result();
    
    if ($result->num_rows > 0) {
        echo json_encode(['success' => true, 'message' => 'Request already exists']);
        exit;
    }

    // Insert new request
    $stmt = $conn->prepare("INSERT INTO orientation_requests (user_id, status, request_date) VALUES (?, 'Pending', NOW())");
    $stmt->bind_param("i", $userId);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        throw new Exception('Failed to save request');
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
<?php
session_start();
require_once 'db_config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$userId = $_SESSION['user_id'];

try {
    // Check if request already exists
    $checkStmt = $conn->prepare("SELECT id FROM orientation_requests WHERE user_id = ? AND status = 'Pending'");
    $checkStmt->bind_param("i", $userId);
    $checkStmt->execute();
    $result = $checkStmt->get_result();
    
    if ($result->num_rows > 0) {
        echo json_encode(['success' => true, 'message' => 'Request already exists']);
        exit;
    }

    // Insert new request
    $stmt = $conn->prepare("INSERT INTO orientation_requests (user_id, status, request_date) VALUES (?, 'Pending', NOW())");
    $stmt->bind_param("i", $userId);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        throw new Exception('Failed to save request');
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
<?php
session_start();
require_once 'db_config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$userId = $_SESSION['user_id'];

try {
    // Check if request already exists
    $checkStmt = $conn->prepare("SELECT id FROM orientation_requests WHERE user_id = ? AND status = 'Pending'");
    $checkStmt->bind_param("i", $userId);
    $checkStmt->execute();
    $result = $checkStmt->get_result();
    
    if ($result->num_rows > 0) {
        echo json_encode(['success' => true, 'message' => 'Request already exists']);
        exit;
    }

    // Insert new request
    $stmt = $conn->prepare("INSERT INTO orientation_requests (user_id, status, request_date) VALUES (?, 'Pending', NOW())");
    $stmt->bind_param("i", $userId);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        throw new Exception('Failed to save request');
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
<?php
session_start();
require_once 'db_config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$userId = $_SESSION['user_id'];

try {
    // Check if request already exists
    $checkStmt = $conn->prepare("SELECT id FROM orientation_requests WHERE user_id = ? AND status = 'Pending'");
    $checkStmt->bind_param("i", $userId);
    $checkStmt->execute();
    $result = $checkStmt->get_result();
    
    if ($result->num_rows > 0) {
        echo json_encode(['success' => true, 'message' => 'Request already exists']);
        exit;
    }

    // Insert new request
    $stmt = $conn->prepare("INSERT INTO orientation_requests (user_id, status, request_date) VALUES (?, 'Pending', NOW())");
    $stmt->bind_param("i", $userId);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        throw new Exception('Failed to save request');
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
<?php
session_start();
require_once 'db_config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$userId = $_SESSION['user_id'];

try {
    // Check if request already exists
    $checkStmt = $conn->prepare("SELECT id FROM orientation_requests WHERE user_id = ? AND status = 'Pending'");
    $checkStmt->bind_param("i", $userId);
    $checkStmt->execute();
    $result = $checkStmt->get_result();
    
    if ($result->num_rows > 0) {
        echo json_encode(['success' => true, 'message' => 'Request already exists']);
        exit;
    }

    // Insert new request
    $stmt = $conn->prepare("INSERT INTO orientation_requests (user_id, status, request_date) VALUES (?, 'Pending', NOW())");
    $stmt->bind_param("i", $userId);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        throw new Exception('Failed to save request');
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
<?php
session_start();
require_once 'db_config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$userId = $_SESSION['user_id'];

try {
    // Check if request already exists
    $checkStmt = $conn->prepare("SELECT id FROM orientation_requests WHERE user_id = ? AND status = 'Pending'");
    $checkStmt->bind_param("i", $userId);
    $checkStmt->execute();
    $result = $checkStmt->get_result();
    
    if ($result->num_rows > 0) {
        echo json_encode(['success' => true, 'message' => 'Request already exists']);
        exit;
    }

    // Insert new request
    $stmt = $conn->prepare("INSERT INTO orientation_requests (user_id, status, request_date) VALUES (?, 'Pending', NOW())");
    $stmt->bind_param("i", $userId);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        throw new Exception('Failed to save request');
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
<?php
session_start();
require_once 'db_config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$userId = $_SESSION['user_id'];

try {
    // Check if request already exists
    $checkStmt = $conn->prepare("SELECT id FROM orientation_requests WHERE user_id = ? AND status = 'Pending'");
    $checkStmt->bind_param("i", $userId);
    $checkStmt->execute();
    $result = $checkStmt->get_result();
    
    if ($result->num_rows > 0) {
        echo json_encode(['success' => true, 'message' => 'Request already exists']);
        exit;
    }

    // Insert new request
    $stmt = $conn->prepare("INSERT INTO orientation_requests (user_id, status, request_date) VALUES (?, 'Pending', NOW())");
    $stmt->bind_param("i", $userId);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        throw new Exception('Failed to save request');
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
<?php
session_start();
require_once 'db_config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$userId = $_SESSION['user_id'];

try {
    // Check if request already exists
    $checkStmt = $conn->prepare("SELECT id FROM orientation_requests WHERE user_id = ? AND status = 'Pending'");
    $checkStmt->bind_param("i", $userId);
    $checkStmt->execute();
    $result = $checkStmt->get_result();
    
    if ($result->num_rows > 0) {
        echo json_encode(['success' => true, 'message' => 'Request already exists']);
        exit;
    }

    // Insert new request
    $stmt = $conn->prepare("INSERT INTO orientation_requests (user_id, status, request_date) VALUES (?, 'Pending', NOW())");
    $stmt->bind_param("i", $userId);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        throw new Exception('Failed to save request');
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
<?php
session_start();
require_once 'db_config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$userId = $_SESSION['user_id'];

try {
    // Check if request already exists
    $checkStmt = $conn->prepare("SELECT id FROM orientation_requests WHERE user_id = ? AND status = 'Pending'");
    $checkStmt->bind_param("i", $userId);
    $checkStmt->execute();
    $result = $checkStmt->get_result();
    
    if ($result->num_rows > 0) {
        echo json_encode(['success' => true, 'message' => 'Request already exists']);
        exit;
    }

    // Insert new request
    $stmt = $conn->prepare("INSERT INTO orientation_requests (user_id, status, request_date) VALUES (?, 'Pending', NOW())");
    $stmt->bind_param("i", $userId);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        throw new Exception('Failed to save request');
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
<?php
session_start();
require_once 'db_config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$userId = $_SESSION['user_id'];

try {
    // Check if request already exists
    $checkStmt = $conn->prepare("SELECT id FROM orientation_requests WHERE user_id = ? AND status = 'Pending'");
    $checkStmt->bind_param("i", $userId);
    $checkStmt->execute();
    $result = $checkStmt->get_result();
    
    if ($result->num_rows > 0) {
        echo json_encode(['success' => true, 'message' => 'Request already exists']);
        exit;
    }

    // Insert new request
    $stmt = $conn->prepare("INSERT INTO orientation_requests (user_id, status, request_date) VALUES (?, 'Pending', NOW())");
    $stmt->bind_param("i", $userId);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        throw new Exception('Failed to save request');
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
<?php
session_start();
require_once 'db_config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$userId = $_SESSION['user_id'];

try {
    // Check if request already exists
    $checkStmt = $conn->prepare("SELECT id FROM orientation_requests WHERE user_id = ? AND status = 'Pending'");
    $checkStmt->bind_param("i", $userId);
    $checkStmt->execute();
    $result = $checkStmt->get_result();
    
    if ($result->num_rows > 0) {
        echo json_encode(['success' => true, 'message' => 'Request already exists']);
        exit;
    }

    // Insert new request
    $stmt = $conn->prepare("INSERT INTO orientation_requests (user_id, status, request_date) VALUES (?, 'Pending', NOW())");
    $stmt->bind_param("i", $userId);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        throw new Exception('Failed to save request');
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
<?php
session_start();
require_once 'db_config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$userId = $_SESSION['user_id'];

try {
    // Check if request already exists
    $checkStmt = $conn->prepare("SELECT id FROM orientation_requests WHERE user_id = ? AND status = 'Pending'");
    $checkStmt->bind_param("i", $userId);
    $checkStmt->execute();
    $result = $checkStmt->get_result();
    
    if ($result->num_rows > 0) {
        echo json_encode(['success' => true, 'message' => 'Request already exists']);
        exit;
    }

    // Insert new request
    $stmt = $conn->prepare("INSERT INTO orientation_requests (user_id, status, request_date) VALUES (?, 'Pending', NOW())");
    $stmt->bind_param("i", $userId);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        throw new Exception('Failed to save request');
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
<?php
session_start();
require_once 'db_config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$userId = $_SESSION['user_id'];

try {
    // Check if request already exists
    $checkStmt = $conn->prepare("SELECT id FROM orientation_requests WHERE user_id = ? AND status = 'Pending'");
    $checkStmt->bind_param("i", $userId);
    $checkStmt->execute();
    $result = $checkStmt->get_result();
    
    if ($result->num_rows > 0) {
        echo json_encode(['success' => true, 'message' => 'Request already exists']);
        exit;
    }

    // Insert new request
    $stmt = $conn->prepare("INSERT INTO orientation_requests (user_id, status, request_date) VALUES (?, 'Pending', NOW())");
    $stmt->bind_param("i", $userId);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        throw new Exception('Failed to save request');
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
<?php
session_start();
require_once 'db_config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$userId = $_SESSION['user_id'];

try {
    // Check if request already exists
    $checkStmt = $conn->prepare("SELECT id FROM orientation_requests WHERE user_id = ? AND status = 'Pending'");
    $checkStmt->bind_param("i", $userId);
    $checkStmt->execute();
    $result = $checkStmt->get_result();
    
    if ($result->num_rows > 0) {
        echo json_encode(['success' => true, 'message' => 'Request already exists']);
        exit;
    }

    // Insert new request
    $stmt = $conn->prepare("INSERT INTO orientation_requests (user_id, status, request_date) VALUES (?, 'Pending', NOW())");
    $stmt->bind_param("i", $userId);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        throw new Exception('Failed to save request');
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
<?php
session_start();
require_once 'db_config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$userId = $_SESSION['user_id'];

try {
    // Check if request already exists
    $checkStmt = $conn->prepare("SELECT id FROM orientation_requests WHERE user_id = ? AND status = 'Pending'");
    $checkStmt->bind_param("i", $userId);
    $checkStmt->execute();
    $result = $checkStmt->get_result();
    
    if ($result->num_rows > 0) {
        echo json_encode(['success' => true, 'message' => 'Request already exists']);
        exit;
    }

    // Insert new request
    $stmt = $conn->prepare("INSERT INTO orientation_requests (user_id, status, request_date) VALUES (?, 'Pending', NOW())");
    $stmt->bind_param("i", $userId);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        throw new Exception('Failed to save request');
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
<?php
session_start();
require_once 'db_config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$userId = $_SESSION['user_id'];

try {
    // Check if request already exists
    $checkStmt = $conn->prepare("SELECT id FROM orientation_requests WHERE user_id = ? AND status = 'Pending'");
    $checkStmt->bind_param("i", $userId);
    $checkStmt->execute();
    $result = $checkStmt->get_result();
    
    if ($result->num_rows > 0) {
        echo json_encode(['success' => true, 'message' => 'Request already exists']);
        exit;
    }

    // Insert new request
    $stmt = $conn->prepare("INSERT INTO orientation_requests (user_id, status, request_date) VALUES (?, 'Pending', NOW())");
    $stmt->bind_param("i", $userId);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        throw new Exception('Failed to save request');
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
<?php
session_start();
require_once 'db_config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$userId = $_SESSION['user_id'];

try {
    // Check if request already exists
    $checkStmt = $conn->prepare("SELECT id FROM orientation_requests WHERE user_id = ? AND status = 'Pending'");
    $checkStmt->bind_param("i", $userId);
    $checkStmt->execute();
    $result = $checkStmt->get_result();
    
    if ($result->num_rows > 0) {
        echo json_encode(['success' => true, 'message' => 'Request already exists']);
        exit;
    }

    // Insert new request
    $stmt = $conn->prepare("INSERT INTO orientation_requests (user_id, status, request_date) VALUES (?, 'Pending', NOW())");
    $stmt->bind_param("i", $userId);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        throw new Exception('Failed to save request');
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
<?php
session_start();
require_once 'db_config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$userId = $_SESSION['user_id'];

try {
    // Check if request already exists
    $checkStmt = $conn->prepare("SELECT id FROM orientation_requests WHERE user_id = ? AND status = 'Pending'");
    $checkStmt->bind_param("i", $userId);
    $checkStmt->execute();
    $result = $checkStmt->get_result();
    
    if ($result->num_rows > 0) {
        echo json_encode(['success' => true, 'message' => 'Request already exists']);
        exit;
    }

    // Insert new request
    $stmt = $conn->prepare("INSERT INTO orientation_requests (user_id, status, request_date) VALUES (?, 'Pending', NOW())");
    $stmt->bind_param("i", $userId);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        throw new Exception('Failed to save request');
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
<?php
session_start();
require_once 'db_config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$userId = $_SESSION['user_id'];

try {
    // Check if request already exists
    $checkStmt = $conn->prepare("SELECT id FROM orientation_requests WHERE user_id = ? AND status = 'Pending'");
    $checkStmt->bind_param("i", $userId);
    $checkStmt->execute();
    $result = $checkStmt->get_result();
    
    if ($result->num_rows > 0) {
        echo json_encode(['success' => true, 'message' => 'Request already exists']);
        exit;
    }

    // Insert new request
    $stmt = $conn->prepare("INSERT INTO orientation_requests (user_id, status, request_date) VALUES (?, 'Pending', NOW())");
    $stmt->bind_param("i", $userId);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        throw new Exception('Failed to save request');
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
<?php
session_start();
require_once 'db_config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$userId = $_SESSION['user_id'];

try {
    // Check if request already exists
    $checkStmt = $conn->prepare("SELECT id FROM orientation_requests WHERE user_id = ? AND status = 'Pending'");
    $checkStmt->bind_param("i", $userId);
    $checkStmt->execute();
    $result = $checkStmt->get_result();
    
    if ($result->num_rows > 0) {
        echo json_encode(['success' => true, 'message' => 'Request already exists']);
        exit;
    }

    // Insert new request
    $stmt = $conn->prepare("INSERT INTO orientation_requests (user_id, status, request_date) VALUES (?, 'Pending', NOW())");
    $stmt->bind_param("i", $userId);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        throw new Exception('Failed to save request');
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
<?php
session_start();
require_once 'db_config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$userId = $_SESSION['user_id'];

try {
    // Check if request already exists
    $checkStmt = $conn->prepare("SELECT id FROM orientation_requests WHERE user_id = ? AND status = 'Pending'");
    $checkStmt->bind_param("i", $userId);
    $checkStmt->execute();
    $result = $checkStmt->get_result();
    
    if ($result->num_rows > 0) {
        echo json_encode(['success' => true, 'message' => 'Request already exists']);
        exit;
    }

    // Insert new request
    $stmt = $conn->prepare("INSERT INTO orientation_requests (user_id, status, request_date) VALUES (?, 'Pending', NOW())");
    $stmt->bind_param("i", $userId);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        throw new Exception('Failed to save request');
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
<?php
session_start();
require_once 'db_config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$userId = $_SESSION['user_id'];

try {
    // Check if request already exists
    $checkStmt = $conn->prepare("SELECT id FROM orientation_requests WHERE user_id = ? AND status = 'Pending'");
    $checkStmt->bind_param("i", $userId);
    $checkStmt->execute();
    $result = $checkStmt->get_result();
    
    if ($result->num_rows > 0) {
        echo json_encode(['success' => true, 'message' => 'Request already exists']);
        exit;
    }

    // Insert new request
    $stmt = $conn->prepare("INSERT INTO orientation_requests (user_id, status, request_date) VALUES (?, 'Pending', NOW())");
    $stmt->bind_param("i", $userId);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        throw new Exception('Failed to save request');
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
<?php
session_start();
require_once 'db_config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$userId = $_SESSION['user_id'];

try {
    // Check if request already exists
    $checkStmt = $conn->prepare("SELECT id FROM orientation_requests WHERE user_id = ? AND status = 'Pending'");
    $checkStmt->bind_param("i", $userId);
    $checkStmt->execute();
    $result = $checkStmt->get_result();
    
    if ($result->num_rows > 0) {
        echo json_encode(['success' => true, 'message' => 'Request already exists']);
        exit;
    }

    // Insert new request
    $stmt = $conn->prepare("INSERT INTO orientation_requests (user_id, status, request_date) VALUES (?, 'Pending', NOW())");
    $stmt->bind_param("i", $userId);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        throw new Exception('Failed to save request');
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
<?php
session_start();
require_once 'db_config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$userId = $_SESSION['user_id'];

try {
    // Check if request already exists
    $checkStmt = $conn->prepare("SELECT id FROM orientation_requests WHERE user_id = ? AND status = 'Pending'");
    $checkStmt->bind_param("i", $userId);
    $checkStmt->execute();
    $result = $checkStmt->get_result();
    
    if ($result->num_rows > 0) {
        echo json_encode(['success' => true, 'message' => 'Request already exists']);
        exit;
    }

    // Insert new request
    $stmt = $conn->prepare("INSERT INTO orientation_requests (user_id, status, request_date) VALUES (?, 'Pending', NOW())");
    $stmt->bind_param("i", $userId);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        throw new Exception('Failed to save request');
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
<?php
session_start();
require_once 'db_config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$userId = $_SESSION['user_id'];

try {
    // Check if request already exists
    $checkStmt = $conn->prepare("SELECT id FROM orientation_requests WHERE user_id = ? AND status = 'Pending'");
    $checkStmt->bind_param("i", $userId);
    $checkStmt->execute();
    $result = $checkStmt->get_result();
    
    if ($result->num_rows > 0) {
        echo json_encode(['success' => true, 'message' => 'Request already exists']);
        exit;
    }

    // Insert new request
    $stmt = $conn->prepare("INSERT INTO orientation_requests (user_id, status, request_date) VALUES (?, 'Pending', NOW())");
    $stmt->bind_param("i", $userId);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        throw new Exception('Failed to save request');
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
<?php
session_start();
require_once 'db_config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$userId = $_SESSION['user_id'];

try {
    // Check if request already exists
    $checkStmt = $conn->prepare("SELECT id FROM orientation_requests WHERE user_id = ? AND status = 'Pending'");
    $checkStmt->bind_param("i", $userId);
    $checkStmt->execute();
    $result = $checkStmt->get_result();
    
    if ($result->num_rows > 0) {
        echo json_encode(['success' => true, 'message' => 'Request already exists']);
        exit;
    }

    // Insert new request
    $stmt = $conn->prepare("INSERT INTO orientation_requests (user_id, status, request_date) VALUES (?, 'Pending', NOW())");
    $stmt->bind_param("i", $userId);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        throw new Exception('Failed to save request');
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
<?php
session_start();
require_once 'db_config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$userId = $_SESSION['user_id'];

try {
    // Check if request already exists
    $checkStmt = $conn->prepare("SELECT id FROM orientation_requests WHERE user_id = ? AND status = 'Pending'");
    $checkStmt->bind_param("i", $userId);
    $checkStmt->execute();
    $result = $checkStmt->get_result();
    
    if ($result->num_rows > 0) {
        echo json_encode(['success' => true, 'message' => 'Request already exists']);
        exit;
    }

    // Insert new request
    $stmt = $conn->prepare("INSERT INTO orientation_requests (user_id, status, request_date) VALUES (?, 'Pending', NOW())");
    $stmt->bind_param("i", $userId);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        throw new Exception('Failed to save request');
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
<?php
session_start();
require_once 'db_config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$userId = $_SESSION['user_id'];

try {
    // Check if request already exists
    $checkStmt = $conn->prepare("SELECT id FROM orientation_requests WHERE user_id = ? AND status = 'Pending'");
    $checkStmt->bind_param("i", $userId);
    $checkStmt->execute();
    $result = $checkStmt->get_result();
    
    if ($result->num_rows > 0) {
        echo json_encode(['success' => true, 'message' => 'Request already exists']);
        exit;
    }

    // Insert new request
    $stmt = $conn->prepare("INSERT INTO orientation_requests (user_id, status, request_date) VALUES (?, 'Pending', NOW())");
    $stmt->bind_param("i", $userId);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        throw new Exception('Failed to save request');
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
<?php
session_start();
require_once 'db_config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$userId = $_SESSION['user_id'];

try {
    // Check if request already exists
    $checkStmt = $conn->prepare("SELECT id FROM orientation_requests WHERE user_id = ? AND status = 'Pending'");
    $checkStmt->bind_param("i", $userId);
    $checkStmt->execute();
    $result = $checkStmt->get_result();
    
    if ($result->num_rows > 0) {
        echo json_encode(['success' => true, 'message' => 'Request already exists']);
        exit;
    }

    // Insert new request
    $stmt = $conn->prepare("INSERT INTO orientation_requests (user_id, status, request_date) VALUES (?, 'Pending', NOW())");
    $stmt->bind_param("i", $userId);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        throw new Exception('Failed to save request');
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
<?php
session_start();
require_once 'db_config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$userId = $_SESSION['user_id'];

try {
    // Check if request already exists
    $checkStmt = $conn->prepare("SELECT id FROM orientation_requests WHERE user_id = ? AND status = 'Pending'");
    $checkStmt->bind_param("i", $userId);
    $checkStmt->execute();
    $result = $checkStmt->get_result();
    
    if ($result->num_rows > 0) {
        echo json_encode(['success' => true, 'message' => 'Request already exists']);
        exit;
    }

    // Insert new request
    $stmt = $conn->prepare("INSERT INTO orientation_requests (user_id, status, request_date) VALUES (?, 'Pending', NOW())");
    $stmt->bind_param("i", $userId);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        throw new Exception('Failed to save request');
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
<?php
session_start();
require_once 'db_config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$userId = $_SESSION['user_id'];

try {
    // Check if request already exists
    $checkStmt = $conn->prepare("SELECT id FROM orientation_requests WHERE user_id = ? AND status = 'Pending'");
    $checkStmt->bind_param("i", $userId);
    $checkStmt->execute();
    $result = $checkStmt->get_result();
    
    if ($result->num_rows > 0) {
        echo json_encode(['success' => true, 'message' => 'Request already exists']);
        exit;
    }

    // Insert new request
    $stmt = $conn->prepare("INSERT INTO orientation_requests (user_id, status, request_date) VALUES (?, 'Pending', NOW())");
    $stmt->bind_param("i", $userId);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        throw new Exception('Failed to save request');
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
<?php
session_start();
require_once 'db_config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$userId = $_SESSION['user_id'];

try {
    // Check if request already exists
    $checkStmt = $conn->prepare("SELECT id FROM orientation_requests WHERE user_id = ? AND status = 'Pending'");
    $checkStmt->bind_param("i", $userId);
    $checkStmt->execute();
    $result = $checkStmt->get_result();
    
    if ($result->num_rows > 0) {
        echo json_encode(['success' => true, 'message' => 'Request already exists']);
        exit;
    }

    // Insert new request
    $stmt = $conn->prepare("INSERT INTO orientation_requests (user_id, status, request_date) VALUES (?, 'Pending', NOW())");
    $stmt->bind_param("i", $userId);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        throw new Exception('Failed to save request');
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
<?php
session_start();
require_once 'db_config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$userId = $_SESSION['user_id'];

try {
    // Check if request already exists
    $checkStmt = $conn->prepare("SELECT id FROM orientation_requests WHERE user_id = ? AND status = 'Pending'");
    $checkStmt->bind_param("i", $userId);
    $checkStmt->execute();
    $result = $checkStmt->get_result();
    
    if ($result->num_rows > 0) {
        echo json_encode(['success' => true, 'message' => 'Request already exists']);
        exit;
    }

    // Insert new request
    $stmt = $conn->prepare("INSERT INTO orientation_requests (user_id, status, request_date) VALUES (?, 'Pending', NOW())");
    $stmt->bind_param("i", $userId);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        throw new Exception('Failed to save request');
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
<?php
session_start();
require_once 'db_config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$userId = $_SESSION['user_id'];

try {
    // Check if request already exists
    $checkStmt = $conn->prepare("SELECT id FROM orientation_requests WHERE user_id = ? AND status = 'Pending'");
    $checkStmt->bind_param("i", $userId);
    $checkStmt->execute();
    $result = $checkStmt->get_result();
    
    if ($result->num_rows > 0) {
        echo json_encode(['success' => true, 'message' => 'Request already exists']);
        exit;
    }

    // Insert new request
    $stmt = $conn->prepare("INSERT INTO orientation_requests (user_id, status, request_date) VALUES (?, 'Pending', NOW())");
    $stmt->bind_param("i", $userId);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        throw new Exception('Failed to save request');
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
<?php
session_start();
require_once 'db_config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$userId = $_SESSION['user_id'];

try {
    // Check if request already exists
    $checkStmt = $conn->prepare("SELECT id FROM orientation_requests WHERE user_id = ? AND status = 'Pending'");
    $checkStmt->bind_param("i", $userId);
    $checkStmt->execute();
    $result = $checkStmt->get_result();
    
    if ($result->num_rows > 0) {
        echo json_encode(['success' => true, 'message' => 'Request already exists']);
        exit;
    }

    // Insert new request
    $stmt = $conn->prepare("INSERT INTO orientation_requests (user_id, status, request_date) VALUES (?, 'Pending', NOW())");
    $stmt->bind_param("i", $userId);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        throw new Exception('Failed to save request');
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
<?php
session_start();
require_once 'db_config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$userId = $_SESSION['user_id'];

try {
    // Check if request already exists
    $checkStmt = $conn->prepare("SELECT id FROM orientation_requests WHERE user_id = ? AND status = 'Pending'");
    $checkStmt->bind_param("i", $userId);
    $checkStmt->execute();
    $result = $checkStmt->get_result();
    
    if ($result->num_rows > 0) {
        echo json_encode(['success' => true, 'message' => 'Request already exists']);
        exit;
    }

    // Insert new request
    $stmt = $conn->prepare("INSERT INTO orientation_requests (user_id, status, request_date) VALUES (?, 'Pending', NOW())");
    $stmt->bind_param("i", $userId);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        throw new Exception('Failed to save request');
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
<?php
session_start();
require_once 'db_config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$userId = $_SESSION['user_id'];

try {
    // Check if request already exists
    $checkStmt = $conn->prepare("SELECT id FROM orientation_requests WHERE user_id = ? AND status = 'Pending'");
    $checkStmt->bind_param("i", $userId);
    $checkStmt->execute();
    $result = $checkStmt->get_result();
    
    if ($result->num_rows > 0) {
        echo json_encode(['success' => true, 'message' => 'Request already exists']);
        exit;
    }

    // Insert new request
    $stmt = $conn->prepare("INSERT INTO orientation_requests (user_id, status, request_date) VALUES (?, 'Pending', NOW())");
    $stmt->bind_param("i", $userId);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        throw new Exception('Failed to save request');
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
<?php
session_start();
require_once 'db_config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$userId = $_SESSION['user_id'];

try {
    // Check if request already exists
    $checkStmt = $conn->prepare("SELECT id FROM orientation_requests WHERE user_id = ? AND status = 'Pending'");
    $checkStmt->bind_param("i", $userId);
    $checkStmt->execute();
    $result = $checkStmt->get_result();
    
    if ($result->num_rows > 0) {
        echo json_encode(['success' => true, 'message' => 'Request already exists']);
        exit;
    }

    // Insert new request
    $stmt = $conn->prepare("INSERT INTO orientation_requests (user_id, status, request_date) VALUES (?, 'Pending', NOW())");
    $stmt->bind_param("i", $userId);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        throw new Exception('Failed to save request');
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
<?php
session_start();
require_once 'db_config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$userId = $_SESSION['user_id'];

try {
    // Check if request already exists
    $checkStmt = $conn->prepare("SELECT id FROM orientation_requests WHERE user_id = ? AND status = 'Pending'");
    $checkStmt->bind_param("i", $userId);
    $checkStmt->execute();
    $result = $checkStmt->get_result();
    
    if ($result->num_rows > 0) {
        echo json_encode(['success' => true, 'message' => 'Request already exists']);
        exit;
    }

    // Insert new request
    $stmt = $conn->prepare("INSERT INTO orientation_requests (user_id, status, request_date) VALUES (?, 'Pending', NOW())");
    $stmt->bind_param("i", $userId);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        throw new Exception('Failed to save request');
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
<?php
session_start();
require_once 'db_config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$userId = $_SESSION['user_id'];

try {
    // Check if request already exists
    $checkStmt = $conn->prepare("SELECT id FROM orientation_requests WHERE user_id = ? AND status = 'Pending'");
    $checkStmt->bind_param("i", $userId);
    $checkStmt->execute();
    $result = $checkStmt->get_result();
    
    if ($result->num_rows > 0) {
        echo json_encode(['success' => true, 'message' => 'Request already exists']);
        exit;
    }

    // Insert new request
    $stmt = $conn->prepare("INSERT INTO orientation_requests (user_id, status, request_date) VALUES (?, 'Pending', NOW())");
    $stmt->bind_param("i", $userId);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        throw new Exception('Failed to save request');
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
<?php
session_start();
require_once 'db_config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$userId = $_SESSION['user_id'];

try {
    // Check if request already exists
    $checkStmt = $conn->prepare("SELECT id FROM orientation_requests WHERE user_id = ? AND status = 'Pending'");
    $checkStmt->bind_param("i", $userId);
    $checkStmt->execute();
    $result = $checkStmt->get_result();
    
    if ($result->num_rows > 0) {
        echo json_encode(['success' => true, 'message' => 'Request already exists']);
        exit;
    }

    // Insert new request
    $stmt = $conn->prepare("INSERT INTO orientation_requests (user_id, status, request_date) VALUES (?, 'Pending', NOW())");
    $stmt->bind_param("i", $userId);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        throw new Exception('Failed to save request');
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
<?php
session_start();
require_once 'db_config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$userId = $_SESSION['user_id'];

try {
    // Check if request already exists
    $checkStmt = $conn->prepare("SELECT id FROM orientation_requests WHERE user_id = ? AND status = 'Pending'");
    $checkStmt->bind_param("i", $userId);
    $checkStmt->execute();
    $result = $checkStmt->get_result();
    
    if ($result->num_rows > 0) {
        echo json_encode(['success' => true, 'message' => 'Request already exists']);
        exit;
    }

    // Insert new request
    $stmt = $conn->prepare("INSERT INTO orientation_requests (user_id, status, request_date) VALUES (?, 'Pending', NOW())");
    $stmt->bind_param("i", $userId);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        throw new Exception('Failed to save request');
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
<?php
session_start();
require_once 'db_config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$userId = $_SESSION['user_id'];

try {
    // Check if request already exists
    $checkStmt = $conn->prepare("SELECT id FROM orientation_requests WHERE user_id = ? AND status = 'Pending'");
    $checkStmt->bind_param("i", $userId);
    $checkStmt->execute();
    $result = $checkStmt->get_result();
    
    if ($result->num_rows > 0) {
        echo json_encode(['success' => true, 'message' => 'Request already exists']);
        exit;
    }

    // Insert new request
    $stmt = $conn->prepare("INSERT INTO orientation_requests (user_id, status, request_date) VALUES (?, 'Pending', NOW())");
    $stmt->bind_param("i", $userId);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        throw new Exception('Failed to save request');
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
<?php
session_start();
require_once 'db_config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$userId = $_SESSION['user_id'];

try {
    // Check if request already exists
    $checkStmt = $conn->prepare("SELECT id FROM orientation_requests WHERE user_id = ? AND status = 'Pending'");
    $checkStmt->bind_param("i", $userId);
    $checkStmt->execute();
    $result = $checkStmt->get_result();
    
    if ($result->num_rows > 0) {
        echo json_encode(['success' => true, 'message' => 'Request already exists']);
        exit;
    }

    // Insert new request
    $stmt = $conn->prepare("INSERT INTO orientation_requests (user_id, status, request_date) VALUES (?, 'Pending', NOW())");
    $stmt->bind_param("i", $userId);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        throw new Exception('Failed to save request');
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
<?php
session_start();
require_once 'db_config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$userId = $_SESSION['user_id'];

try {
    // Check if request already exists
    $checkStmt = $conn->prepare("SELECT id FROM orientation_requests WHERE user_id = ? AND status = 'Pending'");
    $checkStmt->bind_param("i", $userId);
    $checkStmt->execute();
    $result = $checkStmt->get_result();
    
    if ($result->num_rows > 0) {
        echo json_encode(['success' => true, 'message' => 'Request already exists']);
        exit;
    }

    // Insert new request
    $stmt = $conn->prepare("INSERT INTO orientation_requests (user_id, status, request_date) VALUES (?, 'Pending', NOW())");
    $stmt->bind_param("i", $userId);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        throw new Exception('Failed to save request');
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
<?php
session_start();
require_once 'db_config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$userId = $_SESSION['user_id'];

try {
    // Check if request already exists
    $checkStmt = $conn->prepare("SELECT id FROM orientation_requests WHERE user_id = ? AND status = 'Pending'");
    $checkStmt->bind_param("i", $userId);
    $checkStmt->execute();
    $result = $checkStmt->get_result();
    
    if ($result->num_rows > 0) {
        echo json_encode(['success' => true, 'message' => 'Request already exists']);
        exit;
    }

    // Insert new request
    $stmt = $conn->prepare("INSERT INTO orientation_requests (user_id, status, request_date) VALUES (?, 'Pending', NOW())");
    $stmt->bind_param("i", $userId);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        throw new Exception('Failed to save request');
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
<?php
session_start();
require_once 'db_config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$userId = $_SESSION['user_id'];

try {
    // Check if request already exists
    $checkStmt = $conn->prepare("SELECT id FROM orientation_requests WHERE user_id = ? AND status = 'Pending'");
    $checkStmt->bind_param("i", $userId);
    $checkStmt->execute();
    $result = $checkStmt->get_result();
    
    if ($result->num_rows > 0) {
        echo json_encode(['success' => true, '
