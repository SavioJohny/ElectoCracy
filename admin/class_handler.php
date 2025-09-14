<?php
session_start();
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/config/database.php';

requireRole('Super Admin');

header('Content-Type: application/json');

$response = ['success' => false, 'message' => ''];

try {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'add_class':
            $class_name = trim($_POST['class_name'] ?? '');
            $department_id = (int)($_POST['department_id'] ?? 0);
            
            if (empty($class_name)) {
                throw new Exception('Class name is required.');
            }
            
            if (!$department_id) {
                throw new Exception('Department is required.');
            }
            
            // Check if department exists
            $stmt = $pdo->prepare("SELECT department_name FROM departments WHERE department_id = ?");
            $stmt->execute([$department_id]);
            $department = $stmt->fetch();
            
            if (!$department) {
                throw new Exception('Invalid department selected.');
            }
            
            // Check if class already exists in this department
            $stmt = $pdo->prepare("SELECT class_id FROM classes WHERE class_name = ? AND department_id = ?");
            $stmt->execute([$class_name, $department_id]);
            if ($stmt->fetch()) {
                throw new Exception('Class already exists in this department.');
            }
            
            // Insert new class
            $stmt = $pdo->prepare("INSERT INTO classes (class_name, department_id) VALUES (?, ?)");
            $stmt->execute([$class_name, $department_id]);
            
            $response['success'] = true;
            $response['message'] = "Class '$class_name' added successfully to {$department['department_name']}.";
            break;
            
        case 'update_class':
            $class_id = (int)($_POST['class_id'] ?? 0);
            $class_name = trim($_POST['class_name'] ?? '');
            $department_id = (int)($_POST['department_id'] ?? 0);
            
            if (!$class_id) {
                throw new Exception('Invalid class ID.');
            }
            
            if (empty($class_name)) {
                throw new Exception('Class name is required.');
            }
            
            if (!$department_id) {
                throw new Exception('Department is required.');
            }
            
            // Check if class exists
            $stmt = $pdo->prepare("SELECT class_name FROM classes WHERE class_id = ?");
            $stmt->execute([$class_id]);
            $current_class = $stmt->fetch();
            
            if (!$current_class) {
                throw new Exception('Class not found.');
            }
            
            // Check if department exists
            $stmt = $pdo->prepare("SELECT department_name FROM departments WHERE department_id = ?");
            $stmt->execute([$department_id]);
            $department = $stmt->fetch();
            
            if (!$department) {
                throw new Exception('Invalid department selected.');
            }
            
            // Check if new name already exists in the department (excluding current class)
            $stmt = $pdo->prepare("SELECT class_id FROM classes WHERE class_name = ? AND department_id = ? AND class_id != ?");
            $stmt->execute([$class_name, $department_id, $class_id]);
            if ($stmt->fetch()) {
                throw new Exception('Class name already exists in this department.');
            }
            
            // Update class
            $stmt = $pdo->prepare("UPDATE classes SET class_name = ?, department_id = ? WHERE class_id = ?");
            $stmt->execute([$class_name, $department_id, $class_id]);
            
            $response['success'] = true;
            $response['message'] = "Class updated successfully.";
            break;
            
        case 'delete_class':
            $class_id = (int)($_POST['class_id'] ?? 0);
            
            if (!$class_id) {
                throw new Exception('Invalid class ID.');
            }
            
            // Check if class exists
            $stmt = $pdo->prepare("SELECT class_name FROM classes WHERE class_id = ?");
            $stmt->execute([$class_id]);
            $class = $stmt->fetch();
            
            if (!$class) {
                throw new Exception('Class not found.');
            }
            
            // Get student count for confirmation
            $stmt = $pdo->prepare("SELECT COUNT(*) as student_count FROM users WHERE class_id = ? AND role_id = 1");
            $stmt->execute([$class_id]);
            $student_count = $stmt->fetch()['student_count'];
            
            $pdo->beginTransaction();
            
            try {
                // Delete related records in order (due to foreign key constraints)
                
                // 1. Delete invigilator class assignments
                $stmt = $pdo->prepare("DELETE FROM invigilator_class_assignments WHERE class_id = ?");
                $stmt->execute([$class_id]);
                
                // 2. Delete students in this class
                $stmt = $pdo->prepare("DELETE FROM users WHERE class_id = ? AND role_id = 1");
                $stmt->execute([$class_id]);
                
                // 3. Finally delete the class
                $stmt = $pdo->prepare("DELETE FROM classes WHERE class_id = ?");
                $stmt->execute([$class_id]);
                
                $pdo->commit();
                
                $message = "Class '{$class['class_name']}' deleted successfully.";
                if ($student_count > 0) {
                    $message .= " Also deleted $student_count students.";
                }
                
                $response['success'] = true;
                $response['message'] = $message;
                
            } catch (Exception $e) {
                $pdo->rollBack();
                throw $e;
            }
            break;
            
        default:
            throw new Exception('Invalid action.');
    }
    
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $response['message'] = $e->getMessage();
}

echo json_encode($response);
