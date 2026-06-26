<?php
require_once '../../config.php';
require_once '../../includes/auth.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['unread_count' => 0]);
    exit();
}

$user_id = $_SESSION['user_id'];

$query = $conn->prepare("
    SELECT COUNT(*) as count FROM messages 
    WHERE receiver_id = ? AND is_read = 0
");
$query->bind_param("i", $user_id);
$query->execute();
$result = $query->get_result();
$count = $result->fetch_assoc()['count'];

echo json_encode(['unread_count' => $count]);
?>