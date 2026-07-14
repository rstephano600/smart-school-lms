<?php
require_once '../config.php';
require_once '../includes/auth.php';

// ============================================
// STUDENT TRACKING - Update session on logout
// ============================================
if (isLoggedIn()) {
    $user_id = $_SESSION['user_id'];
    $user_role = $_SESSION['role'] ?? '';
    
    // Log activity
    logActivity($user_id, 'logout');
    
    // If student, update session end time
    if ($user_role == 'student') {
        // Get student ID
        $student_id_query = $conn->prepare("SELECT id FROM students WHERE user_id = ?");
        $student_id_query->bind_param("i", $user_id);
        $student_id_query->execute();
        $student = $student_id_query->get_result()->fetch_assoc();
        
        if ($student) {
            // Update last active session
            $session_update = $conn->prepare("
                UPDATE student_sessions 
                SET session_end = NOW(), 
                    duration = TIMESTAMPDIFF(MINUTE, session_start, NOW())
                WHERE student_id = ? AND session_end IS NULL
                ORDER BY session_start DESC LIMIT 1
            ");
            $session_update->bind_param("i", $student['id']);
            $session_update->execute();
            
            // Calculate total time and update user
            $total_time_query = $conn->prepare("
                SELECT SUM(duration) as total_time 
                FROM student_sessions 
                WHERE student_id = ?
            ");
            $total_time_query->bind_param("i", $student['id']);
            $total_time_query->execute();
            $total_time = $total_time_query->get_result()->fetch_assoc()['total_time'] ?? 0;
            
            $update_user = $conn->prepare("
                UPDATE users 
                SET total_time_online = ? 
                WHERE id = ?
            ");
            $update_user->bind_param("ii", $total_time, $user_id);
            $update_user->execute();
            
            // Update online status to offline
            $online_query = $conn->prepare("
                UPDATE user_online_status 
                SET status = 'offline', last_activity = NOW()
                WHERE user_id = ?
            ");
            $online_query->bind_param("i", $user_id);
            $online_query->execute();
        }
    }
}

// Destroy session
session_destroy();

// Clear session cookie
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

header('Location: ../index.php?success=Logged out successfully');
exit();
?>