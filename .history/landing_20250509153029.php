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
    
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    
    <!-- Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    
    <!-- SweetAlert2 -->
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11.7.32/dist/sweetalert2.min.css" rel="stylesheet">

    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: '#3b82f6',
                        secondary: '#1d4ed8',
                        dark: '#1e293b',
                    },
                    fontFamily: {
                        sans: ['Poppins', 'sans-serif'],
                    },
                }
            }
        }
    </script>
</head>
<body class="font-sans">
    <!-- Navigation -->
    <nav class="fixed w-full z-50 transition-all duration-300" x-data="{ isOpen: false }">
        <div class="bg-dark/95 backdrop-blur-lg shadow-lg">
            <div class="container mx-auto px-4">
                <div class="flex justify-between items-center h-20">
                    <a href="#" class="flex items-center space-x-3 group">
                        <img src="\tebz\img\logo12.png" alt="JeepniGo Logo" class="w-12 h-auto transition-transform duration-300 group-hover:rotate-12">
                        <span class="text-2xl font-semibold text-white">JeepniGo</span>
                    </a>
                    
                    <!-- Desktop Menu -->
                    <div class="hidden md:flex items-center space-x-8">
                        <a href="#features" class="text-white hover:text-primary transition-colors duration-300">Features</a>
                        <a href="#about" class="text-white hover:text-primary transition-colors duration-300">About</a>
                        <a href="#testimonials" class="text-white hover:text-primary transition-colors duration-300">Testimonials</a>
                        <a href="#contact" class="text-white hover:text-primary transition-colors duration-300">Contact</a>
                        <button onclick="openLoginModal()" class="bg-primary hover:bg-secondary text-white px-6 py-2 rounded-full transition-all duration-300 transform hover:-translate-y-1 hover:shadow-lg">
                            Get Started
                        </button>
                    </div>

                    <!-- Mobile Menu Button -->
                    <button @click="isOpen = !isOpen" class="md:hidden text-white">
                        <i class="bi bi-list text-2xl"></i>
                    </button>
                </div>

                <!-- Mobile Menu -->
                <div x-show="isOpen" class="md:hidden bg-dark/98 backdrop-blur-lg rounded-lg mt-2 p-4">
                    <div class="flex flex-col space-y-4">
                        <a href="#features" class="text-white hover:text-primary transition-colors duration-300">Features</a>
                        <a href="#about" class="text-white hover:text-primary transition-colors duration-300">About</a>
                        <a href="#testimonials" class="text-white hover:text-primary transition-colors duration-300">Testimonials</a>
                        <a href="#contact" class="text-white hover:text-primary transition-colors duration-300">Contact</a>
                        <button onclick="openLoginModal()" class="bg-primary hover:bg-secondary text-white px-6 py-2 rounded-full transition-all duration-300">
                            Get Started
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section class="min-h-screen flex items-center relative" style="
        background: linear-gradient(rgba(0, 0, 0, 0.4), rgba(0, 0, 0, 0.4)),
        url('assets/img/bg.jpg') no-repeat center center;
        background-size: cover;
        background-attachment: fixed;
    ">
        <div class="absolute inset-0 bg-gradient-to-r from-dark/80 to-dark/40"></div>
        <div class="container mx-auto px-4 relative z-10">
            <div class="max-w-2xl">
                <h1 class="text-5xl md:text-6xl font-bold text-white mb-6 leading-tight">
                    Modern Jeepney Travel Experience
                </h1>
                <p class="text-xl text-white/90 mb-8">
                    Transform your daily commute with real-time tracking, easy booking, and secure payments.
                </p>
                <div class="flex flex-wrap gap-4">
                    <a href="#" class="bg-white hover:bg-gray-100 text-dark px-8 py-3 rounded-full font-medium transition-all duration-300 transform hover:-translate-y-1 hover:shadow-lg">
                        Get Started
                    </a>
                    <a href="#features" class="border-2 border-white text-white hover:bg-white hover:text-dark px-8 py-3 rounded-full font-medium transition-all duration-300 transform hover:-translate-y-1">
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
            <div class="grid md:grid-cols-3 gap-8">
                <!-- Feature Card 1 -->
                <div class="bg-white rounded-2xl p-8 shadow-lg hover:shadow-xl transition-all duration-300 transform hover:-translate-y-2">
                    <div class="w-16 h-16 bg-primary/10 rounded-xl flex items-center justify-center mb-6">
                        <i class="bi bi-geo-alt text-3xl text-primary"></i>
                    </div>
                    <h3 class="text-xl font-semibold text-gray-900 mb-4">Real-time Tracking</h3>
                    <p class="text-gray-600">Know exactly where your jeepney is and when it will arrive.</p>
                </div>

                <!-- Feature Card 2 -->
                <div class="bg-white rounded-2xl p-8 shadow-lg hover:shadow-xl transition-all duration-300 transform hover:-translate-y-2">
                    <div class="w-16 h-16 bg-primary/10 rounded-xl flex items-center justify-center mb-6">
                        <i class="bi bi-phone text-3xl text-primary"></i>
                    </div>
                    <h3 class="text-xl font-semibold text-gray-900 mb-4">Easy Booking</h3>
                    <p class="text-gray-600">Book your rides in advance with just a few taps.</p>
                </div>

                <!-- Feature Card 3 -->
                <div class="bg-white rounded-2xl p-8 shadow-lg hover:shadow-xl transition-all duration-300 transform hover:-translate-y-2">
                    <div class="w-16 h-16 bg-primary/10 rounded-xl flex items-center justify-center mb-6">
                        <i class="bi bi-shield-check text-3xl text-primary"></i>
                    </div>
                    <h3 class="text-xl font-semibold text-gray-900 mb-4">Secure Payments</h3>
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
            <div class="grid md:grid-cols-3 gap-8">
                <!-- About Card 1 -->
                <div class="bg-gray-50 rounded-2xl p-8 hover:bg-primary/5 transition-all duration-300">
                    <div class="w-16 h-16 bg-primary/10 rounded-xl flex items-center justify-center mb-6">
                        <i class="bi bi-people text-3xl text-primary"></i>
                    </div>
                    <h3 class="text-xl font-semibold text-gray-900 mb-4">For Commuters</h3>
                    <p class="text-gray-600">We help commuters find available jeepney rides quickly, especially during peak hours, reducing waiting time and improving the overall travel experience.</p>
                </div>

                <!-- About Card 2 -->
                <div class="bg-gray-50 rounded-2xl p-8 hover:bg-primary/5 transition-all duration-300">
                    <div class="w-16 h-16 bg-primary/10 rounded-xl flex items-center justify-center mb-6">
                        <i class="bi bi-cash-coin text-3xl text-primary"></i>
                    </div>
                    <h3 class="text-xl font-semibold text-gray-900 mb-4">For Drivers</h3>
                    <p class="text-gray-600">Our platform helps drivers manage earnings more effectively, reduce unpaid fares, and optimize their routes for better income and efficiency.</p>
                </div>

                <!-- About Card 3 -->
                <div class="bg-gray-50 rounded-2xl p-8 hover:bg-primary/5 transition-all duration-300">
                    <div class="w-16 h-16 bg-primary/10 rounded-xl flex items-center justify-center mb-6">
                        <i class="bi bi-graph-up text-3xl text-primary"></i>
                    </div>
                    <h3 class="text-xl font-semibold text-gray-900 mb-4">Smart Solutions</h3>
                    <p class="text-gray-600">Through route optimization and real-time tracking, we're creating a more organized and efficient public transportation system for everyone.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Stats Section -->
    <section class="py-20 bg-gradient-to-r from-primary to-secondary">
        <div class="container mx-auto px-4">
            <div class="grid md:grid-cols-4 gap-8 text-center">
                <div class="p-6">
                    <div class="text-4xl font-bold text-white mb-2">50K+</div>
                    <p class="text-white/80">Daily Users</p>
                </div>
                <div class="p-6">
                    <div class="text-4xl font-bold text-white mb-2">1000+</div>
                    <p class="text-white/80">Registered Jeepneys</p>
                </div>
                <div class="p-6">
                    <div class="text-4xl font-bold text-white mb-2">100+</div>
                    <p class="text-white/80">Routes Covered</p>
                </div>
                <div class="p-6">
                    <div class="text-4xl font-bold text-white mb-2">4.8</div>
                    <p class="text-white/80">User Rating</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="bg-dark text-white py-16">
        <div class="container mx-auto px-4">
            <div class="grid md:grid-cols-4 gap-12">
                <div>
                    <div class="flex items-center space-x-3 mb-6">
                        <img src="\tebz\img\logo12.png" alt="JeepniGo Logo" class="w-10 h-auto">
                        <span class="text-2xl font-bold">JeepniGo</span>
                    </div>
                    <p class="text-gray-400 mb-6">Making public transportation more accessible, efficient, and enjoyable for everyone.</p>
                    <div class="flex space-x-4">
                        <a href="#" class="text-gray-400 hover:text-white transition-colors duration-300">
                            <i class="bi bi-facebook text-xl"></i>
                        </a>
                        <a href="#" class="text-gray-400 hover:text-white transition-colors duration-300">
                            <i class="bi bi-twitter text-xl"></i>
                        </a>
                        <a href="#" class="text-gray-400 hover:text-white transition-colors duration-300">
                            <i class="bi bi-instagram text-xl"></i>
                        </a>
                        <a href="#" class="text-gray-400 hover:text-white transition-colors duration-300">
                            <i class="bi bi-linkedin text-xl"></i>
                        </a>
                    </div>
                </div>
                <div>
                    <h5 class="text-lg font-semibold mb-6">Quick Links</h5>
                    <ul class="space-y-3">
                        <li><a href="#" class="text-gray-400 hover:text-white transition-colors duration-300">Home</a></li>
                        <li><a href="#features" class="text-gray-400 hover:text-white transition-colors duration-300">Features</a></li>
                        <li><a href="#about" class="text-gray-400 hover:text-white transition-colors duration-300">About</a></li>
                        <li><a href="#contact" class="text-gray-400 hover:text-white transition-colors duration-300">Contact</a></li>
                    </ul>
                </div>
                <div>
                    <h5 class="text-lg font-semibold mb-6">Legal</h5>
                    <ul class="space-y-3">
                        <li><a href="#" class="text-gray-400 hover:text-white transition-colors duration-300">Privacy Policy</a></li>
                        <li><a href="#" class="text-gray-400 hover:text-white transition-colors duration-300">Terms of Service</a></li>
                        <li><a href="#" class="text-gray-400 hover:text-white transition-colors duration-300">Cookie Policy</a></li>
                        <li><a href="#" class="text-gray-400 hover:text-white transition-colors duration-300">FAQ</a></li>
                    </ul>
                </div>
                <div>
                    <h5 class="text-lg font-semibold mb-6">Newsletter</h5>
                    <p class="text-gray-400 mb-4">Subscribe to our newsletter for updates and offers.</p>
                    <form class="space-y-4">
                        <input type="email" placeholder="Enter your email" class="w-full px-4 py-2 rounded-lg bg-gray-800 border border-gray-700 text-white focus:outline-none focus:border-primary">
                        <button type="submit" class="w-full bg-primary hover:bg-secondary text-white px-6 py-2 rounded-lg transition-colors duration-300">
                            Subscribe
                        </button>
                    </form>
                </div>
            </div>
            <div class="border-t border-gray-800 mt-12 pt-8 text-center text-gray-400">
                <p>&copy; 2024 JeepniGo. All rights reserved.</p>
            </div>
        </div>
    </footer>

    <!-- Alpine.js for mobile menu -->
    <script src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
    
    <!-- Existing scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.7.32/dist/sweetalert2.all.min.js"></script>
    
    <script>
    // Navbar scroll effect
    window.addEventListener('scroll', function() {
        const navbar = document.querySelector('nav');
        if (window.scrollY > 50) {
            navbar.classList.add('shadow-lg');
        } else {
            navbar.classList.remove('shadow-lg');
        }
    });

    // Smooth scroll
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function (e) {
            e.preventDefault();
            document.querySelector(this.getAttribute('href')).scrollIntoView({
                behavior: 'smooth'
            });
        });
    });
    </script>
</body>
</html>
