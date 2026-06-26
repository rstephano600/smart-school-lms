<?php
require_once '../../config.php';
require_once '../../includes/auth.php';

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Not logged in']);
    exit();
}

$user_id = isset($_GET['user']) ? intval($_GET['user']) : 0;
$last_id = isset($_GET['last_id']) ? intval($_GET['last_id']) : 0;

if ($user_id == 0) {
    echo json_encode(['success' => false, 'error' => 'No user specified']);
    exit();
}

$current_user = $_SESSION['user_id'];

// Get new messages
$query = $conn->prepare("
    SELECT id, sender_id, receiver_id, message, created_at, is_read
    FROM messages 
    WHERE ((sender_id = ? AND receiver_id = ?) OR (sender_id = ? AND receiver_id = ?)) AND id > ?
    ORDER BY created_at ASC
");
$query->bind_param("iiiii", $current_user, $user_id, $user_id, $current_user, $last_id);
$query->execute();
$result = $query->get_result();

$messages = [];
while ($row = $result->fetch_assoc()) {
    $messages[] = [
        'id' => $row['id'],
        'sender_id' => $row['sender_id'],
        'receiver_id' => $row['receiver_id'],
        'message' => $row['message'],
        'time' => date('h:i A', strtotime($row['created_at'])),
        'is_read' => $row['is_read']
    ];
}

// Mark messages as read if user is receiver
if (!empty($messages)) {
    $update = $conn->prepare("
        UPDATE messages SET is_read = 1, read_at = NOW() 
        WHERE sender_id = ? AND receiver_id = ? AND is_read = 0
    ");
    $update->bind_param("ii", $user_id, $current_user);
    $update->execute();
}

echo json_encode(['success' => true, 'messages' => $messages]);
?>