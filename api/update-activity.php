<?php
require_once '../config.php';
require_once '../includes/auth.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Not logged in']);
    exit();
}

$user_id = $_SESSION['user_id'];

// Update user last activity
$update = $conn->prepare("
    UPDATE users 
    SET last_activity = NOW() 
    WHERE id = ?
");
$update->bind_param("i", $user_id);
$update->execute();

// Update online status
$online = $conn->prepare("
    INSERT INTO user_online_status (user_id, last_activity, status)
    VALUES (?, NOW(), 'online')
    ON DUPLICATE KEY UPDATE 
    last_activity = NOW(), status = 'online'
");
$online->bind_param("i", $user_id);
$online->execute();

echo json_encode(['success' => true]);
?>