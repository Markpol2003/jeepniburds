<?php
require_once 'db_config.php';

// Create jeepney_assignments table
$sql = "CREATE TABLE IF NOT EXISTS jeepney_assignments (
    id INT PRIMARY KEY AUTO_INCREMENT,
    driver_id INT NOT NULL,
    plate_number VARCHAR(20) NOT NULL,
    body_number VARCHAR(20) NOT NULL,
    route VARCHAR(100) NOT NULL,
    notes TEXT,
    assigned_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    status ENUM('Active', 'Inactive') DEFAULT 'Active',
    FOREIGN KEY (driver_id) REFERENCES users(id) ON DELETE CASCADE
)";

if ($conn->query($sql) === TRUE) {
    echo "Table jeepney_assignments created successfully";
} else {
    echo "Error creating table: " . $conn->error;
}

$conn->close();
?> 