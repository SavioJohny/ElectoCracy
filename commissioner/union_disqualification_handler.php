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
        throw new Exception('Student and election are required.');
    }
    
    // Verify the union election exists and get its details
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
    
    // Check if voting allows modifications (allow changes when not_started or active)
    $voting_status = $election['voting_status'] ?? 'not_started';
    if (!in_array($voting_status, ['not_started', 'active'])) {
        throw new Exception('Students can only be disqualified/requalified when voting status is "Not Started" or "Active". Current status: ' . ucfirst(str_replace('_', ' ', $voting_status)));
    }
    
    // Verify student exists and is union-eligible (approved candidate in class elections)
    $stmt = $pdo->prepare("
        SELECT DISTINCT u.user_id, u.fname, u.lname, u.roll_number, c.class_name
        FROM users u
        JOIN candidates cand ON u.user_id = cand.user_id
        JOIN elections e ON cand.election_id = e.election_id
        JOIN election_types et ON e.election_type_id = et.election_type_id
        JOIN classes c ON u.class_id = c.class_id
        WHERE u.user_id = ?
        AND et.election_type_name = 'class'
        AND e.election_year = ?
        AND cand.is_approved = 'approved'
    ");
    $stmt->execute([$student_id, $election['election_year']]);
    $student = $stmt->fetch();
    
    if (!$student) {
        throw new Exception('Student not found or not eligible for union elections. Only approved candidates from class elections can participate in union elections.');
    }
    
    $student_name = $student['fname'] . ' ' . $student['lname'];
    
    if ($action === 'disqualify') {
        // Check if student is already disqualified
        $stmt = $pdo->prepare("
            SELECT disqualification_id
            FROM election_disqualifications
            WHERE student_id = ? AND election_id = ?
        ");
        $stmt->execute([$student_id, $election_id]);

        if ($stmt->fetch()) {
            throw new Exception('Student is already disqualified from this union election.');
        }

        // Disqualify the student
        $stmt = $pdo->prepare("
            INSERT INTO election_disqualifications (student_id, election_id)
            VALUES (?, ?)
        ");
        $stmt->execute([$student_id, $election_id]);

        $response['message'] = "Student {$student_name} ({$student['class_name']}) has been disqualified from the union election.";

        // Log the action
        error_log("Commissioner {$current_user['fname']} {$current_user['lname']} disqualified student {$student_name} from union election {$election_id}");

    } elseif ($action === 'requalify') {
        // Check if student is disqualified
        $stmt = $pdo->prepare("
            SELECT disqualification_id
            FROM election_disqualifications
            WHERE student_id = ? AND election_id = ?
        ");
        $stmt->execute([$student_id, $election_id]);
        $disqualification = $stmt->fetch();

        if (!$disqualification) {
            throw new Exception('Student is not currently disqualified from this union election.');
        }
        
        // Requalify the student (delete the disqualification record)
        $stmt = $pdo->prepare("
            DELETE FROM election_disqualifications
            WHERE disqualification_id = ?
        ");
        $stmt->execute([$disqualification['disqualification_id']]);
        
        $response['message'] = "Student {$student_name} ({$student['class_name']}) has been requalified for the union election.";

        // Log the action
        error_log("Commissioner {$current_user['fname']} {$current_user['lname']} requalified student {$student_name} for union election {$election_id}");
        
    } else {
        throw new Exception('Invalid action.');
    }
    
    $response['success'] = true;
    
} catch (Exception $e) {
    $response['message'] = $e->getMessage();
}

echo json_encode($response);
?>
