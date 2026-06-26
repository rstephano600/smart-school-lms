<?php
require_once '../../config.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Not logged in']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid request']);
    exit();
}

$receiver_id = isset($_POST['receiver_id']) ? intval($_POST['receiver_id']) : 0;
$message = isset($_POST['message']) ? trim($_POST['message']) : '';

if ($receiver_id == 0) {
    echo json_encode(['success' => false, 'error' => 'Invalid receiver']);
    exit();
}

if (empty($message)) {
    echo json_encode(['success' => false, 'error' => 'Message cannot be empty']);
    exit();
}

$sender_id = $_SESSION['user_id'];

// Insert message
$insert = $conn->prepare("
    INSERT INTO messages (sender_id, receiver_id, message, created_at) 
    VALUES (?, ?, ?, NOW())
");
$insert->bind_param("iis", $sender_id, $receiver_id, $message);

if ($insert->execute()) {
    // Create notification for receiver
    $sender_name = $_SESSION['user_name'] ?? 'Student';
    $short_message = strlen($message) > 50 ? substr($message, 0, 50) . '...' : $message;
    
    $notify = $conn->prepare("
        INSERT INTO notifications (user_id, title, message, type, link, created_at) 
        VALUES (?, 'New Message', CONCAT(?, ': ', ?), 'message', ?, NOW())
    ");
    $link = "/smart-school-lms/teacher/chat/index.php";
    $notify->bind_param("isss", $receiver_id, $sender_name, $short_message, $link);
    $notify->execute();
    
    echo json_encode([
        'success' => true,
        'message_id' => $conn->insert_id,
        'message' => $message,
        'time' => date('h:i A')
    ]);
} else {
    echo json_encode(['success' => false, 'error' => 'Failed to send message']);
}
?>