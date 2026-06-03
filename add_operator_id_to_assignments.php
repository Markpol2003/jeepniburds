<?php
require_once 'db_config.php';

echo "Adding operator_id column to jeepney_assignments table...\n";

// Check if operator_id column exists
$checkColumnQuery = "SHOW COLUMNS FROM jeepney_assignments LIKE 'operator_id'";
$result = $conn->query($checkColumnQuery);

if ($result->num_rows === 0) {
    // Add operator_id column
    $addColumnQuery = "ALTER TABLE jeepney_assignments ADD COLUMN operator_id INT NOT NULL AFTER driver_id";
    if ($conn->query($addColumnQuery)) {
        echo "✅ operator_id column added successfully\n";
        
        // Add foreign key constraint
        $addForeignKeyQuery = "ALTER TABLE jeepney_assignments ADD CONSTRAINT fk_operator_id FOREIGN KEY (operator_id) REFERENCES users(id) ON DELETE CASCADE";
        if ($conn->query($addForeignKeyQuery)) {
            echo "✅ Foreign key constraint added successfully\n";
        } else {
            echo "⚠️  Foreign key constraint already exists or failed to add\n";
        }
    } else {
        echo "❌ Error adding operator_id column: " . $conn->error . "\n";
    }
} else {
    echo "✅ operator_id column already exists\n";
}

// Update existing records with a default operator (first operator found)
echo "Updating existing jeepney_assignments records...\n";

$getOperatorQuery = "SELECT id FROM users WHERE userType = 'operator' LIMIT 1";
$operatorResult = $conn->query($getOperatorQuery);

if ($operatorResult && $operatorResult->num_rows > 0) {
    $operator = $operatorResult->fetch_assoc();
    $operatorId = $operator['id'];
    
    $updateQuery = "UPDATE jeepney_assignments SET operator_id = ? WHERE operator_id = 0 OR operator_id IS NULL";
    $updateStmt = $conn->prepare($updateQuery);
    $updateStmt->bind_param("i", $operatorId);
    
    if ($updateStmt->execute()) {
        $affectedRows = $updateStmt->affected_rows;
        echo "✅ Updated $affectedRows existing jeepney_assignments records with operator_id = $operatorId\n";
    } else {
        echo "❌ Error updating existing records: " . $updateStmt->error . "\n";
    }
} else {
    echo "⚠️  No operator found in users table. Please create an operator account first.\n";
}

echo "\nMigration completed!\n";
?> 