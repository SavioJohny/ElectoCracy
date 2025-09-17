<?php
session_start();
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/config/database.php';

requireRole('Student');

$page_title = 'Election Results';
$current_user = getCurrentUser();

// Check if current student is union-eligible (approved candidate in class elections)
$stmt = $pdo->prepare("
    SELECT COUNT(*) as is_union_eligible
    FROM candidates c
    JOIN elections e ON c.election_id = e.election_id
    JOIN election_types et ON e.election_type_id = et.election_type_id
    WHERE c.user_id = ?
    AND et.election_type_name = 'class'
    AND e.election_year = ?
    AND c.is_approved = 'approved'
");
$stmt->execute([$current_user['user_id'], date('Y')]);
$is_union_eligible = $stmt->fetchColumn() > 0;

// Only show class elections in this page
$where_clause = "e.class_id = ? AND et.election_type_name = 'class'";
$params = [$current_user['class_id']];

// Get class elections where results are published
$stmt = $pdo->prepare("
    SELECT e.*, et.election_type_name, c.class_name, d.department_name
    FROM elections e
    JOIN election_types et ON e.election_type_id = et.election_type_id
    LEFT JOIN classes c ON e.class_id = c.class_id
    LEFT JOIN departments d ON c.department_id = d.department_id
    WHERE {$where_clause}
    AND (e.results_published = 1 OR e.results_published IS NULL)
    ORDER BY e.election_year DESC, e.election_id DESC
");
$stmt->execute($params);
$published_elections = $stmt->fetchAll();

// Get class elections where results are not yet published
$stmt = $pdo->prepare("
    SELECT e.*, et.election_type_name, c.class_name, d.department_name
    FROM elections e
    JOIN election_types et ON e.election_type_id = et.election_type_id
    LEFT JOIN classes c ON e.class_id = c.class_id
    LEFT JOIN departments d ON c.department_id = d.department_id
    WHERE {$where_clause}
    AND e.results_published = 0
    ORDER BY e.election_year DESC, e.election_id DESC
");
$stmt->execute($params);
$unpublished_elections = $stmt->fetchAll();

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

<!-- Unpublished Elections Notice -->
<?php if (!empty($unpublished_elections)): ?>
    <div class="row mb-4">
        <div class="col-12">
            <div class="alert alert-info">
                <h6><i class="fas fa-clock me-2"></i>Results Pending Publication</h6>
                <p class="mb-2">The following elections have completed voting, but results are not yet published:</p>
                <ul class="mb-0">
                    <?php foreach ($unpublished_elections as $election): ?>
                        <li>
                            <?php echo htmlspecialchars($election['class_name']); ?> - 
                            <?php echo htmlspecialchars($election['department_name']); ?>
                            (<?php echo $election['election_year']; ?>)
                        </li>
                    <?php endforeach; ?>
                </ul>
                <small class="text-muted d-block mt-2">
                    Results will be visible here once published by the invigilator.
                </small>
            </div>
        </div>
    </div>
<?php endif; ?>

<!-- Published Results -->
<?php if (empty($published_elections)): ?>
    <div class="row">
        <div class="col-12">
            <div class="alert alert-warning">
                <i class="fas fa-info-circle me-2"></i>
                No published election results are available at this time.
                <?php if (!empty($unpublished_elections)): ?>
                    Please check back later as results are pending publication.
                <?php endif; ?>
            </div>
        </div>
    </div>
<?php else: ?>
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-chart-bar me-2"></i>Class Election Results</h5>
                </div>
                <div class="card-body">
                    <?php if (count($published_elections) === 1): ?>
                        <!-- Single election - show directly without accordion -->
                        <?php $election = $published_elections[0]; ?>
                        <div class="mb-3">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <div>
                                    <h6 class="mb-1">
                                        <?php echo htmlspecialchars($election['class_name']); ?> - 
                                        <?php echo htmlspecialchars($election['department_name']); ?>
                                    </h6>
                                    <small class="text-muted">
                                        Class Election - <?php echo $election['election_year']; ?>
                                    </small>
                                </div>
                                <span class="badge bg-success">Published</span>
                            </div>
                            <div class="election-results" data-election-id="<?php echo $election['election_id']; ?>">
                                <div class="text-center">
                                    <div class="spinner-border text-primary" role="status">
                                        <span class="visually-hidden">Loading results...</span>
                                    </div>
                                    <p class="mt-2 text-muted">Loading election results...</p>
                                </div>
                            </div>
                        </div>
                    <?php else: ?>
                        <!-- Multiple elections - use accordion -->
                        <div class="accordion" id="resultsAccordion">
                            <?php foreach ($published_elections as $index => $election): ?>
                                <div class="accordion-item">
                                    <h2 class="accordion-header" id="heading<?php echo $index; ?>">
                                        <button class="accordion-button <?php echo $index === 0 ? '' : 'collapsed'; ?>" 
                                                type="button" 
                                                data-bs-toggle="collapse" 
                                                data-bs-target="#collapse<?php echo $index; ?>" 
                                                aria-expanded="<?php echo $index === 0 ? 'true' : 'false'; ?>" 
                                                aria-controls="collapse<?php echo $index; ?>">
                                            <div class="d-flex justify-content-between align-items-center w-100 me-3">
                                                <div>
                                                    <strong>
                                                        <?php echo htmlspecialchars($election['class_name']); ?> - 
                                                        <?php echo htmlspecialchars($election['department_name']); ?>
                                                    </strong>
                                                    <small class="text-muted d-block">
                                                        Class Election - <?php echo $election['election_year']; ?>
                                                    </small>
                                                </div>
                                                <span class="badge bg-success">Published</span>
                                            </div>
                                        </button>
                                    </h2>
                                    <div id="collapse<?php echo $index; ?>" 
                                         class="accordion-collapse collapse <?php echo $index === 0 ? 'show' : ''; ?>" 
                                         aria-labelledby="heading<?php echo $index; ?>" 
                                         data-bs-parent="#resultsAccordion">
                                        <div class="accordion-body">
                                            <div class="election-results" data-election-id="<?php echo $election['election_id']; ?>">
                                                <div class="text-center">
                                                    <div class="spinner-border text-primary" role="status">
                                                        <span class="visually-hidden">Loading results...</span>
                                                    </div>
                                                    <p class="mt-2 text-muted">Loading election results...</p>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>

<script>
// Load results when page loads or accordion is opened
document.addEventListener('DOMContentLoaded', function() {
    // Load first election results immediately (for both single and accordion views)
    const firstElection = document.querySelector('.election-results');
    if (firstElection) {
        loadElectionResults(firstElection.dataset.electionId, firstElection);
    }
    
    // Load results when accordion items are opened (if accordion exists)
    const accordion = document.getElementById('resultsAccordion');
    if (accordion) {
        accordion.addEventListener('shown.bs.collapse', function(e) {
            const resultsContainer = e.target.querySelector('.election-results');
            if (resultsContainer && !resultsContainer.dataset.loaded) {
                loadElectionResults(resultsContainer.dataset.electionId, resultsContainer);
            }
        });
    }
});

function loadElectionResults(electionId, container) {
    if (container.dataset.loaded) return;
    
    fetch(`get_results.php?election_id=${electionId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                container.innerHTML = data.html;
                container.dataset.loaded = 'true';
            } else {
                container.innerHTML = `
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-circle me-2"></i>
                        Error loading results: ${data.message}
                    </div>
                `;
            }
        })
        .catch(error => {
            container.innerHTML = `
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle me-2"></i>
                    Network error loading results. Please try again.
                </div>
            `;
        });
}
</script>

<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
