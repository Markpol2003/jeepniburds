<?php
// Test file to demonstrate real-time fare collection system
// This file shows how passengers can pay fares and drivers get notified

session_start();
require_once 'db_config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$userId = $_SESSION['user_id'];
$userType = $_SESSION['user_type'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fare Collection System Test</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body class="bg-light">
    <div class="container mt-5">
        <div class="row">
            <div class="col-md-8 mx-auto">
                <div class="card shadow-lg">
                    <div class="card-header bg-primary text-white">
                        <h4 class="mb-0">
                            <i class="bi bi-cash-coin me-2"></i>
                            Real-Time Fare Collection System Test
                        </h4>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <!-- Passenger Section -->
                            <div class="col-md-6">
                                <div class="card border-success">
                                    <div class="card-header bg-success text-white">
                                        <h5 class="mb-0">
                                            <i class="bi bi-person me-2"></i>
                                            Passenger Test
                                        </h5>
                                    </div>
                                    <div class="card-body">
                                        <p class="text-muted">Simulate a passenger paying fare:</p>
                                        <form id="passengerTestForm">
                                            <div class="mb-3">
                                                <label class="form-label">Route</label>
                                                <select class="form-select" id="testRoute" required>
                                                    <option value="">Select Route</option>
                                                    <option value="Route 1">Route 1</option>
                                                    <option value="Route 2">Route 2</option>
                                                    <option value="Route 3">Route 3</option>
                                                </select>
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label">Amount</label>
                                                <input type="number" class="form-control" id="testAmount" placeholder="Enter amount" required>
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label">Payment Method</label>
                                                <select class="form-select" id="testMethod" required>
                                                    <option value="">Select Method</option>
                                                    <option value="Cash">Cash</option>
                                                    <option value="GCash">GCash</option>
                                                    <option value="Bank">Bank</option>
                                                </select>
                                            </div>
                                            <button type="submit" class="btn btn-success w-100">
                                                <i class="bi bi-credit-card me-2"></i>
                                                Pay Fare
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>

                            <!-- Driver Section -->
                            <div class="col-md-6">
                                <div class="card border-primary">
                                    <div class="card-header bg-primary text-white">
                                        <h5 class="mb-0">
                                            <i class="bi bi-truck me-2"></i>
                                            Driver Test
                                        </h5>
                                    </div>
                                    <div class="card-body">
                                        <p class="text-muted">Monitor fare payments for a route:</p>
                                        <div class="mb-3">
                                            <label class="form-label">Select Route to Monitor</label>
                                            <select class="form-select" id="driverRoute">
                                                <option value="">Select Route</option>
                                                <option value="Route 1">Route 1</option>
                                                <option value="Route 2">Route 2</option>
                                                <option value="Route 3">Route 3</option>
                                            </select>
                                        </div>
                                        <button class="btn btn-primary w-100 mb-3" onclick="startMonitoring()">
                                            <i class="bi bi-play-circle me-2"></i>
                                            Start Monitoring
                                        </button>
                                        <button class="btn btn-secondary w-100" onclick="stopMonitoring()">
                                            <i class="bi bi-stop-circle me-2"></i>
                                            Stop Monitoring
                                        </button>
                                        
                                        <div id="monitoringStatus" class="mt-3" style="display: none;">
                                            <div class="alert alert-info">
                                                <i class="bi bi-info-circle me-2"></i>
                                                Monitoring active - checking for new payments every 5 seconds
                                            </div>
                                        </div>
                                        
                                        <div id="faresList" class="mt-3">
                                            <!-- Fares will be displayed here -->
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Instructions -->
                        <div class="mt-4">
                            <div class="card border-info">
                                <div class="card-header bg-info text-white">
                                    <h6 class="mb-0">
                                        <i class="bi bi-info-circle me-2"></i>
                                        How to Test
                                    </h6>
                                </div>
                                <div class="card-body">
                                    <ol>
                                        <li><strong>Passenger Test:</strong> Select a route, enter amount, choose payment method, and click "Pay Fare"</li>
                                        <li><strong>Driver Test:</strong> Select the same route, click "Start Monitoring" to see real-time updates</li>
                                        <li><strong>Real-time Updates:</strong> When a passenger pays, the driver will see notifications and updated fare list</li>
                                        <li><strong>Notifications:</strong> Both browser notifications and SweetAlert toasts will appear for new payments</li>
                                    </ol>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        let monitoringInterval;
        let currentRoute = '';

        // Passenger test form
        document.getElementById('passengerTestForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const route = document.getElementById('testRoute').value;
            const amount = document.getElementById('testAmount').value;
            const method = document.getElementById('testMethod').value;
            
            if (!route || !amount || !method) {
                Swal.fire('Error', 'Please fill in all fields', 'error');
                return;
            }

            // Show loading
            Swal.fire({
                title: 'Processing Payment...',
                html: 'Please wait while we process your payment.',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });

            // Send payment request
            fetch('pay_fare.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({
                    passenger_id: <?= $userId ?>,
                    route: route,
                    amount: parseFloat(amount),
                    payment_method: method
                })
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Payment Successful!',
                        html: `
                            <div class="text-center">
                                <div class="mb-3">
                                    <i class="bi bi-check-circle-fill text-success" style="font-size: 3rem;"></i>
                                </div>
                                <p><strong>Receipt #:</strong> ${data.receipt.receipt_number}</p>
                                <p><strong>Amount:</strong> ₱${data.receipt.amount}</p>
                                <p><strong>Method:</strong> ${data.receipt.payment_method}</p>
                                <p><strong>Route:</strong> ${data.receipt.route}</p>
                                <small class="text-muted">Driver will be notified automatically!</small>
                            </div>
                        `,
                        confirmButtonText: 'OK',
                        confirmButtonColor: '#28a745'
                    });
                    
                    // Reset form
                    document.getElementById('passengerTestForm').reset();
                    
                } else {
                    Swal.fire('Error', data.message || 'Payment failed', 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                Swal.fire('Error', 'Network error occurred', 'error');
            });
        });

        // Driver monitoring functions
        function startMonitoring() {
            const route = document.getElementById('driverRoute').value;
            if (!route) {
                Swal.fire('Error', 'Please select a route to monitor', 'error');
                return;
            }

            currentRoute = route;
            document.getElementById('monitoringStatus').style.display = 'block';
            
            // Start monitoring
            fetchFares();
            monitoringInterval = setInterval(fetchFares, 5000);
            
            Swal.fire({
                icon: 'success',
                title: 'Monitoring Started',
                text: `Now monitoring ${route} for new fare payments`,
                timer: 2000,
                showConfirmButton: false
            });
        }

        function stopMonitoring() {
            if (monitoringInterval) {
                clearInterval(monitoringInterval);
                monitoringInterval = null;
                document.getElementById('monitoringStatus').style.display = 'none';
                currentRoute = '';
                
                Swal.fire({
                    icon: 'info',
                    title: 'Monitoring Stopped',
                    text: 'No longer monitoring for new payments',
                    timer: 2000,
                    showConfirmButton: false
                });
            }
        }

        let lastFareReceipts = [];
        
        function fetchFares() {
            if (!currentRoute) return;
            
            fetch('pay_fare.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({ action: 'list', route: currentRoute })
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    const currentReceipts = data.fares.map(fare => fare.receipt_number);
                    const newFares = data.fares.filter(fare => !lastFareReceipts.includes(fare.receipt_number));
                    
                    // Show notifications for new fares
                    if (newFares.length > 0 && lastFareReceipts.length > 0) {
                        newFares.forEach(fare => {
                            // Browser notification
                            if (Notification.permission === 'granted') {
                                new Notification('New Fare Payment!', {
                                    body: `${fare.passenger} paid ₱${fare.amount} via ${fare.payment_method}`,
                                    icon: '/tebz/img/logo12.png'
                                });
                            }
                            
                            // SweetAlert notification
                            Swal.fire({
                                toast: true,
                                position: 'top-end',
                                icon: 'success',
                                title: 'New Fare Payment!',
                                text: `${fare.passenger} paid ₱${fare.amount} via ${fare.payment_method}`,
                                showConfirmButton: false,
                                timer: 4000,
                                timerProgressBar: true
                            });
                        });
                    }
                    
                    lastFareReceipts = currentReceipts;
                    
                    // Update fares list
                    displayFares(data.fares);
                }
            })
            .catch(error => {
                console.error('Error fetching fares:', error);
            });
        }

        function displayFares(fares) {
            const container = document.getElementById('faresList');
            
            if (fares.length === 0) {
                container.innerHTML = '<div class="alert alert-info">No fares found for this route</div>';
                return;
            }
            
            let html = '<h6 class="mb-3">Recent Fares:</h6><div class="table-responsive"><table class="table table-sm">';
            html += '<thead><tr><th>Passenger</th><th>Amount</th><th>Method</th><th>Status</th></tr></thead><tbody>';
            
            fares.forEach(fare => {
                html += `<tr>
                    <td>${fare.passenger}</td>
                    <td>₱${fare.amount}</td>
                    <td><span class="badge bg-primary">${fare.payment_method}</span></td>
                    <td><span class="badge bg-${fare.status === 'Collected' ? 'success' : 'warning'}">${fare.status}</span></td>
                </tr>`;
            });
            
            html += '</tbody></table></div>';
            container.innerHTML = html;
        }

        // Request notification permission on page load
        document.addEventListener('DOMContentLoaded', function() {
            if (Notification.permission !== 'granted' && Notification.permission !== 'denied') {
                Notification.requestPermission();
            }
        });
    </script>
</body>
</html> 