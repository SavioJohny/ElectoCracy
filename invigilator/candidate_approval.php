<?php
session_start();
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/config/database.php';

requireRole('Invigilator');

$page_title = 'Candidate Approval';

$user_id = $_SESSION['user_id'];

// Get classes assigned to this invigilator
$current_year = date('Y');
$stmt = $pdo->prepare("
    SELECT DISTINCT c.class_id, c.class_name, d.department_name, ica.election_year
    FROM invigilator_class_assignments ica
    JOIN classes c ON ica.class_id = c.class_id
    JOIN departments d ON c.department_id = d.department_id
    WHERE ica.invigilator_id = ? AND ica.election_year = ?
    ORDER BY d.department_name, c.class_name
");
$stmt->execute([$user_id, $current_year]);
$assigned_classes = $stmt->fetchAll();

// Get filter parameters
$gender_filter = $_GET['gender'] ?? '';

// Build query conditions
$where_conditions = ["e.is_active = 1", "et.election_type_name = 'class'"];
$params = [];

// Filter by assigned classes
if (!empty($assigned_classes)) {
    $class_ids = array_column($assigned_classes, 'class_id');
    $placeholders = str_repeat('?,', count($class_ids) - 1) . '?';
    $where_conditions[] = "e.class_id IN ($placeholders)";
    $params = array_merge($params, $class_ids);
}

if (!empty($gender_filter)) {
    $where_conditions[] = "u.gender = ?";
    $params[] = $gender_filter;
}

$where_clause = implode(' AND ', $where_conditions);

// Check if voting_status column exists
$stmt = $pdo->query("SHOW COLUMNS FROM elections LIKE 'voting_status'");
$voting_column_exists = $stmt->fetch();

// Get candidate applications with voting status
$select_fields = "c.*, u.fname, u.lname, u.roll_number, u.email, u.gender,
           cl.class_name, d.department_name, p.position_name,
           e.election_year, e.election_id";
if ($voting_column_exists) {
    $select_fields .= ", e.voting_status";
}

$stmt = $pdo->prepare("
    SELECT {$select_fields}
    FROM candidates c
    JOIN users u ON c.user_id = u.user_id
    JOIN elections e ON c.election_id = e.election_id
    JOIN election_types et ON e.election_type_id = et.election_type_id
    JOIN classes cl ON e.class_id = cl.class_id
    JOIN departments d ON cl.department_id = d.department_id
    JOIN positions p ON c.position_id = p.position_id
    WHERE $where_clause
");
$stmt->execute($params);
$candidates = $stmt->fetchAll();

include dirname(__DIR__) . '/includes/header.php';
?>

<div class="row">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1>Candidate Approval</h1>
            <a href="dashboard.php" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-1"></i>Back to Dashboard
            </a>
        </div>
    </div>
</div>

<!-- Statistics Cards -->
<div class="row mb-4">
    <?php
    $pending_count = count(array_filter($candidates, fn($c) => $c['is_approved'] === 'pending'));
    $approved_count = count(array_filter($candidates, fn($c) => $c['is_approved'] === 'approved'));
    $rejected_count = count(array_filter($candidates, fn($c) => $c['is_approved'] === 'rejected'));
    ?>
    <div class="col-md-3">
        <div class="card bg-warning text-white">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <h4><?php echo $pending_count; ?></h4>
                        <p class="mb-0">Pending Review</p>
                    </div>
                    <div class="align-self-center">
                        <i class="fas fa-clock fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card bg-success text-white">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <h4><?php echo $approved_count; ?></h4>
                        <p class="mb-0">Approved</p>
                    </div>
                    <div class="align-self-center">
                        <i class="fas fa-check-circle fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card bg-danger text-white">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <h4><?php echo $rejected_count; ?></h4>
                        <p class="mb-0">Rejected</p>
                    </div>
                    <div class="align-self-center">
                        <i class="fas fa-times-circle fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card bg-info text-white">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <h4><?php echo count($candidates); ?></h4>
                        <p class="mb-0">Total Applications</p>
                    </div>
                    <div class="align-self-center">
                        <i class="fas fa-users fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Filters -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <div class="col-md-6">
                        <label for="gender" class="form-label">Filter by Gender</label>
                        <select class="form-select" id="gender" name="gender" onchange="this.form.submit()">
                            <option value="">All Genders</option>
                            <option value="M" <?php echo $gender_filter === 'M' ? 'selected' : ''; ?>>Male</option>
                            <option value="F" <?php echo $gender_filter === 'F' ? 'selected' : ''; ?>>Female</option>
                            <option value="O" <?php echo $gender_filter === 'O' ? 'selected' : ''; ?>>Other</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">&nbsp;</label>
                        <div>
                            <a href="?" class="btn btn-outline-secondary">
                                <i class="fas fa-refresh me-1"></i>Clear 
                            </a>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Candidates List -->
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-users me-2"></i>Candidate Applications</h5>
            </div>
            <div class="card-body">
                <?php if (empty($candidates)): ?>
                    <div class="text-center py-5">
                        <i class="fas fa-users fa-3x text-muted mb-3"></i>
                        <h5 class="text-muted">No candidate applications found</h5>
                        <p class="text-muted">
                            <?php if (empty($assigned_classes)): ?>
                                You are not assigned to any classes for this election year.
                            <?php else: ?>
                                No applications match your current filters.
                            <?php endif; ?>
                        </p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Student</th>
                                    <th>Class</th>
                                    <th class="text-center">Status</th>
                                    <th class="text-center">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($candidates as $candidate): ?>
                                    <tr>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <div class="avatar-sm bg-<?php echo $candidate['gender'] === 'F' ? 'pink' : 'blue'; ?> text-white rounded-circle d-flex align-items-center justify-content-center me-3">
                                                    <?php echo strtoupper(substr($candidate['fname'], 0, 1) . substr($candidate['lname'], 0, 1)); ?>
                                                </div>
                                                <div>
                                                    <strong><?php echo htmlspecialchars($candidate['fname'] . ' ' . $candidate['lname']); ?></strong>
                                                    <br><small class="text-muted"><?php echo htmlspecialchars($candidate['roll_number']); ?></small>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="badge bg-info"><?php echo htmlspecialchars($candidate['class_name']); ?></span>
                                            <br><small class="text-muted"><?php echo htmlspecialchars($candidate['department_name']); ?></small>
                                        </td>
                                        <td class="text-center">
                                            <?php if ($candidate['is_approved'] === 'pending'): ?>
                                                <span class="badge bg-warning">Pending</span>
                                            <?php elseif ($candidate['is_approved'] === 'approved'): ?>
                                                <span class="badge bg-success">Approved</span>
                                            <?php elseif ($candidate['is_approved'] === 'rejected'): ?>
                                                <span class="badge bg-danger">Rejected</span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary">Unknown</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-center">
                                            <?php 
                                            $voting_status = $candidate['voting_status'] ?? 'not_started';
                                            $is_locked = in_array($voting_status, ['active', 'paused', 'ended']);
                                            ?>
                                            <div class="d-flex justify-content-center align-items-center">
                                                <?php if ($is_locked): ?>
                                                    <span class="text-muted me-2" title="Candidate approval locked during voting">
                                                        <i class="fas fa-lock"></i>
                                                    </span>
                                                <?php endif; ?>
                                                
                                                <div class="btn-group btn-group-sm">
                                                    <button class="btn btn-outline-primary view-candidate-btn" 
                                                            data-id="<?php echo $candidate['candidate_id']; ?>"
                                                            title="View Details">
                                                        <i class="fas fa-eye"></i>
                                                    </button>
                                                    
                                                    <?php if (!$is_locked): ?>
                                                        <?php if ($candidate['is_approved'] === 'pending'): ?>
                                                            <button class="btn btn-outline-success approve-btn"
                                                                    data-id="<?php echo $candidate['candidate_id']; ?>"
                                                                    title="Approve">
                                                                <i class="fas fa-check"></i>
                                                            </button>
                                                            <button class="btn btn-outline-danger reject-btn"
                                                                    data-id="<?php echo $candidate['candidate_id']; ?>"
                                                                    title="Reject">
                                                                <i class="fas fa-times"></i>
                                                            </button>
                                                        <?php elseif ($candidate['is_approved'] !== 'pending'): ?>
                                                            <button class="btn btn-outline-secondary reset-btn"
                                                                    data-id="<?php echo $candidate['candidate_id']; ?>"
                                                                    title="Reset to Pending">
                                                                <i class="fas fa-undo"></i>
                                                            </button>
                                                        <?php endif; ?>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
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

<!-- Candidate Details Modal -->
<div class="modal fade" id="candidateModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-user me-2"></i>Candidate Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="candidateDetails">
                <div class="text-center">
                    <div class="spinner-border" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <div id="approvalButtons" style="display: none;">
                    <button type="button" class="btn btn-success" id="approveCandidate">
                        <i class="fas fa-check me-1"></i>Approve
                    </button>
                    <button type="button" class="btn btn-danger" id="rejectCandidate">
                        <i class="fas fa-times me-1"></i>Reject
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    let currentCandidateId = null;

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

    // Handle view candidate buttons
    document.querySelectorAll('.view-candidate-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const candidateId = this.dataset.id;
            currentCandidateId = candidateId;

            // Show modal
            const modal = new bootstrap.Modal(document.getElementById('candidateModal'));
            modal.show();

            // Load candidate details
            loadCandidateDetails(candidateId);
        });
    });

    function loadCandidateDetails(candidateId) {
        const detailsDiv = document.getElementById('candidateDetails');
        const approvalButtons = document.getElementById('approvalButtons');

        detailsDiv.innerHTML = `
            <div class="text-center">
                <div class="spinner-border" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
            </div>
        `;

        fetch('candidate_details_handler.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `action=get_candidate_details&candidate_id=${candidateId}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                detailsDiv.innerHTML = data.html;

                // Show approval buttons if candidate is pending
                if (data.candidate.is_approved === 'pending') {
                    approvalButtons.style.display = 'block';
                } else {
                    approvalButtons.style.display = 'none';
                }
            } else {
                detailsDiv.innerHTML = `<div class="alert alert-danger">${data.message}</div>`;
            }
        })
        .catch(error => {
            detailsDiv.innerHTML = '<div class="alert alert-danger">Error loading candidate details.</div>';
        });
    }

    // Handle direct approve/reject/reset buttons
    document.querySelectorAll('.approve-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const candidateId = this.dataset.id;
            if (confirm('Are you sure you want to approve this candidate?')) {
                updateCandidateStatus(candidateId, 'approved', 'approved');
            }
        });
    });

    document.querySelectorAll('.reject-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const candidateId = this.dataset.id;
            if (confirm('Are you sure you want to reject this candidate?')) {
                updateCandidateStatus(candidateId, 'rejected', 'rejected');
            }
        });
    });

    document.querySelectorAll('.reset-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const candidateId = this.dataset.id;
            if (confirm('Are you sure you want to reset this candidate to pending status?')) {
                updateCandidateStatus(candidateId, 'pending', 'reset to pending');
            }
        });
    });

    // Handle modal approve/reject buttons
    document.getElementById('approveCandidate').addEventListener('click', function() {
        if (currentCandidateId && confirm('Are you sure you want to approve this candidate?')) {
            updateCandidateStatus(currentCandidateId, 'approved', 'approved');
        }
    });

    document.getElementById('rejectCandidate').addEventListener('click', function() {
        if (currentCandidateId && confirm('Are you sure you want to reject this candidate?')) {
            updateCandidateStatus(currentCandidateId, 'rejected', 'rejected');
        }
    });

    function updateCandidateStatus(candidateId, status, action) {
        const formData = new FormData();
        formData.append('action', 'update_candidate_status');
        formData.append('candidate_id', candidateId);
        formData.append('status', status);

        fetch('candidate_approval_handler.php', {
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
</style>

<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
