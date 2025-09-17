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
    $student_id = (int)($_POST['student_id'] ?? 0);
    $election_id = (int)($_POST['election_id'] ?? 0);
    
    if (!$student_id || !$election_id) {
        throw new Exception('Student ID and Election ID are required.');
    }
    
    if (!in_array($action, ['disqualify', 'requalify'])) {
        throw new Exception('Invalid action.');
    }
    
    // Verify the class election exists and get details
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
    
    // Check if election allows modifications
    if ($election['voting_status'] !== 'not_started') {
        throw new Exception('Disqualifications can only be modified when the election status is "Not Started".');
    }
    
    // Verify student exists and belongs to the class
    $stmt = $pdo->prepare("
        SELECT u.*, r.role_name
        FROM users u
        JOIN roles r ON u.role_id = r.role_id
        WHERE u.user_id = ? AND u.class_id = ? AND r.role_name = 'Student'
    ");
    $stmt->execute([$student_id, $election['class_id']]);
    $student = $stmt->fetch();
    
    if (!$student) {
        throw new Exception('Student not found in this class.');
    }
    
    if ($action === 'disqualify') {
        // Check if student is already disqualified
        $stmt = $pdo->prepare("
            SELECT COUNT(*) FROM election_disqualifications 
            WHERE student_id = ? AND election_id = ?
        ");
        $stmt->execute([$student_id, $election_id]);
        
        if ($stmt->fetchColumn() > 0) {
            throw new Exception('Student is already disqualified from this election.');
        }
        
        // Add disqualification
        $stmt = $pdo->prepare("
            INSERT INTO election_disqualifications (student_id, election_id, disqualified_by, disqualified_at)
            VALUES (?, ?, ?, NOW())
        ");
        $stmt->execute([$student_id, $election_id, $current_user['user_id']]);
        
        $response['message'] = "Student {$student['fname']} {$student['lname']} has been disqualified from the class election.";
        
        // Log the action
        error_log("Commissioner {$current_user['fname']} {$current_user['lname']} disqualified student {$student['fname']} {$student['lname']} (ID: {$student_id}) from class election {$election['class_name']} (ID: {$election_id})");
        
    } elseif ($action === 'requalify') {
        // Check if student is disqualified
        $stmt = $pdo->prepare("
            SELECT COUNT(*) FROM election_disqualifications 
            WHERE student_id = ? AND election_id = ?
        ");
        $stmt->execute([$student_id, $election_id]);
        
        if ($stmt->fetchColumn() == 0) {
            throw new Exception('Student is not disqualified from this election.');
        }
        
        // Remove disqualification
        $stmt = $pdo->prepare("
            DELETE FROM election_disqualifications 
            WHERE student_id = ? AND election_id = ?
        ");
        $stmt->execute([$student_id, $election_id]);
        
        $response['message'] = "Student {$student['fname']} {$student['lname']} has been requalified for the class election.";
        
        // Log the action
        error_log("Commissioner {$current_user['fname']} {$current_user['lname']} requalified student {$student['fname']} {$student['lname']} (ID: {$student_id}) for class election {$election['class_name']} (ID: {$election_id})");
    }
    
    $response['success'] = true;
    
} catch (Exception $e) {
    $response['message'] = $e->getMessage();
}

echo json_encode($response);
?>
