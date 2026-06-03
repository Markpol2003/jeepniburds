<!-- Jeepney Assignment Modal -->
<div class="modal fade" id="assignJeepneyModal" tabindex="-1" aria-labelledby="assignJeepneyModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content card shadow-sm">
            <div class="modal-header bg-success text-white text-center">
                <h5 class="modal-title w-100" id="assignJeepneyModalLabel">Assign Jeepney to <span id="driverName"></span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="mb-4"><strong>Email:</strong> <span id="driverEmail"></span></p>

                <form id="assignJeepneyForm" action="process_assign_jeepney.php" method="POST" class="mx-auto" style="max-width: 500px;">
                    <input type="hidden" name="driver_id" id="driverId">
                    <input type="hidden" name="assign_jeepney" value="1">

                    <div class="form-floating mb-3">
                        <select name="plate_number" id="plateNumber" class="form-select" required>
                            <option value="">Select Plate Number</option>
                            <?php while($jeepney = $available_jeepneys->fetch_assoc()): ?>
                                <option value="<?= $jeepney['plate_number'] ?>" data-body="<?= $jeepney['body_number'] ?>" data-route="<?= $jeepney['route'] ?>">
                                    <?= $jeepney['plate_number'] ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                        <label>Plate Number</label>
                    </div>

                    <div class="form-floating mb-3">
                        <input type="text" name="body_number" id="bodyNumber" class="form-control" placeholder="Body Number" readonly required>
                        <label>Body Number</label>
                    </div>

                    <div class="form-floating mb-3">
                        <input type="text" name="route" id="route" class="form-control" placeholder="Assigned Route" readonly required>
                        <label>Assigned Route</label>
                    </div>

                    <div class="form-floating mb-3">
                        <textarea name="notes" id="notes" class="form-control" placeholder="Notes" style="height: 100px"></textarea>
                        <label>Notes (Optional)</label>
                    </div>

                    <button type="submit" class="btn btn-success w-100">
                        <i class="bi bi-truck-front me-1"></i> Assign Jeepney
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
// Initialize Bootstrap components
document.addEventListener('DOMContentLoaded', function() {
    // Initialize all modals
    var modals = document.querySelectorAll('.modal');
    modals.forEach(function(modal) {
        new bootstrap.Modal(modal);
    });

    // Function to open assign modal
    window.openAssignModal = function(driverId, driverName, driverEmail) {
        // Set the modal content
        document.getElementById('driverName').textContent = driverName;
        document.getElementById('driverEmail').textContent = driverEmail;
        document.getElementById('driverId').value = driverId;
        
        // Reset the form
        const form = document.getElementById('assignJeepneyForm');
        if (form) {
            form.reset();
            form.classList.remove('was-validated');
        }
        
        // Show the modal
        const modalElement = document.getElementById('assignJeepneyModal');
        const modal = new bootstrap.Modal(modalElement);
        modal.show();
    };

    // Add event listener for plate number changes
    document.getElementById('plateNumber')?.addEventListener('change', function() {
        const selectedOption = this.options[this.selectedIndex];
        const bodyNumber = selectedOption.getAttribute('data-body');
        const route = selectedOption.getAttribute('data-route');
        
        document.getElementById('bodyNumber').value = bodyNumber || '';
        document.getElementById('route').value = route || '';
    });

    // Form submission handling
    document.getElementById('assignJeepneyForm')?.addEventListener('submit', function(e) {
        e.preventDefault();
        
        Swal.fire({
            title: 'Confirm Assignment',
            text: "Are you sure you want to assign this jeepney to the driver?",
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Yes, Assign',
            cancelButtonText: 'Cancel',
            confirmButtonColor: '#198754'
        }).then((result) => {
            if (result.isConfirmed) {
                // Get form data
                const formData = new FormData(this);
                
                // Send AJAX request
                fetch('process_assign_jeepney.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        Swal.fire({
                            icon: 'success',
                            title: 'Success!',
                            text: data.message,
                            showConfirmButton: false,
                            timer: 1500
                        }).then(() => {
                            // Reload the page to show updated data
                            location.reload();
                        });
                    } else {
                        throw new Error(data.message);
                    }
                })
                .catch(error => {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error!',
                        text: error.message || 'Failed to assign jeepney. Please try again.'
                    });
                });
            }
        });
    });
});
</script> 