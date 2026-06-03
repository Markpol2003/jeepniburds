# Boundary Payment System Documentation

## Overview
The boundary payment system allows drivers to pay boundaries to operators, and operators to collect and manage these payments through their dashboard.

## How It Works

### 1. Driver Side (Pay Boundary)
- Drivers can access the "Pay Boundary" section in their dashboard
- They can specify:
  - Amount (default ₱500)
  - Payment method (Cash, GCash, Bank Transfer, PayMaya)
  - Optional notes
- When submitted, a boundary payment record is created with status "Pending"
- A reference number is automatically generated (format: BND-YYYYMMDD-XXXX)

### 2. Operator Side (Collect Boundaries)
- Operators can view all boundary payments in their "Collect Boundaries" section
- The system shows:
  - Statistics cards (pending payments, amounts, collected payments)
  - Detailed table with driver info, jeepney details, route, payment info
  - Action buttons to confirm collection
- Operators can mark payments as "Collected"

## Database Structure

### boundary_payments Table
```sql
CREATE TABLE boundary_payments (
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
);
```

## API Endpoints

### 1. Submit Boundary Payment
**URL:** `pay_boundary.php`
**Method:** POST
**Data:**
```json
{
    "driver_id": 1,
    "operator_id": 2,
    "jeepney_id": 1,
    "amount": 500,
    "payment_method": "GCash",
    "notes": "Optional notes"
}
```

### 2. List Boundary Payments (Operator)
**URL:** `pay_boundary.php`
**Method:** POST
**Data:**
```json
{
    "action": "list",
    "operator_id": 2
}
```

### 3. Get Statistics (Operator)
**URL:** `pay_boundary.php`
**Method:** POST
**Data:**
```json
{
    "action": "stats",
    "operator_id": 2
}
```

### 4. Confirm Collection (Operator)
**URL:** `pay_boundary.php`
**Method:** POST
**Data:**
```json
{
    "action": "confirm",
    "id": 1
}
```

## Features

### Driver Features
- ✅ Pay boundary with amount and payment method
- ✅ Add optional notes
- ✅ View payment receipt with reference number
- ✅ See payment status (Pending/Collected)
- ✅ Form validation and error handling

### Operator Features
- ✅ View all boundary payments in a table
- ✅ See payment statistics (pending/collected amounts)
- ✅ Confirm payment collection
- ✅ View detailed driver and jeepney information
- ✅ Auto-refresh every 30 seconds
- ✅ Search and filter capabilities

### System Features
- ✅ Automatic reference number generation
- ✅ Payment status tracking
- ✅ Detailed payment history
- ✅ Real-time updates
- ✅ Error handling and validation
- ✅ Responsive design

## Testing

### Test File
Use `test_boundary_system.php` to verify the system is working correctly:
- Checks database table existence
- Tests API endpoints
- Shows sample data
- Creates test payments if needed

### Manual Testing Steps
1. **Driver Side:**
   - Login as a driver
   - Go to "Pay Boundary" section
   - Submit a boundary payment
   - Verify receipt is shown

2. **Operator Side:**
   - Login as an operator
   - Go to "Collect Boundaries" section
   - Verify the driver's payment appears
   - Click "Confirm" to mark as collected
   - Verify statistics update

## File Structure
```
├── pay_boundary.php              # Main API endpoint
├── driver_dashboard.php          # Driver boundary payment interface
├── operator_dashboard.php        # Operator collection interface
├── test_boundary_system.php     # Testing utility
└── BOUNDARY_PAYMENT_SYSTEM.md   # This documentation
```

## Troubleshooting

### Common Issues
1. **No payments showing for operator:**
   - Check if driver has made payments
   - Verify operator_id is correct
   - Check database connection

2. **Payment not submitting:**
   - Check form validation
   - Verify all required fields
   - Check browser console for errors

3. **Statistics not updating:**
   - Refresh the page
   - Check API responses
   - Verify database queries

### Debug Tools
- Use browser developer tools to check network requests
- Check browser console for JavaScript errors
- Use `test_boundary_system.php` for system verification
- Check server error logs for PHP errors

## Future Enhancements
- [ ] Email notifications for new payments
- [ ] Payment receipt generation (PDF)
- [ ] Payment history export
- [ ] Multiple payment methods per payment
- [ ] Payment scheduling
- [ ] Advanced reporting and analytics 