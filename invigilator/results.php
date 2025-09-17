<?php
session_start();
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/config/database.php';

requireRole('Invigilator');

$page_title = 'Election Results';
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

    // Check if results_published column exists
    $stmt = $pdo->query("SHOW COLUMNS FROM elections LIKE 'results_published'");
    $results_column_exists = $stmt->fetch();

    $select_fields = "e.*, et.election_type_name, c.class_name, d.department_name";
    if ($results_column_exists) {
        $select_fields .= ", e.results_published";
    }

    $stmt = $pdo->prepare("
        SELECT {$select_fields}
        FROM elections e
        JOIN election_types et ON e.election_type_id = et.election_type_id
        JOIN classes c ON e.class_id = c.class_id
        JOIN departments d ON c.department_id = d.department_id
        WHERE e.class_id IN ($placeholders)
        ORDER BY e.election_year DESC, c.class_name
    ");
    $stmt->execute($class_ids);
    $elections = $stmt->fetchAll();
}

include dirname(__DIR__) . '/includes/header.php';
?>

<div class="row">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1>Election Results</h1>
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
                No elections found for your assigned classes in <?php echo $current_year; ?>.
            </div>
        </div>
    </div>
<?php else: ?>
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-chart-bar me-2"></i>Elections for <?php echo $current_year; ?></h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Class</th>
                                    <th>Department</th>
                                    <th>Results Status</th>
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
                                            // Check if results_published column exists
                                            $results_published = $election['results_published'] ?? false;
                                            ?>
                                            <?php if ($results_published): ?>
                                                <span class="badge bg-success">
                                                    <i class="fas fa-eye me-1"></i>Published
                                                </span>
                                            <?php else: ?>
                                                <span class="badge bg-warning">
                                                    <i class="fas fa-eye-slash me-1"></i>Not Published
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="btn-group" role="group">
                                                <a href="view_results.php?election_id=<?php echo $election['election_id']; ?>" 
                                                   class="btn btn-sm btn-outline-primary">
                                                    <i class="fas fa-chart-bar me-1"></i>View Results
                                                </a>
                                                
                                                <?php if (isset($election['results_published'])): ?>
                                                    <?php if ($election['voting_status'] !== 'ended'): ?>
                                                        <button class="btn btn-sm btn-outline-secondary" disabled>
                                                            <i class="fas fa-clock me-1"></i>Voting Not Ended
                                                        </button>
                                                    <?php elseif ($election['results_published']): ?>
                                                        <button class="btn btn-sm btn-outline-warning toggle-publish-btn"
                                                                data-election-id="<?php echo $election['election_id']; ?>"
                                                                data-action="unpublish"
                                                                data-class-name="<?php echo htmlspecialchars($election['class_name']); ?>">
                                                            <i class="fas fa-eye-slash me-1"></i>Unpublish
                                                        </button>
                                                    <?php else: ?>
                                                        <button class="btn btn-sm btn-outline-success toggle-publish-btn"
                                                                data-election-id="<?php echo $election['election_id']; ?>"
                                                                data-action="publish"
                                                                data-class-name="<?php echo htmlspecialchars($election['class_name']); ?>">
                                                            <i class="fas fa-eye me-1"></i>Publish
                                                        </button>
                                                    <?php endif; ?>
                                                <?php else: ?>
                                                    <span class="btn btn-sm btn-outline-secondary disabled">
                                                        <i class="fas fa-exclamation-triangle me-1"></i>DB Update Needed
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
// Handle publish/unpublish buttons
document.querySelectorAll('.toggle-publish-btn').forEach(btn => {
    btn.addEventListener('click', function() {
        const electionId = this.dataset.electionId;
        const action = this.dataset.action;
        const className = this.dataset.className;
        
        const actionText = action === 'publish' ? 'publish' : 'unpublish';
        const confirmMessage = `Are you sure you want to ${actionText} results for ${className}?`;
        
        if (!confirm(confirmMessage)) {
            return;
        }
        
        const originalText = this.innerHTML;
        this.disabled = true;
        this.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Processing...';
        
        const formData = new FormData();
        formData.append('action', 'toggle_publish');
        formData.append('election_id', electionId);
        formData.append('publish_action', action);
        
        fetch('results_handler.php', {
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
