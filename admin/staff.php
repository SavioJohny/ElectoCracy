<?php
session_start();
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/config/database.php';

requireRole('Super Admin');

$page_title = 'Staff Management';

// Handle sorting and filtering
$sort_by = $_GET['sort'] ?? 'fname';
$sort_order = $_GET['order'] ?? 'ASC';
$search = $_GET['search'] ?? '';
$role_filter = $_GET['role'] ?? '';
$department_filter = $_GET['department'] ?? '';

// Validate sort parameters
$allowed_sorts = ['fname', 'lname', 'email', 'role_name', 'department_name', 'dob'];
if (!in_array($sort_by, $allowed_sorts)) {
    $sort_by = 'fname';
}
$sort_order = strtoupper($sort_order) === 'DESC' ? 'DESC' : 'ASC';

// Build query - exclude students
$where_conditions = ["u.role_id != 1"]; // Not students
$params = [];

if (!empty($search)) {
    $where_conditions[] = "(u.fname LIKE ? OR u.lname LIKE ? OR u.email LIKE ?)";
    $search_param = "%$search%";
    $params = array_merge($params, [$search_param, $search_param, $search_param]);
}

if (!empty($role_filter)) {
    $where_conditions[] = "r.role_name = ?";
    $params[] = $role_filter;
}

if (!empty($department_filter)) {
    $where_conditions[] = "u.department_id = ?";
    $params[] = $department_filter;
}

$where_clause = implode(' AND ', $where_conditions);

// Get staff with sorting
$stmt = $pdo->prepare("
    SELECT u.*, d.department_name, r.role_name
    FROM users u
    JOIN roles r ON u.role_id = r.role_id
    LEFT JOIN departments d ON u.department_id = d.department_id
    WHERE $where_clause
    ORDER BY $sort_by $sort_order
");
$stmt->execute($params);
$staff = $stmt->fetchAll();

// Get filter options - roles except Student
$stmt = $pdo->query("SELECT role_name FROM roles WHERE role_name != 'Student' ORDER BY role_name");
$roles = $stmt->fetchAll();

$stmt = $pdo->query("SELECT department_id, department_name FROM departments ORDER BY department_name");
$departments = $stmt->fetchAll();

include dirname(__DIR__) . '/includes/header.php';
?>

<div class="row">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1>Staff & Admin Management</h1>
            <div>
                <a href="dashboard.php" class="btn btn-outline-secondary me-2">
                    <i class="fas fa-arrow-left me-1"></i>Back to Dashboard
                </a>
                <button id="massDeleteBtn" class="btn btn-danger me-2" style="display: none;">
                    <i class="fas fa-trash me-1"></i>Delete Selected
                </button>
                <a href="add_staff.php" class="btn btn-success">
                    <i class="fas fa-user-plus me-1"></i>Add Staff
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
                    <div class="col-md-4">
                        <label for="search" class="form-label">Search</label>
                        <input type="text" class="form-control" id="search" name="search" 
                               value="<?php echo htmlspecialchars($search); ?>" 
                               placeholder="Name, Email...">
                    </div>
                    <div class="col-md-3">
                        <label for="role" class="form-label">Role</label>
                        <select class="form-select" id="role" name="role">
                            <option value="">All Roles</option>
                            <?php foreach ($roles as $role): ?>
                                <option value="<?php echo htmlspecialchars($role['role_name']); ?>" 
                                        <?php echo $role_filter == $role['role_name'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($role['role_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
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
                    <div class="col-md-2">
                        <label class="form-label">&nbsp;</label>
                        <div class="d-grid gap-2">
                            <a href="staff.php" class="btn btn-outline-secondary">
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
                    <i class="fas fa-user-tie me-2"></i>Staff & Administrators 
                    <span class="badge bg-primary"><?php echo count($staff); ?></span>
                </h5>
            </div>
            <div class="card-body">
                <?php if (empty($staff)): ?>
                    <div class="text-center py-4">
                        <i class="fas fa-user-tie fa-3x text-muted mb-3"></i>
                        <h5 class="text-muted">No Staff Found</h5>
                        <p class="text-muted">No staff members match your current filters.</p>
                        <a href="staff.php?action=add" class="btn btn-primary">
                            <i class="fas fa-user-plus me-1"></i>Add First Staff Member
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
                                    function getSortLink($column, $label, $current_sort, $current_order, $search, $role_filter, $department_filter) {
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
                                            'role' => $role_filter,
                                            'department' => $department_filter
                                        ];
                                        
                                        $query_string = http_build_query(array_filter($params));
                                        
                                        return "<a href='?$query_string' class='text-decoration-none text-dark'>$label $icon</a>";
                                    }
                                    ?>
                                    <th><?php echo getSortLink('fname', 'Name', $sort_by, $sort_order, $search, $role_filter, $department_filter); ?></th>
                                    <th><?php echo getSortLink('email', 'Email', $sort_by, $sort_order, $search, $role_filter, $department_filter); ?></th>
                                    <th><?php echo getSortLink('role_name', 'Role', $sort_by, $sort_order, $search, $role_filter, $department_filter); ?></th>
                                    <th><?php echo getSortLink('department_name', 'Department', $sort_by, $sort_order, $search, $role_filter, $department_filter); ?></th>
                                    <th>Contact</th>
                                    <th><?php echo getSortLink('dob', 'DOB', $sort_by, $sort_order, $search, $role_filter, $department_filter); ?></th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($staff as $member): ?>
                                    <tr>
                                        <td>
                                            <input type="checkbox" class="form-check-input staff-checkbox"
                                                   value="<?php echo $member['user_id']; ?>"
                                                   <?php echo $member['user_id'] == $_SESSION['user_id'] ? 'disabled title="Cannot select your own account"' : ''; ?>>
                                        </td>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <div class="avatar-sm bg-<?php echo getRoleColor($member['role_name']); ?> text-white rounded-circle d-flex align-items-center justify-content-center me-2">
                                                    <?php echo strtoupper(substr($member['fname'], 0, 1)); ?>
                                                </div>
                                                <div>
                                                    <strong><?php echo htmlspecialchars($member['fname'] . ' ' . $member['lname']); ?></strong>
                                                    <br><small class="text-muted"><?php echo htmlspecialchars($member['gender'] ?? 'N/A'); ?></small>
                                                </div>
                                            </div>
                                        </td>
                                        <td><?php echo htmlspecialchars($member['email']); ?></td>
                                        <td>
                                            <span class="badge bg-<?php echo getRoleColor($member['role_name']); ?>">
                                                <?php echo htmlspecialchars($member['role_name']); ?>
                                            </span>
                                        </td>
                                        <td><?php echo htmlspecialchars($member['department_name'] ?? 'N/A'); ?></td>
                                        <td>
                                            <?php if ($member['phone']): ?>
                                                <small><i class="fas fa-phone me-1"></i><?php echo htmlspecialchars($member['phone']); ?></small>
                                            <?php else: ?>
                                                <small class="text-muted">No phone</small>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo $member['dob'] ? date('M j, Y', strtotime($member['dob'])) : 'N/A'; ?></td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <a href="staff_details.php?id=<?php echo $member['user_id']; ?>" 
                                                   class="btn btn-outline-primary" title="View Details">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                                <a href="edit_staff.php?id=<?php echo $member['user_id']; ?>" 
                                                   class="btn btn-outline-warning" title="Edit">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                <?php if ($member['user_id'] != $_SESSION['user_id']): // Can't delete self ?>
                                                    <button class="btn btn-outline-danger delete-confirm" 
                                                            data-id="<?php echo $member['user_id']; ?>" title="Delete">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                <?php endif; ?>
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

<?php
function getRoleColor($role) {
    switch ($role) {
        case 'Super Admin':
            return 'danger';
        case 'Election Commissioner':
            return 'warning';
        case 'Invigilator':
            return 'info';
        default:
            return 'secondary';
    }
}
?>

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
    const staffCheckboxes = document.querySelectorAll('.staff-checkbox:not([disabled])');
    const massDeleteBtn = document.getElementById('massDeleteBtn');
    const filterForm = document.querySelector('form[method="GET"]');
    const searchInput = document.getElementById('search');
    const roleSelect = document.getElementById('role');
    const departmentSelect = document.getElementById('department');

    // Auto-submit form when filters change
    function autoSubmitForm() {
        filterForm.submit();
    }

    // Add event listeners for auto-filtering
    roleSelect.addEventListener('change', autoSubmitForm);
    departmentSelect.addEventListener('change', autoSubmitForm);
    
    // For search input, add a small delay to avoid too many requests
    let searchTimeout;
    searchInput.addEventListener('input', function() {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(autoSubmitForm, 500);
    });

    // Auto-focus search field on page load
    searchInput.focus();
    
    // Move cursor to end of search input if it has content
    if (searchInput.value) {
        searchInput.setSelectionRange(searchInput.value.length, searchInput.value.length);
    }

    // Handle select all functionality
    selectAllCheckbox.addEventListener('change', function() {
        staffCheckboxes.forEach(checkbox => {
            checkbox.checked = this.checked;
        });
        toggleMassDeleteButton();
    });

    // Handle individual checkbox changes
    staffCheckboxes.forEach(checkbox => {
        checkbox.addEventListener('change', function() {
            // Update select all checkbox state
            const checkedCount = document.querySelectorAll('.staff-checkbox:checked').length;
            selectAllCheckbox.checked = checkedCount === staffCheckboxes.length;
            selectAllCheckbox.indeterminate = checkedCount > 0 && checkedCount < staffCheckboxes.length;

            toggleMassDeleteButton();
        });
    });

    // Show/hide mass delete button
    function toggleMassDeleteButton() {
        const checkedCount = document.querySelectorAll('.staff-checkbox:checked').length;
        massDeleteBtn.style.display = checkedCount > 0 ? 'inline-block' : 'none';
        massDeleteBtn.textContent = `Delete Selected (${checkedCount})`;
    }

    // Handle mass delete
    massDeleteBtn.addEventListener('click', function() {
        const selectedIds = Array.from(document.querySelectorAll('.staff-checkbox:checked'))
            .map(checkbox => checkbox.value);

        if (selectedIds.length === 0) {
            showAlert('No staff members selected.', 'warning');
            return;
        }

        if (!confirm(`Are you sure you want to delete ${selectedIds.length} staff member(s)? This action cannot be undone.`)) {
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

            if (!confirm('Are you sure you want to delete this staff member? This action cannot be undone.')) {
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
});
</script>

<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
