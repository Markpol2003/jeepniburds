-- Update payment_method column name in membership_payments table
ALTER TABLE membership_payments
CHANGE COLUMN method payment_method VARCHAR(50) NOT NULL; 