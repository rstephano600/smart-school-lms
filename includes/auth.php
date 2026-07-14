<?php
// =====================================================
// AUTHENTICATION FUNCTIONS - SMART SCHOOL LMS
// =====================================================

// Check if user is logged in
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

// Check if user has specific role
function hasRole($role) {
    return isset($_SESSION['role']) && $_SESSION['role'] === $role;
}

// Require login
function requireLogin() {
    if (!isLoggedIn()) {
        $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
        header('Location: ' . SITE_URL . 'index.php');
        exit();
    }
}

// Require specific role
function requireRole($role) {
    requireLogin();
    if (!hasRole($role)) {
        // Redirect to appropriate dashboard based on role
        $role_map = [
            'admin' => SITE_URL . 'admin/dashboard.php',
            'academic' => SITE_URL . 'academic/dashboard.php',
            'teacher' => SITE_URL . 'teacher/dashboard.php',
            'student' => SITE_URL . 'student/dashboard.php',
            'parent' => SITE_URL . 'parent/dashboard.php'
        ];
        $redirect = $role_map[$_SESSION['role'] ?? ''] ?? SITE_URL . 'index.php';
        header('Location: ' . $redirect);
        exit();
    }
}

// Get current user
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

// Get user role
function getUserRole() {
    return $_SESSION['role'] ?? 'guest';
}

// Get user name
function getUserName() {
    return $_SESSION['user_name'] ?? 'User';
}

// Get user ID
function getUserId() {
    return $_SESSION['user_id'] ?? 0;
}

// Login user
function loginUser($user) {
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['user_name'] = $user['first_name'] . ' ' . $user['last_name'];
    $_SESSION['role'] = $user['role'];
    $_SESSION['email'] = $user['email'];
    
    // Update last login
    global $conn;
    $update = $conn->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
    $update->bind_param("i", $user['id']);
    $update->execute();
    
    // Log activity - logActivity is in config.php
    logActivity($user['id'], 'login');
}

// Logout user
function logoutUser() {
    if (isLoggedIn()) {
        global $conn;
        logActivity($_SESSION['user_id'], 'logout');
    }
    session_destroy();
    header('Location: ' . SITE_URL . 'index.php');
    exit();
}

// Verify password
function verifyPassword($conn, $user_id, $password) {
    $query = $conn->prepare("SELECT password FROM users WHERE id = ?");
    $query->bind_param("i", $user_id);
    $query->execute();
    $user = $query->get_result()->fetch_assoc();
    
    if (!$user) return false;
    
    $stored_hash = $user['password'];
    
    // Try password_verify
    if (password_verify($password, $stored_hash)) {
        return true;
    }
    
    // If stored is plain text (legacy)
    if ($password === $stored_hash) {
        // Re-hash and update
        $new_hash = password_hash($password, PASSWORD_DEFAULT);
        $update = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
        $update->bind_param("si", $new_hash, $user_id);
        $update->execute();
        return true;
    }
    
    return false;
}

// Change password
function changePassword($conn, $user_id, $current_password, $new_password) {
    if (!verifyPassword($conn, $user_id, $current_password)) {
        return ['success' => false, 'error' => 'Current password is incorrect'];
    }
    
    if (strlen($new_password) < 6) {
        return ['success' => false, 'error' => 'Password must be at least 6 characters'];
    }
    
    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
    $update = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
    $update->bind_param("si", $hashed_password, $user_id);
    
    if ($update->execute()) {
        logActivity($user_id, 'changed password');
        return ['success' => true, 'message' => 'Password changed successfully'];
    }
    
    return ['success' => false, 'error' => 'Failed to change password'];
}
?>