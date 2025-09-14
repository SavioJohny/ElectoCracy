<?php
session_start();
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/config/database.php';

requireRole('Election Commissioner');

$page_title = 'Generate Reports';
$current_year = date('Y');

// Get all elections for the current year
$stmt = $pdo->prepare("
    SELECT e.*, et.election_type_name, c.class_name, d.department_name
    FROM elections e
    JOIN election_types et ON e.election_type_id = et.election_type_id
    LEFT JOIN classes c ON e.class_id = c.class_id
    LEFT JOIN departments d ON c.department_id = d.department_id
    WHERE e.election_year = ?
    ORDER BY et.election_type_name, c.class_name, e.election_id
");
$stmt->execute([$current_year]);
$all_elections = $stmt->fetchAll();

// Separate elections by type
$class_elections = array_filter($all_elections, function($e) {
    return $e['election_type_name'] === 'class';
});

$union_elections = array_filter($all_elections, function($e) {
    return $e['election_type_name'] === 'union';
});

include dirname(__DIR__) . '/includes/header.php';
?>

<div class="container-fluid">
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h2><i class="fas fa-file-alt me-2"></i>Generate Reports - <?php echo $current_year; ?></h2>
                    <p class="text-muted">Export election results and data as PDF reports</p>
                </div>
                <div>
                    <a href="dashboard.php" class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-left me-1"></i>Back to Dashboard
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Class Elections Reports -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-users me-2"></i>Class Election Reports
                    </h5>
                </div>
                <div class="card-body">
                    <?php if (empty($class_elections)): ?>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>
                            No class elections found for <?php echo $current_year; ?>.
                        </div>
                    <?php else: ?>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <?php
                                // Check if all class elections have ended
                                $all_class_ended = true;
                                foreach ($class_elections as $election) {
                                    if ($election['voting_status'] !== 'ended') {
                                        $all_class_ended = false;
                                        break;
                                    }
                                }
                                ?>
                                <?php if ($all_class_ended): ?>
                                    <button class="btn btn-success w-100" onclick="generateAllClassReports()">
                                        <i class="fas fa-download me-2"></i>Download All Class Reports
                                    </button>
                                <?php else: ?>
                                    <button class="btn btn-outline-secondary w-100" disabled title="Some class elections have not ended yet">
                                        <i class="fas fa-download me-2"></i>Download All Class Reports
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Class</th>
                                        <th>Department</th>
                                        <th>Status</th>
                                        <th>Total Students</th>
                                        <th>Candidates</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($class_elections as $election): ?>
                                        <?php
                                        // Get total students in class
                                        $stmt = $pdo->prepare("
                                            SELECT COUNT(*) as total_students
                                            FROM users u
                                            JOIN roles r ON u.role_id = r.role_id
                                            WHERE u.class_id = ? AND r.role_name = 'Student'
                                        ");
                                        $stmt->execute([$election['class_id']]);
                                        $total_students = $stmt->fetchColumn();

                                        // Get total candidates
                                        $stmt = $pdo->prepare("
                                            SELECT COUNT(*) as total_candidates
                                            FROM candidates
                                            WHERE election_id = ? AND is_approved = 'approved'
                                        ");
                                        $stmt->execute([$election['election_id']]);
                                        $total_candidates = $stmt->fetchColumn();
                                        ?>
                                        <tr>
                                            <td>
                                                <strong><?php echo htmlspecialchars($election['class_name']); ?></strong>
                                            </td>
                                            <td><?php echo htmlspecialchars($election['department_name']); ?></td>
                                            <td>
                                                <span class="badge bg-<?php 
                                                    echo $election['voting_status'] === 'ended' ? 'success' : 
                                                        ($election['voting_status'] === 'active' ? 'primary' : 'secondary'); 
                                                ?>">
                                                    <?php echo ucfirst(str_replace('_', ' ', $election['voting_status'])); ?>
                                                </span>
                                            </td>
                                            <td><span class="badge bg-info"><?php echo $total_students; ?></span></td>
                                            <td><span class="badge bg-success"><?php echo $total_candidates; ?></span></td>
                                            <td>
                                                <div class="btn-group btn-group-sm">
                                                    <?php if ($election['voting_status'] === 'ended'): ?>
                                                        <button class="btn btn-outline-primary" 
                                                                onclick="generateClassReport(<?php echo $election['election_id']; ?>)"
                                                                title="Download Class Report">
                                                            <i class="fas fa-download me-1"></i>PDF
                                                        </button>
                                                        <button class="btn btn-outline-info" 
                                                                onclick="previewClassReport(<?php echo $election['election_id']; ?>)"
                                                                title="Preview Report">
                                                            <i class="fas fa-eye me-1"></i>Preview
                                                        </button>
                                                    <?php else: ?>
                                                        <button class="btn btn-outline-secondary" disabled>
                                                            <i class="fas fa-clock me-1"></i>Voting Not Ended
                                                        </button>
                                                    <?php endif; ?>
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

    <!-- Union Elections Reports -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-university me-2"></i>Union Election Reports
                    </h5>
                </div>
                <div class="card-body">
                    <?php if (empty($union_elections)): ?>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>
                            No union elections found for <?php echo $current_year; ?>.
                        </div>
                    <?php else: ?>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <?php
                                // Check if all union elections have ended
                                $all_union_ended = true;
                                foreach ($union_elections as $election) {
                                    if ($election['voting_status'] !== 'ended') {
                                        $all_union_ended = false;
                                        break;
                                    }
                                }
                                ?>
                                <?php if ($all_union_ended): ?>
                                    <button class="btn btn-success w-100" onclick="generateAllUnionReports()">
                                        <i class="fas fa-download me-2"></i>Download All Union Reports
                                    </button>
                                <?php else: ?>
                                    <button class="btn btn-outline-secondary w-100" disabled title="Some union elections have not ended yet">
                                        <i class="fas fa-download me-2"></i>Download All Union Reports
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Election Year</th>
                                        <th>Status</th>
                                        <th>Total Students</th>
                                        <th>Candidates</th>
                                        <th>Positions</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($union_elections as $election): ?>
                                        <?php
                                        // Get total union-eligible students
                                        $stmt = $pdo->prepare("
                                            SELECT COUNT(DISTINCT c.user_id) as union_eligible
                                            FROM candidates c
                                            JOIN elections e ON c.election_id = e.election_id
                                            JOIN election_types et ON e.election_type_id = et.election_type_id
                                            WHERE et.election_type_name = 'class'
                                            AND e.election_year = ?
                                            AND c.is_approved = 'approved'
                                        ");
                                        $stmt->execute([$election['election_year']]);
                                        $union_eligible = $stmt->fetchColumn();

                                        // Get total union candidates
                                        $stmt = $pdo->prepare("
                                            SELECT COUNT(*) as total_candidates
                                            FROM candidates
                                            WHERE election_id = ? AND is_approved = 'approved'
                                        ");
                                        $stmt->execute([$election['election_id']]);
                                        $total_candidates = $stmt->fetchColumn();

                                        // Get total positions
                                        $stmt = $pdo->prepare("
                                            SELECT COUNT(*) as total_positions
                                            FROM positions
                                            WHERE election_type_id = 2
                                        ");
                                        $stmt->execute();
                                        $total_positions = $stmt->fetchColumn();
                                        ?>
                                        <tr>
                                            <td>
                                                <strong>Union Election <?php echo $election['election_year']; ?></strong>
                                            </td>
                                            <td>
                                                <span class="badge bg-<?php 
                                                    echo $election['voting_status'] === 'ended' ? 'success' : 
                                                        ($election['voting_status'] === 'active' ? 'primary' : 'secondary'); 
                                                ?>">
                                                    <?php echo ucfirst(str_replace('_', ' ', $election['voting_status'])); ?>
                                                </span>
                                            </td>
                                            <td><span class="badge bg-info"><?php echo $union_eligible; ?></span></td>
                                            <td><span class="badge bg-success"><?php echo $total_candidates; ?></span></td>
                                            <td><span class="badge bg-primary"><?php echo $total_positions; ?></span></td>
                                            <td>
                                                <div class="btn-group btn-group-sm">
                                                    <?php if ($election['voting_status'] === 'ended'): ?>
                                                        <button class="btn btn-outline-primary" 
                                                                onclick="generateUnionReport(<?php echo $election['election_id']; ?>)"
                                                                title="Download Union Report">
                                                            <i class="fas fa-download me-1"></i>PDF
                                                        </button>
                                                        <button class="btn btn-outline-info" 
                                                                onclick="previewUnionReport(<?php echo $election['election_id']; ?>)"
                                                                title="Preview Report">
                                                            <i class="fas fa-eye me-1"></i>Preview
                                                        </button>
                                                    <?php else: ?>
                                                        <button class="btn btn-outline-secondary" disabled>
                                                            <i class="fas fa-clock me-1"></i>Voting Not Ended
                                                        </button>
                                                    <?php endif; ?>
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

</div>

<script>
// Class Election Reports
function generateClassReport(electionId) {
    showAlert('Generating class election report...', 'info');
    window.open(`generate_pdf.php?type=class&election_id=${electionId}`, '_blank');
}

function previewClassReport(electionId) {
    showAlert('Opening report preview...', 'info');
    window.open(`preview_report.php?type=class&election_id=${electionId}`, '_blank');
}

function generateAllClassReports() {
    if (confirm('This will generate PDF reports for all class elections. Continue?')) {
        showAlert('Generating all class election reports...', 'info');
        window.open(`generate_pdf.php?type=all_class&year=<?php echo $current_year; ?>`, '_blank');
    }
}


// Union Election Reports
function generateUnionReport(electionId) {
    showAlert('Generating union election report...', 'info');
    window.open(`generate_pdf.php?type=union&election_id=${electionId}`, '_blank');
}

function previewUnionReport(electionId) {
    showAlert('Opening report preview...', 'info');
    window.open(`preview_report.php?type=union&election_id=${electionId}`, '_blank');
}

function generateAllUnionReports() {
    if (confirm('This will generate PDF reports for all union elections. Continue?')) {
        showAlert('Generating all union election reports...', 'info');
        window.open(`generate_pdf.php?type=all_union&year=<?php echo $current_year; ?>`, '_blank');
    }
}


function showAlert(message, type) {
    // Create alert element
    const alertDiv = document.createElement('div');
    alertDiv.className = `alert alert-${type} alert-dismissible fade show`;
    alertDiv.innerHTML = `
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;
    
    // Insert at top of container
    const container = document.querySelector('.container-fluid');
    container.insertBefore(alertDiv, container.firstChild);
    
    // Auto dismiss after 3 seconds
    setTimeout(() => {
        if (alertDiv.parentNode) {
            alertDiv.remove();
        }
    }, 3000);
}
</script>

<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
