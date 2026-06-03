ALTER TABLE membership_payments
ADD COLUMN reference_number VARCHAR(50) AFTER method,
ADD COLUMN receipt_number VARCHAR(20) AFTER reference_number; 