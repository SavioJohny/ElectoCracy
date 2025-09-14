<?php
session_start();
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/config/database.php';

requireRole('Super Admin');

$page_title = 'Student Management';

// Handle sorting and filtering
$sort_by = $_GET['sort'] ?? 'fname';
$sort_order = $_GET['order'] ?? 'ASC';
$search = $_GET['search'] ?? '';
$class_filter = $_GET['class'] ?? $_GET['class_id'] ?? '';
$department_filter = $_GET['department'] ?? $_GET['department_id'] ?? '';

// Validate sort parameters
$allowed_sorts = ['fname', 'lname', 'roll_number', 'email', 'class_name', 'department_name', 'dob'];
if (!in_array($sort_by, $allowed_sorts)) {
    $sort_by = 'fname';
}
$sort_order = strtoupper($sort_order) === 'DESC' ? 'DESC' : 'ASC';

// Build query
$where_conditions = ["u.role_id = 1"]; // Students only
$params = [];

if (!empty($search)) {
    $where_conditions[] = "(u.fname LIKE ? OR u.lname LIKE ? OR u.roll_number LIKE ? OR u.email LIKE ?)";
    $search_param = "%$search%";
    $params = array_merge($params, [$search_param, $search_param, $search_param, $search_param]);
}

if (!empty($class_filter)) {
    $where_conditions[] = "u.class_id = ?";
    $params[] = $class_filter;
}

if (!empty($department_filter)) {
    $where_conditions[] = "u.department_id = ?";
    $params[] = $department_filter;
}

$where_clause = implode(' AND ', $where_conditions);

// Get students with sorting
$stmt = $pdo->prepare("
    SELECT u.*, c.class_name, d.department_name, r.role_name
    FROM users u
    JOIN roles r ON u.role_id = r.role_id
    LEFT JOIN classes c ON u.class_id = c.class_id
    LEFT JOIN departments d ON u.department_id = d.department_id
    WHERE $where_clause
    ORDER BY $sort_by $sort_order
");
$stmt->execute($params);
$students = $stmt->fetchAll();

// Get filter options
$stmt = $pdo->query("SELECT class_id, class_name FROM classes ORDER BY class_name");
$classes = $stmt->fetchAll();

$stmt = $pdo->query("SELECT department_id, department_name FROM departments ORDER BY department_name");
$departments = $stmt->fetchAll();

// Get current filter context for display
$current_class = null;
$current_department = null;

if (!empty($class_filter)) {
    $stmt = $pdo->prepare("
        SELECT c.class_name, d.department_name, d.department_id
        FROM classes c
        JOIN departments d ON c.department_id = d.department_id
        WHERE c.class_id = ?
    ");
    $stmt->execute([$class_filter]);
    $current_class = $stmt->fetch();
}

if (!empty($department_filter) && empty($class_filter)) {
    $stmt = $pdo->prepare("SELECT department_name FROM departments WHERE department_id = ?");
    $stmt->execute([$department_filter]);
    $current_department = $stmt->fetch();
}

include dirname(__DIR__) . '/includes/header.php';
?>

<div class="row">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1>Student Management</h1>
                <?php if ($current_class): ?>
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb mb-0">
                            <li class="breadcrumb-item"><a href="students.php">All Students</a></li>
                            <li class="breadcrumb-item"><a href="students.php?department_id=<?php echo $current_class['department_id']; ?>"><?php echo htmlspecialchars($current_class['department_name']); ?></a></li>
                            <li class="breadcrumb-item active"><?php echo htmlspecialchars($current_class['class_name']); ?></li>
                        </ol>
                    </nav>
                <?php elseif ($current_department): ?>
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb mb-0">
                            <li class="breadcrumb-item"><a href="students.php">All Students</a></li>
                            <li class="breadcrumb-item active"><?php echo htmlspecialchars($current_department['department_name']); ?></li>
                        </ol>
                    </nav>
                <?php endif; ?>
            </div>
            <div>
                <?php if ($current_class): ?>
                    <a href="classes.php?department_id=<?php echo $current_class['department_id']; ?>" class="btn btn-outline-info me-2">
                        <i class="fas fa-arrow-left me-1"></i>Back to Classes
                    </a>
                <?php elseif ($current_department): ?>
                    <a href="departments.php" class="btn btn-outline-info me-2">
                        <i class="fas fa-arrow-left me-1"></i>Back to Departments
                    </a>
                <?php else: ?>
                    <a href="dashboard.php" class="btn btn-outline-secondary me-2">
                        <i class="fas fa-arrow-left me-1"></i>Back to Dashboard
                    </a>
                <?php endif; ?>
                <div class="btn-group me-2">
                    <button type="button" class="btn btn-outline-info dropdown-toggle" data-bs-toggle="dropdown">
                        <i class="fas fa-exchange-alt me-1"></i>Import/Export
                    </button>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="import_students.php">
                            <i class="fas fa-upload me-2"></i>Import Students
                        </a></li>
                        <li><a class="dropdown-item" href="export_students.php">
                            <i class="fas fa-download me-2"></i>Export Students
                        </a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="download_sample.php">
                            <i class="fas fa-file-csv me-2"></i>Download Sample CSV
                        </a></li>
                    </ul>
                </div>
                <button id="massDeleteBtn" class="btn btn-danger me-2" style="display: none;">
                    <i class="fas fa-trash me-1"></i>Delete Selected
                </button>
                <a href="add_student.php" class="btn btn-success">
                    <i class="fas fa-user-plus me-1"></i>Add Student
                </a>
            </div>
        </div>
    </div>
</div>

<!-- Filters and Search -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <div class="col-md-3">
                        <label for="search" class="form-label">Search</label>
                        <input type="text" class="form-control" id="search" name="search" 
                               value="<?php echo htmlspecialchars($search); ?>" 
                               placeholder="Name, Roll No, Email...">
                    </div>
                    <div class="col-md-3">
                        <label for="department" class="form-label">Department</label>
                        <select class="form-select" id="department" name="department">
                            <option value="">All Departments</option>
                            <?php foreach ($departments as $dept): ?>
                                <option value="<?php echo $dept['department_id']; ?>" 
                                        <?php echo $department_filter == $dept['department_id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($dept['department_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label for="class" class="form-label">Class</label>
                        <select class="form-select" id="class" name="class">
                            <option value="">All Classes</option>
                            <?php foreach ($classes as $class): ?>
                                <option value="<?php echo $class['class_id']; ?>" 
                                        <?php echo $class_filter == $class['class_id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($class['class_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">&nbsp;</label>
                        <div class="d-grid gap-2 d-md-flex">
                            <a href="students.php" class="btn btn-outline-secondary">
                                <i class="fas fa-times me-1"></i>Clear
                            </a>
                        </div>
                    </div>
                    <!-- Preserve sort parameters -->
                    <input type="hidden" name="sort" value="<?php echo htmlspecialchars($sort_by); ?>">
                    <input type="hidden" name="order" value="<?php echo htmlspecialchars($sort_order); ?>">
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Results -->
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-graduation-cap me-2"></i>Students 
                    <span class="badge bg-primary"><?php echo count($students); ?></span>
                </h5>
            </div>
            <div class="card-body">
                <?php if (empty($students)): ?>
                    <div class="text-center py-4">
                        <i class="fas fa-user-graduate fa-3x text-muted mb-3"></i>
                        <h5 class="text-muted">No Students Found</h5>
                        <p class="text-muted">No students match your current filters.</p>
                        <a href="students.php?action=add" class="btn btn-primary">
                            <i class="fas fa-user-plus me-1"></i>Add First Student
                        </a>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th width="40">
                                        <input type="checkbox" id="selectAll" class="form-check-input">
                                    </th>
                                    <?php
                                    $sort_icon = $sort_order === 'ASC' ? 'fa-sort-up' : 'fa-sort-down';
                                    $next_order = $sort_order === 'ASC' ? 'DESC' : 'ASC';
                                    
                                    function getSortLink($column, $label, $current_sort, $current_order, $search, $class_filter, $department_filter) {
                                        $icon = '';
                                        $next_order = 'ASC';
                                        
                                        if ($current_sort === $column) {
                                            $icon = $current_order === 'ASC' ? '<i class="fas fa-sort-up ms-1"></i>' : '<i class="fas fa-sort-down ms-1"></i>';
                                            $next_order = $current_order === 'ASC' ? 'DESC' : 'ASC';
                                        }
                                        
                                        $params = [
                                            'sort' => $column,
                                            'order' => $next_order,
                                            'search' => $search,
                                            'class' => $class_filter,
                                            'department' => $department_filter
                                        ];
                                        
                                        $query_string = http_build_query(array_filter($params));
                                        
                                        return "<a href='?$query_string' class='text-decoration-none text-dark'>$label $icon</a>";
                                    }
                                    ?>
                                    <th><?php echo getSortLink('roll_number', 'Roll No', $sort_by, $sort_order, $search, $class_filter, $department_filter); ?></th>
                                    <th><?php echo getSortLink('fname', 'Name', $sort_by, $sort_order, $search, $class_filter, $department_filter); ?></th>
                                    <th><?php echo getSortLink('email', 'Email', $sort_by, $sort_order, $search, $class_filter, $department_filter); ?></th>
                                    <th><?php echo getSortLink('class_name', 'Class', $sort_by, $sort_order, $search, $class_filter, $department_filter); ?></th>
                                    <th><?php echo getSortLink('department_name', 'Department', $sort_by, $sort_order, $search, $class_filter, $department_filter); ?></th>
                                    <th><?php echo getSortLink('dob', 'DOB', $sort_by, $sort_order, $search, $class_filter, $department_filter); ?></th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($students as $student): ?>
                                    <tr>
                                        <td>
                                            <input type="checkbox" class="form-check-input student-checkbox"
                                                   value="<?php echo $student['user_id']; ?>">
                                        </td>
                                        <td>
                                            <strong><?php echo htmlspecialchars($student['roll_number']); ?></strong>
                                        </td>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <div class="avatar-sm bg-primary text-white rounded-circle d-flex align-items-center justify-content-center me-2">
                                                    <?php echo strtoupper(substr($student['fname'], 0, 1)); ?>
                                                </div>
                                                <div>
                                                    <strong><?php echo htmlspecialchars($student['fname'] . ' ' . $student['lname']); ?></strong>
                                                    <br><small class="text-muted"><?php echo htmlspecialchars($student['gender'] ?? 'N/A'); ?></small>
                                                </div>
                                            </div>
                                        </td>
                                        <td><?php echo htmlspecialchars($student['email']); ?></td>
                                        <td>
                                            <span class="badge bg-info"><?php echo htmlspecialchars($student['class_name'] ?? 'N/A'); ?></span>
                                        </td>
                                        <td><?php echo htmlspecialchars($student['department_name'] ?? 'N/A'); ?></td>
                                        <td><?php echo $student['dob'] ? date('M j, Y', strtotime($student['dob'])) : 'N/A'; ?></td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <a href="student_details.php?id=<?php echo $student['user_id']; ?>" 
                                                   class="btn btn-outline-primary" title="View Details">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                                <a href="edit_student.php?id=<?php echo $student['user_id']; ?>" 
                                                   class="btn btn-outline-warning" title="Edit">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                <button class="btn btn-outline-danger delete-confirm" 
                                                        data-id="<?php echo $student['user_id']; ?>" title="Delete">
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

<style>
.avatar-sm {
    width: 32px;
    height: 32px;
    font-size: 14px;
    font-weight: bold;
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const selectAllCheckbox = document.getElementById('selectAll');
    const studentCheckboxes = document.querySelectorAll('.student-checkbox');
    const massDeleteBtn = document.getElementById('massDeleteBtn');
    const filterForm = document.querySelector('form[method="GET"]');
    const searchInput = document.getElementById('search');
    const departmentSelect = document.getElementById('department');
    const classSelect = document.getElementById('class');

    // Auto-submit form when filters change
    function autoSubmitForm() {
        filterForm.submit();
    }

    // Add event listeners for auto-filtering
    departmentSelect.addEventListener('change', autoSubmitForm);
    classSelect.addEventListener('change', autoSubmitForm);
    
    // For search input, add a small delay to avoid too many requests
    let searchTimeout;
    searchInput.addEventListener('input', function() {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(autoSubmitForm, 500);
    });

    // Handle select all functionality
    selectAllCheckbox.addEventListener('change', function() {
        studentCheckboxes.forEach(checkbox => {
            checkbox.checked = this.checked;
        });
        toggleMassDeleteButton();
    });

    // Handle individual checkbox changes
    studentCheckboxes.forEach(checkbox => {
        checkbox.addEventListener('change', function() {
            // Update select all checkbox state
            const checkedCount = document.querySelectorAll('.student-checkbox:checked').length;
            selectAllCheckbox.checked = checkedCount === studentCheckboxes.length;
            selectAllCheckbox.indeterminate = checkedCount > 0 && checkedCount < studentCheckboxes.length;

            toggleMassDeleteButton();
        });
    });

    // Show/hide mass delete button
    function toggleMassDeleteButton() {
        const checkedCount = document.querySelectorAll('.student-checkbox:checked').length;
        massDeleteBtn.style.display = checkedCount > 0 ? 'inline-block' : 'none';
        massDeleteBtn.textContent = `Delete Selected (${checkedCount})`;
    }

    // Handle mass delete
    massDeleteBtn.addEventListener('click', function() {
        const selectedIds = Array.from(document.querySelectorAll('.student-checkbox:checked'))
            .map(checkbox => checkbox.value);

        if (selectedIds.length === 0) {
            showAlert('No students selected.', 'warning');
            return;
        }

        if (!confirm(`Are you sure you want to delete ${selectedIds.length} student(s)? This action cannot be undone.`)) {
            return;
        }

        const formData = new FormData();
        formData.append('action', 'mass_delete');
        selectedIds.forEach(id => formData.append('user_ids[]', id));

        massDeleteBtn.disabled = true;
        massDeleteBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Deleting...';

        fetch('user_handler.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showAlert(data.message, 'success');
                setTimeout(() => {
                    window.location.reload();
                }, 1500);
            } else {
                showAlert(data.message, 'danger');
                massDeleteBtn.disabled = false;
                massDeleteBtn.innerHTML = '<i class="fas fa-trash me-1"></i>Delete Selected';
            }
        })
        .catch(error => {
            showAlert('Network error. Please try again.', 'danger');
            massDeleteBtn.disabled = false;
            massDeleteBtn.innerHTML = '<i class="fas fa-trash me-1"></i>Delete Selected';
        });
    });

    // Handle individual delete buttons
    document.querySelectorAll('.delete-confirm').forEach(button => {
        button.addEventListener('click', function() {
            const userId = this.dataset.id;

            if (!confirm('Are you sure you want to delete this student? This action cannot be undone.')) {
                return;
            }

            const formData = new FormData();
            formData.append('action', 'delete_user');
            formData.append('user_id', userId);

            this.disabled = true;
            this.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';

            fetch('user_handler.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showAlert(data.message, 'success');
                    setTimeout(() => {
                        window.location.reload();
                    }, 1500);
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
        });
    });

    // Auto-focus search field on page load
    searchInput.focus();
    
    // Move cursor to end of search input if it has content
    if (searchInput.value) {
        searchInput.setSelectionRange(searchInput.value.length, searchInput.value.length);
    }
});
</script>

<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
