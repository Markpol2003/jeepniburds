<?php
require_once 'db_config.php';

echo "<h2>🔧 Fix Boundary Payment Display</h2>";

// Step 1: Check current session
session_start();
echo "<h3>Step 1: Session Check</h3>";
if (isset($_SESSION['user_id']) && isset($_SESSION['user_type'])) {
    echo "✅ User logged in: ID={$_SESSION['user_id']}, Type={$_SESSION['user_type']}<br>";
    
    if ($_SESSION['user_type'] === 'operator') {
        echo "✅ User is operator<br>";
    } else {
        echo "❌ User is not operator (current type: {$_SESSION['user_type']})<br>";
    }
} else {
    echo "❌ No active session<br>";
}

// Step 2: Check database for boundary payments
echo "<h3>Step 2: Database Check</h3>";
$totalPayments = $conn->query("SELECT COUNT(*) as count FROM boundary_payments")->fetch_assoc()['count'];
echo "Total boundary payments: $totalPayments<br>";

if ($totalPayments == 0) {
    echo "❌ No boundary payments found. Creating test data...<br>";
    
    // Get first driver and operator
    $driver = $conn->query("SELECT id, firstName, lastName FROM users WHERE userType = 'driver' LIMIT 1")->fetch_assoc();
    $operator = $conn->query("SELECT id, firstName, lastName FROM users WHERE userType = 'operator' LIMIT 1")->fetch_assoc();
    
    if ($driver && $operator) {
        echo "✅ Found driver: {$driver['firstName']} {$driver['lastName']} (ID: {$driver['id']})<br>";
        echo "✅ Found operator: {$operator['firstName']} {$operator['lastName']} (ID: {$operator['id']})<br>";
        
        // Create test assignment if none exists
        $assignment = $conn->query("SELECT id FROM jeepney_assignments WHERE driver_id = {$driver['id']} AND status = 'Active' LIMIT 1")->fetch_assoc();
        if (!$assignment) {
            $conn->query("INSERT INTO jeepney_assignments (driver_id, operator_id, plate_number, body_number, route, status, assigned_date) VALUES ({$driver['id']}, {$operator['id']}, 'TEST-123', 'BODY-001', 'Test Route', 'Active', NOW())");
            echo "✅ Created test assignment<br>";
            $assignment = $conn->query("SELECT id FROM jeepney_assignments WHERE driver_id = {$driver['id']} AND status = 'Active' LIMIT 1")->fetch_assoc();
        } else {
            echo "✅ Found existing assignment<br>";
        }
        
        // Create multiple test boundary payments
        for ($i = 1; $i <= 3; $i++) {
            $reference = 'TEST-' . date('Ymd') . '-' . str_pad($i, 3, '0', STR_PAD_LEFT);
            $amount = 500 + ($i * 100);
            $payment_method = ['GCash', 'Cash', 'Bank'][$i % 3];
            
            $stmt = $conn->prepare("INSERT INTO boundary_payments (driver_id, operator_id, jeepney_id, amount, payment_method, status, reference_number, notes) VALUES (?, ?, ?, ?, ?, 'Pending', ?, ?)");
            $notes = "Test payment #$i from fix script";
            $stmt->bind_param('iiidsss', $driver['id'], $operator['id'], $assignment['id'], $amount, $payment_method, $reference, $notes);
            
            if ($stmt->execute()) {
                echo "✅ Created test payment #$i: ₱$amount via $payment_method<br>";
            } else {
                echo "❌ Failed to create test payment #$i: " . $stmt->error . "<br>";
            }
        }
        
        // Create one collected payment
        $reference = 'TEST-' . date('Ymd') . '-COL';
        $stmt = $conn->prepare("INSERT INTO boundary_payments (driver_id, operator_id, jeepney_id, amount, payment_method, status, reference_number, notes) VALUES (?, ?, ?, ?, ?, 'Collected', ?, ?)");
        $notes = "Test collected payment from fix script";
        $stmt->bind_param('iiidsss', $driver['id'], $operator['id'], $assignment['id'], 600, 'Cash', $reference, $notes);
        
        if ($stmt->execute()) {
            echo "✅ Created collected test payment: ₱600 via Cash<br>";
        } else {
            echo "❌ Failed to create collected test payment: " . $stmt->error . "<br>";
        }
        
    } else {
        echo "❌ Need at least one driver and one operator<br>";
    }
} else {
    echo "✅ Boundary payments already exist<br>";
}

// Step 3: Test API with current operator
echo "<h3>Step 3: API Test</h3>";
if (isset($_SESSION['user_id']) && $_SESSION['user_type'] === 'operator') {
    $operator_id = $_SESSION['user_id'];
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
    echo "❌ Not logged in as operator<br>";
}

// Step 4: Test stats API
echo "<h3>Step 4: Stats API Test</h3>";
if (isset($_SESSION['user_id']) && $_SESSION['user_type'] === 'operator') {
    $operator_id = $_SESSION['user_id'];
    
    $statsData = [
        'action' => 'stats',
        'operator_id' => $operator_id
    ];
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'http://localhost/tebzcopy/pay_boundary.php');
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($statsData));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    
    $response = curl_exec($ch);
    curl_close($ch);
    
    if ($response) {
        $data = json_decode($response, true);
        if ($data && isset($data['success']) && $data['success']) {
            echo "✅ Stats API working<br>";
            echo "- Pending payments: " . $data['stats']['pending']['count'] . "<br>";
            echo "- Pending amount: ₱" . number_format($data['stats']['pending']['total'], 2) . "<br>";
            echo "- Collected payments: " . $data['stats']['collected']['count'] . "<br>";
            echo "- Collected amount: ₱" . number_format($data['stats']['collected']['total'], 2) . "<br>";
        } else {
            echo "❌ Stats API failed: " . ($data['message'] ?? 'Unknown error') . "<br>";
        }
    } else {
        echo "❌ No response from stats API<br>";
    }
}

// Step 5: JavaScript debugging
echo "<h3>Step 5: JavaScript Debug</h3>";
echo "<div id='jsDebug'></div>";
echo "<script>
// Test the JavaScript variables
const operator_id = <?= json_encode($_SESSION['user_id'] ?? 'null') ?>;
console.log('Operator ID from JavaScript:', operator_id);

// Test the API call directly
fetch('pay_boundary.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ action: 'list', operator_id })
})
.then(res => res.json())
.then(data => {
    console.log('Direct API response:', data);
    document.getElementById('jsDebug').innerHTML = 
        '<div style=\"background: #d4edda; padding: 10px; margin: 10px 0; border-radius: 5px;\">' +
        '<strong>JavaScript API Test:</strong><br>' +
        'Success: ' + data.success + '<br>' +
        'Boundaries found: ' + (data.boundaries ? data.boundaries.length : 0) + '<br>' +
        'Message: ' + (data.message || 'N/A') +
        '</div>';
})
.catch(error => {
    console.error('JavaScript API error:', error);
    document.getElementById('jsDebug').innerHTML = 
        '<div style=\"background: #f8d7da; padding: 10px; margin: 10px 0; border-radius: 5px;\">' +
        '<strong>JavaScript API Error:</strong><br>' +
        error.message +
        '</div>';
});
</script>";

echo "<h3>🔧 Quick Actions</h3>";
echo "<ul>";
echo "<li><a href='operator_dashboard.php?page=collect_boundaries' target='_blank'>Go to Operator Dashboard</a></li>";
echo "<li><a href='debug_boundary_issue.php' target='_blank'>Run Full Debug</a></li>";
echo "<li><a href='boundary_system_status.php' target='_blank'>Check System Status</a></li>";
echo "</ul>";

echo "<h3>📋 Summary</h3>";
echo "<div style='background: #e7f3ff; padding: 15px; border-radius: 5px;'>";
echo "<strong>What this script does:</strong><br>";
echo "1. Checks user session and login status<br>";
echo "2. Creates test boundary payments if none exist<br>";
echo "3. Tests the API endpoints directly<br>";
echo "4. Tests JavaScript functionality<br>";
echo "5. Provides debugging information<br>";
echo "<br>";
echo "<strong>If you still see 0 payments:</strong><br>";
echo "1. Make sure you're logged in as an operator<br>";
echo "2. Check the browser console for JavaScript errors<br>";
echo "3. Verify the API endpoints are working<br>";
echo "4. Check if there are any database connection issues<br>";
echo "</div>";
?> 