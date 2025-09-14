<?php
session_start();
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/config/database.php';

requireRole('Election Commissioner');

$page_title = 'Class Election Disqualifications';
$election_id = (int)($_GET['election_id'] ?? 0);

if (!$election_id) {
    header('Location: disqualifications.php');
    exit;
}

// Get class election details
$stmt = $pdo->prepare("
    SELECT e.*, et.election_type_name, c.class_name, d.department_name
    FROM elections e
    JOIN election_types et ON e.election_type_id = et.election_type_id
    LEFT JOIN classes c ON e.class_id = c.class_id
    LEFT JOIN departments d ON c.department_id = d.department_id
    WHERE e.election_id = ? AND et.election_type_name = 'class'
");
$stmt->execute([$election_id]);
$class_election = $stmt->fetch();

if (!$class_election) {
    header('Location: disqualifications.php');
    exit;
}

// Get all students in this class
$stmt = $pdo->prepare("
    SELECT u.user_id, u.fname, u.lname, u.roll_number, u.email, u.gender
    FROM users u
    JOIN roles r ON u.role_id = r.role_id
    WHERE u.class_id = ? AND r.role_name = 'Student'
    ORDER BY u.roll_number, u.fname, u.lname
");
$stmt->execute([$class_election['class_id']]);
$class_students = $stmt->fetchAll();

// Get disqualified students for this class election
$stmt = $pdo->prepare("
    SELECT student_id
    FROM election_disqualifications
    WHERE election_id = ?
");
$stmt->execute([$class_election['election_id']]);
$disqualified_student_ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
$disqualified_students = array_flip($disqualified_student_ids);

$voting_status = $class_election['voting_status'];
$can_modify = $voting_status === 'not_started';

include dirname(__DIR__) . '/includes/header.php';
?>

<div class="container-fluid">
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h2><i class="fas fa-users me-2"></i>Class Election Disqualifications - <?php echo htmlspecialchars($class_election['class_name']); ?></h2>
                    <p class="text-muted"><?php echo htmlspecialchars($class_election['department_name']); ?> - <?php echo $class_election['election_year']; ?></p>
                </div>
                <div>
                    <a href="disqualifications.php" class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-left me-1"></i>Back to Disqualifications
                    </a>
                </div>
            </div>
        </div>
    </div>


    <?php if (!$can_modify): ?>
        <div class="row mb-4">
            <div class="col-12">
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <strong>Disqualifications are locked.</strong> 
                    Students can only be disqualified or requalified when the voting status is "Not Started". 
                    Current status: <strong><?php echo ucfirst(str_replace('_', ' ', $voting_status)); ?></strong>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Students List -->
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">
                            <i class="fas fa-users me-2"></i>Class Students
                        </h5>
                        <div>
                            <label for="statusFilter" class="form-label me-2 mb-0">Filter by Status:</label>
                            <select id="statusFilter" class="form-select form-select-sm" style="width: auto; display: inline-block;">
                                <option value="all">All Students</option>
                                <option value="eligible">Eligible Only</option>
                                <option value="disqualified">Disqualified Only</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <?php if (empty($class_students)): ?>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>
                            No students found in this class.
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Roll Number</th>
                                        <th>Student Name</th>
                                        <th>Email</th>
                                        <th>Gender</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($class_students as $student): ?>
                                        <?php $is_disqualified = isset($disqualified_students[$student['user_id']]); ?>
                                        <tr class="student-row <?php echo $is_disqualified ? 'table-danger disqualified' : 'eligible'; ?>" data-status="<?php echo $is_disqualified ? 'disqualified' : 'eligible'; ?>">
                                            <td><?php echo htmlspecialchars($student['roll_number']); ?></td>
                                            <td>
                                                <strong><?php echo htmlspecialchars($student['fname'] . ' ' . $student['lname']); ?></strong>
                                            </td>
                                            <td><?php echo htmlspecialchars($student['email']); ?></td>
                                            <td>
                                                <?php if ($student['gender'] === 'M'): ?>
                                                    <span class="badge bg-primary">
                                                        <i class="fas fa-male me-1"></i>
                                                        Male
                                                    </span>
                                                <?php else: ?>
                                                    <span class="badge" style="background-color: #e91e63; color: white;">
                                                        <i class="fas fa-female me-1"></i>
                                                        Female
                                                    </span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($is_disqualified): ?>
                                                    <span class="badge bg-danger">
                                                        <i class="fas fa-user-slash me-1"></i>Disqualified
                                                    </span>
                                                <?php else: ?>
                                                    <span class="badge bg-success">
                                                        <i class="fas fa-user-check me-1"></i>Eligible
                                                    </span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($can_modify): ?>
                                                    <?php if ($is_disqualified): ?>
                                                        <button class="btn btn-sm btn-success requalify-btn"
                                                                data-student-id="<?php echo $student['user_id']; ?>"
                                                                data-student-name="<?php echo htmlspecialchars($student['fname'] . ' ' . $student['lname']); ?>"
                                                                data-election-id="<?php echo $class_election['election_id']; ?>">
                                                            <i class="fas fa-user-check me-1"></i>Requalify
                                                        </button>
                                                    <?php else: ?>
                                                        <button class="btn btn-sm btn-danger disqualify-btn"
                                                                data-student-id="<?php echo $student['user_id']; ?>"
                                                                data-student-name="<?php echo htmlspecialchars($student['fname'] . ' ' . $student['lname']); ?>"
                                                                data-election-id="<?php echo $class_election['election_id']; ?>">
                                                            <i class="fas fa-user-slash me-1"></i>Disqualify
                                                        </button>
                                                    <?php endif; ?>
                                                <?php else: ?>
                                                    <button class="btn btn-sm btn-outline-secondary" disabled>
                                                        <i class="fas fa-lock me-1"></i>Locked
                                                    </button>
                                                <?php endif; ?>
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
document.addEventListener('DOMContentLoaded', function() {
    // Status filter functionality
    const statusFilter = document.getElementById('statusFilter');
    const studentRows = document.querySelectorAll('.student-row');
    
    statusFilter.addEventListener('change', function() {
        const filterValue = this.value;
        
        studentRows.forEach(row => {
            const studentStatus = row.getAttribute('data-status');
            
            if (filterValue === 'all') {
                row.style.display = '';
            } else if (filterValue === studentStatus) {
                row.style.display = '';
            } else {
                row.style.display = 'none';
            }
        });
    });

    // Disqualify student
    document.querySelectorAll('.disqualify-btn').forEach(button => {
        button.addEventListener('click', function() {
            const studentId = this.dataset.studentId;
            const studentName = this.dataset.studentName;
            const electionId = this.dataset.electionId;
            
            if (confirm(`Are you sure you want to disqualify ${studentName} from the class election?\n\nThis will prevent them from voting in the class election.`)) {
                handleDisqualification('disqualify', studentId, electionId, studentName);
            }
        });
    });
    
    // Requalify student
    document.querySelectorAll('.requalify-btn').forEach(button => {
        button.addEventListener('click', function() {
            const studentId = this.dataset.studentId;
            const studentName = this.dataset.studentName;
            const electionId = this.dataset.electionId;
            
            if (confirm(`Are you sure you want to requalify ${studentName} for the class election?\n\nThis will allow them to vote in the class election again.`)) {
                handleDisqualification('requalify', studentId, electionId, studentName);
            }
        });
    });
});

function handleDisqualification(action, studentId, electionId, studentName) {
    const formData = new FormData();
    formData.append('action', action);
    formData.append('student_id', studentId);
    formData.append('election_id', electionId);
    
    fetch('class_disqualification_handler.php', {
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
        }
    })
    .catch(error => {
        showAlert('Network error. Please try again.', 'danger');
    });
}

function showAlert(message, type) {
    // Create alert element
    const alertDiv = document.createElement('div');
    alertDiv.className = `alert alert-${type} alert-dismissible fade show`;
    alertDiv.innerHTML = `
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;
    
    // Insert at top of container
    const container = document.querySelector('.container-fluid');
    container.insertBefore(alertDiv, container.firstChild);
    
    // Auto dismiss after 5 seconds
    setTimeout(() => {
        if (alertDiv.parentNode) {
            alertDiv.remove();
        }
    }, 5000);
}
</script>

<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
