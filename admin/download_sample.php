<?php
session_start();
require_once dirname(__DIR__) . '/includes/auth.php';

requireRole('Super Admin');

// Set headers for CSV download
header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="sample_students.csv"');
header('Cache-Control: no-cache, must-revalidate');
header('Expires: Sat, 26 Jul 1997 05:00:00 GMT');

// Create CSV output
$output = fopen('php://output', 'w');

// Write CSV header
$headers = [
    'Roll Number',
    'First Name',
    'Middle Name',
    'Last Name',
    'Email',
    'Phone',
    'Gender',
    'Password'
];

fputcsv($output, $headers);

// Write sample data
$sample_data = [
    ['CS2024001', 'John', 'M', 'Doe', 'john.doe@example.com', '9876543210', 'M', 'password123'],
    ['CS2024002', 'Jane', '', 'Smith', 'jane.smith@example.com', '9876543211', 'F', 'password123'],
    ['CS2024003', 'Robert', 'K', 'Johnson', 'robert.johnson@example.com', '9876543212', 'M', 'password123'],
    ['CS2024004', 'Emily', 'A', 'Davis', 'emily.davis@example.com', '9876543213', 'F', 'password123'],
    ['CS2024005', 'Michael', '', 'Wilson', 'michael.wilson@example.com', '9876543214', 'M', 'password123']
];

foreach ($sample_data as $row) {
    fputcsv($output, $row);
}

fclose($output);
