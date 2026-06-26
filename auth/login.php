<?php
require_once '../config.php';
require_once '../includes/auth.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'];
    $password = $_POST['password'];
    $remember = isset($_POST['remember']);

    $query = "SELECT * FROM users WHERE email = ? AND is_active = 1";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();
        
        // For demo, we'll use plain text password (you should use password_hash in production)
        // For now, let's create demo accounts
        if ($password === 'Admin@123' || $password === 'Teacher@123' || $password === 'Student@123' || $password === 'Parent@123' || password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_name'] = $user['first_name'] . ' ' . $user['last_name'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['email'] = $user['email'];

            // Update last login
            $update = "UPDATE users SET last_login = NOW() WHERE id = ?";
            $stmt = $conn->prepare($update);
            $stmt->bind_param("i", $user['id']);
            $stmt->execute();

            // Log activity
            logActivity($user['id'], 'login');

            // Redirect based on role
            switch($user['role']) {
                case 'admin': header('Location: ../admin/dashboard.php'); break;
                case 'academic': header('Location: ../academic/dashboard.php'); break;
                case 'teacher': header('Location: ../teacher/dashboard.php'); break;
                case 'student': header('Location: ../student/dashboard.php'); break;
                case 'parent': header('Location: ../parent/dashboard.php'); break;
                default: header('Location: ../index.php');
            }
            exit();
        } else {
            header('Location: ../index.php?error=Invalid password');
        }
    } else {
        header('Location: ../index.php?error=User not found or inactive');
    }
} else {
    header('Location: ../index.php');
}
?>