<?php
session_start();

// Check if user was logged out by back button
if (isset($_SESSION['logged_out_by_back_button'])) {
    session_destroy();
    header('Location: ../login.php');
    exit();
}

require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/config/database.php';

requireRole('Invigilator');

// Prevent caching of this page
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

$page_title = 'Invigilator Dashboard';
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

// Get candidates requiring approval for assigned classes
$candidates_pending = [];
if (!empty($assigned_classes)) {
    $class_ids = array_column($assigned_classes, 'class_id');
    $placeholders = str_repeat('?,', count($class_ids) - 1) . '?';
    
    $stmt = $pdo->prepare("
        SELECT c.*, u.fname, u.lname, u.roll_number, cl.class_name, e.election_year, et.election_type_name
        FROM candidates c
        JOIN users u ON c.user_id = u.user_id
        JOIN elections e ON c.election_id = e.election_id
        JOIN election_types et ON e.election_type_id = et.election_type_id
        LEFT JOIN classes cl ON e.class_id = cl.class_id
        WHERE c.is_approved = 'pending'
        AND (e.class_id IN ($placeholders) OR e.class_id IS NULL)
        AND e.is_active = 1
        ORDER BY c.candidate_id DESC
    ");
    $stmt->execute($class_ids);
    $candidates_pending = $stmt->fetchAll();
}

// Get active class elections for assigned classes
$active_class_elections = [];
$voting_stats = [];

if (!empty($assigned_classes)) {
    $class_ids = array_column($assigned_classes, 'class_id');
    $placeholders = str_repeat('?,', count($class_ids) - 1) . '?';

    $stmt = $pdo->prepare("
        SELECT e.*, et.election_type_name, c.class_name, d.department_name
        FROM elections e
        JOIN election_types et ON e.election_type_id = et.election_type_id
        JOIN classes c ON e.class_id = c.class_id
        JOIN departments d ON c.department_id = d.department_id
        WHERE e.is_active = 1
        AND et.election_type_name = 'class'
        AND e.class_id IN ($placeholders)
        ORDER BY e.election_year DESC, c.class_name
    ");
    $stmt->execute($class_ids);
    $active_class_elections = $stmt->fetchAll();

    // Get voting statistics for each active class election
    foreach ($active_class_elections as $election) {
        // Get total students in the class
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as total_students
            FROM users u
            JOIN roles r ON u.role_id = r.role_id
            WHERE u.class_id = ? AND r.role_name = 'Student'
        ");
        $stmt->execute([$election['class_id']]);
        $total_students = $stmt->fetchColumn();

        // Get students who have voted (at least one vote in this election)
        $stmt = $pdo->prepare("
            SELECT COUNT(DISTINCT v.voter_id) as voted_students
            FROM votes v
            JOIN users u ON v.voter_id = u.user_id
            JOIN roles r ON u.role_id = r.role_id
            WHERE v.election_id = ? AND u.class_id = ? AND r.role_name = 'Student'
        ");
        $stmt->execute([$election['election_id'], $election['class_id']]);
        $voted_students = $stmt->fetchColumn();

        // Get detailed voting breakdown (girls/boys)
        $stmt = $pdo->prepare("
            SELECT
                v.gender_category,
                COUNT(DISTINCT v.voter_id) as voted_count
            FROM votes v
            JOIN users u ON v.voter_id = u.user_id
            JOIN roles r ON u.role_id = r.role_id
            WHERE v.election_id = ? AND u.class_id = ? AND r.role_name = 'Student'
            GROUP BY v.gender_category
        ");
        $stmt->execute([$election['election_id'], $election['class_id']]);
        $gender_voting = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

        $voting_stats[$election['election_id']] = [
            'total_students' => $total_students,
            'voted_students' => $voted_students,
            'not_voted_students' => $total_students - $voted_students,
            'voting_percentage' => $total_students > 0 ? round(($voted_students / $total_students) * 100, 1) : 0,
            'girls_voted' => $gender_voting['girls'] ?? 0,
            'boys_voted' => $gender_voting['boys'] ?? 0
        ];
    }
}

include dirname(__DIR__) . '/includes/header.php';
?>

<div class="row">
    <div class="col-12">
        <h1 class="mb-4">Invigilator Dashboard</h1>
    </div>
</div>


<div class="row">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-chalkboard-teacher me-2"></i>Assigned Class</h5>
            </div>
            <div class="card-body">
                <?php if (empty($assigned_classes)): ?>
                    <div class="text-center py-4">
                        <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                        <h5 class="text-muted">No Class Assignments</h5>
                        <p class="text-muted">You have no class assignments for <?php echo $current_year; ?>.</p>
                    </div>
                <?php else: ?>
                    <div class="list-group list-group-flush">
                        <?php foreach ($assigned_classes as $class): ?>
                            <div class="list-group-item border-0 px-0">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="mb-1"><?php echo htmlspecialchars($class['class_name']); ?></h6>
                                        <small class="text-muted"><?php echo htmlspecialchars($class['department_name']); ?></small>
                                    </div>
                                    <span class="badge bg-primary"><?php echo $class['election_year']; ?></span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-user-clock me-2"></i>Pending Candidate Approvals</h5>
            </div>
            <div class="card-body">
                <?php if (empty($candidates_pending)): ?>
                    <div class="text-center py-4">
                        <i class="fas fa-check-circle fa-3x text-success mb-3"></i>
                        <h5 class="text-muted">All Caught Up!</h5>
                        <p class="text-muted">No candidates pending approval.</p>
                    </div>
                <?php else: ?>
                    <div class="list-group list-group-flush">
                        <?php foreach (array_slice($candidates_pending, 0, 5) as $candidate): ?>
                            <div class="list-group-item border-0 px-0 py-2">
                                <div>
                                    <h6 class="mb-1">
                                        <?php echo htmlspecialchars($candidate['fname'] . ' ' . $candidate['lname']); ?>
                                    </h6>
                                    <small class="text-muted">
                                        Roll: <?php echo htmlspecialchars($candidate['roll_number']); ?> | 
                                        <?php echo ucfirst($candidate['election_type_name']); ?> <?php echo $candidate['election_year']; ?>
                                    </small>
                                </div>
                            </div>
                        <?php endforeach; ?>
                        <?php if (count($candidates_pending) > 5): ?>
                            <div class="list-group-item border-0 px-0 text-center">
                                <a href="candidate_approval.php" class="btn btn-sm btn-outline-primary">
                                    View All (<?php echo count($candidates_pending); ?>)
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="row mt-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-tasks me-2"></i>Actions List</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-3 mb-3">
                        <a href="candidate_approval.php" class="btn btn-outline-primary w-100">
                            <i class="fas fa-users fa-2x mb-2"></i><br>
                            Review Candidates
                        </a>
                    </div>
                    <div class="col-md-3 mb-3">
                        <a href="voting_control.php" class="btn btn-outline-warning w-100">
                            <i class="fas fa-play-circle fa-2x mb-2"></i><br>
                            Voting Control
                        </a>
                    </div>
                    <div class="col-md-3 mb-3">
                        <a href="student_disqualification.php" class="btn btn-outline-danger w-100">
                            <i class="fas fa-user-slash fa-2x mb-2"></i><br>
                            Student Disqualification
                        </a>
                    </div>
                    <div class="col-md-3 mb-3">
                        <a href="results.php" class="btn btn-outline-success w-100">
                            <i class="fas fa-chart-bar fa-2x mb-2"></i><br>
                            Results
                        </a>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-3 mb-3">
                        <?php if (!empty($assigned_classes)): ?>
                            <?php $first_class = $assigned_classes[0]; ?>
                            <?php 
                            // Get the election for this class to create monitor link
                            $stmt = $pdo->prepare("
                                SELECT e.election_id 
                                FROM elections e
                                JOIN election_types et ON e.election_type_id = et.election_type_id
                                WHERE e.class_id = ? AND et.election_type_name = 'class' AND e.is_active = 1
                                ORDER BY e.election_year DESC
                                LIMIT 1
                            ");
                            $stmt->execute([$first_class['class_id']]);
                            $election_id = $stmt->fetchColumn();
                            ?>
                            <?php if ($election_id): ?>
                                <a href="voting_status.php?election_id=<?php echo $election_id; ?>" class="btn btn-outline-info w-100">
                                    <i class="fas fa-chart-line fa-2x mb-2"></i><br>
                                    Monitor Voting
                                </a>
                            <?php else: ?>
                                <button class="btn btn-outline-secondary w-100" disabled>
                                    <i class="fas fa-chart-line fa-2x mb-2"></i><br>
                                    Monitor Voting
                                </button>
                            <?php endif; ?>
                        <?php else: ?>
                            <button class="btn btn-outline-secondary w-100" disabled>
                                <i class="fas fa-chart-line fa-2x mb-2"></i><br>
                                Monitor Voting
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>



<script>
// Kill session ONLY on back button press
window.addEventListener('load', function() {
    // Push state and listen for popstate (back button only)
    window.history.pushState(null, "", window.location.href);
    window.addEventListener('popstate', function() {
        // Back button pressed - kill session and redirect
        var xhr = new XMLHttpRequest();
        xhr.open('POST', '../kill_session.php', false);
        xhr.send();

        // Force redirect to login
        window.location.href = '../login.php';
    });
});
</script>

<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
