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
        case 'delete_user':
            $user_id = (int)$_POST['user_id'];
            
            // Prevent self-deletion
            if ($user_id == $_SESSION['user_id']) {
                throw new Exception('You cannot delete your own account.');
            }
            
            // Check if user exists
            $stmt = $pdo->prepare("SELECT user_id, fname, lname FROM users WHERE user_id = ?");
            $stmt->execute([$user_id]);
            $user = $stmt->fetch();
            
            if (!$user) {
                throw new Exception('User not found.');
            }
            
            // Delete user (cascading deletes will handle related records)
            $stmt = $pdo->prepare("DELETE FROM users WHERE user_id = ?");
            $stmt->execute([$user_id]);
            
            $response['success'] = true;
            $response['message'] = "User {$user['fname']} {$user['lname']} deleted successfully.";
            break;
            
        case 'mass_delete':
            $user_ids = $_POST['user_ids'] ?? [];
            
            if (empty($user_ids)) {
                throw new Exception('No users selected for deletion.');
            }
            
            // Remove current user from deletion list
            $user_ids = array_filter($user_ids, function($id) {
                return (int)$id != $_SESSION['user_id'];
            });
            
            if (empty($user_ids)) {
                throw new Exception('Cannot delete selected users (includes your own account).');
            }
            
            $placeholders = str_repeat('?,', count($user_ids) - 1) . '?';
            
            // Get user names for confirmation
            $stmt = $pdo->prepare("SELECT fname, lname FROM users WHERE user_id IN ($placeholders)");
            $stmt->execute($user_ids);
            $users = $stmt->fetchAll();
            
            // Delete users
            $stmt = $pdo->prepare("DELETE FROM users WHERE user_id IN ($placeholders)");
            $stmt->execute($user_ids);
            
            $deleted_count = $stmt->rowCount();
            $response['success'] = true;
            $response['message'] = "Successfully deleted $deleted_count user(s).";
            break;
            
        case 'create_user':
            $data = [
                'email' => trim($_POST['email'] ?? ''),
                'password' => $_POST['password'] ?? '',
                'role_id' => (int)($_POST['role_id'] ?? 0),
                'fname' => trim($_POST['fname'] ?? ''),
                'mname' => trim($_POST['mname'] ?? ''),
                'lname' => trim($_POST['lname'] ?? ''),
                'dob' => ($_POST['dob'] ?? '') ?: null,
                'gender' => ($_POST['gender'] ?? '') ?: null,
                'phone' => trim($_POST['phone'] ?? ''),
                'address_line1' => trim($_POST['address_line1'] ?? ''),
                'address_line2' => trim($_POST['address_line2'] ?? ''),
                'city' => trim($_POST['city'] ?? ''),
                'state' => trim($_POST['state'] ?? ''),
                'postal_code' => trim($_POST['postal_code'] ?? ''),
                'department_id' => ($_POST['department_id'] ?? '') ? (int)$_POST['department_id'] : null,
                'roll_number' => trim($_POST['roll_number'] ?? ''),
                'class_id' => ($_POST['class_id'] ?? '') ? (int)$_POST['class_id'] : null
            ];
            
            // Validate required fields
            if (empty($data['email']) || empty($data['password']) || empty($data['fname']) || empty($data['lname'])) {
                throw new Exception('Email, password, first name, and last name are required.');
            }
            
            // Validate email format
            if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
                throw new Exception('Invalid email format.');
            }
            
            // Check if email already exists
            $stmt = $pdo->prepare("SELECT user_id FROM users WHERE email = ?");
            $stmt->execute([$data['email']]);
            if ($stmt->fetch()) {
                throw new Exception('Email already exists.');
            }
            
            // Hash password
            $data['password_hash'] = password_hash($data['password'], PASSWORD_DEFAULT);
            unset($data['password']);
            
            // For students, validate roll number and class
            if ($data['role_id'] == 1) { // Student role
                if (empty($data['roll_number'])) {
                    throw new Exception('Roll number is required for students.');
                }
                if (empty($data['class_id'])) {
                    throw new Exception('Class is required for students.');
                }
                
                // Check if roll number already exists
                $stmt = $pdo->prepare("SELECT user_id FROM users WHERE roll_number = ?");
                $stmt->execute([$data['roll_number']]);
                if ($stmt->fetch()) {
                    throw new Exception('Roll number already exists.');
                }
            } else {
                // For non-students, clear student-specific fields
                $data['roll_number'] = null;
                $data['class_id'] = null;
            }
            
            // Insert user
            $fields = implode(', ', array_keys($data));
            $placeholders = ':' . implode(', :', array_keys($data));
            
            $stmt = $pdo->prepare("INSERT INTO users ($fields) VALUES ($placeholders)");
            $stmt->execute($data);
            
            $response['success'] = true;
            $response['message'] = 'User created successfully.';
            $response['user_id'] = $pdo->lastInsertId();
            break;
            
        case 'update_user':
            $user_id = (int)$_POST['user_id'];
            
            $data = [
                'email' => trim($_POST['email'] ?? ''),
                'role_id' => (int)($_POST['role_id'] ?? 0),
                'fname' => trim($_POST['fname'] ?? ''),
                'mname' => trim($_POST['mname'] ?? ''),
                'lname' => trim($_POST['lname'] ?? ''),
                'dob' => ($_POST['dob'] ?? '') ?: null,
                'gender' => ($_POST['gender'] ?? '') ?: null,
                'phone' => trim($_POST['phone'] ?? ''),
                'address_line1' => trim($_POST['address_line1'] ?? ''),
                'address_line2' => trim($_POST['address_line2'] ?? ''),
                'city' => trim($_POST['city'] ?? ''),
                'state' => trim($_POST['state'] ?? ''),
                'postal_code' => trim($_POST['postal_code'] ?? ''),
                'department_id' => ($_POST['department_id'] ?? '') ? (int)$_POST['department_id'] : null,
                'roll_number' => trim($_POST['roll_number'] ?? ''),
                'class_id' => ($_POST['class_id'] ?? '') ? (int)$_POST['class_id'] : null
            ];
            
            // Validate required fields
            if (empty($data['email']) || empty($data['fname']) || empty($data['lname'])) {
                throw new Exception('Email, first name, and last name are required.');
            }
            
            // Validate email format
            if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
                throw new Exception('Invalid email format.');
            }
            
            // Check if email already exists (excluding current user)
            $stmt = $pdo->prepare("SELECT user_id FROM users WHERE email = ? AND user_id != ?");
            $stmt->execute([$data['email'], $user_id]);
            if ($stmt->fetch()) {
                throw new Exception('Email already exists.');
            }
            
            // For students, validate roll number and class
            if ($data['role_id'] == 1) { // Student role
                if (empty($data['roll_number'])) {
                    throw new Exception('Roll number is required for students.');
                }
                if (empty($data['class_id'])) {
                    throw new Exception('Class is required for students.');
                }
                
                // Check if roll number already exists (excluding current user)
                $stmt = $pdo->prepare("SELECT user_id FROM users WHERE roll_number = ? AND user_id != ?");
                $stmt->execute([$data['roll_number'], $user_id]);
                if ($stmt->fetch()) {
                    throw new Exception('Roll number already exists.');
                }
            } else {
                // For non-students, clear student-specific fields
                $data['roll_number'] = null;
                $data['class_id'] = null;
            }
            
            // Handle password update if provided
            if (!empty($_POST['password'])) {
                $data['password_hash'] = password_hash($_POST['password'], PASSWORD_DEFAULT);
            }
            
            // Build update query
            $set_clauses = [];
            $params = [];
            
            foreach ($data as $key => $value) {
                $set_clauses[] = "$key = ?";
                $params[] = $value;
            }
            $params[] = $user_id;
            
            $sql = "UPDATE users SET " . implode(', ', $set_clauses) . " WHERE user_id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            
            $response['success'] = true;
            $response['message'] = 'User updated successfully.';
            break;
            
        default:
            throw new Exception('Invalid action.');
    }

} catch (Exception $e) {
    $response['message'] = $e->getMessage();
}

echo json_encode($response);
?>
