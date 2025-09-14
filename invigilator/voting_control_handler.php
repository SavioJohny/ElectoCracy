<?php
session_start();
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/config/database.php';

requireRole('Invigilator');

header('Content-Type: application/json');

$response = ['success' => false, 'message' => ''];

try {
    $current_user = getCurrentUser();
    
    if (!isset($_POST['action'])) {
        throw new Exception('No action specified.');
    }
    
    switch ($_POST['action']) {
        case 'control_voting':
            if (!isset($_POST['election_id']) || !isset($_POST['voting_action'])) {
                throw new Exception('Missing required parameters.');
            }
            
            $election_id = (int)$_POST['election_id'];
            $voting_action = $_POST['voting_action']; // 'start', 'pause', 'end'
            
            // Validate voting action
            if (!in_array($voting_action, ['start', 'pause', 'end'])) {
                throw new Exception('Invalid voting action.');
            }
            
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
                AND et.election_type_name = 'class'
            ");
            $stmt->execute([$election_id, $current_user['user_id']]);
            $election = $stmt->fetch();
            
            if (!$election) {
                throw new Exception('Election not found or you do not have permission to manage this election.');
            }
            
            // Check if election is active
            if (!$election['is_active']) {
                throw new Exception('Cannot control voting for inactive elections.');
            }
            
            // Check if voting_status column exists
            $stmt = $pdo->query("SHOW COLUMNS FROM elections LIKE 'voting_status'");
            $column_exists = $stmt->fetch();
            
            if (!$column_exists) {
                throw new Exception('Voting control feature not yet enabled. Please contact the administrator to add the database column.');
            }
            
            // Get current voting status
            $stmt = $pdo->prepare("SELECT voting_status FROM elections WHERE election_id = ?");
            $stmt->execute([$election_id]);
            $current_status = $stmt->fetchColumn();
            
            // Validate state transitions
            $valid_transitions = [
                'not_started' => ['start'],
                'active' => ['pause', 'end'],
                'paused' => ['start', 'end'],
                'ended' => [] // Cannot transition from ended
            ];
            
            if (!in_array($voting_action, $valid_transitions[$current_status])) {
                $status_text = ucfirst(str_replace('_', ' ', $current_status));
                throw new Exception("Cannot {$voting_action} voting when status is '{$status_text}'.");
            }
            
            // Determine new status
            $new_status_map = [
                'start' => 'active',
                'pause' => 'paused',
                'end' => 'ended'
            ];
            $new_status = $new_status_map[$voting_action];
            
            // Update voting status
            $stmt = $pdo->prepare("UPDATE elections SET voting_status = ? WHERE election_id = ?");
            $stmt->execute([$new_status, $election_id]);
            
            // Generate response message
            $action_messages = [
                'start' => 'started',
                'pause' => 'paused',
                'end' => 'ended'
            ];
            $action_text = $action_messages[$voting_action];
            
            $response['success'] = true;
            $response['message'] = "Voting for {$election['class_name']} has been {$action_text}.";
            
            // Log the action (optional - for audit trail)
            $log_message = "Invigilator {$current_user['fname']} {$current_user['lname']} {$action_text} voting for election {$election_id} ({$election['class_name']})";
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
