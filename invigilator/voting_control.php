<?php
session_start();
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/config/database.php';

requireRole('Invigilator');

$page_title = 'Voting Control';
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

// Get elections for assigned classes
$elections = [];
if (!empty($assigned_classes)) {
    $class_ids = array_column($assigned_classes, 'class_id');
    $placeholders = str_repeat('?,', count($class_ids) - 1) . '?';

    // Check if voting_status column exists
    $stmt = $pdo->query("SHOW COLUMNS FROM elections LIKE 'voting_status'");
    $voting_column_exists = $stmt->fetch();
    
    $select_fields = "e.*, et.election_type_name, c.class_name, d.department_name";
    if ($voting_column_exists) {
        $select_fields .= ", e.voting_status";
    }

    $stmt = $pdo->prepare("
        SELECT {$select_fields}
        FROM elections e
        JOIN election_types et ON e.election_type_id = et.election_type_id
        JOIN classes c ON e.class_id = c.class_id
        JOIN departments d ON c.department_id = d.department_id
        WHERE e.class_id IN ($placeholders)
        AND et.election_type_name = 'class'
        ORDER BY e.election_year DESC, c.class_name
    ");
    $stmt->execute($class_ids);
    $elections = $stmt->fetchAll();

    // Get voting statistics for each election
    foreach ($elections as &$election) {
        // Get total students in class
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as total_students
            FROM users u
            JOIN roles r ON u.role_id = r.role_id
            WHERE u.class_id = ? AND r.role_name = 'Student'
        ");
        $stmt->execute([$election['class_id']]);
        $election['total_students'] = $stmt->fetchColumn();

        // Get students who have voted for both categories
        $stmt = $pdo->prepare("
            SELECT COUNT(DISTINCT voter_id) as voted_students
            FROM votes
            WHERE election_id = ?
            GROUP BY voter_id
            HAVING COUNT(DISTINCT gender_category) = 2
        ");
        $stmt->execute([$election['election_id']]);
        $election['fully_voted_students'] = $stmt->rowCount();

        // Calculate voting progress
        $election['voting_progress'] = $election['total_students'] > 0 ?
            round(($election['fully_voted_students'] / $election['total_students']) * 100, 1) : 0;
    }
}

include dirname(__DIR__) . '/includes/header.php';
?>

<div class="row">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1>Voting Control</h1>
            <a href="dashboard.php" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-1"></i>Back to Dashboard
            </a>
        </div>
    </div>
</div>

<?php if (empty($assigned_classes)): ?>
    <div class="row">
        <div class="col-12">
            <div class="alert alert-info">
                <i class="fas fa-info-circle me-2"></i>
                You are not assigned to any classes for the current year (<?php echo $current_year; ?>).
                Please contact the Election Commissioner for class assignments.
            </div>
        </div>
    </div>
<?php elseif (empty($elections)): ?>
    <div class="row">
        <div class="col-12">
            <div class="alert alert-info">
                <i class="fas fa-info-circle me-2"></i>
                No class elections found for your assigned classes in <?php echo $current_year; ?>.
            </div>
        </div>
    </div>
<?php else: ?>
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-vote-yea me-2"></i>Class Elections Voting Control</h5>
                </div>
                <div class="card-body">
                    <?php if (!isset($elections[0]['voting_status'])): ?>
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            Voting control feature not yet enabled. Please contact the administrator to add the database column.
                            <br><small>Required: <code>voting_status</code> column in elections table</small>
                        </div>
                    <?php endif; ?>
                    
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Class</th>
                                    <th>Department</th>
                                    <th>Voting Status</th>
                                    <th>Progress</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($elections as $election): ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo htmlspecialchars($election['class_name']); ?></strong>
                                        </td>
                                        <td><?php echo htmlspecialchars($election['department_name']); ?></td>
                                        <td>
                                            <?php 
                                            $voting_status = $election['voting_status'] ?? 'not_started';
                                            $status_config = [
                                                'not_started' => ['badge' => 'bg-secondary', 'icon' => 'fas fa-clock', 'text' => 'Not Started'],
                                                'active' => ['badge' => 'bg-success', 'icon' => 'fas fa-play', 'text' => 'Active'],
                                                'paused' => ['badge' => 'bg-warning', 'icon' => 'fas fa-pause', 'text' => 'Paused'],
                                                'ended' => ['badge' => 'bg-danger', 'icon' => 'fas fa-stop', 'text' => 'Ended']
                                            ];
                                            $config = $status_config[$voting_status];
                                            ?>
                                            <span class="badge <?php echo $config['badge']; ?>">
                                                <i class="<?php echo $config['icon']; ?> me-1"></i>
                                                <?php echo $config['text']; ?>
                                            </span>
                                            <?php if ($voting_status === 'ended' && $election['voting_progress'] >= 100): ?>
                                                <br><small class="text-muted">Auto-ended</small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <div class="progress me-2" style="width: 80px; height: 20px;">
                                                    <div class="progress-bar" role="progressbar"
                                                         style="width: <?php echo $election['voting_progress']; ?>%"
                                                         aria-valuenow="<?php echo $election['voting_progress']; ?>"
                                                         aria-valuemin="0" aria-valuemax="100">
                                                    </div>
                                                </div>
                                                <small class="text-muted">
                                                    <?php echo $election['fully_voted_students']; ?>/<?php echo $election['total_students']; ?>
                                                    (<?php echo $election['voting_progress']; ?>%)
                                                </small>
                                            </div>
                                        </td>
                                        <td>
                                            <?php if (isset($election['voting_status']) && $election['is_active']): ?>
                                                <div class="btn-group" role="group">
                                                    <?php if ($voting_status === 'not_started' || $voting_status === 'paused'): ?>
                                                        <button class="btn btn-sm btn-success control-voting-btn"
                                                                data-election-id="<?php echo $election['election_id']; ?>"
                                                                data-action="start"
                                                                data-class-name="<?php echo htmlspecialchars($election['class_name']); ?>">
                                                            <i class="fas fa-play me-1"></i>Start Voting
                                                        </button>
                                                    <?php endif; ?>
                                                    
                                                    <?php if ($voting_status === 'active'): ?>
                                                        <button class="btn btn-sm btn-warning control-voting-btn"
                                                                data-election-id="<?php echo $election['election_id']; ?>"
                                                                data-action="pause"
                                                                data-class-name="<?php echo htmlspecialchars($election['class_name']); ?>">
                                                            <i class="fas fa-pause me-1"></i>Pause
                                                        </button>
                                                        <button class="btn btn-sm btn-danger control-voting-btn"
                                                                data-election-id="<?php echo $election['election_id']; ?>"
                                                                data-action="end"
                                                                data-class-name="<?php echo htmlspecialchars($election['class_name']); ?>">
                                                            <i class="fas fa-stop me-1"></i>End Voting
                                                        </button>
                                                    <?php endif; ?>
                                                </div>
                                            <?php else: ?>
                                                <?php if (!$election['is_active']): ?>
                                                    <span class="text-muted">Election Inactive</span>
                                                <?php else: ?>
                                                    <span class="text-muted">Feature Not Available</span>
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
// Handle voting control buttons
document.querySelectorAll('.control-voting-btn').forEach(btn => {
    btn.addEventListener('click', function() {
        const electionId = this.dataset.electionId;
        const action = this.dataset.action;
        const className = this.dataset.className;
        
        const actionTexts = {
            'start': 'start voting',
            'pause': 'pause voting',
            'end': 'end voting'
        };
        
        const confirmMessage = `Are you sure you want to ${actionTexts[action]} for ${className}?`;
        
        if (!confirm(confirmMessage)) {
            return;
        }
        
        const originalText = this.innerHTML;
        this.disabled = true;
        this.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Processing...';
        
        const formData = new FormData();
        formData.append('action', 'control_voting');
        formData.append('election_id', electionId);
        formData.append('voting_action', action);
        
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
                this.innerHTML = originalText;
            }
        })
        .catch(error => {
            showAlert('Network error. Please try again.', 'danger');
            this.disabled = false;
            this.innerHTML = originalText;
        });
    });
});

function showAlert(message, type) {
    const alertDiv = document.createElement('div');
    alertDiv.className = `alert alert-${type} alert-dismissible fade show`;
    alertDiv.innerHTML = `
        <i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-circle'} me-2"></i>
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;
    
    const container = document.querySelector('.row').parentNode;
    container.insertBefore(alertDiv, container.firstChild);
    
    // Auto-dismiss after 5 seconds
    setTimeout(() => {
        if (alertDiv.parentNode) {
            alertDiv.remove();
        }
    }, 5000);
}
</script>

<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
