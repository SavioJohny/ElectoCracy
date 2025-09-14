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

requireRole('Election Commissioner');

// Prevent caching of this page
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

$page_title = 'Election Commissioner Dashboard';

// Get election statistics
$current_year = date('Y');

// Total elections this year
$stmt = $pdo->prepare("SELECT COUNT(*) as count FROM elections WHERE election_year = ?");
$stmt->execute([$current_year]);
$total_elections = $stmt->fetch()['count'];

// Active elections
$stmt = $pdo->prepare("SELECT COUNT(*) as count FROM elections WHERE election_year = ? AND is_active = 1");
$stmt->execute([$current_year]);
$active_elections = $stmt->fetch()['count'];

// Total candidates this year
$stmt = $pdo->prepare("
    SELECT COUNT(*) as count 
    FROM candidates c 
    JOIN elections e ON c.election_id = e.election_id 
    WHERE e.election_year = ?
");
$stmt->execute([$current_year]);
$total_candidates = $stmt->fetch()['count'];

// Total votes this year
$stmt = $pdo->prepare("
    SELECT COUNT(*) as count 
    FROM votes v 
    JOIN elections e ON v.election_id = e.election_id 
    WHERE e.election_year = ?
");
$stmt->execute([$current_year]);
$total_votes = $stmt->fetch()['count'];

// Recent elections
$stmt = $pdo->prepare("
    SELECT e.*, et.election_type_name, c.class_name, d.department_name,
           COUNT(DISTINCT cand.candidate_id) as candidate_count,
           COUNT(DISTINCT v.vote_id) as vote_count
    FROM elections e
    JOIN election_types et ON e.election_type_id = et.election_type_id
    LEFT JOIN classes c ON e.class_id = c.class_id
    LEFT JOIN departments d ON c.department_id = d.department_id
    LEFT JOIN candidates cand ON e.election_id = cand.election_id
    LEFT JOIN votes v ON e.election_id = v.election_id
    WHERE e.election_year = ?
    GROUP BY e.election_id
    ORDER BY e.election_id DESC
    LIMIT 5
");
$stmt->execute([$current_year]);
$recent_elections = $stmt->fetchAll();

include dirname(__DIR__) . '/includes/header.php';
?>

<div class="row">
    <div class="col-12">
        <h1 class="mb-4">Election Commissioner Dashboard</h1>
    </div>
</div>



<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-cogs me-2"></i>Election Management</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-3 mb-3">
                        <a href="elections.php" class="btn btn-outline-dark w-100">
                            <i class="fas fa-users fa-2x mb-2"></i><br>
                            Class Elections
                        </a>
                    </div>
                    <div class="col-md-3 mb-3">
                        <a href="voting_status.php" class="btn btn-outline-secondary w-100">
                            <i class="fas fa-chart-line fa-2x mb-2"></i><br>
                            Class Voting Monitor
                        </a>
                    </div>
                    <div class="col-md-3 mb-3">
                        <a href="union_elections.php" class="btn btn-outline-success w-100">
                            <i class="fas fa-university fa-2x mb-2"></i><br>
                            Union Elections
                        </a>
                    </div>
                    <div class="col-md-3 mb-3">
                        <a href="union_voting_status.php" class="btn btn-outline-info w-100">
                            <i class="fas fa-university fa-2x mb-2"></i><br>
                            Union Voting Monitor
                        </a>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-3 mb-3">
                        <a href="voting_control.php" class="btn btn-outline-dark w-100">
                            <i class="fas fa-vote-yea fa-2x mb-2"></i><br>
                            Union Voting Control
                        </a>
                    </div>
                    <div class="col-md-3 mb-3">
                        <a href="disqualifications.php" class="btn btn-outline-danger w-100">
                            <i class="fas fa-user-slash fa-2x mb-2"></i><br>
                            Student Disqualifications
                        </a>
                    </div>
                    <div class="col-md-3 mb-3">
                        <a href="results.php" class="btn btn-outline-info w-100">
                            <i class="fas fa-chart-bar fa-2x mb-2"></i><br>
                            Election Results
                        </a>
                    </div>
                    <div class="col-md-3 mb-3">
                        <a href="reports.php" class="btn btn-outline-warning w-100">
                            <i class="fas fa-file-alt fa-2x mb-2"></i><br>
                            Generate Reports
                        </a>
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
