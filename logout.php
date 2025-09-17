<?php
session_start();
require_once 'config/database.php';

// Clear session token from database
if (isset($_SESSION['user_id'])) {
    try {
        $stmt = $pdo->prepare("UPDATE users SET session_token = NULL WHERE user_id = ?");
        $stmt->execute([$_SESSION['user_id']]);
    } catch (PDOException $e) {
        // Log error but continue with logout
    }
}

// Destroy session
session_destroy();

// Redirect to login
header('Location: login.php');
exit();
?>
