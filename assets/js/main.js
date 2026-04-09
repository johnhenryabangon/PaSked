// PaSked JavaScript Functions
// Interactive features for the booking system

document.addEventListener('DOMContentLoaded', function() {
    // Form validation for booking form
    const bookingForm = document.getElementById('bookingForm');
    if (bookingForm) {
        bookingForm.addEventListener('submit', validateBookingForm);
    }

    // Date picker validation - prevent past dates
    const dateInputs = document.querySelectorAll('input[type="date"]');
    dateInputs.forEach(input => {
        const today = new Date().toISOString().split('T')[0];
        input.min = today;
    });

    // Time picker validation
    validateTimeInputs();

    // Auto-hide alerts after 5 seconds - DISABLED
    // autoHideAlerts();

    // Confirmation dialogs for admin actions
    setupConfirmationDialogs();
});

// Booking form validation
function validateBookingForm(event) {
    const form = event.target;
    const formData = new FormData(form);

    // Get form values
    const name = formData.get('name').trim();
    const contact = formData.get('contact_number').trim();
    const email = formData.get('email').trim();
    const date = formData.get('schedule_date');
    const startTime = formData.get('start_time');
    const endTime = formData.get('end_time');

    let errors = [];

    // Name validation
    if (name.length < 2) {
        errors.push('Name must be at least 2 characters long');
    }

    if (!/^[a-zA-Z\s]+$/.test(name)) {
        errors.push('Name should only contain letters and spaces');
    }

    // Contact number validation (Philippine format)
    if (!/^(09|\+639)\d{9}$/.test(contact.replace(/\s/g, ''))) {
        errors.push('Please enter a valid Philippine mobile number (09XXXXXXXXX)');
    }

    // Email validation
    const emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    if (!emailPattern.test(email)) {
        errors.push('Please enter a valid email address');
    }

    // Date validation
    const selectedDate = new Date(date);
    const today = new Date();
    today.setHours(0, 0, 0, 0);

    if (selectedDate < today) {
        errors.push('Please select a future date');
    }

    // Time validation
    if (startTime >= endTime) {
        errors.push('End time must be after start time');
    }

    // Check minimum booking duration (1 hour)
    const start = new Date(`2000-01-01 ${startTime}`);
    const end = new Date(`2000-01-01 ${endTime}`);
    const diff = (end - start) / (1000 * 60 * 60); // hours

    if (diff < 1) {
        errors.push('Minimum booking duration is 1 hour');
    }

    if (diff > 8) {
        errors.push('Maximum booking duration is 8 hours');
    }

    // Display errors
    if (errors.length > 0) {
        event.preventDefault();
        showErrorMessages(errors);
        return false;
    }

    return true;
}

// Time input validation
function validateTimeInputs() {
    const startTimeInput = document.getElementById('start_time');
    const endTimeInput = document.getElementById('end_time');

    if (startTimeInput && endTimeInput) {
        startTimeInput.addEventListener('change', function() {
            if (this.value && endTimeInput.value) {
                if (this.value >= endTimeInput.value) {
                    endTimeInput.value = '';
                    showAlert('End time must be after start time', 'error');
                }
            }
        });

        endTimeInput.addEventListener('change', function() {
            if (this.value && startTimeInput.value) {
                if (this.value <= startTimeInput.value) {
                    this.value = '';
                    showAlert('End time must be after start time', 'error');
                }
            }
        });
    }
}

// Show error messages
function showErrorMessages(errors) {
    // Remove existing error display
    const existingErrors = document.querySelector('.error-messages');
    if (existingErrors) {
        existingErrors.remove();
    }

    // Create error display
    const errorDiv = document.createElement('div');
    errorDiv.className = 'alert alert-error error-messages';
    errorDiv.innerHTML = '<strong>Please fix the following errors:</strong><ul>' + 
        errors.map(error => `<li>${error}</li>`).join('') + '</ul>';

    // Insert at top of form
    const form = document.getElementById('bookingForm');
    form.insertBefore(errorDiv, form.firstChild);

    // Scroll to error
    errorDiv.scrollIntoView({ behavior: 'smooth', block: 'center' });
}

// Show alert message
function showAlert(message, type = 'success') {
    const alertDiv = document.createElement('div');
    alertDiv.className = `alert alert-${type}`;
    alertDiv.textContent = message;

    // Insert at top of container
    const container = document.querySelector('.container') || document.body;
    container.insertBefore(alertDiv, container.firstChild);

    // Don't auto hide - let it stay visible
}

// Confirmation dialogs for admin actions
function setupConfirmationDialogs() {
    // Confirm booking actions
    const confirmButtons = document.querySelectorAll('[data-confirm]');
    confirmButtons.forEach(button => {
        button.addEventListener('click', function(event) {
            const message = this.getAttribute('data-confirm');
            if (!confirm(message)) {
                event.preventDefault();
                return false;
            }
        });
    });

    // Decline booking confirmation
    const declineButtons = document.querySelectorAll('.btn-danger');
    declineButtons.forEach(button => {
        if (button.textContent.includes('Decline')) {
            button.addEventListener('click', function(event) {
                if (!confirm('Are you sure you want to decline this booking?')) {
                    event.preventDefault();
                    return false;
                }
            });
        }
    });
}

// MISSING FUNCTIONS - ADD THESE FOR ADMIN DASHBOARD SEARCH/FILTER

// Search functionality for admin dashboard
function searchBookings() {
    const searchInput = document.getElementById('searchInput');
    if (!searchInput) return; // Exit if not on admin dashboard

    const searchTerm = searchInput.value.toLowerCase();
    const tableRows = document.querySelectorAll('tbody tr');

    tableRows.forEach(row => {
        // Skip the "no bookings" message row
        if (row.querySelector('td[colspan]')) {
            return;
        }

        const text = row.textContent.toLowerCase();
        if (text.includes(searchTerm)) {
            row.style.display = '';
        } else {
            row.style.display = 'none';
        }
    });
}

// Filter bookings by status
function filterByStatus(status) {
    const tableRows = document.querySelectorAll('tbody tr');

    tableRows.forEach(row => {
        // Skip the "no bookings" message row
        if (row.querySelector('td[colspan]')) {
            return;
        }

        if (status === 'all') {
            row.style.display = '';
        } else {
            const statusCell = row.querySelector('.status-badge');
            if (statusCell && statusCell.textContent.toLowerCase().includes(status)) {
                row.style.display = '';
            } else {
                row.style.display = 'none';
            }
        }
    });
}

// Format phone number as user types
function formatPhoneNumber(input) {
    let value = input.value.replace(/\D/g, ''); // Remove non-digits

    if (value.startsWith('63')) {
        value = '+' + value;
    } else if (value.startsWith('9') && value.length === 10) {
        value = '0' + value;
    }

    input.value = value;
}

// Real-time form feedback
function setupRealTimeValidation() {
    const nameInput = document.querySelector('input[name="name"]');
    const emailInput = document.querySelector('input[name="email"]');
    const contactInput = document.querySelector('input[name="contact_number"]');

    if (nameInput) {
        nameInput.addEventListener('input', function() {
            if (this.value.length < 2) {
                this.style.borderColor = '#f85149';
            } else {
                this.style.borderColor = '#3fb950';
            }
        });
    }

    if (emailInput) {
        emailInput.addEventListener('input', function() {
            const emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (emailPattern.test(this.value)) {
                this.style.borderColor = '#3fb950';
            } else {
                this.style.borderColor = '#f85149';
            }
        });
    }

    if (contactInput) {
        contactInput.addEventListener('input', function() {
            formatPhoneNumber(this);
            const phonePattern = /^(09|\+639)\d{9}$/;
            if (phonePattern.test(this.value.replace(/\s/g, ''))) {
                this.style.borderColor = '#3fb950';
            } else {
                this.style.borderColor = '#f85149';
            }
        });
    }
}

// Initialize real-time validation on form pages
if (document.querySelector('form')) {
    setupRealTimeValidation();
}


  document.getElementById('openEditModal').addEventListener('click', function() {
    document.getElementById('editModal').style.display = 'block';
  });

  document.getElementById('closeEditModal').addEventListener('click', function() {
    document.getElementById('editModal').style.display = 'none';
  });

  window.addEventListener('click', function(event) {
    if (event.target === document.getElementById('editModal')) {
      document.getElementById('editModal').style.display = 'none';
    }
  });




