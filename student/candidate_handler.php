<?php
session_start();
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/config/database.php';

requireRole('Student');

header('Content-Type: application/json');

$response = ['success' => false, 'message' => ''];

try {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'apply_candidate':
            $user_id = $_SESSION['user_id'];
            $election_id = (int)($_POST['election_id'] ?? 0);
            $position_id = (int)($_POST['position_id'] ?? 0);
            $statement = trim($_POST['statement'] ?? '');
            
            // Validate required fields
            if (!$election_id || !$position_id) {
                throw new Exception('Election and position are required.');
            }
            
            // Validate files
            if (!isset($_FILES['marksheet']) || !isset($_FILES['attendance'])) {
                throw new Exception('Both marksheet and attendance documents are required.');
            }
            
            if ($_FILES['marksheet']['error'] !== UPLOAD_ERR_OK || $_FILES['attendance']['error'] !== UPLOAD_ERR_OK) {
                throw new Exception('File upload failed. Please try again.');
            }
            
            // Get student information
            $stmt = $pdo->prepare("
                SELECT u.*, c.class_name, d.department_name 
                FROM users u
                JOIN classes c ON u.class_id = c.class_id
                JOIN departments d ON c.department_id = d.department_id
                WHERE u.user_id = ? AND u.role_id = 1
            ");
            $stmt->execute([$user_id]);
            $student = $stmt->fetch();
            
            if (!$student) {
                throw new Exception('Student not found.');
            }
            
            // Check if voting_status column exists
            $stmt = $pdo->query("SHOW COLUMNS FROM elections LIKE 'voting_status'");
            $voting_column_exists = $stmt->fetch();

            // Validate election
            $select_fields = "e.*, et.election_type_name";
            if ($voting_column_exists) {
                $select_fields .= ", e.voting_status";
            }

            $stmt = $pdo->prepare("
                SELECT {$select_fields}
                FROM elections e
                JOIN election_types et ON e.election_type_id = et.election_type_id
                WHERE e.election_id = ? AND e.is_active = 1
            ");
            $stmt->execute([$election_id]);
            $election = $stmt->fetch();

            if (!$election) {
                throw new Exception('Election not found or not active.');
            }

            // For class elections, check if candidate applications are allowed
            if ($election['election_type_name'] === 'class' && $voting_column_exists) {
                $voting_status = $election['voting_status'] ?? 'not_started';
                if ($voting_status === 'active') {
                    throw new Exception('Candidate applications are no longer accepted. Voting has already started for this election.');
                } elseif ($voting_status === 'paused') {
                    throw new Exception('Candidate applications are not currently accepted. The election is paused.');
                } elseif ($voting_status === 'ended') {
                    throw new Exception('Candidate applications are closed. This election has ended.');
                }
                // Only allow applications when status is 'not_started'
            }

            // Check if student is disqualified from this election
            $stmt = $pdo->prepare("
                SELECT disqualification_id
                FROM election_disqualifications
                WHERE student_id = ? AND election_id = ?
            ");
            $stmt->execute([$user_id, $election_id]);
            $disqualification = $stmt->fetch();

            if ($disqualification) {
                throw new Exception('You are disqualified from participating in this election and cannot apply as a candidate.');
            }
            
            // For class elections, validate student belongs to the class
            if ($election['election_type_name'] === 'class' && $election['class_id'] != $student['class_id']) {
                throw new Exception('You can only apply for elections in your own class.');
            }
            
            // Validate position
            $stmt = $pdo->prepare("SELECT * FROM positions WHERE position_id = ?");
            $stmt->execute([$position_id]);
            $position = $stmt->fetch();
            
            if (!$position) {
                throw new Exception('Position not found.');
            }
            
            // Position is valid for application
            
            // Check if student already applied for this election and position
            $stmt = $pdo->prepare("
                SELECT candidate_id
                FROM candidates
                WHERE user_id = ? AND election_id = ? AND position_id = ?
            ");
            $stmt->execute([$user_id, $election_id, $position_id]);
            
            if ($stmt->fetch()) {
                throw new Exception('You have already applied for this position in this election.');
            }
            
            // Create uploads directory if it doesn't exist
            $upload_dir = dirname(__DIR__) . '/uploads/candidate_documents/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            
            // Process file uploads
            $marksheet_filename = null;
            $attendance_filename = null;
            
            // Upload marksheet
            $marksheet_file = $_FILES['marksheet'];
            $marksheet_ext = strtolower(pathinfo($marksheet_file['name'], PATHINFO_EXTENSION));
            $allowed_extensions = ['pdf', 'jpg', 'jpeg', 'png'];
            
            if (!in_array($marksheet_ext, $allowed_extensions)) {
                throw new Exception('Marksheet must be a PDF or image file.');
            }
            
            if ($marksheet_file['size'] > 5 * 1024 * 1024) {
                throw new Exception('Marksheet file size must be less than 5MB.');
            }
            
            $marksheet_filename = 'marksheet_' . $user_id . '_' . $election_id . '_' . time() . '.' . $marksheet_ext;
            $marksheet_path = $upload_dir . $marksheet_filename;
            
            if (!move_uploaded_file($marksheet_file['tmp_name'], $marksheet_path)) {
                throw new Exception('Failed to upload marksheet.');
            }
            
            // Upload attendance
            $attendance_file = $_FILES['attendance'];
            $attendance_ext = strtolower(pathinfo($attendance_file['name'], PATHINFO_EXTENSION));
            
            if (!in_array($attendance_ext, $allowed_extensions)) {
                throw new Exception('Attendance document must be a PDF or image file.');
            }
            
            if ($attendance_file['size'] > 5 * 1024 * 1024) {
                throw new Exception('Attendance file size must be less than 5MB.');
            }
            
            $attendance_filename = 'attendance_' . $user_id . '_' . $election_id . '_' . time() . '.' . $attendance_ext;
            $attendance_path = $upload_dir . $attendance_filename;
            
            if (!move_uploaded_file($attendance_file['tmp_name'], $attendance_path)) {
                // Clean up marksheet if attendance upload fails
                unlink($marksheet_path);
                throw new Exception('Failed to upload attendance document.');
            }
            
            // Insert candidate application (defaults to 'pending' status)
            $stmt = $pdo->prepare("
                INSERT INTO candidates (user_id, election_id, position_id, is_approved)
                VALUES (?, ?, ?, 'pending')
            ");
            $stmt->execute([$user_id, $election_id, $position_id]);

            // Get the candidate ID
            $candidate_id = $pdo->lastInsertId();

            // Insert document information
            $stmt = $pdo->prepare("
                INSERT INTO candidate_documents (candidate_id, marksheet_file, attendance_file)
                VALUES (?, ?, ?)
            ");
            $stmt->execute([$candidate_id, $marksheet_filename, $attendance_filename]);
            
            $response['success'] = true;
            $response['message'] = "Your application has been submitted successfully with required documents. It will be reviewed by the invigilator.";
            break;
            
        default:
            throw new Exception('Invalid action.');
    }
    
} catch (Exception $e) {
    // Clean up uploaded files if there was an error
    if (isset($marksheet_path) && file_exists($marksheet_path)) {
        unlink($marksheet_path);
    }
    if (isset($attendance_path) && file_exists($attendance_path)) {
        unlink($attendance_path);
    }
    
    $response['message'] = $e->getMessage();
}

echo json_encode($response);
