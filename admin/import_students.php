<?php
session_start();
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/config/database.php';

requireRole('Super Admin');

$page_title = 'Import Students';

// Get departments and classes for dropdowns
$stmt = $pdo->query("SELECT department_id, department_name FROM departments ORDER BY department_name");
$departments = $stmt->fetchAll();

$stmt = $pdo->query("
    SELECT c.class_id, c.class_name, d.department_name, c.department_id
    FROM classes c 
    JOIN departments d ON c.department_id = d.department_id 
    ORDER BY d.department_name, c.class_name
");
$classes = $stmt->fetchAll();

include dirname(__DIR__) . '/includes/header.php';
?>

<div class="row">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1>Import Students</h1>
            <div>
                <a href="export_students.php" class="btn btn-outline-success me-2">
                    <i class="fas fa-download me-1"></i>Export Students
                </a>
                <a href="students.php" class="btn btn-outline-secondary">
                    <i class="fas fa-arrow-left me-1"></i>Back to Students
                </a>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-8">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-upload me-2"></i>Import Students from CSV</h5>
            </div>
            <div class="card-body">
                <form id="importForm" enctype="multipart/form-data" class="needs-validation" novalidate>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="department_id" class="form-label">Department <span class="text-danger">*</span></label>
                                <select class="form-select" id="department_id" name="department_id" required>
                                    <option value="">Select Department</option>
                                    <?php foreach ($departments as $dept): ?>
                                        <option value="<?php echo $dept['department_id']; ?>">
                                            <?php echo htmlspecialchars($dept['department_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="invalid-feedback">Please select a department.</div>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="class_id" class="form-label">Class <span class="text-danger">*</span></label>
                                <select class="form-select" id="class_id" name="class_id" required>
                                    <option value="">Select Class</option>
                                    <?php foreach ($classes as $class): ?>
                                        <option value="<?php echo $class['class_id']; ?>" data-department="<?php echo $class['department_id']; ?>">
                                            <?php echo htmlspecialchars($class['class_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="invalid-feedback">Please select a class.</div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="csv_file" class="form-label">CSV File <span class="text-danger">*</span></label>
                        <input type="file" class="form-control" id="csv_file" name="csv_file" accept=".csv" required>
                        <div class="form-text">Upload a CSV file with student data. Maximum file size: 5MB</div>
                        <div class="invalid-feedback">Please select a CSV file.</div>
                    </div>
                    
                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="update_existing" name="update_existing">
                            <label class="form-check-label" for="update_existing">
                                Update existing students (based on roll number)
                            </label>
                        </div>
                    </div>
                    
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-upload me-1"></i>Import Students
                    </button>
                </form>
            </div>
        </div>
    </div>
    
    <div class="col-md-4">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-info-circle me-2"></i>CSV Format</h5>
            </div>
            <div class="card-body">
                <p><strong>Required CSV format:</strong></p>
                <div class="bg-light p-3 rounded mb-3">
                    <code>
                        Roll Number,First Name,Middle Name,Last Name,Email,Phone,Gender,Password<br>
                        CS2024001,John,M,Doe,john.doe@example.com,9876543210,M,password123<br>
                        CS2024002,Jane,,Smith,jane.smith@example.com,9876543211,F,password123
                    </code>
                </div>
                
                <p><strong>Field Requirements:</strong></p>
                <ul class="small">
                    <li><strong>Roll Number:</strong> Required, must be unique</li>
                    <li><strong>First Name:</strong> Required</li>
                    <li><strong>Middle Name:</strong> Optional (can be empty)</li>
                    <li><strong>Last Name:</strong> Required</li>
                    <li><strong>Email:</strong> Required, must be valid format</li>
                    <li><strong>Phone:</strong> Optional, 10 digits if provided</li>
                    <li><strong>Gender:</strong> Required (M/F/O)</li>
                    <li><strong>Password:</strong> Required, min 8 characters</li>
                </ul>
                
            </div>
        </div>
        
        <div class="card mt-3">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-download me-2"></i>Sample File</h5>
            </div>
            <div class="card-body">
                <p>Download a sample CSV file to see the correct format:</p>
                <a href="download_sample.php" class="btn btn-outline-primary btn-sm">
                    <i class="fas fa-download me-1"></i>Download Sample CSV
                </a>
            </div>
        </div>
    </div>
</div>

<div id="importResults" class="mt-4" style="display: none;">
    <div class="card">
        <div class="card-header">
            <h5 class="mb-0"><i class="fas fa-chart-bar me-2"></i>Import Results</h5>
        </div>
        <div class="card-body" id="resultsContent">
            <!-- Results will be populated here -->
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const departmentSelect = document.getElementById('department_id');
    const classSelect = document.getElementById('class_id');
    const form = document.getElementById('importForm');
    
    if (!form) return;
    
    // Define showAlert function locally if not available
    function showAlert(message, type = 'info') {
        if (window.showAlert && typeof window.showAlert === 'function') {
            return window.showAlert(message, type);
        }
        
        const alertDiv = document.createElement('div');
        alertDiv.className = `alert alert-${type} alert-dismissible fade show`;
        alertDiv.innerHTML = `
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        `;
        
        const container = document.querySelector('.container');
        if (container) {
            container.insertBefore(alertDiv, container.firstChild);
        }
        
        setTimeout(() => {
            if (alertDiv.parentNode) {
                alertDiv.remove();
            }
        }, 5000);
    }
    
    // Filter classes based on selected department
    function filterClasses() {
        const selectedDept = departmentSelect.value;
        const classOptions = classSelect.querySelectorAll('option[data-department]');
        
        // Clear current class selection when department changes
        if (classSelect.value && classSelect.querySelector(`option[value="${classSelect.value}"]`)?.dataset.department !== selectedDept) {
            classSelect.value = '';
        }
        
        // Disable class select if no department is selected
        if (!selectedDept) {
            classSelect.disabled = true;
            classSelect.value = '';
            // Hide all class options except the default one
            classOptions.forEach(option => {
                option.style.display = 'none';
            });
        } else {
            classSelect.disabled = false;
            // Show/hide class options based on selected department
            classOptions.forEach(option => {
                if (option.dataset.department === selectedDept) {
                    option.style.display = '';
                } else {
                    option.style.display = 'none';
                }
            });
        }
    }
    
    // Initial setup - disable class dropdown since no department is selected by default
    classSelect.disabled = true;
    filterClasses();

    departmentSelect.addEventListener('change', function() {
        filterClasses();
        // Remove validation state when department changes
        classSelect.classList.remove('is-valid', 'is-invalid');
    });
    
    // Handle form submission
    const submitBtn = form.querySelector('button[type="submit"]');
    
    if (submitBtn) {
        submitBtn.addEventListener('click', function(e) {
            e.preventDefault();
            
            if (!form.checkValidity()) {
                form.classList.add('was-validated');
                showAlert('Please fill in all required fields and select a valid CSV file.', 'danger');
                return;
            }
            
            const formData = new FormData(form);
            const originalText = submitBtn.innerHTML;
            
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Importing...';
            
            fetch('import_handler.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.text())
            .then(text => {
                try {
                    const data = JSON.parse(text);
                    
                    if (data.success) {
                        showAlert(data.message, 'success');
                        
                        // Show detailed results
                        if (data.results) {
                            document.getElementById('importResults').style.display = 'block';
                            document.getElementById('resultsContent').innerHTML = data.results;
                        }
                        
                        // Reset form
                        form.reset();
                        form.classList.remove('was-validated');
                    } else {
                        showAlert(data.message, 'danger');
                    }
                } catch (parseError) {
                    showAlert('Server error occurred. Please try again.', 'danger');
                }
                
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalText;
            })
            .catch(error => {
                showAlert('Network error. Please check your connection.', 'danger');
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalText;
            });
        });
    }
});
</script>

<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
