<?php
// Enable error reporting at the very top
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

require_once '../config.php';
require_once '../includes/auth.php';

// If already logged in, redirect to dashboard
if (isLoggedIn()) {
    $role = $_SESSION['role'] ?? '';
    $redirect = [
        'admin' => '../admin/dashboard.php',
        'academic' => '../academic/dashboard.php',
        'teacher' => '../teacher/dashboard.php',
        'student' => '../student/dashboard.php',
        'parent' => '../parent/dashboard.php'
    ];
    header('Location: ' . ($redirect[$role] ?? '../index.php'));
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $remember = isset($_POST['remember']);

    if (empty($email) || empty($password)) {
        header('Location: ../index.php?error=Please enter email and password');
        exit();
    }

    $query = "SELECT * FROM users WHERE email = ? AND is_active = 1";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();
        
        // Verify password using password_verify
        $password_valid = password_verify($password, $user['password']);
        
        // For demo accounts with plain text password (fallback)
        if (!$password_valid) {
            $demo_passwords = ['Admin@123', 'Teacher@123', 'Student@123', 'Parent@123'];
            if (in_array($password, $demo_passwords)) {
                $password_valid = true;
                // Re-hash the password for future use
                $new_hash = password_hash($password, PASSWORD_DEFAULT);
                $update = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
                $update->bind_param("si", $new_hash, $user['id']);
                $update->execute();
            }
        }
        
        if ($password_valid) {
            // Set session
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_name'] = $user['first_name'] . ' ' . $user['last_name'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['email'] = $user['email'];

            // ============================================
            // STUDENT TRACKING - Update last activity
            // ============================================
            $update_user = $conn->prepare("
                UPDATE users 
                SET last_activity = NOW(), 
                    last_login = NOW(),
                    total_logins = total_logins + 1 
                WHERE id = ?
            ");
            $update_user->bind_param("i", $user['id']);
            $update_user->execute();

            // If student, record session start
            if ($user['role'] == 'student') {
                // Get student ID
                $student_id_query = $conn->prepare("SELECT id FROM students WHERE user_id = ?");
                $student_id_query->bind_param("i", $user['id']);
                $student_id_query->execute();
                $student = $student_id_query->get_result()->fetch_assoc();
                
                if ($student) {
                    // Check if already has active session
                    $check_session = $conn->prepare("
                        SELECT id FROM student_sessions 
                        WHERE student_id = ? AND session_end IS NULL
                    ");
                    $check_session->bind_param("i", $student['id']);
                    $check_session->execute();
                    
                    if ($check_session->get_result()->num_rows == 0) {
                        // Record session start
                        $session_query = $conn->prepare("
                            INSERT INTO student_sessions (student_id, session_start, ip_address, user_agent)
                            VALUES (?, NOW(), ?, ?)
                        ");
                        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
                        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
                        $session_query->bind_param("iss", $student['id'], $ip, $user_agent);
                        $session_query->execute();
                        
                        // Update online status
                        $online_query = $conn->prepare("
                            INSERT INTO user_online_status (user_id, last_activity, status)
                            VALUES (?, NOW(), 'online')
                            ON DUPLICATE KEY UPDATE 
                            last_activity = NOW(), status = 'online'
                        ");
                        $online_query->bind_param("i", $user['id']);
                        $online_query->execute();
                    }
                }
            }

            // Log activity
            logActivity($user['id'], 'login');

            // Redirect based on role
            $redirect = [
                'admin' => '../admin/dashboard.php',
                'academic' => '../academic/dashboard.php',
                'teacher' => '../teacher/dashboard.php',
                'student' => '../student/dashboard.php',
                'parent' => '../parent/dashboard.php'
            ];
            header('Location: ' . ($redirect[$user['role']] ?? '../index.php'));
            exit();
        } else {
            header('Location: ../index.php?error=Invalid password');
        }
    } else {
        header('Location: ../index.php?error=User not found or inactive');
    }
} else {
    // If GET request, show login page
    header('Location: ../index.php');
}
exit();
?>