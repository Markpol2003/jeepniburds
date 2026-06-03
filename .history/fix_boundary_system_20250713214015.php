<?php
require_once 'db_config.php';

echo "<h2>Boundary Payment System Fix</h2>";

// Step 1: Ensure boundary_payments table exists with correct structure
echo "<h3>Step 1: Creating/Updating boundary_payments table</h3>";

$createTable = "CREATE TABLE IF NOT EXISTS boundary_payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    driver_id INT NOT NULL,
    operator_id INT NOT NULL,
    jeepney_id INT NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    payment_method VARCHAR(50) NOT NULL,
    paid_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    status VARCHAR(20) DEFAULT 'Pending',
    reference_number VARCHAR(100),
    notes TEXT,
    INDEX idx_operator_id (operator_id),
    INDEX idx_driver_id (driver_id),
    INDEX idx_status (status)
)";

if ($conn->query($createTable)) {
    echo "✅ boundary_payments table created/updated successfully<br>";
} else {
    echo "❌ Error creating table: " . $conn->error . "<br>";
}

// Step 2: Add missing columns if they don't exist
echo "<h3>Step 2: Adding missing columns</h3>";

$columns = ['reference_number', 'notes'];
foreach ($columns as $column) {
    $checkColumn = $conn->query("SHOW COLUMNS FROM boundary_payments LIKE '$column'");
    if ($checkColumn->num_rows == 0) {
        $addColumn = "ALTER TABLE boundary_payments ADD COLUMN $column " . 
                    ($column == 'reference_number' ? 'VARCHAR(100)' : 'TEXT') . 
                    " AFTER status";
        if ($conn->query($addColumn)) {
            echo "✅ Added $column column<br>";
        } else {
            echo "❌ Error adding $column column: " . $conn->error . "<br>";
        }
    } else {
        echo "✅ $column column already exists<br>";
    }
}

// Step 3: Check and create test data
echo "<h3>Step 3: Creating test data</h3>";

// Get a driver and operator
$driver = $conn->query("SELECT id, firstName, lastName FROM users WHERE userType = 'driver' LIMIT 1")->fetch_assoc();
$operator = $conn->query("SELECT id, firstName, lastName FROM users WHERE userType = 'operator' LIMIT 1")->fetch_assoc();

if ($driver && $operator) {
    echo "Found driver: {$driver['firstName']} {$driver['lastName']} (ID: {$driver['id']})<br>";
    echo "Found operator: {$operator['firstName']} {$operator['lastName']} (ID: {$operator['id']})<br>";
    
    // Check if driver has assignment
    $assignment = $conn->query("SELECT * FROM jeepney_assignments WHERE driver_id = {$driver['id']} AND status = 'Active'")->fetch_assoc();
    
    if (!$assignment) {
        echo "⚠️ Driver has no active assignment. Creating one...<br>";
        
        // Create a test assignment
        $createAssignment = "INSERT INTO jeepney_assignments (driver_id, operator_id, plate_number, body_number, route, status) 
                           VALUES ({$driver['id']}, {$operator['id']}, 'TEST-001', 'BODY-001', 'Test Route', 'Active')";
        
        if ($conn->query($createAssignment)) {
            echo "✅ Created test assignment<br>";
            $assignment = $conn->query("SELECT * FROM jeepney_assignments WHERE driver_id = {$driver['id']} AND status = 'Active'")->fetch_assoc();
        } else {
            echo "❌ Error creating assignment: " . $conn->error . "<br>";
        }
    } else {
        echo "✅ Driver has active assignment<br>";
    }
    
    // Create a test boundary payment
    $testPayment = "INSERT INTO boundary_payments (driver_id, operator_id, jeepney_id, amount, payment_method, status, reference_number) 
                    VALUES ({$driver['id']}, {$operator['id']}, 1, 500, 'GCash', 'Pending', 'TEST-" . date('YmdHis') . "')";
    
    if ($conn->query($testPayment)) {
        echo "✅ Created test boundary payment<br>";
    } else {
        echo "❌ Error creating test payment: " . $conn->error . "<br>";
    }
} else {
    echo "❌ No driver or operator found<br>";
}

// Step 4: Test the boundary listing
echo "<h3>Step 4: Testing boundary listing</h3>";

if ($operator) {
    $testQuery = "SELECT COUNT(*) as count FROM boundary_payments WHERE operator_id = {$operator['id']}";
    $result = $conn->query($testQuery);
    $count = $result->fetch_assoc()['count'];
    
    echo "Found $count boundary payments for operator {$operator['id']}<br>";
    
    if ($count > 0) {
        $payments = $conn->query("SELECT * FROM boundary_payments WHERE operator_id = {$operator['id']} ORDER BY paid_at DESC LIMIT 3");
        while ($payment = $payments->fetch_assoc()) {
            echo "- Payment ID: {$payment['id']}, Amount: ₱{$payment['amount']}, Status: {$payment['status']}<br>";
        }
    }
}

// Step 5: Verify operator dashboard functionality
echo "<h3>Step 5: Testing operator dashboard integration</h3>";

// Test the API endpoint
$testData = [
    'action' => 'list',
    'operator_id' => $operator['id'] ?? 1
];

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'http://localhost/tebzcopy/pay_boundary.php');
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($testData));
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode == 200) {
    $data = json_decode($response, true);
    if ($data && isset($data['success'])) {
        echo "✅ API endpoint working correctly<br>";
        echo "Response: " . json_encode($data) . "<br>";
    } else {
        echo "❌ API returned invalid response<br>";
    }
} else {
    echo "❌ API returned HTTP code: $httpCode<br>";
}

echo "<h3>Fix Complete</h3>";
echo "<p><a href='test_boundary_simple.php'>Run Test</a></p>";
echo "<p><a href='operator_dashboard.php?page=collect_boundaries'>Go to Operator Dashboard</a></p>";
echo "<p><a href='driver_dashboard.php?page=pay_boundary'>Go to Driver Dashboard</a></p>";
?> 