<?php
session_start();
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/config/database.php';

requireRole('Election Commissioner');

$page_title = 'Class Election Results';
$election_id = (int)($_GET['election_id'] ?? 0);

if (!$election_id) {
    header('Location: results.php');
    exit;
}

// Get class election details
$stmt = $pdo->prepare("
    SELECT e.*, et.election_type_name, c.class_name, d.department_name
    FROM elections e
    JOIN election_types et ON e.election_type_id = et.election_type_id
    LEFT JOIN classes c ON e.class_id = c.class_id
    LEFT JOIN departments d ON c.department_id = d.department_id
    WHERE e.election_id = ? AND et.election_type_name = 'class'
");
$stmt->execute([$election_id]);
$class_election = $stmt->fetch();

if (!$class_election) {
    header('Location: results.php');
    exit;
}

// Check if voting has ended - results should only be viewable when voting_status is 'ended'
if ($class_election['voting_status'] !== 'ended') {
    header('Location: results.php?error=voting_not_ended');
    exit;
}

// Get class positions
$stmt = $pdo->prepare("
    SELECT position_id, position_name, voting_order, is_active
    FROM positions
    WHERE election_type_id = 1
    ORDER BY voting_order
");
$stmt->execute();
$class_positions = $stmt->fetchAll();

include dirname(__DIR__) . '/includes/header.php';
?>

<div class="container-fluid">
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h2><i class="fas fa-users me-2"></i>Class Election Results - <?php echo htmlspecialchars($class_election['class_name']); ?></h2>
                    <p class="text-muted">Detailed results for <?php echo htmlspecialchars($class_election['department_name']); ?> - <?php echo $class_election['election_year']; ?></p>
                </div>
                <div>
                    <a href="results.php" class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-left me-1"></i>Back to Results
                    </a>
                    <?php if ($class_election['voting_status'] === 'ended'): ?>
                        <button class="btn btn-<?php echo $class_election['results_published'] ? 'warning' : 'success'; ?>"
                                onclick="toggleResultsPublication(<?php echo $class_election['results_published'] ? 'false' : 'true'; ?>)">
                            <i class="fas fa-<?php echo $class_election['results_published'] ? 'eye-slash' : 'eye'; ?> me-1"></i>
                            <?php echo $class_election['results_published'] ? 'Hide Results' : 'Publish Results'; ?>
                        </button>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>


    <?php if ($class_election['voting_status'] === 'not_started'): ?>
        <div class="row">
            <div class="col-12">
                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-2"></i>
                    <strong>Election Not Started:</strong> No votes have been cast yet. Results will be available once voting begins.
                </div>
            </div>
        </div>
    <?php else: ?>
        <!-- Results for each gender category -->
        <?php
        $gender_categories = [
            'girls' => ['name' => 'Girls Representative', 'gender' => 'F'],
            'boys' => ['name' => 'Boys Representative', 'gender' => 'M']
        ];

        foreach ($gender_categories as $category => $info): ?>
            <?php
            // Get results for this gender category
            $stmt = $pdo->prepare("
                SELECT
                    c.candidate_id,
                    u.fname,
                    u.lname,
                    u.roll_number,
                    u.gender,
                    COUNT(v.vote_id) as vote_count
                FROM candidates c
                JOIN users u ON c.user_id = u.user_id
                LEFT JOIN votes v ON c.candidate_id = v.candidate_id AND v.election_id = ? AND v.gender_category = ?
                WHERE c.election_id = ? AND c.is_approved = 'approved' AND u.gender = ?
                GROUP BY c.candidate_id
                ORDER BY vote_count DESC, u.fname, u.lname
            ");
            $stmt->execute([$class_election['election_id'], $category, $class_election['election_id'], $info['gender']]);
            $category_results = $stmt->fetchAll();

            // Get nil votes for this gender category
            $stmt = $pdo->prepare("
                SELECT COUNT(*) as nil_votes
                FROM votes v
                WHERE v.election_id = ? AND v.gender_category = ? AND v.candidate_id IS NULL
            ");
            $stmt->execute([$class_election['election_id'], $category]);
            $nil_votes = $stmt->fetchColumn();

            // Calculate total votes for this gender category
            $total_category_votes = array_sum(array_column($category_results, 'vote_count')) + $nil_votes;
            ?>

            <div class="row mb-4">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">
                                <?php if ($category === 'girls'): ?>
                                    <span class="badge me-2" style="background-color: #e91e63; color: white;">
                                        <i class="fas fa-female me-1"></i>
                                        Girls Representative
                                    </span>
                                <?php else: ?>
                                    <span class="badge bg-primary me-2">
                                        <i class="fas fa-male me-1"></i>
                                        Boys Representative
                                    </span>
                                <?php endif; ?>
                                <small class="text-muted ms-2">(<?php echo $total_category_votes; ?> total votes)</small>
                            </h5>
                        </div>
                        <div class="card-body">
                            <?php if (!empty($category_results) || $nil_votes > 0): ?>
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>Rank</th>
                                                <th>Candidate</th>
                                                <th>Gender</th>
                                                <th>Votes</th>
                                                <th>Percentage</th>
                                                <th>Status</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (!empty($category_results)): ?>
                                                <?php
                                                $rank = 1;
                                                $prev_votes = -1;
                                                $actual_rank = 1;
                                                foreach ($category_results as $result):
                                                    if ($result['vote_count'] != $prev_votes) {
                                                        $actual_rank = $rank;
                                                    }
                                                    $percentage = $total_category_votes > 0 ? round(($result['vote_count'] / $total_category_votes) * 100, 1) : 0;
                                                    $is_winner = ($actual_rank === 1 && $result['vote_count'] > 0);
                                                ?>
                                                    <tr class="<?php echo $is_winner ? 'table-success' : ''; ?>">
                                                        <td>
                                                            <?php if ($is_winner): ?>
                                                                <i class="fas fa-trophy text-warning me-1"></i>
                                                            <?php endif; ?>
                                                            <?php echo $actual_rank; ?>
                                                        </td>
                                                        <td>
                                                            <strong><?php echo htmlspecialchars($result['fname'] . ' ' . $result['lname']); ?></strong>
                                                            <br><small class="text-muted"><?php echo htmlspecialchars($result['roll_number']); ?></small>
                                                        </td>
                                                        <td>
                                                            <?php if ($result['gender'] === 'M'): ?>
                                                                <span class="badge bg-primary">
                                                                    <i class="fas fa-male me-1"></i>
                                                                    Male
                                                                </span>
                                                            <?php else: ?>
                                                                <span class="badge" style="background-color: #e91e63; color: white;">
                                                                    <i class="fas fa-female me-1"></i>
                                                                    Female
                                                                </span>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td><strong><?php echo $result['vote_count']; ?></strong></td>
                                                        <td><?php echo $percentage; ?>%</td>
                                                        <td>
                                                            <?php if ($is_winner): ?>
                                                                <span class="badge bg-success">Winner</span>
                                                            <?php else: ?>
                                                                <span class="badge bg-secondary">Candidate</span>
                                                            <?php endif; ?>
                                                        </td>
                                                    </tr>
                                                <?php
                                                    $prev_votes = $result['vote_count'];
                                                    $rank++;
                                                endforeach; ?>
                                            <?php endif; ?>

                                            <?php if ($nil_votes > 0): ?>
                                                <tr>
                                                    <td>-</td>
                                                    <td><em>Nil Votes</em></td>
                                                    <td>-</td>
                                                    <td><strong><?php echo $nil_votes; ?></strong></td>
                                                    <td><?php echo $total_category_votes > 0 ? round(($nil_votes / $total_category_votes) * 100, 1) : 0; ?>%</td>
                                                    <td><span class="badge bg-secondary">Nil</span></td>
                                                </tr>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <div class="alert alert-info">
                                    <i class="fas fa-info-circle me-2"></i>
                                    No votes cast for this position yet.
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<script>
function toggleResultsPublication(publish) {
    const action = publish ? 'publish' : 'hide';
    const confirmMessage = publish ?
        'Are you sure you want to publish the class election results? Students will be able to see them.' :
        'Are you sure you want to hide the class election results? Students will no longer be able to see them.';

    if (!confirm(confirmMessage)) {
        return;
    }

    const formData = new FormData();
    formData.append('action', 'toggle_results_publication');
    formData.append('election_id', <?php echo $election_id; ?>);
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
