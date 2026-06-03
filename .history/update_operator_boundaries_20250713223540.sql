-- SQL Script to Update Boundary Payments Operator ID
-- Replace '34' with your actual operator user ID

-- First, check what operator IDs currently exist in boundary_payments
SELECT DISTINCT operator_id, COUNT(*) as count 
FROM boundary_payments 
GROUP BY operator_id;

-- Update all boundary payments to operator ID 34 (replace with your actual operator ID)
UPDATE boundary_payments 
SET operator_id = 34 
WHERE operator_id != 34;

-- Verify the update
SELECT COUNT(*) as total_payments 
FROM boundary_payments 
WHERE operator_id = 34;

-- Show sample of updated payments
SELECT bp.id, u.firstName, u.lastName, bp.amount, bp.payment_method, bp.status, bp.paid_at
FROM boundary_payments bp 
JOIN users u ON bp.driver_id = u.id 
WHERE bp.operator_id = 34 
ORDER BY bp.paid_at DESC 
LIMIT 10; 