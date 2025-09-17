<?php
session_start();
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/config/database.php';

requireRole('Student');

$page_title = 'Vote';

$user_id = $_SESSION['user_id'];

// Get student information
$stmt = $pdo->prepare("
    SELECT u.*, c.class_name, d.department_name, c.class_id, d.department_id
    FROM users u
    JOIN classes c ON u.class_id = c.class_id
    JOIN departments d ON c.department_id = d.department_id
    WHERE u.user_id = ?
");
$stmt->execute([$user_id]);
$student = $stmt->fetch();

if (!$student) {
    header('Location: dashboard.php');
    exit();
}

// Check if voting_status column exists
$stmt = $pdo->query("SHOW COLUMNS FROM elections LIKE 'voting_status'");
$voting_column_exists = $stmt->fetch();

// Get active elections for this student's class
$select_fields = "e.*, et.election_type_name";
if ($voting_column_exists) {
    $select_fields .= ", e.voting_status";
}

// Check if student is union-eligible first
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
$stmt->execute([$user_id, date('Y')]);
$is_union_eligible = $stmt->fetchColumn() > 0;

// Check for active union elections only if student is eligible
if ($is_union_eligible) {
    $stmt = $pdo->prepare("
        SELECT {$select_fields}
        FROM elections e
        JOIN election_types et ON e.election_type_id = et.election_type_id
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
            SELECT {$select_fields}
            FROM elections e
            JOIN election_types et ON e.election_type_id = et.election_type_id
            WHERE e.class_id = ? AND e.is_active = 1 AND et.election_type_name = 'class'
            ORDER BY e.election_year DESC
        ");
        $stmt->execute([$student['class_id']]);
        $active_elections = $stmt->fetchAll();
        $election_mode = 'class';
    }
} else {
    // Otherwise, show class elections for student's class
    $stmt = $pdo->prepare("
        SELECT {$select_fields}
        FROM elections e
        JOIN election_types et ON e.election_type_id = et.election_type_id
        WHERE e.class_id = ? AND e.is_active = 1 AND et.election_type_name = 'class'
        ORDER BY e.election_year DESC
    ");
    $stmt->execute([$student['class_id']]);
    $active_elections = $stmt->fetchAll();
    $election_mode = 'class';
}

// Check for disqualifications
$disqualified_elections = [];
if (!empty($active_elections)) {
    $election_ids = array_column($active_elections, 'election_id');
    $placeholders = str_repeat('?,', count($election_ids) - 1) . '?';

    $stmt = $pdo->prepare("
        SELECT election_id
        FROM election_disqualifications
        WHERE student_id = ? AND election_id IN ($placeholders)
    ");
    $stmt->execute(array_merge([$user_id], $election_ids));
    $disqualified_elections = array_column($stmt->fetchAll(), 'election_id');
}

// Filter out disqualified elections
$eligible_elections = array_filter($active_elections, function($election) use ($disqualified_elections) {
    return !in_array($election['election_id'], $disqualified_elections);
});

// Get voting status for each eligible election based on current mode
$voting_data = [];
foreach ($eligible_elections as $election) {
    if ($election_mode === 'class') {
        // Handle class elections (existing logic)
        $stmt = $pdo->prepare("
            SELECT c.*, u.fname, u.lname, u.roll_number, u.gender, p.position_name, p.position_type
            FROM candidates c
            JOIN users u ON c.user_id = u.user_id
            JOIN positions p ON c.position_id = p.position_id
            WHERE c.election_id = ? AND c.is_approved = 'approved'
            ORDER BY p.position_type, u.fname, u.lname
        ");
        $stmt->execute([$election['election_id']]);
        $candidates = $stmt->fetchAll();

        // Group candidates by gender (Girls/Boys)
        $girls_candidates = array_filter($candidates, fn($c) => $c['gender'] === 'F');
        $boys_candidates = array_filter($candidates, fn($c) => $c['gender'] === 'M');

        // Check if student has already voted for Girls Representative
        $stmt = $pdo->prepare("
            SELECT v.vote_id
            FROM votes v
            WHERE v.voter_id = ? AND v.election_id = ? AND v.gender_category = 'girls'
        ");
        $stmt->execute([$user_id, $election['election_id']]);
        $voted_for_girls = $stmt->fetch() ? true : false;

        // Check if student has already voted for Boys Representative
        $stmt = $pdo->prepare("
            SELECT v.vote_id
            FROM votes v
            WHERE v.voter_id = ? AND v.election_id = ? AND v.gender_category = 'boys'
        ");
        $stmt->execute([$user_id, $election['election_id']]);
        $voted_for_boys = $stmt->fetch() ? true : false;

        $voting_data[] = [
            'type' => 'class',
            'election' => $election,
            'girls_candidates' => $girls_candidates,
            'boys_candidates' => $boys_candidates,
            'voted_for_girls' => $voted_for_girls,
            'voted_for_boys' => $voted_for_boys,
            'can_vote_girls' => !$voted_for_girls && ($election['voting_status'] ?? 'active') === 'active',
            'can_vote_boys' => !$voted_for_boys && ($election['voting_status'] ?? 'active') === 'active'
        ];
    } elseif ($election_mode === 'union') {
        // Handle union elections with position-based voting
        // Only show positions that are currently active for voting
        $stmt = $pdo->prepare("
            SELECT p.*,
                   COUNT(c.candidate_id) as candidate_count
            FROM positions p
            LEFT JOIN candidates c ON p.position_id = c.position_id
                AND c.election_id = ? AND c.is_approved = 'approved'
            WHERE p.election_type_id = 2 AND p.is_active = 1
            GROUP BY p.position_id
            ORDER BY p.voting_order
        ");
        $stmt->execute([$election['election_id']]);
        $active_positions = $stmt->fetchAll();

        $union_positions = [];
        foreach ($active_positions as $position) {
            // Get candidates for this position
            $stmt = $pdo->prepare("
                SELECT c.*, u.fname, u.lname, u.roll_number, u.gender
                FROM candidates c
                JOIN users u ON c.user_id = u.user_id
                WHERE c.election_id = ? AND c.position_id = ? AND c.is_approved = 'approved'
                ORDER BY u.fname, u.lname
            ");
            $stmt->execute([$election['election_id'], $position['position_id']]);
            $candidates = $stmt->fetchAll();

            // Check if student has already voted for this position
            $stmt = $pdo->prepare("
                SELECT v.vote_id
                FROM votes v
                WHERE v.voter_id = ? AND v.election_id = ? AND v.position_id = ?
            ");
            $stmt->execute([$user_id, $election['election_id'], $position['position_id']]);
            $has_voted = $stmt->fetch() ? true : false;

            $union_positions[] = [
                'position' => $position,
                'candidates' => $candidates,
                'has_voted' => $has_voted,
                'can_vote' => !$has_voted &&
                             ($election['voting_status'] ?? 'active') === 'active' &&
                             count($candidates) > 0
            ];
        }

        $voting_data[] = [
            'type' => 'union',
            'election' => $election,
            'positions' => $union_positions,
            'can_vote' => ($election['voting_status'] ?? 'active') === 'active'
        ];
    }
}

include dirname(__DIR__) . '/includes/header.php';
?>

<?php if (!empty($disqualified_elections)): ?>
    <!-- Disqualification Notice -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="alert alert-danger">
                <h5 class="alert-heading">
                    <i class="fas fa-user-slash me-2"></i>Voting Disqualification Notice
                </h5>
                <p class="mb-0">
                    You are disqualified from voting in <?php echo count($disqualified_elections); ?> election(s).
                    Only elections you are eligible for are shown below.
                </p>
            </div>
        </div>
    </div>
<?php endif; ?>

<div class="row">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1>
                <?php if ($election_mode === 'union'): ?>
                    Vote in Union Elections
                <?php else: ?>
                    Vote for Class Representatives
                <?php endif; ?>
            </h1>
            <a href="dashboard.php" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-1"></i>Back to Dashboard
            </a>
        </div>
    </div>
</div>


<!-- Voting Sections -->
<?php if (empty($voting_data)): ?>
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body text-center py-5">
                    <i class="fas fa-vote-yea fa-3x text-muted mb-3"></i>
                    <?php if (!empty($disqualified_elections)): ?>
                        <h5 class="text-muted">No Available Elections</h5>
                        <p class="text-muted">
                            <?php if ($election_mode === 'union'): ?>
                                You are disqualified from all active union elections.
                            <?php else: ?>
                                You are disqualified from all active elections for your class.
                            <?php endif; ?>
                        </p>
                    <?php else: ?>
                        <h5 class="text-muted">No Active Elections</h5>
                        <p class="text-muted">
                            <?php if ($election_mode === 'union'): ?>
                                There are currently no active union elections available for voting.
                            <?php else: ?>
                                There are no active class elections for your class at this time.
                            <?php endif; ?>
                        </p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
<?php else: ?>

    <?php foreach ($voting_data as $data): ?>
        <div class="row mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <div class="d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">
                                <i class="fas fa-vote-yea me-2"></i>
                                <?php if ($data['type'] === 'class'): ?>
                                    Class Representative Election - <?php echo $data['election']['election_year']; ?>
                                <?php else: ?>
                                    Union Election - <?php echo $data['election']['election_year']; ?>
                                <?php endif; ?>
                            </h5>
                            <?php
                            $voting_status = $data['election']['voting_status'] ?? 'active';
                            $status_config = [
                                'not_started' => ['badge' => 'bg-secondary', 'icon' => 'fas fa-clock', 'text' => 'Not Started'],
                                'active' => ['badge' => 'bg-success', 'icon' => 'fas fa-play', 'text' => 'Voting Active'],
                                'paused' => ['badge' => 'bg-warning', 'icon' => 'fas fa-pause', 'text' => 'Voting Paused'],
                                'ended' => ['badge' => 'bg-danger', 'icon' => 'fas fa-stop', 'text' => 'Voting Ended']
                            ];
                            $config = $status_config[$voting_status];
                            ?>
                            <span class="badge <?php echo $config['badge']; ?>">
                                <i class="<?php echo $config['icon']; ?> me-1"></i>
                                <?php echo $config['text']; ?>
                            </span>
                        </div>
                    </div>
                    <div class="card-body">
                        <?php if ($voting_status !== 'active'): ?>
                            <div class="alert alert-warning">
                                <i class="fas fa-exclamation-triangle me-2"></i>
                                <?php if ($voting_status === 'not_started'): ?>
                                    <strong>Voting has not started yet.</strong><br>
                                    Please wait for the commissioner to begin the voting process.
                                <?php elseif ($voting_status === 'paused'): ?>
                                    <strong>The election has been temporarily paused.</strong><br>
                                    The commissioner has paused the voting process. Please wait for the election to resume.
                                <?php elseif ($voting_status === 'ended'): ?>
                                    <strong>Voting has concluded.</strong><br>
                                    This election has ended and no further votes can be cast. Results will be published soon.
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>

                        <?php if ($data['type'] === 'class'): ?>
                            <!-- Class Election Voting (existing logic) -->
                        <div class="row">
                            <!-- Girls Representative Section -->
                            <div class="col-md-6">
                                <div class="card h-100">
                                    <div class="card-header bg-pink text-white">
                                        <h6 class="mb-0">
                                            <i class="fas fa-female me-2"></i>Girls Representative
                                        </h6>
                                    </div>
                                    <div class="card-body">
                                        <?php if ($data['voted_for_girls']): ?>
                                            <div class="alert alert-success">
                                                <i class="fas fa-check-circle me-2"></i>
                                                You have already voted for Girls Representative.
                                            </div>
                                        <?php elseif ($voting_status !== 'active'): ?>
                                            <div class="alert alert-secondary">
                                                <i class="fas fa-clock me-2"></i>
                                                Voting is not currently active.
                                            </div>
                                        <?php else: ?>
                                            <form class="voting-form" data-election-id="<?php echo $data['election']['election_id']; ?>" data-vote-type="girls">
                                                <p class="text-muted mb-3">Select one option:</p>

                                                <?php if (!empty($data['girls_candidates'])): ?>
                                                    <?php foreach ($data['girls_candidates'] as $candidate): ?>
                                                        <div class="form-check mb-3">
                                                            <input class="form-check-input" type="radio"
                                                                   name="vote_choice"
                                                                   value="candidate_<?php echo $candidate['candidate_id']; ?>"
                                                                   id="girl_<?php echo $candidate['candidate_id']; ?>">
                                                            <label class="form-check-label" for="girl_<?php echo $candidate['candidate_id']; ?>">
                                                                <div class="d-flex align-items-center">
                                                                    <div class="avatar-sm bg-pink text-white rounded-circle d-flex align-items-center justify-content-center me-3">
                                                                        <?php echo strtoupper(substr($candidate['fname'], 0, 1) . substr($candidate['lname'], 0, 1)); ?>
                                                                    </div>
                                                                    <div>
                                                                        <strong><?php echo htmlspecialchars($candidate['fname'] . ' ' . $candidate['lname']); ?></strong>
                                                                        <br><small class="text-muted"><?php echo htmlspecialchars($candidate['roll_number']); ?></small>
                                                                    </div>
                                                                </div>
                                                            </label>
                                                        </div>
                                                    <?php endforeach; ?>
                                                <?php else: ?>
                                                    <div class="alert alert-info mb-3">
                                                        <i class="fas fa-info-circle me-2"></i>
                                                        No girls candidates available.
                                                    </div>
                                                <?php endif; ?>

                                                <!-- Nil Vote Option -->
                                                <div class="form-check mb-3">
                                                    <input class="form-check-input" type="radio"
                                                           name="vote_choice"
                                                           value="nil"
                                                           id="girls_nil">
                                                    <label class="form-check-label" for="girls_nil">
                                                        <div class="d-flex align-items-center">
                                                            <div class="avatar-sm bg-secondary text-white rounded-circle d-flex align-items-center justify-content-center me-3">
                                                                <i class="fas fa-ban"></i>
                                                            </div>
                                                            <div>
                                                                <strong>Nil Vote</strong>
                                                                <br><small class="text-muted">I don't want to vote for any candidate</small>
                                                            </div>
                                                        </div>
                                                    </label>
                                                </div>

                                                <button type="submit" class="btn btn-success w-100">
                                                    <i class="fas fa-vote-yea me-1"></i>Vote for Girls Representative
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>

                            <!-- Boys Representative Section -->
                            <div class="col-md-6">
                                <div class="card h-100">
                                    <div class="card-header bg-blue text-white">
                                        <h6 class="mb-0">
                                            <i class="fas fa-male me-2"></i>Boys Representative
                                        </h6>
                                    </div>
                                    <div class="card-body">
                                        <?php if ($data['voted_for_boys']): ?>
                                            <div class="alert alert-success">
                                                <i class="fas fa-check-circle me-2"></i>
                                                You have already voted for Boys Representative.
                                            </div>
                                        <?php elseif ($voting_status !== 'active'): ?>
                                            <div class="alert alert-secondary">
                                                <i class="fas fa-clock me-2"></i>
                                                Voting is not currently active.
                                            </div>
                                        <?php else: ?>
                                            <form class="voting-form" data-election-id="<?php echo $data['election']['election_id']; ?>" data-vote-type="boys">
                                                <p class="text-muted mb-3">Select one option:</p>

                                                <?php if (!empty($data['boys_candidates'])): ?>
                                                    <?php foreach ($data['boys_candidates'] as $candidate): ?>
                                                        <div class="form-check mb-3">
                                                            <input class="form-check-input" type="radio"
                                                                   name="vote_choice"
                                                                   value="candidate_<?php echo $candidate['candidate_id']; ?>"
                                                                   id="boy_<?php echo $candidate['candidate_id']; ?>">
                                                            <label class="form-check-label" for="boy_<?php echo $candidate['candidate_id']; ?>">
                                                                <div class="d-flex align-items-center">
                                                                    <div class="avatar-sm bg-blue text-white rounded-circle d-flex align-items-center justify-content-center me-3">
                                                                        <?php echo strtoupper(substr($candidate['fname'], 0, 1) . substr($candidate['lname'], 0, 1)); ?>
                                                                    </div>
                                                                    <div>
                                                                        <strong><?php echo htmlspecialchars($candidate['fname'] . ' ' . $candidate['lname']); ?></strong>
                                                                        <br><small class="text-muted"><?php echo htmlspecialchars($candidate['roll_number']); ?></small>
                                                                    </div>
                                                                </div>
                                                            </label>
                                                        </div>
                                                    <?php endforeach; ?>
                                                <?php else: ?>
                                                    <div class="alert alert-info mb-3">
                                                        <i class="fas fa-info-circle me-2"></i>
                                                        No boys candidates available.
                                                    </div>
                                                <?php endif; ?>

                                                <!-- Nil Vote Option -->
                                                <div class="form-check mb-3">
                                                    <input class="form-check-input" type="radio"
                                                           name="vote_choice"
                                                           value="nil"
                                                           id="boys_nil">
                                                    <label class="form-check-label" for="boys_nil">
                                                        <div class="d-flex align-items-center">
                                                            <div class="avatar-sm bg-secondary text-white rounded-circle d-flex align-items-center justify-content-center me-3">
                                                                <i class="fas fa-ban"></i>
                                                            </div>
                                                            <div>
                                                                <strong>Nil Vote</strong>
                                                                <br><small class="text-muted">I don't want to vote for any candidate</small>
                                                            </div>
                                                        </div>
                                                    </label>
                                                </div>

                                                <button type="submit" class="btn btn-success w-100">
                                                    <i class="fas fa-vote-yea me-1"></i>Vote for Boys Representative
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <?php elseif ($data['type'] === 'union'): ?>
                            <!-- Union Election Voting (position-based) -->
                            <?php if ($voting_status === 'active' && !empty($data['positions'])): ?>

                                <?php foreach ($data['positions'] as $position_data): ?>
                                    <div class="card mb-3">
                                        <div class="card-header">
                                            <div class="d-flex justify-content-between align-items-center">
                                                <h6 class="mb-0">
                                                    <span class="badge bg-primary me-2"><?php echo $position_data['position']['voting_order']; ?></span>
                                                    <?php echo htmlspecialchars($position_data['position']['position_name']); ?>
                                                </h6>
                                                <?php if ($position_data['has_voted']): ?>
                                                    <span class="badge bg-success">
                                                        <i class="fas fa-check me-1"></i>Voted
                                                    </span>
                                                <?php elseif ($position_data['can_vote']): ?>
                                                    <span class="badge bg-warning">
                                                        <i class="fas fa-clock me-1"></i>Pending
                                                    </span>
                                                <?php else: ?>
                                                    <span class="badge bg-secondary">
                                                        <i class="fas fa-pause me-1"></i>Inactive
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <div class="card-body">
                                            <?php if ($position_data['has_voted']): ?>
                                                <div class="alert alert-success">
                                                    <i class="fas fa-check-circle me-2"></i>
                                                    You have already voted for this position.
                                                </div>
                                            <?php elseif (!$position_data['can_vote']): ?>
                                                <div class="alert alert-secondary">
                                                    <i class="fas fa-pause me-2"></i>
                                                    Voting is not currently active for this position.
                                                </div>
                                            <?php else: ?>
                                                <form class="union-voting-form"
                                                      data-election-id="<?php echo $data['election']['election_id']; ?>"
                                                      data-position-id="<?php echo $position_data['position']['position_id']; ?>">
                                                    <p class="text-muted mb-3">Select one candidate:</p>

                                                    <?php if (!empty($position_data['candidates'])): ?>
                                                        <?php foreach ($position_data['candidates'] as $candidate): ?>
                                                            <div class="form-check mb-3">
                                                                <input class="form-check-input" type="radio"
                                                                       name="vote_choice"
                                                                       value="candidate_<?php echo $candidate['candidate_id']; ?>"
                                                                       id="union_<?php echo $candidate['candidate_id']; ?>">
                                                                <label class="form-check-label" for="union_<?php echo $candidate['candidate_id']; ?>">
                                                                    <div class="d-flex align-items-center">
                                                                        <div class="avatar-sm bg-primary text-white rounded-circle d-flex align-items-center justify-content-center me-3">
                                                                            <?php echo strtoupper(substr($candidate['fname'], 0, 1) . substr($candidate['lname'], 0, 1)); ?>
                                                                        </div>
                                                                        <div>
                                                                            <strong><?php echo htmlspecialchars($candidate['fname'] . ' ' . $candidate['lname']); ?></strong>
                                                                            <br><small class="text-muted"><?php echo htmlspecialchars($candidate['roll_number']); ?></small>
                                                                        </div>
                                                                    </div>
                                                                </label>
                                                            </div>
                                                        <?php endforeach; ?>
                                                    <?php else: ?>
                                                        <div class="alert alert-info mb-3">
                                                            <i class="fas fa-info-circle me-2"></i>
                                                            No candidates available for this position.
                                                        </div>
                                                    <?php endif; ?>

                                                    <!-- Nil Vote Option -->
                                                    <div class="form-check mb-3">
                                                        <input class="form-check-input" type="radio"
                                                               name="vote_choice"
                                                               value="nil"
                                                               id="nil_<?php echo $position_data['position']['position_id']; ?>">
                                                        <label class="form-check-label" for="nil_<?php echo $position_data['position']['position_id']; ?>">
                                                            <div class="d-flex align-items-center">
                                                                <div class="avatar-sm bg-secondary text-white rounded-circle d-flex align-items-center justify-content-center me-3">
                                                                    <i class="fas fa-ban"></i>
                                                                </div>
                                                                <div>
                                                                    <strong>Nil Vote</strong>
                                                                    <br><small class="text-muted">I choose not to vote for any candidate</small>
                                                                </div>
                                                            </div>
                                                        </label>
                                                    </div>

                                                    <button type="submit" class="btn btn-primary">
                                                        <i class="fas fa-vote-yea me-2"></i>Cast Vote
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="alert alert-info">
                                    <i class="fas fa-info-circle me-2"></i>
                                    <?php if ($voting_status !== 'active'): ?>
                                        Union election voting is not currently active.
                                    <?php else: ?>
                                        No active positions available for voting at this time.
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
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

    // Handle voting form submissions
    document.querySelectorAll('.voting-form').forEach(form => {
        form.addEventListener('submit', function(e) {
            e.preventDefault();

            const electionId = this.dataset.electionId;
            const genderCategory = this.dataset.voteType; // This contains 'girls' or 'boys'
            const selectedChoice = this.querySelector('input[name="vote_choice"]:checked');

            if (!selectedChoice) {
                showAlert('Please select an option before voting.', 'warning');
                return;
            }

            const choice = selectedChoice.value;
            const genderText = genderCategory === 'girls' ? 'Girls' : 'Boys';

            let confirmMessage;
            if (choice === 'nil') {
                confirmMessage = `Are you sure you want to cast a Nil vote for ${genderText} Representative? This action cannot be undone.`;
            } else {
                confirmMessage = `Are you sure you want to vote for this candidate as ${genderText} Representative? This action cannot be undone.`;
            }

            if (!confirm(confirmMessage)) {
                return;
            }

            const formData = new FormData();
            formData.append('action', 'cast_vote');
            formData.append('election_id', electionId);
            formData.append('gender_category', genderCategory);
            formData.append('vote_choice', choice);

            const submitBtn = this.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;

            submitBtn.disabled = true;
            submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Voting...';

            fetch('vote_handler.php', {
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
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = originalText;
                }
            })
            .catch(error => {
                showAlert('Network error. Please try again.', 'danger');
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalText;
            });
        });
    });

    // Handle union election voting form submissions
    document.querySelectorAll('.union-voting-form').forEach(form => {
        form.addEventListener('submit', function(e) {
            e.preventDefault();

            const electionId = this.dataset.electionId;
            const positionId = this.dataset.positionId;
            const selectedChoice = this.querySelector('input[name="vote_choice"]:checked');

            if (!selectedChoice) {
                showAlert('Please select a candidate or nil vote.', 'warning');
                return;
            }

            const submitBtn = this.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;

            submitBtn.disabled = true;
            submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Casting Vote...';

            const formData = new FormData();
            formData.append('action', 'cast_union_vote');
            formData.append('election_id', electionId);
            formData.append('position_id', positionId);
            formData.append('vote_choice', selectedChoice.value);

            fetch('union_vote_handler.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showAlert(data.message, 'success');
                    setTimeout(() => {
                        window.location.reload();
                    }, 2000);
                } else {
                    showAlert(data.message, 'danger');
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = originalText;
                }
            })
            .catch(error => {
                showAlert('Network error. Please try again.', 'danger');
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalText;
            });
        });
    });
});
</script>

<style>
.avatar-sm {
    width: 40px;
    height: 40px;
    font-size: 0.875rem;
    font-weight: bold;
}

.bg-pink {
    background-color: #e91e63 !important;
}

.bg-blue {
    background-color: #2196f3 !important;
}

.form-check-label {
    cursor: pointer;
    width: 100%;
}

.form-check-input:checked + .form-check-label {
    background-color: #f8f9fa;
    border-radius: 0.375rem;
    padding: 0.5rem;
}
</style>

<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
