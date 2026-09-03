<?php
require_once __DIR__ . '/db_config.php';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Sanitize inputs
    $firstName = htmlspecialchars($_POST['firstName']);
    $middleName = !empty($_POST['middleName']) ? htmlspecialchars($_POST['middleName']) : null;
    $lastName = htmlspecialchars($_POST['lastName']);
    $email = htmlspecialchars($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $userType = 'passenger'; // Default userType set to 'passenger'

    // Validate inputs
    if (empty($firstName) || empty($lastName) || empty($email) || empty($password)) {
        header("Location: index.php?signup_error=Please fill out all required fields");
        exit();
    }

    // Validate email
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        header("Location: index.php?signup_error=Invalid email address");
        exit();
    }

    // Validate passwords
    if ($password !== $confirm_password) {
        header("Location: index.php?signup_error=Passwords do not match");
        exit();
    }

    try {
        // Prepare the SQL query
        $passwordHash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("INSERT INTO users (email, password, firstName, middleName, lastName, userType) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("ssssss", $email, $passwordHash, $firstName, $middleName, $lastName, $userType);

        if ($stmt->execute()) {
            header("Location: index.php?signup_success=Account created successfully");
        } else {
            // Handle duplicate email or other SQL errors
            if ($conn->errno === 1062) { // Duplicate entry error code
                header("Location: index.php?signup_error=Email already exists");
            } else {
                header("Location: index.php?signup_error=An unexpected error occurred");
                error_log("Signup Error: " . $stmt->error); // Log the actual error
            }
        }
    } catch (Exception $e) {
        error_log("Signup Error: " . $e->getMessage()); // Log exception
        header("Location: index.php?signup_error=An unexpected error occurred");
    } finally {
        $stmt->close();
        $conn->close();
    }
}
?>
