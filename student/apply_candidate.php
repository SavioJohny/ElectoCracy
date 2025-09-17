<?php
session_start();
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/config/database.php';

requireRole('Student');

$page_title = 'Apply as Candidate';

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

// Get active class elections for this student's class
$select_fields = "e.*, et.election_type_name";
if ($voting_column_exists) {
    $select_fields .= ", e.voting_status";
}

$stmt = $pdo->prepare("
    SELECT {$select_fields}
    FROM elections e
    JOIN election_types et ON e.election_type_id = et.election_type_id
    WHERE e.class_id = ? AND e.is_active = 1 AND et.election_type_name = 'class'
    ORDER BY e.election_year DESC
");
$stmt->execute([$student['class_id']]);
$active_elections = $stmt->fetchAll();

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

// Get student's existing applications
$stmt = $pdo->prepare("
    SELECT c.*, e.election_year, p.position_name
    FROM candidates c
    JOIN elections e ON c.election_id = e.election_id
    JOIN positions p ON c.position_id = p.position_id
    WHERE c.user_id = ?
    ORDER BY e.election_year DESC
");
$stmt->execute([$user_id]);
$existing_applications = $stmt->fetchAll();

// Get available positions for class elections (only Class Representative)
$stmt = $pdo->query("
    SELECT position_id, position_name, position_type
    FROM positions
    WHERE position_type = 'Class Representative'
    AND is_active = 1
    ORDER BY position_name
");
$class_positions = $stmt->fetchAll();

include dirname(__DIR__) . '/includes/header.php';
?>

<?php if (!empty($disqualified_elections)): ?>
    <!-- Disqualification Notice -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="alert alert-danger">
                <h5 class="alert-heading">
                    <i class="fas fa-user-slash me-2"></i>Candidate Application Disqualification Notice
                </h5>
                <p class="mb-0">
                    You are disqualified from applying as a candidate in <?php echo count($disqualified_elections); ?> election(s).
                    Only elections you are eligible for are shown below.
                </p>
            </div>
        </div>
    </div>
<?php endif; ?>

<div class="row">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1>Apply as Candidate - 2025</h1>
            <a href="dashboard.php" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-1"></i>Back to Dashboard
            </a>
        </div>
    </div>
</div>


<!-- Active Elections -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-vote-yea me-2"></i>Election Information</h5>
            </div>
            <div class="card-body">
                <?php if (empty($eligible_elections)): ?>
                    <div class="text-center py-3">
                        <i class="fas fa-vote-yea fa-2x text-muted mb-2"></i>
                        <?php if (!empty($disqualified_elections)): ?>
                            <p class="text-muted mb-0">You are disqualified from all active elections for your class.</p>
                        <?php else: ?>
                            <p class="text-muted mb-0">No active elections available for your class.</p>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <?php foreach ($eligible_elections as $election): ?>
                        <?php
                        $voting_status = $election['voting_status'] ?? 'not_started';
                        $status_config = [
                            'not_started' => ['badge' => 'bg-secondary', 'icon' => 'fas fa-clock', 'text' => 'Applications Open', 'can_apply' => true],
                            'active' => ['badge' => 'bg-success', 'icon' => 'fas fa-play', 'text' => 'Voting Active', 'can_apply' => false],
                            'paused' => ['badge' => 'bg-warning', 'icon' => 'fas fa-pause', 'text' => 'Election Paused', 'can_apply' => false],
                            'ended' => ['badge' => 'bg-danger', 'icon' => 'fas fa-stop', 'text' => 'Election Ended', 'can_apply' => false]
                        ];
                        $config = $status_config[$voting_status];
                        ?>
                        <div class="card mb-3">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <h6 class="card-title">
                                            Class Election - <?php echo $election['election_year']; ?>
                                            <span class="badge <?php echo $config['badge']; ?> ms-2">
                                                <i class="<?php echo $config['icon']; ?> me-1"></i>
                                                <?php echo $config['text']; ?>
                                            </span>
                                        </h6>
                                        <p class="card-text">
                                            <strong>Available Positions:</strong> Class Representative (Girls & Boys)
                                        </p>

                                        <?php if (!$config['can_apply']): ?>
                                            <div class="alert alert-info mb-0">
                                                <i class="fas fa-info-circle me-2"></i>
                                                <?php if ($voting_status === 'active'): ?>
                                                    Candidate applications are closed. Voting is currently in progress.
                                                <?php elseif ($voting_status === 'paused'): ?>
                                                    The election has been temporarily paused by the invigilator. Please wait for further updates.
                                                <?php elseif ($voting_status === 'ended'): ?>
                                                    This election has concluded. No further applications are accepted.
                                                <?php endif; ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <div>
                                        <?php if (!$config['can_apply']): ?>
                            <span class="badge bg-secondary">
                                <i class="fas fa-ban me-1"></i>Applications Closed
                            </span>
                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Application Form -->
<?php if (!empty($eligible_elections)): ?>
    <?php $can_apply_election = null; ?>
    <?php foreach ($eligible_elections as $election): ?>
        <?php if (($election['voting_status'] ?? 'not_started') === 'not_started'): ?>
            <?php $can_apply_election = $election; break; ?>
        <?php endif; ?>
    <?php endforeach; ?>
    
    <?php if ($can_apply_election): ?>
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-hand-paper me-2"></i>Apply as Candidate - <?php echo $can_apply_election['election_year']; ?></h5>
                </div>
                <div class="card-body">
                    
                    <form id="applicationForm" enctype="multipart/form-data" class="needs-validation" novalidate>
                        <input type="hidden" name="election_id" value="<?php echo $can_apply_election['election_id']; ?>">
                        <input type="hidden" name="position_id" value="<?php echo !empty($class_positions) ? $class_positions[0]['position_id'] : ''; ?>">
                        
                        <div class="mb-3">
                            <label for="marksheet" class="form-label">Previous Semester Marksheet <span class="text-danger">*</span></label>
                            <input type="file" class="form-control" id="marksheet" name="marksheet" 
                                   accept=".pdf,.jpg,.jpeg,.png" required>
                            <div class="form-text">Upload PDF or image file (max 5MB)</div>
                            <div class="invalid-feedback">Please upload your marksheet.</div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="attendance" class="form-label">Previous Semester Attendance <span class="text-danger">*</span></label>
                            <input type="file" class="form-control" id="attendance" name="attendance" 
                                   accept=".pdf,.jpg,.jpeg,.png" required>
                            <div class="form-text">Upload PDF or image file (max 5MB)</div>
                            <div class="invalid-feedback">Please upload your attendance record.</div>
                        </div>
                        
                        <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-paper-plane me-1"></i>Submit Application
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
<?php endif; ?>

<!-- Existing Applications -->
<?php if (!empty($existing_applications)): ?>
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-history me-2"></i>Your Applications</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Election Year</th>
                                <th>Position</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($existing_applications as $app): ?>
                                <tr>
                                    <td><?php echo $app['election_year']; ?></td>
                                    <td><?php echo htmlspecialchars($app['position_name']); ?></td>
                                    <td>
                                        <?php if ($app['is_approved'] === 'pending'): ?>
                                            <span class="badge bg-warning">
                                                <i class="fas fa-clock me-1"></i>Pending
                                            </span>
                                        <?php elseif ($app['is_approved'] === 'approved'): ?>
                                            <span class="badge bg-success">
                                                <i class="fas fa-check me-1"></i>Approved
                                            </span>
                                        <?php elseif ($app['is_approved'] === 'rejected'): ?>
                                            <span class="badge bg-danger">
                                                <i class="fas fa-times me-1"></i>Rejected
                                            </span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">
                                                <i class="fas fa-question me-1"></i>Unknown
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="btn-group" role="group">
                                            <a href="application_details.php?id=<?php echo $app['candidate_id']; ?>"
                                               class="btn btn-sm btn-outline-primary"
                                               title="View Application Details">
                                                <i class="fas fa-eye"></i> View
                                            </a>
                                            <?php if ($app['is_approved'] === 'pending'): ?>
                                                <span class="btn btn-sm btn-outline-secondary disabled">
                                                    <i class="fas fa-clock"></i> Awaiting Review
                                                </span>
                                            <?php elseif ($app['is_approved'] === 'approved'): ?>
                                                <span class="btn btn-sm btn-outline-success disabled">
                                                    <i class="fas fa-check"></i> Approved
                                                </span>
                                            <?php elseif ($app['is_approved'] === 'rejected'): ?>
                                                <span class="btn btn-sm btn-outline-danger disabled">
                                                    <i class="fas fa-times"></i> Rejected
                                                </span>
                                            <?php endif; ?>
                                        </div>
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
    const form = document.getElementById('applicationForm');

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

    // Handle form submission
    if (form) {
        const submitBtn = form.querySelector('button[type="submit"]');
        
        if (submitBtn) {
            submitBtn.addEventListener('click', function(e) {
                e.preventDefault();

                if (!form.checkValidity()) {
                    form.classList.add('was-validated');
                    showAlert('Please upload the required documents.', 'danger');
                    return;
                }

                // Validate file sizes
                const marksheet = document.getElementById('marksheet').files[0];
                const attendance = document.getElementById('attendance').files[0];

                if (marksheet && marksheet.size > 5 * 1024 * 1024) {
                    showAlert('Marksheet file size must be less than 5MB.', 'danger');
                    return;
                }

                if (attendance && attendance.size > 5 * 1024 * 1024) {
                    showAlert('Attendance file size must be less than 5MB.', 'danger');
                    return;
                }

                const formData = new FormData(form);
                formData.append('action', 'apply_candidate');

                const originalText = submitBtn.innerHTML;

                submitBtn.disabled = true;
                submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Submitting...';

                fetch('candidate_handler.php', {
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
                })
                .finally(() => {
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = originalText;
                });
            });
        }
    }
});
</script>

<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
