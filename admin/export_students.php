<?php
session_start();
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/config/database.php';

requireRole('Super Admin');

$page_title = 'Export Students';

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

// Get student counts for preview
$stmt = $pdo->query("
    SELECT d.department_name, c.class_name, COUNT(u.user_id) as student_count
    FROM departments d
    LEFT JOIN classes c ON d.department_id = c.department_id
    LEFT JOIN users u ON c.class_id = u.class_id AND u.role_id = 1
    GROUP BY d.department_id, c.class_id
    ORDER BY d.department_name, c.class_name
");
$student_counts = $stmt->fetchAll();

include dirname(__DIR__) . '/includes/header.php';
?>

<div class="row">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1>Export Students</h1>
            <div>
                <a href="import_students.php" class="btn btn-outline-primary me-2">
                    <i class="fas fa-upload me-1"></i>Import Students
                </a>
                <a href="students.php" class="btn btn-outline-secondary">
                    <i class="fas fa-arrow-left me-1"></i>Back to Students
                </a>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-download me-2"></i>Export Students to CSV</h5>
            </div>
            <div class="card-body">
                <form id="exportForm" class="needs-validation" novalidate>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="department_id" class="form-label">Department</label>
                                <select class="form-select" id="department_id" name="department_id">
                                    <option value="">All Departments</option>
                                    <?php foreach ($departments as $dept): ?>
                                        <option value="<?php echo $dept['department_id']; ?>">
                                            <?php echo htmlspecialchars($dept['department_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="class_id" class="form-label">Class</label>
                                <select class="form-select" id="class_id" name="class_id">
                                    <option value="">All Classes</option>
                                    <?php foreach ($classes as $class): ?>
                                        <option value="<?php echo $class['class_id']; ?>" data-department="<?php echo $class['department_id']; ?>">
                                            <?php echo htmlspecialchars($class['class_name']); ?> 
                                            (<?php echo htmlspecialchars($class['department_name']); ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    
                    
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-download me-1"></i>Export CSV
                    </button>
                </form>
            </div>
        </div>
    </div>
    
</div>

<div class="row mt-4">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-info-circle me-2"></i>Export Information</h5>
            </div>
            <div class="card-body">
                <p><strong>CSV Format:</strong></p>
                <ul class="small">
                    <li>Roll Number</li>
                    <li>First Name</li>
                    <li>Middle Name</li>
                    <li>Last Name</li>
                    <li>Email</li>
                    <li>Phone</li>
                    <li>Gender</li>
                    <li>Address fields</li>
                </ul>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const departmentSelect = document.getElementById('department_id');
    const classSelect = document.getElementById('class_id');
    const form = document.getElementById('exportForm');
    
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
    departmentSelect.addEventListener('change', function() {
        const selectedDept = this.value;
        const classOptions = classSelect.querySelectorAll('option[data-department]');
        
        classSelect.value = '';
        
        classOptions.forEach(option => {
            if (!selectedDept || option.dataset.department === selectedDept) {
                option.style.display = '';
            } else {
                option.style.display = 'none';
            }
        });
        
    });
    
    // Handle form submission (export)
    const submitBtn = form.querySelector('button[type="submit"]');
    
    if (submitBtn) {
        submitBtn.addEventListener('click', function(e) {
            e.preventDefault();
            
            const formData = new FormData(form);
            const originalText = submitBtn.innerHTML;
            
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Exporting...';
            
            // Create a temporary form for file download
            const downloadForm = document.createElement('form');
            downloadForm.method = 'POST';
            downloadForm.action = 'export_handler.php';
            downloadForm.style.display = 'none';
            
            // Add form data to download form (always include addresses)
            for (let [key, value] of formData.entries()) {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = key;
                input.value = value;
                downloadForm.appendChild(input);
            }
            
            // Always include address information
            const addressInput = document.createElement('input');
            addressInput.type = 'hidden';
            addressInput.name = 'include_addresses';
            addressInput.value = '1';
            downloadForm.appendChild(addressInput);
            
            document.body.appendChild(downloadForm);
            downloadForm.submit();
            document.body.removeChild(downloadForm);
            
            // Reset button after a delay
            setTimeout(() => {
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalText;
                showAlert('Export started. File download should begin shortly.', 'success');
            }, 1000);
        });
    }
});
</script>

<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
