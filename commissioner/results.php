<?php
session_start();
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/config/database.php';

requireRole('Election Commissioner');

$page_title = 'Election Results Management';
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
                    <h2><i class="fas fa-chart-bar me-2"></i>Election Results Management - <?php echo $current_year; ?></h2>
                    <p class="text-muted">Manage and publish election results for class and union elections</p>
                </div>
                <div>
                    <a href="dashboard.php" class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-left me-1"></i>Back to Dashboard
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Class Elections Results -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-users me-2"></i>Class Election Results
                    </h5>
                </div>
                <div class="card-body">
                    <?php if (empty($class_elections)): ?>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>
                            No class elections found for <?php echo $current_year; ?>.
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Class</th>
                                        <th>Department</th>
                                        <th>Status</th>
                                        <th>Results Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($class_elections as $election): ?>
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
                                            <td>
                                                <span class="badge bg-<?php echo $election['results_published'] ? 'success' : 'secondary'; ?>">
                                                    <?php echo $election['results_published'] ? 'Published' : 'Hidden'; ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div class="btn-group btn-group-sm">
                                                    <?php if ($election['voting_status'] === 'ended'): ?>
                                                        <a href="class_results.php?election_id=<?php echo $election['election_id']; ?>" 
                                                           class="btn btn-outline-primary">
                                                            View Results
                                                        </a>
                                                        <button class="btn btn-outline-<?php echo $election['results_published'] ? 'warning' : 'success'; ?>"
                                                                onclick="toggleClassResults(<?php echo $election['election_id']; ?>, <?php echo $election['results_published'] ? 'false' : 'true'; ?>)">
                                                            <?php echo $election['results_published'] ? 'Hide Results' : 'Publish Results'; ?>
                                                        </button>
                                                    <?php else: ?>
                                                        <button class="btn btn-outline-secondary" disabled>
                                                            Voting Not Ended
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

    <!-- Union Elections Results -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-university me-2"></i>Union Election Results
                    </h5>
                </div>
                <div class="card-body">
                    <?php if (empty($union_elections)): ?>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>
                            No union elections found for <?php echo $current_year; ?>.
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Election Year</th>
                                        <th>Status</th>
                                        <th>Results Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($union_elections as $election): ?>
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
                                            <td>
                                                <span class="badge bg-<?php echo $election['results_published'] ? 'success' : 'secondary'; ?>">
                                                    <?php echo $election['results_published'] ? 'Published' : 'Hidden'; ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div class="btn-group btn-group-sm">
                                                    <?php if ($election['voting_status'] === 'ended'): ?>
                                                        <a href="union_results.php?election_id=<?php echo $election['election_id']; ?>" 
                                                           class="btn btn-outline-primary">
                                                            View Results
                                                        </a>
                                                        <button class="btn btn-outline-<?php echo $election['results_published'] ? 'warning' : 'success'; ?>"
                                                                onclick="toggleUnionResults(<?php echo $election['election_id']; ?>, <?php echo $election['results_published'] ? 'false' : 'true'; ?>)">
                                                            <?php echo $election['results_published'] ? 'Hide Results' : 'Publish Results'; ?>
                                                        </button>
                                                    <?php else: ?>
                                                        <button class="btn btn-outline-secondary" disabled>
                                                            Voting Not Ended
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
function toggleClassResults(electionId, publish) {
    const action = publish ? 'publish' : 'hide';
    const confirmMessage = publish ?
        'Are you sure you want to publish the class election results? Students will be able to see them.' :
        'Are you sure you want to hide the class election results? Students will no longer be able to see them.';

    if (!confirm(confirmMessage)) {
        return;
    }

    const formData = new FormData();
    formData.append('action', 'toggle_results_publication');
    formData.append('election_id', electionId);
    formData.append('publish', publish ? '1' : '0');

    fetch('class_results_handler.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showAlert(data.message, 'success');
            setTimeout(() => {
                window.location.reload();
            }, 1500);
        } else {
            showAlert(data.message, 'danger');
        }
    })
    .catch(error => {
        showAlert('Network error. Please try again.', 'danger');
    });
}

function toggleUnionResults(electionId, publish) {
    const action = publish ? 'publish' : 'hide';
    const confirmMessage = publish ?
        'Are you sure you want to publish the union election results? Students will be able to see them.' :
        'Are you sure you want to hide the union election results? Students will no longer be able to see them.';

    if (!confirm(confirmMessage)) {
        return;
    }

    const formData = new FormData();
    formData.append('action', 'toggle_results_publication');
    formData.append('election_id', electionId);
    formData.append('publish', publish ? '1' : '0');

    fetch('union_results_handler.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showAlert(data.message, 'success');
            setTimeout(() => {
                window.location.reload();
            }, 1500);
        } else {
            showAlert(data.message, 'danger');
        }
    })
    .catch(error => {
        showAlert('Network error. Please try again.', 'danger');
    });
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
    
    // Auto dismiss after 5 seconds
    setTimeout(() => {
        if (alertDiv.parentNode) {
            alertDiv.remove();
        }
    }, 5000);
}
</script>

<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
