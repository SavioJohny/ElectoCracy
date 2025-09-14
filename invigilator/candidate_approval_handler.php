<?php
session_start();
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/config/database.php';

requireRole('Invigilator');

header('Content-Type: application/json');

$response = ['success' => false, 'message' => ''];

try {
    $action = $_POST['action'] ?? '';
    $user_id = $_SESSION['user_id'];
    
    switch ($action) {
        case 'update_candidate_status':
            $candidate_id = (int)($_POST['candidate_id'] ?? 0);
            $status = $_POST['status'] ?? '';
            $reason = trim($_POST['reason'] ?? '');

            if (!$candidate_id) {
                throw new Exception('Invalid candidate ID.');
            }

            if (!in_array($status, ['pending', 'approved', 'rejected'])) {
                throw new Exception('Invalid status value.');
            }
            
            // Get candidate details and verify invigilator has permission
            $stmt = $pdo->prepare("
                SELECT c.*, u.fname, u.lname, u.roll_number,
                       e.election_id, e.class_id, cl.class_name, d.department_name,
                       p.position_name
                FROM candidates c
                JOIN users u ON c.user_id = u.user_id
                JOIN elections e ON c.election_id = e.election_id
                JOIN classes cl ON e.class_id = cl.class_id
                JOIN departments d ON cl.department_id = d.department_id
                JOIN positions p ON c.position_id = p.position_id
                WHERE c.candidate_id = ?
            ");
            $stmt->execute([$candidate_id]);
            $candidate = $stmt->fetch();
            
            if (!$candidate) {
                throw new Exception('Candidate not found.');
            }
            
            // Verify invigilator is assigned to this class
            $stmt = $pdo->prepare("
                SELECT assignment_id 
                FROM invigilator_class_assignments 
                WHERE invigilator_id = ? AND class_id = ? AND election_year = YEAR(NOW())
            ");
            $stmt->execute([$user_id, $candidate['class_id']]);
            
            if (!$stmt->fetch()) {
                throw new Exception('You are not authorized to approve candidates for this class.');
            }
            
            // Allow status changes (no restriction on current status)
            
            // Update candidate status
            $stmt = $pdo->prepare("
                UPDATE candidates
                SET is_approved = ?
                WHERE candidate_id = ?
            ");
            $stmt->execute([$status, $candidate_id]);
            
            $student_name = $candidate['fname'] . ' ' . $candidate['lname'];

            $response['success'] = true;
            $response['message'] = "Candidate {$student_name} status has been updated to '{$status}' for {$candidate['position_name']}.";
            break;
            
        default:
            throw new Exception('Invalid action.');
    }
    
} catch (Exception $e) {
    $response['message'] = $e->getMessage();
}

echo json_encode($response);
