<?php
session_start();
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/config/database.php';

requireRole('Election Commissioner');

$page_title = 'Student Disqualifications';
$current_year = date('Y');

// Get all elections for the current year
$stmt = $pdo->prepare("
    SELECT e.*, et.election_type_name, c.class_name, d.department_name
    FROM elections e
    JOIN election_types et ON e.election_type_id = et.election_type_id
    LEFT JOIN classes c ON e.class_id = c.class_id
    LEFT JOIN departments d ON c.department_id = d.department_id
    WHERE e.election_year = ?
    ORDER BY et.election_type_name, c.class_name, e.election_id
");
$stmt->execute([$current_year]);
$all_elections = $stmt->fetchAll();

// Separate elections by type
$class_elections = array_filter($all_elections, function($e) {
    return $e['election_type_name'] === 'class';
});

$union_elections = array_filter($all_elections, function($e) {
    return $e['election_type_name'] === 'union';
});

include dirname(__DIR__) . '/includes/header.php';
?>

<div class="container-fluid">
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h2><i class="fas fa-user-slash me-2"></i>Student Disqualifications - <?php echo $current_year; ?></h2>
                    <p class="text-muted">Manage student disqualifications for class and union elections</p>
                </div>
                <div>
                    <a href="dashboard.php" class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-left me-1"></i>Back to Dashboard
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Union Elections Disqualifications -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-university me-2"></i>Union Election Disqualifications
                    </h5>
                </div>
                <div class="card-body">
                    <?php if (empty($union_elections)): ?>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>
                            No union elections found for <?php echo $current_year; ?>.
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Election Year</th>
                                        <th>Status</th>
                                        <th>Union-Eligible Students</th>
                                        <th>Disqualified</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($union_elections as $election): ?>
                                        <?php
                                        // Get total union-eligible students (approved candidates in class elections)
                                        $stmt = $pdo->prepare("
                                            SELECT COUNT(DISTINCT c.user_id) as union_eligible
                                            FROM candidates c
                                            JOIN elections e ON c.election_id = e.election_id
                                            JOIN election_types et ON e.election_type_id = et.election_type_id
                                            WHERE et.election_type_name = 'class'
                                            AND e.election_year = ?
                                            AND c.is_approved = 'approved'
                                        ");
                                        $stmt->execute([$election['election_year']]);
                                        $union_eligible = $stmt->fetchColumn();

                                        // Get disqualified students for this union election
                                        $stmt = $pdo->prepare("
                                            SELECT COUNT(*) as disqualified_count
                                            FROM election_disqualifications
                                            WHERE election_id = ?
                                        ");
                                        $stmt->execute([$election['election_id']]);
                                        $disqualified_count = $stmt->fetchColumn();
                                        ?>
                                        <tr>
                                            <td>
                                                <strong>Union Election <?php echo $election['election_year']; ?></strong>
                                            </td>
                                            <td>
                                                <span class="badge bg-<?php 
                                                    echo $election['voting_status'] === 'ended' ? 'success' : 
                                                        ($election['voting_status'] === 'active' ? 'primary' : 'secondary'); 
                                                ?>">
                                                    <?php echo ucfirst(str_replace('_', ' ', $election['voting_status'])); ?>
                                                </span>
                                            </td>
                                            <td><span class="badge bg-info"><?php echo $union_eligible; ?></span></td>
                                            <td><span class="badge bg-danger"><?php echo $disqualified_count; ?></span></td>
                                            <td>
                                                <a href="union_disqualifications.php?election_id=<?php echo $election['election_id']; ?>" 
                                                   class="btn btn-sm btn-outline-primary" title="Manage Disqualifications">
                                                    <i class="fas fa-user-slash me-1"></i>Manage
                                                </a>
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

    <!-- Class Elections Disqualifications -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">
                        <i class="fas fa-users me-2"></i>Class Election Disqualifications
                    </h5>
                    <div class="d-flex gap-2">
                        <select id="departmentFilter" class="form-select form-select-sm" style="width: auto;">
                            <option value="">All Departments</option>
                            <?php
                            // Get unique departments for filter
                            $departments = array_unique(array_column($class_elections, 'department_name'));
                            sort($departments);
                            foreach ($departments as $dept): 
                                if ($dept): ?>
                                    <option value="<?php echo htmlspecialchars($dept); ?>"><?php echo htmlspecialchars($dept); ?></option>
                                <?php endif;
                            endforeach; ?>
                        </select>
                        <button class="btn btn-sm btn-outline-secondary" onclick="sortClassTable('department')" title="Sort by Department">
                            <i class="fas fa-sort-alpha-down"></i>
                        </button>
                    </div>
                </div>
                <div class="card-body">
                    <?php if (empty($class_elections)): ?>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>
                            No class elections found for <?php echo $current_year; ?>.
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover" id="classElectionsTable">
                                <thead>
                                    <tr>
                                        <th>
                                            <a href="#" onclick="sortClassTable('class'); return false;" class="text-decoration-none text-dark">
                                                Class <i class="fas fa-sort ms-1"></i>
                                            </a>
                                        </th>
                                        <th>
                                            <a href="#" onclick="sortClassTable('department'); return false;" class="text-decoration-none text-dark">
                                                Department <i class="fas fa-sort ms-1"></i>
                                            </a>
                                        </th>
                                        <th>
                                            <a href="#" onclick="sortClassTable('status'); return false;" class="text-decoration-none text-dark">
                                                Status <i class="fas fa-sort ms-1"></i>
                                            </a>
                                        </th>
                                        <th>
                                            <a href="#" onclick="sortClassTable('total'); return false;" class="text-decoration-none text-dark">
                                                Total Students <i class="fas fa-sort ms-1"></i>
                                            </a>
                                        </th>
                                        <th>
                                            <a href="#" onclick="sortClassTable('disqualified'); return false;" class="text-decoration-none text-dark">
                                                Disqualified <i class="fas fa-sort ms-1"></i>
                                            </a>
                                        </th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($class_elections as $election): ?>
                                        <?php
                                        // Get total students in class
                                        $stmt = $pdo->prepare("
                                            SELECT COUNT(*) as total_students
                                            FROM users u
                                            JOIN roles r ON u.role_id = r.role_id
                                            WHERE u.class_id = ? AND r.role_name = 'Student'
                                        ");
                                        $stmt->execute([$election['class_id']]);
                                        $total_students = $stmt->fetchColumn();

                                        // Get disqualified students for this election
                                        $stmt = $pdo->prepare("
                                            SELECT COUNT(*) as disqualified_count
                                            FROM election_disqualifications
                                            WHERE election_id = ?
                                        ");
                                        $stmt->execute([$election['election_id']]);
                                        $disqualified_count = $stmt->fetchColumn();
                                        ?>
                                        <tr data-department="<?php echo htmlspecialchars($election['department_name']); ?>">
                                            <td data-sort="<?php echo htmlspecialchars($election['class_name']); ?>">
                                                <strong><?php echo htmlspecialchars($election['class_name']); ?></strong>
                                            </td>
                                            <td data-sort="<?php echo htmlspecialchars($election['department_name']); ?>"><?php echo htmlspecialchars($election['department_name']); ?></td>
                                            <td data-sort="<?php echo $election['voting_status']; ?>">
                                                <span class="badge bg-<?php 
                                                    echo $election['voting_status'] === 'ended' ? 'success' : 
                                                        ($election['voting_status'] === 'active' ? 'primary' : 'secondary'); 
                                                ?>">
                                                    <?php echo ucfirst(str_replace('_', ' ', $election['voting_status'])); ?>
                                                </span>
                                            </td>
                                            <td data-sort="<?php echo $total_students; ?>"><span class="badge bg-info"><?php echo $total_students; ?></span></td>
                                            <td data-sort="<?php echo $disqualified_count; ?>"><span class="badge bg-danger"><?php echo $disqualified_count; ?></span></td>
                                            <td>
                                                <a href="class_disqualifications.php?election_id=<?php echo $election['election_id']; ?>" 
                                                   class="btn btn-sm btn-outline-primary" title="Manage Disqualifications">
                                                    <i class="fas fa-user-slash me-1"></i>Manage
                                                </a>
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

</div>

<script>
// Sorting functionality for Class Elections table
let sortDirection = {};

function sortClassTable(column) {
    const table = document.getElementById('classElectionsTable');
    const tbody = table.querySelector('tbody');
    const rows = Array.from(tbody.querySelectorAll('tr'));
    
    // Toggle sort direction
    sortDirection[column] = sortDirection[column] === 'asc' ? 'desc' : 'asc';
    
    rows.sort((a, b) => {
        let aVal, bVal;
        
        switch(column) {
            case 'class':
                aVal = a.cells[0].getAttribute('data-sort') || a.cells[0].textContent.trim();
                bVal = b.cells[0].getAttribute('data-sort') || b.cells[0].textContent.trim();
                break;
            case 'department':
                aVal = a.cells[1].getAttribute('data-sort') || a.cells[1].textContent.trim();
                bVal = b.cells[1].getAttribute('data-sort') || b.cells[1].textContent.trim();
                break;
            case 'status':
                aVal = a.cells[2].getAttribute('data-sort') || a.cells[2].textContent.trim();
                bVal = b.cells[2].getAttribute('data-sort') || b.cells[2].textContent.trim();
                break;
            case 'total':
                aVal = parseInt(a.cells[3].getAttribute('data-sort')) || 0;
                bVal = parseInt(b.cells[3].getAttribute('data-sort')) || 0;
                break;
            case 'disqualified':
                aVal = parseInt(a.cells[4].getAttribute('data-sort')) || 0;
                bVal = parseInt(b.cells[4].getAttribute('data-sort')) || 0;
                break;
            default:
                return 0;
        }
        
        if (typeof aVal === 'string') {
            aVal = aVal.toLowerCase();
            bVal = bVal.toLowerCase();
        }
        
        if (sortDirection[column] === 'asc') {
            return aVal > bVal ? 1 : aVal < bVal ? -1 : 0;
        } else {
            return aVal < bVal ? 1 : aVal > bVal ? -1 : 0;
        }
    });
    
    // Clear tbody and append sorted rows
    tbody.innerHTML = '';
    rows.forEach(row => tbody.appendChild(row));
    
    // Update sort icons
    updateSortIcons(column);
}

function updateSortIcons(activeColumn) {
    const headers = document.querySelectorAll('#classElectionsTable th a');
    headers.forEach(header => {
        const icon = header.querySelector('i');
        if (icon) {
            icon.className = 'fas fa-sort ms-1';
        }
    });
    
    // Update active column icon
    const activeHeader = document.querySelector(`#classElectionsTable th a[onclick*="${activeColumn}"]`);
    if (activeHeader) {
        const icon = activeHeader.querySelector('i');
        if (icon) {
            icon.className = sortDirection[activeColumn] === 'asc' ? 'fas fa-sort-up ms-1' : 'fas fa-sort-down ms-1';
        }
    }
}

// Department filter functionality
document.addEventListener('DOMContentLoaded', function() {
    const departmentFilter = document.getElementById('departmentFilter');
    if (departmentFilter) {
        departmentFilter.addEventListener('change', function() {
            const selectedDept = this.value.toLowerCase();
            const rows = document.querySelectorAll('#classElectionsTable tbody tr');
            
            rows.forEach(row => {
                const dept = row.getAttribute('data-department').toLowerCase();
                if (selectedDept === '' || dept.includes(selectedDept)) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        });
    }
});
</script>

<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
