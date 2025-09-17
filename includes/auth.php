<?php
require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/config/config.php';

/**
 * Check if user is authenticated
 */
function isAuthenticated() {
    return isset($_SESSION['user_id']) && isset($_SESSION['session_token']);
}

/**
 * Require authentication - redirect to login if not authenticated
 */
function requireAuth() {
    if (!isAuthenticated()) {
        header('Location: ' . BASE_URL . 'login.php');
        exit();
    }
}

/**
 * Check if user has specific role
 */
function hasRole($required_role) {
    return isset($_SESSION['role_name']) && $_SESSION['role_name'] === $required_role;
}

/**
 * Require specific role - redirect to unauthorized page if not authorized
 */
function requireRole($required_role) {
    requireAuth();
    if (!hasRole($required_role)) {
        header('Location: ' . BASE_URL . 'unauthorized.php');
        exit();
    }
}

/**
 * Check if user has any of the specified roles
 */
function hasAnyRole($roles) {
    if (!isset($_SESSION['role_name'])) {
        return false;
    }
    return in_array($_SESSION['role_name'], $roles);
}

/**
 * Require any of the specified roles
 */
function requireAnyRole($roles) {
    requireAuth();
    if (!hasAnyRole($roles)) {
        header('Location: ' . BASE_URL . 'unauthorized.php');
        exit();
    }
}

/**
 * Validate session token against database
 */
function validateSessionToken() {
    global $pdo;
    
    if (!isAuthenticated()) {
        return false;
    }
    
    try {
        $stmt = $pdo->prepare("SELECT session_token FROM users WHERE user_id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch();
        
        return $user && $user['session_token'] === $_SESSION['session_token'];
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Get current user information
 */
function getCurrentUser() {
    global $pdo;
    
    if (!isAuthenticated()) {
        return null;
    }
    
    try {
        $stmt = $pdo->prepare("
            SELECT u.*, r.role_name, d.department_name, c.class_name 
            FROM users u 
            JOIN roles r ON u.role_id = r.role_id 
            LEFT JOIN departments d ON u.department_id = d.department_id 
            LEFT JOIN classes c ON u.class_id = c.class_id 
            WHERE u.user_id = ?
        ");
        $stmt->execute([$_SESSION['user_id']]);
        return $stmt->fetch();
    } catch (PDOException $e) {
        return null;
    }
}

/**
 * Generate CSRF token
 */
function generateCSRFToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Validate CSRF token
 */
function validateCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}
?>
