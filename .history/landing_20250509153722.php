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
<section class="min-h-screen bg-cover bg-center bg-fixed relative" style="background-image: linear-gradient(rgba(0, 0, 0, 0.4), rgba(0, 0, 0, 0.4)), url('assets/img/bg.jpg');">
    <div class="container mx-auto px-4 h-screen flex items-center">
        <div class="max-w-2xl">
            <h1 class="text-5xl md:text-6xl font-bold text-white mb-6 leading-tight">Modern Jeepney Travel Experience</h1>
            <p class="text-xl text-white/90 mb-8">Transform your daily commute with real-time tracking, easy booking, and secure payments.</p>
            <div class="flex gap-4">
                <a href="#" class="inline-flex items-center px-8 py-3 text-lg font-medium text-gray-900 bg-white rounded-lg hover:bg-gray-100 transition-all duration-300 shadow-lg hover:shadow-xl">
                    Get Started
                </a>
                <a href="#" class="inline-flex items-center px-8 py-3 text-lg font-medium text-white border-2 border-white rounded-lg hover:bg-white/10 transition-all duration-300">
                    Learn More
                </a>
            </div>
        </div>
    </div>
</section>

    <!-- Features Section -->
    <section id="features" class="py-20 bg-gray-50">
        <div class="container mx-auto px-4">
            <div class="text-center max-w-3xl mx-auto mb-16">
                <h2 class="text-4xl font-bold text-gray-900 mb-4">Why Choose JeepniGo</h2>
                <p class="text-xl text-gray-600">Experience the future of public transportation with our innovative features.</p>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                <div class="bg-white p-8 rounded-2xl shadow-lg hover:shadow-xl transition-all duration-300 transform hover:-translate-y-1">
                    <div class="w-14 h-14 bg-blue-100 rounded-xl flex items-center justify-center mb-6">
                        <i class="bi bi-geo-alt text-2xl text-blue-600"></i>
                    </div>
                    <h4 class="text-xl font-semibold text-gray-900 mb-3">Real-time Tracking</h4>
                    <p class="text-gray-600">Know exactly where your jeepney is and when it will arrive.</p>
                </div>
                <div class="bg-white p-8 rounded-2xl shadow-lg hover:shadow-xl transition-all duration-300 transform hover:-translate-y-1">
                    <div class="w-14 h-14 bg-blue-100 rounded-xl flex items-center justify-center mb-6">
                        <i class="bi bi-phone text-2xl text-blue-600"></i>
                    </div>
                    <h4 class="text-xl font-semibold text-gray-900 mb-3">Easy Booking</h4>
                    <p class="text-gray-600">Book your rides in advance with just a few taps.</p>
                </div>
                <div class="bg-white p-8 rounded-2xl shadow-lg hover:shadow-xl transition-all duration-300 transform hover:-translate-y-1">
                    <div class="w-14 h-14 bg-blue-100 rounded-xl flex items-center justify-center mb-6">
                        <i class="bi bi-shield-check text-2xl text-blue-600"></i>
                    </div>
                    <h4 class="text-xl font-semibold text-gray-900 mb-3">Secure Payments</h4>
                    <p class="text-gray-600">Pay safely and conveniently through our platform.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- About Section -->
<section id="about" class="py-20 bg-white">
    <div class="container mx-auto px-4">
        <div class="text-center max-w-3xl mx-auto mb-16">
            <h2 class="text-4xl font-bold text-gray-900 mb-4">Our Mission</h2>
            <p class="text-xl text-gray-600">JeepniGo is revolutionizing public transportation by connecting commuters with jeepney drivers, making daily commutes more efficient and reliable for everyone.</p>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
            <div class="bg-gray-50 p-8 rounded-2xl hover:shadow-lg transition-all duration-300">
                <div class="w-14 h-14 bg-blue-100 rounded-xl flex items-center justify-center mb-6">
                    <i class="bi bi-people text-2xl text-blue-600"></i>
                </div>
                <h4 class="text-xl font-semibold text-gray-900 mb-3">For Commuters</h4>
                <p class="text-gray-600">We help commuters find available jeepney rides quickly, especially during peak hours, reducing waiting time and improving the overall travel experience.</p>
            </div>
            <div class="bg-gray-50 p-8 rounded-2xl hover:shadow-lg transition-all duration-300">
                <div class="w-14 h-14 bg-blue-100 rounded-xl flex items-center justify-center mb-6">
                    <i class="bi bi-cash-coin text-2xl text-blue-600"></i>
                </div>
                <h4 class="text-xl font-semibold text-gray-900 mb-3">For Drivers</h4>
                <p class="text-gray-600">Our platform helps drivers manage earnings more effectively, reduce unpaid fares, and optimize their routes for better income and efficiency.</p>
            </div>
            <div class="bg-gray-50 p-8 rounded-2xl hover:shadow-lg transition-all duration-300">
                <div class="w-14 h-14 bg-blue-100 rounded-xl flex items-center justify-center mb-6">
                    <i class="bi bi-graph-up text-2xl text-blue-600"></i>
                </div>
                <h4 class="text-xl font-semibold text-gray-900 mb-3">Smart Solutions</h4>
                <p class="text-gray-600">Through route optimization and real-time tracking, we're creating a more organized and efficient public transportation system for everyone.</p>
            </div>
        </div>
    </div>
</section>

<!-- Stats Section -->
<section class="py-20 bg-gradient-to-r from-blue-600 to-blue-800">
    <div class="container mx-auto px-4">
        <div class="grid grid-cols-2 md:grid-cols-4 gap-8 text-center">
            <div class="text-white">
                <div class="text-4xl md:text-5xl font-bold mb-2">50K+</div>
                <p class="text-blue-100">Daily Users</p>
            </div>
            <div class="text-white">
                <div class="text-4xl md:text-5xl font-bold mb-2">1000+</div>
                <p class="text-blue-100">Registered Jeepneys</p>
            </div>
            <div class="text-white">
                <div class="text-4xl md:text-5xl font-bold mb-2">100+</div>
                <p class="text-blue-100">Routes Covered</p>
            </div>
            <div class="text-white">
                <div class="text-4xl md:text-5xl font-bold mb-2">4.8</div>
                <p class="text-blue-100">User Rating</p>
            </div>
        </div>
    </div>
</section>

<!-- Call to Action -->
<section class="py-20 bg-gray-900 text-center">
    <div class="container mx-auto px-4">
        <h2 class="text-4xl font-bold text-white mb-4">Ready to Transform Your Travel?</h2>
        <p class="text-xl text-gray-300 mb-8">Join thousands of satisfied commuters who have made the switch to JeepniGo.</p>
        <a href="#" class="inline-flex items-center px-8 py-3 text-lg font-medium text-gray-900 bg-white rounded-lg hover:bg-gray-100 transition-all duration-300 shadow-lg hover:shadow-xl">
            Get Started Now
        </a>
    </div>
</section>

<!-- Footer -->
<footer class="bg-gray-900 text-gray-300 py-16">
    <div class="container mx-auto px-4">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-8">
            <div>
                <h3 class="text-2xl font-bold text-white mb-4">JeepniGo</h3>
                <p class="text-gray-400">Making public transportation more accessible, efficient, and enjoyable for everyone.</p>
            </div>
            <div>
                <h5 class="text-lg font-semibold text-white mb-4">Quick Links</h5>
                <ul class="space-y-2">
                    <li><a href="#" class="hover:text-white transition-colors">Home</a></li>
                    <li><a href="#" class="hover:text-white transition-colors">About</a></li>
                    <li><a href="#" class="hover:text-white transition-colors">Features</a></li>
                    <li><a href="#" class="hover:text-white transition-colors">Contact</a></li>
                </ul>
            </div>
            <div>
                <h5 class="text-lg font-semibold text-white mb-4">Legal</h5>
                <ul class="space-y-2">
                    <li><a href="#" class="hover:text-white transition-colors">Privacy Policy</a></li>
                    <li><a href="#" class="hover:text-white transition-colors">Terms of Service</a></li>
                    <li><a href="#" class="hover:text-white transition-colors">FAQ</a></li>
                </ul>
            </div>
            <div>
                <h5 class="text-lg font-semibold text-white mb-4">Newsletter</h5>
                <p class="text-gray-400 mb-4">Subscribe to our newsletter for updates and offers.</p>
                <div class="flex gap-2">
                    <input type="email" class="flex-1 px-4 py-2 rounded-lg bg-gray-800 border border-gray-700 focus:outline-none focus:border-blue-500" placeholder="Enter your email">
                    <button class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
                        Subscribe
                    </button>
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
</body>
</html>
