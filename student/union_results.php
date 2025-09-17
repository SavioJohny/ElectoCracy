<?php
session_start();
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/config/database.php';

requireRole('Student');

$page_title = 'Union Election Results';
$current_user = getCurrentUser();

// Get current year
$current_year = date('Y');

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
$stmt->execute([$current_user['user_id'], $current_year]);
$is_union_eligible = $stmt->fetchColumn() > 0;

// If student is not union-eligible, redirect to dashboard with message
if (!$is_union_eligible) {
    header('Location: dashboard.php?message=union_not_eligible');
    exit;
}

// Get published union election results
$stmt = $pdo->prepare("
    SELECT e.*, et.election_type_name
    FROM elections e
    JOIN election_types et ON e.election_type_id = et.election_type_id
    WHERE e.election_year = ? AND et.election_type_name = 'union' 
    AND e.results_published = 1
    ORDER BY e.election_year DESC
");
$stmt->execute([$current_year]);
$union_election = $stmt->fetch();

// Get all union positions
$stmt = $pdo->prepare("
    SELECT * FROM positions
    WHERE election_type_id = 2
    ORDER BY voting_order
");
$stmt->execute();
$union_positions = $stmt->fetchAll();

include dirname(__DIR__) . '/includes/header.php';
?>

<div class="container mt-4">
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1><i class="fas fa-trophy me-2"></i>Union Election Results</h1>
                <a href="dashboard.php" class="btn btn-outline-secondary">
                    <i class="fas fa-arrow-left me-1"></i>Back to Dashboard
                </a>
            </div>
        </div>
    </div>

    <?php if (!$union_election): ?>
        <!-- No Results Available -->
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-body text-center py-5">
                        <i class="fas fa-chart-bar fa-3x text-muted mb-3"></i>
                        <h4 class="text-muted">No Results Available</h4>
                        <p class="text-muted">
                            Union election results for <?php echo $current_year; ?> have not been published yet.<br>
                            Please check back later or contact your election commissioner.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    <?php else: ?>
        <!-- Election Info -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0">
                            <i class="fas fa-university me-2"></i>
                            Union Election Results - <?php echo $union_election['election_year']; ?>
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <p><strong>Election Year:</strong> <?php echo $union_election['election_year']; ?></p>
                                <p><strong>Election Type:</strong> Union Wide</p>
                            </div>
                            <div class="col-md-6">
                                <p><strong>Status:</strong> 
                                    <span class="badge bg-success">Results Published</span>
                                </p>
                                <p><strong>Total Positions:</strong> <?php echo count($union_positions); ?></p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Results by Position -->
        <?php foreach ($union_positions as $position): ?>
            <?php
            // Get results for this position
            $stmt = $pdo->prepare("
                SELECT 
                    c.candidate_id,
                    u.fname,
                    u.lname,
                    u.roll_number,
                    cl.class_name,
                    d.department_name,
                    COUNT(v.vote_id) as vote_count
                FROM candidates c
                JOIN users u ON c.user_id = u.user_id
                JOIN classes cl ON u.class_id = cl.class_id
                JOIN departments d ON cl.department_id = d.department_id
                LEFT JOIN votes v ON c.candidate_id = v.candidate_id AND v.election_id = ?
                WHERE c.position_id = ? AND c.election_id = ? AND c.is_approved = 'approved'
                GROUP BY c.candidate_id
                ORDER BY vote_count DESC, u.fname, u.lname
            ");
            $stmt->execute([$union_election['election_id'], $position['position_id'], $union_election['election_id']]);
            $position_results = $stmt->fetchAll();

            // Get nil votes for this position
            $stmt = $pdo->prepare("
                SELECT COUNT(*) as nil_votes
                FROM votes v
                WHERE v.election_id = ? AND v.position_id = ? AND v.candidate_id IS NULL
            ");
            $stmt->execute([$union_election['election_id'], $position['position_id']]);
            $nil_votes = $stmt->fetchColumn();

            // Calculate total votes for this position
            $total_position_votes = array_sum(array_column($position_results, 'vote_count')) + $nil_votes;
            ?>

            <div class="row mb-4">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">
                                <span class="badge bg-primary me-2"><?php echo $position['voting_order']; ?></span>
                                <?php echo htmlspecialchars($position['position_name']); ?>
                                <small class="text-muted ms-2">(<?php echo $total_position_votes; ?> total votes)</small>
                            </h5>
                        </div>
                        <div class="card-body">
                            <?php if (!empty($position_results)): ?>
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>Rank</th>
                                                <th>Candidate</th>
                                                <th>Class</th>
                                                <th>Votes</th>
                                                <th>Percentage</th>
                                                <th>Status</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php 
                                            $rank = 1;
                                            $prev_votes = -1;
                                            $actual_rank = 1;
                                            foreach ($position_results as $result): 
                                                if ($result['vote_count'] != $prev_votes) {
                                                    $actual_rank = $rank;
                                                }
                                                $percentage = $total_position_votes > 0 ? round(($result['vote_count'] / $total_position_votes) * 100, 1) : 0;
                                                $is_winner = ($actual_rank === 1 && $result['vote_count'] > 0);
                                            ?>
                                                <tr class="<?php echo $is_winner ? 'table-success' : ''; ?>">
                                                    <td>
                                                        <?php if ($is_winner): ?>
                                                            <i class="fas fa-trophy text-warning me-1"></i>
                                                        <?php endif; ?>
                                                        <strong><?php echo $actual_rank; ?></strong>
                                                    </td>
                                                    <td>
                                                        <div class="d-flex align-items-center">
                                                            <div class="avatar-sm bg-primary text-white rounded-circle d-flex align-items-center justify-content-center me-3">
                                                                <?php echo strtoupper(substr($result['fname'], 0, 1) . substr($result['lname'], 0, 1)); ?>
                                                            </div>
                                                            <div>
                                                                <strong><?php echo htmlspecialchars($result['fname'] . ' ' . $result['lname']); ?></strong>
                                                                <br><small class="text-muted"><?php echo htmlspecialchars($result['roll_number']); ?></small>
                                                            </div>
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <?php echo htmlspecialchars($result['class_name']); ?>
                                                        <br><small class="text-muted"><?php echo htmlspecialchars($result['department_name']); ?></small>
                                                    </td>
                                                    <td><strong><?php echo $result['vote_count']; ?></strong></td>
                                                    <td>
                                                        <div class="progress" style="height: 20px;">
                                                            <div class="progress-bar <?php echo $is_winner ? 'bg-success' : 'bg-primary'; ?>" 
                                                                 style="width: <?php echo $percentage; ?>%">
                                                                <?php echo $percentage; ?>%
                                                            </div>
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <?php if ($is_winner): ?>
                                                            <span class="badge bg-success">
                                                                <i class="fas fa-crown me-1"></i>Winner
                                                            </span>
                                                        <?php else: ?>
                                                            <span class="badge bg-secondary">Candidate</span>
                                                        <?php endif; ?>
                                                    </td>
                                                </tr>
                                            <?php 
                                                $prev_votes = $result['vote_count'];
                                                $rank++;
                                            endforeach; ?>
                                            
                                            <?php if ($nil_votes > 0): ?>
                                                <tr>
                                                    <td>-</td>
                                                    <td>
                                                        <div class="d-flex align-items-center">
                                                            <div class="avatar-sm bg-secondary text-white rounded-circle d-flex align-items-center justify-content-center me-3">
                                                                <i class="fas fa-ban"></i>
                                                            </div>
                                                            <div>
                                                                <em>Nil Votes</em>
                                                                <br><small class="text-muted">No candidate selected</small>
                                                            </div>
                                                        </div>
                                                    </td>
                                                    <td>-</td>
                                                    <td><strong><?php echo $nil_votes; ?></strong></td>
                                                    <td>
                                                        <div class="progress" style="height: 20px;">
                                                            <div class="progress-bar bg-secondary" 
                                                                 style="width: <?php echo $total_position_votes > 0 ? round(($nil_votes / $total_position_votes) * 100, 1) : 0; ?>%">
                                                                <?php echo $total_position_votes > 0 ? round(($nil_votes / $total_position_votes) * 100, 1) : 0; ?>%
                                                            </div>
                                                        </div>
                                                    </td>
                                                    <td><span class="badge bg-secondary">Nil</span></td>
                                                </tr>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <div class="alert alert-info">
                                    <i class="fas fa-info-circle me-2"></i>
                                    No candidates participated in this position.
                                    <?php if ($nil_votes > 0): ?>
                                        <br><strong>Nil Votes:</strong> <?php echo $nil_votes; ?>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<style>
.avatar-sm {
    width: 40px;
    height: 40px;
    font-size: 0.875rem;
    font-weight: bold;
}

.progress {
    background-color: #e9ecef;
}

.table-success {
    background-color: rgba(25, 135, 84, 0.1);
}
</style>

<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
