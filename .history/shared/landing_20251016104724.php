<?php
session_start();
require_once __DIR__ . '/../db_config.php';

// If already logged in, redirect to the appropriate dashboard
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_SESSION['user_id'])) {
    $role = strtolower($_SESSION['user_type'] ?? '');
    $dashboardRoutes = [
        'passenger' => '../passenger/passenger_dashboard.php?page=dashboard',
        'driver'    => '../driver/driver_dashboard.php',
        'operator'  => '../operator/operator_dashboard.php',
        'manager'   => '../manager/manager_dashboard.php',
        'admin'     => '../manager/admin.php',
        'treasurer' => '../treasurer/treasurer_dashboard.php'
    ];
    if (isset($dashboardRoutes[$role])) {
        header('Location: ' . $dashboardRoutes[$role]);
        exit();
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        if ($_POST['action'] == 'signup') {
            // Sanitize inputs
            $firstName = htmlspecialchars($_POST['firstName']);
            $lastName = htmlspecialchars($_POST['lastName']);
            $email = htmlspecialchars($_POST['email']);
            $password = $_POST['password'];
            $confirm_password = $_POST['confirm_password'];
            $userType = 'passenger';  // default userType is passenger

            // Validate inputs
            if (empty($firstName) || empty($lastName) || empty($email) || empty($password)) {
                echo json_encode(['status' => 'error', 'message' => 'Please fill out all required fields']);
                exit();
            }

            // Validate email
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                echo json_encode(['status' => 'error', 'message' => 'Invalid email address']);
                exit();
            }

            // Validate passwords
            if ($password !== $confirm_password) {
                echo json_encode(['status' => 'error', 'message' => 'Passwords do not match']);
                exit();
            }

            try {
                // Prepare the SQL query
                $stmt = $conn->prepare("INSERT INTO users (email, password, firstName, lastName, userType) VALUES (?, ?, ?, ?, ?)");
                $stmt->bind_param("sssss", $email, $password, $firstName, $lastName, $userType);

                if ($stmt->execute()) {
                    echo json_encode(['status' => 'success', 'message' => 'Account created successfully']);
                } else {
                    if ($conn->errno === 1062) {
                        echo json_encode(['status' => 'error', 'message' => 'Email already exists']);
                    } else {
                        echo json_encode(['status' => 'error', 'message' => 'An unexpected error occurred']);
                    }
                }
            } catch (Exception $e) {
                echo json_encode(['status' => 'error', 'message' => 'An unexpected error occurred']);
            }
            exit();
        } elseif ($_POST['action'] == 'login') {
            $email = htmlspecialchars($_POST['email']);
            $password = $_POST['password'];

            if (empty($email) || empty($password)) {
                echo json_encode(['status' => 'error', 'message' => 'Please fill out all fields']);
                exit();
            }

            $stmt = $conn->prepare("SELECT * FROM users WHERE email = ?");
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $result = $stmt->get_result();
            $user = $result->fetch_assoc();

            if ($user) {
                // Support both hashed and plain-text passwords
                $isValid = password_verify($password, $user['password']) || ($password === $user['password']);
                if ($isValid) {
                    session_regenerate_id(true); // Prevent session fixation attacks

                    // Set session variables (normalize role)
                    $role = strtolower($user['userType'] ?? '');
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['user_email'] = $user['email'];
                    $_SESSION['user_firstName'] = $user['firstName'];
                    $_SESSION['user_lastName'] = $user['lastName'];
                    $_SESSION['user_type'] = $role;

                    // Redirect based on user role
                    $dashboardRoutes = [
                        'passenger' => '../passenger/passenger_dashboard.php?page=dashboard',
                        'driver'    => '../driver/driver_dashboard.php',
                        'operator'  => '../operator/operator_dashboard.php',
                        'manager'   => '../manager/manager_dashboard.php',
                        'admin'     => '../manager/admin.php',
                        'treasurer' => '../treasurer/treasurer_dashboard.php'
                    ];

                    $redirect = $dashboardRoutes[$role] ?? '../passenger/passenger_dashboard.php';

                    echo json_encode([
                        'status' => 'success',
                        'message' => 'Login successful',
                        'redirect' => $redirect
                    ]);
                } else {
                    echo json_encode(['status' => 'error', 'message' => 'Invalid email or password']);
                }
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Invalid email or password']);
            }

            exit();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>JeepniGo</title>
    
    <!-- Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Bootstrap and Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    
    <!-- SweetAlert2 -->
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11.7.32/dist/sweetalert2.min.css" rel="stylesheet">

    <link href="assets/css/landing.css" rel="stylesheet">
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg fixed-top" style="
        backdrop-filter: blur(10px);
        -webkit-backdrop-filter: blur(10px);
        background-color: rgba(15, 23, 42, 0.95);
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
        padding: 1rem 0;
        transition: all 0.3s ease;
    ">
        <div class="container" style="max-width: 1200px; padding: 0 2rem;">
            <a class="navbar-brand d-flex align-items-center" href="#" style="transition: transform 0.3s ease;">
                <img src="\tebz\img\logo12.png" alt="JeepniGo Logo" class="navbar-logo" style="width: 45px; height: auto; margin-right: 10px; transition: transform 0.3s ease;">
                <span style="font-size: 1.5rem; font-weight: 600; color: white; transition: color 0.3s ease;">JeepniGo</span>
            </a>
            <button class="navbar-toggler border-0 shadow-none" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" style="transition: transform 0.3s ease;">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto align-items-center">
                    <li class="nav-item mx-2">
                        <a class="nav-link fw-medium" href="#features" style="
                            color: white;
                            transition: all 0.3s ease;
                            position: relative;
                            padding: 0.5rem 0;
                        ">
                            Features
                            <span class="nav-link-underline" style="
                                position: absolute;
                                bottom: 0;
                                left: 0;
                                width: 0;
                                height: 2px;
                                background-color: #3b82f6;
                                transition: width 0.3s ease;
                            "></span>
                        </a>
                    </li>
                    <li class="nav-item mx-2">
                        <a class="nav-link fw-medium" href="#about" style="
                            color: white;
                            transition: all 0.3s ease;
                            position: relative;
                            padding: 0.5rem 0;
                        ">
                            About
                            <span class="nav-link-underline" style="
                                position: absolute;
                                bottom: 0;
                                left: 0;
                                width: 0;
                                height: 2px;
                                background-color: #3b82f6;
                                transition: width 0.3s ease;
                            "></span>
                        </a>
                    </li>
                    <li class="nav-item mx-2">
                        <a class="nav-link fw-medium" href="#testimonials" style="
                            color: white;
                            transition: all 0.3s ease;
                            position: relative;
                            padding: 0.5rem 0;
                        ">
                            Testimonials
                            <span class="nav-link-underline" style="
                                position: absolute;
                                bottom: 0;
                                left: 0;
                                width: 0;
                                height: 2px;
                                background-color: #3b82f6;
                                transition: width 0.3s ease;
                            "></span>
                        </a>
                    </li>
                    <li class="nav-item mx-2">
                        <a class="nav-link fw-medium" href="#contact" style="
                            color: white;
                            transition: all 0.3s ease;
                            position: relative;
                            padding: 0.5rem 0;
                        ">
                            Contact
                            <span class="nav-link-underline" style="
                                position: absolute;
                                bottom: 0;
                                left: 0;
                                width: 0;
                                height: 2px;
                                background-color: #3b82f6;
                                transition: width 0.3s ease;
                            "></span>
                        </a>
                    </li>
                    <li class="nav-item ms-3">
                        <button onclick="openLoginModal()" class="btn px-6 py-2 rounded-full fw-medium" 
                            style="
                                background-color: #3b82f6;
                                color: white;
                                border: none;
                                box-shadow: 0 2px 6px rgba(59, 130, 246, 0.3);
                                transition: all 0.3s ease;
                                position: relative;
                                overflow: hidden;
                            ">
                            <span style="position: relative; z-index: 1;">Get Started</span>
                            <span class="btn-shine" style="
                                position: absolute;
                                top: 0;
                                left: -100%;
                                width: 100%;
                                height: 100%;
                                background: linear-gradient(
                                    90deg,
                                    transparent,
                                    rgba(255, 255, 255, 0.2),
                                    transparent
                                );
                                transition: 0.5s;
                            "></span>
                        </button>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <style>
    /* Navbar hover effects */
    .navbar-brand:hover {
        transform: scale(1.05);
    }

    .navbar-brand:hover .navbar-logo {
        transform: rotate(5deg);
    }

    .nav-link {
        color: white !important;
        transition: all 0.3s ease;
    }

    .nav-link:hover {
        color: #3b82f6 !important;
    }

    .nav-link:hover .nav-link-underline {
        width: 100%;
    }

    .btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(59, 130, 246, 0.4);
    }

    .btn:hover .btn-shine {
        left: 100%;
    }

    /* Navbar scroll effect */
    .navbar {
        background-color: rgba(15, 23, 42, 0.95);
        transition: all 0.3s ease;
    }

    .navbar.scrolled {
        background-color: rgba(15, 23, 42, 0.98);
        padding: 0.8rem 0;
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
    }

    .navbar-brand span {
        color: white !important;
    }

    /* Mobile menu animation */
    .navbar-collapse {
        transition: all 0.3s ease;
    }

    @media (max-width: 991.98px) {
        .navbar-collapse {
            background-color: rgba(15, 23, 42, 0.98);
            padding: 1rem;
            border-radius: 0.5rem;
            margin-top: 1rem;
        }
        
        .nav-link {
            padding: 0.8rem 0;
        }
    }
    </style>

    <script>
    // Add this function to handle opening the login modal
    function openLoginModal() {
        const loginModal = new bootstrap.Modal(document.getElementById('loginModal'));
        loginModal.show();
    }

    // Navbar scroll effect
    window.addEventListener('scroll', function() {
        const navbar = document.querySelector('.navbar');
        if (window.scrollY > 50) {
            navbar.classList.add('scrolled');
        } else {
            navbar.classList.remove('scrolled');
        }
    });

    // Add hover effect to nav links
    document.querySelectorAll('.nav-link').forEach(link => {
        link.addEventListener('mouseenter', function() {
            this.querySelector('.nav-link-underline').style.width = '100%';
        });
        
        link.addEventListener('mouseleave', function() {
            this.querySelector('.nav-link-underline').style.width = '0';
        });
    });
    </script>

<!-- Hero Section -->
<section class="hero-section" style="
  min-height: 100vh;
  background: linear-gradient(135deg, #667eea 0%, #764ba2 50%, #f093fb 100%);
  position: relative;
  overflow: hidden;
  display: flex;
  align-items: center;
  padding: 100px 0 50px;
">
  <!-- Animated Background Elements -->
  <div class="hero-bg-shapes">
    <div class="shape shape-1"></div>
    <div class="shape shape-2"></div>
    <div class="shape shape-3"></div>
    <div class="shape shape-4"></div>
  </div>

  <div class="container" style="position: relative; z-index: 10;">
    <div class="row align-items-center">
      <div class="col-lg-6 mb-5 mb-lg-0">
        <div class="hero-content" style="animation: fadeInLeft 1s ease-out;">
          <div class="badge-pill mb-4" style="
            display: inline-block;
            padding: 8px 20px;
            background: rgba(255, 255, 255, 0.2);
            backdrop-filter: blur(10px);
            border-radius: 50px;
            color: white;
            font-weight: 500;
            font-size: 0.9rem;
          ">
            <i class="bi bi-star-fill me-2"></i>The Future of Transportation
          </div>
          <h1 class="display-3 fw-bold text-white mb-4" style="line-height: 1.2;">
            Modern Jeepney Travel Experience
          </h1>
          <p class="lead text-white mb-4" style="font-size: 1.25rem; opacity: 0.95;">
            Transform your daily commute with real-time tracking, easy booking, and secure payments. Experience seamless travel at your fingertips.
          </p>
          <div class="d-flex flex-wrap gap-3">
            <button onclick="openLoginModal()" class="btn btn-hero-primary btn-lg px-5 py-3" style="
              background: white;
              color: #667eea;
              border: none;
              border-radius: 50px;
              font-weight: 600;
              box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
              transition: all 0.3s ease;
            ">
              <i class="bi bi-rocket-takeoff me-2"></i>Get Started
            </button>
            <a href="#features" class="btn btn-hero-outline btn-lg px-5 py-3" style="
              background: transparent;
              color: white;
              border: 2px solid white;
              border-radius: 50px;
              font-weight: 600;
              transition: all 0.3s ease;
            ">
              <i class="bi bi-play-circle me-2"></i>Learn More
            </a>
          </div>
          <!-- Stats Row -->
          <div class="row mt-5">
            <div class="col-4">
              <div class="hero-stat">
                <h3 class="text-white fw-bold mb-0">50K+</h3>
                <p class="text-white mb-0" style="opacity: 0.9; font-size: 0.9rem;">Users</p>
              </div>
            </div>
            <div class="col-4">
              <div class="hero-stat">
                <h3 class="text-white fw-bold mb-0">1000+</h3>
                <p class="text-white mb-0" style="opacity: 0.9; font-size: 0.9rem;">Jeepneys</p>
              </div>
            </div>
            <div class="col-4">
              <div class="hero-stat">
                <h3 class="text-white fw-bold mb-0">4.8★</h3>
                <p class="text-white mb-0" style="opacity: 0.9; font-size: 0.9rem;">Rating</p>
              </div>
            </div>
          </div>
        </div>
      </div>
      <div class="col-lg-6">
        <div class="hero-illustration" style="animation: fadeInRight 1s ease-out;">
          <div class="phone-mockup" style="
            background: white;
            border-radius: 40px;
            padding: 20px;
            box-shadow: 0 30px 60px rgba(0, 0, 0, 0.3);
            max-width: 500px;
            margin: 0 auto;
            position: relative;
          ">
            <div class="phone-screen" style="
              background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
              border-radius: 30px;
              padding: 30px;
              min-height: 400px;
              display: flex;
              flex-direction: column;
              justify-content: space-between;
            ">
              <!-- App Preview Content -->
              <div class="app-header mb-4">
                <div class="d-flex justify-content-between align-items-center mb-4">
                  <h4 class="mb-0 fw-bold" style="color: #667eea;">JeepniGo</h4>
                  <div class="notification-badge" style="
                    width: 35px;
                    height: 35px;
                    background: #667eea;
                    border-radius: 50%;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                  ">
                    <i class="bi bi-bell-fill text-white"></i>
                  </div>
                </div>
                <div style="
                  background: white;
                  padding: 15px;
                  border-radius: 15px;
                  box-shadow: 0 5px 15px rgba(0,0,0,0.1);
                ">
                  <p class="mb-2 fw-semibold" style="color: #333; font-size: 0.9rem;">Next Jeepney Arriving In</p>
                  <h2 class="mb-0 fw-bold" style="color: #667eea;">3 minutes</h2>
                </div>
              </div>
              
              <div class="app-features">
                <div class="feature-item mb-3 p-3" style="
                  background: white;
                  border-radius: 15px;
                  display: flex;
                  align-items: center;
                  box-shadow: 0 5px 15px rgba(0,0,0,0.1);
                ">
                  <div style="
                    width: 50px;
                    height: 50px;
                    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                    border-radius: 12px;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    margin-right: 15px;
                  ">
                    <i class="bi bi-geo-alt-fill text-white" style="font-size: 1.5rem;"></i>
                  </div>
                  <div>
                    <p class="mb-0 fw-semibold" style="color: #333;">Real-time Tracking</p>
                    <p class="mb-0" style="color: #666; font-size: 0.85rem;">Live location updates</p>
                  </div>
                </div>
                
                <div class="feature-item mb-3 p-3" style="
                  background: white;
                  border-radius: 15px;
                  display: flex;
                  align-items: center;
                  box-shadow: 0 5px 15px rgba(0,0,0,0.1);
                ">
                  <div style="
                    width: 50px;
                    height: 50px;
                    background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
                    border-radius: 12px;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    margin-right: 15px;
                  ">
                    <i class="bi bi-wallet2 text-white" style="font-size: 1.5rem;"></i>
                  </div>
                  <div>
                    <p class="mb-0 fw-semibold" style="color: #333;">Secure Payments</p>
                    <p class="mb-0" style="color: #666; font-size: 0.85rem;">Quick & safe transactions</p>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Scroll Indicator -->
  <div class="scroll-indicator" style="
    position: absolute;
    bottom: 30px;
    left: 50%;
    transform: translateX(-50%);
    animation: bounce 2s infinite;
  ">
    <a href="#features" style="color: white; font-size: 2rem;">
      <i class="bi bi-chevron-down"></i>
    </a>
  </div>
</section>

<style>
/* Hero Section Animations */
@keyframes fadeInLeft {
  from {
    opacity: 0;
    transform: translateX(-50px);
  }
  to {
    opacity: 1;
    transform: translateX(0);
  }
}

@keyframes fadeInRight {
  from {
    opacity: 0;
    transform: translateX(50px);
  }
  to {
    opacity: 1;
    transform: translateX(0);
  }
}

@keyframes bounce {
  0%, 20%, 50%, 80%, 100% {
    transform: translateX(-50%) translateY(0);
  }
  40% {
    transform: translateX(-50%) translateY(-10px);
  }
  60% {
    transform: translateX(-50%) translateY(-5px);
  }
}

@keyframes float {
  0%, 100% {
    transform: translateY(0px) rotate(0deg);
  }
  50% {
    transform: translateY(-20px) rotate(5deg);
  }
}

/* Animated Background Shapes */
.hero-bg-shapes {
  position: absolute;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
  overflow: hidden;
  z-index: 1;
}

.shape {
  position: absolute;
  border-radius: 50%;
  background: rgba(255, 255, 255, 0.1);
  backdrop-filter: blur(10px);
}

.shape-1 {
  width: 300px;
  height: 300px;
  top: 10%;
  left: 5%;
  animation: float 6s ease-in-out infinite;
}

.shape-2 {
  width: 200px;
  height: 200px;
  top: 60%;
  left: 10%;
  animation: float 8s ease-in-out infinite;
  animation-delay: 1s;
}

.shape-3 {
  width: 250px;
  height: 250px;
  top: 20%;
  right: 10%;
  animation: float 7s ease-in-out infinite;
  animation-delay: 2s;
}

.shape-4 {
  width: 180px;
  height: 180px;
  bottom: 15%;
  right: 15%;
  animation: float 9s ease-in-out infinite;
  animation-delay: 0.5s;
}

/* Button Hover Effects */
.btn-hero-primary:hover {
  transform: translateY(-3px);
  box-shadow: 0 15px 40px rgba(0, 0, 0, 0.3) !important;
  background: #f8f9fa !important;
}

.btn-hero-outline:hover {
  background: white !important;
  color: #667eea !important;
  transform: translateY(-3px);
}

/* Responsive Design */
@media (max-width: 991.98px) {
  .hero-section {
    padding: 80px 0 30px;
  }
  
  .hero-content h1 {
    font-size: 2.5rem !important;
  }
  
  .phone-mockup {
    max-width: 350px !important;
  }
  
  .hero-stat h3 {
    font-size: 1.5rem;
  }
}

@media (max-width: 767.98px) {
  .hero-content h1 {
    font-size: 2rem !important;
  }
  
  .btn-hero-primary, .btn-hero-outline {
    width: 100%;
    margin-bottom: 10px;
  }
  
  .shape {
    display: none;
  }
}
</style>



    <!-- Features Section -->
    <section id="features" style="
        padding: 100px 0;
        background: linear-gradient(180deg, #ffffff 0%, #f8f9fa 100%);
        position: relative;
    ">
        <div class="container">
            <div class="row text-center mb-5 animate__animated animate__fadeIn">
                <div class="col-lg-8 mx-auto">
                    <div class="section-badge mb-3" style="
                        display: inline-block;
                        padding: 8px 20px;
                        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                        border-radius: 50px;
                        color: white;
                        font-weight: 600;
                        font-size: 0.85rem;
                        letter-spacing: 1px;
                    ">
                        FEATURES
                    </div>
                    <h2 class="display-5 fw-bold mb-4" style="color: #1a202c;">Why Choose JeepniGo</h2>
                    <p class="lead" style="color: #718096;">Experience the future of public transportation with our innovative features designed for modern commuters.</p>
                </div>
            </div>
            <div class="row g-4">
                <div class="col-lg-4 col-md-6">
                    <div class="modern-feature-card animate__animated animate__fadeInUp" style="
                        background: white;
                        padding: 40px 30px;
                        border-radius: 20px;
                        height: 100%;
                        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
                        transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
                        border: 1px solid #f0f0f0;
                        position: relative;
                        overflow: hidden;
                    ">
                        <div class="feature-gradient" style="
                            position: absolute;
                            top: 0;
                            left: 0;
                            width: 100%;
                            height: 4px;
                            background: linear-gradient(90deg, #667eea 0%, #764ba2 100%);
                        "></div>
                        <div class="modern-feature-icon" style="
                            width: 70px;
                            height: 70px;
                            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                            border-radius: 18px;
                            display: flex;
                            align-items: center;
                            justify-content: center;
                            margin-bottom: 25px;
                            box-shadow: 0 8px 20px rgba(102, 126, 234, 0.3);
                        ">
                            <i class="bi bi-calendar-check-fill" style="font-size: 2rem; color: white;"></i>
                        </div>
                        <h4 class="h4 mb-3 fw-bold" style="color: #1a202c;">Digital Booking</h4>
                        <p style="color: #718096; line-height: 1.7;">Reserve your seat in advance and avoid long waiting times at terminals.</p>
                    </div>
                </div>
                <div class="col-lg-4 col-md-6">
                    <div class="modern-feature-card animate__animated animate__fadeInUp" style="
                        background: white;
                        padding: 40px 30px;
                        border-radius: 20px;
                        height: 100%;
                        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
                        transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
                        border: 1px solid #f0f0f0;
                        position: relative;
                        overflow: hidden;
                        animation-delay: 0.1s;
                    ">
                        <div class="feature-gradient" style="
                            position: absolute;
                            top: 0;
                            left: 0;
                            width: 100%;
                            height: 4px;
                            background: linear-gradient(90deg, #f093fb 0%, #f5576c 100%);
                        "></div>
                        <div class="modern-feature-icon" style="
                            width: 70px;
                            height: 70px;
                            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
                            border-radius: 18px;
                            display: flex;
                            align-items: center;
                            justify-content: center;
                            margin-bottom: 25px;
                            box-shadow: 0 8px 20px rgba(240, 147, 251, 0.3);
                        ">
                            <i class="bi bi-geo-alt-fill" style="font-size: 2rem; color: white;"></i>
                        </div>
                        <h4 class="h4 mb-3 fw-bold" style="color: #1a202c;">Real-Time Tracking</h4>
                        <p style="color: #718096; line-height: 1.7;">Track jeepney locations live on the map for a smoother and safer commute.</p>
                    </div>
                </div>
                <div class="col-lg-4 col-md-6">
                    <div class="modern-feature-card animate__animated animate__fadeInUp" style="
                        background: white;
                        padding: 40px 30px;
                        border-radius: 20px;
                        height: 100%;
                        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
                        transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
                        border: 1px solid #f0f0f0;
                        position: relative;
                        overflow: hidden;
                        animation-delay: 0.2s;
                    ">
                        <div class="feature-gradient" style="
                            position: absolute;
                            top: 0;
                            left: 0;
                            width: 100%;
                            height: 4px;
                            background: linear-gradient(90deg, #4facfe 0%, #00f2fe 100%);
                        "></div>
                        <div class="modern-feature-icon" style="
                            width: 70px;
                            height: 70px;
                            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
                            border-radius: 18px;
                            display: flex;
                            align-items: center;
                            justify-content: center;
                            margin-bottom: 25px;
                            box-shadow: 0 8px 20px rgba(79, 172, 254, 0.3);
                        ">
                            <i class="bi bi-wallet2" style="font-size: 2rem; color: white;"></i>
                        </div>
                        <h4 class="h4 mb-3 fw-bold" style="color: #1a202c;">Cashless Payment</h4>
                        <p style="color: #718096; line-height: 1.7;">Pay fares securely using e-wallets or digital payment options.</p>
                    </div>
                </div>
                <div class="col-lg-4 col-md-6">
                    <div class="modern-feature-card animate__animated animate__fadeInUp" style="
                        background: white;
                        padding: 40px 30px;
                        border-radius: 20px;
                        height: 100%;
                        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
                        transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
                        border: 1px solid #f0f0f0;
                        position: relative;
                        overflow: hidden;
                        animation-delay: 0.3s;
                    ">
                        <div class="feature-gradient" style="
                            position: absolute;
                            top: 0;
                            left: 0;
                            width: 100%;
                            height: 4px;
                            background: linear-gradient(90deg, #fbbf24 0%, #f59e0b 100%);
                        "></div>
                        <div class="modern-feature-icon" style="
                            width: 70px;
                            height: 70px;
                            background: linear-gradient(135deg, #fbbf24 0%, #f59e0b 100%);
                            border-radius: 18px;
                            display: flex;
                            align-items: center;
                            justify-content: center;
                            margin-bottom: 25px;
                            box-shadow: 0 8px 20px rgba(251, 191, 36, 0.3);
                        ">
                            <i class="bi bi-signpost-split-fill" style="font-size: 2rem; color: white;"></i>
                        </div>
                        <h4 class="h4 mb-3 fw-bold" style="color: #1a202c;">Route Optimization</h4>
                        <p style="color: #718096; line-height: 1.7;">Drivers and operators get improved route planning to save time and fuel.</p>
                    </div>
                </div>
                <div class="col-lg-4 col-md-6">
                    <div class="modern-feature-card animate__animated animate__fadeInUp" style="
                        background: white;
                        padding: 40px 30px;
                        border-radius: 20px;
                        height: 100%;
                        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
                        transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
                        border: 1px solid #f0f0f0;
                        position: relative;
                        overflow: hidden;
                        animation-delay: 0.4s;
                    ">
                        <div class="feature-gradient" style="
                            position: absolute;
                            top: 0;
                            left: 0;
                            width: 100%;
                            height: 4px;
                            background: linear-gradient(90deg, #10b981 0%, #059669 100%);
                        "></div>
                        <div class="modern-feature-icon" style="
                            width: 70px;
                            height: 70px;
                            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
                            border-radius: 18px;
                            display: flex;
                            align-items: center;
                            justify-content: center;
                            margin-bottom: 25px;
                            box-shadow: 0 8px 20px rgba(16, 185, 129, 0.3);
                        ">
                            <i class="bi bi-universal-access" style="font-size: 2rem; color: white;"></i>
                        </div>
                        <h4 class="h4 mb-3 fw-bold" style="color: #1a202c;">Accessibility Support</h4>
                        <p style="color: #718096; line-height: 1.7;">Designed with features that assist PWD passengers for easier commuting.</p>
                    </div>
                </div>
                <div class="col-lg-4 col-md-6">
                    <div class="modern-feature-card animate__animated animate__fadeInUp" style="
                        background: white;
                        padding: 40px 30px;
                        border-radius: 20px;
                        height: 100%;
                        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
                        transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
                        border: 1px solid #f0f0f0;
                        position: relative;
                        overflow: hidden;
                        animation-delay: 0.5s;
                    ">
                        <div class="feature-gradient" style="
                            position: absolute;
                            top: 0;
                            left: 0;
                            width: 100%;
                            height: 4px;
                            background: linear-gradient(90deg, #8b5cf6 0%, #7c3aed 100%);
                        "></div>
                        <div class="modern-feature-icon" style="
                            width: 70px;
                            height: 70px;
                            background: linear-gradient(135deg, #8b5cf6 0%, #7c3aed 100%);
                            border-radius: 18px;
                            display: flex;
                            align-items: center;
                            justify-content: center;
                            margin-bottom: 25px;
                            box-shadow: 0 8px 20px rgba(139, 92, 246, 0.3);
                        ">
                            <i class="bi bi-speedometer2" style="font-size: 2rem; color: white;"></i>
                        </div>
                        <h4 class="h4 mb-3 fw-bold" style="color: #1a202c;">Driver & Operator Dashboard</h4>
                        <p style="color: #718096; line-height: 1.7;">Monitor earnings, schedules, and cooperative records with ease.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

<style>
.modern-feature-card:hover {
    transform: translateY(-10px);
    box-shadow: 0 20px 40px rgba(0, 0, 0, 0.15) !important;
}

.modern-feature-card:hover .modern-feature-icon {
    transform: scale(1.1) rotate(5deg);
}
</style>

    <!-- Testimonials Section -->
    <section id="testimonials" style="
        padding: 100px 0;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        position: relative;
        overflow: hidden;
    ">
        <div class="container">
            <div class="row text-center mb-5 animate__animated animate__fadeIn">
                <div class="col-lg-8 mx-auto">
                    <div class="section-badge mb-3" style="
                        display: inline-block;
                        padding: 8px 20px;
                        background: rgba(255, 255, 255, 0.2);
                        backdrop-filter: blur(10px);
                        border-radius: 50px;
                        color: white;
                        font-weight: 600;
                        font-size: 0.85rem;
                        letter-spacing: 1px;
                    ">
                        TESTIMONIALS
                    </div>
                    <h2 class="display-5 fw-bold mb-4 text-white">What Our Users Say</h2>
                    <p class="lead text-white" style="opacity: 0.95;">Hear from our satisfied commuters and drivers about their experience with JeepniGo.</p>
                </div>
            </div>
            <div class="row g-4">
                <div class="col-lg-4 col-md-6">
                    <div class="modern-testimonial-card animate__animated animate__fadeInUp" style="
                        background: white;
                        padding: 35px;
                        border-radius: 20px;
                        height: 100%;
                        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
                        transition: all 0.4s ease;
                        animation-delay: 0.2s;
                    ">
                        <div class="quote-icon mb-3" style="
                            width: 50px;
                            height: 50px;
                            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                            border-radius: 12px;
                            display: flex;
                            align-items: center;
                            justify-content: center;
                        ">
                            <i class="bi bi-quote" style="font-size: 1.5rem; color: white;"></i>
                        </div>
                        <div class="rating mb-3">
                            <i class="bi bi-star-fill" style="color: #fbbf24;"></i>
                            <i class="bi bi-star-fill" style="color: #fbbf24;"></i>
                            <i class="bi bi-star-fill" style="color: #fbbf24;"></i>
                            <i class="bi bi-star-fill" style="color: #fbbf24;"></i>
                            <i class="bi bi-star-fill" style="color: #fbbf24;"></i>
                        </div>
                        <p class="mb-4" style="color: #4a5568; line-height: 1.8; font-style: italic;">
                            "JeepniGo has made my daily commute so much easier. I can now track my jeepney in real-time and plan my journey better."
                        </p>
                        <div class="d-flex align-items-center">
                            <div class="avatar-placeholder" style="
                                width: 50px;
                                height: 50px;
                                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                                border-radius: 50%;
                                display: flex;
                                align-items: center;
                                justify-content: center;
                                margin-right: 15px;
                                color: white;
                                font-weight: 600;
                            ">
                                SJ
                            </div>
                            <div>
                                <h6 class="mb-0 fw-bold" style="color: #1a202c;">Sarah Johnson</h6>
                                <small style="color: #718096;">Regular Commuter</small>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-4 col-md-6">
                    <div class="modern-testimonial-card animate__animated animate__fadeInUp" style="
                        background: white;
                        padding: 35px;
                        border-radius: 20px;
                        height: 100%;
                        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
                        transition: all 0.4s ease;
                        animation-delay: 0.4s;
                    ">
                        <div class="quote-icon mb-3" style="
                            width: 50px;
                            height: 50px;
                            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
                            border-radius: 12px;
                            display: flex;
                            align-items: center;
                            justify-content: center;
                        ">
                            <i class="bi bi-quote" style="font-size: 1.5rem; color: white;"></i>
                        </div>
                        <div class="rating mb-3">
                            <i class="bi bi-star-fill" style="color: #fbbf24;"></i>
                            <i class="bi bi-star-fill" style="color: #fbbf24;"></i>
                            <i class="bi bi-star-fill" style="color: #fbbf24;"></i>
                            <i class="bi bi-star-fill" style="color: #fbbf24;"></i>
                            <i class="bi bi-star-fill" style="color: #fbbf24;"></i>
                        </div>
                        <p class="mb-4" style="color: #4a5568; line-height: 1.8; font-style: italic;">
                            "As a jeepney driver, JeepniGo has helped me increase my earnings and manage my routes more efficiently."
                        </p>
                        <div class="d-flex align-items-center">
                            <div class="avatar-placeholder" style="
                                width: 50px;
                                height: 50px;
                                background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
                                border-radius: 50%;
                                display: flex;
                                align-items: center;
                                justify-content: center;
                                margin-right: 15px;
                                color: white;
                                font-weight: 600;
                            ">
                                MS
                            </div>
                            <div>
                                <h6 class="mb-0 fw-bold" style="color: #1a202c;">Michael Santos</h6>
                                <small style="color: #718096;">Jeepney Driver</small>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-4 col-md-6">
                    <div class="modern-testimonial-card animate__animated animate__fadeInUp" style="
                        background: white;
                        padding: 35px;
                        border-radius: 20px;
                        height: 100%;
                        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
                        transition: all 0.4s ease;
                        animation-delay: 0.6s;
                    ">
                        <div class="quote-icon mb-3" style="
                            width: 50px;
                            height: 50px;
                            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
                            border-radius: 12px;
                            display: flex;
                            align-items: center;
                            justify-content: center;
                        ">
                            <i class="bi bi-quote" style="font-size: 1.5rem; color: white;"></i>
                        </div>
                        <div class="rating mb-3">
                            <i class="bi bi-star-fill" style="color: #fbbf24;"></i>
                            <i class="bi bi-star-fill" style="color: #fbbf24;"></i>
                            <i class="bi bi-star-fill" style="color: #fbbf24;"></i>
                            <i class="bi bi-star-fill" style="color: #fbbf24;"></i>
                            <i class="bi bi-star-half" style="color: #fbbf24;"></i>
                        </div>
                        <p class="mb-4" style="color: #4a5568; line-height: 1.8; font-style: italic;">
                            "The booking system is so convenient, and the real-time tracking feature gives me peace of mind during my commute."
                        </p>
                        <div class="d-flex align-items-center">
                            <div class="avatar-placeholder" style="
                                width: 50px;
                                height: 50px;
                                background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
                                border-radius: 50%;
                                display: flex;
                                align-items: center;
                                justify-content: center;
                                margin-right: 15px;
                                color: white;
                                font-weight: 600;
                            ">
                                MG
                            </div>
                            <div>
                                <h6 class="mb-0 fw-bold" style="color: #1a202c;">Maria Garcia</h6>
                                <small style="color: #718096;">Student</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

<style>
.modern-testimonial-card:hover {
    transform: translateY(-10px);
    box-shadow: 0 20px 40px rgba(0, 0, 0, 0.3) !important;
}
</style>

    <!-- Stats Section -->
    <section style="
        padding: 80px 0;
        background: linear-gradient(180deg, #f8f9fa 0%, #ffffff 100%);
        position: relative;
    ">
        <div class="container">
            <div class="row text-center g-4">
                <div class="col-lg-3 col-md-6 animate__animated animate__fadeInUp" style="animation-delay: 0.1s;">
                    <div class="modern-stat-card" style="
                        padding: 30px 20px;
                        background: white;
                        border-radius: 20px;
                        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
                        transition: all 0.4s ease;
                        border: 1px solid #f0f0f0;
                    ">
                        <div class="stat-icon-modern mb-3" style="
                            width: 70px;
                            height: 70px;
                            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                            border-radius: 18px;
                            display: flex;
                            align-items: center;
                            justify-content: center;
                            margin: 0 auto;
                            box-shadow: 0 8px 20px rgba(102, 126, 234, 0.3);
                        ">
                            <i class="bi bi-people-fill" style="font-size: 2rem; color: white;"></i>
                        </div>
                        <div class="stat-number-modern" style="
                            font-size: 2.5rem;
                            font-weight: 700;
                            color: #1a202c;
                            margin-bottom: 8px;
                        ">50K+</div>
                        <p class="mb-0 fw-medium" style="color: #718096;">Daily Users</p>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6 animate__animated animate__fadeInUp" style="animation-delay: 0.2s;">
                    <div class="modern-stat-card" style="
                        padding: 30px 20px;
                        background: white;
                        border-radius: 20px;
                        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
                        transition: all 0.4s ease;
                        border: 1px solid #f0f0f0;
                    ">
                        <div class="stat-icon-modern mb-3" style="
                            width: 70px;
                            height: 70px;
                            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
                            border-radius: 18px;
                            display: flex;
                            align-items: center;
                            justify-content: center;
                            margin: 0 auto;
                            box-shadow: 0 8px 20px rgba(240, 147, 251, 0.3);
                        ">
                            <i class="bi bi-truck" style="font-size: 2rem; color: white;"></i>
                        </div>
                        <div class="stat-number-modern" style="
                            font-size: 2.5rem;
                            font-weight: 700;
                            color: #1a202c;
                            margin-bottom: 8px;
                        ">1000+</div>
                        <p class="mb-0 fw-medium" style="color: #718096;">Registered Jeepneys</p>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6 animate__animated animate__fadeInUp" style="animation-delay: 0.3s;">
                    <div class="modern-stat-card" style="
                        padding: 30px 20px;
                        background: white;
                        border-radius: 20px;
                        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
                        transition: all 0.4s ease;
                        border: 1px solid #f0f0f0;
                    ">
                        <div class="stat-icon-modern mb-3" style="
                            width: 70px;
                            height: 70px;
                            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
                            border-radius: 18px;
                            display: flex;
                            align-items: center;
                            justify-content: center;
                            margin: 0 auto;
                            box-shadow: 0 8px 20px rgba(79, 172, 254, 0.3);
                        ">
                            <i class="bi bi-map" style="font-size: 2rem; color: white;"></i>
                        </div>
                        <div class="stat-number-modern" style="
                            font-size: 2.5rem;
                            font-weight: 700;
                            color: #1a202c;
                            margin-bottom: 8px;
                        ">100+</div>
                        <p class="mb-0 fw-medium" style="color: #718096;">Routes Covered</p>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6 animate__animated animate__fadeInUp" style="animation-delay: 0.4s;">
                    <div class="modern-stat-card" style="
                        padding: 30px 20px;
                        background: white;
                        border-radius: 20px;
                        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
                        transition: all 0.4s ease;
                        border: 1px solid #f0f0f0;
                    ">
                        <div class="stat-icon-modern mb-3" style="
                            width: 70px;
                            height: 70px;
                            background: linear-gradient(135deg, #fbbf24 0%, #f59e0b 100%);
                            border-radius: 18px;
                            display: flex;
                            align-items: center;
                            justify-content: center;
                            margin: 0 auto;
                            box-shadow: 0 8px 20px rgba(251, 191, 36, 0.3);
                        ">
                            <i class="bi bi-star-fill" style="font-size: 2rem; color: white;"></i>
                        </div>
                        <div class="stat-number-modern" style="
                            font-size: 2.5rem;
                            font-weight: 700;
                            color: #1a202c;
                            margin-bottom: 8px;
                        ">4.8★</div>
                        <p class="mb-0 fw-medium" style="color: #718096;">User Rating</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

<style>
.modern-stat-card:hover {
    transform: translateY(-10px);
    box-shadow: 0 20px 40px rgba(0, 0, 0, 0.15) !important;
}

.modern-stat-card:hover .stat-icon-modern {
    transform: scale(1.1) rotate(-5deg);
}
</style>

    <!-- Call to Action -->
    <section style="
        padding: 100px 0;
        background: linear-gradient(135deg, #1a202c 0%, #2d3748 100%);
        position: relative;
        overflow: hidden;
    ">
        <!-- Background Pattern -->
        <div style="
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            opacity: 0.1;
            background-image: radial-gradient(circle, white 1px, transparent 1px);
            background-size: 30px 30px;
        "></div>
        
        <div class="container text-center" style="position: relative; z-index: 10;">
            <div class="row justify-content-center">
                <div class="col-lg-8">
                    <div class="cta-badge mb-4 animate__animated animate__fadeIn" style="
                        display: inline-block;
                        padding: 10px 25px;
                        background: rgba(255, 255, 255, 0.1);
                        backdrop-filter: blur(10px);
                        border-radius: 50px;
                        color: white;
                        font-weight: 600;
                        font-size: 0.9rem;
                    ">
                        <i class="bi bi-lightning-fill me-2"></i>JOIN US TODAY
                    </div>
                    <h2 class="display-4 fw-bold text-white mb-4 animate__animated animate__fadeIn" style="animation-delay: 0.2s;">
                        Ready to Transform Your Travel?
                    </h2>
                    <p class="lead text-white mb-5 animate__animated animate__fadeIn" style="opacity: 0.9; animation-delay: 0.3s;">
                        Join thousands of satisfied commuters who have made the switch to JeepniGo. Experience the future of transportation today.
                    </p>
                    <button onclick="openLoginModal()" class="btn btn-cta-modern btn-lg px-5 py-3 animate__animated animate__fadeInUp" style="
                        background: white;
                        color: #667eea;
                        border: none;
                        border-radius: 50px;
                        font-weight: 600;
                        font-size: 1.1rem;
                        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
                        transition: all 0.3s ease;
                        animation-delay: 0.4s;
                    ">
                        <i class="bi bi-rocket-takeoff me-2"></i>Get Started Now
                    </button>
                </div>
            </div>
        </div>
    </section>

<style>
.btn-cta-modern:hover {
    transform: translateY(-5px);
    box-shadow: 0 15px 40px rgba(0, 0, 0, 0.4) !important;
    background: #f8f9fa !important;
    color: #667eea !important;
}
</style>

    <!-- Contact Section -->
    <section id="contact" style="
        padding: 100px 0;
        background: linear-gradient(180deg, #ffffff 0%, #f8f9fa 100%);
        position: relative;
    ">
        <div class="container">
            <div class="row text-center mb-5 animate__animated animate__fadeIn">
                <div class="col-lg-8 mx-auto">
                    <div class="section-badge mb-3" style="
                        display: inline-block;
                        padding: 8px 20px;
                        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                        border-radius: 50px;
                        color: white;
                        font-weight: 600;
                        font-size: 0.85rem;
                        letter-spacing: 1px;
                    ">
                        CONTACT US
                    </div>
                    <h2 class="display-5 fw-bold mb-4" style="color: #1a202c;">Get in Touch</h2>
                    <p class="lead" style="color: #718096;">Have questions? We'd love to hear from you. Send us a message and we'll respond as soon as possible.</p>
                </div>
            </div>
            <div class="row justify-content-center">
                <div class="col-lg-8">
                    <div class="modern-contact-card animate__animated animate__fadeInUp" style="
                        background: white;
                        padding: 50px;
                        border-radius: 20px;
                        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
                        border: 1px solid #f0f0f0;
                    ">
                        <form id="contactForm" class="needs-validation" novalidate>
                            <div class="row g-4">
                                <div class="col-md-6">
                                    <div class="modern-form-group">
                                        <label for="name" style="
                                            display: block;
                                            margin-bottom: 8px;
                                            color: #1a202c;
                                            font-weight: 600;
                                            font-size: 0.9rem;
                                        ">Your Name</label>
                                        <input type="text" class="modern-form-control" id="name" placeholder="John Doe" required style="
                                            width: 100%;
                                            padding: 15px 20px;
                                            border: 2px solid #e2e8f0;
                                            border-radius: 12px;
                                            font-size: 1rem;
                                            transition: all 0.3s ease;
                                        ">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="modern-form-group">
                                        <label for="contact-email" style="
                                            display: block;
                                            margin-bottom: 8px;
                                            color: #1a202c;
                                            font-weight: 600;
                                            font-size: 0.9rem;
                                        ">Your Email</label>
                                        <input type="email" class="modern-form-control" id="contact-email" placeholder="john@example.com" required style="
                                            width: 100%;
                                            padding: 15px 20px;
                                            border: 2px solid #e2e8f0;
                                            border-radius: 12px;
                                            font-size: 1rem;
                                            transition: all 0.3s ease;
                                        ">
                                    </div>
                                </div>
                                <div class="col-12">
                                    <div class="modern-form-group">
                                        <label for="subject" style="
                                            display: block;
                                            margin-bottom: 8px;
                                            color: #1a202c;
                                            font-weight: 600;
                                            font-size: 0.9rem;
                                        ">Subject</label>
                                        <input type="text" class="modern-form-control" id="subject" placeholder="How can we help?" required style="
                                            width: 100%;
                                            padding: 15px 20px;
                                            border: 2px solid #e2e8f0;
                                            border-radius: 12px;
                                            font-size: 1rem;
                                            transition: all 0.3s ease;
                                        ">
                                    </div>
                                </div>
                                <div class="col-12">
                                    <div class="modern-form-group">
                                        <label for="message" style="
                                            display: block;
                                            margin-bottom: 8px;
                                            color: #1a202c;
                                            font-weight: 600;
                                            font-size: 0.9rem;
                                        ">Your Message</label>
                                        <textarea class="modern-form-control" id="message" placeholder="Tell us more..." required style="
                                            width: 100%;
                                            padding: 15px 20px;
                                            border: 2px solid #e2e8f0;
                                            border-radius: 12px;
                                            font-size: 1rem;
                                            transition: all 0.3s ease;
                                            min-height: 150px;
                                            resize: vertical;
                                        "></textarea>
                                    </div>
                                </div>
                                <div class="col-12 text-center">
                                    <button type="submit" class="btn-contact-submit" style="
                                        padding: 15px 50px;
                                        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                                        color: white;
                                        border: none;
                                        border-radius: 50px;
                                        font-weight: 600;
                                        font-size: 1rem;
                                        box-shadow: 0 10px 25px rgba(102, 126, 234, 0.3);
                                        transition: all 0.3s ease;
                                        cursor: pointer;
                                    ">
                                        <i class="bi bi-send me-2"></i>Send Message
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </section>

<style>
.modern-form-control:focus {
    border-color: #667eea;
    outline: none;
    box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
}

.btn-contact-submit:hover {
    transform: translateY(-3px);
    box-shadow: 0 15px 35px rgba(102, 126, 234, 0.4);
}
</style>

    <!-- Footer -->
    <footer style="
        padding: 80px 0 30px;
        background: linear-gradient(135deg, #1a202c 0%, #2d3748 100%);
        color: #cbd5e0;
        position: relative;
        overflow: hidden;
    ">
        <!-- Background Pattern -->
        <div style="
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            opacity: 0.05;
            background-image: radial-gradient(circle, white 1px, transparent 1px);
            background-size: 30px 30px;
        "></div>

        <div class="container" style="position: relative; z-index: 10;">
            <div class="row g-4 mb-5">
                <div class="col-lg-4 col-md-6 animate__animated animate__fadeInLeft">
                    <div class="footer-brand mb-4">
                        <h3 class="text-white fw-bold mb-3" style="font-size: 1.8rem;">
                            <i class="bi bi-bus-front me-2" style="color: #667eea;"></i>JeepniGo
                        </h3>
                        <p style="color: #a0aec0; line-height: 1.8;">
                            Making public transportation more accessible, efficient, and enjoyable for everyone. Join the revolution today.
                        </p>
                    </div>
                    <div class="footer-social">
                        <a href="#" style="
                            display: inline-flex;
                            align-items: center;
                            justify-content: center;
                            width: 45px;
                            height: 45px;
                            background: rgba(255, 255, 255, 0.1);
                            border-radius: 12px;
                            color: white;
                            margin-right: 10px;
                            transition: all 0.3s ease;
                            text-decoration: none;
                        " onmouseover="this.style.background='linear-gradient(135deg, #667eea 0%, #764ba2 100%)'; this.style.transform='translateY(-5px)'" onmouseout="this.style.background='rgba(255, 255, 255, 0.1)'; this.style.transform='translateY(0)'">
                            <i class="bi bi-facebook"></i>
                        </a>
                        <a href="#" style="
                            display: inline-flex;
                            align-items: center;
                            justify-content: center;
                            width: 45px;
                            height: 45px;
                            background: rgba(255, 255, 255, 0.1);
                            border-radius: 12px;
                            color: white;
                            margin-right: 10px;
                            transition: all 0.3s ease;
                            text-decoration: none;
                        " onmouseover="this.style.background='linear-gradient(135deg, #667eea 0%, #764ba2 100%)'; this.style.transform='translateY(-5px)'" onmouseout="this.style.background='rgba(255, 255, 255, 0.1)'; this.style.transform='translateY(0)'">
                            <i class="bi bi-twitter"></i>
                        </a>
                        <a href="#" style="
                            display: inline-flex;
                            align-items: center;
                            justify-content: center;
                            width: 45px;
                            height: 45px;
                            background: rgba(255, 255, 255, 0.1);
                            border-radius: 12px;
                            color: white;
                            margin-right: 10px;
                            transition: all 0.3s ease;
                            text-decoration: none;
                        " onmouseover="this.style.background='linear-gradient(135deg, #667eea 0%, #764ba2 100%)'; this.style.transform='translateY(-5px)'" onmouseout="this.style.background='rgba(255, 255, 255, 0.1)'; this.style.transform='translateY(0)'">
                            <i class="bi bi-instagram"></i>
                        </a>
                        <a href="#" style="
                            display: inline-flex;
                            align-items: center;
                            justify-content: center;
                            width: 45px;
                            height: 45px;
                            background: rgba(255, 255, 255, 0.1);
                            border-radius: 12px;
                            color: white;
                            transition: all 0.3s ease;
                            text-decoration: none;
                        " onmouseover="this.style.background='linear-gradient(135deg, #667eea 0%, #764ba2 100%)'; this.style.transform='translateY(-5px)'" onmouseout="this.style.background='rgba(255, 255, 255, 0.1)'; this.style.transform='translateY(0)'">
                            <i class="bi bi-linkedin"></i>
                        </a>
                    </div>
                </div>
                <div class="col-lg-2 col-md-6 animate__animated animate__fadeInUp" style="animation-delay: 0.1s;">
                    <h5 class="text-white fw-bold mb-4">Quick Links</h5>
                    <ul style="list-style: none; padding: 0;">
                        <li style="margin-bottom: 12px;">
                            <a href="#" style="color: #a0aec0; text-decoration: none; transition: all 0.3s ease;" onmouseover="this.style.color='white'; this.style.paddingLeft='5px'" onmouseout="this.style.color='#a0aec0'; this.style.paddingLeft='0'">
                                <i class="bi bi-chevron-right me-2" style="font-size: 0.8rem;"></i>Home
                            </a>
                        </li>
                        <li style="margin-bottom: 12px;">
                            <a href="#about" style="color: #a0aec0; text-decoration: none; transition: all 0.3s ease;" onmouseover="this.style.color='white'; this.style.paddingLeft='5px'" onmouseout="this.style.color='#a0aec0'; this.style.paddingLeft='0'">
                                <i class="bi bi-chevron-right me-2" style="font-size: 0.8rem;"></i>About
                            </a>
                        </li>
                        <li style="margin-bottom: 12px;">
                            <a href="#features" style="color: #a0aec0; text-decoration: none; transition: all 0.3s ease;" onmouseover="this.style.color='white'; this.style.paddingLeft='5px'" onmouseout="this.style.color='#a0aec0'; this.style.paddingLeft='0'">
                                <i class="bi bi-chevron-right me-2" style="font-size: 0.8rem;"></i>Features
                            </a>
                        </li>
                        <li style="margin-bottom: 12px;">
                            <a href="#contact" style="color: #a0aec0; text-decoration: none; transition: all 0.3s ease;" onmouseover="this.style.color='white'; this.style.paddingLeft='5px'" onmouseout="this.style.color='#a0aec0'; this.style.paddingLeft='0'">
                                <i class="bi bi-chevron-right me-2" style="font-size: 0.8rem;"></i>Contact
                            </a>
                        </li>
                    </ul>
                </div>
                <div class="col-lg-2 col-md-6 animate__animated animate__fadeInUp" style="animation-delay: 0.2s;">
                    <h5 class="text-white fw-bold mb-4">Legal</h5>
                    <ul style="list-style: none; padding: 0;">
                        <li style="margin-bottom: 12px;">
                            <a href="#" style="color: #a0aec0; text-decoration: none; transition: all 0.3s ease;" onmouseover="this.style.color='white'; this.style.paddingLeft='5px'" onmouseout="this.style.color='#a0aec0'; this.style.paddingLeft='0'">
                                <i class="bi bi-chevron-right me-2" style="font-size: 0.8rem;"></i>Privacy Policy
                            </a>
                        </li>
                        <li style="margin-bottom: 12px;">
                            <a href="#" style="color: #a0aec0; text-decoration: none; transition: all 0.3s ease;" onmouseover="this.style.color='white'; this.style.paddingLeft='5px'" onmouseout="this.style.color='#a0aec0'; this.style.paddingLeft='0'">
                                <i class="bi bi-chevron-right me-2" style="font-size: 0.8rem;"></i>Terms of Service
                            </a>
                        </li>
                        <li style="margin-bottom: 12px;">
                            <a href="#" style="color: #a0aec0; text-decoration: none; transition: all 0.3s ease;" onmouseover="this.style.color='white'; this.style.paddingLeft='5px'" onmouseout="this.style.color='#a0aec0'; this.style.paddingLeft='0'">
                                <i class="bi bi-chevron-right me-2" style="font-size: 0.8rem;"></i>FAQ
                            </a>
                        </li>
                    </ul>
                </div>
                <div class="col-lg-4 col-md-6 animate__animated animate__fadeInRight" style="animation-delay: 0.3s;">
                    <h5 class="text-white fw-bold mb-4">Newsletter</h5>
                    <p style="color: #a0aec0; margin-bottom: 20px;">Subscribe to our newsletter for updates and offers.</p>
                    <div class="newsletter-form" style="position: relative;">
                        <input type="email" placeholder="Enter your email" style="
                            width: 100%;
                            padding: 15px 20px;
                            padding-right: 130px;
                            border: 2px solid rgba(255, 255, 255, 0.1);
                            border-radius: 50px;
                            background: rgba(255, 255, 255, 0.05);
                            color: white;
                            font-size: 0.95rem;
                            transition: all 0.3s ease;
                        " onfocus="this.style.borderColor='#667eea'; this.style.background='rgba(255, 255, 255, 0.1)'" onblur="this.style.borderColor='rgba(255, 255, 255, 0.1)'; this.style.background='rgba(255, 255, 255, 0.05)'">
                        <button type="button" style="
                            position: absolute;
                            right: 5px;
                            top: 5px;
                            padding: 10px 25px;
                            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                            color: white;
                            border: none;
                            border-radius: 50px;
                            font-weight: 600;
                            cursor: pointer;
                            transition: all 0.3s ease;
                        " onmouseover="this.style.transform='scale(1.05)'; this.style.boxShadow='0 5px 15px rgba(102, 126, 234, 0.4)'" onmouseout="this.style.transform='scale(1)'; this.style.boxShadow='none'">
                            Subscribe
                        </button>
                    </div>
                </div>
            </div>

            <!-- Footer Bottom -->
            <div class="footer-bottom" style="
                padding-top: 30px;
                margin-top: 50px;
                border-top: 1px solid rgba(255, 255, 255, 0.1);
                text-align: center;
            ">
                <p style="color: #a0aec0; margin: 0; font-size: 0.95rem;">
                    &copy; 2025 JeepniGo. All rights reserved. Made with <i class="bi bi-heart-fill" style="color: #f5576c;"></i> in the Philippines
                </p>
            </div>
        </div>
    </footer>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Navbar scroll effect
        window.addEventListener('scroll', function() {
            if (window.scrollY > 50) {
                document.querySelector('.navbar').classList.add('scrolled');
            } else {
                document.querySelector('.navbar').classList.remove('scrolled');
            }
        });
    </script>

   <style>
    .modal-header {
    padding: 2rem 2rem 1rem;
    display: flex;
    flex-direction: column;
    align-items: center;
    text-align: center;
    position: relative;
}

.modal-title {
    font-weight: 700;
    font-size: 1.8rem;
    margin: 0 auto;
    color: var(--dark-color);
}

.btn-close {
    position: absolute;
    right: 1.5rem;
    top: 1.5rem;
}
   </style>
    
    <!-- Login Modal -->
    <div class="modal fade" id="loginModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header border-0">
                    <h5 class="modal-title">Welcome Back</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                <form id="loginForm" method="post">
                        <input type="hidden" name="action" value="login">
                        <div class="mb-3">
                            <label class="form-label">Email address</label>
                            <input type="email" name="email" class="form-control" placeholder="Enter your email" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Password</label>
                            <input type="password" name="password" class="form-control" placeholder="Enter your password" required>
                        </div>
                        <div class="mb-3 form-check">
                            <input type="checkbox" class="form-check-input" id="rememberMe">
                            <label class="form-check-label" for="rememberMe">Remember me</label>
                        </div>
                        <button type="submit" class="btn btn-primary w-100">Login</button>
                    </form>
                    <div class="text-center mt-3">
                        <p class="mb-0">Don't have an account? <a href="#" data-bs-toggle="modal" data-bs-target="#signupModal" data-bs-dismiss="modal">Sign up</a></p>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Signup Modal -->
    <div class="modal fade" id="signupModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header border-0">
                    <h5 class="modal-title">Create Account</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    <form id="signupForm" method="post">
                        <input type="hidden" name="action" value="signup">
                        <div class="mb-3">
                            <label class="form-label">First Name</label>
                            <input type="text" name="firstName" class="form-control" placeholder="Enter your first name" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Last Name</label>
                            <input type="text" name="lastName" class="form-control" placeholder="Enter your last name" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Email address</label>
                            <input type="email" name="email" class="form-control" placeholder="Enter your email" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Password</label>
                            <input type="password" name="password" class="form-control" placeholder="Create a password" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Confirm Password</label>
                            <input type="password" name="confirm_password" class="form-control" placeholder="Confirm your password" required>
                        </div>
                        <button type="submit" class="btn btn-primary w-100">Sign Up</button>
                    </form>
                    <div class="text-center mt-3">
                        <p class="mb-0">Already have an account? <a href="#" data-bs-toggle="modal" data-bs-target="#loginModal" data-bs-dismiss="modal">Login</a></p>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.7.32/dist/sweetalert2.all.min.js"></script>
    <script>
document.addEventListener("DOMContentLoaded", function () {
    // Initialize Bootstrap modal
    const loginModal = new bootstrap.Modal(document.getElementById('loginModal'));
    const signupModal = new bootstrap.Modal(document.getElementById('signupModal'));

    // Function to open login modal
    window.openLoginModal = function() {
        loginModal.show();
    }

    // Add click handler to Get Started button
    document.querySelector('button[onclick="openLoginModal()"]').addEventListener('click', function(e) {
        e.preventDefault();
        openLoginModal();
    });

    // Form submission handlers
    document.getElementById('loginForm').addEventListener('submit', function(e) {
        e.preventDefault();
        fetch('', {
            method: 'POST',
            body: new FormData(this)
        })
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success') {
                Swal.fire({
                    icon: 'success',
                    title: 'Success!',
                    text: data.message,
                    showConfirmButton: false,
                    timer: 1500
                }).then(() => {
                    window.location.href = data.redirect;
                });
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Error!',
                    text: data.message
                });
            }
        });
    });

    document.getElementById('signupForm').addEventListener('submit', function(e) {
        e.preventDefault();
        fetch('', {
            method: 'POST',
            body: new FormData(this)
        })
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success') {
                Swal.fire({
                    icon: 'success',
                    title: 'Success!',
                    text: data.message,
                    showConfirmButton: false,
                    timer: 1500
                }).then(() => {
                    window.location.href = 'landing.php';
                });
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Error!',
                    text: data.message
                });
            }
        });
    });

});
</script>

<style>
/* Advanced Animations and Effects */
@keyframes float {
    0% { transform: translateY(0px); }
    50% { transform: translateY(-10px); }
    100% { transform: translateY(0px); }
}

@keyframes shine {
    0% { background-position: -100% 0; }
    100% { background-position: 200% 0; }
}

@keyframes pulse {
    0% { transform: scale(1); }
    50% { transform: scale(1.05); }
    100% { transform: scale(1); }
}

/* Enhanced Feature Cards */
.feature-card {
    background: white;
    padding: 2.5rem;
    border-radius: 1.5rem;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
    height: 100%;
    position: relative;
    overflow: hidden;
    border: 1px solid rgba(59, 130, 246, 0.1);
}

.feature-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: linear-gradient(45deg, transparent, rgba(59, 130, 246, 0.05), transparent);
    transform: translateX(-100%);
    transition: transform 0.6s cubic-bezier(0.4, 0, 0.2, 1);
}

.feature-card:hover::before {
    transform: translateX(100%);
}

.feature-card:hover {
    transform: translateY(-10px) scale(1.02);
    box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
    border-color: rgba(59, 130, 246, 0.2);
}

.feature-icon {
    width: 80px;
    height: 80px;
    background: #e3f2fd;
    border-radius: 1.5rem;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-bottom: 2rem;
    transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
    position: relative;
    overflow: hidden;
}

.feature-icon::after {
    content: '';
    position: absolute;
    top: -50%;
    left: -50%;
    width: 200%;
    height: 200%;
    background: linear-gradient(45deg, transparent, rgba(255, 255, 255, 0.3), transparent);
    transform: rotate(45deg);
    transition: transform 0.6s cubic-bezier(0.4, 0, 0.2, 1);
}

.feature-card:hover .feature-icon {
    background: #3b82f6;
    transform: rotate(360deg) scale(1.1);
}

.feature-card:hover .feature-icon::after {
    transform: rotate(45deg) translate(50%, 50%);
}

.feature-icon i {
    font-size: 2.5rem;
    color: #3b82f6;
    transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
    position: relative;
    z-index: 1;
}

.feature-card:hover .feature-icon i {
    color: white;
    animation: pulse 1s infinite;
}

/* Enhanced Testimonial Cards */
.testimonial-card {
    background: white;
    padding: 2.5rem;
    border-radius: 1.5rem;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
    height: 100%;
    position: relative;
    overflow: hidden;
    border: 1px solid rgba(59, 130, 246, 0.1);
}

.testimonial-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: linear-gradient(45deg, transparent, rgba(59, 130, 246, 0.05), transparent);
    transform: translateX(-100%);
    transition: transform 0.6s cubic-bezier(0.4, 0, 0.2, 1);
}

.testimonial-card:hover::before {
    transform: translateX(100%);
}

.testimonial-card:hover {
    transform: translateY(-10px) scale(1.02);
    box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
    border-color: rgba(59, 130, 246, 0.2);
}

.testimonial-content {
    height: 100%;
    display: flex;
    flex-direction: column;
    position: relative;
    z-index: 1;
}

.testimonial-content p {
    flex-grow: 1;
    font-style: italic;
    color: #6c757d;
    transition: all 0.3s ease;
}

.testimonial-card:hover .testimonial-content p {
    color: #3b82f6;
}

.rating {
    transition: all 0.3s ease;
}

.testimonial-card:hover .rating {
    transform: scale(1.1);
}

.testimonial-author {
    transition: all 0.3s ease;
}

.testimonial-card:hover .testimonial-author {
    transform: translateX(10px);
}

.testimonial-author img {
    transition: all 0.3s ease;
    border: 2px solid transparent;
}

.testimonial-card:hover .testimonial-author img {
    border-color: #3b82f6;
    transform: scale(1.1);
}

/* Enhanced Contact Form */
.contact-card {
    background: white;
    padding: 3.5rem;
    border-radius: 1.5rem;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    position: relative;
    overflow: hidden;
    border: 1px solid rgba(59, 130, 246, 0.1);
    transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
}

.contact-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: linear-gradient(45deg, transparent, rgba(59, 130, 246, 0.05), transparent);
    transform: translateX(-100%);
    transition: transform 0.6s cubic-bezier(0.4, 0, 0.2, 1);
}

.contact-card:hover::before {
    transform: translateX(100%);
}

.contact-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
    border-color: rgba(59, 130, 246, 0.2);
}

.form-floating > .form-control {
    padding: 1.25rem 0.75rem;
    border: 1px solid rgba(59, 130, 246, 0.2);
    transition: all 0.3s ease;
}

.form-floating > .form-control:focus {
    border-color: #3b82f6;
    box-shadow: 0 0 0 0.25rem rgba(59, 130, 246, 0.25);
    transform: translateY(-2px);
}

.form-floating > label {
    padding: 1.25rem 0.75rem;
    color: #6c757d;
    transition: all 0.3s ease;
}

.form-floating > .form-control:focus ~ label {
    color: #3b82f6;
    transform: translateY(-1rem) scale(0.85);
}

.btn-primary {
    background: linear-gradient(45deg, #3b82f6, #2563eb);
    border: none;
    padding: 1rem 2.5rem;
    border-radius: 0.75rem;
    font-weight: 600;
    letter-spacing: 0.5px;
    transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
    position: relative;
    overflow: hidden;
}

.btn-primary::before {
    content: '';
    position: absolute;
    top: 0;
    left: -100%;
    width: 100%;
    height: 100%;
    background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
    transition: all 0.6s cubic-bezier(0.4, 0, 0.2, 1);
}

.btn-primary:hover {
    transform: translateY(-3px);
    box-shadow: 0 10px 20px rgba(37, 99, 235, 0.3);
}

.btn-primary:hover::before {
    left: 100%;
}

/* Section Spacing and Headers */
section {
    position: relative;
    overflow: hidden;
}

section::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: linear-gradient(45deg, transparent, rgba(59, 130, 246, 0.02), transparent);
    pointer-events: none;
}

.section-header {
    position: relative;
    margin-bottom: 4rem;
}

.section-header span {
    display: inline-block;
    padding: 0.5rem 1rem;
    background: rgba(59, 130, 246, 0.1);
    border-radius: 2rem;
    color: #3b82f6;
    font-weight: 600;
    margin-bottom: 1rem;
    transition: all 0.3s ease;
}

.section-header:hover span {
    transform: translateY(-3px);
    box-shadow: 0 4px 12px rgba(59, 130, 246, 0.2);
}

.section-header h2 {
    position: relative;
    display: inline-block;
}

.section-header h2::after {
    content: '';
    position: absolute;
    bottom: -10px;
    left: 50%;
    transform: translateX(-50%);
    width: 50px;
    height: 3px;
    background: #3b82f6;
    border-radius: 3px;
    transition: all 0.3s ease;
}

.section-header:hover h2::after {
    width: 100px;
}

/* Enhanced Animations */
.animate__animated {
    animation-duration: 1s;
    animation-fill-mode: both;
}

.animate__fadeInUp {
    animation-name: fadeInUp;
    animation-duration: 1.2s;
}

.animate__fadeInLeft {
    animation-name: fadeInLeft;
    animation-duration: 1.2s;
}

.animate__fadeInRight {
    animation-name: fadeInRight;
    animation-duration: 1.2s;
}

/* Responsive Adjustments */
@media (max-width: 768px) {
    .feature-card, .testimonial-card, .contact-card {
        padding: 2rem;
    }
    
    .section-header {
        margin-bottom: 3rem;
    }
    
    .btn-primary {
        padding: 0.875rem 2rem;
    }
}
</style>

<script>
// Enhanced intersection observer with smooth animations
document.addEventListener('DOMContentLoaded', function() {
    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.style.visibility = 'visible';
                entry.target.classList.add('animate__animated');
                
                // Add animation class based on element type
                if (entry.target.classList.contains('stat-card')) {
                    entry.target.classList.add('animate__fadeInUp');
                }
            }
        });
    }, {
        threshold: 0.1,
        rootMargin: '50px'
    });

    // Observe all animated elements
    document.querySelectorAll('.stat-card, .section-header').forEach((el) => {
        el.style.visibility = 'hidden';
        observer.observe(el);
    });
});

// Smooth scroll behavior
document.querySelectorAll('a[href^="#"]').forEach(anchor => {
    anchor.addEventListener('click', function (e) {
        e.preventDefault();
        const target = document.querySelector(this.getAttribute('href'));
        if (target) {
            target.scrollIntoView({
                behavior: 'smooth',
                block: 'start'
            });
        }
    });
});
</script>

<style>
/* Add these styles for animations and hover effects */
.feature-card, .about-card {
    background: white;
    padding: 2rem;
    border-radius: 1rem;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    transition: all 0.3s ease;
}

.feature-card:hover, .about-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 15px rgba(0, 0, 0, 0.2);
}

.feature-icon, .about-icon {
    width: 60px;
    height: 60px;
    background: #e3f2fd;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-bottom: 1.5rem;
    transition: all 0.3s ease;
}

.feature-card:hover .feature-icon, .about-card:hover .about-icon {
    background: #3b82f6;
    color: white;
    transform: rotate(360deg);
}

.feature-icon i, .about-icon i {
    font-size: 1.5rem;
    color: #3b82f6;
    transition: all 0.3s ease;
}

.feature-card:hover .feature-icon i, .about-card:hover .about-icon i {
    color: white;
}

.stat-number {
    font-size: 3.5rem;
    font-weight: bold;
    color: black !important;
    margin-bottom: 0.5rem;
    text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.1);
}

.stats p {
    color: #000 !important;
    font-size: 1.1rem;
    margin-bottom: 0;
}

/* Add hover effect for stats */
.stat-number {
    transition: all 0.3s ease;
}

.stat-number:hover {
    transform: scale(1.1);
    color: #3b82f6 !important;
}

/* Rest of the existing styles */
// ... existing styles ...
</style>

<script>
// Add intersection observer for scroll animations
document.addEventListener('DOMContentLoaded', function() {
    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.style.visibility = 'visible';
                entry.target.classList.add('animate__animated');
            }
        });
    }, {
        threshold: 0.1
    });

    document.querySelectorAll('.feature-card, .about-card, .stat-number, .cta h2, .cta p, .cta a, .footer > div > div').forEach((el) => {
        el.style.visibility = 'hidden';
        observer.observe(el);
    });
});
</script>

<style>
/* Add these styles for testimonials and contact sections */
.testimonial-card {
    background: white;
    padding: 2rem;
    border-radius: 1rem;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    transition: all 0.3s ease;
    height: 100%;
}

.testimonial-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 15px rgba(0, 0, 0, 0.2);
}

.testimonial-content {
    height: 100%;
    display: flex;
    flex-direction: column;
}

.testimonial-content p {
    flex-grow: 1;
    font-style: italic;
    color: #6c757d;
}

.contact-card {
    background: white;
    padding: 3rem;
    border-radius: 1rem;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
}

.form-floating > .form-control {
    padding: 1rem 0.75rem;
}

.form-floating > label {
    padding: 1rem 0.75rem;
}

.form-control:focus {
    border-color: #3b82f6;
    box-shadow: 0 0 0 0.25rem rgba(59, 130, 246, 0.25);
}

.btn-primary {
    background-color: #3b82f6;
    border-color: #3b82f6;
    padding: 0.75rem 2rem;
    transition: all 0.3s ease;
}

.btn-primary:hover {
    background-color: #2563eb;
    border-color: #2563eb;
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(37, 99, 235, 0.3);
}

/* Enhanced feature section styles */
.feature-card {
    background: white;
    padding: 2.5rem;
    border-radius: 1rem;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    transition: all 0.3s ease;
    height: 100%;
}

.feature-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 15px rgba(0, 0, 0, 0.2);
}

.feature-icon {
    width: 70px;
    height: 70px;
    background: #e3f2fd;
    border-radius: 1rem;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-bottom: 2rem;
    transition: all 0.3s ease;
}

.feature-card:hover .feature-icon {
    background: #3b82f6;
    transform: rotate(360deg);
}

.feature-icon i {
    font-size: 2rem;
    color: #3b82f6;
    transition: all 0.3s ease;
}

.feature-card:hover .feature-icon i {
    color: white;
}
</style>

<script>
// Add form validation
document.addEventListener('DOMContentLoaded', function() {
    const forms = document.querySelectorAll('.needs-validation');
    
    Array.from(forms).forEach(form => {
        form.addEventListener('submit', event => {
            if (!form.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
            }
            form.classList.add('was-validated');
        }, false);
    });
});
</script>
</body>
</html>
