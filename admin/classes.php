<?php
session_start();
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/config/database.php';

requireRole('Super Admin');

$page_title = 'Class Management';

// Get department filter
$department_filter = $_GET['department_id'] ?? '';

// Build query conditions
$where_conditions = [];
$params = [];

if (!empty($department_filter)) {
    $where_conditions[] = "c.department_id = ?";
    $params[] = $department_filter;
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Get classes with student counts
$stmt = $pdo->prepare("
    SELECT c.*, d.department_name,
           COUNT(u.user_id) as student_count
    FROM classes c
    JOIN departments d ON c.department_id = d.department_id
    LEFT JOIN users u ON c.class_id = u.class_id AND u.role_id = 1
    $where_clause
    GROUP BY c.class_id
    ORDER BY d.department_name, c.class_name
");
$stmt->execute($params);
$classes = $stmt->fetchAll();

// Get departments for dropdown
$stmt = $pdo->query("SELECT department_id, department_name FROM departments ORDER BY department_name");
$departments = $stmt->fetchAll();

// Get current department info if filtered
$current_department = null;
if (!empty($department_filter)) {
    $stmt = $pdo->prepare("SELECT * FROM departments WHERE department_id = ?");
    $stmt->execute([$department_filter]);
    $current_department = $stmt->fetch();
}

include dirname(__DIR__) . '/includes/header.php';
?>

<div class="row">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1>
                Class Management
                <?php if ($current_department): ?>
                    <small class="text-muted">- <?php echo htmlspecialchars($current_department['department_name']); ?></small>
                <?php endif; ?>
            </h1>
            <div>
                <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#addClassModal">
                    <i class="fas fa-plus me-1"></i>Add Class
                </button>
                <a href="departments.php" class="btn btn-outline-secondary">
                    <i class="fas fa-arrow-left me-1"></i>Back to Departments
                </a>
            </div>
        </div>
    </div>
</div>

<!-- Filters -->
<div class="row mb-4">
    <div class="col-md-4">
        <div class="card">
            <div class="card-body">
                <h6 class="card-title"><i class="fas fa-filter me-2"></i>Filters</h6>
                <form method="GET">
                    <div class="mb-3">
                        <label for="department_id" class="form-label">Department</label>
                        <select class="form-select" id="department_id" name="department_id" onchange="this.form.submit()">
                            <option value="">All Departments</option>
                            <?php foreach ($departments as $dept): ?>
                                <option value="<?php echo $dept['department_id']; ?>" 
                                        <?php echo $department_filter == $dept['department_id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($dept['department_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <div class="col-md-8">
        <div class="card">
            <div class="card-body">
                <h6 class="card-title"><i class="fas fa-chart-bar me-2"></i>Statistics</h6>
                <div class="row">
                    <div class="col-md-6">
                        <div class="text-center">
                            <h4 class="text-primary"><?php echo count($classes); ?></h4>
                            <small class="text-muted">Total Classes</small>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="text-center">
                            <h4 class="text-success"><?php echo array_sum(array_column($classes, 'student_count')); ?></h4>
                            <small class="text-muted">Total Students</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-list me-2"></i>Classes</h5>
            </div>
            <div class="card-body">
                <?php if (empty($classes)): ?>
                    <div class="text-center py-5">
                        <i class="fas fa-list fa-3x text-muted mb-3"></i>
                        <h5 class="text-muted">No classes found</h5>
                        <p class="text-muted">
                            <?php if ($current_department): ?>
                                No classes found in <?php echo htmlspecialchars($current_department['department_name']); ?>.
                            <?php else: ?>
                                Create your first class to get started.
                            <?php endif; ?>
                        </p>
                        <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#addClassModal">
                            <i class="fas fa-plus me-1"></i>Add Class
                        </button>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Class Name</th>
                                    <th>Department</th>
                                    <th class="text-center">Students</th>
                                    <th class="text-center">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($classes as $class): ?>
                                    <tr>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <div class="avatar-sm bg-info text-white rounded-circle d-flex align-items-center justify-content-center me-3">
                                                    <?php echo strtoupper(substr($class['class_name'], 0, 2)); ?>
                                                </div>
                                                <div>
                                                    <strong><?php echo htmlspecialchars($class['class_name']); ?></strong>
                                                    <br><small class="text-muted">ID: <?php echo $class['class_id']; ?></small>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="badge bg-primary"><?php echo htmlspecialchars($class['department_name']); ?></span>
                                        </td>
                                        <td class="text-center">
                                            <a href="students.php?class_id=<?php echo $class['class_id']; ?>" 
                                               class="badge bg-success text-decoration-none">
                                                <?php echo $class['student_count']; ?> Students
                                            </a>
                                        </td>
                                        <td class="text-center">
                                            <div class="btn-group btn-group-sm">
                                                <a href="students.php?class_id=<?php echo $class['class_id']; ?>" 
                                                   class="btn btn-outline-success" title="View Students">
                                                    <i class="fas fa-users"></i>
                                                </a>
                                                <button class="btn btn-outline-warning edit-class-btn" 
                                                        data-id="<?php echo $class['class_id']; ?>"
                                                        data-name="<?php echo htmlspecialchars($class['class_name']); ?>"
                                                        data-department="<?php echo $class['department_id']; ?>"
                                                        title="Edit Class">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <button class="btn btn-outline-danger delete-class-btn" 
                                                        data-id="<?php echo $class['class_id']; ?>"
                                                        data-name="<?php echo htmlspecialchars($class['class_name']); ?>"
                                                        data-students="<?php echo $class['student_count']; ?>"
                                                        title="Delete Class">
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

<!-- Add Class Modal -->
<div class="modal fade" id="addClassModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-plus me-2"></i>Add Class</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="addClassForm" class="needs-validation" novalidate>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="class_name" class="form-label">Class Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="class_name" name="class_name" required>
                        <div class="invalid-feedback">Please provide a class name.</div>
                    </div>
                    <div class="mb-3">
                        <label for="add_department_id" class="form-label">Department <span class="text-danger">*</span></label>
                        <select class="form-select" id="add_department_id" name="department_id" required>
                            <option value="">Select Department</option>
                            <?php foreach ($departments as $dept): ?>
                                <option value="<?php echo $dept['department_id']; ?>"
                                        <?php echo $department_filter == $dept['department_id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($dept['department_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="invalid-feedback">Please select a department.</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-plus me-1"></i>Add Class
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Class Modal -->
<div class="modal fade" id="editClassModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-edit me-2"></i>Edit Class</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="editClassForm" class="needs-validation" novalidate>
                <input type="hidden" id="edit_class_id" name="class_id">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="edit_class_name" class="form-label">Class Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="edit_class_name" name="class_name" required>
                        <div class="invalid-feedback">Please provide a class name.</div>
                    </div>
                    <div class="mb-3">
                        <label for="edit_department_id" class="form-label">Department <span class="text-danger">*</span></label>
                        <select class="form-select" id="edit_department_id" name="department_id" required>
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
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning">
                        <i class="fas fa-save me-1"></i>Update Class
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

    // Handle add class form
    const addForm = document.getElementById('addClassForm');
    if (addForm) {
        addForm.addEventListener('submit', function(e) {
            e.preventDefault();

            if (!this.checkValidity()) {
                this.classList.add('was-validated');
                return;
            }

            const formData = new FormData(this);
            formData.append('action', 'add_class');

            const submitBtn = this.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;

            submitBtn.disabled = true;
            submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Adding...';

            fetch('class_handler.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showAlert(data.message, 'success');
                    bootstrap.Modal.getInstance(document.getElementById('addClassModal')).hide();
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

    // Handle edit class buttons
    document.querySelectorAll('.edit-class-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const id = this.dataset.id;
            const name = this.dataset.name;
            const department = this.dataset.department;

            document.getElementById('edit_class_id').value = id;
            document.getElementById('edit_class_name').value = name;
            document.getElementById('edit_department_id').value = department;

            new bootstrap.Modal(document.getElementById('editClassModal')).show();
        });
    });

    // Handle edit class form
    const editForm = document.getElementById('editClassForm');
    if (editForm) {
        editForm.addEventListener('submit', function(e) {
            e.preventDefault();

            if (!this.checkValidity()) {
                this.classList.add('was-validated');
                return;
            }

            const formData = new FormData(this);
            formData.append('action', 'update_class');

            const submitBtn = this.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;

            submitBtn.disabled = true;
            submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Updating...';

            fetch('class_handler.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showAlert(data.message, 'success');
                    bootstrap.Modal.getInstance(document.getElementById('editClassModal')).hide();
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

    // Handle delete class buttons
    document.querySelectorAll('.delete-class-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const id = this.dataset.id;
            const name = this.dataset.name;
            const students = parseInt(this.dataset.students);

            let message = `Are you sure you want to delete the class "${name}"?`;

            if (students > 0) {
                message += `\n\nThis will also delete ${students} students.\n\nThis action cannot be undone.`;
            }

            if (confirm(message)) {
                const formData = new FormData();
                formData.append('action', 'delete_class');
                formData.append('class_id', id);

                this.disabled = true;
                this.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';

                fetch('class_handler.php', {
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
