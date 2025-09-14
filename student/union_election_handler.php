<?php
session_start();
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/config/database.php';

requireRole('Student');

header('Content-Type: application/json');

$response = ['success' => false, 'message' => ''];

try {
    $action = $_POST['action'] ?? '';
    $user_id = $_SESSION['user_id'];
    $current_year = date('Y');
    
    switch ($action) {
        case 'apply_position':
            $position_id = (int)($_POST['position_id'] ?? 0);
            
            if (!$position_id) {
                throw new Exception('Position ID is required.');
            }
            
            // Check if student is eligible (either won or is an approved candidate)
            // Simplified approach - just check if student is an approved candidate
            $stmt = $pdo->prepare("
                SELECT
                    cand.candidate_id,
                    e.election_id,
                    'Class Representative' as position_name,
                    e.election_year,
                    NULL as gender_category,
                    0 as vote_count,
                    'potential' as status
                FROM elections e
                JOIN candidates cand ON e.election_id = cand.election_id
                WHERE cand.user_id = ?
                AND e.election_type_id = 1
                AND e.election_year = ?
                AND cand.is_approved = 'approved'
                LIMIT 1
            ");
            $stmt->execute([$user_id, $current_year]);
            $class_win = $stmt->fetch();

            // If no actual win, check if student is an approved candidate
            if (!$class_win) {
                $stmt = $pdo->prepare("
                    SELECT
                        cand.candidate_id,
                        e.election_id,
                        'Class Representative' as position_name,
                        e.election_year,
                        NULL as gender_category,
                        0 as vote_count,
                        'potential' as status
                    FROM elections e
                    JOIN candidates cand ON e.election_id = cand.election_id
                    WHERE cand.user_id = ?
                    AND e.election_type_id = 1
                    AND e.election_year = ?
                    AND cand.is_approved = 'approved'
                    LIMIT 1
                ");
                $stmt->execute([$user_id, $current_year]);
                $class_win = $stmt->fetch();
            }

            if (!$class_win) {
                throw new Exception('You are not eligible for union elections. You must be an approved candidate in class elections.');
            }
            
            // Check if union election exists and is accepting applications
            $stmt = $pdo->prepare("
                SELECT e.*
                FROM elections e
                JOIN election_types et ON e.election_type_id = et.election_type_id
                WHERE e.election_year = ? AND et.election_type_name = 'union'
            ");
            $stmt->execute([$current_year]);
            $union_election = $stmt->fetch();
            
            if (!$union_election) {
                throw new Exception('No union election found for this year.');
            }
            
            // Check if applications are allowed based on election status
            if ($union_election['voting_status'] == 'ended') {
                throw new Exception('Union election has ended. Applications are no longer accepted.');
            }
            
            // Validate position exists and is active
            $stmt = $pdo->prepare("
                SELECT p.*, et.election_type_name
                FROM positions p
                JOIN election_types et ON p.election_type_id = et.election_type_id
                WHERE p.position_id = ? AND et.election_type_name = 'union'
            ");
            $stmt->execute([$position_id]);
            $position = $stmt->fetch();
            
            if (!$position) {
                throw new Exception('Position not found.');
            }

            // Check if position is active (students cannot apply for active positions)
            if ($position['is_active']) {
                throw new Exception('Cannot apply for this position: Voting is currently active for this position.');
            }
            
            // Check if student already applied for ANY position in this union election
            $stmt = $pdo->prepare("
                SELECT candidate_id, p.position_name
                FROM candidates c
                JOIN positions p ON c.position_id = p.position_id
                WHERE c.user_id = ? AND c.election_id = ?
            ");
            $stmt->execute([$user_id, $union_election['election_id']]);
            $existing_application = $stmt->fetch();
            
            if ($existing_application) {
                throw new Exception('You have already applied for the position of ' . $existing_application['position_name'] . '. Students can only apply for one union position.');
            }
            
            // Create candidate application
            $stmt = $pdo->prepare("
                INSERT INTO candidates (user_id, election_id, position_id, is_approved)
                VALUES (?, ?, ?, 'pending')
            ");
            $stmt->execute([$user_id, $union_election['election_id'], $position_id]);
            
            $response['success'] = true;
            $response['message'] = "Successfully applied for {$position['position_name']}. Your application is pending approval.";
            break;
            
        default:
            throw new Exception('Invalid action.');
    }
    
} catch (Exception $e) {
    $response['message'] = $e->getMessage();
}

echo json_encode($response);
?>
