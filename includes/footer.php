</main>
        </div>
    </div>

    <!-- Bootstrap 5 JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Custom JavaScript -->
    <script>
        // Auto-hide alerts after 5 seconds
        setTimeout(function() {
            var alerts = document.querySelectorAll('.alert');
            alerts.forEach(function(alert) {
                var bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
            });
        }, 5000);

        // Confirm delete actions
        function confirmDelete(message = 'Are you sure you want to delete this item?') {
            return confirm(message);
        }

        // File upload validation
        function validateFileUpload(input, maxFiles = 3, maxSize = 5) {
            const files = input.files;
            const allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'application/pdf'];
            const maxSizeBytes = maxSize * 1024 * 1024; // Convert MB to bytes

            if (files.length > maxFiles) {
                alert(`Maximum ${maxFiles} files allowed.`);
                input.value = '';
                return false;
            }

            for (let i = 0; i < files.length; i++) {
                const file = files[i];
                
                if (!allowedTypes.includes(file.type)) {
                    alert('Only PDF and image files (JPEG, PNG, GIF) are allowed.');
                    input.value = '';
                    return false;
                }
                
                if (file.size > maxSizeBytes) {
                    alert(`File "${file.name}" exceeds ${maxSize}MB limit.`);
                    input.value = '';
                    return false;
                }
            }
            
            return true;
        }

        // Dynamic subcategory loading
        function loadSubcategories(categoryId, subcategorySelect) {
            if (!categoryId) {
                subcategorySelect.innerHTML = '<option value="">Select Subcategory</option>';
                return;
            }

            // Show loading state
            subcategorySelect.innerHTML = '<option value="">Loading...</option>';

            // Construct the correct API URL based on current location
            let apiUrl;
            const currentPath = window.location.pathname;
            
            if (currentPath.includes('/requests/')) {
                apiUrl = '../api/subcategories.php';
            } else if (currentPath.includes('/it-request-system/') && !currentPath.includes('/requests/')) {
                // We're in the main directory or another subdirectory
                apiUrl = '/it-request-system/api/subcategories.php';
            } else {
                // Fallback
                apiUrl = 'api/subcategories.php';
            }

            fetch(`${apiUrl}?category_id=${categoryId}`)
                .then(response => {
                    if (!response.ok) {
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }
                    return response.json();
                })
                .then(data => {
                    subcategorySelect.innerHTML = '<option value="">Select Subcategory</option>';
                    
                    if (Array.isArray(data) && data.length > 0) {
                        data.forEach(subcategory => {
                            const option = document.createElement('option');
                            option.value = subcategory.id;
                            option.textContent = subcategory.name;
                            subcategorySelect.appendChild(option);
                        });
                    } else {
                        const option = document.createElement('option');
                        option.value = '';
                        option.textContent = 'No subcategories available';
                        option.disabled = true;
                        subcategorySelect.appendChild(option);
                    }
                })
                .catch(error => {
                    console.error('Error loading subcategories:', error);
                    subcategorySelect.innerHTML = '<option value="">Error loading subcategories</option>';
                    
                    // Show user-friendly error
                    alert('Failed to load subcategories. Please refresh the page and try again.');
                });
        }

        // Initialize tooltips
        document.addEventListener('DOMContentLoaded', function() {
            var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            var tooltipList = tooltipTriggerList.map(function(tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl);
            });
        });
    </script>

    <footer class="footer mt-auto py-3 bg-light">
        <div class="container text-center">
            <span class="text-muted">
                &copy; <?php echo date('Y'); ?> IT Request Management System. All rights reserved.
            </span>
        </div>
    </footer>

</body>
</html>