<?php
session_start();
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/config/database.php';

requireRole('Election Commissioner');

$page_title = 'Union Voting Control';

// Get current year
$current_year = date('Y');

// Get union election for current year
$stmt = $pdo->prepare("
    SELECT e.*, et.election_type_name,
           COUNT(DISTINCT cand.candidate_id) as candidate_count,
           COUNT(DISTINCT v.vote_id) as vote_count,
           COUNT(DISTINCT CASE WHEN cand.is_approved = 'approved' THEN cand.candidate_id END) as approved_candidates
    FROM elections e
    JOIN election_types et ON e.election_type_id = et.election_type_id
    LEFT JOIN candidates cand ON e.election_id = cand.election_id
    LEFT JOIN votes v ON e.election_id = v.election_id
    WHERE e.election_year = ? AND et.election_type_name = 'union'
    GROUP BY e.election_id
    ORDER BY e.election_id DESC
");
$stmt->execute([$current_year]);
$union_election = $stmt->fetch();

// Get all union positions
$union_positions = [];
$union_candidates = [];

if ($union_election) {
    $stmt = $pdo->prepare("
        SELECT * FROM positions
        WHERE election_type_id = 2
        ORDER BY voting_order
    ");
    $stmt->execute();
    $union_positions = $stmt->fetchAll();

    // Get union election candidates
    $stmt = $pdo->prepare("
        SELECT 
            cand.*,
            u.fname,
            u.lname,
            u.email,
            u.gender,
            p.position_name,
            p.voting_order,
            c.class_name,
            d.department_name,
            COUNT(v.vote_id) as vote_count
        FROM candidates cand
        JOIN users u ON cand.user_id = u.user_id
        JOIN positions p ON cand.position_id = p.position_id
        LEFT JOIN classes c ON u.class_id = c.class_id
        LEFT JOIN departments d ON c.department_id = d.department_id
        LEFT JOIN votes v ON cand.candidate_id = v.candidate_id
        WHERE cand.election_id = ?
        GROUP BY cand.candidate_id
        ORDER BY p.voting_order, u.fname
    ");
    $stmt->execute([$union_election['election_id']]);
    $union_candidates = $stmt->fetchAll();
}

include dirname(__DIR__) . '/includes/header.php';
?>

<div class="row">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1>Union Voting Control</h1>
            <a href="dashboard.php" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-1"></i>Back to Dashboard
            </a>
        </div>
    </div>
</div>

<?php if (!$union_election): ?>
    <div class="row">
        <div class="col-12">
            <div class="alert alert-info">
                <i class="fas fa-info-circle me-2"></i>
                No union election found for the current year (<?php echo $current_year; ?>).
                Please create a union election first.
                <div class="mt-2">
                    <a href="union_elections.php" class="btn btn-primary btn-sm">
                        <i class="fas fa-university me-1"></i>Manage Union Elections
                    </a>
                </div>
            </div>
        </div>
    </div>
<?php else: ?>
    <!-- Election Status Card -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="fas fa-university me-2"></i>Union Election - <?php echo $current_year; ?></h5>
                    <div>
                        <span class="badge bg-<?php echo $union_election['is_active'] ? 'success' : 'secondary'; ?> me-2">
                            <?php echo $union_election['is_active'] ? 'Active' : 'Inactive'; ?>
                        </span>
                        <span class="badge bg-<?php echo $union_election['voting_status'] == 'active' ? 'primary' : 'secondary'; ?>">
                            Voting: <?php echo ucfirst(str_replace('_', ' ', $union_election['voting_status'])); ?>
                        </span>
                    </div>
                </div>
                <div class="card-body">
                    <div class="row text-center">
                        <div class="col-md-4">
                            <div class="border-end">
                                <h4 class="text-primary"><?php echo count($union_positions); ?></h4>
                                <small class="text-muted">Total Positions</small>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="border-end">
                                <h4 class="text-success"><?php echo $union_election['approved_candidates']; ?></h4>
                                <small class="text-muted">Approved Candidates</small>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="text-center">
                                <?php if ($union_election['voting_status'] == 'ended'): ?>
                                    <span class="badge bg-secondary fs-6 py-2 px-3">
                                        <i class="fas fa-flag-checkered me-1"></i>Election Ended
                                    </span>
                                <?php else: ?>
                                    <div class="btn-group" role="group">
                                        <button class="btn btn-outline-<?php echo $union_election['voting_status'] == 'active' ? 'warning' : 'success'; ?> election-voting-control-btn"
                                                data-action="<?php echo $union_election['voting_status'] == 'active' ? 'pause' : ($union_election['voting_status'] == 'paused' ? 'resume' : 'start'); ?>"
                                                data-election-id="<?php echo $union_election['election_id']; ?>">
                                            <i class="fas fa-<?php echo $union_election['voting_status'] == 'active' ? 'pause' : 'play'; ?> me-1"></i>
                                            <?php
                                            echo match($union_election['voting_status']) {
                                                'active' => 'Pause Election',
                                                'paused' => 'Resume Election',
                                                default => 'Start Election'
                                            };
                                            ?>
                                        </button>
                                        <?php if ($union_election['voting_status'] == 'active' || $union_election['voting_status'] == 'paused'): ?>
                                            <button class="btn btn-outline-danger election-voting-control-btn"
                                                    data-action="end"
                                                    data-election-id="<?php echo $union_election['election_id']; ?>">
                                                <i class="fas fa-stop me-1"></i>End Election
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Position-by-Position Voting Control -->
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-vote-yea me-2"></i>Position-by-Position Voting Control</h5>
                </div>
                <div class="card-body">

                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Order</th>
                                    <th>Position</th>
                                    <th>Candidates</th>
                                    <th>Approved</th>
                                    <th>Status</th>
                                    <th>Votes Cast</th>
                                    <th class="text-center">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($union_positions as $position): ?>
                                    <?php
                                    $position_candidates = array_filter($union_candidates, function($c) use ($position) {
                                        return $c['position_id'] == $position['position_id'];
                                    });
                                    $approved_candidates = array_filter($position_candidates, function($c) {
                                        return $c['is_approved'] == 'approved';
                                    });

                                    // Get vote count for this position
                                    $position_votes = 0;
                                    foreach ($position_candidates as $candidate) {
                                        $position_votes += $candidate['vote_count'];
                                    }

                                    $position_active = $position['is_active'];
                                    ?>
                                    <tr>
                                        <td>
                                            <span class="badge bg-primary"><?php echo $position['voting_order']; ?></span>
                                        </td>
                                        <td>
                                            <strong><?php echo htmlspecialchars($position['position_name']); ?></strong>
                                        </td>
                                        <td>
                                            <span class="badge bg-info"><?php echo count($position_candidates); ?></span>
                                        </td>
                                        <td>
                                            <span class="badge bg-success"><?php echo count($approved_candidates); ?></span>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?php echo $position_active ? 'success' : 'warning'; ?>">
                                                <?php echo $position_active ? 'Active' : 'Inactive'; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge bg-primary"><?php echo $position_votes; ?></span>
                                        </td>
                                        <td class="text-center">
                                            <?php if ($position_active): ?>
                                                <button class="btn btn-outline-warning btn-sm toggle-position-status-btn"
                                                        data-position-id="<?php echo $position['position_id']; ?>"
                                                        data-position-name="<?php echo htmlspecialchars($position['position_name']); ?>"
                                                        data-action="deactivate">
                                                    <i class="fas fa-pause me-1"></i>Deactivate
                                                </button>
                                            <?php else: ?>
                                                <?php if ($union_election['voting_status'] != 'active'): ?>
                                                    <span class="text-muted">Election must be active</span>
                                                <?php elseif (count($approved_candidates) == 0): ?>
                                                    <span class="text-muted">No approved candidates</span>
                                                <?php else: ?>
                                                    <button class="btn btn-outline-success btn-sm toggle-position-status-btn"
                                                            data-position-id="<?php echo $position['position_id']; ?>"
                                                            data-position-name="<?php echo htmlspecialchars($position['position_name']); ?>"
                                                            data-action="activate"
                                                            data-candidates="<?php echo count($approved_candidates); ?>">
                                                        <i class="fas fa-play me-1"></i>Activate
                                                    </button>
                                                <?php endif; ?>
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
<?php endif; ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Define showAlert function
    function showAlert(message, type = 'info') {
        const alertDiv = document.createElement('div');
        alertDiv.className = `alert alert-${type} alert-dismissible fade show`;
        alertDiv.innerHTML = `
            <i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-circle'} me-2"></i>
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        `;

        const container = document.querySelector('.row').parentNode;
        container.insertBefore(alertDiv, container.firstChild);

        setTimeout(() => {
            if (alertDiv.parentNode) {
                alertDiv.remove();
            }
        }, 5000);
    }

    // Election-level voting control handler
    document.querySelectorAll('.election-voting-control-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const action = this.dataset.action;
            const electionId = this.dataset.electionId;

            let confirmMessage = '';
            switch(action) {
                case 'start':
                    confirmMessage = 'Are you sure you want to start the union election?\n\nThis will allow commissioners to activate positions and students to vote for active positions.';
                    break;
                case 'pause':
                    confirmMessage = 'Are you sure you want to pause the union election?\n\nThis will stop all voting immediately, but students can still apply for inactive positions.';
                    break;
                case 'resume':
                    confirmMessage = 'Are you sure you want to resume the union election?\n\nThis will allow voting to continue for active positions.';
                    break;
                case 'end':
                    confirmMessage = 'Are you sure you want to END the union election?\n\nWARNING: This action cannot be undone!\n\nEnding the election will:\n• Stop all voting immediately\n• Deactivate all positions\n• Finalize all results\n• Prevent any further voting or changes\n\nOnly end the election when you are certain all voting is complete.';
                    break;
                default:
                    confirmMessage = `Are you sure you want to ${action} the union election?`;
            }

            if (confirm(confirmMessage)) {
                const formData = new FormData();
                formData.append('action', 'control_election_voting');
                formData.append('election_id', electionId);
                formData.append('voting_action', action);

                this.disabled = true;
                this.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Processing...';

                fetch('voting_control_handler.php', {
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
                        this.innerHTML = this.dataset.originalText || 'Control Election';
                    }
                })
                .catch(error => {
                    showAlert('Network error. Please try again.', 'danger');
                    this.disabled = false;
                    this.innerHTML = this.dataset.originalText || 'Control Election';
                });
            }
        });
    });

    // Position status toggle handlers
    document.querySelectorAll('.toggle-position-status-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const positionId = this.dataset.positionId;
            const positionName = this.dataset.positionName;
            const action = this.dataset.action;
            const candidates = this.dataset.candidates || 0;

            let confirmMessage = '';
            if (action === 'activate') {
                confirmMessage = `Are you sure you want to activate voting for ${positionName}?\n\nThis will allow students to vote for this position with ${candidates} approved candidates.\n\nMake sure all candidates have given their speeches.`;
            } else {
                confirmMessage = `Are you sure you want to deactivate voting for ${positionName}?\n\nThis will immediately stop all voting for this position.`;
            }

            if (confirm(confirmMessage)) {
                togglePositionStatus(positionId, action, positionName);
            }
        });
    });

    // Position status toggle function
    function togglePositionStatus(positionId, action, positionName) {
        const formData = new FormData();
        formData.append('action', 'toggle_position_status');
        formData.append('position_id', positionId);
        formData.append('status_action', action);

        fetch('voting_control_handler.php', {
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
            }
        })
        .catch(error => {
            showAlert('Network error. Please try again.', 'danger');
        });
    }
});
</script>

<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
