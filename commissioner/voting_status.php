<?php
session_start();
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/config/database.php';

requireRole('Election Commissioner');

$page_title = 'Detailed Voting Status';
$current_user = getCurrentUser();

$election_id = (int)($_GET['election_id'] ?? 0);

// Get all available elections for class selection
$stmt = $pdo->prepare("
    SELECT e.election_id, e.election_year, c.class_name, d.department_name, c.class_id
    FROM elections e
    JOIN classes c ON e.class_id = c.class_id
    JOIN departments d ON c.department_id = d.department_id
    WHERE e.election_year = ?
    ORDER BY d.department_name, c.class_name
");
$stmt->execute([date('Y')]);
$available_elections = $stmt->fetchAll();

$election = null;
$students = [];

if ($election_id) {
    // Get election details (no restriction for commissioner)
    $stmt = $pdo->prepare("
        SELECT e.*, c.class_name, d.department_name
        FROM elections e
        JOIN classes c ON e.class_id = c.class_id
        JOIN departments d ON c.department_id = d.department_id
        WHERE e.election_id = ?
    ");
    $stmt->execute([$election_id]);
    $election = $stmt->fetch();

    if ($election) {
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
    }
}

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

<?php if (!$election_id): ?>
<!-- Class Selection Screen -->
<div class="row">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1>Detailed Voting Status</h1>
                <p class="text-muted mb-0">Select a class election to monitor voting progress</p>
            </div>
            <div>
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
                <h5 class="mb-0"><i class="fas fa-vote-yea me-2"></i>Select Class Election</h5>
            </div>
            <div class="card-body">
                <?php if (empty($available_elections)): ?>
                    <div class="text-center py-5">
                        <i class="fas fa-inbox fa-4x text-muted mb-3"></i>
                        <h4>No Elections Available</h4>
                        <p class="text-muted">There are no active elections for the current year.</p>
                    </div>
                <?php else: ?>
                    <!-- Filter Controls -->
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <label for="departmentFilter" class="form-label">Filter by Department</label>
                            <select class="form-select" id="departmentFilter">
                                <option value="">All Departments</option>
                                <?php 
                                $departments = array_unique(array_column($available_elections, 'department_name'));
                                sort($departments);
                                foreach ($departments as $dept): ?>
                                    <option value="<?php echo htmlspecialchars($dept); ?>"><?php echo htmlspecialchars($dept); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <!-- Elections Grid -->
                    <div class="row" id="electionsGrid">
                        <?php foreach ($available_elections as $avail_election): ?>
                            <div class="col-md-6 col-lg-4 mb-3 election-card" 
                                 data-department="<?php echo htmlspecialchars($avail_election['department_name']); ?>"
                                 data-class="<?php echo htmlspecialchars(strtolower($avail_election['class_name'])); ?>">
                                <div class="card h-100 border-primary">
                                    <div class="card-body">
                                        <h6 class="card-title"><?php echo htmlspecialchars($avail_election['class_name']); ?></h6>
                                        <p class="card-text text-muted small">
                                            <?php echo htmlspecialchars($avail_election['department_name']); ?><br>
                                            Election Year: <?php echo $avail_election['election_year']; ?>
                                        </p>
                                        <a href="?election_id=<?php echo $avail_election['election_id']; ?>" 
                                           class="btn btn-primary btn-sm">
                                            <i class="fas fa-eye me-1"></i>Monitor Voting
                                        </a>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <!-- No Results Message -->
                    <div id="noResults" class="text-center py-5" style="display: none;">
                        <i class="fas fa-search fa-4x text-muted mb-3"></i>
                        <h4>No Elections Found</h4>
                        <p class="text-muted">No elections match your current filter criteria.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php else: ?>
<!-- Voting Status Display (Carbon Copy of Invigilator Page) -->
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
                <a href="voting_status.php" class="btn btn-outline-secondary me-2">
                    <i class="fas fa-list me-1"></i>Select Different Class
                </a>
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
// Auto-filtering functionality for class selection screen
function filterElections() {
    const departmentFilter = document.getElementById('departmentFilter');
    
    if (!departmentFilter) return; // Only run on class selection screen
    
    const selectedDepartment = departmentFilter.value.toLowerCase();
    const electionCards = document.querySelectorAll('.election-card');
    const noResults = document.getElementById('noResults');
    
    let visibleCount = 0;
    
    electionCards.forEach(card => {
        const cardDepartment = card.dataset.department.toLowerCase();
        
        const departmentMatch = !selectedDepartment || cardDepartment === selectedDepartment;
        
        if (departmentMatch) {
            card.style.display = '';
            visibleCount++;
        } else {
            card.style.display = 'none';
        }
    });
    
    // Show/hide no results message
    if (visibleCount === 0) {
        noResults.style.display = 'block';
    } else {
        noResults.style.display = 'none';
    }
}

// Add event listeners for auto-filtering
document.addEventListener('DOMContentLoaded', function() {
    const departmentFilter = document.getElementById('departmentFilter');
    
    if (departmentFilter) {
        // Auto-filter on department selection change
        departmentFilter.addEventListener('change', filterElections);
    }
});

// Search functionality for student list (only on voting status screen)
const searchStudent = document.getElementById('searchStudent');
if (searchStudent) {
    searchStudent.addEventListener('input', function() {
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
}

// Filter functionality for student list (only on voting status screen)
const filterStatus = document.getElementById('filterStatus');
if (filterStatus) {
    filterStatus.addEventListener('change', function() {
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
}

// Refresh data
function refreshData() {
    location.reload();
}

// Auto-refresh every 30 seconds (only on voting status screen)
<?php if ($election_id): ?>
setInterval(refreshData, 30000);
<?php endif; ?>
</script>

<?php endif; ?>

<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
