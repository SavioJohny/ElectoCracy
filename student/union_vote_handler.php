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

    if ($action === 'cast_union_vote') {
        $election_id = (int)($_POST['election_id'] ?? 0);
        $position_id = (int)($_POST['position_id'] ?? 0);
        $vote_choice = $_POST['vote_choice'] ?? '';

        if (!$election_id || !$position_id || !$vote_choice) {
            throw new Exception('Missing required parameters.');
        }

        // Validate union election exists and is active
        $stmt = $pdo->prepare("
            SELECT e.*, et.election_type_name
            FROM elections e
            JOIN election_types et ON e.election_type_id = et.election_type_id
            WHERE e.election_id = ? AND et.election_type_name = 'union' AND e.is_active = 1
        ");
        $stmt->execute([$election_id]);
        $election = $stmt->fetch();

        if (!$election) {
            throw new Exception('Union election not found or not active.');
        }

        // Check election voting status
        if ($election['voting_status'] !== 'active') {
            throw new Exception('Union election voting is not currently active.');
        }

        // Validate position exists and is active for voting
        $stmt = $pdo->prepare("
            SELECT position_name FROM positions
            WHERE position_id = ? AND election_type_id = 2 AND is_active = 1
        ");
        $stmt->execute([$position_id]);
        $position = $stmt->fetch();

        if (!$position) {
            throw new Exception('Position not found or voting is not active for this position.');
        }

        // Check if student has already voted for this position
        $stmt = $pdo->prepare("
            SELECT vote_id FROM votes
            WHERE voter_id = ? AND election_id = ? AND position_id = ?
        ");
        $stmt->execute([$user_id, $election_id, $position_id]);
        
        if ($stmt->fetch()) {
            throw new Exception('You have already voted for this position.');
        }

        // Validate vote choice
        $candidate_id = null;
        if ($vote_choice === 'nil') {
            $candidate_id = null; // Nil vote
        } elseif (strpos($vote_choice, 'candidate_') === 0) {
            $candidate_id = (int)str_replace('candidate_', '', $vote_choice);
            
            // Validate candidate exists and is approved for this position
            $stmt = $pdo->prepare("
                SELECT candidate_id FROM candidates
                WHERE candidate_id = ? AND election_id = ? AND position_id = ? AND is_approved = 'approved'
            ");
            $stmt->execute([$candidate_id, $election_id, $position_id]);
            
            if (!$stmt->fetch()) {
                throw new Exception('Invalid candidate selected.');
            }
        } else {
            throw new Exception('Invalid vote choice.');
        }

        // Cast the vote (union elections don't use gender_category, so set it as NULL)
        $stmt = $pdo->prepare("
            INSERT INTO votes (voter_id, election_id, position_id, candidate_id, vote_type, gender_category)
            VALUES (?, ?, ?, ?, ?, NULL)
        ");
        $vote_type = $candidate_id ? 'valid' : NULL;
        $stmt->execute([$user_id, $election_id, $position_id, $candidate_id, $vote_type]);

        if ($candidate_id) {
            $response['message'] = "Your vote for {$position['position_name']} has been cast successfully.";
        } else {
            $response['message'] = "Your nil vote for {$position['position_name']} has been recorded.";
        }

        // Auto-end logic removed - elections must be manually ended by commissioners

        $response['success'] = true;
    } else {
        throw new Exception('Invalid action.');
    }

} catch (Exception $e) {
    $response['message'] = $e->getMessage();
}

// Auto-end function removed - union elections must be manually ended by commissioners

echo json_encode($response);
