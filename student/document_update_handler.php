<?php
session_start();
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/config/database.php';

requireRole('Student');

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['action']) || $_POST['action'] !== 'replace_document') {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit();
}

$user_id = $_SESSION['user_id'];
$candidate_id = (int)($_POST['candidate_id'] ?? 0);
$document_type = $_POST['document_type'] ?? '';

// Validate inputs
if (!$candidate_id || !in_array($document_type, ['marksheet', 'attendance'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid parameters']);
    exit();
}

// Verify the candidate belongs to the current user and is in pending status
$stmt = $pdo->prepare("
    SELECT c.candidate_id, c.is_approved, cd.marksheet_file, cd.attendance_file
    FROM candidates c
    LEFT JOIN candidate_documents cd ON c.candidate_id = cd.candidate_id
    WHERE c.candidate_id = ? AND c.user_id = ?
");
$stmt->execute([$candidate_id, $user_id]);
$candidate = $stmt->fetch();

if (!$candidate) {
    echo json_encode(['success' => false, 'message' => 'Application not found']);
    exit();
}

if ($candidate['is_approved'] !== 'pending') {
    echo json_encode(['success' => false, 'message' => 'Documents can only be replaced for pending applications']);
    exit();
}

// Check if file was uploaded
if (!isset($_FILES['new_document']) || $_FILES['new_document']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['success' => false, 'message' => 'No file uploaded or upload error']);
    exit();
}

$file = $_FILES['new_document'];

// Validate file type
$allowed_types = ['application/pdf', 'image/jpeg', 'image/jpg', 'image/png'];
$file_type = $file['type'];
$file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

if (!in_array($file_type, $allowed_types) && !in_array($file_extension, ['pdf', 'jpg', 'jpeg', 'png'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid file type. Only PDF and image files are allowed']);
    exit();
}

// Validate file size (5MB max)
if ($file['size'] > 5 * 1024 * 1024) {
    echo json_encode(['success' => false, 'message' => 'File size must be less than 5MB']);
    exit();
}

try {
    $pdo->beginTransaction();
    
    // Generate unique filename
    $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $new_filename = $document_type . '_' . $user_id . '_' . $candidate_id . '_' . time() . '.' . $file_extension;
    
    // Upload directory
    $upload_dir = dirname(__DIR__) . '/uploads/candidate_documents/';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }
    
    $upload_path = $upload_dir . $new_filename;
    
    // Move uploaded file
    if (!move_uploaded_file($file['tmp_name'], $upload_path)) {
        throw new Exception('Failed to upload file');
    }
    
    // Delete old file if exists
    $old_filename = $document_type === 'marksheet' ? $candidate['marksheet_file'] : $candidate['attendance_file'];
    if ($old_filename) {
        $old_file_path = $upload_dir . $old_filename;
        if (file_exists($old_file_path)) {
            unlink($old_file_path);
        }
    }
    
    // Check if candidate_documents record exists
    $stmt = $pdo->prepare("SELECT candidate_id FROM candidate_documents WHERE candidate_id = ?");
    $stmt->execute([$candidate_id]);
    $doc_exists = $stmt->fetch();
    
    if ($doc_exists) {
        // Update existing record
        $field = $document_type === 'marksheet' ? 'marksheet_file' : 'attendance_file';
        $stmt = $pdo->prepare("UPDATE candidate_documents SET {$field} = ? WHERE candidate_id = ?");
        $stmt->execute([$new_filename, $candidate_id]);
    } else {
        // Insert new record
        if ($document_type === 'marksheet') {
            $stmt = $pdo->prepare("INSERT INTO candidate_documents (candidate_id, marksheet_file) VALUES (?, ?)");
        } else {
            $stmt = $pdo->prepare("INSERT INTO candidate_documents (candidate_id, attendance_file) VALUES (?, ?)");
        }
        $stmt->execute([$candidate_id, $new_filename]);
    }
    
    $pdo->commit();
    
    $document_name = $document_type === 'marksheet' ? 'Marksheet' : 'Attendance Record';
    echo json_encode([
        'success' => true, 
        'message' => $document_name . ' has been successfully replaced!'
    ]);
    
} catch (Exception $e) {
    $pdo->rollBack();
    
    // Clean up uploaded file if database operation failed
    if (isset($upload_path) && file_exists($upload_path)) {
        unlink($upload_path);
    }
    
    echo json_encode([
        'success' => false, 
        'message' => 'Failed to replace document: ' . $e->getMessage()
    ]);
}
?>
