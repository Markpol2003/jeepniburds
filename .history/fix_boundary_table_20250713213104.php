<?php
require_once 'db_config.php';

echo "<h2>Fixing Boundary Payments Table Structure</h2>";

try {
    // Check if table exists
    $tableExists = $conn->query("SHOW TABLES LIKE 'boundary_payments'");
    if ($tableExists->num_rows == 0) {
        echo "❌ boundary_payments table does not exist. Creating it...<br>";
        
        $createTable = "CREATE TABLE boundary_payments (
            id INT AUTO_INCREMENT PRIMARY KEY,
            driver_id INT NOT NULL,
            operator_id INT NOT NULL,
            jeepney_id INT NOT NULL,
            amount DECIMAL(10,2) NOT NULL,
            payment_method VARCHAR(50) NOT NULL,
            paid_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            status VARCHAR(20) DEFAULT 'Pending',
            reference_number VARCHAR(100),
            notes TEXT
        )";
        
        if ($conn->query($createTable)) {
            echo "✅ boundary_payments table created successfully<br>";
        } else {
            echo "❌ Failed to create table: " . $conn->error . "<br>";
        }
    } else {
        echo "✅ boundary_payments table exists<br>";
    }
    
    // Check and add reference_number column
    $checkRefColumn = $conn->query("SHOW COLUMNS FROM boundary_payments LIKE 'reference_number'");
    if ($checkRefColumn->num_rows == 0) {
        echo "Adding reference_number column...<br>";
        if ($conn->query("ALTER TABLE boundary_payments ADD COLUMN reference_number VARCHAR(100) AFTER status")) {
            echo "✅ reference_number column added successfully<br>";
        } else {
            echo "❌ Failed to add reference_number column: " . $conn->error . "<br>";
        }
    } else {
        echo "✅ reference_number column already exists<br>";
    }
    
    // Check and add notes column
    $checkNotesColumn = $conn->query("SHOW COLUMNS FROM boundary_payments LIKE 'notes'");
    if ($checkNotesColumn->num_rows == 0) {
        echo "Adding notes column...<br>";
        if ($conn->query("ALTER TABLE boundary_payments ADD COLUMN notes TEXT AFTER reference_number")) {
            echo "✅ notes column added successfully<br>";
        } else {
            echo "❌ Failed to add notes column: " . $conn->error . "<br>";
        }
    } else {
        echo "✅ notes column already exists<br>";
    }
    
    // Show current table structure
    echo "<h3>Current Table Structure:</h3>";
    $columns = $conn->query("SHOW COLUMNS FROM boundary_payments");
    echo "<table border='1' style='border-collapse: collapse;'>";
    echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
    while ($column = $columns->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . $column['Field'] . "</td>";
        echo "<td>" . $column['Type'] . "</td>";
        echo "<td>" . $column['Null'] . "</td>";
        echo "<td>" . $column['Key'] . "</td>";
        echo "<td>" . $column['Default'] . "</td>";
        echo "<td>" . $column['Extra'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    // Test inserting a sample record
    echo "<h3>Testing Insert:</h3>";
    
    // Get a driver and operator for testing
    $driverResult = $conn->query("SELECT id FROM users WHERE userType = 'driver' LIMIT 1");
    $operatorResult = $conn->query("SELECT id FROM users WHERE userType = 'operator' LIMIT 1");
    
    if ($driverResult->num_rows > 0 && $operatorResult->num_rows > 0) {
        $driver = $driverResult->fetch_assoc();
        $operator = $operatorResult->fetch_assoc();
        
        $testStmt = $conn->prepare("INSERT INTO boundary_payments (driver_id, operator_id, jeepney_id, amount, payment_method, status, reference_number, notes) VALUES (?, ?, ?, ?, ?, 'Pending', ?, ?)");
        $reference = 'TEST-' . date('Ymd') . '-001';
        $notes = 'Test payment from migration script';
        $testStmt->bind_param('iiidsss', $driver['id'], $operator['id'], 1, 500, 'Cash', $reference, $notes);
        
        if ($testStmt->execute()) {
            echo "✅ Test record inserted successfully<br>";
            echo "Reference Number: " . $reference . "<br>";
        } else {
            echo "❌ Failed to insert test record: " . $testStmt->error . "<br>";
        }
    } else {
        echo "❌ No drivers or operators found for testing<br>";
    }
    
    echo "<br><strong>Table structure fix completed!</strong><br>";
    echo "You can now try the boundary payment again.";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "<br>";
}
?> 