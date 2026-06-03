<?php
session_start();

// Check if the user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

// Fetch session data
$userId = $_SESSION['user_id'];
$userEmail = $_SESSION['user_email'] ?? "";
$userFirstName = $_SESSION['user_firstName'] ?? "";
$userMiddleName = $_SESSION['user_middleName'] ?? "";
$userLastName = $_SESSION['user_lastName'] ?? "";
$userPhoto = $_SESSION['user_photo'] ?? "default.jpg"; // Default photo

// Handle profile update form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    require_once __DIR__ . '/../db_config.php';

    // Sanitize input
    $updatedEmail = htmlspecialchars($_POST['email']);
    $updatedFirstName = htmlspecialchars($_POST['firstName']);
    $updatedMiddleName = htmlspecialchars($_POST['middleName']);
    $updatedLastName = htmlspecialchars($_POST['lastName']);

    // Handle photo upload
    if (isset($_FILES['profile_photo']) && $_FILES['profile_photo']['error'] === 0) {
        $fileTmpPath = $_FILES['profile_photo']['tmp_name'];
        $fileName = $_FILES['profile_photo']['name'];
        $fileNameCmps = explode(".", $fileName);
        $fileExtension = strtolower(end($fileNameCmps));

        // Allowed extensions
        $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif'];
        if (!in_array($fileExtension, $allowedExtensions)) {
            echo "<script>alert('Invalid file format. Only JPG, JPEG, PNG, and GIF are allowed.');</script>";
            exit();
        }

        // Enforce file size limit of 2MB
        if ($_FILES['profile_photo']['size'] > 2 * 1024 * 1024) {
            echo "<script>alert('File size exceeds 2MB limit.');</script>";
            exit();
        }

        // Generate new file name and upload
        $newFileName = $userId . '_' . time() . '.' . $fileExtension;
        $uploadPath = 'uploads/profile_photos/' . $newFileName;
        if (move_uploaded_file($fileTmpPath, $uploadPath)) {
            $_SESSION['user_photo'] = $newFileName;

            // Update user data with photo
            $updateQuery = "UPDATE users SET email = ?, firstName = ?, middleName = ?, lastName = ?, profile_photo = ? WHERE id = ?";
            $stmt = $conn->prepare($updateQuery);
            $stmt->bind_param("sssssi", $updatedEmail, $updatedFirstName, $updatedMiddleName, $updatedLastName, $newFileName, $userId);
            $stmt->execute();
        } else {
            echo "<script>alert('Error uploading photo.');</script>";
            exit();
        }
    } else {
        // Update user data without photo
        $updateQuery = "UPDATE users SET email = ?, firstName = ?, middleName = ?, lastName = ? WHERE id = ?";
        $stmt = $conn->prepare($updateQuery);
        $stmt->bind_param("ssssi", $updatedEmail, $updatedFirstName, $updatedMiddleName, $updatedLastName, $userId);
        $stmt->execute();
    }

    // Update session data
    $_SESSION['user_email'] = $updatedEmail;
    $_SESSION['user_firstName'] = $updatedFirstName;
    $_SESSION['user_middleName'] = $updatedMiddleName;
    $_SESSION['user_lastName'] = $updatedLastName;

    echo "<script>alert('Profile updated successfully!');</script>";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Dashboard</title>
    <style>
        /* General Styles */
        body {
    margin: 0;
    font-family: Arial, sans-serif;
    background-color: #f4f4f9;
}

.container {
    display: flex;
    min-height: 100vh;
}

/* Sidebar */
.sidebar {
    background-color: #34495e;
    color: white;
    width: 250px;
    padding: 20px 15px;
    display: flex;
    flex-direction: column;
}

.sidebar .logo {
    text-align: center;
    font-size: 1.5rem;
    font-weight: bold;
    margin-bottom: 20px;
}

.sidebar ul {
    list-style: none;
    padding: 0;
}

.sidebar ul li a {
    text-decoration: none;
    color: white;
    padding: 10px;
    display: block;
    border-radius: 5px;
    transition: background-color 0.3s ease;
}

.sidebar ul li a:hover {
    background-color: #2c3e50;
}

/* Main Content */
.content {
    flex-grow: 1;
    padding: 20px;
}

header {
    background-color: #3498db;
    color: white;
    padding: 15px 20px;
    border-radius: 5px;
}

/* Modal */
.modal {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0, 0, 0, 0.5);
    justify-content: center;
    align-items: center;
}

.modal-content {
    background-color: white;
    padding: 20px;
    border-radius: 10px;
    width: 90%;
    max-width: 400px;
    text-align: center;
}

.modal-content .close {
    position: absolute;
    top: 10px;
    right: 20px;
    cursor: pointer;
    font-size: 1.5rem;
    color: gray;
}
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="logo">
                <h2>JeepniGo</h2>
            </div>
            <ul>
                <li><a href="javascript:void(0)" id="profileBtn">Profile</a></li>
                <li><a href="#apply">Apply as Member</a></li>
                <li><a href="logout.php">Logout</a></li>
            </ul>
        </aside>

        <!-- Main Content -->
        <main class="content">
            <header>
                <h1>Welcome, <?php echo htmlspecialchars($userFirstName . ' ' . $userLastName); ?>!</h1>
                <p>We’re thrilled to have you here! Explore JeepniGo and make your journey smoother and more convenient.</p>
            </header>
        </main>
    </div>

    <!-- Modal for Profile Edit -->
    <div id="profileModal" class="modal">
        <div class="modal-content">
            <span class="close">&times;</span>
            <h2>Update Your Profile</h2>
            <form action="user_dashboard.php" method="POST" enctype="multipart/form-data">
                <div>
                    <img src="uploads/profile_photos/<?php echo $userPhoto; ?>" alt="Profile Photo" class="profile-photo">
                    <input type="file" name="profile_photo" accept="image/*">
                </div>
                <input type="text" name="firstName" placeholder="First Name" value="<?php echo htmlspecialchars($userFirstName); ?>" required>
                <input type="text" name="middleName" placeholder="Middle Name" value="<?php echo htmlspecialchars($userMiddleName); ?>">
                <input type="text" name="lastName" placeholder="Last Name" value="<?php echo htmlspecialchars($userLastName); ?>" required>
                <input type="email" name="email" placeholder="Email Address" value="<?php echo htmlspecialchars($userEmail); ?>" required>
                <button type="submit" name="update_profile">Update Profile</button>
            </form>
        </div>
    </div>

    <script src="scripts/dashboard.js"></script>
</body>
</html>