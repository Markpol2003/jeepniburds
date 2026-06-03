<!-- Jeepney Assignment Modal -->
<div class="modal fade" id="assignJeepneyModal" tabindex="-1" aria-labelledby="assignJeepneyModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content card shadow">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title" id="assignJeepneyModalLabel">
                    <i class="bi bi-truck-front me-2"></i>
                    Assign Jeepney to <span id="driverName" class="fw-bold"></span>
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <div class="driver-info mb-4 p-3 bg-light rounded">
                    <div class="row">
                        <div class="col-md-6">
                            <p class="mb-2"><strong>Driver Name:</strong> <span id="driverNameInfo"></span></p>
                            <p class="mb-0"><strong>Email:</strong> <span id="driverEmail"></span></p>
                        </div>
                        <div class="col-md-6">
                            <p class="mb-2"><strong>Status:</strong> <span class="badge bg-warning">Ready for Assignment</span></p>
                            <p class="mb-0"><strong>Member Since:</strong> <span id="memberSince"></span></p>
                        </div>
                    </div>
                </div>

                <form id="assignJeepneyForm" action="process_assign_jeepney.php" method="POST" class="needs-validation" novalidate>
                    <input type="hidden" name="driver_id" id="driverId">
                    <input type="hidden" name="assign_jeepney" value="1">

                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-floating mb-3">
                                <select name="plate_number" id="plateNumber" class="form-select" required>
                                    <option value="">Select Plate Number</option>
                                    <?php while($jeepney = $available_jeepneys->fetch_assoc()): ?>
                                        <option value="<?= $jeepney['plate_number'] ?>" 
                                                data-body="<?= $jeepney['body_number'] ?>" 
                                                data-route="<?= $jeepney['route'] ?>"
                                                data-status="<?= $jeepney['status'] ?>">
                                            <?= $jeepney['plate_number'] ?>
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                                <label>Plate Number <span class="text-danger">*</span></label>
                                <div class="invalid-feedback">Please select a plate number</div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-floating mb-3">
                                <input type="text" name="body_number" id="bodyNumber" class="form-control" placeholder="Body Number" readonly required>
                                <label>Body Number <span class="text-danger">*</span></label>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-floating mb-3">
                                <input type="text" name="route" id="route" class="form-control" placeholder="Assigned Route" readonly required>
                                <label>Assigned Route <span class="text-danger">*</span></label>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-floating mb-3">
                                <input type="date" name="assignment_date" id="assignmentDate" class="form-control" required>
                                <label>Assignment Date <span class="text-danger">*</span></label>
                                <div class="invalid-feedback">Please select an assignment date</div>
                            </div>
                        </div>
                    </div>

                    <div class="form-floating mb-4">
                        <textarea name="notes" id="notes" class="form-control" placeholder="Notes" style="height: 100px"></textarea>
                        <label>Notes (Optional)</label>
                    </div>

                    <div class="d-flex justify-content-end gap-2">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            <i class="bi bi-x-lg me-1"></i> Cancel
                        </button>
                        <button type="submit" class="btn btn-success">
                            <i class="bi bi-truck-front me-1"></i> Assign Jeepney
                        </button>
                    </div>
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

    // Set today's date as default for assignment date
    const today = new Date().toISOString().split('T')[0];
    document.getElementById('assignmentDate').value = today;

    // Function to open assign modal
    window.openAssignModal = function(driverId, driverName, driverEmail, memberSince) {
        // Set the modal content
        document.getElementById('driverName').textContent = driverName;
        document.getElementById('driverNameInfo').textContent = driverName;
        document.getElementById('driverEmail').textContent = driverEmail;
        document.getElementById('memberSince').textContent = memberSince;
        document.getElementById('driverId').value = driverId;
        
        // Reset the form
        const form = document.getElementById('assignJeepneyForm');
        if (form) {
            form.reset();
            form.classList.remove('was-validated');
            // Reset date to today
            document.getElementById('assignmentDate').value = today;
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
        const status = selectedOption.getAttribute('data-status');
        
        document.getElementById('bodyNumber').value = bodyNumber || '';
        document.getElementById('route').value = route || '';

        // Validate the form
        const form = document.getElementById('assignJeepneyForm');
        if (form) {
            form.classList.add('was-validated');
        }
    });

    // Form submission handling
    document.getElementById('assignJeepneyForm')?.addEventListener('submit', function(e) {
        e.preventDefault();
        
        // Validate form
        if (!this.checkValidity()) {
            e.stopPropagation();
            this.classList.add('was-validated');
            return;
        }

        // Get form data
        const formData = new FormData(this);
        
        // Show confirmation dialog
        Swal.fire({
            title: 'Confirm Assignment',
            html: `
                <div class="text-start">
                    <p>Are you sure you want to assign this jeepney to the driver?</p>
                    <div class="mt-3">
                        <strong>Driver:</strong> ${document.getElementById('driverNameInfo').textContent}<br>
                        <strong>Plate Number:</strong> ${formData.get('plate_number')}<br>
                        <strong>Body Number:</strong> ${formData.get('body_number')}<br>
                        <strong>Route:</strong> ${formData.get('route')}<br>
                        <strong>Assignment Date:</strong> ${formData.get('assignment_date')}
                    </div>
                </div>
            `,
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Yes, Assign',
            cancelButtonText: 'Cancel',
            confirmButtonColor: '#198754',
            showLoaderOnConfirm: true,
            preConfirm: () => {
                return fetch('process_assign_jeepney.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (!data.success) {
                        throw new Error(data.message);
                    }
                    return data;
                })
                .catch(error => {
                    Swal.showValidationMessage(`Request failed: ${error.message}`);
                });
            },
            allowOutsideClick: () => !Swal.isLoading()
        }).then((result) => {
            if (result.isConfirmed) {
                Swal.fire({
                    icon: 'success',
                    title: 'Success!',
                    text: result.value.message,
                    showConfirmButton: false,
                    timer: 1500
                }).then(() => {
                    // Reload the page to show updated data
                    location.reload();
                });
            }
        });
    });
});
</script> 