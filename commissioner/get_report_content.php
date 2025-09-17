<?php
session_start();
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/config/database.php';

requireRole('Election Commissioner');

$type = $_GET['type'] ?? '';
$election_id = (int)($_GET['election_id'] ?? 0);

if (!$type || !$election_id) {
    die('Report type and election ID are required.');
}

try {
    if ($type === 'class') {
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
        $election = $stmt->fetch();
        
        if (!$election) {
            throw new Exception('Class election not found.');
        }
        
        echo generateClassReportContent($pdo, $election);
        
    } elseif ($type === 'union') {
        // Get union election details
        $stmt = $pdo->prepare("
            SELECT e.*, et.election_type_name
            FROM elections e
            JOIN election_types et ON e.election_type_id = et.election_type_id
            WHERE e.election_id = ? AND et.election_type_name = 'union'
        ");
        $stmt->execute([$election_id]);
        $election = $stmt->fetch();
        
        if (!$election) {
            throw new Exception('Union election not found.');
        }
        
        echo generateUnionReportContent($pdo, $election);
        
    } else {
        throw new Exception('Invalid report type.');
    }
    
} catch (Exception $e) {
    echo '<div class="alert alert-danger">Error: ' . $e->getMessage() . '</div>';
}

function generateClassReportContent($pdo, $election) {
    $content = "<div class='section'>";
    $content .= "<h3>Election Information</h3>";
    $content .= "<div class='alert alert-info'>";
    $content .= "<p><strong>Class:</strong> {$election['class_name']}</p>";
    $content .= "<p><strong>Department:</strong> {$election['department_name']}</p>";
    $content .= "<p><strong>Election Year:</strong> {$election['election_year']}</p>";
    $content .= "<p><strong>Status:</strong> " . ucfirst(str_replace('_', ' ', $election['voting_status'])) . "</p>";
    $content .= "</div></div>";
    
    // Get voting statistics
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as total_students
        FROM users u
        JOIN roles r ON u.role_id = r.role_id
        WHERE u.class_id = ? AND r.role_name = 'Student'
    ");
    $stmt->execute([$election['class_id']]);
    $total_students = $stmt->fetchColumn();
    
    $stmt = $pdo->prepare("
        SELECT COUNT(DISTINCT voter_id) as voted_students
        FROM votes
        WHERE election_id = ?
    ");
    $stmt->execute([$election['election_id']]);
    $voted_students = $stmt->fetchColumn();
    
    $content .= "<div class='section'>";
    $content .= "<h3>Voting Statistics</h3>";
    $content .= "<div class='alert alert-secondary'>";
    $content .= "<p><strong>Total Students:</strong> {$total_students}</p>";
    $content .= "<p><strong>Participation Rate:</strong> " . ($total_students > 0 ? round(($voted_students / $total_students) * 100, 1) : 0) . "%</p>";
    $content .= "</div></div>";
    
    // Generate results for each gender category
    $gender_categories = [
        'girls' => ['name' => 'Girls Representative', 'gender' => 'F'],
        'boys' => ['name' => 'Boys Representative', 'gender' => 'M']
    ];
    
    foreach ($gender_categories as $category => $info) {
        $content .= "<div class='section'>";
        $content .= "<h3>{$info['name']} Results</h3>";
        
        // Get candidates and results
        $stmt = $pdo->prepare("
            SELECT
                c.candidate_id,
                u.fname,
                u.lname,
                u.roll_number,
                COUNT(v.vote_id) as vote_count
            FROM candidates c
            JOIN users u ON c.user_id = u.user_id
            LEFT JOIN votes v ON c.candidate_id = v.candidate_id AND v.election_id = ? AND v.gender_category = ?
            WHERE c.election_id = ? AND c.is_approved = 'approved' AND u.gender = ?
            GROUP BY c.candidate_id
            ORDER BY vote_count DESC, u.fname, u.lname
        ");
        $stmt->execute([$election['election_id'], $category, $election['election_id'], $info['gender']]);
        $results = $stmt->fetchAll();
        
        // Get nil votes
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as nil_votes
            FROM votes v
            WHERE v.election_id = ? AND v.gender_category = ? AND v.candidate_id IS NULL
        ");
        $stmt->execute([$election['election_id'], $category]);
        $nil_votes = $stmt->fetchColumn();
        
        $total_votes = array_sum(array_column($results, 'vote_count')) + $nil_votes;
        
        $content .= "<div class='table-responsive'>";
        $content .= "<table class='table table-bordered table-hover'>";
        $content .= "<thead class='table-dark'><tr><th>Rank</th><th>Candidate</th><th>Roll Number</th><th>Votes</th><th>Percentage</th><th>Status</th></tr></thead>";
        $content .= "<tbody>";
        
        $rank = 1;
        foreach ($results as $result) {
            $percentage = $total_votes > 0 ? round(($result['vote_count'] / $total_votes) * 100, 1) : 0;
            $is_winner = ($rank === 1 && $result['vote_count'] > 0);
            $row_class = $is_winner ? "table-success" : "";
            
            $content .= "<tr class='{$row_class}'>";
            $content .= "<td>{$rank}</td>";
            $content .= "<td><strong>{$result['fname']} {$result['lname']}</strong></td>";
            $content .= "<td>{$result['roll_number']}</td>";
            $content .= "<td><span class='badge bg-primary'>{$result['vote_count']}</span></td>";
            $content .= "<td>{$percentage}%</td>";
            $content .= "<td>" . ($is_winner ? "<span class='badge bg-success'>Winner</span>" : "<span class='badge bg-secondary'>Candidate</span>") . "</td>";
            $content .= "</tr>";
            $rank++;
        }
        
        if ($nil_votes > 0) {
            $nil_percentage = $total_votes > 0 ? round(($nil_votes / $total_votes) * 100, 1) : 0;
            $content .= "<tr>";
            $content .= "<td>-</td>";
            $content .= "<td><em>Nil Votes</em></td>";
            $content .= "<td>-</td>";
            $content .= "<td><span class='badge bg-secondary'>{$nil_votes}</span></td>";
            $content .= "<td>{$nil_percentage}%</td>";
            $content .= "<td><span class='badge bg-secondary'>Nil</span></td>";
            $content .= "</tr>";
        }
        
        $content .= "</tbody></table>";
        $content .= "</div>";
        $content .= "<p><strong>Total Votes for {$info['name']}:</strong> <span class='badge bg-info'>{$total_votes}</span></p>";
        $content .= "</div>";
    }
    
    return $content;
}

function generateUnionReportContent($pdo, $election) {
    $content = "<div class='section'>";
    $content .= "<h3>Election Information</h3>";
    $content .= "<div class='alert alert-info'>";
    $content .= "<p><strong>Election Year:</strong> {$election['election_year']}</p>";
    $content .= "<p><strong>Status:</strong> " . ucfirst(str_replace('_', ' ', $election['voting_status'])) . "</p>";
    $content .= "</div></div>";
    
    // Get union positions
    $stmt = $pdo->prepare("
        SELECT position_id, position_name, voting_order
        FROM positions
        WHERE election_type_id = 2
        ORDER BY voting_order
    ");
    $stmt->execute();
    $positions = $stmt->fetchAll();
    
    foreach ($positions as $position) {
        $content .= "<div class='section'>";
        $content .= "<h3>{$position['position_name']} Results</h3>";
        
        // Get candidates and results for this position
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
        $stmt->execute([$election['election_id'], $position['position_id'], $election['election_id']]);
        $results = $stmt->fetchAll();
        
        // Get nil votes for this position
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as nil_votes
            FROM votes v
            WHERE v.election_id = ? AND v.position_id = ? AND v.candidate_id IS NULL
        ");
        $stmt->execute([$election['election_id'], $position['position_id']]);
        $nil_votes = $stmt->fetchColumn();
        
        $total_votes = array_sum(array_column($results, 'vote_count')) + $nil_votes;
        
        $content .= "<div class='table-responsive'>";
        $content .= "<table class='table table-bordered table-hover'>";
        $content .= "<thead class='table-dark'><tr><th>Rank</th><th>Candidate</th><th>Class</th><th>Votes</th><th>Percentage</th><th>Status</th></tr></thead>";
        $content .= "<tbody>";
        
        $rank = 1;
        foreach ($results as $result) {
            $percentage = $total_votes > 0 ? round(($result['vote_count'] / $total_votes) * 100, 1) : 0;
            $is_winner = ($rank === 1 && $result['vote_count'] > 0);
            $row_class = $is_winner ? "table-success" : "";
            
            $content .= "<tr class='{$row_class}'>";
            $content .= "<td>{$rank}</td>";
            $content .= "<td><strong>{$result['fname']} {$result['lname']}</strong><br><small class='text-muted'>{$result['roll_number']}</small></td>";
            $content .= "<td>{$result['class_name']}<br><small class='text-muted'>{$result['department_name']}</small></td>";
            $content .= "<td><span class='badge bg-primary'>{$result['vote_count']}</span></td>";
            $content .= "<td>{$percentage}%</td>";
            $content .= "<td>" . ($is_winner ? "<span class='badge bg-success'>Winner</span>" : "<span class='badge bg-secondary'>Candidate</span>") . "</td>";
            $content .= "</tr>";
            $rank++;
        }
        
        if ($nil_votes > 0) {
            $nil_percentage = $total_votes > 0 ? round(($nil_votes / $total_votes) * 100, 1) : 0;
            $content .= "<tr>";
            $content .= "<td>-</td>";
            $content .= "<td><em>Nil Votes</em></td>";
            $content .= "<td>-</td>";
            $content .= "<td><span class='badge bg-secondary'>{$nil_votes}</span></td>";
            $content .= "<td>{$nil_percentage}%</td>";
            $content .= "<td><span class='badge bg-secondary'>Nil</span></td>";
            $content .= "</tr>";
        }
        
        $content .= "</tbody></table>";
        $content .= "</div>";
        $content .= "<p><strong>Total Votes for {$position['position_name']}:</strong> <span class='badge bg-info'>{$total_votes}</span></p>";
        $content .= "</div>";
    }
    
    return $content;
}
?>
