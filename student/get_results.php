<?php
session_start();
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/config/database.php';

requireRole('Student');

header('Content-Type: application/json');

$response = ['success' => false, 'message' => '', 'html' => ''];

try {
    $current_user = getCurrentUser();
    
    if (!isset($_GET['election_id'])) {
        throw new Exception('Election ID not provided.');
    }
    
    $election_id = (int)$_GET['election_id'];
    
    // Verify student can view this election and it's published
    $stmt = $pdo->prepare("
        SELECT e.*, et.election_type_name, c.class_name, d.department_name
        FROM elections e
        JOIN election_types et ON e.election_type_id = et.election_type_id
        LEFT JOIN classes c ON e.class_id = c.class_id
        LEFT JOIN departments d ON c.department_id = d.department_id
        WHERE e.election_id = ? 
        AND (e.class_id = ? OR e.class_id IS NULL)
        AND (e.results_published = 1 OR e.results_published IS NULL)
    ");
    $stmt->execute([$election_id, $current_user['class_id']]);
    $election = $stmt->fetch();
    
    if (!$election) {
        throw new Exception('Election not found or results not published.');
    }
    
    // Get results for each gender category
    $html = '';
    
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
        
        // Generate HTML for this gender category
        $gender_icon = $gender_category === 'girls' ? 'female' : 'male';
        $gender_title = ucfirst($gender_category);
        
        $html .= "
        <div class='row mb-4'>
            <div class='col-12'>
                <h6><i class='fas fa-{$gender_icon} me-2'></i>{$gender_title} Representative Results</h6>";
        
        if (empty($candidates)) {
            $html .= "
                <div class='alert alert-info'>
                    <i class='fas fa-info-circle me-2'></i>
                    No approved candidates found for {$gender_category} representative.
                </div>";
        } else {
            $html .= "
                <div class='table-responsive'>
                    <table class='table table-sm table-hover'>
                        <thead>
                            <tr>
                                <th>Rank</th>
                                <th>Candidate</th>
                                <th>Roll Number</th>
                                <th>Votes</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>";
            
            $rank = 1;
            $prev_votes = null;
            $actual_rank = 1;
            
            foreach ($candidates as $candidate) {
                if ($prev_votes !== null && $candidate['vote_count'] < $prev_votes) {
                    $actual_rank = $rank;
                }
                
                $row_class = $candidate['is_winner'] ? 'table-success' : '';
                $rank_badge = $actual_rank === 1 ? 'bg-warning' : 'bg-secondary';
                
                $html .= "
                    <tr class='{$row_class}'>
                        <td>
                            <span class='badge {$rank_badge}'>{$actual_rank}</span>
                        </td>
                        <td>
                            <strong>" . htmlspecialchars($candidate['fname'] . ' ' . $candidate['lname']) . "</strong>";
                
                if ($candidate['is_winner']) {
                    $html .= "
                            <span class='badge bg-success ms-2'>
                                <i class='fas fa-crown me-1'></i>Winner
                            </span>";
                }
                
                $html .= "
                        </td>
                        <td>" . htmlspecialchars($candidate['roll_number']) . "</td>
                        <td>
                            <span class='badge bg-primary'>{$candidate['vote_count']}</span>
                        </td>
                        <td>";
                
                if ($candidate['is_winner']) {
                    $html .= "
                            <span class='text-success'>
                                <i class='fas fa-trophy me-1'></i>Elected
                            </span>";
                } else {
                    $html .= "
                            <span class='text-muted'>Not Elected</span>";
                }
                
                $html .= "
                        </td>
                    </tr>";
                
                $prev_votes = $candidate['vote_count'];
                $rank++;
            }
            
            if ($nil_votes > 0) {
                $html .= "
                    <tr class='table-light'>
                        <td>-</td>
                        <td><em>NIL Votes</em></td>
                        <td>-</td>
                        <td>
                            <span class='badge bg-secondary'>{$nil_votes}</span>
                        </td>
                        <td><em>No Candidate</em></td>
                    </tr>";
            }
            
            $html .= "
                        </tbody>
                    </table>
                </div>
                <small class='text-muted'>
                    Total votes for {$gender_category} representative: <strong>{$total_votes}</strong>
                </small>";
        }
        
        $html .= "
            </div>
        </div>";
    }
    
    $response['success'] = true;
    $response['html'] = $html;
    
} catch (Exception $e) {
    $response['message'] = $e->getMessage();
}

echo json_encode($response);
