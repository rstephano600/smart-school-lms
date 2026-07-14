<?php
require_once '../../config.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';
requireRole('admin');

$user_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$action = isset($_GET['action']) ? $_GET['action'] : 'suspend';

if (!$user_id) {
    header('Location: index.php?error=No user specified');
    exit();
}

// Prevent deactivating own account
if ($user_id == $_SESSION['user_id']) {
    header('Location: index.php?error=You cannot deactivate your own account');
    exit();
}

if ($action == 'suspend') {
    $update = $conn->prepare("UPDATE users SET is_active = 0 WHERE id = ?");
    $update->bind_param("i", $user_id);
    $update->execute();
    logActivity($_SESSION['user_id'], 'suspended user', 'user', $user_id);
    header('Location: index.php?suspended=1');
    exit();
} elseif ($action == 'activate') {
    $update = $conn->prepare("UPDATE users SET is_active = 1 WHERE id = ?");
    $update->bind_param("i", $user_id);
    $update->execute();
    logActivity($_SESSION['user_id'], 'activated user', 'user', $user_id);
    header('Location: index.php?activated=1');
    exit();
} else {
    header('Location: index.php');
    exit();
}
?>