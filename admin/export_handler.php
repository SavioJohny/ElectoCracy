<?php
session_start();
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/config/database.php';

requireRole('Super Admin');

try {
    $department_id = $_POST['department_id'] ?? '';
    $class_id = $_POST['class_id'] ?? '';
    $include_passwords = isset($_POST['include_passwords']);
    $include_addresses = isset($_POST['include_addresses']);
    $preview = isset($_POST['preview']);
    
    // Build query conditions
    $where_conditions = ["u.role_id = 1"]; // Students only
    $params = [];
    
    if (!empty($department_id)) {
        $where_conditions[] = "u.department_id = ?";
        $params[] = $department_id;
    }
    
    if (!empty($class_id)) {
        $where_conditions[] = "u.class_id = ?";
        $params[] = $class_id;
    }
    
    $where_clause = implode(' AND ', $where_conditions);
    
    // If preview, just return count
    if ($preview) {
        header('Content-Type: application/json');
        
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as count
            FROM users u
            WHERE $where_clause
        ");
        $stmt->execute($params);
        $count = $stmt->fetch()['count'];
        
        echo json_encode([
            'success' => true,
            'count' => $count,
            'message' => "$count students found"
        ]);
        exit;
    }
    
    // Build select fields
    $select_fields = [
        'u.roll_number',
        'u.fname',
        'u.mname',
        'u.lname',
        'u.email',
        'u.phone',
        'u.gender'
    ];
    
    if ($include_passwords) {
        $select_fields[] = "'[ENCRYPTED]' as password"; // Don't export actual passwords
    }
    
    if ($include_addresses) {
        $select_fields = array_merge($select_fields, [
            'u.address_line1',
            'u.address_line2',
            'u.city',
            'u.state',
            'u.postal_code'
        ]);
    }
    
    $select_clause = implode(', ', $select_fields);
    
    // Get students data
    $stmt = $pdo->prepare("
        SELECT $select_clause, c.class_name, d.department_name
        FROM users u
        LEFT JOIN classes c ON u.class_id = c.class_id
        LEFT JOIN departments d ON u.department_id = d.department_id
        WHERE $where_clause
        ORDER BY u.roll_number
    ");
    $stmt->execute($params);
    $students = $stmt->fetchAll();
    
    if (empty($students)) {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'message' => 'No students found with the selected criteria.'
        ]);
        exit;
    }
    
    // Generate filename
    $filename_parts = ['students'];
    
    if (!empty($department_id)) {
        $dept_name = $students[0]['department_name'] ?? 'dept';
        $filename_parts[] = strtolower(str_replace(' ', '_', $dept_name));
    }
    
    if (!empty($class_id)) {
        $class_name = $students[0]['class_name'] ?? 'class';
        $filename_parts[] = strtolower(str_replace(' ', '_', $class_name));
    }
    
    $filename_parts[] = date('Y-m-d');
    $filename = implode('_', $filename_parts) . '.csv';
    
    // Set headers for CSV download
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: no-cache, must-revalidate');
    header('Expires: Sat, 26 Jul 1997 05:00:00 GMT');
    
    // Create CSV output
    $output = fopen('php://output', 'w');
    
    // Write CSV header
    $csv_headers = [
        'Roll Number',
        'First Name',
        'Middle Name',
        'Last Name',
        'Email',
        'Phone',
        'Gender'
    ];
    
    if ($include_passwords) {
        $csv_headers[] = 'Password';
    }
    
    if ($include_addresses) {
        $csv_headers = array_merge($csv_headers, [
            'Address Line 1',
            'Address Line 2',
            'City',
            'State',
            'Postal Code'
        ]);
    }
    
    fputcsv($output, $csv_headers);
    
    // Write data rows
    foreach ($students as $student) {
        $row = [
            $student['roll_number'],
            $student['fname'],
            $student['mname'] ?? '',
            $student['lname'],
            $student['email'],
            $student['phone'] ?? '',
            $student['gender'] ?? ''
        ];
        
        if ($include_passwords) {
            $row[] = '[ENCRYPTED]'; // Don't export actual passwords
        }
        
        if ($include_addresses) {
            $row = array_merge($row, [
                $student['address_line1'] ?? '',
                $student['address_line2'] ?? '',
                $student['city'] ?? '',
                $student['state'] ?? '',
                $student['postal_code'] ?? ''
            ]);
        }
        
        fputcsv($output, $row);
    }
    
    fclose($output);
    
} catch (Exception $e) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
