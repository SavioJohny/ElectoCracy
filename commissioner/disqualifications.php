<?php
session_start();
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/config/database.php';

requireRole('Election Commissioner');

$page_title = 'Student Disqualifications';
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
                    <h2><i class="fas fa-user-slash me-2"></i>Student Disqualifications - <?php echo $current_year; ?></h2>
                    <p class="text-muted">Manage student disqualifications for class and union elections</p>
                </div>
                <div>
                    <a href="dashboard.php" class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-left me-1"></i>Back to Dashboard
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Class Elections Disqualifications -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-users me-2"></i>Class Election Disqualifications
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
                                        <th>Total Students</th>
                                        <th>Disqualified</th>
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

                                        // Get disqualified students for this election
                                        $stmt = $pdo->prepare("
                                            SELECT COUNT(*) as disqualified_count
                                            FROM election_disqualifications
                                            WHERE election_id = ?
                                        ");
                                        $stmt->execute([$election['election_id']]);
                                        $disqualified_count = $stmt->fetchColumn();
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
                                            <td><span class="badge bg-danger"><?php echo $disqualified_count; ?></span></td>
                                            <td>
                                                <a href="class_disqualifications.php?election_id=<?php echo $election['election_id']; ?>" 
                                                   class="btn btn-sm btn-outline-primary" title="Manage Disqualifications">
                                                    <i class="fas fa-user-slash me-1"></i>Manage
                                                </a>
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

    <!-- Union Elections Disqualifications -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-university me-2"></i>Union Election Disqualifications
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
                                        <th>Union-Eligible Students</th>
                                        <th>Disqualified</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($union_elections as $election): ?>
                                        <?php
                                        // Get total union-eligible students (approved candidates in class elections)
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

                                        // Get disqualified students for this union election
                                        $stmt = $pdo->prepare("
                                            SELECT COUNT(*) as disqualified_count
                                            FROM election_disqualifications
                                            WHERE election_id = ?
                                        ");
                                        $stmt->execute([$election['election_id']]);
                                        $disqualified_count = $stmt->fetchColumn();
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
                                            <td><span class="badge bg-danger"><?php echo $disqualified_count; ?></span></td>
                                            <td>
                                                <a href="union_disqualifications.php?election_id=<?php echo $election['election_id']; ?>" 
                                                   class="btn btn-sm btn-outline-primary" title="Manage Disqualifications">
                                                    <i class="fas fa-user-slash me-1"></i>Manage
                                                </a>
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

<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
