<?php
session_start();
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/config/database.php';

requireRole('Invigilator');

$current_user = getCurrentUser();
$message = '';
$error = '';

try {
    $action = $_POST['action'] ?? '';
    $student_id = (int)($_POST['student_id'] ?? 0);
    $election_id = (int)($_POST['election_id'] ?? 0);
    
    if (!$student_id || !$election_id) {
        throw new Exception('Student and election are required.');
    }
    
    // Verify the election exists and get its details
    $stmt = $pdo->prepare("
        SELECT e.*, c.class_name, e.voting_status
        FROM elections e
        JOIN classes c ON e.class_id = c.class_id
        WHERE e.election_id = ?
    ");
    $stmt->execute([$election_id]);
    $election = $stmt->fetch();
    
    if (!$election) {
        throw new Exception('Election not found.');
    }
    
    // Check if voting has started (only allow changes when not_started)
    $voting_status = $election['voting_status'] ?? 'not_started';
    if ($voting_status !== 'not_started') {
        throw new Exception('Students cannot be disqualified/requalified after voting has started. Current status: ' . ucfirst(str_replace('_', ' ', $voting_status)));
    }
    
    // Verify invigilator has permission for this class
    $stmt = $pdo->prepare("
        SELECT ica.assignment_id
        FROM invigilator_class_assignments ica
        WHERE ica.invigilator_id = ? AND ica.class_id = ? AND ica.election_year = ?
    ");
    $stmt->execute([$current_user['user_id'], $election['class_id'], $election['election_year']]);
    
    if (!$stmt->fetch()) {
        throw new Exception('You do not have permission to manage this class.');
    }
    
    // Verify student exists and is in the correct class
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
            throw new Exception('Student is already disqualified from this election.');
        }

        // Disqualify the student
        $stmt = $pdo->prepare("
            INSERT INTO election_disqualifications (student_id, election_id)
            VALUES (?, ?)
        ");
        $stmt->execute([$student_id, $election_id]);

        $message = "Student {$student_name} has been disqualified from the {$election['class_name']} election.";

        // Log the action
        error_log("Invigilator {$current_user['fname']} {$current_user['lname']} disqualified student {$student_name} from election {$election_id}");
        
    } elseif ($action === 'requalify') {
        // Check if student is currently disqualified
        $stmt = $pdo->prepare("
            SELECT disqualification_id
            FROM election_disqualifications
            WHERE student_id = ? AND election_id = ?
        ");
        $stmt->execute([$student_id, $election_id]);
        $disqualification = $stmt->fetch();
        
        if (!$disqualification) {
            throw new Exception('Student is not currently disqualified from this election.');
        }
        
        // Requalify the student (delete the disqualification record)
        $stmt = $pdo->prepare("
            DELETE FROM election_disqualifications
            WHERE disqualification_id = ?
        ");
        $stmt->execute([$disqualification['disqualification_id']]);
        
        $message = "Student {$student_name} has been requalified for the {$election['class_name']} election.";

        // Log the action
        error_log("Invigilator {$current_user['fname']} {$current_user['lname']} requalified student {$student_name} for election {$election_id}");
        
    } else {
        throw new Exception('Invalid action.');
    }
    
} catch (Exception $e) {
    $error = $e->getMessage();
}

// Redirect back with message
$redirect_url = "student_disqualification.php?election_id={$election_id}";

if ($message) {
    $redirect_url .= "&success=" . urlencode($message);
} elseif ($error) {
    $redirect_url .= "&error=" . urlencode($error);
}

header("Location: {$redirect_url}");
exit;
?>
