<?php
// includes/auth.php - Authentication functions

require_once dirname(__DIR__) . '/config.php';

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function requireLogin() {
    if (!isLoggedIn()) {
        $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
        header('Location: ' . SITE_URL . 'index.php');
        exit();
    }
}

function hasRole($role) {
    return isset($_SESSION['role']) && $_SESSION['role'] === $role;
}

function requireRole($role) {
    requireLogin();
    if (!hasRole($role)) {
        http_response_code(403);
        die("Access Denied! You don't have permission to access this page.");
    }
}

function getCurrentUser() {
    global $conn;
    if (isLoggedIn()) {
        $user_id = $_SESSION['user_id'];
        $query = "SELECT * FROM users WHERE id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        return $result->fetch_assoc();
    }
    return null;
}

function getUserRoleBasedData($user_id, $role) {
    global $conn;
    
    switch($role) {
        case 'student':
            $query = "SELECT * FROM students WHERE user_id = ?";
            break;
        case 'teacher':
            $query = "SELECT * FROM teachers WHERE user_id = ?";
            break;
        case 'parent':
            $query = "SELECT * FROM parents WHERE user_id = ?";
            break;
        default:
            return null;
    }
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->fetch_assoc();
}

function logActivity($user_id, $action, $entity_type = null, $entity_id = null) {
    global $conn;
    
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    
    $query = "INSERT INTO activity_logs (user_id, action, entity_type, entity_id, ip_address, user_agent) 
              VALUES (?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ississ", $user_id, $action, $entity_type, $entity_id, $ip, $user_agent);
    return $stmt->execute();
}
?>