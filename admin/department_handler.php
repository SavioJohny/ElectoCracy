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
        case 'add_department':
            $department_name = trim($_POST['department_name'] ?? '');
            
            if (empty($department_name)) {
                throw new Exception('Department name is required.');
            }
            
            // Check if department already exists
            $stmt = $pdo->prepare("SELECT department_id FROM departments WHERE department_name = ?");
            $stmt->execute([$department_name]);
            if ($stmt->fetch()) {
                throw new Exception('Department already exists.');
            }
            
            // Insert new department
            $stmt = $pdo->prepare("INSERT INTO departments (department_name) VALUES (?)");
            $stmt->execute([$department_name]);
            
            $response['success'] = true;
            $response['message'] = "Department '$department_name' added successfully.";
            break;
            
        case 'update_department':
            $department_id = (int)($_POST['department_id'] ?? 0);
            $department_name = trim($_POST['department_name'] ?? '');
            
            if (!$department_id) {
                throw new Exception('Invalid department ID.');
            }
            
            if (empty($department_name)) {
                throw new Exception('Department name is required.');
            }
            
            // Check if department exists
            $stmt = $pdo->prepare("SELECT department_name FROM departments WHERE department_id = ?");
            $stmt->execute([$department_id]);
            $current_dept = $stmt->fetch();
            
            if (!$current_dept) {
                throw new Exception('Department not found.');
            }
            
            // Check if new name already exists (excluding current department)
            $stmt = $pdo->prepare("SELECT department_id FROM departments WHERE department_name = ? AND department_id != ?");
            $stmt->execute([$department_name, $department_id]);
            if ($stmt->fetch()) {
                throw new Exception('Department name already exists.');
            }
            
            // Update department
            $stmt = $pdo->prepare("UPDATE departments SET department_name = ? WHERE department_id = ?");
            $stmt->execute([$department_name, $department_id]);
            
            $response['success'] = true;
            $response['message'] = "Department updated successfully.";
            break;
            
        case 'delete_department':
            $department_id = (int)($_POST['department_id'] ?? 0);
            
            if (!$department_id) {
                throw new Exception('Invalid department ID.');
            }
            
            // Check if department exists
            $stmt = $pdo->prepare("SELECT department_name FROM departments WHERE department_id = ?");
            $stmt->execute([$department_id]);
            $dept = $stmt->fetch();
            
            if (!$dept) {
                throw new Exception('Department not found.');
            }
            
            // Get counts for confirmation
            $stmt = $pdo->prepare("SELECT COUNT(*) as class_count FROM classes WHERE department_id = ?");
            $stmt->execute([$department_id]);
            $class_count = $stmt->fetch()['class_count'];
            
            $stmt = $pdo->prepare("SELECT COUNT(*) as student_count FROM users WHERE department_id = ? AND role_id = 1");
            $stmt->execute([$department_id]);
            $student_count = $stmt->fetch()['student_count'];
            
            $pdo->beginTransaction();
            
            try {
                // Delete related records in order (due to foreign key constraints)
                
                // 1. Delete invigilator class assignments for classes in this department
                $stmt = $pdo->prepare("
                    DELETE ica FROM invigilator_class_assignments ica
                    JOIN classes c ON ica.class_id = c.class_id
                    WHERE c.department_id = ?
                ");
                $stmt->execute([$department_id]);
                
                // 2. Delete users (students and staff) in this department
                $stmt = $pdo->prepare("DELETE FROM users WHERE department_id = ?");
                $stmt->execute([$department_id]);
                
                // 3. Delete classes in this department
                $stmt = $pdo->prepare("DELETE FROM classes WHERE department_id = ?");
                $stmt->execute([$department_id]);
                
                // 4. Finally delete the department
                $stmt = $pdo->prepare("DELETE FROM departments WHERE department_id = ?");
                $stmt->execute([$department_id]);
                
                $pdo->commit();
                
                $message = "Department '{$dept['department_name']}' deleted successfully.";
                if ($class_count > 0 || $student_count > 0) {
                    $message .= " Also deleted $class_count classes and $student_count students.";
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
