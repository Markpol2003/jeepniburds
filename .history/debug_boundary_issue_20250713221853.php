<?php
require_once 'db_config.php';

echo "<h2>🔍 Boundary Payment Debug</h2>";

// Step 1: Check if boundary_payments table exists and has data
echo "<h3>Step 1: Database Check</h3>";
$tableExists = $conn->query("SHOW TABLES LIKE 'boundary_payments'");
if ($tableExists->num_rows > 0) {
    echo "✅ boundary_payments table exists<br>";
    
    $totalPayments = $conn->query("SELECT COUNT(*) as count FROM boundary_payments")->fetch_assoc()['count'];
    echo "Total boundary payments in database: $totalPayments<br>";
    
    if ($totalPayments > 0) {
        echo "<h4>Recent Payments:</h4>";
        $payments = $conn->query("SELECT * FROM boundary_payments ORDER BY paid_at DESC LIMIT 5");
        while ($payment = $payments->fetch_assoc()) {
            echo "- ID: {$payment['id']}, Driver: {$payment['driver_id']}, Operator: {$payment['operator_id']}, Amount: ₱{$payment['amount']}, Status: {$payment['status']}<br>";
        }
    } else {
        echo "❌ No boundary payments found in database<br>";
    }
} else {
    echo "❌ boundary_payments table does not exist<br>";
    exit;
}

// Step 2: Check users table for operators
echo "<h3>Step 2: User Check</h3>";
$operators = $conn->query("SELECT id, firstName, lastName, userType FROM users WHERE userType = 'operator'");
echo "Operators found: " . $operators->num_rows . "<br>";
while ($operator = $operators->fetch_assoc()) {
    echo "- ID: {$operator['id']}, Name: {$operator['firstName']} {$operator['lastName']}, Type: {$operator['userType']}<br>";
}

$drivers = $conn->query("SELECT id, firstName, lastName, userType FROM users WHERE userType = 'driver'");
echo "Drivers found: " . $drivers->num_rows . "<br>";
while ($driver = $drivers->fetch_assoc()) {
    echo "- ID: {$driver['id']}, Name: {$driver['firstName']} {$driver['lastName']}, Type: {$driver['userType']}<br>";
}

// Step 3: Check jeepney assignments
echo "<h3>Step 3: Jeepney Assignments Check</h3>";
$assignments = $conn->query("SELECT * FROM jeepney_assignments WHERE status = 'Active'");
echo "Active assignments: " . $assignments->num_rows . "<br>";
while ($assignment = $assignments->fetch_assoc()) {
    echo "- Driver: {$assignment['driver_id']}, Operator: {$assignment['operator_id']}, Jeepney: {$assignment['plate_number']}<br>";
}

// Step 4: Test API directly
echo "<h3>Step 4: API Test</h3>";
if ($operators->num_rows > 0) {
    $operator = $conn->query("SELECT id FROM users WHERE userType = 'operator' LIMIT 1")->fetch_assoc();
    $operator_id = $operator['id'];
    
    echo "Testing API for operator_id: $operator_id<br>";
    
    $testData = [
        'action' => 'list',
        'operator_id' => $operator_id
    ];
    
    // Simulate API call
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'http://localhost/tebzcopy/pay_boundary.php');
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($testData));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    echo "HTTP Response Code: $httpCode<br>";
    echo "API Response: " . htmlspecialchars($response) . "<br>";
    
    if ($response) {
        $data = json_decode($response, true);
        if ($data && isset($data['success'])) {
            if ($data['success']) {
                echo "✅ API working - Found " . count($data['boundaries']) . " boundary payments<br>";
                foreach ($data['boundaries'] as $boundary) {
                    echo "- Driver: {$boundary['driver']}, Amount: ₱{$boundary['amount']}, Status: {$boundary['status']}<br>";
                }
            } else {
                echo "❌ API failed: " . ($data['message'] ?? 'Unknown error') . "<br>";
            }
        } else {
            echo "❌ Invalid API response format<br>";
        }
    } else {
        echo "❌ No response from API<br>";
    }
} else {
    echo "❌ No operators found to test with<br>";
}

// Step 5: Create test data if needed
echo "<h3>Step 5: Create Test Data</h3>";
if ($totalPayments == 0) {
    echo "No boundary payments found. Creating test data...<br>";
    
    // Get first driver and operator
    $driver = $conn->query("SELECT id FROM users WHERE userType = 'driver' LIMIT 1")->fetch_assoc();
    $operator = $conn->query("SELECT id FROM users WHERE userType = 'operator' LIMIT 1")->fetch_assoc();
    
    if ($driver && $operator) {
        // Create test assignment if none exists
        $assignment = $conn->query("SELECT id FROM jeepney_assignments WHERE driver_id = {$driver['id']} AND status = 'Active' LIMIT 1")->fetch_assoc();
        if (!$assignment) {
            $conn->query("INSERT INTO jeepney_assignments (driver_id, operator_id, plate_number, body_number, route, status, assigned_date) VALUES ({$driver['id']}, {$operator['id']}, 'TEST-123', 'BODY-001', 'Test Route', 'Active', NOW())");
            echo "✅ Created test assignment<br>";
            $assignment = $conn->query("SELECT id FROM jeepney_assignments WHERE driver_id = {$driver['id']} AND status = 'Active' LIMIT 1")->fetch_assoc();
        }
        
        // Create test boundary payment
        $reference = 'TEST-' . date('Ymd') . '-001';
        $stmt = $conn->prepare("INSERT INTO boundary_payments (driver_id, operator_id, jeepney_id, amount, payment_method, status, reference_number, notes) VALUES (?, ?, ?, ?, ?, 'Pending', ?, ?)");
        $stmt->bind_param('iiidsss', $driver['id'], $operator['id'], $assignment['id'], 500, 'GCash', $reference, 'Test payment from debug script');
        
        if ($stmt->execute()) {
            echo "✅ Created test boundary payment<br>";
            echo "- Driver ID: {$driver['id']}<br>";
            echo "- Operator ID: {$operator['id']}<br>";
            echo "- Amount: ₱500<br>";
            echo "- Status: Pending<br>";
        } else {
            echo "❌ Failed to create test payment: " . $stmt->error . "<br>";
        }
    } else {
        echo "❌ Need at least one driver and one operator<br>";
    }
} else {
    echo "Boundary payments already exist in database<br>";
}

// Step 6: Check operator dashboard session
echo "<h3>Step 6: Session Check</h3>";
session_start();
if (isset($_SESSION['user_id']) && isset($_SESSION['user_type'])) {
    echo "Current session user: ID={$_SESSION['user_id']}, Type={$_SESSION['user_type']}<br>";
    
    if ($_SESSION['user_type'] === 'operator') {
        echo "✅ User is logged in as operator<br>";
        
        // Test the API with current operator
        $testData = [
            'action' => 'list',
            'operator_id' => $_SESSION['user_id']
        ];
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'http://localhost/tebzcopy/pay_boundary.php');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($testData));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        
        $response = curl_exec($ch);
        curl_close($ch);
        
        echo "API Response for current operator: " . htmlspecialchars($response) . "<br>";
    } else {
        echo "❌ User is not logged in as operator<br>";
    }
} else {
    echo "❌ No active session found<br>";
}

echo "<h3>🔧 Quick Fixes</h3>";
echo "<ul>";
echo "<li><a href='force_create_boundary_payment.php' target='_blank'>Force Create Boundary Payment</a></li>";
echo "<li><a href='test_boundary_payment_flow.php' target='_blank'>Run Full Boundary Test</a></li>";
echo "<li><a href='operator_dashboard.php?page=collect_boundaries' target='_blank'>Go to Operator Dashboard</a></li>";
echo "</ul>";

echo "<h3>📋 Summary</h3>";
echo "<div style='background: #e7f3ff; padding: 15px; border-radius: 5px;'>";
echo "<strong>Common Issues:</strong><br>";
echo "1. No boundary payments in database<br>";
echo "2. Operator ID mismatch<br>";
echo "3. API endpoint not working<br>";
echo "4. Session issues<br>";
echo "5. Database connection problems<br>";
echo "</div>";
?> 