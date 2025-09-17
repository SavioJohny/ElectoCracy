<?php
session_start();
require_once 'config/database.php';

// Redirect to login if not authenticated
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Redirect to appropriate dashboard based on role
switch ($_SESSION['role_name']) {
    case 'Student':
        header('Location: student/dashboard.php');
        break;
    case 'Invigilator':
        header('Location: invigilator/dashboard.php');
        break;
    case 'Election Commissioner':
        header('Location: commissioner/dashboard.php');
        break;
    case 'Super Admin':
        header('Location: admin/dashboard.php');
        break;
    default:
        header('Location: login.php');
        break;
}
exit();
?>
