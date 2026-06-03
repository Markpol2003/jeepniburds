<?php
session_start();
require_once 'db_config.php';

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
                // Compare password directly (since no hashing)
                if ($password === $user['password']) {
                    session_regenerate_id(true); // Prevent session fixation attacks

                    // Set session variables
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['user_email'] = $user['email'];
                    $_SESSION['user_firstName'] = $user['firstName'];
                    $_SESSION['user_lastName'] = $user['lastName'];
                    $_SESSION['user_type'] = $user['userType'];

                    // Redirect based on user role
                    $dashboardRoutes = [
                        'passenger' => 'passenger_dashboard.php?page=dashboard',
                        'driver'    => 'driver_dashboard.php',
                        'operator'  => 'operator_dashboard.php',
                        'manager'   => 'manager_dashboard.php',
                        'admin'     => 'admin_dashboard.php',
                        'treasurer' => 'treasurer_dashboard.php'
                    ];

                    $redirect = $dashboardRoutes[$user['userType']] ?? 'passenger_dashboard.php';

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
<section style="
  min-height: 100vh;
  background: linear-gradient(rgba(0, 0, 0, 0.4), rgba(0, 0, 0, 0.4)),
  url('assets/img/bg.jpg') no-repeat center center;
  background-size: cover;
  background-attachment: fixed;
  color: white;
  display: flex;
  align-items: center;
">
  <div class="container">
    <div class="row">
      <div class="col-lg-6">
        <h1 class="display-4 fw-bold">Modern Jeepney Travel Experience</h1>
        <p class="lead">Transform your daily commute with real-time tracking, easy booking, and secure payments.</p>
        <a href="#" class="btn btn-light btn-lg me-3">Get Started</a>
        <a href="#" class="btn btn-outline-light btn-lg">Learn More</a>
      </div>
    </div>
  </div>
</section>



    <!-- Features Section -->
    <section id="features" class="py-20 bg-gray-50">
        <div class="container">
            <div class="row text-center mb-5 animate__animated animate__fadeIn">
                <div class="col-lg-8 mx-auto">
                    <br>
                    <br>
                    <br>
                    <h2 class="display-5 fw-bold mb-4">Why Choose JeepniGo</h2>
                    <p class="lead text-muted">Experience the future of public transportation with our innovative features.</p>
                </div>
            </div>
            <div class="row g-4">
                <div class="col-lg-4">
                    <div class="feature-card animate__animated animate__fadeInUp" style="animation-delay: 0.2s;">
                        <div class="feature-icon">
                            <i class="bi bi-geo-alt"></i>
                        </div>
                        <h4 class="h3 mb-3">Real-time Tracking</h4>
                        <p class="text-muted">Know exactly where your jeepney is and when it will arrive.</p>
                    </div>
                </div>
                <div class="col-lg-4">
                    <div class="feature-card animate__animated animate__fadeInUp" style="animation-delay: 0.4s;">
                        <div class="feature-icon">
                            <i class="bi bi-phone"></i>
                        </div>
                        <h4 class="h3 mb-3">Easy Booking</h4>
                        <p class="text-muted">Book your rides in advance with just a few taps.</p>
                    </div>
                </div>
                <div class="col-lg-4">
                    <div class="feature-card animate__animated animate__fadeInUp" style="animation-delay: 0.6s;">
                        <div class="feature-icon">
                            <i class="bi bi-shield-check"></i>
                        </div>
                        <h4 class="h3 mb-3">Secure Payments</h4>
                        <p class="text-muted">Pay safely and conveniently through our platform.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Testimonials Section -->
    <section id="testimonials" class="py-20 bg-white">
        <div class="container">
            <div class="row text-center mb-5 animate__animated animate__fadeIn">
                <div class="col-lg-8 mx-auto">
                    <br>
                    <br>
                    <br>
                    <h2 class="display-5 fw-bold mb-4">What Our Users Say</h2>
                    <p class="lead text-muted">Hear from our satisfied commuters and drivers about their experience with JeepniGo.</p>
                </div>
            </div>
            <div class="row g-4">
                <div class="col-lg-4">
                    <div class="testimonial-card animate__animated animate__fadeInUp" style="animation-delay: 0.2s;">
                        <div class="testimonial-content">
                            <div class="rating mb-3">
                                <i class="bi bi-star-fill text-warning"></i>
                                <i class="bi bi-star-fill text-warning"></i>
                                <i class="bi bi-star-fill text-warning"></i>
                                <i class="bi bi-star-fill text-warning"></i>
                                <i class="bi bi-star-fill text-warning"></i>
                            </div>
                            <p class="mb-4">"JeepniGo has made my daily commute so much easier. I can now track my jeepney in real-time and plan my journey better."</p>
                            <div class="testimonial-author d-flex align-items-center">
                                <img src="assets/img/testimonial-1.jpg" alt="User" class="rounded-circle me-3" width="50">
                                <div>
                                    <h6 class="mb-0">Sarah Johnson</h6>
                                    <small class="text-muted">Regular Commuter</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-4">
                    <div class="testimonial-card animate__animated animate__fadeInUp" style="animation-delay: 0.4s;">
                        <div class="testimonial-content">
                            <div class="rating mb-3">
                                <i class="bi bi-star-fill text-warning"></i>
                                <i class="bi bi-star-fill text-warning"></i>
                                <i class="bi bi-star-fill text-warning"></i>
                                <i class="bi bi-star-fill text-warning"></i>
                                <i class="bi bi-star-fill text-warning"></i>
                            </div>
                            <p class="mb-4">"As a jeepney driver, JeepniGo has helped me increase my earnings and manage my routes more efficiently."</p>
                            <div class="testimonial-author d-flex align-items-center">
                                <img src="assets/img/testimonial-2.jpg" alt="User" class="rounded-circle me-3" width="50">
                                <div>
                                    <h6 class="mb-0">Michael Santos</h6>
                                    <small class="text-muted">Jeepney Driver</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-4">
                    <div class="testimonial-card animate__animated animate__fadeInUp" style="animation-delay: 0.6s;">
                        <div class="testimonial-content">
                            <div class="rating mb-3">
                                <i class="bi bi-star-fill text-warning"></i>
                                <i class="bi bi-star-fill text-warning"></i>
                                <i class="bi bi-star-fill text-warning"></i>
                                <i class="bi bi-star-fill text-warning"></i>
                                <i class="bi bi-star-half text-warning"></i>
                            </div>
                            <p class="mb-4">"The booking system is so convenient, and the real-time tracking feature gives me peace of mind during my commute."</p>
                            <div class="testimonial-author d-flex align-items-center">
                                <img src="assets/img/testimonial-3.jpg" alt="User" class="rounded-circle me-3" width="50">
                                <div>
                                    <h6 class="mb-0">Maria Garcia</h6>
                                    <small class="text-muted">Student</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Stats Section -->
    <section class="stats py-20 bg-gradient-to-r from-blue-600 to-blue-800">
        <div class="container">
            <div class="row text-center">
                <div class="col-lg-3 col-md-6 animate__animated animate__fadeInUp" style="animation-delay: 0.2s;">
                    <div class="stat-card">
                        <div class="stat-icon mb-3">
                            <i class="bi bi-people-fill"></i>
                        </div>
                        <div class="stat-number text-dark">50K+</div>
                        <p class="text-dark fw-medium">Daily Users</p>
                        <div class="user-profiles mt-3">
                            <img src="assets/img/user1.jpg" alt="User" class="rounded-circle" width="40">
                            <img src="assets/img/user2.jpg" alt="User" class="rounded-circle" width="40">
                            <img src="assets/img/user3.jpg" alt="User" class="rounded-circle" width="40">
                            <span class="more-users">+47K</span>
                        </div>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6 animate__animated animate__fadeInUp" style="animation-delay: 0.4s;">
                    <div class="stat-card">
                        <div class="stat-icon mb-3">
                            <i class="bi bi-truck"></i>
                        </div>
                        <div class="stat-number text-dark">1000+</div>
                        <p class="text-dark fw-medium">Registered Jeepneys</p>
                        <div class="driver-profiles mt-3">
                            <div class="driver">
                                <img src="assets/img/driver1.jpg" alt="Driver" class="rounded-circle" width="40">
                                <span>Juan Dela Cruz</span>
                            </div>
                            <div class="driver">
                                <img src="assets/img/driver2.jpg" alt="Driver" class="rounded-circle" width="40">
                                <span>Maria Santos</span>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6 animate__animated animate__fadeInUp" style="animation-delay: 0.6s;">
                    <div class="stat-card">
                        <div class="stat-icon mb-3">
                            <i class="bi bi-map"></i>
                        </div>
                        <div class="stat-number text-dark">100+</div>
                        <p class="text-dark fw-medium">Routes Covered</p>
                        <div class="popular-routes mt-3">
                            <div class="route">
                                <i class="bi bi-geo-alt-fill text-primary"></i>
                                <span>Manila - Quezon City</span>
                            </div>
                            <div class="route">
                                <i class="bi bi-geo-alt-fill text-primary"></i>
                                <span>Makati - Taguig</span>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6 animate__animated animate__fadeInUp" style="animation-delay: 0.8s;">
                    <div class="stat-card">
                        <div class="stat-icon mb-3">
                            <i class="bi bi-star-fill"></i>
                        </div>
                        <div class="stat-number text-dark">4.8</div>
                        <p class="text-dark fw-medium">User Rating</p>
                        <div class="ratings mt-3">
                            <div class="rating">
                                <img src="assets/img/user4.jpg" alt="User" class="rounded-circle" width="40">
                                <div class="stars">
                                    <i class="bi bi-star-fill text-warning"></i>
                                    <i class="bi bi-star-fill text-warning"></i>
                                    <i class="bi bi-star-fill text-warning"></i>
                                    <i class="bi bi-star-fill text-warning"></i>
                                    <i class="bi bi-star-fill text-warning"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Call to Action -->
    <section class="cta text-center py-20 bg-gray-900">
        <div class="container">
            <h2 class="mb-4 text-white animate__animated animate__fadeIn">Ready to Transform Your Travel?</h2>
            <p class="mb-4 text-gray-300 animate__animated animate__fadeIn" style="animation-delay: 0.2s;">Join thousands of satisfied commuters who have made the switch to JeepniGo.</p>
            <a href="#" class="btn btn-light btn-lg animate__animated animate__fadeInUp" style="animation-delay: 0.4s;">Get Started Now</a>
        </div>
    </section>

    <!-- Contact Section -->
    <section id="contact" class="py-20 bg-gray-50">
        <div class="container">
            <div class="row text-center mb-5 animate__animated animate__fadeIn">
                <div class="col-lg-8 mx-auto">
                    <br>
                    <br>
                    <br>


                    <h2 class="display-5 fw-bold mb-4">Get in Touch</h2>
                    <p class="lead text-muted">Have questions? We'd love to hear from you. Send us a message and we'll respond as soon as possible.</p>
                </div>
            </div>
            <div class="row justify-content-center">
                <div class="col-lg-8">
                    <div class="contact-card animate__animated animate__fadeInUp">
                        <form id="contactForm" class="needs-validation" novalidate>
                            <div class="row g-4">
                                <div class="col-md-6">
                                    <div class="form-floating mb-3">
                                        <input type="text" class="form-control" id="name" placeholder="Your Name" required>
                                        <label for="name">Your Name</label>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-floating mb-3">
                                        <input type="email" class="form-control" id="email" placeholder="Your Email" required>
                                        <label for="email">Your Email</label>
                                    </div>
                                </div>
                                <div class="col-12">
                                    <div class="form-floating mb-3">
                                        <input type="text" class="form-control" id="subject" placeholder="Subject" required>
                                        <label for="subject">Subject</label>
                                    </div>
                                </div>
                                <div class="col-12">
                                    <div class="form-floating mb-3">
                                        <textarea class="form-control" id="message" placeholder="Your Message" style="height: 150px" required></textarea>
                                        <label for="message">Your Message</label>
                                    </div>
                                </div>
                                <div class="col-12 text-center">
                                    <button type="submit" class="btn btn-primary btn-lg px-5">Send Message</button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="footer py-16 bg-gray-900 text-gray-300">
        <div class="container">
            <div class="row">
                <div class="col-lg-4 animate__animated animate__fadeInLeft">
                    <h3 class="text-white">JeepniGo</h3>
                    <p>Making public transportation more accessible, efficient, and enjoyable for everyone.</p>
                </div>
                <div class="col-lg-2 animate__animated animate__fadeInUp" style="animation-delay: 0.2s;">
                    <h5 class="text-white">Quick Links</h5>
                    <ul class="footer-links">
                        <li><a href="#">Home</a></li>
                        <li><a href="#">About</a></li>
                        <li><a href="#">Features</a></li>
                        <li><a href="#">Contact</a></li>
                    </ul>
                </div>
                <div class="col-lg-2 animate__animated animate__fadeInUp" style="animation-delay: 0.4s;">
                    <h5 class="text-white">Legal</h5>
                    <ul class="footer-links">
                        <li><a href="#">Privacy Policy</a></li>
                        <li><a href="#">Terms of Service</a></li>
                        <li><a href="#">FAQ</a></li>
                    </ul>
                </div>
                <div class="col-lg-4 animate__animated animate__fadeInRight">
                    <h5 class="text-white">Newsletter</h5>
                    <p>Subscribe to our newsletter for updates and offers.</p>
                    <div class="input-group mb-3">
                        <input type="email" class="form-control" placeholder="Enter your email">
                        <button class="btn btn-primary" type="button">Subscribe</button>
                    </div>
                </div>
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
