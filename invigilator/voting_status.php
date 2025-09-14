<?php
session_start();
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/config/database.php';

requireRole('Invigilator');

$page_title = 'Detailed Voting Status';
$current_user = getCurrentUser();

$election_id = (int)($_GET['election_id'] ?? 0);

if (!$election_id) {
    header('Location: dashboard.php');
    exit();
}

// Verify this election belongs to invigilator's assigned class
$stmt = $pdo->prepare("
    SELECT e.*, c.class_name, d.department_name
    FROM elections e
    JOIN classes c ON e.class_id = c.class_id
    JOIN departments d ON c.department_id = d.department_id
    JOIN invigilator_class_assignments ica ON c.class_id = ica.class_id
    WHERE e.election_id = ? AND ica.invigilator_id = ? AND ica.election_year = ?
");
$stmt->execute([$election_id, $current_user['user_id'], date('Y')]);
$election = $stmt->fetch();

if (!$election) {
    header('Location: dashboard.php');
    exit();
}

// Get all students in the class with their voting status
$stmt = $pdo->prepare("
    SELECT 
        u.user_id,
        u.fname,
        u.lname,
        u.roll_number,
        u.gender,
        u.email,
        CASE 
            WHEN girls_vote.vote_id IS NOT NULL THEN 'voted'
            ELSE 'not_voted'
        END as girls_voting_status,
        CASE 
            WHEN boys_vote.vote_id IS NOT NULL THEN 'voted'
            ELSE 'not_voted'
        END as boys_voting_status,
        CASE 
            WHEN girls_vote.vote_id IS NOT NULL OR boys_vote.vote_id IS NOT NULL THEN 'voted'
            ELSE 'not_voted'
        END as overall_status,
        CASE 
            WHEN ed.disqualification_id IS NOT NULL THEN 'disqualified'
            ELSE 'eligible'
        END as disqualification_status
    FROM users u
    JOIN roles r ON u.role_id = r.role_id
    LEFT JOIN votes girls_vote ON u.user_id = girls_vote.voter_id
        AND girls_vote.election_id = ?
        AND girls_vote.gender_category = 'girls'
    LEFT JOIN votes boys_vote ON u.user_id = boys_vote.voter_id
        AND boys_vote.election_id = ?
        AND boys_vote.gender_category = 'boys'
    LEFT JOIN election_disqualifications ed ON u.user_id = ed.student_id
        AND ed.election_id = ?
    WHERE u.class_id = ? AND r.role_name = 'Student'
    ORDER BY u.fname, u.lname
");
$stmt->execute([$election_id, $election_id, $election_id, $election['class_id']]);
$students = $stmt->fetchAll();

// Calculate statistics
$total_students = count($students);
$eligible_students_array = array_filter($students, fn($s) => $s['disqualification_status'] === 'eligible');
$voted_students = count(array_filter($eligible_students_array, fn($s) => $s['overall_status'] === 'voted'));
$eligible_students = count($eligible_students_array);
$not_voted_students = $eligible_students - $voted_students;
$disqualified_students = count(array_filter($students, fn($s) => $s['disqualification_status'] === 'disqualified'));
$voting_percentage = $eligible_students > 0 ? round(($voted_students / $eligible_students) * 100, 1) : 0;

// Gender-wise statistics
$girls_voted = count(array_filter($students, fn($s) => $s['girls_voting_status'] === 'voted'));
$boys_voted = count(array_filter($students, fn($s) => $s['boys_voting_status'] === 'voted'));

include dirname(__DIR__) . '/includes/header.php';
?>

<div class="row">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1>Detailed Voting Status</h1>
                <p class="text-muted mb-0">
                    <?php echo htmlspecialchars($election['class_name']); ?> - 
                    <?php echo htmlspecialchars($election['department_name']); ?> 
                    (Election Year: <?php echo $election['election_year']; ?>)
                </p>
            </div>
            <div>
                <button class="btn btn-outline-success me-2" onclick="refreshData()">
                    <i class="fas fa-sync-alt me-1"></i>Refresh
                </button>
                <a href="dashboard.php" class="btn btn-outline-secondary">
                    <i class="fas fa-arrow-left me-1"></i>Back to Dashboard
                </a>
            </div>
        </div>
    </div>
</div>

<!-- Statistics Cards -->
<div class="row mb-4">
    <div class="col">
        <div class="card bg-primary text-white">
            <div class="card-body text-center">
                <h3 class="mb-0"><?php echo $total_students; ?></h3>
                <small>Total Students</small>
            </div>
        </div>
    </div>
    <div class="col">
        <div class="card bg-success text-white">
            <div class="card-body text-center">
                <h3 class="mb-0"><?php echo $voted_students; ?></h3>
                <small>Voted</small>
            </div>
        </div>
    </div>
    <div class="col">
        <div class="card bg-warning text-white">
            <div class="card-body text-center">
                <h3 class="mb-0"><?php echo $not_voted_students; ?></h3>
                <small>Not Voted</small>
            </div>
        </div>
    </div>
    <div class="col">
        <div class="card bg-danger text-white">
            <div class="card-body text-center">
                <h3 class="mb-0"><?php echo $disqualified_students; ?></h3>
                <small>Disqualified</small>
            </div>
        </div>
    </div>
    <div class="col">
        <div class="card bg-info text-white">
            <div class="card-body text-center">
                <h3 class="mb-0"><?php echo $voting_percentage; ?>%</h3>
                <small>Turnout</small>
            </div>
        </div>
    </div>
</div>

<!-- Progress Bar -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <h6 class="mb-0">Overall Voting Progress</h6>
                    <span><?php echo $voted_students; ?>/<?php echo $eligible_students; ?> students</span>
                </div>
                <div class="progress" style="height: 25px;">
                    <div class="progress-bar bg-<?php echo $voting_percentage >= 75 ? 'success' : ($voting_percentage >= 50 ? 'warning' : 'danger'); ?>" 
                         role="progressbar" 
                         style="width: <?php echo $voting_percentage; ?>%"
                         aria-valuenow="<?php echo $voting_percentage; ?>" 
                         aria-valuemin="0" 
                         aria-valuemax="100">
                        <?php echo $voting_percentage; ?>%
                    </div>
                </div>
                
                <div class="row mt-3">
                    <div class="col-md-6">
                        <div class="d-flex align-items-center">
                            <i class="fas fa-female me-2" style="color: #e91e63;"></i>
                            <span>Girls Voted: <strong><?php echo $girls_voted; ?></strong></span>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="d-flex align-items-center">
                            <i class="fas fa-male me-2" style="color: #2196f3;"></i>
                            <span>Boys Voted: <strong><?php echo $boys_voted; ?></strong></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Filter and Search -->
<div class="row mb-3">
    <div class="col-md-6">
        <div class="input-group">
            <span class="input-group-text"><i class="fas fa-search"></i></span>
            <input type="text" class="form-control" id="searchStudent" placeholder="Search by name or roll number...">
        </div>
    </div>
    <div class="col-md-6">
        <select class="form-select" id="filterStatus">
            <option value="">All Students</option>
            <option value="voted">Voted</option>
            <option value="not_voted">Not Voted</option>
            <option value="partial">Partially Voted</option>
            <option value="disqualified">Disqualified</option>
        </select>
    </div>
</div>

<!-- Students List -->
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-users me-2"></i>Student Voting Status</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover" id="studentsTable">
                        <thead>
                            <tr>
                                <th>Student</th>
                                <th>Roll Number</th>
                                <th>Gender</th>
                                <th class="text-center">Girls Rep Vote</th>
                                <th class="text-center">Boys Rep Vote</th>
                                <th class="text-center">Overall Status</th>
                                <th class="text-center">Disqualification Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($students as $student): ?>
                                <tr class="student-row" 
                                    data-name="<?php echo strtolower($student['fname'] . ' ' . $student['lname']); ?>"
                                    data-roll="<?php echo strtolower($student['roll_number']); ?>"
                                    data-status="<?php echo $student['overall_status']; ?>"
                                    data-partial="<?php echo ($student['girls_voting_status'] !== $student['boys_voting_status']) ? 'partial' : 'complete'; ?>"
                                    data-disqualified="<?php echo $student['disqualification_status']; ?>">
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="rounded-circle d-flex align-items-center justify-content-center me-3" 
                                                 style="width: 40px; height: 40px; background-color: <?php echo $student['gender'] === 'F' ? '#e91e63' : '#2196f3'; ?>; color: white; font-weight: bold;">
                                                <?php echo strtoupper(substr($student['fname'], 0, 1) . substr($student['lname'], 0, 1)); ?>
                                            </div>
                                            <div>
                                                <strong><?php echo htmlspecialchars($student['fname'] . ' ' . $student['lname']); ?></strong>
                                                <br><small class="text-muted"><?php echo htmlspecialchars($student['email']); ?></small>
                                            </div>
                                        </div>
                                    </td>
                                    <td><?php echo htmlspecialchars($student['roll_number']); ?></td>
                                    <td>
                                        <span class="badge" style="background-color: <?php echo $student['gender'] === 'F' ? '#e91e63' : '#2196f3'; ?>; color: white;">
                                            <i class="fas fa-<?php echo $student['gender'] === 'F' ? 'female' : 'male'; ?> me-1"></i>
                                            <?php echo $student['gender'] === 'F' ? 'Female' : 'Male'; ?>
                                        </span>
                                    </td>
                                    <td class="text-center">
                                        <?php if ($student['girls_voting_status'] === 'voted'): ?>
                                            <span class="badge bg-success"><i class="fas fa-check"></i> Voted</span>
                                        <?php else: ?>
                                            <span class="badge bg-warning"><i class="fas fa-clock"></i> Not Voted</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center">
                                        <?php if ($student['boys_voting_status'] === 'voted'): ?>
                                            <span class="badge bg-success"><i class="fas fa-check"></i> Voted</span>
                                        <?php else: ?>
                                            <span class="badge bg-warning"><i class="fas fa-clock"></i> Not Voted</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center">
                                        <?php if ($student['overall_status'] === 'voted'): ?>
                                            <?php if ($student['girls_voting_status'] === 'voted' && $student['boys_voting_status'] === 'voted'): ?>
                                                <span class="badge bg-success"><i class="fas fa-check-double"></i> Complete</span>
                                            <?php else: ?>
                                                <span class="badge bg-info"><i class="fas fa-check"></i> Partial</span>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span class="badge bg-danger"><i class="fas fa-times"></i> Not Voted</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center">
                                        <?php if ($student['disqualification_status'] === 'disqualified'): ?>
                                            <span class="badge bg-danger"><i class="fas fa-ban"></i> Disqualified</span>
                                        <?php else: ?>
                                            <span class="badge bg-success"><i class="fas fa-check"></i> Eligible</span>
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

<script>
// Search functionality
document.getElementById('searchStudent').addEventListener('input', function() {
    const searchTerm = this.value.toLowerCase();
    const rows = document.querySelectorAll('.student-row');
    
    rows.forEach(row => {
        const name = row.dataset.name;
        const roll = row.dataset.roll;
        
        if (name.includes(searchTerm) || roll.includes(searchTerm)) {
            row.style.display = '';
        } else {
            row.style.display = 'none';
        }
    });
});

// Filter functionality
document.getElementById('filterStatus').addEventListener('change', function() {
    const filterValue = this.value;
    const rows = document.querySelectorAll('.student-row');
    
    rows.forEach(row => {
        const status = row.dataset.status;
        const partial = row.dataset.partial;
        const disqualified = row.dataset.disqualified;
        
        if (!filterValue || 
            (filterValue === 'voted' && status === 'voted') ||
            (filterValue === 'not_voted' && status === 'not_voted') ||
            (filterValue === 'partial' && status === 'voted' && partial === 'partial') ||
            (filterValue === 'disqualified' && disqualified === 'disqualified')) {
            row.style.display = '';
        } else {
            row.style.display = 'none';
        }
    });
});

// Refresh data
function refreshData() {
    location.reload();
}

// Auto-refresh every 30 seconds
setInterval(refreshData, 30000);
</script>

<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
