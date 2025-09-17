<?php
session_start();
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/config/database.php';

requireRole('Election Commissioner');

$page_title = 'Manage Students - Union Election Candidates';

// Get current year
$current_year = date('Y');

// Get class election winners (eligible candidates for union elections)
$class_winners = [];

// First, get completed elections with votes
$stmt = $pdo->prepare("
    SELECT DISTINCT e.election_id, e.class_id
    FROM elections e
    JOIN votes v ON e.election_id = v.election_id
    WHERE e.election_year = ?
    AND e.election_type_id = 1
    AND e.voting_status = 'ended'
");
$stmt->execute([$current_year]);
$completed_elections = $stmt->fetchAll();

// For each completed election, find the winners
foreach ($completed_elections as $election) {
    foreach (['girls', 'boys'] as $gender_category) {
        $stmt = $pdo->prepare("
            SELECT
                cand.candidate_id,
                u.user_id,
                u.fname,
                u.lname,
                u.email,
                u.gender,
                c.class_name,
                d.department_name,
                d.department_id,
                c.class_id,
                'Class Representative' as won_position,
                ? as election_id,
                ? as gender_category,
                COUNT(v.vote_id) as vote_count,
                'winner' as status
            FROM candidates cand
            JOIN users u ON cand.user_id = u.user_id
            JOIN elections e ON cand.election_id = e.election_id
            JOIN classes c ON e.class_id = c.class_id
            JOIN departments d ON c.department_id = d.department_id
            LEFT JOIN votes v ON cand.candidate_id = v.candidate_id AND v.gender_category = ?
            WHERE cand.election_id = ?
            AND cand.is_approved = 'approved'
            AND u.gender = ?
            GROUP BY cand.candidate_id
            HAVING COUNT(v.vote_id) = (
                SELECT MAX(vote_count)
                FROM (
                    SELECT COUNT(v2.vote_id) as vote_count
                    FROM candidates c2
                    JOIN users u2 ON c2.user_id = u2.user_id
                    LEFT JOIN votes v2 ON c2.candidate_id = v2.candidate_id
                        AND v2.gender_category = ?
                    WHERE c2.election_id = ?
                    AND c2.is_approved = 'approved'
                    AND u2.gender = ?
                    GROUP BY c2.candidate_id
                ) as vote_counts
            )
            ORDER BY COUNT(v.vote_id) DESC
            LIMIT 1
        ");

        $gender = substr($gender_category, 0, 1); // 'girls' -> 'F', 'boys' -> 'M'
        $gender = $gender == 'g' ? 'F' : 'M';

        $stmt->execute([
            $election['election_id'],
            $gender_category,
            $gender_category,
            $election['election_id'],
            $gender,
            $gender_category,
            $election['election_id'],
            $gender
        ]);

        $winner = $stmt->fetch();
        if ($winner) {
            $class_winners[] = $winner;
        }
    }
}

// If no winners yet, get potential candidates from ongoing elections
if (empty($class_winners)) {
    $stmt = $pdo->prepare("
        SELECT DISTINCT
            cand.candidate_id,
            u.user_id,
            u.fname,
            u.lname,
            u.email,
            u.gender,
            c.class_name,
            d.department_name,
            d.department_id,
            c.class_id,
            'Class Representative' as won_position,
            e.election_id,
            NULL as gender_category,
            0 as vote_count,
            'potential' as status
        FROM elections e
        JOIN candidates cand ON e.election_id = cand.election_id
        JOIN users u ON cand.user_id = u.user_id
        JOIN classes c ON e.class_id = c.class_id
        JOIN departments d ON c.department_id = d.department_id
        WHERE e.election_year = ?
        AND e.election_type_id = 1
        AND cand.is_approved = 'approved'
        ORDER BY d.department_name, c.class_name, u.fname
    ");
    $stmt->execute([$current_year]);
    $class_winners = $stmt->fetchAll();
}

// Get all departments for filter
$stmt = $pdo->prepare("SELECT DISTINCT d.department_id, d.department_name FROM departments d ORDER BY d.department_name");
$stmt->execute();
$departments = $stmt->fetchAll();

// Sort class winners by department and class
usort($class_winners, function($a, $b) {
    $dept_compare = strcmp($a['department_name'], $b['department_name']);
    if ($dept_compare !== 0) {
        return $dept_compare;
    }
    $class_compare = strcmp($a['class_name'], $b['class_name']);
    if ($class_compare !== 0) {
        return $class_compare;
    }
    return strcmp($a['fname'], $b['fname']);
});

include dirname(__DIR__) . '/includes/header.php';
?>

<div class="row">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1>Manage Students - Union Election Candidates</h1>
            <a href="union_elections.php" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-1"></i>Back to Union Elections
            </a>
        </div>
    </div>
</div>

<!-- Filter Section -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col-md-4">
                        <label for="departmentFilter" class="form-label">Filter by Department:</label>
                        <select class="form-select" id="departmentFilter">
                            <option value="">All Departments</option>
                            <?php foreach ($departments as $dept): ?>
                                <option value="<?php echo $dept['department_id']; ?>">
                                    <?php echo htmlspecialchars($dept['department_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label for="classFilter" class="form-label">Filter by Class:</label>
                        <select class="form-select" id="classFilter">
                            <option value="">All Classes</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <div class="mt-4">
                            <span class="badge bg-primary me-2">Total: <span id="totalCount"><?php echo count($class_winners); ?></span></span>
                            <span class="badge bg-success">Filtered: <span id="filteredCount"><?php echo count($class_winners); ?></span></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Students Section -->
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-users me-2"></i>
                    <?php if (!empty($class_winners) && $class_winners[0]['status'] == 'winner'): ?>
                        Class Election Winners - Eligible for Union Elections
                    <?php else: ?>
                        Potential Union Election Candidates
                    <?php endif; ?>
                </h5>
            </div>
            <div class="card-body">
                <?php if (empty($class_winners)): ?>
                    <div class="text-center py-4">
                        <i class="fas fa-users fa-2x text-muted mb-3"></i>
                        <h6 class="text-muted">No candidates available yet</h6>
                        <p class="text-muted">Create class elections and approve candidates first to see potential union election participants.</p>
                        <a href="elections.php" class="btn btn-outline-primary">
                            <i class="fas fa-users me-1"></i>Manage Class Elections
                        </a>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover" id="studentsTable">
                            <thead>
                                <tr>
                                    <th>Student</th>
                                    <th>Class</th>
                                    <th>Department</th>
                                    <th>Gender</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($class_winners as $winner): ?>
                                    <tr data-department-id="<?php echo $winner['department_id']; ?>" 
                                        data-class-id="<?php echo $winner['class_id']; ?>"
                                        data-department-name="<?php echo htmlspecialchars($winner['department_name']); ?>"
                                        data-class-name="<?php echo htmlspecialchars($winner['class_name']); ?>">
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <div class="avatar-sm bg-success text-white rounded-circle d-flex align-items-center justify-content-center me-3">
                                                    <i class="fas fa-user"></i>
                                                </div>
                                                <div>
                                                    <strong><?php echo htmlspecialchars($winner['fname'] . ' ' . $winner['lname']); ?></strong>
                                                    <br><small class="text-muted"><?php echo htmlspecialchars($winner['email']); ?></small>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="badge bg-info"><?php echo htmlspecialchars($winner['class_name']); ?></span>
                                        </td>
                                        <td>
                                            <small class="text-muted"><?php echo htmlspecialchars($winner['department_name']); ?></small>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?php echo $winner['gender'] == 'F' ? 'pink' : 'blue'; ?>">
                                                <?php echo $winner['gender'] == 'F' ? 'Female' : 'Male'; ?>
                                            </span>
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

<script>
document.addEventListener('DOMContentLoaded', function() {
    const departmentFilter = document.getElementById('departmentFilter');
    const classFilter = document.getElementById('classFilter');
    const studentsTable = document.getElementById('studentsTable');
    const totalCount = document.getElementById('totalCount');
    const filteredCount = document.getElementById('filteredCount');
    
    // Store all classes data for dynamic filtering
    const classesData = {};
    
    // Populate classes data from table rows
    const tableRows = studentsTable.querySelectorAll('tbody tr');
    tableRows.forEach(row => {
        const deptId = row.dataset.departmentId;
        const classId = row.dataset.classId;
        const deptName = row.dataset.departmentName;
        const className = row.dataset.className;
        
        if (!classesData[deptId]) {
            classesData[deptId] = [];
        }
        
        // Add class if not already exists
        if (!classesData[deptId].find(c => c.id === classId)) {
            classesData[deptId].push({
                id: classId,
                name: className
            });
        }
    });
    
    // Sort classes within each department
    Object.keys(classesData).forEach(deptId => {
        classesData[deptId].sort((a, b) => a.name.localeCompare(b.name));
    });
    
    // Update class filter based on department selection
    function updateClassFilter() {
        const selectedDept = departmentFilter.value;
        classFilter.innerHTML = '<option value="">All Classes</option>';
        
        if (selectedDept && classesData[selectedDept]) {
            classesData[selectedDept].forEach(cls => {
                const option = document.createElement('option');
                option.value = cls.id;
                option.textContent = cls.name;
                classFilter.appendChild(option);
            });
        }
        
        filterTable();
    }
    
    // Filter table based on selections
    function filterTable() {
        const selectedDept = departmentFilter.value;
        const selectedClass = classFilter.value;
        let visibleCount = 0;
        
        tableRows.forEach(row => {
            const rowDeptId = row.dataset.departmentId;
            const rowClassId = row.dataset.classId;
            
            let showRow = true;
            
            if (selectedDept && rowDeptId !== selectedDept) {
                showRow = false;
            }
            
            if (selectedClass && rowClassId !== selectedClass) {
                showRow = false;
            }
            
            if (showRow) {
                row.style.display = '';
                visibleCount++;
            } else {
                row.style.display = 'none';
            }
        });
        
        filteredCount.textContent = visibleCount;
    }
    
    // Event listeners
    departmentFilter.addEventListener('change', updateClassFilter);
    classFilter.addEventListener('change', filterTable);
    
    // Initial setup
    updateClassFilter();
});
</script>

<style>
.avatar-sm {
    width: 40px;
    height: 40px;
    font-size: 0.875rem;
    font-weight: bold;
}

.badge.bg-pink {
    background-color: #e83e8c !important;
}

.badge.bg-blue {
    background-color: #007bff !important;
}
</style>

<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
