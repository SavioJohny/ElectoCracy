<?php
session_start();
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/config/database.php';

requireRole('Invigilator');

$page_title = 'View Election Results';
$current_user = getCurrentUser();

if (!isset($_GET['election_id'])) {
    header('Location: results.php');
    exit;
}

$election_id = (int)$_GET['election_id'];

// Verify invigilator has access to this election
$stmt = $pdo->prepare("
    SELECT e.*, c.class_name, d.department_name, et.election_type_name
    FROM elections e
    JOIN classes c ON e.class_id = c.class_id
    JOIN departments d ON c.department_id = d.department_id
    JOIN election_types et ON e.election_type_id = et.election_type_id
    JOIN invigilator_class_assignments ica ON e.class_id = ica.class_id 
        AND e.election_year = ica.election_year
    WHERE e.election_id = ? AND ica.invigilator_id = ?
");
$stmt->execute([$election_id, $current_user['user_id']]);
$election = $stmt->fetch();

if (!$election) {
    header('Location: results.php');
    exit;
}

// Check if voting has ended - results should only be viewable when voting_status is 'ended'
if ($election['voting_status'] !== 'ended') {
    header('Location: results.php?error=voting_not_ended');
    exit;
}

// Get voting statistics
$stmt = $pdo->prepare("
    SELECT 
        COUNT(DISTINCT u.user_id) as total_students,
        COUNT(DISTINCT v.voter_id) as voted_students
    FROM users u
    JOIN roles r ON u.role_id = r.role_id
    LEFT JOIN votes v ON u.user_id = v.voter_id AND v.election_id = ?
    WHERE u.class_id = ? AND r.role_name = 'Student'
");
$stmt->execute([$election_id, $election['class_id']]);
$voting_stats = $stmt->fetch();

// Get results for each gender category
$results = [];
foreach (['girls', 'boys'] as $gender_category) {
    // Get candidates and their vote counts
    $stmt = $pdo->prepare("
        SELECT 
            c.candidate_id,
            u.fname,
            u.lname,
            u.roll_number,
            u.gender,
            COUNT(v.vote_id) as vote_count,
            CASE WHEN COUNT(v.vote_id) = (
                SELECT MAX(vote_count) 
                FROM (
                    SELECT COUNT(v2.vote_id) as vote_count
                    FROM candidates c2
                    JOIN users u2 ON c2.user_id = u2.user_id
                    LEFT JOIN votes v2 ON c2.candidate_id = v2.candidate_id 
                        AND v2.gender_category = ?
                    WHERE c2.election_id = ? AND c2.is_approved = 'approved'
                    AND u2.gender = ?
                    GROUP BY c2.candidate_id
                ) as vote_counts
            ) THEN 1 ELSE 0 END as is_winner
        FROM candidates c
        JOIN users u ON c.user_id = u.user_id
        LEFT JOIN votes v ON c.candidate_id = v.candidate_id AND v.gender_category = ?
        WHERE c.election_id = ? AND c.is_approved = 'approved'
        AND u.gender = ?
        GROUP BY c.candidate_id, u.fname, u.lname, u.roll_number, u.gender
        ORDER BY vote_count DESC, u.fname, u.lname
    ");
    // Convert gender category to actual gender
    $expected_gender = $gender_category === 'girls' ? 'F' : 'M';
    $stmt->execute([$gender_category, $election_id, $expected_gender, $gender_category, $election_id, $expected_gender]);
    $candidates = $stmt->fetchAll();
    
    // Get NIL votes count
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as nil_votes
        FROM votes
        WHERE election_id = ? AND gender_category = ? AND candidate_id IS NULL
    ");
    $stmt->execute([$election_id, $gender_category]);
    $nil_votes = $stmt->fetchColumn();
    
    // Get total votes for this category
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as total_votes
        FROM votes
        WHERE election_id = ? AND gender_category = ?
    ");
    $stmt->execute([$election_id, $gender_category]);
    $total_votes = $stmt->fetchColumn();
    
    $results[$gender_category] = [
        'candidates' => $candidates,
        'nil_votes' => $nil_votes,
        'total_votes' => $total_votes
    ];
}

include dirname(__DIR__) . '/includes/header.php';
?>

<div class="row">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1>Election Results</h1>
            <a href="results.php" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-1"></i>Back to Results
            </a>
        </div>
    </div>
</div>

<!-- Election Info -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-info-circle me-2"></i>
                    <?php echo htmlspecialchars($election['class_name']); ?> - 
                    <?php echo htmlspecialchars($election['department_name']); ?>
                </h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-3">
                        <strong>Election Year:</strong> <?php echo $election['election_year']; ?>
                    </div>
                    <div class="col-md-3">
                        <strong>Election Type:</strong> <?php echo ucfirst($election['election_type_name']); ?>
                    </div>
                    <div class="col-md-3">
                        <strong>Total Students:</strong> <?php echo $voting_stats['total_students']; ?>
                    </div>
                    <div class="col-md-3">
                        <strong>Students Voted:</strong> 
                        <?php echo $voting_stats['voted_students']; ?>
                        (<?php echo $voting_stats['total_students'] > 0 ? round(($voting_stats['voted_students'] / $voting_stats['total_students']) * 100, 1) : 0; ?>%)
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Results by Gender Category -->
<?php foreach (['girls', 'boys'] as $gender_category): ?>
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-<?php echo $gender_category === 'girls' ? 'female' : 'male'; ?> me-2"></i>
                        <?php echo ucfirst($gender_category); ?> Representative Results
                    </h5>
                </div>
                <div class="card-body">
                    <?php if (empty($results[$gender_category]['candidates'])): ?>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>
                            No approved candidates found for <?php echo $gender_category; ?> representative.
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Rank</th>
                                        <th>Candidate</th>
                                        <th>Roll Number</th>
                                        <th>Votes</th>
                                        <th>Percentage</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    $rank = 1;
                                    $prev_votes = null;
                                    $actual_rank = 1;
                                    foreach ($results[$gender_category]['candidates'] as $candidate): 
                                        if ($prev_votes !== null && $candidate['vote_count'] < $prev_votes) {
                                            $actual_rank = $rank;
                                        }
                                        $percentage = $results[$gender_category]['total_votes'] > 0 ? 
                                            round(($candidate['vote_count'] / $results[$gender_category]['total_votes']) * 100, 1) : 0;
                                    ?>
                                        <tr class="<?php echo $candidate['is_winner'] ? 'table-success' : ''; ?>">
                                            <td>
                                                <span class="badge bg-<?php echo $actual_rank === 1 ? 'warning' : 'secondary'; ?>">
                                                    <?php echo $actual_rank; ?>
                                                </span>
                                            </td>
                                            <td>
                                                <strong><?php echo htmlspecialchars($candidate['fname'] . ' ' . $candidate['lname']); ?></strong>
                                                <?php if ($candidate['is_winner']): ?>
                                                    <span class="badge bg-success ms-2">
                                                        <i class="fas fa-crown me-1"></i>Winner
                                                    </span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo htmlspecialchars($candidate['roll_number']); ?></td>
                                            <td>
                                                <span class="badge bg-primary"><?php echo $candidate['vote_count']; ?></span>
                                            </td>
                                            <td><?php echo $percentage; ?>%</td>
                                            <td>
                                                <?php if ($candidate['is_winner']): ?>
                                                    <span class="text-success">
                                                        <i class="fas fa-trophy me-1"></i>Elected
                                                    </span>
                                                <?php else: ?>
                                                    <span class="text-muted">Not Elected</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php 
                                        $prev_votes = $candidate['vote_count'];
                                        $rank++;
                                    endforeach; ?>
                                    
                                    <?php if ($results[$gender_category]['nil_votes'] > 0): ?>
                                        <tr class="table-light">
                                            <td>-</td>
                                            <td><em>NIL Votes</em></td>
                                            <td>-</td>
                                            <td>
                                                <span class="badge bg-secondary"><?php echo $results[$gender_category]['nil_votes']; ?></span>
                                            </td>
                                            <td>
                                                <?php echo $results[$gender_category]['total_votes'] > 0 ? 
                                                    round(($results[$gender_category]['nil_votes'] / $results[$gender_category]['total_votes']) * 100, 1) : 0; ?>%
                                            </td>
                                            <td><em>No Candidate</em></td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <div class="mt-3">
                            <small class="text-muted">
                                Total votes for <?php echo $gender_category; ?> representative: 
                                <strong><?php echo $results[$gender_category]['total_votes']; ?></strong>
                            </small>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
<?php endforeach; ?>

<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
