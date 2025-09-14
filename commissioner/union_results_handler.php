<?php
session_start();
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/config/database.php';

requireRole('Election Commissioner');

header('Content-Type: application/json');

$response = ['success' => false, 'message' => ''];
$current_user = getCurrentUser();

try {
    $action = $_POST['action'] ?? '';
    $election_id = (int)($_POST['election_id'] ?? 0);
    
    if (!$election_id) {
        throw new Exception('Election ID is required.');
    }
    
    // Verify the union election exists
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
    
    if ($action === 'toggle_results_publication') {
        $publish = (int)($_POST['publish'] ?? 0);
        
        // Check if election has ended
        if ($election['voting_status'] !== 'ended') {
            throw new Exception('Results can only be published/hidden for ended elections.');
        }
        
        // Update results publication status
        $stmt = $pdo->prepare("
            UPDATE elections
            SET results_published = ?
            WHERE election_id = ?
        ");
        $stmt->execute([$publish, $election_id]);
        
        $action_text = $publish ? 'published' : 'hidden';
        $response['message'] = "Union election results have been {$action_text} successfully.";
        
        // Log the action
        error_log("Commissioner {$current_user['fname']} {$current_user['lname']} {$action_text} union election results for election {$election_id}");
        
        $response['success'] = true;
    } else {
        throw new Exception('Invalid action.');
    }
    
} catch (Exception $e) {
    $response['message'] = $e->getMessage();
}

echo json_encode($response);
?>
