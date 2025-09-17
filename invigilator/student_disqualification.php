<?php
session_start();
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/config/database.php';

requireRole('Invigilator');

$page_title = 'Student Disqualification Management';
$current_user = getCurrentUser();

// Get assigned classes for current year
$current_year = date('Y');
$stmt = $pdo->prepare("
    SELECT ica.*, c.class_name, d.department_name
    FROM invigilator_class_assignments ica
    JOIN classes c ON ica.class_id = c.class_id
    JOIN departments d ON c.department_id = d.department_id
    WHERE ica.invigilator_id = ? AND ica.election_year = ?
");
$stmt->execute([$current_user['user_id'], $current_year]);
$assigned_classes = $stmt->fetchAll();

if (empty($assigned_classes)) {
    header('Location: dashboard.php?error=no_classes');
    exit;
}

$class_ids = array_column($assigned_classes, 'class_id');
$placeholders = str_repeat('?,', count($class_ids) - 1) . '?';

// Check if voting_status column exists
$stmt = $pdo->query("SHOW COLUMNS FROM elections LIKE 'voting_status'");
$voting_column_exists = $stmt->fetch();

// Get elections for assigned classes
$select_fields = "e.*, c.class_name, d.department_name, et.election_type_name";
if ($voting_column_exists) {
    $select_fields .= ", e.voting_status";
}

$stmt = $pdo->prepare("
    SELECT {$select_fields}
    FROM elections e
    JOIN election_types et ON e.election_type_id = et.election_type_id
    JOIN classes c ON e.class_id = c.class_id
    JOIN departments d ON c.department_id = d.department_id
    WHERE e.class_id IN ($placeholders)
    AND et.election_type_name = 'class'
    AND e.is_active = 1
    ORDER BY e.election_year DESC, c.class_name
");
$stmt->execute($class_ids);
$elections = $stmt->fetchAll();

// Use the first available election automatically
$selected_election = $elections[0] ?? null;
$selected_election_id = $selected_election['election_id'] ?? null;

// Get students and disqualifications for selected election
$students = [];
$disqualified_students = [];

if ($selected_election) {
    // Get all students in the class
    $stmt = $pdo->prepare("
        SELECT u.user_id, u.fname, u.lname, u.roll_number, u.email, u.gender
        FROM users u
        JOIN roles r ON u.role_id = r.role_id
        WHERE u.class_id = ? AND r.role_name = 'Student'
        ORDER BY u.roll_number, u.fname, u.lname
    ");
    $stmt->execute([$selected_election['class_id']]);
    $students = $stmt->fetchAll();
    
    // Get disqualified students
    $stmt = $pdo->prepare("
        SELECT student_id
        FROM election_disqualifications
        WHERE election_id = ?
    ");
    $stmt->execute([$selected_election_id]);
    $disqualified_student_ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
    $disqualified_students = array_flip($disqualified_student_ids);
}

include dirname(__DIR__) . '/includes/header.php';
?>

<div class="container-fluid mt-4">
    <?php if (isset($_GET['success'])): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <i class="fas fa-check-circle me-2"></i><?php echo htmlspecialchars($_GET['success']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    
    <?php if (isset($_GET['error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <i class="fas fa-exclamation-triangle me-2"></i><?php echo htmlspecialchars($_GET['error']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h2><i class="fas fa-user-slash me-2"></i>Student Disqualification Management</h2>
                    <p class="text-muted mb-0">Manage student disqualifications for elections due to absence or other issues</p>
                </div>
                <a href="dashboard.php" class="btn btn-outline-secondary">
                    <i class="fas fa-arrow-left me-1"></i>Back to Dashboard
                </a>
            </div>
        </div>
    </div>

    <?php if (empty($elections)): ?>
        <div class="row mb-4">
            <div class="col-12">
                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-2"></i>
                    No active class elections found for your assigned classes.
                </div>
            </div>
        </div>
    <?php endif; ?>

    <?php if ($selected_election): ?>
        <?php 
        $voting_status = $selected_election['voting_status'] ?? 'not_started';
        $can_modify = $voting_status === 'not_started';
        ?>
        
        <!-- Students List -->
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <div class="d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">
                                <i class="fas fa-users me-2"></i>
                                Students - <?php echo htmlspecialchars($selected_election['class_name']); ?>
                            </h5>
                            <div class="d-flex align-items-center gap-3">
                                <div class="d-flex align-items-center">
                                    <label for="statusFilter" class="form-label me-2 mb-0">Filter by Status:</label>
                                    <select id="statusFilter" class="form-select form-select-sm" style="width: auto;">
                                        <option value="all">All Students</option>
                                        <option value="qualified">Qualified Only</option>
                                        <option value="disqualified">Disqualified Only</option>
                                    </select>
                                </div>
                                <?php if (!$can_modify): ?>
                                    <span class="badge bg-warning">
                                        <i class="fas fa-lock me-1"></i>Modifications Locked
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <div class="card-body">
                        <?php if (!$can_modify): ?>
                            <div class="alert alert-warning">
                                <i class="fas fa-exclamation-triangle me-2"></i>
                                <strong>Disqualifications are locked.</strong> 
                                Students can only be disqualified or requalified when the voting status is "Not Started". 
                                Current status: <strong><?php echo ucfirst(str_replace('_', ' ', $voting_status)); ?></strong>
                            </div>
                        <?php endif; ?>
                        
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Roll Number</th>
                                        <th>Student Name</th>
                                        <th>Gender</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody id="studentsTableBody">
                                    <?php foreach ($students as $student): ?>
                                        <?php $is_disqualified = isset($disqualified_students[$student['user_id']]); ?>
                                        <tr class="student-row" data-status="<?php echo $is_disqualified ? 'disqualified' : 'qualified'; ?>">
                                            <td><?php echo htmlspecialchars($student['roll_number']); ?></td>
                                            <td>
                                                <strong><?php echo htmlspecialchars($student['fname'] . ' ' . $student['lname']); ?></strong>
                                                <br><small class="text-muted"><?php echo htmlspecialchars($student['email']); ?></small>
                                            </td>
                                            <td>
                                                <span class="badge bg-<?php echo $student['gender'] === 'F' ? 'pink' : 'blue'; ?>">
                                                    <?php echo $student['gender'] === 'F' ? 'Female' : 'Male'; ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php if ($is_disqualified): ?>
                                                    <span class="badge bg-danger">
                                                        <i class="fas fa-user-slash me-1"></i>Disqualified
                                                    </span>
                                                <?php else: ?>
                                                    <span class="badge bg-success">
                                                        <i class="fas fa-user-check me-1"></i>Qualified
                                                    </span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($can_modify): ?>
                                                    <?php if ($is_disqualified): ?>
                                                        <form method="POST" action="student_disqualification_handler.php" style="display: inline;">
                                                            <input type="hidden" name="action" value="requalify">
                                                            <input type="hidden" name="student_id" value="<?php echo $student['user_id']; ?>">
                                                            <input type="hidden" name="election_id" value="<?php echo $selected_election_id; ?>">
                                                            <button type="submit" class="btn btn-sm btn-success"
                                                                    onclick="return confirm('Requalify <?php echo htmlspecialchars($student['fname'] . ' ' . $student['lname']); ?>?')">
                                                                <i class="fas fa-user-check me-1"></i>Requalify
                                                            </button>
                                                        </form>
                                                    <?php else: ?>
                                                        <form method="POST" action="student_disqualification_handler.php" style="display: inline;">
                                                            <input type="hidden" name="action" value="disqualify">
                                                            <input type="hidden" name="student_id" value="<?php echo $student['user_id']; ?>">
                                                            <input type="hidden" name="election_id" value="<?php echo $selected_election_id; ?>">
                                                            <button type="submit" class="btn btn-sm btn-danger"
                                                                    onclick="return confirm('Disqualify <?php echo htmlspecialchars($student['fname'] . ' ' . $student['lname']); ?>?')">
                                                                <i class="fas fa-user-slash me-1"></i>Disqualify
                                                            </button>
                                                        </form>
                                                    <?php endif; ?>
                                                <?php else: ?>
                                                    <span class="text-muted">
                                                        <i class="fas fa-lock me-1"></i>Locked
                                                    </span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>



<style>
.bg-pink {
    background-color: #e91e63 !important;
}

.bg-blue {
    background-color: #2196f3 !important;
}

.alert-sm {
    padding: 0.25rem 0.5rem;
    font-size: 0.75rem;
}
</style>



<script>
document.addEventListener('DOMContentLoaded', function() {
    const statusFilter = document.getElementById('statusFilter');
    const studentRows = document.querySelectorAll('.student-row');
    
    // Auto-filter function
    function filterStudents() {
        const selectedStatus = statusFilter.value;
        
        studentRows.forEach(row => {
            const rowStatus = row.getAttribute('data-status');
            
            if (selectedStatus === 'all') {
                row.style.display = '';
            } else if (selectedStatus === rowStatus) {
                row.style.display = '';
            } else {
                row.style.display = 'none';
            }
        });
        
        // Update visible count
        updateVisibleCount();
    }
    
    // Update visible student count
    function updateVisibleCount() {
        const visibleRows = document.querySelectorAll('.student-row[style=""], .student-row:not([style])');
        const totalRows = studentRows.length;
        
        // Create or update count display
        let countDisplay = document.getElementById('studentCount');
        if (!countDisplay) {
            countDisplay = document.createElement('small');
            countDisplay.id = 'studentCount';
            countDisplay.className = 'text-muted ms-2';
            const header = document.querySelector('.card-header h5');
            header.appendChild(countDisplay);
        }
        
        const visibleCount = visibleRows.length;
        if (statusFilter.value === 'all') {
            countDisplay.textContent = `(${totalRows} students)`;
        } else {
            countDisplay.textContent = `(${visibleCount} of ${totalRows} students)`;
        }
    }
    
    // Add event listener for auto-filtering
    statusFilter.addEventListener('change', filterStudents);
    
    // Initialize count display
    updateVisibleCount();
});
</script>

<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
