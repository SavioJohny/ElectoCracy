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

requireRole('Student');

// Prevent caching of this page
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

$page_title = 'Student Dashboard';
$current_user = getCurrentUser();

// Check if student is union-eligible (must be an approved candidate in class elections)
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
$stmt->execute([$current_user['user_id'], date('Y')]);
$is_union_eligible = $stmt->fetchColumn() > 0;

// Check for union elections only if student is eligible AND union election exists
$union_election_exists = false;
if ($is_union_eligible) {
    // First check if any union election exists for current year
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as union_count
        FROM elections e
        JOIN election_types et ON e.election_type_id = et.election_type_id
        WHERE et.election_type_name = 'union' AND e.election_year = ?
    ");
    $stmt->execute([date('Y')]);
    $union_election_exists = $stmt->fetchColumn() > 0;
}

// Show union elections only if student is eligible AND union election exists
if ($is_union_eligible && $union_election_exists) {
    $stmt = $pdo->prepare("
        SELECT e.*, et.election_type_name, c.class_name, d.department_name
        FROM elections e
        JOIN election_types et ON e.election_type_id = et.election_type_id
        LEFT JOIN classes c ON e.class_id = c.class_id
        LEFT JOIN departments d ON c.department_id = d.department_id
        WHERE e.is_active = 1 AND et.election_type_name = 'union'
        ORDER BY e.election_year DESC
    ");
    $stmt->execute();
    $union_elections = $stmt->fetchAll();

    // If there are active union elections, only show those
    if (!empty($union_elections)) {
        $active_elections = $union_elections;
        $election_mode = 'union';
    } else {
        // Otherwise, show class elections for student's class
        $stmt = $pdo->prepare("
            SELECT e.*, et.election_type_name, c.class_name, d.department_name
            FROM elections e
            JOIN election_types et ON e.election_type_id = et.election_type_id
            LEFT JOIN classes c ON e.class_id = c.class_id
            LEFT JOIN departments d ON c.department_id = d.department_id
            WHERE e.class_id = ? AND e.is_active = 1 AND et.election_type_name = 'class'
            ORDER BY e.election_year DESC
        ");
        $stmt->execute([$current_user['class_id']]);
        $active_elections = $stmt->fetchAll();
        $election_mode = 'class';
    }
} else {
    // Show class elections for student's class
    $stmt = $pdo->prepare("
        SELECT e.*, et.election_type_name, c.class_name, d.department_name
        FROM elections e
        JOIN election_types et ON e.election_type_id = et.election_type_id
        LEFT JOIN classes c ON e.class_id = c.class_id
        LEFT JOIN departments d ON c.department_id = d.department_id
        WHERE e.class_id = ? AND e.is_active = 1 AND et.election_type_name = 'class'
        ORDER BY e.election_year DESC
    ");
    $stmt->execute([$current_user['class_id']]);
    $active_elections = $stmt->fetchAll();
    $election_mode = 'class';
}

// Check for disqualifications in active elections
$disqualified_elections = [];
if (!empty($active_elections)) {
    $election_ids = array_column($active_elections, 'election_id');
    $placeholders = str_repeat('?,', count($election_ids) - 1) . '?';

    $stmt = $pdo->prepare("
        SELECT ed.election_id, e.election_year, c.class_name, et.election_type_name
        FROM election_disqualifications ed
        JOIN elections e ON ed.election_id = e.election_id
        JOIN election_types et ON e.election_type_id = et.election_type_id
        LEFT JOIN classes c ON e.class_id = c.class_id
        WHERE ed.student_id = ? AND ed.election_id IN ($placeholders)
    ");
    $stmt->execute(array_merge([$current_user['user_id']], $election_ids));
    $disqualified_elections = $stmt->fetchAll();
}

// Create a map of disqualified election IDs for easy checking
$disqualified_election_ids = array_column($disqualified_elections, 'election_id');

// Check if student has voted in any active elections (only for non-disqualified elections)
$voted_elections = [];
$eligible_elections = array_filter($active_elections, function($election) use ($disqualified_election_ids) {
    return !in_array($election['election_id'], $disqualified_election_ids);
});

// Handle voting status based on election mode
$union_voting_status = [];
$class_voting_status = [];

if (!empty($eligible_elections)) {
    $eligible_election_ids = array_column($eligible_elections, 'election_id');
    $placeholders = str_repeat('?,', count($eligible_election_ids) - 1) . '?';

    if ($election_mode === 'union') {
        // Check votes for union elections - check for complete voting (both gender categories)
        foreach ($eligible_elections as $election) {
            if ($election['election_type_name'] === 'union') {
                // Check if student has voted for both gender categories
                $stmt = $pdo->prepare("
                    SELECT DISTINCT gender_category
                    FROM votes v
                    WHERE v.voter_id = ? AND v.election_id = ?
                ");
                $stmt->execute([$current_user['user_id'], $election['election_id']]);
                $voted_categories = array_column($stmt->fetchAll(), 'gender_category');
                
                // Only consider fully voted if both 'girls' and 'boys' categories are voted
                if (count($voted_categories) >= 2 || (in_array('girls', $voted_categories) && in_array('boys', $voted_categories))) {
                    $class_voting_status[] = $election['election_id'];
                }
            }
        }
        $voted_elections = $class_voting_status;
    } else {
        // For class elections, check if student can vote for any active positions
        foreach ($eligible_elections as $election) {
            // Check if there are any active positions student can vote for
            $stmt = $pdo->prepare("
                SELECT COUNT(DISTINCT p.position_id) as votable_positions
                FROM positions p
                WHERE p.election_type_id = 2 AND p.is_active = 1
            ");
            $stmt->execute();
            $votable_positions = $stmt->fetchColumn();

            // Check if student has voted for any positions in this union election
            $stmt = $pdo->prepare("
                SELECT COUNT(DISTINCT v.position_id) as voted_positions
                FROM votes v
                JOIN positions p ON v.position_id = p.position_id
                WHERE v.voter_id = ? AND v.election_id = ? AND p.election_type_id = 2
            ");
            $stmt->execute([$current_user['user_id'], $election['election_id']]);
            $voted_positions = $stmt->fetchColumn();

            $union_voting_status[$election['election_id']] = [
                'votable_positions' => $votable_positions,
                'voted_positions' => $voted_positions,
                'can_vote' => ($election['voting_status'] === 'active' && $votable_positions > 0),
                'has_pending_votes' => ($votable_positions > $voted_positions && $election['voting_status'] === 'active')
            ];
        }
        $voted_elections = []; // Union elections don't use traditional voted_elections array
    }
}

// Get student's candidate applications
$stmt = $pdo->prepare("
    SELECT c.*, e.election_year, e.election_id, p.position_name, et.election_type_name
    FROM candidates c
    JOIN elections e ON c.election_id = e.election_id
    JOIN positions p ON c.position_id = p.position_id
    JOIN election_types et ON e.election_type_id = et.election_type_id
    WHERE c.user_id = ?
    ORDER BY e.election_year DESC, c.candidate_id DESC
");
$stmt->execute([$current_user['user_id']]);
$candidate_applications = $stmt->fetchAll();

// Check for active class elections where student can apply (excluding disqualified elections)
$can_apply_elections = [];

// Check if class elections are over for non-union-eligible students
$class_elections_over = false;
if (!$is_union_eligible) {
    // Check if there are any class elections that have ended
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as ended_class_elections
        FROM elections e
        JOIN election_types et ON e.election_type_id = et.election_type_id
        WHERE e.class_id = ? AND et.election_type_name = 'class' 
        AND e.election_year = ? AND e.voting_status = 'ended'
    ");
    $stmt->execute([$current_user['class_id'], date('Y')]);
    $class_elections_over = $stmt->fetchColumn() > 0;
}

// Check if class elections are currently active or paused (voting phase)
$class_elections_active = false;
$class_elections_paused = false;
$stmt = $pdo->prepare("
    SELECT COUNT(*) as active_class_elections
    FROM elections e
    JOIN election_types et ON e.election_type_id = et.election_type_id
    WHERE e.class_id = ? AND et.election_type_name = 'class' 
    AND e.election_year = ? AND e.voting_status = 'active'
");
$stmt->execute([$current_user['class_id'], date('Y')]);
$class_elections_active = $stmt->fetchColumn() > 0;

// Check if class elections are currently paused
$stmt = $pdo->prepare("
    SELECT COUNT(*) as paused_class_elections
    FROM elections e
    JOIN election_types et ON e.election_type_id = et.election_type_id
    WHERE e.class_id = ? AND et.election_type_name = 'class' 
    AND e.election_year = ? AND e.voting_status = 'paused'
");
$stmt->execute([$current_user['class_id'], date('Y')]);
$class_elections_paused = $stmt->fetchColumn() > 0;

foreach ($eligible_elections as $election) {
    if ($election['election_type_name'] === 'class' && $election['class_id'] == $current_user['class_id']) {
        // Check if student already applied for this election
        $already_applied = false;
        foreach ($candidate_applications as $app) {
            if ($app['election_id'] == $election['election_id']) {
                $already_applied = true;
                break;
            }
        }
        if (!$already_applied) {
            $can_apply_elections[] = $election;
        }
    }
}

include dirname(__DIR__) . '/includes/header.php';
?>

<?php if (isset($_GET['message']) && $_GET['message'] === 'union_not_eligible'): ?>
    <div class="row mb-4">
        <div class="col-12">
            <div class="alert alert-warning alert-dismissible fade show">
                <i class="fas fa-exclamation-triangle me-2"></i>
                <strong>Union Elections Access Restricted:</strong> You are not eligible to access union elections.
                You must be an approved candidate in class elections to participate in union elections.
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php if (isset($_GET['message']) && $_GET['message'] === 'union_disqualified'): ?>
    <div class="row mb-4">
        <div class="col-12">
            <div class="alert alert-danger alert-dismissible fade show">
                <i class="fas fa-ban me-2"></i>
                <strong>Union Elections Access Denied:</strong> You have been disqualified from union elections.
                Disqualified students cannot apply as candidates or participate in union elections.
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php if (!empty($disqualified_elections)): ?>
    <!-- Disqualification Notice -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="alert alert-danger border-0 shadow-sm">
                <div class="d-flex align-items-center mb-3">
                    <div class="alert-icon me-3">
                        <i class="fas fa-user-slash fa-2x text-danger"></i>
                    </div>
                    <div>
                        <h4 class="alert-heading mb-1">
                            <i class="fas fa-exclamation-triangle me-2"></i>Election Disqualification Notice
                        </h4>
                        <p class="mb-0">You have been disqualified from participating in the following election(s):</p>
                    </div>
                </div>

                <div class="disqualified-elections">
                    <?php foreach ($disqualified_elections as $disq_election): ?>
                        <div class="disqualified-election-item p-3 mb-2 bg-light rounded border-start border-danger border-3">
                            <div class="row align-items-center">
                                <div class="col-md-8">
                                    <h6 class="mb-1">
                                        <i class="fas fa-vote-yea me-2 text-danger"></i>
                                        <?php if ($disq_election['election_type_name'] === 'class'): ?>
                                            <?php echo htmlspecialchars($disq_election['class_name']); ?> Class Election
                                        <?php else: ?>
                                            Union Election
                                        <?php endif; ?>
                                    </h6>
                                    <small class="text-muted">
                                        <i class="fas fa-calendar me-1"></i>Election Year: <?php echo $disq_election['election_year']; ?>
                                    </small>
                                </div>
                                <div class="col-md-4 text-md-end">
                                    <span class="badge bg-danger fs-6">
                                        <i class="fas fa-ban me-1"></i>Disqualified
                                    </span>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div class="mt-3 p-3 bg-white rounded border">
                    <h6 class="text-danger mb-2">
                        <i class="fas fa-info-circle me-2"></i>What this means:
                    </h6>
                    <ul class="mb-0 text-muted">
                        <li>You <strong>cannot vote</strong> in the disqualified election(s)</li>
                        <li>Contact your invigilator if you believe this is an error</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <style>
    .disqualified-election-item {
        transition: all 0.3s ease;
    }

    .disqualified-election-item:hover {
        background-color: #f8f9fa !important;
        transform: translateY(-2px);
        box-shadow: 0 4px 8px rgba(0,0,0,0.1);
    }

    .alert-icon {
        flex-shrink: 0;
    }

    .border-start.border-danger {
        border-left-width: 4px !important;
    }
    </style>
<?php endif; ?>

<div class="row">
    <div class="col-12">
        <h1 class="mb-4">Welcome, <?php echo htmlspecialchars($current_user['fname']); ?>!</h1>
    </div>
</div>


<!-- Election Hub -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-vote-yea me-2"></i>Election Hub</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <div class="d-grid gap-2">
                            <?php if ($class_elections_active): ?>
                                <button class="btn btn-outline-secondary" disabled>
                                    <i class="fas fa-hand-paper me-2"></i>Apply as Candidate
                                    <small class="d-block">Class elections are currently active - applications closed</small>
                                </button>
                            <?php elseif ($class_elections_paused): ?>
                                <button class="btn btn-outline-secondary" disabled>
                                    <i class="fas fa-pause-circle me-2"></i>Apply as Candidate
                                    <small class="d-block">Class elections are currently paused - applications closed</small>
                                </button>
                            <?php elseif (!empty($can_apply_elections) && !($class_elections_over && !$is_union_eligible)): ?>
                                <a href="apply_candidate.php" class="btn btn-success">
                                    <i class="fas fa-hand-paper me-2"></i>Apply as Candidate
                                    <span class="badge bg-light text-success ms-2"><?php echo count($can_apply_elections); ?> Available</span>
                                </a>
                            <?php else: ?>
                                <button class="btn btn-outline-secondary" disabled>
                                    <i class="fas fa-hand-paper me-2"></i>Apply as Candidate
                                    <small class="d-block">
                                        <?php if ($class_elections_over && !$is_union_eligible): ?>
                                            Class elections are over
                                        <?php else: ?>
                                            No elections available for application
                                        <?php endif; ?>
                                    </small>
                                </button>
                            <?php endif; ?>

                            <?php
                            // Calculate pending votes based on election mode
                            $pending_votes = 0;
                            $has_votable_elections = false;

                            if ($election_mode === 'union') {
                                foreach ($eligible_elections as $election) {
                                    if (isset($union_voting_status[$election['election_id']]) &&
                                        $union_voting_status[$election['election_id']]['has_pending_votes']) {
                                        $pending_votes++;
                                        $has_votable_elections = true;
                                    }
                                }
                            } else {
                                // Class elections
                                foreach ($eligible_elections as $election) {
                                    if (!in_array($election['election_id'], $voted_elections) &&
                                        $election['voting_status'] === 'active') {
                                        $pending_votes++;
                                        $has_votable_elections = true;
                                    }
                                }
                            }

                            if ($has_votable_elections):
                            ?>
                                <a href="vote.php" class="btn btn-primary">
                                    <i class="fas fa-vote-yea me-2"></i>Vote Now
                                    <span class="badge bg-light text-primary ms-2"><?php echo $pending_votes; ?> Pending</span>
                                </a>
                            <?php else: ?>
                                <button class="btn btn-outline-secondary" disabled>
                                    <i class="fas fa-vote-yea me-2"></i>Vote Now
                                    <small class="d-block">No pending votes</small>
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="d-grid gap-2">
                            <?php
                            // Check for published class results
                            $stmt = $pdo->prepare("
                                SELECT results_published FROM elections
                                WHERE election_year = ? AND election_type_id = 1 AND class_id = ? AND results_published = 1
                            ");
                            $stmt->execute([date('Y'), $current_user['class_id']]);
                            $has_published_class_results = $stmt->fetch() ? true : false;
                            
                            // Check for published union results if student is union-eligible and union election exists
                            $has_published_union_results = false;
                            if ($is_union_eligible && $union_election_exists) {
                                $stmt = $pdo->prepare("
                                    SELECT results_published FROM elections
                                    WHERE election_year = ? AND election_type_id = 2 AND results_published = 1
                                ");
                                $stmt->execute([date('Y')]);
                                $has_published_union_results = $stmt->fetch() ? true : false;
                            }
                            ?>

                            <?php if ($has_published_class_results): ?>
                                <a href="election_results.php" class="btn btn-outline-primary">
                                    <i class="fas fa-chart-bar me-2"></i>Class Results
                                    <span class="badge bg-primary ms-2">Published</span>
                                </a>
                            <?php else: ?>
                                <button class="btn btn-outline-secondary" disabled>
                                    <i class="fas fa-chart-bar me-2"></i>Class Results
                                    <small class="d-block">Not published yet</small>
                                </button>
                            <?php endif; ?>
                            
                            <?php if ($is_union_eligible && $union_election_exists): ?>
                                <a href="union_elections.php" class="btn btn-outline-info">
                                    <i class="fas fa-university me-2"></i>Union Elections
                                </a>
                                
                                <?php if ($has_published_union_results): ?>
                                    <a href="union_results.php" class="btn btn-outline-success">
                                        <i class="fas fa-trophy me-2"></i>Union Results
                                        <span class="badge bg-success ms-2">Published</span>
                                    </a>
                                <?php else: ?>
                                    <button class="btn btn-outline-secondary" disabled>
                                        <i class="fas fa-trophy me-2"></i>Union Results
                                        <small class="d-block">Not published yet</small>
                                    </button>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <div class="d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="fas fa-vote-yea me-2"></i>Available Elections</h5>
                    <?php if (!empty($disqualified_elections)): ?>
                        <span class="badge bg-warning">
                            <i class="fas fa-exclamation-triangle me-1"></i>
                            <?php echo count($disqualified_elections); ?> Disqualified
                        </span>
                    <?php endif; ?>
                </div>
            </div>
            <div class="card-body">
                <?php if (empty($eligible_elections)): ?>
                    <div class="text-center py-4">
                        <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                        <h5 class="text-muted">No Available Elections</h5>
                        <?php if (!empty($disqualified_elections)): ?>
                            <p class="text-muted">You are disqualified from all active elections.</p>
                        <?php else: ?>
                            <p class="text-muted">There are currently no active elections for your class.</p>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Election Type</th>
                                    <th>Year</th>
                                    <th>Scope</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($eligible_elections as $election): ?>
                                    <tr>
                                        <td>
                                            <i class="fas fa-vote-yea me-2"></i>
                                            <?php echo ucfirst($election['election_type_name']); ?> Election
                                        </td>
                                        <td><?php echo $election['election_year']; ?></td>
                                        <td>
                                            <?php if ($election['class_id']): ?>
                                                <span class="badge bg-info"><?php echo htmlspecialchars($election['class_name']); ?></span>
                                            <?php else: ?>
                                                <span class="badge bg-primary">Union Wide</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php
                                            // Display proper status labels
                                            switch ($election['voting_status']) {
                                                case 'not_started':
                                                    echo '<span class="badge bg-secondary"><i class="fas fa-clock me-1"></i>Not Started</span>';
                                                    break;
                                                case 'active':
                                                    if ($election_mode === 'union') {
                                                        // Always show "Started" for union elections, regardless of voting status
                                                        echo '<span class="badge bg-primary"><i class="fas fa-play me-1"></i>Started</span>';
                                                    } else {
                                                        if (in_array($election['election_id'], $voted_elections)) {
                                                            echo '<span class="badge bg-success"><i class="fas fa-check me-1"></i>Voted</span>';
                                                        } else {
                                                            echo '<span class="badge bg-primary"><i class="fas fa-play me-1"></i>Started</span>';
                                                        }
                                                    }
                                                    break;
                                                case 'paused':
                                                    echo '<span class="badge bg-warning"><i class="fas fa-pause me-1"></i>Paused</span>';
                                                    break;
                                                case 'ended':
                                                    echo '<span class="badge bg-dark"><i class="fas fa-stop me-1"></i>Ended</span>';
                                                    break;
                                                default:
                                                    echo '<span class="badge bg-secondary"><i class="fas fa-question me-1"></i>' . ucfirst($election['voting_status']) . '</span>';
                                                    break;
                                            }
                                            ?>
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

<!-- Candidate Applications -->
<?php if (!empty($candidate_applications)): ?>
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-hand-paper me-2"></i>Your Candidate Applications</h5>
            </div>
            <div class="card-body">
                <!-- Desktop/Tablet Table View -->
                <div class="d-none d-md-block">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Election</th>
                                    <th>Position</th>
                                    <th>Status</th>
                                    <th class="text-center">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($candidate_applications as $app): ?>
                                    <tr>
                                        <td>
                                            <div>
                                                <strong><?php echo ucfirst($app['election_type_name']); ?> Election</strong>
                                                <br><small class="text-muted"><?php echo $app['election_year']; ?></small>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="fw-medium"><?php echo htmlspecialchars($app['position_name']); ?></span>
                                        </td>
                                        <td>
                                            <?php if ($app['is_approved'] === 'pending'): ?>
                                                <span class="badge bg-warning">
                                                    <i class="fas fa-clock me-1"></i>Pending Review
                                                </span>
                                            <?php elseif ($app['is_approved'] === 'approved'): ?>
                                                <span class="badge bg-success">
                                                    <i class="fas fa-check-circle me-1"></i>Approved
                                                </span>
                                            <?php elseif ($app['is_approved'] === 'rejected'): ?>
                                                <span class="badge bg-danger">
                                                    <i class="fas fa-times-circle me-1"></i>Rejected
                                                </span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary">
                                                    <i class="fas fa-question-circle me-1"></i>Unknown
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-center">
                                            <a href="application_details.php?id=<?php echo $app['candidate_id']; ?>"
                                               class="btn btn-sm btn-outline-primary">
                                                <i class="fas fa-eye me-1"></i>View Details
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Mobile Card View -->
                <div class="d-block d-md-none">
                    <?php foreach ($candidate_applications as $index => $app): ?>
                        <div class="card mb-3 border-start border-3 <?php 
                            echo $app['is_approved'] === 'approved' ? 'border-success' : 
                                ($app['is_approved'] === 'rejected' ? 'border-danger' : 'border-warning'); 
                        ?>">
                            <div class="card-body p-3">
                                <!-- Election Info -->
                                <div class="d-flex justify-content-between align-items-start mb-2">
                                    <div>
                                        <h6 class="card-title mb-1">
                                            <i class="fas fa-vote-yea me-2 text-primary"></i>
                                            <?php echo ucfirst($app['election_type_name']); ?> Election
                                        </h6>
                                        <small class="text-muted">
                                            <i class="fas fa-calendar me-1"></i><?php echo $app['election_year']; ?>
                                        </small>
                                    </div>
                                    <!-- Status Badge -->
                                    <div>
                                        <?php if ($app['is_approved'] === 'pending'): ?>
                                            <span class="badge bg-warning">
                                                <i class="fas fa-clock me-1"></i>Pending
                                            </span>
                                        <?php elseif ($app['is_approved'] === 'approved'): ?>
                                            <span class="badge bg-success">
                                                <i class="fas fa-check-circle me-1"></i>Approved
                                            </span>
                                        <?php elseif ($app['is_approved'] === 'rejected'): ?>
                                            <span class="badge bg-danger">
                                                <i class="fas fa-times-circle me-1"></i>Rejected
                                            </span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">
                                                <i class="fas fa-question-circle me-1"></i>Unknown
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <!-- Position -->
                                <div class="mb-3">
                                    <div class="d-flex align-items-center">
                                        <i class="fas fa-user-tie me-2 text-info"></i>
                                        <span class="fw-medium"><?php echo htmlspecialchars($app['position_name']); ?></span>
                                    </div>
                                </div>

                                <!-- Action Button -->
                                <div class="d-grid">
                                    <a href="application_details.php?id=<?php echo $app['candidate_id']; ?>"
                                       class="btn btn-outline-primary btn-sm">
                                        <i class="fas fa-eye me-2"></i>View Application Details
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <?php if (!empty($can_apply_elections)): ?>
                    <div class="mt-3">
                        <div class="alert alert-info">
                            <div class="d-flex flex-column flex-sm-row align-items-start align-items-sm-center">
                                <div class="flex-grow-1 mb-2 mb-sm-0">
                                    <i class="fas fa-info-circle me-2"></i>
                                    <strong>New Elections Available!</strong>
                                    <span class="d-block d-sm-inline">
                                        You can apply for <?php echo count($can_apply_elections); ?> more election(s).
                                    </span>
                                </div>
                                <div class="d-grid d-sm-block">
                                    <a href="apply_candidate.php" class="btn btn-success btn-sm">
                                        <i class="fas fa-hand-paper me-1"></i>
                                        <span class="d-none d-sm-inline">Apply Now</span>
                                        <span class="d-inline d-sm-none">Apply for Elections</span>
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Custom CSS for Enhanced Mobile Experience -->
<style>
@media (max-width: 767.98px) {
    .card-body {
        padding: 1rem !important;
    }
    
    .badge {
        font-size: 0.75rem;
        padding: 0.35em 0.65em;
    }
    
    .btn-sm {
        padding: 0.375rem 0.75rem;
        font-size: 0.875rem;
    }
    
    .card-title {
        font-size: 1rem;
        line-height: 1.4;
    }
    
    .border-start {
        border-left-width: 4px !important;
    }
}

@media (min-width: 768px) and (max-width: 991.98px) {
    .table td, .table th {
        padding: 0.75rem 0.5rem;
        font-size: 0.9rem;
    }
    
    .btn-sm {
        padding: 0.25rem 0.5rem;
        font-size: 0.8rem;
    }
}

/* Hover effects for better interactivity */
.card:hover {
    transform: translateY(-2px);
    transition: transform 0.2s ease-in-out;
    box-shadow: 0 4px 8px rgba(0,0,0,0.1);
}

.table-hover tbody tr:hover {
    background-color: rgba(0,123,255,0.05);
}

/* Status badge improvements */
.badge {
    font-weight: 500;
    letter-spacing: 0.025em;
}

.badge i {
    font-size: 0.85em;
}

/* Button improvements */
.btn-outline-primary:hover {
    transform: translateY(-1px);
    transition: transform 0.15s ease-in-out;
}

.d-grid .btn {
    border-radius: 0.375rem;
}

/* Alert improvements for mobile */
@media (max-width: 575.98px) {
    .alert {
        padding: 1rem;
        border-radius: 0.5rem;
    }
    
    .alert .btn {
        margin-top: 0.5rem;
        width: 100%;
    }
}
</style>
<?php endif; ?>

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
