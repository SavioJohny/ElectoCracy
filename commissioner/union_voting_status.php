<?php
session_start();
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/config/database.php';

requireRole('Election Commissioner');

$page_title = 'Union Election Voting Status';
$current_user = getCurrentUser();

// Automatically get the current year's union election
$stmt = $pdo->prepare("
    SELECT e.election_id, e.election_year, et.election_type_name
    FROM elections e
    JOIN election_types et ON e.election_type_id = et.election_type_id
    WHERE e.election_year = ? AND et.election_type_name = 'union'
    ORDER BY e.election_year DESC
    LIMIT 1
");
$stmt->execute([date('Y')]);
$current_election = $stmt->fetch();

$election_id = $current_election ? $current_election['election_id'] : 0;
$election = null;
$students = [];

if ($election_id) {
    // Get union election details
    $stmt = $pdo->prepare("
        SELECT e.*, et.election_type_name
        FROM elections e
        JOIN election_types et ON e.election_type_id = et.election_type_id
        WHERE e.election_id = ? AND et.election_type_name = 'union'
    ");
    $stmt->execute([$election_id]);
    $election = $stmt->fetch();

    if ($election) {
        // Get union positions for this election
        $stmt = $pdo->prepare("
            SELECT * FROM positions
            WHERE election_type_id = 2
            ORDER BY voting_order
        ");
        $stmt->execute();
        $union_positions = $stmt->fetchAll();

        // Get union-eligible students (approved candidates in class elections) with their voting status for union election
        $stmt = $pdo->prepare("
            SELECT DISTINCT
                u.user_id,
                u.fname,
                u.lname,
                u.roll_number,
                u.gender,
                u.email,
                c.class_name,
                d.department_name,
                CASE 
                    WHEN union_vote.vote_id IS NOT NULL THEN 'voted'
                    ELSE 'not_voted'
                END as voting_status,
                CASE 
                    WHEN ed.disqualification_id IS NOT NULL THEN 'disqualified'
                    ELSE 'qualified'
                END as disqualification_status
            FROM users u
            JOIN roles r ON u.role_id = r.role_id
            JOIN candidates cand ON u.user_id = cand.user_id
            JOIN elections class_e ON cand.election_id = class_e.election_id
            JOIN election_types et ON class_e.election_type_id = et.election_type_id
            LEFT JOIN classes c ON u.class_id = c.class_id
            LEFT JOIN departments d ON c.department_id = d.department_id
            LEFT JOIN votes union_vote ON u.user_id = union_vote.voter_id
                AND union_vote.election_id = ?
            LEFT JOIN election_disqualifications ed ON u.user_id = ed.student_id
                AND ed.election_id = ?
            WHERE r.role_name = 'Student'
                AND et.election_type_name = 'class'
                AND class_e.election_year = ?
                AND cand.is_approved = 'approved'
            ORDER BY d.department_name, c.class_name, u.fname, u.lname
        ");
        $stmt->execute([$election_id, $election_id, $election['election_year']]);
        $students = $stmt->fetchAll();

        // Get position-specific voting data
        $position_voting_data = [];
        foreach ($union_positions as $position) {
            $stmt = $pdo->prepare("
                SELECT DISTINCT
                    u.user_id,
                    u.fname,
                    u.lname,
                    u.roll_number,
                    u.gender,
                    u.email,
                    c.class_name,
                    d.department_name,
                    CASE 
                        WHEN pos_vote.vote_id IS NOT NULL THEN 'voted'
                        ELSE 'not_voted'
                    END as voting_status,
                    CASE 
                        WHEN ed.disqualification_id IS NOT NULL THEN 'disqualified'
                        ELSE 'qualified'
                    END as disqualification_status
                FROM users u
                JOIN roles r ON u.role_id = r.role_id
                JOIN candidates cand ON u.user_id = cand.user_id
                JOIN elections class_e ON cand.election_id = class_e.election_id
                JOIN election_types et ON class_e.election_type_id = et.election_type_id
                LEFT JOIN classes c ON u.class_id = c.class_id
                LEFT JOIN departments d ON c.department_id = d.department_id
                LEFT JOIN votes pos_vote ON u.user_id = pos_vote.voter_id
                    AND pos_vote.election_id = ?
                    AND pos_vote.position_id = ?
                LEFT JOIN election_disqualifications ed ON u.user_id = ed.student_id
                    AND ed.election_id = ?
                WHERE r.role_name = 'Student'
                    AND et.election_type_name = 'class'
                    AND class_e.election_year = ?
                    AND cand.is_approved = 'approved'
                ORDER BY d.department_name, c.class_name, u.fname, u.lname
            ");
            $stmt->execute([$election_id, $position['position_id'], $election_id, $election['election_year']]);
            $position_voting_data[$position['position_id']] = [
                'position' => $position,
                'students' => $stmt->fetchAll()
            ];
        }
    }
}

// Calculate statistics (excluding disqualified students who haven't voted)
$eligible_students = array_filter($students, fn($s) => $s['disqualification_status'] === 'qualified' || $s['voting_status'] === 'voted');
$total_students = count($eligible_students);
$voted_students = count(array_filter($eligible_students, fn($s) => $s['voting_status'] === 'voted'));
$not_voted_students = $total_students - $voted_students;
$voting_percentage = $total_students > 0 ? round(($voted_students / $total_students) * 100, 1) : 0;

// Gender-wise statistics (excluding disqualified students who haven't voted)
$girls_voted = count(array_filter($eligible_students, fn($s) => $s['voting_status'] === 'voted' && $s['gender'] === 'F'));
$boys_voted = count(array_filter($eligible_students, fn($s) => $s['voting_status'] === 'voted' && $s['gender'] === 'M'));

include dirname(__DIR__) . '/includes/header.php';
?>

<?php if (!$election_id): ?>
<!-- No Union Election Available -->
<div class="row">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1>Union Election Voting Status</h1>
                <p class="text-muted mb-0">Monitor voting progress for union elections</p>
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
            <div class="card-body text-center py-5">
                <i class="fas fa-inbox fa-4x text-muted mb-3"></i>
                <h4>No Union Elections Available</h4>
                <p class="text-muted">There are no union elections for the current year.</p>
                <a href="union_elections.php" class="btn btn-primary">
                    <i class="fas fa-plus me-1"></i>Create Union Election
                </a>
            </div>
        </div>
    </div>
</div>

<?php else: ?>
<!-- Union Voting Status Display -->
<div class="row">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1>Union Election Voting Status</h1>
                <p class="text-muted mb-0">
                    Union Election - Election Year: <?php echo $election['election_year']; ?>
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



<!-- Filter and Search -->
<div class="row mb-3">
    <div class="col-md-4">
        <div class="input-group">
            <span class="input-group-text"><i class="fas fa-search"></i></span>
            <input type="text" class="form-control" id="searchStudent" placeholder="Search by name or roll number...">
        </div>
    </div>
    <div class="col-md-4">
        <select class="form-select" id="filterStatus">
            <option value="">All Students</option>
            <option value="voted">Voted</option>
            <option value="not_voted">Not Voted</option>
            <option value="disqualified">Disqualified</option>
        </select>
    </div>
    <div class="col-md-4">
        <select class="form-select" id="filterDepartment">
            <option value="">All Departments</option>
            <?php 
            $departments = array_unique(array_column($students, 'department_name'));
            sort($departments);
            foreach ($departments as $dept): ?>
                <option value="<?php echo htmlspecialchars($dept); ?>"><?php echo htmlspecialchars($dept); ?></option>
            <?php endforeach; ?>
        </select>
    </div>
</div>

<!-- Position-wise Student Voting Status -->
<?php if (!empty($position_voting_data)): ?>
    <?php foreach ($position_voting_data as $pos_id => $pos_data): ?>
        <div class="row mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <div class="d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">
                                <i class="fas fa-sitemap me-2"></i>
                                <?php echo htmlspecialchars($pos_data['position']['position_name']); ?> - Student Voting Status
                            </h5>
                            <div>
                                <?php 
                                // Calculate position statistics (excluding disqualified students who haven't voted for this position)
                                $pos_eligible = array_filter($pos_data['students'], fn($s) => $s['disqualification_status'] === 'qualified' || $s['voting_status'] === 'voted');
                                $pos_voted = count(array_filter($pos_eligible, fn($s) => $s['voting_status'] === 'voted'));
                                $pos_total = count($pos_eligible);
                                $pos_percentage = $pos_total > 0 ? round(($pos_voted / $pos_total) * 100, 1) : 0;
                                ?>
                                <span class="badge bg-info me-2"><?php echo $pos_total; ?> Total</span>
                                <span class="badge bg-success me-2"><?php echo $pos_voted; ?> Voted</span>
                                <span class="badge bg-primary"><?php echo $pos_percentage; ?>% Turnout</span>
                                <button class="btn btn-sm btn-outline-secondary ms-2" type="button" 
                                        data-bs-toggle="collapse" 
                                        data-bs-target="#position-<?php echo $pos_id; ?>" 
                                        aria-expanded="false">
                                    <i class="fas fa-chevron-down"></i> Toggle List
                                </button>
                            </div>
                        </div>
                    </div>
                    <div class="collapse" id="position-<?php echo $pos_id; ?>">
                        <div class="card-body">
                            <!-- Position-specific filters -->
                            <div class="row mb-3">
                                <div class="col-md-4">
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fas fa-search"></i></span>
                                        <input type="text" class="form-control position-search" 
                                               data-position="<?php echo $pos_id; ?>" 
                                               placeholder="Search by name or roll number...">
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <select class="form-select position-status-filter" data-position="<?php echo $pos_id; ?>">
                                        <option value="">All Students</option>
                                        <option value="voted">Voted</option>
                                        <option value="not_voted">Not Voted</option>
                                        <option value="disqualified">Disqualified</option>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <select class="form-select position-department-filter" data-position="<?php echo $pos_id; ?>">
                                        <option value="">All Departments</option>
                                        <?php 
                                        $pos_departments = array_unique(array_column($pos_data['students'], 'department_name'));
                                        sort($pos_departments);
                                        foreach ($pos_departments as $dept): ?>
                                            <option value="<?php echo htmlspecialchars($dept); ?>"><?php echo htmlspecialchars($dept); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>

                            <div class="table-responsive">
                                <table class="table table-hover position-table" data-position="<?php echo $pos_id; ?>">
                                    <thead>
                                        <tr>
                                            <th>Student</th>
                                            <th>Roll Number</th>
                                            <th>Class</th>
                                            <th>Department</th>
                                            <th>Gender</th>
                                            <th class="text-center">Voting Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($pos_data['students'] as $student): ?>
                                            <tr class="student-row position-<?php echo $pos_id; ?>-row" 
                                                data-name="<?php echo strtolower($student['fname'] . ' ' . $student['lname']); ?>"
                                                data-roll="<?php echo strtolower($student['roll_number']); ?>"
                                                data-status="<?php echo $student['voting_status']; ?>"
                                                data-department="<?php echo htmlspecialchars($student['department_name']); ?>"
                                                data-disqualified="<?php echo $student['disqualification_status']; ?>">
                                                <td>
                                                    <div class="d-flex align-items-center">
                                                        <div class="rounded-circle d-flex align-items-center justify-content-center me-3" 
                                                             style="width: 40px; height: 40px; background-color: <?php echo $student['gender'] === 'F' ? '#e91e63' : '#2196f3'; ?>; color: white; font-weight: bold;">
                                                            <?php echo strtoupper(substr($student['fname'], 0, 1) . substr($student['lname'], 0, 1)); ?>
                                                        </div>
                                                        <div>
                                                            <strong><?php echo htmlspecialchars($student['fname'] . ' ' . $student['lname']); ?></strong>
                                                            <?php if ($student['disqualification_status'] === 'disqualified' && $student['voting_status'] !== 'voted'): ?>
                                                                <span class="badge bg-warning text-dark ms-2"><i class="fas fa-ban"></i> Disqualified</span>
                                                            <?php endif; ?>
                                                            <br><small class="text-muted"><?php echo htmlspecialchars($student['email']); ?></small>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td><?php echo htmlspecialchars($student['roll_number']); ?></td>
                                                <td><?php echo htmlspecialchars($student['class_name']); ?></td>
                                                <td><?php echo htmlspecialchars($student['department_name']); ?></td>
                                                <td>
                                                    <span class="badge" style="background-color: <?php echo $student['gender'] === 'F' ? '#e91e63' : '#2196f3'; ?>; color: white;">
                                                        <i class="fas fa-<?php echo $student['gender'] === 'F' ? 'female' : 'male'; ?> me-1"></i>
                                                        <?php echo $student['gender'] === 'F' ? 'Female' : 'Male'; ?>
                                                    </span>
                                                </td>
                                                <td class="text-center">
                                                    <?php if ($student['voting_status'] === 'voted'): ?>
                                                        <span class="badge bg-success"><i class="fas fa-check"></i> Voted</span>
                                                    <?php elseif ($student['disqualification_status'] === 'disqualified'): ?>
                                                        <span class="badge bg-warning text-dark"><i class="fas fa-ban"></i> Disqualified</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-danger"><i class="fas fa-times"></i> Not Voted</span>
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
        </div>
    <?php endforeach; ?>
<?php else: ?>
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body text-center py-5">
                    <i class="fas fa-users fa-3x text-muted mb-3"></i>
                    <h5 class="text-muted">No Union Positions Available</h5>
                    <p class="text-muted">No union positions have been configured for this election.</p>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>

<script>
// Position-specific filtering functionality
document.addEventListener('DOMContentLoaded', function() {
    // Handle position-specific search
    document.querySelectorAll('.position-search').forEach(searchInput => {
        searchInput.addEventListener('input', function() {
            const positionId = this.dataset.position;
            filterPositionStudents(positionId);
        });
    });

    // Handle position-specific status filter
    document.querySelectorAll('.position-status-filter').forEach(statusFilter => {
        statusFilter.addEventListener('change', function() {
            const positionId = this.dataset.position;
            filterPositionStudents(positionId);
        });
    });

    // Handle position-specific department filter
    document.querySelectorAll('.position-department-filter').forEach(deptFilter => {
        deptFilter.addEventListener('change', function() {
            const positionId = this.dataset.position;
            filterPositionStudents(positionId);
        });
    });
});

function filterPositionStudents(positionId) {
    const searchInput = document.querySelector(`.position-search[data-position="${positionId}"]`);
    const statusFilter = document.querySelector(`.position-status-filter[data-position="${positionId}"]`);
    const deptFilter = document.querySelector(`.position-department-filter[data-position="${positionId}"]`);
    
    const searchTerm = searchInput ? searchInput.value.toLowerCase() : '';
    const statusValue = statusFilter ? statusFilter.value : '';
    const deptValue = deptFilter ? deptFilter.value : '';
    
    const rows = document.querySelectorAll(`.position-${positionId}-row`);
    
    rows.forEach(row => {
        const name = row.dataset.name;
        const roll = row.dataset.roll;
        const status = row.dataset.status;
        const department = row.dataset.department;
        const disqualified = row.dataset.disqualified;
        
        const nameMatch = !searchTerm || name.includes(searchTerm) || roll.includes(searchTerm);
        let statusMatch = true;
        if (statusValue) {
            if (statusValue === 'disqualified') {
                statusMatch = disqualified === 'disqualified' && status !== 'voted';
            } else {
                statusMatch = status === statusValue;
            }
        }
        const departmentMatch = !deptValue || department === deptValue;
        
        if (nameMatch && statusMatch && departmentMatch) {
            row.style.display = '';
        } else {
            row.style.display = 'none';
        }
    });
}

// Global search functionality (for overall filters)
const searchStudent = document.getElementById('searchStudent');
if (searchStudent) {
    searchStudent.addEventListener('input', function() {
        filterStudents();
    });
}

const filterStatus = document.getElementById('filterStatus');
if (filterStatus) {
    filterStatus.addEventListener('change', function() {
        filterStudents();
    });
}

const filterDepartment = document.getElementById('filterDepartment');
if (filterDepartment) {
    filterDepartment.addEventListener('change', function() {
        filterStudents();
    });
}

function filterStudents() {
    const searchTerm = searchStudent ? searchStudent.value.toLowerCase() : '';
    const statusFilter = filterStatus ? filterStatus.value : '';
    const departmentFilter = filterDepartment ? filterDepartment.value : '';
    const rows = document.querySelectorAll('.student-row');
    
    rows.forEach(row => {
        const name = row.dataset.name;
        const roll = row.dataset.roll;
        const status = row.dataset.status;
        const department = row.dataset.department;
        const disqualified = row.dataset.disqualified;
        
        const nameMatch = !searchTerm || name.includes(searchTerm) || roll.includes(searchTerm);
        let statusMatch = true;
        if (statusFilter) {
            if (statusFilter === 'disqualified') {
                statusMatch = disqualified === 'disqualified' && status !== 'voted';
            } else {
                statusMatch = status === statusFilter;
            }
        }
        const departmentMatch = !departmentFilter || department === departmentFilter;
        
        if (nameMatch && statusMatch && departmentMatch) {
            row.style.display = '';
        } else {
            row.style.display = 'none';
        }
    });
}

// Refresh data
function refreshData() {
    location.reload();
}

// Auto-refresh every 30 seconds
setInterval(refreshData, 30000);
</script>

<?php endif; ?>

<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
