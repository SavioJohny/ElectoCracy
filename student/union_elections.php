<?php
session_start();
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/config/database.php';

requireRole('Student');

$page_title = 'Union Elections';
$user_id = $_SESSION['user_id'];

// Get current year
$current_year = date('Y');

// Check if current student is union-eligible (approved candidate in class elections)
$stmt = $pdo->prepare("
    SELECT COUNT(*) as is_union_eligible
    FROM candidates c
    JOIN elections e ON c.election_id = e.election_id
    JOIN election_types et ON e.election_type_id = et.election_type_id
    WHERE c.user_id = ?
    AND et.election_type_name = 'class'
    AND e.election_year = ?
    AND c.is_approved = 'approved'
");
$stmt->execute([$user_id, $current_year]);
$is_union_eligible = $stmt->fetchColumn() > 0;

// If student is not union-eligible, redirect to dashboard with message
if (!$is_union_eligible) {
    header('Location: dashboard.php?message=union_not_eligible');
    exit;
}

// Check if student is disqualified from union elections
$stmt = $pdo->prepare("
    SELECT COUNT(*) as is_disqualified
    FROM election_disqualifications ed
    JOIN elections e ON ed.election_id = e.election_id
    JOIN election_types et ON e.election_type_id = et.election_type_id
    WHERE ed.student_id = ? 
    AND et.election_type_name = 'union'
    AND e.election_year = ?
");
$stmt->execute([$user_id, $current_year]);
$is_disqualified = $stmt->fetchColumn() > 0;

// If student is disqualified from union elections, redirect to dashboard
if ($is_disqualified) {
    header('Location: dashboard.php?message=union_disqualified');
    exit;
}

// Get student information
$stmt = $pdo->prepare("
    SELECT u.*, c.class_name, d.department_name
    FROM users u
    JOIN classes c ON u.class_id = c.class_id
    JOIN departments d ON c.department_id = d.department_id
    WHERE u.user_id = ?
");
$stmt->execute([$user_id]);
$student = $stmt->fetch();

// Check if student won any class election by calculating from votes
// First check for actual wins from completed elections
$class_win = null;

// Get student's gender first
$stmt = $pdo->prepare("SELECT gender FROM users WHERE user_id = ?");
$stmt->execute([$user_id]);
$student_gender = $stmt->fetchColumn();

// Check if student won in any completed election
$stmt = $pdo->prepare("
    SELECT DISTINCT e.election_id, e.class_id
    FROM elections e
    JOIN candidates cand ON e.election_id = cand.election_id
    JOIN votes v ON e.election_id = v.election_id
    WHERE cand.user_id = ?
    AND e.election_type_id = 1
    AND e.election_year = ?
    AND e.voting_status = 'ended'
    AND cand.is_approved = 'approved'
");
$stmt->execute([$user_id, $current_year]);
$student_elections = $stmt->fetchAll();

// Check each election to see if student won
foreach ($student_elections as $election) {
    $gender_category = $student_gender == 'F' ? 'girls' : 'boys';

    $stmt = $pdo->prepare("
        SELECT
            cand.candidate_id,
            ? as election_id,
            'Class Representative' as position_name,
            ? as election_year,
            ? as gender_category,
            COUNT(v.vote_id) as vote_count,
            'winner' as status
        FROM candidates cand
        LEFT JOIN votes v ON cand.candidate_id = v.candidate_id AND v.gender_category = ?
        WHERE cand.user_id = ?
        AND cand.election_id = ?
        AND cand.is_approved = 'approved'
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
        LIMIT 1
    ");

    $stmt->execute([
        $election['election_id'],
        $current_year,
        $gender_category,
        $gender_category,
        $user_id,
        $election['election_id'],
        $gender_category,
        $election['election_id'],
        $student_gender
    ]);

    $win_check = $stmt->fetch();
    if ($win_check) {
        $class_win = $win_check;
        break; // Found a win, no need to check other elections
    }
}

// If no actual win, check if student is an approved candidate (potential eligibility)
if (!$class_win) {
    $stmt = $pdo->prepare("
        SELECT
            cand.candidate_id,
            e.election_id,
            'Class Representative' as position_name,
            e.election_year,
            NULL as gender_category,
            0 as vote_count,
            'potential' as status
        FROM elections e
        JOIN candidates cand ON e.election_id = cand.election_id
        WHERE cand.user_id = ?
        AND e.election_type_id = 1
        AND e.election_year = ?
        AND cand.is_approved = 'approved'
        LIMIT 1
    ");
    $stmt->execute([$user_id, $current_year]);
    $class_win = $stmt->fetch();
}

// Get union election for current year
$stmt = $pdo->prepare("
    SELECT e.*
    FROM elections e
    JOIN election_types et ON e.election_type_id = et.election_type_id
    WHERE e.election_year = ? AND et.election_type_name = 'union'
");
$stmt->execute([$current_year]);
$union_election = $stmt->fetch();

// Get all union positions (students can see all positions but can only apply/vote for active ones)
$stmt = $pdo->prepare("
    SELECT * FROM positions
    WHERE election_type_id = 2
    ORDER BY voting_order
");
$stmt->execute();
$union_positions = $stmt->fetchAll();

// Get student's union applications
$student_applications = [];
if ($union_election) {
    $stmt = $pdo->prepare("
        SELECT cand.*, p.position_name, p.voting_order
        FROM candidates cand
        JOIN positions p ON cand.position_id = p.position_id
        WHERE cand.user_id = ? AND cand.election_id = ?
        ORDER BY p.voting_order
    ");
    $stmt->execute([$user_id, $union_election['election_id']]);
    $student_applications = $stmt->fetchAll();
}

include dirname(__DIR__) . '/includes/header.php';
?>

<div class="row">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1>Union Elections - <?php echo $current_year; ?></h1>
            <div>
                <a href="dashboard.php" class="btn btn-outline-secondary">
                    <i class="fas fa-arrow-left me-1"></i>Back to Dashboard
                </a>
            </div>
        </div>
    </div>
</div>


<?php if (!$union_election): ?>
    <!-- No Union Election -->
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body text-center py-5">
                    <i class="fas fa-university fa-3x text-muted mb-3"></i>
                    <h5 class="text-muted">No Union Election Available</h5>
                    <p class="text-muted">Union election for <?php echo $current_year; ?> has not been created yet.</p>
                </div>
            </div>
        </div>
    </div>
<?php elseif (!$class_win): ?>
    <!-- Not Eligible -->
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body text-center py-5">
                    <i class="fas fa-ban fa-3x text-warning mb-3"></i>
                    <h5 class="text-warning">Not Eligible for Union Elections</h5>
                    <p class="text-muted">Only class election winners can participate in union elections.</p>
                    <p class="text-muted">Win a class election first to become eligible for union positions.</p>
                </div>
            </div>
        </div>
    </div>
<?php else: ?>

    <!-- Your Applications -->
    <?php if (!empty($student_applications)): ?>
    <div class="row mt-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-file-alt me-2"></i>Your Applications</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <?php foreach ($student_applications as $application): ?>
                            <div class="col-md-6 mb-3">
                                <div class="card border-left-primary">
                                    <div class="card-body p-3">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <div>
                                                <h6 class="mb-1"><?php echo htmlspecialchars($application['position_name']); ?></h6>
                                            </div>
                                            <div>
                                                <span class="badge bg-<?php echo $application['is_approved'] == 'approved' ? 'success' : ($application['is_approved'] == 'rejected' ? 'danger' : 'warning'); ?>">
                                                    <?php echo ucfirst($application['is_approved']); ?>
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Available Positions -->
    <div class="row mt-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-sitemap me-2"></i>Available Union Positions</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($union_positions)): ?>
                        <div class="text-center py-4">
                            <i class="fas fa-sitemap fa-2x text-muted mb-3"></i>
                            <h6 class="text-muted">No positions available</h6>
                            <p class="text-muted">Union positions have not been set up yet.</p>
                        </div>
                    <?php else: ?>
                        <div class="row">
                            <?php foreach ($union_positions as $position): ?>
                                <?php
                                $already_applied = false;
                                foreach ($student_applications as $app) {
                                    if ($app['position_id'] == $position['position_id']) {
                                        $already_applied = true;
                                        break;
                                    }
                                }

                                // Check position and election status for applications and voting
                                $position_active = $position['is_active'];

                                // Application rules based on election and position status
                                $can_apply = false;
                                if ($union_election['voting_status'] == 'ended') {
                                    $can_apply = false; // No applications when election ended
                                } elseif ($position_active) {
                                    $can_apply = false; // Cannot apply for active positions (voting in progress)
                                } elseif (!empty($student_applications)) {
                                    $can_apply = false; // Cannot apply for any position if already applied for another
                                } elseif (!$already_applied) {
                                    $can_apply = true; // Can apply for inactive positions if not already applied
                                }

                                // Voting rules based on election and position status
                                $can_vote = false;
                                if ($union_election['voting_status'] == 'active' && $position_active) {
                                    $can_vote = true; // Can vote only when election is active and position is active
                                }
                                ?>
                                <div class="col-md-6 mb-3">
                                    <div class="card h-100">
                                        <div class="card-body">
                                            <div class="d-flex justify-content-between align-items-start mb-3">
                                                <div>
                                                    <h6 class="mb-1">
                                                        <span class="badge bg-primary me-2"><?php echo $position['voting_order']; ?></span>
                                                        <?php echo htmlspecialchars($position['position_name']); ?>
                                                    </h6>
                                                    <small class="text-muted"><?php echo htmlspecialchars($position['position_type']); ?></small>
                                                </div>
                                                <div>
                                                    <?php if ($already_applied): ?>
                                                        <span class="badge bg-success me-1">Applied</span>
                                                    <?php endif; ?>

                                                    <span class="badge bg-<?php echo $position_active ? 'success' : 'warning'; ?>">
                                                        <?php echo $position_active ? 'Voting Active' : 'Voting Inactive'; ?>
                                                    </span>

                                                    <?php if ($can_apply): ?>
                                                        <button class="btn btn-outline-primary btn-sm apply-position-btn"
                                                                data-position-id="<?php echo $position['position_id']; ?>"
                                                                data-position-name="<?php echo htmlspecialchars($position['position_name']); ?>">
                                                            <i class="fas fa-plus me-1"></i>Apply
                                                        </button>
                                                    <?php else: ?>
                                                        <span class="badge bg-secondary">
                                                            <?php
                                                            if ($union_election['voting_status'] == 'ended') {
                                                                echo 'Election Ended';
                                                            } elseif ($position_active) {
                                                                echo 'Position Active - No Applications';
                                                            } elseif (!empty($student_applications) && !$already_applied) {
                                                                echo 'Already Applied for Another Position';
                                                            } elseif ($already_applied) {
                                                                echo 'Already Applied';
                                                            } else {
                                                                echo 'Applications Closed';
                                                            }
                                                            ?>
                                                        </span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Define showAlert function locally if not available
    function showAlert(message, type = 'info') {
        if (window.showAlert && typeof window.showAlert === 'function') {
            return window.showAlert(message, type);
        }

        const alertDiv = document.createElement('div');
        alertDiv.className = `alert alert-${type} alert-dismissible fade show`;
        alertDiv.innerHTML = `
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        `;

        const container = document.querySelector('.container');
        if (container) {
            container.insertBefore(alertDiv, container.firstChild);
        }

        setTimeout(() => {
            if (alertDiv.parentNode) {
                alertDiv.remove();
            }
        }, 5000);
    }

    // Handle apply for position buttons
    document.querySelectorAll('.apply-position-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const positionId = this.dataset.positionId;
            const positionName = this.dataset.positionName;

            if (confirm(`Are you sure you want to apply for the position of ${positionName}?`)) {
                const formData = new FormData();
                formData.append('action', 'apply_position');
                formData.append('position_id', positionId);

                this.disabled = true;
                this.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Applying...';

                fetch('union_election_handler.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showAlert(data.message, 'success');
                        setTimeout(() => window.location.reload(), 1500);
                    } else {
                        showAlert(data.message, 'danger');
                        this.disabled = false;
                        this.innerHTML = '<i class="fas fa-plus me-1"></i>Apply';
                    }
                })
                .catch(error => {
                    showAlert('Network error. Please try again.', 'danger');
                    this.disabled = false;
                    this.innerHTML = '<i class="fas fa-plus me-1"></i>Apply';
                });
            }
        });
    });
});
</script>

<style>
.border-left-primary {
    border-left: 4px solid #007bff !important;
}

.card.h-100 {
    height: 100% !important;
}
</style>

<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
