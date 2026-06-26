<?php
require_once '../config.php';
require_once '../includes/auth.php';

if (isset($_SESSION['user_id'])) {
    logActivity($_SESSION['user_id'], 'logout');
}

session_destroy();
header('Location: ../index.php?success=Logged out successfully');
exit();
?>