<?php
// Payment Page Content
?>
<div class="card shadow-sm">
    <div class="card-header bg-success text-white">
        <h5 class="mb-0">💰 Pay Membership Fee</h5>
    </div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-6">
                <div class="alert alert-info">
                    <h6 class="alert-heading"><i class="bi bi-info-circle-fill"></i> Payment Instructions</h6>
                    <hr>
                    <p class="mb-1"><strong>Amount:</strong> ₱1,000.00</p>
                    <p class="mb-1"><strong>Payment Methods:</strong></p>
                    <ul class="mb-0">
                        <li>GCash: 09123456789</li>
                        <li>Bank Transfer: BDO 1234567890</li>
                        <li>Cash Payment at Office</li>
                    </ul>
                </div>
            </div>
            <div class="col-md-6">
                <form id="paymentForm" class="needs-validation" novalidate>
                    <div class="mb-3">
                        <label for="amount" class="form-label">Amount</label>
                        <div class="input-group">
                            <span class="input-group-text">₱</span>
                            <input type="number" class="form-control" id="amount" name="amount" value="1000" readonly>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="payment_method" class="form-label">Payment Method</label>
                        <select class="form-select" id="payment_method" name="payment_method" required>
                            <option value="">Choose payment method</option>
                            <option value="gcash">GCash</option>
                            <option value="bank">Bank Transfer</option>
                            <option value="cash">Cash Payment</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="reference_number" class="form-label">Reference Number</label>
                        <input type="text" class="form-control" id="reference_number" name="reference_number" required>
                        <div class="form-text">Enter your GCash/Bank reference number or receipt number for cash payment</div>
                    </div>
                    <button type="submit" class="btn btn-success w-100">
                        <i class="bi bi-credit-card"></i> Submit Payment
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
document.getElementById('paymentForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    
    // Show loading state
    Swal.fire({
        title: 'Processing Payment...',
        text: 'Please wait while we process your payment.',
        allowOutsideClick: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });

    fetch('process_payment.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            Swal.fire({
                icon: 'success',
                title: 'Payment Submitted!',
                text: 'Your payment is being processed. We will notify you once confirmed.',
                confirmButtonColor: '#198754'
            }).then(() => {
                location.reload();
            });
        } else {
            throw new Error(data.message || 'Failed to process payment');
        }
    })
    .catch(error => {
        Swal.fire({
            icon: 'error',
            title: 'Payment Failed',
            text: error.message || 'Please try again later.',
            confirmButtonColor: '#dc3545'
        });
    });
});
</script> 