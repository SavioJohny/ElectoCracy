<?php
session_start();
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/config/database.php';

requireRole('Super Admin');

header('Content-Type: application/json');

$response = ['success' => false, 'message' => ''];

try {
    // Validate required fields
    if (!isset($_POST['department_id']) || !isset($_POST['class_id']) || !isset($_FILES['csv_file'])) {
        throw new Exception('Missing required fields.');
    }
    
    $department_id = (int)$_POST['department_id'];
    $class_id = (int)$_POST['class_id'];
    $update_existing = isset($_POST['update_existing']);
    
    // Validate department and class
    $stmt = $pdo->prepare("
        SELECT c.class_id, c.class_name, d.department_name 
        FROM classes c 
        JOIN departments d ON c.department_id = d.department_id 
        WHERE c.class_id = ? AND c.department_id = ?
    ");
    $stmt->execute([$class_id, $department_id]);
    $class_info = $stmt->fetch();
    
    if (!$class_info) {
        throw new Exception('Invalid department or class selection.');
    }
    
    // Validate file upload
    $file = $_FILES['csv_file'];
    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('File upload failed.');
    }
    
    if ($file['size'] > 5 * 1024 * 1024) { // 5MB limit
        throw new Exception('File size exceeds 5MB limit.');
    }
    
    $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if ($file_extension !== 'csv') {
        throw new Exception('Only CSV files are allowed.');
    }
    
    // Read and parse CSV file
    $csv_data = [];
    $handle = fopen($file['tmp_name'], 'r');
    
    if (!$handle) {
        throw new Exception('Could not read CSV file.');
    }
    
    // Read header row
    $header = fgetcsv($handle);
    if (!$header) {
        throw new Exception('CSV file is empty or invalid.');
    }
    
    // Expected headers
    $expected_headers = ['Roll Number', 'First Name', 'Middle Name', 'Last Name', 'Email', 'Phone', 'Gender', 'Password'];
    
    // Validate headers
    foreach ($expected_headers as $expected) {
        if (!in_array($expected, $header)) {
            throw new Exception("Missing required column: $expected");
        }
    }
    
    // Read data rows
    $row_number = 1;
    while (($row = fgetcsv($handle)) !== false) {
        $row_number++;
        
        if (count($row) !== count($header)) {
            continue; // Skip malformed rows
        }
        
        $csv_data[] = array_combine($header, $row);
    }
    
    fclose($handle);
    
    if (empty($csv_data)) {
        throw new Exception('No valid data rows found in CSV file.');
    }
    
    // Process data
    $success_count = 0;
    $error_count = 0;
    $update_count = 0;
    $errors = [];
    
    $pdo->beginTransaction();
    
    foreach ($csv_data as $index => $row) {
        try {
            $row_num = $index + 2; // +2 because index starts at 0 and we skip header
            
            // Validate required fields
            $roll_number = trim($row['Roll Number']);
            $fname = trim($row['First Name']);
            $lname = trim($row['Last Name']);
            $email = trim($row['Email']);
            $password = trim($row['Password']);
            $gender = trim($row['Gender']);
            
            if (empty($roll_number) || empty($fname) || empty($lname) || empty($email) || empty($password) || empty($gender)) {
                throw new Exception("Row $row_num: Missing required fields!");
            }
            
            // Validate gender value
            if (!in_array(strtoupper($gender), ['M', 'F', 'O'])) {
                throw new Exception("Row $row_num: Gender must be M, F, or O");
            }
            
            // Validate email format
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new Exception("Row $row_num: Invalid email format");
            }
            
            // Validate password length
            if (strlen($password) < 8) {
                throw new Exception("Row $row_num: Password must be at least 8 characters");
            }
            
            // Check if student exists (by roll number)
            $stmt = $pdo->prepare("SELECT user_id FROM users WHERE roll_number = ?");
            $stmt->execute([$roll_number]);
            $existing_user = $stmt->fetch();
            
            if ($existing_user && !$update_existing) {
                throw new Exception("Row $row_num: Roll number '$roll_number' already exists");
            }
            
            // Check if email exists (excluding current user if updating)
            $email_check_sql = "SELECT user_id FROM users WHERE email = ?";
            $email_params = [$email];
            
            if ($existing_user) {
                $email_check_sql .= " AND user_id != ?";
                $email_params[] = $existing_user['user_id'];
            }
            
            $stmt = $pdo->prepare($email_check_sql);
            $stmt->execute($email_params);
            
            if ($stmt->fetch()) {
                throw new Exception("Row $row_num: Email '$email' already exists");
            }
            
            // Prepare data
            $data = [
                'roll_number' => $roll_number,
                'fname' => $fname,
                'mname' => trim($row['Middle Name'] ?? ''),
                'lname' => $lname,
                'email' => $email,
                'phone' => trim($row['Phone'] ?? ''),
                'gender' => strtoupper($gender),
                'password_hash' => password_hash($password, PASSWORD_DEFAULT),
                'role_id' => 1, // Student role
                'department_id' => $department_id,
                'class_id' => $class_id
            ];
            
            if ($existing_user) {
                // Update existing user
                $sql = "UPDATE users SET 
                        fname = ?, mname = ?, lname = ?, email = ?, phone = ?, 
                        gender = ?, password_hash = ?, department_id = ?, class_id = ?
                        WHERE user_id = ?";
                
                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    $data['fname'], $data['mname'], $data['lname'], $data['email'], 
                    $data['phone'], $data['gender'], $data['password_hash'], 
                    $data['department_id'], $data['class_id'], $existing_user['user_id']
                ]);
                
                $update_count++;
            } else {
                // Insert new user
                $sql = "INSERT INTO users (roll_number, fname, mname, lname, email, phone, gender, password_hash, role_id, department_id, class_id) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                
                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    $data['roll_number'], $data['fname'], $data['mname'], $data['lname'], 
                    $data['email'], $data['phone'], $data['gender'], $data['password_hash'], 
                    $data['role_id'], $data['department_id'], $data['class_id']
                ]);
                
                $success_count++;
            }
            
        } catch (Exception $e) {
            $error_count++;
            $errors[] = $e->getMessage();
        }
    }
    
    $pdo->commit();
    
    // Prepare results
    $total_processed = $success_count + $update_count + $error_count;
    $message = "Import completed: $success_count new students added";
    
    if ($update_count > 0) {
        $message .= ", $update_count students updated";
    }
    
    if ($error_count > 0) {
        $message .= ", $error_count errors";
    }
    
    $results_html = "
        <div class='row'>
            <div class='col-md-3'>
                <div class='text-center'>
                    <h4 class='text-success'>$success_count</h4>
                    <small class='text-muted'>New Students</small>
                </div>
            </div>
            <div class='col-md-3'>
                <div class='text-center'>
                    <h4 class='text-info'>$update_count</h4>
                    <small class='text-muted'>Updated</small>
                </div>
            </div>
            <div class='col-md-3'>
                <div class='text-center'>
                    <h4 class='text-danger'>$error_count</h4>
                    <small class='text-muted'>Errors</small>
                </div>
            </div>
            <div class='col-md-3'>
                <div class='text-center'>
                    <h4 class='text-primary'>$total_processed</h4>
                    <small class='text-muted'>Total Processed</small>
                </div>
            </div>
        </div>
    ";
    
    if (!empty($errors)) {
        $results_html .= "<div class='mt-3'><h6>Errors:</h6><ul class='small'>";
        foreach (array_slice($errors, 0, 10) as $error) { // Show first 10 errors
            $results_html .= "<li>" . htmlspecialchars($error) . "</li>";
        }
        if (count($errors) > 10) {
            $results_html .= "<li><em>... and " . (count($errors) - 10) . " more errors</em></li>";
        }
        $results_html .= "</ul></div>";
    }
    
    $response['success'] = true;
    $response['message'] = $message;
    $response['results'] = $results_html;
    $response['stats'] = [
        'new' => $success_count,
        'updated' => $update_count,
        'errors' => $error_count,
        'total' => $total_processed
    ];

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $response['message'] = $e->getMessage();
}

echo json_encode($response);
