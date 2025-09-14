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
        case 'toggle_publish':
            if (!isset($_POST['election_id']) || !isset($_POST['publish_action'])) {
                throw new Exception('Missing required parameters.');
            }
            
            $election_id = (int)$_POST['election_id'];
            $publish_action = $_POST['publish_action']; // 'publish' or 'unpublish'
            
            // Verify invigilator has access to this election
            $stmt = $pdo->prepare("
                SELECT e.*, c.class_name, d.department_name
                FROM elections e
                JOIN classes c ON e.class_id = c.class_id
                JOIN departments d ON c.department_id = d.department_id
                JOIN invigilator_class_assignments ica ON e.class_id = ica.class_id 
                    AND e.election_year = ica.election_year
                WHERE e.election_id = ? AND ica.invigilator_id = ?
            ");
            $stmt->execute([$election_id, $current_user['user_id']]);
            $election = $stmt->fetch();
            
            if (!$election) {
                throw new Exception('Election not found or you do not have permission to manage this election.');
            }
            
            // Check if voting has ended - results should only be publishable when voting_status is 'ended'
            if ($election['voting_status'] !== 'ended') {
                throw new Exception('Results can only be published after voting has ended.');
            }
            
            // Check if results_published column exists
            $stmt = $pdo->query("SHOW COLUMNS FROM elections LIKE 'results_published'");
            $column_exists = $stmt->fetch();
            
            if (!$column_exists) {
                throw new Exception('Results publication feature not yet enabled. Please contact the administrator to add the database column.');
            }
            
            // Update publication status
            $new_status = ($publish_action === 'publish') ? 1 : 0;
            $stmt = $pdo->prepare("UPDATE elections SET results_published = ? WHERE election_id = ?");
            $stmt->execute([$new_status, $election_id]);
            
            $action_text = ($publish_action === 'publish') ? 'published' : 'unpublished';
            $response['success'] = true;
            $response['message'] = "Results for {$election['class_name']} have been {$action_text}.";
            break;
            
        default:
            throw new Exception('Invalid action.');
    }
    
} catch (Exception $e) {
    $response['message'] = $e->getMessage();
}

echo json_encode($response);
?>
