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

// Destroy session completely
session_unset();
session_destroy();

// Start new session and set logout flag
session_start();
$_SESSION['logged_out_by_back_button'] = true;

// Set headers to prevent caching
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

// Return success response
header('Content-Type: application/json');
echo json_encode(['success' => true, 'message' => 'Session killed']);
exit();
