<?php
session_start();
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/config/database.php';

requireRole('Election Commissioner');

header('Content-Type: application/json');

$response = ['success' => false, 'message' => ''];

try {
    $current_user = getCurrentUser();
    
    if (!isset($_POST['action'])) {
        throw new Exception('No action specified.');
    }
    
    switch ($_POST['action']) {
        case 'control_election_voting':
            if (!isset($_POST['election_id']) || !isset($_POST['voting_action'])) {
                throw new Exception('Missing required parameters.');
            }
            
            $election_id = (int)$_POST['election_id'];
            $voting_action = $_POST['voting_action']; // 'start', 'pause', 'resume', 'end'
            
            // Validate voting action
            if (!in_array($voting_action, ['start', 'pause', 'resume', 'end'])) {
                throw new Exception('Invalid voting action.');
            }
            
            // Get union election
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
            
            // Check if election is active
            if (!$election['is_active']) {
                throw new Exception('Cannot control voting for inactive elections.');
            }
            
            // Get current voting status
            $current_status = $election['voting_status'];
            
            // Validate state transitions
            $valid_transitions = [
                'not_started' => ['start'],
                'active' => ['pause', 'end'],
                'paused' => ['resume', 'end'],
                'ended' => [] // Cannot transition from ended
            ];
            
            // Use the actual voting action for validation (no mapping needed)
            if (!in_array($voting_action, $valid_transitions[$current_status])) {
                $status_text = ucfirst(str_replace('_', ' ', $current_status));
                throw new Exception("Cannot {$voting_action} voting when status is '{$status_text}'.");
            }
            
            // Determine new status
            $new_status_map = [
                'start' => 'active',
                'pause' => 'paused',
                'resume' => 'active',
                'end' => 'ended'
            ];
            $new_status = $new_status_map[$voting_action];
            
            // Update voting status
            $stmt = $pdo->prepare("UPDATE elections SET voting_status = ? WHERE election_id = ?");
            $stmt->execute([$new_status, $election_id]);
            
            // If ending the election, deactivate all union positions to prevent further voting
            if ($voting_action === 'end') {
                $stmt = $pdo->prepare("UPDATE positions SET is_active = 0 WHERE election_type_id = 2");
                $stmt->execute();
            }
            
            // Generate response message
            $action_messages = [
                'start' => 'started',
                'pause' => 'paused',
                'resume' => 'resumed',
                'end' => 'ended'
            ];
            $action_text = $action_messages[$voting_action];
            
            $response['success'] = true;
            $response['message'] = "Union election voting has been {$action_text}.";
            
            // Log the action
            $log_message = "Commissioner {$current_user['fname']} {$current_user['lname']} {$action_text} union election voting (ID: {$election_id})";
            error_log($log_message);
            
            break;
            
        case 'toggle_position_status':
            if (!isset($_POST['position_id']) || !isset($_POST['status_action'])) {
                throw new Exception('Missing required parameters.');
            }
            
            $position_id = (int)$_POST['position_id'];
            $status_action = $_POST['status_action']; // 'activate' or 'deactivate'
            
            // Validate status action
            if (!in_array($status_action, ['activate', 'deactivate'])) {
                throw new Exception('Invalid status action.');
            }
            
            // Get position details
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
            
            // Get current union election
            $current_year = date('Y');
            $stmt = $pdo->prepare("
                SELECT e.*
                FROM elections e
                JOIN election_types et ON e.election_type_id = et.election_type_id
                WHERE e.election_year = ? AND et.election_type_name = 'union'
            ");
            $stmt->execute([$current_year]);
            $union_election = $stmt->fetch();
            
            if (!$union_election) {
                throw new Exception('No union election found for current year.');
            }
            
            // Check if we can activate positions (election must be active)
            if ($status_action === 'activate' && $union_election['voting_status'] !== 'active') {
                throw new Exception('Cannot activate positions when election is not active.');
            }
            
            // Check if position has approved candidates for activation
            if ($status_action === 'activate') {
                $stmt = $pdo->prepare("
                    SELECT COUNT(*) as approved_count
                    FROM candidates
                    WHERE election_id = ? AND position_id = ? AND is_approved = 'approved'
                ");
                $stmt->execute([$union_election['election_id'], $position_id]);
                $approved_count = $stmt->fetchColumn();
                
                if ($approved_count == 0) {
                    throw new Exception('Cannot activate position with no approved candidates.');
                }
            }
            
            // Update position status
            $new_status = $status_action === 'activate' ? 1 : 0;
            $stmt = $pdo->prepare("UPDATE positions SET is_active = ? WHERE position_id = ?");
            $stmt->execute([$new_status, $position_id]);
            
            $action_text = $status_action === 'activate' ? 'activated' : 'deactivated';
            $response['success'] = true;
            $response['message'] = "Position '{$position['position_name']}' has been {$action_text}.";
            
            // Log the action
            $log_message = "Commissioner {$current_user['fname']} {$current_user['lname']} {$action_text} position '{$position['position_name']}' (ID: {$position_id})";
            error_log($log_message);
            
            break;
            
        default:
            throw new Exception('Invalid action.');
    }
    
} catch (Exception $e) {
    $response['message'] = $e->getMessage();
}

echo json_encode($response);
?>
