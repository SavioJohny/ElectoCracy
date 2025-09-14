<?php
session_start();
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/config/database.php';

requireRole('Election Commissioner');

$page_title = 'Manage Union Candidates';

// Get current year
$current_year = date('Y');

// Get union election for current year
$stmt = $pdo->prepare("
    SELECT e.*, et.election_type_name
    FROM elections e
    JOIN election_types et ON e.election_type_id = et.election_type_id
    WHERE e.election_year = ? AND et.election_type_name = 'union'
    ORDER BY e.election_id DESC
    LIMIT 1
");
$stmt->execute([$current_year]);
$union_election = $stmt->fetch();

if (!$union_election) {
    header('Location: union_elections.php');
    exit();
}

// Get all union positions for filtering
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
        p.is_active as position_is_active,
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

include dirname(__DIR__) . '/includes/header.php';
?>

<div class="row">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1>Manage Union Candidates - <?php echo $current_year; ?></h1>
            <div>
                <a href="union_elections.php" class="btn btn-outline-secondary">
                    <i class="fas fa-arrow-left me-1"></i>Back to Union Elections
                </a>
            </div>
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
                        <label for="positionFilter" class="form-label">Filter by Position:</label>
                        <select id="positionFilter" class="form-select">
                            <option value="">All Positions</option>
                            <?php foreach ($union_positions as $position): ?>
                                <option value="<?php echo $position['position_id']; ?>">
                                    <?php echo htmlspecialchars($position['position_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label for="statusFilter" class="form-label">Filter by Status:</label>
                        <select id="statusFilter" class="form-select">
                            <option value="">All Status</option>
                            <option value="pending">Pending</option>
                            <option value="approved">Approved</option>
                            <option value="rejected">Rejected</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">&nbsp;</label>
                        <div>
                            <button id="clearFilters" class="btn btn-outline-secondary">
                                <i class="fas fa-times me-1"></i>Clear Filters
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Candidates Table -->
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-users me-2"></i>Union Candidates</h5>
            </div>
            <div class="card-body">
                <?php if (empty($union_candidates)): ?>
                    <div class="text-center py-4">
                        <i class="fas fa-users fa-2x text-muted mb-3"></i>
                        <h6 class="text-muted">No union candidates yet</h6>
                        <p class="text-muted">Class winners need to apply for union positions first.</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover" id="candidatesTable">
                            <thead>
                                <tr>
                                    <th>Candidate</th>
                                    <th>Position</th>
                                    <th>Class</th>
                                    <th>Status</th>
                                    <th class="text-center">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($union_candidates as $candidate): ?>
                                    <tr data-position-id="<?php echo $candidate['position_id']; ?>" 
                                        data-status="<?php echo $candidate['is_approved']; ?>">
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <div class="avatar-sm bg-primary text-white rounded-circle d-flex align-items-center justify-content-center me-3">
                                                    <i class="fas fa-user"></i>
                                                </div>
                                                <div>
                                                    <strong><?php echo htmlspecialchars($candidate['fname'] . ' ' . $candidate['lname']); ?></strong>
                                                    <br><small class="text-muted"><?php echo htmlspecialchars($candidate['email']); ?></small>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="badge bg-primary"><?php echo htmlspecialchars($candidate['position_name']); ?></span>
                                        </td>
                                        <td>
                                            <small>
                                                <?php echo htmlspecialchars($candidate['class_name']); ?> -
                                                <?php echo htmlspecialchars($candidate['department_name']); ?>
                                            </small>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?php echo $candidate['is_approved'] == 'approved' ? 'success' : ($candidate['is_approved'] == 'rejected' ? 'danger' : 'warning'); ?>">
                                                <?php echo ucfirst($candidate['is_approved']); ?>
                                            </span>
                                        </td>
                                        <td class="text-center">
                                            <?php if ($candidate['position_is_active']): ?>
                                                <span class="badge bg-warning text-dark">
                                                    <i class="fas fa-lock me-1"></i>Locked
                                                </span>
                                            <?php elseif ($candidate['is_approved'] == 'pending'): ?>
                                                <div class="btn-group btn-group-sm">
                                                    <button class="btn btn-outline-success approve-candidate-btn"
                                                            data-candidate-id="<?php echo $candidate['candidate_id']; ?>"
                                                            title="Approve">
                                                        <i class="fas fa-check"></i>
                                                    </button>
                                                    <button class="btn btn-outline-danger reject-candidate-btn"
                                                            data-candidate-id="<?php echo $candidate['candidate_id']; ?>"
                                                            title="Reject">
                                                        <i class="fas fa-times"></i>
                                                    </button>
                                                    <button class="btn btn-outline-warning btn-sm remove-candidate-btn"
                                                            data-candidate-id="<?php echo $candidate['candidate_id']; ?>"
                                                            data-candidate-name="<?php echo htmlspecialchars($candidate['fname'] . ' ' . $candidate['lname']); ?>"
                                                            data-position-name="<?php echo htmlspecialchars($candidate['position_name']); ?>"
                                                            title="Remove Application">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </div>
                                            <?php else: ?>
                                                <div class="btn-group btn-group-sm">
                                                    <button class="btn btn-outline-secondary btn-sm reset-approval-btn"
                                                            data-candidate-id="<?php echo $candidate['candidate_id']; ?>"
                                                            title="Reset to Pending">
                                                        <i class="fas fa-undo"></i>
                                                    </button>
                                                    <button class="btn btn-outline-warning btn-sm remove-candidate-btn"
                                                            data-candidate-id="<?php echo $candidate['candidate_id']; ?>"
                                                            data-candidate-name="<?php echo htmlspecialchars($candidate['fname'] . ' ' . $candidate['lname']); ?>"
                                                            data-position-name="<?php echo htmlspecialchars($candidate['position_name']); ?>"
                                                            title="Remove Application">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </div>
                                            <?php endif; ?>
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

    // Filter functionality
    const positionFilter = document.getElementById('positionFilter');
    const statusFilter = document.getElementById('statusFilter');
    const clearFiltersBtn = document.getElementById('clearFilters');
    const candidatesTable = document.getElementById('candidatesTable');

    function filterTable() {
        const positionValue = positionFilter.value;
        const statusValue = statusFilter.value;
        const rows = candidatesTable.querySelectorAll('tbody tr');

        rows.forEach(row => {
            const positionId = row.dataset.positionId;
            const status = row.dataset.status;

            let showRow = true;

            if (positionValue && positionId !== positionValue) {
                showRow = false;
            }

            if (statusValue && status !== statusValue) {
                showRow = false;
            }

            row.style.display = showRow ? '' : 'none';
        });
    }

    // Auto-filter when selection changes
    positionFilter.addEventListener('change', filterTable);
    statusFilter.addEventListener('change', filterTable);

    // Clear filters
    clearFiltersBtn.addEventListener('click', function() {
        positionFilter.value = '';
        statusFilter.value = '';
        filterTable();
    });

    // Handle candidate approval buttons
    document.querySelectorAll('.approve-candidate-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const candidateId = this.dataset.candidateId;

            if (confirm('Are you sure you want to approve this candidate?')) {
                const formData = new FormData();
                formData.append('action', 'manage_candidate');
                formData.append('candidate_id', candidateId);
                formData.append('approval_action', 'approve');

                this.disabled = true;
                this.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';

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
                        this.innerHTML = '<i class="fas fa-check"></i>';
                    }
                })
                .catch(error => {
                    showAlert('Network error. Please try again.', 'danger');
                    this.disabled = false;
                    this.innerHTML = '<i class="fas fa-check"></i>';
                });
            }
        });
    });

    // Handle candidate rejection buttons
    document.querySelectorAll('.reject-candidate-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const candidateId = this.dataset.candidateId;

            if (confirm('Are you sure you want to reject this candidate?')) {
                const formData = new FormData();
                formData.append('action', 'manage_candidate');
                formData.append('candidate_id', candidateId);
                formData.append('approval_action', 'reject');

                this.disabled = true;
                this.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';

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
                        this.innerHTML = '<i class="fas fa-times"></i>';
                    }
                })
                .catch(error => {
                    showAlert('Network error. Please try again.', 'danger');
                    this.disabled = false;
                    this.innerHTML = '<i class="fas fa-times"></i>';
                });
            }
        });
    });

    // Handle reset approval buttons
    document.querySelectorAll('.reset-approval-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const candidateId = this.dataset.candidateId;

            if (confirm('Are you sure you want to reset this candidate to pending status?')) {
                const formData = new FormData();
                formData.append('action', 'manage_candidate');
                formData.append('candidate_id', candidateId);
                formData.append('approval_action', 'reset');

                this.disabled = true;
                this.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';

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
                        this.innerHTML = '<i class="fas fa-undo"></i>';
                    }
                })
                .catch(error => {
                    showAlert('Network error. Please try again.', 'danger');
                    this.disabled = false;
                    this.innerHTML = '<i class="fas fa-undo"></i>';
                });
            }
        });
    });

    // Handle remove candidate buttons
    document.querySelectorAll('.remove-candidate-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const candidateId = this.dataset.candidateId;
            const candidateName = this.dataset.candidateName;
            const positionName = this.dataset.positionName;

            if (confirm(`Are you sure you want to permanently remove ${candidateName}'s application for ${positionName}? This action cannot be undone.`)) {
                const formData = new FormData();
                formData.append('action', 'remove_candidate');
                formData.append('candidate_id', candidateId);

                this.disabled = true;
                this.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';

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
                        this.innerHTML = '<i class="fas fa-trash"></i>';
                    }
                })
                .catch(error => {
                    showAlert('Network error. Please try again.', 'danger');
                    this.disabled = false;
                    this.innerHTML = '<i class="fas fa-trash"></i>';
                });
            }
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
</style>

<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
