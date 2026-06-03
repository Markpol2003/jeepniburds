-- Drop existing table if it exists
DROP TABLE IF EXISTS payment_details;

-- Create payment_details table
CREATE TABLE payment_details (
    id INT PRIMARY KEY AUTO_INCREMENT,
    receipt_number VARCHAR(50) NOT NULL UNIQUE,
    reference_number VARCHAR(100) NOT NULL,
    account_name VARCHAR(255) NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (receipt_number) REFERENCES membership_payments(receipt_number)
); 