<?php
session_start();
require_once __DIR__ . '/../db_config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['loginEmail']);
    $password = trim($_POST['loginPassword']);

    if (empty($email) || empty($password)) {
        header("Location: index.php?login_error=Please enter both email and password");
        exit();
    }

    // Validate email format
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        header("Location: index.php?login_error=Invalid email format");
        exit();
    }

    // Fetch user from the database
    $stmt = $conn->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();

    if ($user) {
        // Debugging: Log fetched userType
        error_log("Fetched userType for email {$user['email']}: " . ($user['userType'] ?? 'NULL'));

        // Check if userType is valid
        if (empty($user['userType'])) {
            error_log("Login Error: userType is undefined for user ID {$user['id']}");
            header("Location: index.php?login_error=User role is not defined. Please contact support.");
            exit();
        }

        // Compare hashed or plain password (supports both)
        if (password_verify($password, $user['password']) || $password === $user['password']) {
            session_regenerate_id(true); // Prevent session fixation attacks

            // Set session variables (normalize role)
            $role = strtolower($user['userType'] ?? '');
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_email'] = $user['email'];
            $_SESSION['user_firstName'] = $user['firstName'];
            $_SESSION['user_middleName'] = $user['middleName'] ?? "";
            $_SESSION['user_lastName'] = $user['lastName'];
            $_SESSION['user_type'] = $role;

            // Debugging: Log session userType
            error_log("Session user_type: " . $_SESSION['user_type']);

            // Redirect based on user role
            redirectToDashboard($_SESSION['user_type']);
        } else {
            // Invalid password
            error_log("Login Error: Invalid password for email {$user['email']}");
            header("Location: index.php?login_error=Invalid password");
            exit();
        }
    } else {
        // User not found
        error_log("Login Error: User with email {$email} not found");
        header("Location: index.php?login_error=Email not found");
        exit();
    }
}

/**
 * Redirect users based on their role.
 *
 * @param string $role The user type (e.g., passenger, driver, operator, manager, admin, treasurer).
 */
function redirectToDashboard($role) {
    $dashboardRoutes = [
        'passenger' => '../passenger/passenger_dashboard.php?page=dashboard',
        'driver' => '../driver/driver_dashboard.php',
        'operator' => '../operator/operator_dashboard.php',
        'manager' => '../manager/manager_dashboard.php',
        'admin' => '../manager/admin.php',
        'treasurer' => '../treasurer/treasurer_dashboard.php',
    ];

    if (array_key_exists($role, $dashboardRoutes)) {
        header("Location: " . $dashboardRoutes[$role]);
        exit();
    } else {
        error_log("Invalid user role: $role");
        header("Location: index.php?login_error=Invalid user role");
        exit();
    }
}
?>
