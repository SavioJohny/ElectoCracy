<?php
session_start();
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/config/database.php';

requireRole('Election Commissioner');

$page_title = 'Union Elections Management';

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

// Get all union positions (both active and inactive for management)
$stmt = $pdo->prepare("
    SELECT * FROM positions
    WHERE election_type_id = 2
    ORDER BY voting_order
");
$stmt->execute();
$union_positions = $stmt->fetchAll();

// Get class election winners (eligible candidates for union elections)
// For now, let's simplify and get actual winners from completed elections
$class_winners = [];

// First, get completed elections with votes
$stmt = $pdo->prepare("
    SELECT DISTINCT e.election_id, e.class_id
    FROM elections e
    JOIN votes v ON e.election_id = v.election_id
    WHERE e.election_year = ?
    AND e.election_type_id = 1
    AND e.voting_status = 'ended'
");
$stmt->execute([$current_year]);
$completed_elections = $stmt->fetchAll();

// For each completed election, find the winners
foreach ($completed_elections as $election) {
    foreach (['girls', 'boys'] as $gender_category) {
        $stmt = $pdo->prepare("
            SELECT
                cand.candidate_id,
                u.user_id,
                u.fname,
                u.lname,
                u.email,
                u.gender,
                c.class_name,
                d.department_name,
                'Class Representative' as won_position,
                ? as election_id,
                ? as gender_category,
                COUNT(v.vote_id) as vote_count,
                'winner' as status
            FROM candidates cand
            JOIN users u ON cand.user_id = u.user_id
            JOIN elections e ON cand.election_id = e.election_id
            JOIN classes c ON e.class_id = c.class_id
            JOIN departments d ON c.department_id = d.department_id
            LEFT JOIN votes v ON cand.candidate_id = v.candidate_id AND v.gender_category = ?
            WHERE cand.election_id = ?
            AND cand.is_approved = 'approved'
            AND u.gender = ?
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
            ORDER BY COUNT(v.vote_id) DESC
            LIMIT 1
        ");

        $gender = substr($gender_category, 0, 1); // 'girls' -> 'F', 'boys' -> 'M'
        $gender = $gender == 'g' ? 'F' : 'M';

        $stmt->execute([
            $election['election_id'],
            $gender_category,
            $gender_category,
            $election['election_id'],
            $gender,
            $gender_category,
            $election['election_id'],
            $gender
        ]);

        $winner = $stmt->fetch();
        if ($winner) {
            $class_winners[] = $winner;
        }
    }
}

// If no winners yet, get potential candidates from ongoing elections
if (empty($class_winners)) {
    $stmt = $pdo->prepare("
        SELECT DISTINCT
            cand.candidate_id,
            u.user_id,
            u.fname,
            u.lname,
            u.email,
            u.gender,
            c.class_name,
            d.department_name,
            'Class Representative' as won_position,
            e.election_id,
            NULL as gender_category,
            0 as vote_count,
            'potential' as status
        FROM elections e
        JOIN candidates cand ON e.election_id = cand.election_id
        JOIN users u ON cand.user_id = u.user_id
        JOIN classes c ON e.class_id = c.class_id
        JOIN departments d ON c.department_id = d.department_id
        WHERE e.election_year = ?
        AND e.election_type_id = 1
        AND cand.is_approved = 'approved'
        ORDER BY d.department_name, c.class_name, u.fname
    ");
    $stmt->execute([$current_year]);
    $class_winners = $stmt->fetchAll();
}

// Get union election candidates if union election exists
$union_candidates = [];
if ($union_election) {
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
            <h1>Union Elections Management - <?php echo $current_year; ?></h1>
            <div>
                <?php if (!$union_election): ?>
                    <button type="button" class="btn btn-success me-2" data-bs-toggle="modal" data-bs-target="#createUnionElectionModal">
                        <i class="fas fa-plus me-1"></i>Create Union Election
                    </button>
                <?php endif; ?>
                <a href="dashboard.php" class="btn btn-outline-secondary">
                    <i class="fas fa-arrow-left me-1"></i>Back to Dashboard
                </a>
            </div>
        </div>
    </div>
</div>

<!-- Statistics Cards -->
<div class="row mb-4">
    <div class="col-md-3">
        <div class="card bg-primary text-white">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <h4><?php echo count($class_winners); ?></h4>
                        <p class="mb-0">Total Students</p>
                    </div>
                    <div class="align-self-center">
                        <i class="fas fa-trophy fa-2x"></i>
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
                        <h4><?php echo count($union_positions); ?></h4>
                        <p class="mb-0">Union Positions</p>
                    </div>
                    <div class="align-self-center">
                        <i class="fas fa-sitemap fa-2x"></i>
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
                        <h4><?php echo $union_election ? count($union_candidates) : 0; ?></h4>
                        <p class="mb-0">Union Candidates</p>
                    </div>
                    <div class="align-self-center">
                        <i class="fas fa-users fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card bg-warning text-white">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <h4><?php echo $union_election ? ($union_election['vote_count'] ?? 0) : 0; ?></h4>
                        <p class="mb-0">Total Votes</p>
                    </div>
                    <div class="align-self-center">
                        <i class="fas fa-ballot-check fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php if (!$union_election): ?>
    <!-- No Union Election State -->
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-university me-2"></i>Union Election - <?php echo $current_year; ?></h5>
                </div>
                <div class="card-body">
                    <div class="text-center py-5">
                        <i class="fas fa-university fa-3x text-muted mb-3"></i>
                        <h5 class="text-muted">No union election created yet</h5>
                        <p class="text-muted">Create a union election for <?php echo $current_year; ?> to allow class winners to compete for union positions.</p>
                        <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#createUnionElectionModal">
                            <i class="fas fa-plus me-1"></i>Create Union Election
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
<?php else: ?>
    <!-- Union Election Management -->
    <div class="row">
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
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <div class="btn-group me-2">
                                <a href="manage_union_candidates.php" class="btn btn-outline-success">
                                    <i class="fas fa-users me-1"></i>Manage Candidates
                                </a>
                                <a href="manage_students.php" class="btn btn-outline-info">
                                    <i class="fas fa-user-graduate me-1"></i>Manage Students
                                </a>
                            </div>
                        </div>
                        <div class="col-md-6 text-end">
                            <div class="btn-group">
                                <button class="btn btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#editUnionElectionModal">
                                    <i class="fas fa-edit me-1"></i>Edit
                                </button>
                                <button class="btn btn-outline-danger delete-union-election-btn"
                                        data-election-id="<?php echo $union_election['election_id']; ?>"
                                        data-year="<?php echo $union_election['election_year']; ?>"
                                        data-candidates="<?php echo count($union_candidates); ?>"
                                        data-votes="<?php echo $union_election['vote_count'] ?? 0; ?>">
                                    <i class="fas fa-trash me-1"></i>Delete
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>


<!-- Union Positions and Candidates Section -->
<?php if ($union_election && !empty($union_positions)): ?>
<div class="row mt-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-sitemap me-2"></i>Union Positions & Candidates</h5>
            </div>
            <div class="card-body">
                <?php foreach ($union_positions as $position): ?>
                    <div class="position-section mb-4">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h6 class="mb-0">
                                <span class="badge bg-primary me-2"><?php echo $position['voting_order']; ?></span>
                                <?php echo htmlspecialchars($position['position_name']); ?>
                                <span class="badge bg-<?php echo $position['is_active'] ? 'success' : 'warning'; ?> ms-2">
                                    <?php echo $position['is_active'] ? 'Voting Active' : 'Voting Inactive'; ?>
                                </span>
                            </h6>
                            <div>
                                <?php
                                $position_candidates = array_filter($union_candidates, function($c) use ($position) {
                                    return $c['position_id'] == $position['position_id'] && $c['is_approved'] == 'approved';
                                });
                                ?>
                                <span class="badge bg-success"><?php echo count($position_candidates); ?> Approved Candidates</span>
                            </div>
                        </div>

                        <?php if (empty($position_candidates)): ?>
                            <div class="alert alert-light">
                                <i class="fas fa-info-circle me-2"></i>No candidates for this position yet.
                            </div>
                        <?php else: ?>
                            <div class="row">
                                <?php foreach ($position_candidates as $candidate): ?>
                                    <div class="col-md-6 mb-3">
                                        <div class="card border-left-primary">
                                            <div class="card-body p-3">
                                                <div class="d-flex justify-content-between align-items-start">
                                                    <div>
                                                        <h6 class="mb-1"><?php echo htmlspecialchars($candidate['fname'] . ' ' . $candidate['lname']); ?></h6>
                                                        <small class="text-muted">
                                                            <?php echo htmlspecialchars($candidate['class_name']); ?> -
                                                            <?php echo htmlspecialchars($candidate['department_name']); ?>
                                                        </small>
                                                        <br>
                                                        <small class="text-muted"><?php echo htmlspecialchars($candidate['email']); ?></small>
                                                    </div>
                                                    <div class="text-end">
                                                        <span class="badge bg-<?php echo $candidate['is_approved'] == 'approved' ? 'success' : ($candidate['is_approved'] == 'rejected' ? 'danger' : 'warning'); ?>">
                                                            <?php echo ucfirst($candidate['is_approved']); ?>
                                                        </span>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    <hr>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>











<!-- Create Union Election Modal -->
<div class="modal fade" id="createUnionElectionModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-plus me-2"></i>Create Union Election</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="createUnionElectionForm" class="needs-validation" novalidate>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="union_election_year" class="form-label">Election Year <span class="text-danger">*</span></label>
                        <input type="number" class="form-control" id="union_election_year" name="election_year"
                               value="<?php echo $current_year; ?>" min="<?php echo $current_year; ?>"
                               max="<?php echo $current_year + 1; ?>" required>
                        <div class="invalid-feedback">Please provide a valid election year.</div>
                    </div>

                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-plus me-1"></i>Create Union Election
                    </button>
                </div>
            </form>
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

    // Handle create union election form
    const createForm = document.getElementById('createUnionElectionForm');
    if (createForm) {
        createForm.addEventListener('submit', function(e) {
            e.preventDefault();

            if (!this.checkValidity()) {
                this.classList.add('was-validated');
                showAlert('Please fill in all required fields correctly.', 'danger');
                return;
            }

            const formData = new FormData(this);
            formData.append('action', 'create_union_election');

            const submitBtn = this.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;

            submitBtn.disabled = true;
            submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Creating...';

            fetch('union_election_handler.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showAlert(data.message, 'success');
                    bootstrap.Modal.getInstance(document.getElementById('createUnionElectionModal')).hide();
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






    // Handle edit union election form
    const editForm = document.getElementById('editUnionElectionForm');
    if (editForm) {
        editForm.addEventListener('submit', function(e) {
            e.preventDefault();

            if (!this.checkValidity()) {
                this.classList.add('was-validated');
                showAlert('Please fill in all required fields correctly.', 'danger');
                return;
            }

            const formData = new FormData(this);
            formData.append('action', 'update_union_election');

            const submitBtn = this.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;

            submitBtn.disabled = true;
            submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Updating...';

            fetch('union_election_handler.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showAlert(data.message, 'success');
                    bootstrap.Modal.getInstance(document.getElementById('editUnionElectionModal')).hide();
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

    // Handle delete union election button
    document.querySelectorAll('.delete-union-election-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const electionId = this.dataset.electionId;
            const year = this.dataset.year;
            const candidates = this.dataset.candidates;
            const votes = this.dataset.votes;

            let confirmMessage = `Are you sure you want to delete the union election for ${year}?`;
            confirmMessage += `\n\nThis will permanently delete:`;
            confirmMessage += `\n• The union election`;
            confirmMessage += `\n• ${candidates} candidate applications`;
            confirmMessage += `\n• ${votes} votes cast`;
            confirmMessage += `\n• All related data`;
            confirmMessage += `\n\nThis action cannot be undone!`;
            confirmMessage += `\n\nType "DELETE UNION" to confirm:`;

            const userInput = prompt(confirmMessage);

            if (userInput === 'DELETE UNION') {
                const formData = new FormData();
                formData.append('action', 'delete_union_election');
                formData.append('election_id', electionId);

                this.disabled = true;
                this.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Deleting...';

                fetch('union_election_handler.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showAlert(data.message, 'success');
                        setTimeout(() => window.location.reload(), 2000);
                    } else {
                        showAlert(data.message, 'danger');
                        this.disabled = false;
                        this.innerHTML = '<i class="fas fa-trash me-1"></i>Delete';
                    }
                })
                .catch(error => {
                    showAlert('Network error. Please try again.', 'danger');
                    this.disabled = false;
                    this.innerHTML = '<i class="fas fa-trash me-1"></i>Delete';
                });
            } else if (userInput !== null) {
                alert('Deletion cancelled. You must type "DELETE UNION" exactly to confirm.');
            }
        });
    });



});




</script>


<!-- Edit Union Election Modal -->
<div class="modal fade" id="editUnionElectionModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-edit me-2"></i>Edit Union Election</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="editUnionElectionForm" class="needs-validation" novalidate>
                <input type="hidden" name="election_id" value="<?php echo $union_election ? $union_election['election_id'] : ''; ?>">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="edit_election_year" class="form-label">Election Year <span class="text-danger">*</span></label>
                        <input type="number" class="form-control" id="edit_election_year" name="election_year"
                               value="<?php echo $union_election ? $union_election['election_year'] : $current_year; ?>"
                               min="<?php echo $current_year; ?>" max="<?php echo $current_year + 1; ?>" required>
                        <div class="invalid-feedback">Please provide a valid election year.</div>
                    </div>

                    <div class="mb-3">
                        <label for="edit_is_active" class="form-label">Election Status</label>
                        <select class="form-select" id="edit_is_active" name="is_active">
                            <option value="1" <?php echo ($union_election && $union_election['is_active']) ? 'selected' : ''; ?>>Active</option>
                            <option value="0" <?php echo ($union_election && !$union_election['is_active']) ? 'selected' : ''; ?>>Inactive</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label for="edit_voting_status" class="form-label">Voting Status</label>
                        <select class="form-select" id="edit_voting_status" name="voting_status">
                            <option value="not_started" <?php echo ($union_election && $union_election['voting_status'] == 'not_started') ? 'selected' : ''; ?>>Not Started</option>
                            <option value="active" <?php echo ($union_election && $union_election['voting_status'] == 'active') ? 'selected' : ''; ?>>Active</option>
                            <option value="paused" <?php echo ($union_election && $union_election['voting_status'] == 'paused') ? 'selected' : ''; ?>>Paused</option>
                            <option value="ended" <?php echo ($union_election && $union_election['voting_status'] == 'ended') ? 'selected' : ''; ?>>Ended</option>
                        </select>
                    </div>



                    <div class="alert alert-warning">
                        <h6><i class="fas fa-exclamation-triangle me-2"></i>Warning</h6>
                        <ul class="mb-0">
                            <li>Changing the election year may affect candidate eligibility</li>
                            <li>Deactivating the election will prevent student access</li>
                            <li>Changing voting status affects when students can vote</li>
                        </ul>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-1"></i>Update Union Election
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
.border-left-primary {
    border-left: 4px solid #007bff !important;
}

.position-section {
    border: 1px solid #dee2e6;
    border-radius: 0.375rem;
    padding: 1rem;
    background-color: #f8f9fa;
}

.badge.bg-pink {
    background-color: #e83e8c !important;
}

.badge.bg-blue {
    background-color: #007bff !important;
}

.avatar-sm {
    width: 40px;
    height: 40px;
    font-size: 0.875rem;
    font-weight: bold;
}
</style>

<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
