<?php
session_start();
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/config/database.php';

requireRole('Super Admin');

$page_title = 'Department Management';

// Get departments with accurate class and student counts
$stmt = $pdo->query("
    SELECT d.*,
           COALESCE(class_counts.class_count, 0) as class_count,
           COALESCE(student_counts.student_count, 0) as student_count
    FROM departments d
    LEFT JOIN (
        SELECT department_id, COUNT(*) as class_count
        FROM classes
        GROUP BY department_id
    ) class_counts ON d.department_id = class_counts.department_id
    LEFT JOIN (
        SELECT department_id, COUNT(*) as student_count
        FROM users
        WHERE role_id = 1
        GROUP BY department_id
    ) student_counts ON d.department_id = student_counts.department_id
    ORDER BY d.department_name
");
$departments = $stmt->fetchAll();

include dirname(__DIR__) . '/includes/header.php';
?>

<div class="row">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1>Department Management</h1>
            <div>
                <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#addDepartmentModal">
                    <i class="fas fa-plus me-1"></i>Add Department
                </button>
                <a href="dashboard.php" class="btn btn-outline-secondary">
                    <i class="fas fa-arrow-left me-1"></i>Back to Dashboard
                </a>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-building me-2"></i>Departments</h5>
            </div>
            <div class="card-body">
                <?php if (empty($departments)): ?>
                    <div class="text-center py-5">
                        <i class="fas fa-building fa-3x text-muted mb-3"></i>
                        <h5 class="text-muted">No departments found</h5>
                        <p class="text-muted">Create your first department to get started.</p>
                        <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#addDepartmentModal">
                            <i class="fas fa-plus me-1"></i>Add Department
                        </button>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Department Name</th>
                                    <th class="text-center">Classes</th>
                                    <th class="text-center">Students</th>
                                    <th class="text-center">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($departments as $dept): ?>
                                    <tr>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <div class="avatar-sm bg-primary text-white rounded-circle d-flex align-items-center justify-content-center me-3">
                                                    <?php echo strtoupper(substr($dept['department_name'], 0, 2)); ?>
                                                </div>
                                                <div>
                                                    <strong><?php echo htmlspecialchars($dept['department_name']); ?></strong>
                                                    <br><small class="text-muted">ID: <?php echo $dept['department_id']; ?></small>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="text-center">
                                            <a href="classes.php?department_id=<?php echo $dept['department_id']; ?>" 
                                               class="badge bg-info text-decoration-none">
                                                <?php echo $dept['class_count']; ?> Classes
                                            </a>
                                        </td>
                                        <td class="text-center">
                                            <span class="badge bg-success"><?php echo $dept['student_count']; ?></span>
                                        </td>
                                        <td class="text-center">
                                            <div class="btn-group btn-group-sm">
                                                <a href="classes.php?department_id=<?php echo $dept['department_id']; ?>" 
                                                   class="btn btn-outline-info" title="Manage Classes">
                                                    <i class="fas fa-list"></i>
                                                </a>
                                                <button class="btn btn-outline-warning edit-dept-btn" 
                                                        data-id="<?php echo $dept['department_id']; ?>"
                                                        data-name="<?php echo htmlspecialchars($dept['department_name']); ?>"
                                                        title="Edit Department">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <button class="btn btn-outline-danger delete-dept-btn" 
                                                        data-id="<?php echo $dept['department_id']; ?>"
                                                        data-name="<?php echo htmlspecialchars($dept['department_name']); ?>"
                                                        data-classes="<?php echo $dept['class_count']; ?>"
                                                        data-students="<?php echo $dept['student_count']; ?>"
                                                        title="Delete Department">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Add Department Modal -->
<div class="modal fade" id="addDepartmentModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-plus me-2"></i>Add Department</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="addDepartmentForm" class="needs-validation" novalidate>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="department_name" class="form-label">Department Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="department_name" name="department_name" required>
                        <div class="invalid-feedback">Please provide a department name.</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-plus me-1"></i>Add Department
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Department Modal -->
<div class="modal fade" id="editDepartmentModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-edit me-2"></i>Edit Department</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="editDepartmentForm" class="needs-validation" novalidate>
                <input type="hidden" id="edit_department_id" name="department_id">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="edit_department_name" class="form-label">Department Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="edit_department_name" name="department_name" required>
                        <div class="invalid-feedback">Please provide a department name.</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning">
                        <i class="fas fa-save me-1"></i>Update Department
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
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
    
    // Handle add department form
    const addForm = document.getElementById('addDepartmentForm');
    if (addForm) {
        addForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            if (!this.checkValidity()) {
                this.classList.add('was-validated');
                return;
            }
            
            const formData = new FormData(this);
            formData.append('action', 'add_department');
            
            const submitBtn = this.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;
            
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Adding...';
            
            fetch('department_handler.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showAlert(data.message, 'success');
                    bootstrap.Modal.getInstance(document.getElementById('addDepartmentModal')).hide();
                    setTimeout(() => window.location.reload(), 1500);
                } else {
                    showAlert(data.message, 'danger');
                }
            })
            .catch(error => {
                showAlert('Network error. Please try again.', 'danger');
            })
            .finally(() => {
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalText;
            });
        });
    }
    
    // Handle edit department buttons
    document.querySelectorAll('.edit-dept-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const id = this.dataset.id;
            const name = this.dataset.name;
            
            document.getElementById('edit_department_id').value = id;
            document.getElementById('edit_department_name').value = name;
            
            new bootstrap.Modal(document.getElementById('editDepartmentModal')).show();
        });
    });
    
    // Handle edit department form
    const editForm = document.getElementById('editDepartmentForm');
    if (editForm) {
        editForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            if (!this.checkValidity()) {
                this.classList.add('was-validated');
                return;
            }
            
            const formData = new FormData(this);
            formData.append('action', 'update_department');
            
            const submitBtn = this.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;
            
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Updating...';
            
            fetch('department_handler.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showAlert(data.message, 'success');
                    bootstrap.Modal.getInstance(document.getElementById('editDepartmentModal')).hide();
                    setTimeout(() => window.location.reload(), 1500);
                } else {
                    showAlert(data.message, 'danger');
                }
            })
            .catch(error => {
                showAlert('Network error. Please try again.', 'danger');
            })
            .finally(() => {
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalText;
            });
        });
    }
    
    // Handle delete department buttons
    document.querySelectorAll('.delete-dept-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const id = this.dataset.id;
            const name = this.dataset.name;
            const classes = parseInt(this.dataset.classes);
            const students = parseInt(this.dataset.students);
            
            let message = `Are you sure you want to delete the department "${name}"?`;
            
            if (classes > 0 || students > 0) {
                message += `\n\nThis will also delete:\n- ${classes} classes\n- ${students} students\n\nThis action cannot be undone.`;
            }
            
            if (confirm(message)) {
                const formData = new FormData();
                formData.append('action', 'delete_department');
                formData.append('department_id', id);
                
                this.disabled = true;
                this.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';
                
                fetch('department_handler.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showAlert(data.message, 'success');
                        setTimeout(() => window.location.reload(), 1500);
                    } else {
                        showAlert(data.message, 'danger');
                        this.disabled = false;
                        this.innerHTML = '<i class="fas fa-trash"></i>';
                    }
                })
                .catch(error => {
                    showAlert('Network error. Please try again.', 'danger');
                    this.disabled = false;
                    this.innerHTML = '<i class="fas fa-trash"></i>';
                });
            }
        });
    });
});
</script>

<style>
.avatar-sm {
    width: 40px;
    height: 40px;
    font-size: 0.875rem;
    font-weight: bold;
}
</style>

<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
