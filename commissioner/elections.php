<?php
session_start();
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/config/database.php';

requireRole('Election Commissioner');

$page_title = 'Class Elections Management';

// Get current year
$current_year = date('Y');

// Get class elections with statistics (only class elections, not union)
$stmt = $pdo->prepare("
    SELECT e.*, et.election_type_name, c.class_name, d.department_name,
           COUNT(DISTINCT cand.candidate_id) as candidate_count,
           COUNT(DISTINCT v.vote_id) as vote_count,
           COUNT(DISTINCT CASE WHEN cand.is_approved = 1 THEN cand.candidate_id END) as approved_candidates,
           ica.invigilator_id, inv.fname as inv_fname, inv.lname as inv_lname
    FROM elections e
    JOIN election_types et ON e.election_type_id = et.election_type_id
    JOIN classes c ON e.class_id = c.class_id
    JOIN departments d ON c.department_id = d.department_id
    LEFT JOIN candidates cand ON e.election_id = cand.election_id
    LEFT JOIN votes v ON e.election_id = v.election_id
    LEFT JOIN invigilator_class_assignments ica ON e.class_id = ica.class_id AND e.election_year = ica.election_year
    LEFT JOIN users inv ON ica.invigilator_id = inv.user_id
    WHERE e.election_year = ? AND et.election_type_name = 'class'
    GROUP BY e.election_id
    ORDER BY e.election_id DESC
");
$stmt->execute([$current_year]);
$elections = $stmt->fetchAll();

// Get departments and classes for new election form
$stmt = $pdo->query("SELECT department_id, department_name FROM departments ORDER BY department_name");
$departments = $stmt->fetchAll();

$stmt = $pdo->query("
    SELECT c.class_id, c.class_name, d.department_name, c.department_id
    FROM classes c 
    JOIN departments d ON c.department_id = d.department_id 
    ORDER BY d.department_name, c.class_name
");
$classes = $stmt->fetchAll();

include dirname(__DIR__) . '/includes/header.php';
?>

<div class="row">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1>Class Elections Management - <?php echo $current_year; ?></h1>
            <div>
                <div class="btn-group me-2">
                    <button type="button" class="btn btn-success dropdown-toggle" data-bs-toggle="dropdown">
                        <i class="fas fa-plus me-1"></i>Create Election
                    </button>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#createElectionModal">
                            <i class="fas fa-plus me-2"></i>Single Class Election
                        </a></li>
                        <li><a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#createBulkElectionModal">
                            <i class="fas fa-layer-group me-2"></i>All Classes Election
                        </a></li>
                    </ul>
                </div>
                <a href="dashboard.php" class="btn btn-outline-secondary">
                    <i class="fas fa-arrow-left me-1"></i>Back to Dashboard
                </a>
            </div>
        </div>
    </div>
</div>


<!-- Elections List -->
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-users me-2"></i>Class Elections - <?php echo $current_year; ?></h5>
            </div>
            <div class="card-body">
                <?php if (empty($elections)): ?>
                    <div class="text-center py-5">
                        <i class="fas fa-users fa-3x text-muted mb-3"></i>
                        <h5 class="text-muted">No class elections created yet</h5>
                        <p class="text-muted">Create your first class election for <?php echo $current_year; ?>.</p>
                        <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#createElectionModal">
                            <i class="fas fa-plus me-1"></i>Create Class Election
                        </button>
                    </div>
                <?php else: ?>
                    <!-- Mass Delete Controls -->
                    <div class="d-flex justify-content-between align-items-center mb-3" id="massDeleteControls" style="display: none !important;">
                        <div>
                            <span id="selectedCount">0</span> elections selected
                        </div>
                        <div>
                            <button type="button" class="btn btn-outline-secondary btn-sm me-2" id="selectAllBtn">
                                <i class="fas fa-check-square me-1"></i>Select All
                            </button>
                            <button type="button" class="btn btn-outline-secondary btn-sm me-2" id="clearSelectionBtn">
                                <i class="fas fa-times me-1"></i>Clear Selection
                            </button>
                            <button type="button" class="btn btn-danger btn-sm" id="massDeleteBtn" style="display: none;">
                                <i class="fas fa-trash me-1"></i>Delete Selected
                            </button>
                        </div>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th width="40">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="selectAllCheckbox">
                                        </div>
                                    </th>
                                    <th>Class</th>
                                    <th>Department</th>
                                    <th>Invigilator</th>
                                    <th class="text-center">Status</th>
                                    <th class="text-center">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($elections as $election): ?>
                                    <tr>
                                        <td>
                                            <div class="form-check">
                                                <input class="form-check-input election-checkbox" type="checkbox"
                                                       value="<?php echo $election['election_id']; ?>"
                                                       data-type="Class"
                                                       data-class="<?php echo htmlspecialchars($election['class_name']); ?>"
                                                       data-candidates="<?php echo $election['candidate_count']; ?>"
                                                       data-votes="<?php echo $election['vote_count']; ?>">
                                            </div>
                                        </td>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <div class="avatar-sm bg-info text-white rounded-circle d-flex align-items-center justify-content-center me-3">
                                                    <i class="fas fa-users"></i>
                                                </div>
                                                <div>
                                                    <strong><?php echo htmlspecialchars($election['class_name']); ?></strong>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <small class="text-muted"><?php echo htmlspecialchars($election['department_name']); ?></small>
                                        </td>
                                        <td>
                                            <?php if ($election['invigilator_id']): ?>
                                                <div class="d-flex align-items-center">
                                                    <div class="avatar-xs bg-success text-white rounded-circle d-flex align-items-center justify-content-center me-2">
                                                        <i class="fas fa-user-tie"></i>
                                                    </div>
                                                    <div>
                                                        <small><strong><?php echo htmlspecialchars($election['inv_fname'] . ' ' . $election['inv_lname']); ?></strong></small>
                                                    </div>
                                                </div>
                                            <?php else: ?>
                                                <span class="badge bg-warning text-dark">
                                                    <i class="fas fa-exclamation-triangle me-1"></i>Not Assigned
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-center">
                                            <?php 
                                            $status = $election['voting_status'];
                                            $badge_class = '';
                                            $display_text = '';
                                            
                                            switch($status) {
                                                case 'not_started':
                                                    $badge_class = 'bg-secondary';
                                                    $display_text = 'Not Started';
                                                    break;
                                                case 'active':
                                                    $badge_class = 'bg-success';
                                                    $display_text = 'Active';
                                                    break;
                                                case 'paused':
                                                    $badge_class = 'bg-warning text-dark';
                                                    $display_text = 'Paused';
                                                    break;
                                                case 'ended':
                                                    $badge_class = 'bg-danger';
                                                    $display_text = 'Ended';
                                                    break;
                                                default:
                                                    $badge_class = 'bg-secondary';
                                                    $display_text = ucfirst(str_replace('_', ' ', $status));
                                            }
                                            ?>
                                            <span class="badge <?php echo $badge_class; ?>"><?php echo $display_text; ?></span>
                                        </td>
                                        <td class="text-center">
                                            <div class="btn-group btn-group-sm">
                                                <button class="btn btn-outline-secondary assign-invigilator-btn"
                                                        data-election-id="<?php echo $election['election_id']; ?>"
                                                        data-class-id="<?php echo $election['class_id']; ?>"
                                                        data-class-name="<?php echo htmlspecialchars($election['class_name']); ?>"
                                                        data-election-year="<?php echo $election['election_year']; ?>"
                                                        title="Assign Invigilator">
                                                    <i class="fas fa-user-tie"></i>
                                                </button>
                                                <button class="btn btn-outline-<?php echo $election['is_active'] ? 'warning' : 'success'; ?> toggle-status-btn"
                                                        data-id="<?php echo $election['election_id']; ?>"
                                                        data-status="<?php echo $election['is_active']; ?>"
                                                        title="<?php echo $election['is_active'] ? 'Deactivate' : 'Activate'; ?>">
                                                    <i class="fas fa-<?php echo $election['is_active'] ? 'pause' : 'play'; ?>"></i>
                                                </button>
                                                <button class="btn btn-outline-danger delete-election-btn"
                                                        data-id="<?php echo $election['election_id']; ?>"
                                                        data-type="Class"
                                                        data-class="<?php echo htmlspecialchars($election['class_name']); ?>"
                                                        data-candidates="<?php echo $election['candidate_count']; ?>"
                                                        data-votes="<?php echo $election['vote_count']; ?>"
                                                        title="Delete Election">
                                                    <i class="fas fa-trash"></i>
                                                </button>
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

<!-- Create Election Modal -->
<div class="modal fade" id="createElectionModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-plus me-2"></i>Create New Class Election</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="createElectionForm" class="needs-validation" novalidate>
                <input type="hidden" name="election_type_id" value="1">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="election_year" class="form-label">Election Year <span class="text-danger">*</span></label>
                                <input type="number" class="form-control" id="election_year" name="election_year"
                                       value="<?php echo $current_year; ?>" min="<?php echo $current_year; ?>"
                                       max="<?php echo $current_year + 1; ?>" required>
                                <div class="invalid-feedback">Please provide a valid election year.</div>
                            </div>
                        </div>
                    </div>
                    
                    <div id="classSelectionDiv">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="department_id" class="form-label">Department <span class="text-danger">*</span></label>
                                    <select class="form-select" id="department_id" name="department_id">
                                        <option value="">Select Department</option>
                                        <?php foreach ($departments as $dept): ?>
                                            <option value="<?php echo $dept['department_id']; ?>">
                                                <?php echo htmlspecialchars($dept['department_name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <div class="invalid-feedback">Please select a department.</div>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="class_id" class="form-label">Class <span class="text-danger">*</span></label>
                                    <select class="form-select" id="class_id" name="class_id">
                                        <option value="">Select Class</option>
                                        <?php foreach ($classes as $class): ?>
                                            <option value="<?php echo $class['class_id']; ?>" data-department="<?php echo $class['department_id']; ?>">
                                                <?php echo htmlspecialchars($class['class_name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <div class="invalid-feedback">Please select a class.</div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="alert alert-info">
                        <h6><i class="fas fa-info-circle me-2"></i>Class Election Information</h6>
                        <div id="electionInfo">
                            <p><strong>Class Election:</strong></p>
                            <ul class="mb-0">
                                <li>Students compete for Class Representative position</li>
                                <li>Separate elections for Girls and Boys</li>
                                <li>Only students from the selected class can participate</li>
                                <li>Candidates need marksheet and attendance approval</li>
                            </ul>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-plus me-1"></i>Create Class Election
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Create Bulk Elections Modal -->
<div class="modal fade" id="createBulkElectionModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-layer-group me-2"></i>Create Elections for All Classes</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="createBulkElectionForm" class="needs-validation" novalidate>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="bulk_election_year" class="form-label">Election Year <span class="text-danger">*</span></label>
                                <input type="number" class="form-control" id="bulk_election_year" name="election_year"
                                       value="<?php echo $current_year; ?>" min="<?php echo $current_year; ?>"
                                       max="<?php echo $current_year + 1; ?>" required>
                                <div class="invalid-feedback">Please provide a valid election year.</div>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="department_filter" class="form-label">Department Filter</label>
                                <select class="form-select" id="department_filter" name="department_filter">
                                    <option value="">All Departments</option>
                                    <?php foreach ($departments as $dept): ?>
                                        <option value="<?php echo $dept['department_id']; ?>">
                                            <?php echo htmlspecialchars($dept['department_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="form-text">Optional: Create elections only for classes in selected department</div>
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <h6 class="text-primary mb-3">Classes to Include:</h6>
                        <div id="classPreview" class="border rounded p-3 bg-light">
                            <div class="text-center text-muted">
                                <div class="spinner-border spinner-border-sm me-2"></div>
                                Loading classes...
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-layer-group me-1"></i>Create All Elections
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Assign Invigilator Modal -->
<div class="modal fade" id="assignInvigilatorModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-user-tie me-2"></i>Assign Invigilator</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="assignInvigilatorForm" class="needs-validation" novalidate>
                <input type="hidden" id="assign_election_id" name="election_id">
                <input type="hidden" id="assign_class_id" name="class_id">
                <input type="hidden" id="assign_election_year" name="election_year">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Class Information</label>
                        <div class="bg-light p-3 rounded">
                            <div id="classInfo">
                                <strong>Class:</strong> <span id="modalClassName"></span><br>
                                <strong>Election Year:</strong> <span id="modalElectionYear"></span>
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="invigilator_id" class="form-label">Select Invigilator <span class="text-danger">*</span></label>
                        <select class="form-select" id="invigilator_id" name="invigilator_id" required>
                            <option value="">Loading invigilators...</option>
                        </select>
                        <div class="invalid-feedback">Please select an invigilator.</div>
                    </div>

                    <div id="currentAssignments" style="display: none;">
                        <h6 class="text-primary mb-2">Current Assignments:</h6>
                        <div id="assignmentsList" class="bg-light p-2 rounded small">
                            <!-- Current assignments will be loaded here -->
                        </div>
                    </div>

                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-user-tie me-1"></i>Assign Invigilator
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const departmentSelect = document.getElementById('department_id');
    const classSelect = document.getElementById('class_id');
    const form = document.getElementById('createElectionForm');
    const bulkForm = document.getElementById('createBulkElectionForm');
    const departmentFilter = document.getElementById('department_filter');
    const classPreview = document.getElementById('classPreview');

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

    // Set department and class as required since we only have class elections
    departmentSelect.required = true;
    classSelect.required = true;

    // Filter classes based on selected department
    departmentSelect.addEventListener('change', function() {
        const selectedDept = this.value;
        const classOptions = classSelect.querySelectorAll('option[data-department]');

        classSelect.value = '';

        classOptions.forEach(option => {
            if (!selectedDept || option.dataset.department === selectedDept) {
                option.style.display = '';
            } else {
                option.style.display = 'none';
            }
        });
    });

    // Handle form submission
    const submitBtn = form.querySelector('button[type="submit"]');

    if (submitBtn) {
        submitBtn.addEventListener('click', function(e) {
            e.preventDefault();

            if (!form.checkValidity()) {
                form.classList.add('was-validated');
                showAlert('Please fill in all required fields correctly.', 'danger');
                return;
            }

            const formData = new FormData(form);
            formData.append('action', 'create_election');

            const originalText = submitBtn.innerHTML;

            submitBtn.disabled = true;
            submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Creating...';

            fetch('election_handler.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showAlert(data.message, 'success');
                    bootstrap.Modal.getInstance(document.getElementById('createElectionModal')).hide();
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

    // Handle bulk election form
    if (bulkForm) {
        // Load class preview when modal opens or department filter changes
        document.getElementById('createBulkElectionModal').addEventListener('show.bs.modal', loadClassPreview);
        if (departmentFilter) {
            departmentFilter.addEventListener('change', loadClassPreview);
        }

        function loadClassPreview() {
            const departmentId = departmentFilter ? departmentFilter.value : '';
            const electionYear = document.getElementById('bulk_election_year').value;

            classPreview.innerHTML = `
                <div class="text-center text-muted">
                    <div class="spinner-border spinner-border-sm me-2"></div>
                    Loading classes...
                </div>
            `;

            const formData = new FormData();
            formData.append('action', 'preview_bulk_classes');
            formData.append('department_id', departmentId);
            formData.append('election_year', electionYear);

            fetch('election_handler.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    if (data.classes.length === 0) {
                        classPreview.innerHTML = `
                            <div class="text-center text-muted">
                                <i class="fas fa-info-circle me-2"></i>
                                No classes available for election creation.
                            </div>
                        `;
                    } else {
                        let html = `<div class="row">`;
                        data.classes.forEach(cls => {
                            const statusBadge = cls.has_election ?
                                '<span class="badge bg-warning">Already exists</span>' :
                                '<span class="badge bg-success">Will be created</span>';

                            html += `
                                <div class="col-md-6 mb-2">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <strong>${cls.class_name}</strong>
                                            <br><small class="text-muted">${cls.department_name}</small>
                                        </div>
                                        <div>${statusBadge}</div>
                                    </div>
                                </div>
                            `;
                        });
                        html += `</div>`;

                        const newCount = data.classes.filter(c => !c.has_election).length;
                        const existingCount = data.classes.filter(c => c.has_election).length;

                        html += `
                            <div class="mt-3 pt-3 border-top">
                                <div class="row text-center">
                                    <div class="col-md-4">
                                        <h5 class="text-success">${newCount}</h5>
                                        <small class="text-muted">New Elections</small>
                                    </div>
                                    <div class="col-md-4">
                                        <h5 class="text-warning">${existingCount}</h5>
                                        <small class="text-muted">Already Exist</small>
                                    </div>
                                    <div class="col-md-4">
                                        <h5 class="text-primary">${data.classes.length}</h5>
                                        <small class="text-muted">Total Classes</small>
                                    </div>
                                </div>
                            </div>
                        `;

                        classPreview.innerHTML = html;
                    }
                } else {
                    classPreview.innerHTML = `
                        <div class="text-center text-danger">
                            <i class="fas fa-exclamation-circle me-2"></i>
                            Error loading classes: ${data.message}
                        </div>
                    `;
                }
            })
            .catch(error => {
                classPreview.innerHTML = `
                    <div class="text-center text-danger">
                        <i class="fas fa-exclamation-circle me-2"></i>
                        Error loading classes.
                    </div>
                `;
            });
        }

        // Handle bulk form submission
        const bulkSubmitBtn = bulkForm.querySelector('button[type="submit"]');

        if (bulkSubmitBtn) {
            bulkSubmitBtn.addEventListener('click', function(e) {
                e.preventDefault();

                if (!bulkForm.checkValidity()) {
                    bulkForm.classList.add('was-validated');
                    showAlert('Please fill in all required fields correctly.', 'danger');
                    return;
                }

                if (!confirm('Are you sure you want to create elections for all eligible classes? This action cannot be undone.')) {
                    return;
                }

                const formData = new FormData(bulkForm);
                formData.append('action', 'create_bulk_elections');

                const originalText = bulkSubmitBtn.innerHTML;

                bulkSubmitBtn.disabled = true;
                bulkSubmitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Creating Elections...';

                fetch('election_handler.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showAlert(data.message, 'success');
                        bootstrap.Modal.getInstance(document.getElementById('createBulkElectionModal')).hide();
                        setTimeout(() => window.location.reload(), 2000);
                    } else {
                        showAlert(data.message, 'danger');
                    }
                })
                .catch(error => {
                    showAlert('Network error. Please try again.', 'danger');
                })
                .finally(() => {
                    bulkSubmitBtn.disabled = false;
                    bulkSubmitBtn.innerHTML = originalText;
                });
            });
        }
    }

    // Handle invigilator assignment
    const assignModal = document.getElementById('assignInvigilatorModal');
    const assignForm = document.getElementById('assignInvigilatorForm');

    // Handle assign invigilator buttons
    document.querySelectorAll('.assign-invigilator-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const electionId = this.dataset.electionId;
            const classId = this.dataset.classId;
            const className = this.dataset.className;
            const electionYear = this.dataset.electionYear;

            // Set form data
            document.getElementById('assign_election_id').value = electionId;
            document.getElementById('assign_class_id').value = classId;
            document.getElementById('assign_election_year').value = electionYear;
            document.getElementById('modalClassName').textContent = className;
            document.getElementById('modalElectionYear').textContent = electionYear;

            // Show modal
            new bootstrap.Modal(assignModal).show();

            // Load invigilators and current assignments
            loadInvigilators(classId, electionYear);
        });
    });

    function loadInvigilators(classId, electionYear) {
        const invigilatorSelect = document.getElementById('invigilator_id');
        const currentAssignments = document.getElementById('currentAssignments');
        const assignmentsList = document.getElementById('assignmentsList');

        // Reset select
        invigilatorSelect.innerHTML = '<option value="">Loading invigilators...</option>';
        currentAssignments.style.display = 'none';

        const formData = new FormData();
        formData.append('action', 'get_invigilators_and_assignments');
        formData.append('class_id', classId);
        formData.append('election_year', electionYear);

        fetch('election_handler.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Populate invigilators
                invigilatorSelect.innerHTML = '<option value="">Select Invigilator</option>';
                data.invigilators.forEach(inv => {
                    const option = document.createElement('option');
                    option.value = inv.user_id;
                    option.textContent = `${inv.fname} ${inv.lname} (${inv.email})`;
                    invigilatorSelect.appendChild(option);
                });

                // Show current assignments if any
                if (data.current_assignment) {
                    const assignment = data.current_assignment;
                    assignmentsList.innerHTML = `
                        <div class="d-flex justify-content-between align-items-center">
                            <span><strong>${assignment.fname} ${assignment.lname}</strong> (${assignment.email})</span>
                            <button type="button" class="btn btn-sm btn-outline-danger remove-assignment-btn"
                                    data-assignment-id="${assignment.assignment_id}">
                                <i class="fas fa-times"></i> Remove
                            </button>
                        </div>
                    `;
                    currentAssignments.style.display = 'block';

                    // Set current invigilator as selected
                    invigilatorSelect.value = assignment.invigilator_id;

                    // Handle remove assignment
                    assignmentsList.querySelector('.remove-assignment-btn').addEventListener('click', function() {
                        if (confirm('Are you sure you want to remove this invigilator assignment?')) {
                            removeAssignment(this.dataset.assignmentId);
                        }
                    });
                } else {
                    assignmentsList.innerHTML = '<em class="text-muted">No invigilator currently assigned</em>';
                    currentAssignments.style.display = 'block';
                }
            } else {
                invigilatorSelect.innerHTML = '<option value="">Error loading invigilators</option>';
                showAlert(data.message, 'danger');
            }
        })
        .catch(error => {
            invigilatorSelect.innerHTML = '<option value="">Error loading invigilators</option>';
            showAlert('Error loading invigilator data.', 'danger');
        });
    }

    function removeAssignment(assignmentId) {
        const formData = new FormData();
        formData.append('action', 'remove_invigilator_assignment');
        formData.append('assignment_id', assignmentId);

        fetch('election_handler.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showAlert(data.message, 'success');
                bootstrap.Modal.getInstance(assignModal).hide();
                setTimeout(() => window.location.reload(), 1500);
            } else {
                showAlert(data.message, 'danger');
            }
        })
        .catch(error => {
            showAlert('Error removing assignment.', 'danger');
        });
    }

    // Handle assign form submission
    if (assignForm) {
        const assignSubmitBtn = assignForm.querySelector('button[type="submit"]');

        assignSubmitBtn.addEventListener('click', function(e) {
            e.preventDefault();

            if (!assignForm.checkValidity()) {
                assignForm.classList.add('was-validated');
                showAlert('Please select an invigilator.', 'danger');
                return;
            }

            const formData = new FormData(assignForm);
            formData.append('action', 'assign_invigilator');

            const originalText = assignSubmitBtn.innerHTML;

            assignSubmitBtn.disabled = true;
            assignSubmitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Assigning...';

            fetch('election_handler.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showAlert(data.message, 'success');
                    bootstrap.Modal.getInstance(assignModal).hide();
                    setTimeout(() => window.location.reload(), 1500);
                } else {
                    showAlert(data.message, 'danger');
                }
            })
            .catch(error => {
                showAlert('Network error. Please try again.', 'danger');
            })
            .finally(() => {
                assignSubmitBtn.disabled = false;
                assignSubmitBtn.innerHTML = originalText;
            });
        });
    }

    // Handle toggle status buttons
    document.querySelectorAll('.toggle-status-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const electionId = this.dataset.id;
            const currentStatus = this.dataset.status === '1';
            const newStatus = currentStatus ? 0 : 1;
            const action = currentStatus ? 'deactivate' : 'activate';

            if (confirm(`Are you sure you want to ${action} this election?`)) {
                const formData = new FormData();
                formData.append('action', 'toggle_status');
                formData.append('election_id', electionId);
                formData.append('status', newStatus);

                this.disabled = true;
                this.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';

                fetch('election_handler.php', {
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
                        this.innerHTML = `<i class="fas fa-${currentStatus ? 'pause' : 'play'}"></i>`;
                    }
                })
                .catch(error => {
                    showAlert('Network error. Please try again.', 'danger');
                    this.disabled = false;
                    this.innerHTML = `<i class="fas fa-${currentStatus ? 'pause' : 'play'}"></i>`;
                });
            }
        });
    });

    // Handle delete election buttons
    document.querySelectorAll('.delete-election-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const electionId = this.dataset.id;
            const electionType = this.dataset.type;
            const electionClass = this.dataset.class;
            const candidateCount = parseInt(this.dataset.candidates);
            const voteCount = parseInt(this.dataset.votes);

            // Create detailed confirmation message
            let confirmMessage = `Are you sure you want to delete this ${electionType} Election?`;
            confirmMessage += `\n\nElection Details:`;
            confirmMessage += `\n• Type: ${electionType} Election`;
            confirmMessage += `\n• Class/Scope: ${electionClass}`;
            confirmMessage += `\n• Candidates: ${candidateCount}`;
            confirmMessage += `\n• Votes Cast: ${voteCount}`;

            if (candidateCount > 0 || voteCount > 0) {
                confirmMessage += `\n\n⚠️ WARNING: This election has ${candidateCount > 0 ? 'candidates' : ''}${candidateCount > 0 && voteCount > 0 ? ' and ' : ''}${voteCount > 0 ? 'votes' : ''}. All related data will be permanently deleted!`;
            }

            confirmMessage += `\n\nThis action cannot be undone. Type "DELETE" to confirm:`;

            const userInput = prompt(confirmMessage);

            if (userInput === 'DELETE') {
                const formData = new FormData();
                formData.append('action', 'delete_election');
                formData.append('election_id', electionId);

                this.disabled = true;
                this.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';

                fetch('election_handler.php', {
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
            } else if (userInput !== null) {
                alert('Deletion cancelled. You must type "DELETE" exactly to confirm.');
            }
        });
    });

    // Mass Delete Functionality
    const selectAllCheckbox = document.getElementById('selectAllCheckbox');
    const electionCheckboxes = document.querySelectorAll('.election-checkbox');
    const massDeleteControls = document.getElementById('massDeleteControls');
    const selectedCountSpan = document.getElementById('selectedCount');
    const selectAllBtn = document.getElementById('selectAllBtn');
    const clearSelectionBtn = document.getElementById('clearSelectionBtn');
    const massDeleteBtn = document.getElementById('massDeleteBtn');

    // Update mass delete controls visibility and count
    function updateMassDeleteControls() {
        const checkedBoxes = document.querySelectorAll('.election-checkbox:checked');
        const count = checkedBoxes.length;

        if (count > 0) {
            massDeleteControls.style.display = 'flex';
            massDeleteBtn.style.display = 'inline-block';
            selectedCountSpan.textContent = count;
        } else {
            massDeleteControls.style.display = 'none';
            massDeleteBtn.style.display = 'none';
        }

        // Update select all checkbox state
        if (count === 0) {
            selectAllCheckbox.indeterminate = false;
            selectAllCheckbox.checked = false;
        } else if (count === electionCheckboxes.length) {
            selectAllCheckbox.indeterminate = false;
            selectAllCheckbox.checked = true;
        } else {
            selectAllCheckbox.indeterminate = true;
            selectAllCheckbox.checked = false;
        }
    }

    // Handle individual checkbox changes
    electionCheckboxes.forEach(checkbox => {
        checkbox.addEventListener('change', function() {
            // Update row highlighting
            const row = this.closest('tr');
            if (this.checked) {
                row.classList.add('selected');
            } else {
                row.classList.remove('selected');
            }
            updateMassDeleteControls();
        });
    });

    // Handle select all checkbox
    selectAllCheckbox.addEventListener('change', function() {
        const isChecked = this.checked;
        electionCheckboxes.forEach(checkbox => {
            checkbox.checked = isChecked;
            const row = checkbox.closest('tr');
            if (isChecked) {
                row.classList.add('selected');
            } else {
                row.classList.remove('selected');
            }
        });
        updateMassDeleteControls();
    });

    // Handle select all button
    selectAllBtn.addEventListener('click', function() {
        electionCheckboxes.forEach(checkbox => {
            checkbox.checked = true;
            checkbox.closest('tr').classList.add('selected');
        });
        updateMassDeleteControls();
    });

    // Handle clear selection button
    clearSelectionBtn.addEventListener('click', function() {
        electionCheckboxes.forEach(checkbox => {
            checkbox.checked = false;
            checkbox.closest('tr').classList.remove('selected');
        });
        updateMassDeleteControls();
    });

    // Handle mass delete button
    massDeleteBtn.addEventListener('click', function() {
        const checkedBoxes = document.querySelectorAll('.election-checkbox:checked');
        const selectedElections = Array.from(checkedBoxes).map(checkbox => ({
            id: checkbox.value,
            type: checkbox.dataset.type,
            class: checkbox.dataset.class,
            candidates: parseInt(checkbox.dataset.candidates),
            votes: parseInt(checkbox.dataset.votes)
        }));

        if (selectedElections.length === 0) {
            showAlert('No elections selected for deletion.', 'warning');
            return;
        }

        // Calculate totals
        const totalCandidates = selectedElections.reduce((sum, election) => sum + election.candidates, 0);
        const totalVotes = selectedElections.reduce((sum, election) => sum + election.votes, 0);

        // Create detailed confirmation message
        let confirmMessage = `Are you sure you want to delete ${selectedElections.length} selected elections?`;
        confirmMessage += `\n\nThis will permanently delete:`;
        confirmMessage += `\n• ${selectedElections.length} elections`;
        confirmMessage += `\n• ${totalCandidates} candidates`;
        confirmMessage += `\n• ${totalVotes} votes`;
        confirmMessage += `\n• All related data (assignments, results, etc.)`;
        confirmMessage += `\n\nThis action cannot be undone!`;
        confirmMessage += `\n\nType "DELETE ALL" to confirm:`;

        const userInput = prompt(confirmMessage);

        if (userInput === 'DELETE ALL') {
            const electionIds = selectedElections.map(election => election.id);

            const formData = new FormData();
            formData.append('action', 'mass_delete_elections');
            formData.append('election_ids', JSON.stringify(electionIds));

            this.disabled = true;
            this.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Deleting...';

            fetch('election_handler.php', {
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
                    this.innerHTML = '<i class="fas fa-trash me-1"></i>Delete Selected';
                }
            })
            .catch(error => {
                showAlert('Network error. Please try again.', 'danger');
                this.disabled = false;
                this.innerHTML = '<i class="fas fa-trash me-1"></i>Delete Selected';
            });
        } else if (userInput !== null) {
            alert('Mass deletion cancelled. You must type "DELETE ALL" exactly to confirm.');
        }
    });

    // Initialize mass delete controls
    updateMassDeleteControls();
});
</script>

<style>
.avatar-sm {
    width: 40px;
    height: 40px;
    font-size: 0.875rem;
    font-weight: bold;
}

.avatar-xs {
    width: 24px;
    height: 24px;
    font-size: 0.75rem;
    font-weight: bold;
}

#massDeleteControls {
    background-color: #f8f9fa;
    border: 1px solid #dee2e6;
    border-radius: 0.375rem;
    padding: 0.75rem 1rem;
    margin-bottom: 1rem;
}

.election-checkbox:checked + label,
.election-checkbox:checked {
    background-color: #0d6efd;
    border-color: #0d6efd;
}

tr:has(.election-checkbox:checked) {
    background-color: #e7f3ff !important;
}

/* Fallback for browsers that don't support :has() */
tr.selected {
    background-color: #e7f3ff !important;
}
</style>

<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
