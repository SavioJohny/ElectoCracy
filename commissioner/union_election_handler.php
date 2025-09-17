<?php
session_start();
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/config/database.php';

requireRole('Election Commissioner');

header('Content-Type: application/json');

$response = ['success' => false, 'message' => ''];

try {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'create_union_election':
            $election_year = (int)($_POST['election_year'] ?? 0);
            
            // Validate required fields
            if (!$election_year) {
                throw new Exception('Election year is required.');
            }
            
            // Validate election year
            $current_year = (int)date('Y');
            if ($election_year < $current_year || $election_year > $current_year + 1) {
                throw new Exception('Invalid election year.');
            }
            
            // Check if union election already exists for this year
            $stmt = $pdo->prepare("
                SELECT election_id 
                FROM elections 
                WHERE election_type_id = 2 AND election_year = ?
            ");
            $stmt->execute([$election_year]);
            
            if ($stmt->fetch()) {
                throw new Exception("Union election already exists for {$election_year}.");
            }
            
            // Check if there are any class elections for this year
            // Allow union election creation if class elections exist (they don't need to be completed yet)
            $stmt = $pdo->prepare("
                SELECT COUNT(DISTINCT e.election_id) as class_elections
                FROM elections e
                WHERE e.election_year = ? AND e.election_type_id = 1
            ");
            $stmt->execute([$election_year]);
            $result = $stmt->fetch();

            if ($result['class_elections'] == 0) {
                throw new Exception("No class elections found for {$election_year}. Create class elections first.");
            }
            
            // Create the union election
            $stmt = $pdo->prepare("
                INSERT INTO elections (election_type_id, election_year, class_id, is_active, voting_status) 
                VALUES (2, ?, NULL, 1, 'not_started')
            ");
            $stmt->execute([$election_year]);
            
            $response['success'] = true;
            $response['message'] = "Union election created successfully for {$election_year}.";
            break;
            
        case 'control_voting':
            $election_id = (int)($_POST['election_id'] ?? 0);
            $voting_action = $_POST['voting_action'] ?? '';
            
            if (!$election_id || !in_array($voting_action, ['start', 'pause', 'end'])) {
                throw new Exception('Invalid parameters.');
            }
            
            // Validate election exists and is union election
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
            
            // Determine new voting status
            $new_status = match($voting_action) {
                'start' => 'active',
                'pause' => 'paused',
                'end' => 'ended',
                default => throw new Exception('Invalid voting action.')
            };
            
            // Update voting status
            $stmt = $pdo->prepare("UPDATE elections SET voting_status = ? WHERE election_id = ?");
            $stmt->execute([$new_status, $election_id]);
            
            $response['success'] = true;
            $response['message'] = "Voting has been " . ($voting_action == 'start' ? 'started' : $voting_action . 'ed') . " for the union election.";
            break;
            

        case 'remove_candidate':
            $candidate_id = (int)($_POST['candidate_id'] ?? 0);

            if (!$candidate_id) {
                throw new Exception('Candidate ID is required.');
            }

            // Get candidate details before deletion
            $stmt = $pdo->prepare("
                SELECT c.*, u.fname, u.lname, p.position_name
                FROM candidates c
                JOIN users u ON c.user_id = u.user_id
                JOIN positions p ON c.position_id = p.position_id
                WHERE c.candidate_id = ?
            ");
            $stmt->execute([$candidate_id]);
            $candidate = $stmt->fetch();

            if (!$candidate) {
                throw new Exception('Candidate not found.');
            }

            // Check if position is active (cannot remove candidates from active positions)
            $stmt = $pdo->prepare("SELECT is_active FROM positions WHERE position_id = ?");
            $stmt->execute([$candidate['position_id']]);
            $position_active = $stmt->fetchColumn();

            if ($position_active) {
                throw new Exception('Cannot remove candidate: Position is currently active for voting.');
            }

            // Delete the candidate application
            $stmt = $pdo->prepare("DELETE FROM candidates WHERE candidate_id = ?");
            $stmt->execute([$candidate_id]);

            $response['success'] = true;
            $response['message'] = "Successfully removed {$candidate['fname']} {$candidate['lname']}'s application for {$candidate['position_name']}.";
            break;

        case 'manage_candidate':
            $candidate_id = (int)($_POST['candidate_id'] ?? 0);
            $approval_action = $_POST['approval_action'] ?? '';
            
            if (!$candidate_id || !in_array($approval_action, ['approve', 'reject', 'reset'])) {
                throw new Exception('Invalid parameters.');
            }
            
            // Validate candidate exists and is for union election
            $stmt = $pdo->prepare("
                SELECT cand.*, u.fname, u.lname, p.position_name, e.election_type_id
                FROM candidates cand
                JOIN users u ON cand.user_id = u.user_id
                JOIN positions p ON cand.position_id = p.position_id
                JOIN elections e ON cand.election_id = e.election_id
                WHERE cand.candidate_id = ? AND e.election_type_id = 2
            ");
            $stmt->execute([$candidate_id]);
            $candidate = $stmt->fetch();
            
            if (!$candidate) {
                throw new Exception('Union candidate not found.');
            }
            
            // Determine new approval status
            $new_status = match($approval_action) {
                'approve' => 'approved',
                'reject' => 'rejected',
                'reset' => 'pending',
                default => throw new Exception('Invalid approval action.')
            };
            
            // Update candidate approval status
            $stmt = $pdo->prepare("UPDATE candidates SET is_approved = ? WHERE candidate_id = ?");
            $stmt->execute([$new_status, $candidate_id]);
            
            $candidate_name = $candidate['fname'] . ' ' . $candidate['lname'];
            $position_name = $candidate['position_name'];
            
            $response['success'] = true;
            $response['message'] = "Candidate {$candidate_name} for {$position_name} has been {$new_status}.";
            break;
            
        case 'toggle_position':
            $position_id = (int)($_POST['position_id'] ?? 0);
            $new_status = (int)($_POST['status'] ?? 0);
            
            if (!$position_id) {
                throw new Exception('Position ID is required.');
            }
            
            // Validate position exists and is union position
            $stmt = $pdo->prepare("
                SELECT p.*, et.election_type_name
                FROM positions p
                JOIN election_types et ON p.election_type_id = et.election_type_id
                WHERE p.position_id = ? AND et.election_type_name = 'union'
            ");
            $stmt->execute([$position_id]);
            $position = $stmt->fetch();
            
            if (!$position) {
                throw new Exception('Union position not found.');
            }
            
            // Update position status
            $stmt = $pdo->prepare("UPDATE positions SET is_active = ? WHERE position_id = ?");
            $stmt->execute([$new_status, $position_id]);
            
            $action_text = $new_status ? 'activated' : 'deactivated';
            
            $response['success'] = true;
            $response['message'] = "Position '{$position['position_name']}' has been {$action_text}.";
            break;

        case 'update_union_election':
            $election_id = (int)($_POST['election_id'] ?? 0);
            $election_year = (int)($_POST['election_year'] ?? 0);
            $is_active = (int)($_POST['is_active'] ?? 1);
            $voting_status = $_POST['voting_status'] ?? 'not_started';

            if (!$election_id || !$election_year) {
                throw new Exception('Election ID and year are required.');
            }

            // Validate election year
            $current_year = (int)date('Y');
            if ($election_year < $current_year || $election_year > $current_year + 1) {
                throw new Exception('Invalid election year.');
            }

            // Validate voting status
            if (!in_array($voting_status, ['not_started', 'active', 'paused', 'ended'])) {
                throw new Exception('Invalid voting status.');
            }

            // Validate election exists and is union election
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

            // Check if changing year would conflict with existing election
            if ($election_year != $election['election_year']) {
                $stmt = $pdo->prepare("
                    SELECT election_id
                    FROM elections
                    WHERE election_type_id = 2 AND election_year = ? AND election_id != ?
                ");
                $stmt->execute([$election_year, $election_id]);

                if ($stmt->fetch()) {
                    throw new Exception("Another union election already exists for {$election_year}.");
                }
            }

            // Update the election
            $stmt = $pdo->prepare("
                UPDATE elections
                SET election_year = ?, is_active = ?, voting_status = ?
                WHERE election_id = ?
            ");
            $stmt->execute([$election_year, $is_active, $voting_status, $election_id]);

            $response['success'] = true;
            $response['message'] = "Union election updated successfully.";
            break;

        case 'delete_union_election':
            $election_id = (int)($_POST['election_id'] ?? 0);

            if (!$election_id) {
                throw new Exception('Election ID is required.');
            }

            // Validate election exists and is union election
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

            // Get deletion statistics before deleting
            $stmt = $pdo->prepare("
                SELECT
                    COUNT(DISTINCT cand.candidate_id) as candidate_count,
                    COUNT(DISTINCT v.vote_id) as vote_count
                FROM elections e
                LEFT JOIN candidates cand ON e.election_id = cand.election_id
                LEFT JOIN votes v ON e.election_id = v.election_id
                WHERE e.election_id = ?
            ");
            $stmt->execute([$election_id]);
            $stats = $stmt->fetch();

            // Start transaction for safe deletion
            $pdo->beginTransaction();

            try {
                // Delete in proper order to respect foreign key constraints

                // 1. Delete votes
                $stmt = $pdo->prepare("DELETE FROM votes WHERE election_id = ?");
                $stmt->execute([$election_id]);
                $deleted_votes = $stmt->rowCount();

                // 2. Delete candidates
                $stmt = $pdo->prepare("DELETE FROM candidates WHERE election_id = ?");
                $stmt->execute([$election_id]);
                $deleted_candidates = $stmt->rowCount();

                // 3. Delete the election itself
                $stmt = $pdo->prepare("DELETE FROM elections WHERE election_id = ?");
                $stmt->execute([$election_id]);

                // Commit the transaction
                $pdo->commit();

                $response['success'] = true;
                $response['message'] = "Union election for {$election['election_year']} deleted successfully. Removed {$deleted_candidates} candidates and {$deleted_votes} votes.";

            } catch (Exception $e) {
                // Rollback transaction on error
                $pdo->rollBack();
                throw new Exception('Failed to delete union election: ' . $e->getMessage());
            }
            break;

        case 'bulk_update_positions':
            $position_ids_json = $_POST['position_ids'] ?? '';
            $new_status = (int)($_POST['status'] ?? 1);

            if (empty($position_ids_json)) {
                throw new Exception('No positions selected.');
            }

            $position_ids = json_decode($position_ids_json, true);

            if (!is_array($position_ids) || empty($position_ids)) {
                throw new Exception('Invalid position IDs provided.');
            }

            // Validate all position IDs are integers and belong to union positions
            $position_ids = array_map('intval', $position_ids);
            $position_ids = array_filter($position_ids, function($id) { return $id > 0; });

            if (empty($position_ids)) {
                throw new Exception('No valid position IDs provided.');
            }

            // Validate positions exist and are union positions
            $placeholders = str_repeat('?,', count($position_ids) - 1) . '?';
            $stmt = $pdo->prepare("
                SELECT p.position_id, p.position_name, et.election_type_name
                FROM positions p
                JOIN election_types et ON p.election_type_id = et.election_type_id
                WHERE p.position_id IN ($placeholders) AND et.election_type_name = 'union'
            ");
            $stmt->execute($position_ids);
            $positions = $stmt->fetchAll();

            if (count($positions) !== count($position_ids)) {
                throw new Exception('Some positions were not found or are not union positions.');
            }

            // Update all positions
            $stmt = $pdo->prepare("
                UPDATE positions
                SET is_active = ?
                WHERE position_id IN ($placeholders) AND election_type_id = 2
            ");
            $params = array_merge([$new_status], $position_ids);
            $stmt->execute($params);

            $updated_count = $stmt->rowCount();
            $action_text = $new_status ? 'activated' : 'deactivated';

            $response['success'] = true;
            $response['message'] = "Successfully {$action_text} {$updated_count} union positions.";
            break;

        case 'toggle_position_status':
            $position_id = (int)($_POST['position_id'] ?? 0);
            $status_action = $_POST['status_action'] ?? '';

            if (!$position_id || !in_array($status_action, ['activate', 'deactivate'])) {
                throw new Exception('Invalid parameters.');
            }

            // Validate position exists and is union position
            $stmt = $pdo->prepare("
                SELECT p.position_name, p.is_active, et.election_type_name
                FROM positions p
                JOIN election_types et ON p.election_type_id = et.election_type_id
                WHERE p.position_id = ? AND et.election_type_name = 'union'
            ");
            $stmt->execute([$position_id]);
            $position = $stmt->fetch();

            if (!$position) {
                throw new Exception('Position not found or not a union position.');
            }

            // Check current status
            $current_status = $position['is_active'];
            $new_status = ($status_action === 'activate') ? 1 : 0;

            if ($current_status == $new_status) {
                $status_text = $new_status ? 'active' : 'inactive';
                throw new Exception("Position is already {$status_text}.");
            }

            // If activating, check election status and approved candidates
            if ($status_action === 'activate') {
                $current_year = date('Y');

                // Check if union election is active
                $stmt = $pdo->prepare("
                    SELECT voting_status
                    FROM elections e
                    WHERE e.election_year = ? AND e.election_type_id = 2
                    LIMIT 1
                ");
                $stmt->execute([$current_year]);
                $election_status = $stmt->fetchColumn();

                if ($election_status !== 'active') {
                    throw new Exception('Cannot activate position: Union election must be active first.');
                }

                // Check if there are approved candidates
                $stmt = $pdo->prepare("
                    SELECT COUNT(*) as approved_count
                    FROM candidates c
                    JOIN elections e ON c.election_id = e.election_id
                    WHERE c.position_id = ? AND c.is_approved = 'approved'
                    AND e.election_year = ? AND e.election_type_id = 2
                ");
                $stmt->execute([$position_id, $current_year]);
                $approved_count = $stmt->fetchColumn();

                if ($approved_count == 0) {
                    throw new Exception('Cannot activate position: No approved candidates for this position.');
                }
            }

            // Update position status
            $stmt = $pdo->prepare("
                UPDATE positions
                SET is_active = ?
                WHERE position_id = ?
            ");
            $stmt->execute([$new_status, $position_id]);

            $action_text = $status_action === 'activate' ? 'activated' : 'deactivated';

            $response['success'] = true;
            $response['message'] = "Position '{$position['position_name']}' has been {$action_text}.";

            // Auto-end logic removed - elections must be manually ended by commissioners
            break;

        case 'control_election_voting':
            $election_id = (int)($_POST['election_id'] ?? 0);
            $voting_action = $_POST['voting_action'] ?? '';

            if (!$election_id || !in_array($voting_action, ['start', 'pause', 'resume', 'end'])) {
                throw new Exception('Invalid parameters.');
            }

            // Validate election exists and is union election
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

            // Validate action based on current status
            $current_status = $election['voting_status'];
            $valid_transitions = [
                'not_started' => ['start'],
                'active' => ['pause', 'end'],
                'paused' => ['resume', 'end'],
                'ended' => []
            ];

            if (!in_array($voting_action, $valid_transitions[$current_status])) {
                throw new Exception("Cannot {$voting_action} election: Election is currently {$current_status}.");
            }

            // Determine new status
            $new_status = match($voting_action) {
                'start', 'resume' => 'active',
                'pause' => 'paused',
                'end' => 'ended',
                default => throw new Exception('Invalid voting action.')
            };

            // If starting election, check if there are any approved candidates
            if ($voting_action == 'start') {
                $stmt = $pdo->prepare("
                    SELECT COUNT(*) as approved_count
                    FROM candidates
                    WHERE election_id = ? AND is_approved = 'approved'
                ");
                $stmt->execute([$election_id]);
                $approved_count = $stmt->fetchColumn();

                if ($approved_count == 0) {
                    throw new Exception('Cannot start election: No approved candidates found.');
                }
            }

            // Update election status
            $stmt = $pdo->prepare("
                UPDATE elections
                SET voting_status = ?
                WHERE election_id = ?
            ");
            $stmt->execute([$new_status, $election_id]);

            // If pausing or ending election, deactivate all positions
            if (in_array($voting_action, ['pause', 'end'])) {
                $stmt = $pdo->prepare("
                    UPDATE positions
                    SET is_active = 0
                    WHERE election_type_id = 2
                ");
                $stmt->execute();
            }

            $action_text = match($voting_action) {
                'start' => 'started',
                'pause' => 'paused',
                'resume' => 'resumed',
                'end' => 'ended',
                default => $voting_action
            };

            $response['success'] = true;
            $response['message'] = "Union election for {$election['election_year']} has been {$action_text}.";
            break;

        case 'toggle_results_publication':
            $publish = (bool)($_POST['publish'] ?? false);

            // Get current union election
            $stmt = $pdo->prepare("
                SELECT e.*, et.election_type_name
                FROM elections e
                JOIN election_types et ON e.election_type_id = et.election_type_id
                WHERE e.election_year = ? AND et.election_type_name = 'union'
            ");
            $stmt->execute([date('Y')]);
            $election = $stmt->fetch();

            if (!$election) {
                throw new Exception('Union election not found.');
            }

            // Only allow results publication if election has ended
            if ($election['voting_status'] !== 'ended') {
                throw new Exception('Results can only be published after the election has ended.');
            }

            // Update results publication status
            $stmt = $pdo->prepare("
                UPDATE elections
                SET results_published = ?
                WHERE election_id = ?
            ");
            $stmt->execute([$publish ? 1 : 0, $election['election_id']]);

            $action_text = $publish ? 'published' : 'hidden';
            $response['success'] = true;
            $response['message'] = "Union election results have been {$action_text}.";
            break;

        default:
            throw new Exception('Invalid action.');
    }
    
} catch (Exception $e) {
    $response['message'] = $e->getMessage();
}

// Auto-end function removed - union elections must be manually ended by commissioners

echo json_encode($response);
?>
