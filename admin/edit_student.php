<?php
session_start();
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/config/database.php';

requireRole('Super Admin');

$page_title = 'Edit Student';

// Get student ID
$student_id = (int)($_GET['id'] ?? 0);

if (!$student_id) {
    header('Location: students.php');
    exit();
}

// Get student data
$stmt = $pdo->prepare("
    SELECT u.*, c.class_name, d.department_name
    FROM users u
    LEFT JOIN classes c ON u.class_id = c.class_id
    LEFT JOIN departments d ON u.department_id = d.department_id
    WHERE u.user_id = ? AND u.role_id = 1
");
$stmt->execute([$student_id]);
$student = $stmt->fetch();

if (!$student) {
    header('Location: students.php');
    exit();
}

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
            <h1>Edit Student</h1>
            <div>
                <a href="student_details.php?id=<?php echo $student_id; ?>" class="btn btn-outline-info me-2">
                    <i class="fas fa-eye me-1"></i>View Details
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
                <h5 class="mb-0">
                    <i class="fas fa-user-edit me-2"></i>Edit Student Information
                    <span class="badge bg-info ms-2"><?php echo htmlspecialchars($student['roll_number']); ?></span>
                </h5>
            </div>
            <div class="card-body">
                <form id="editStudentForm" class="needs-validation" novalidate>
                    <input type="hidden" name="action" value="update_user">
                    <input type="hidden" name="user_id" value="<?php echo $student_id; ?>">
                    <input type="hidden" name="role_id" value="1"> <!-- Student role -->
                    
                    <div class="row">
                        <!-- Personal Information -->
                        <div class="col-md-6">
                            <h6 class="text-primary mb-3">Personal Information</h6>
                            
                            <div class="mb-3">
                                <label for="fname" class="form-label">First Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="fname" name="fname" 
                                       value="<?php echo htmlspecialchars($student['fname']); ?>" required>
                                <div class="invalid-feedback">Please provide a first name.</div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="mname" class="form-label">Middle Name</label>
                                <input type="text" class="form-control" id="mname" name="mname" 
                                       value="<?php echo htmlspecialchars($student['mname'] ?? ''); ?>">
                            </div>
                            
                            <div class="mb-3">
                                <label for="lname" class="form-label">Last Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="lname" name="lname" 
                                       value="<?php echo htmlspecialchars($student['lname']); ?>" required>
                                <div class="invalid-feedback">Please provide a last name.</div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="dob" class="form-label">Date of Birth</label>
                                        <input type="date" class="form-control" id="dob" name="dob" 
                                               value="<?php echo $student['dob']; ?>">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="gender" class="form-label">Gender</label>
                                        <select class="form-select" id="gender" name="gender">
                                            <option value="">Select Gender</option>
                                            <option value="M" <?php echo $student['gender'] == 'M' ? 'selected' : ''; ?>>Male</option>
                                            <option value="F" <?php echo $student['gender'] == 'F' ? 'selected' : ''; ?>>Female</option>
                                            <option value="O" <?php echo $student['gender'] == 'O' ? 'selected' : ''; ?>>Other</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="phone" class="form-label">Phone Number</label>
                                <input type="tel" class="form-control" id="phone" name="phone" 
                                       value="<?php echo htmlspecialchars($student['phone'] ?? ''); ?>" pattern="[0-9]{10}">
                                <div class="form-text">Enter 10-digit phone number</div>
                            </div>
                        </div>
                        
                        <!-- Academic Information -->
                        <div class="col-md-6">
                            <h6 class="text-primary mb-3">Academic Information</h6>
                            
                            <div class="mb-3">
                                <label for="email" class="form-label">Email Address <span class="text-danger">*</span></label>
                                <input type="email" class="form-control" id="email" name="email" 
                                       value="<?php echo htmlspecialchars($student['email']); ?>" required>
                                <div class="invalid-feedback">Please provide a valid email address.</div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="password" class="form-label">New Password</label>
                                <input type="password" class="form-control" id="password" name="password" minlength="8">
                                <div class="form-text">Leave blank to keep current password</div>
                                <div class="invalid-feedback">Password must be at least 8 characters long.</div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="roll_number" class="form-label">Roll Number <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="roll_number" name="roll_number" 
                                       value="<?php echo htmlspecialchars($student['roll_number']); ?>" required>
                                <div class="invalid-feedback">Please provide a roll number.</div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="department_id" class="form-label">Department <span class="text-danger">*</span></label>
                                <select class="form-select" id="department_id" name="department_id" required>
                                    <option value="">Select Department</option>
                                    <?php foreach ($departments as $dept): ?>
                                        <option value="<?php echo $dept['department_id']; ?>" 
                                                <?php echo $student['department_id'] == $dept['department_id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($dept['department_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="invalid-feedback">Please select a department.</div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="class_id" class="form-label">Class <span class="text-danger">*</span></label>
                                <select class="form-select" id="class_id" name="class_id" required>
                                    <option value="">Select Class</option>
                                    <?php foreach ($classes as $class): ?>
                                        <option value="<?php echo $class['class_id']; ?>" 
                                                data-department="<?php echo $class['department_id']; ?>"
                                                <?php echo $student['class_id'] == $class['class_id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($class['class_name']); ?> 
                                            (<?php echo htmlspecialchars($class['department_name']); ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="invalid-feedback">Please select a class.</div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Address Information -->
                    <div class="row mt-4">
                        <div class="col-12">
                            <h6 class="text-primary mb-3">Address Information</h6>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="address_line1" class="form-label">Address Line 1</label>
                                <input type="text" class="form-control" id="address_line1" name="address_line1" 
                                       value="<?php echo htmlspecialchars($student['address_line1'] ?? ''); ?>">
                            </div>
                            
                            <div class="mb-3">
                                <label for="address_line2" class="form-label">Address Line 2</label>
                                <input type="text" class="form-control" id="address_line2" name="address_line2" 
                                       value="<?php echo htmlspecialchars($student['address_line2'] ?? ''); ?>">
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="city" class="form-label">City</label>
                                        <input type="text" class="form-control" id="city" name="city" 
                                               value="<?php echo htmlspecialchars($student['city'] ?? ''); ?>">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="state" class="form-label">State</label>
                                        <input type="text" class="form-control" id="state" name="state" 
                                               value="<?php echo htmlspecialchars($student['state'] ?? ''); ?>">
                                    </div>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="postal_code" class="form-label">Postal Code</label>
                                <input type="text" class="form-control" id="postal_code" name="postal_code" 
                                       value="<?php echo htmlspecialchars($student['postal_code'] ?? ''); ?>" pattern="[0-9]{6}">
                                <div class="form-text">Enter 6-digit postal code</div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row mt-4">
                        <div class="col-12">
                            <div class="d-flex gap-2">
                                <button type="submit" class="btn btn-primary btn-loading">
                                    <i class="fas fa-save me-1"></i>Update Student
                                </button>
                                <a href="student_details.php?id=<?php echo $student_id; ?>" class="btn btn-info">
                                    <i class="fas fa-eye me-1"></i>View Details
                                </a>
                                <a href="students.php" class="btn btn-secondary">
                                    <i class="fas fa-times me-1"></i>Cancel
                                </a>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const departmentSelect = document.getElementById('department_id');
    const classSelect = document.getElementById('class_id');
    const form = document.getElementById('editStudentForm');
    
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
    
    // Initial filter - check if department is already selected
    const initialDept = departmentSelect.value;
    if (!initialDept) {
        classSelect.disabled = true;
    }
    filterClasses();
    
    departmentSelect.addEventListener('change', function() {
        filterClasses();
        // Remove validation state when department changes
        classSelect.classList.remove('is-valid', 'is-invalid');
    });
    
    // Define showAlert function locally if not available
    function showAlert(message, type = 'info') {
        // Try to use global showAlert first
        if (window.showAlert && typeof window.showAlert === 'function') {
            return window.showAlert(message, type);
        }

        // Fallback: create alert manually
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

        // Auto-dismiss after 5 seconds
        setTimeout(() => {
            if (alertDiv.parentNode) {
                alertDiv.remove();
            }
        }, 5000);
    }

    const submitBtn = form.querySelector('button[type="submit"]');

    if (submitBtn) {
        submitBtn.addEventListener('click', function(e) {
            e.preventDefault();

            if (!form.checkValidity()) {
                form.classList.add('was-validated');
                showAlert('Please fill in all required fields correctly.', 'danger');
                return;
            }

            const formData = new FormData(form);
            const originalText = submitBtn.innerHTML;

            submitBtn.disabled = true;
            submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Updating...';

            fetch('user_handler.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.text())
            .then(text => {
                try {
                    const data = JSON.parse(text);

                    if (data.success) {
                        showAlert(data.message, 'success');
                        setTimeout(() => {
                            window.location.href = 'student_details.php?id=<?php echo $student_id; ?>';
                        }, 1500);
                    } else {
                        showAlert(data.message, 'danger');
                        submitBtn.disabled = false;
                        submitBtn.innerHTML = originalText;
                    }
                } catch (parseError) {
                    showAlert('Server error occurred. Please try again.', 'danger');
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = originalText;
                }
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
