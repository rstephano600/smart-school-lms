<?php
require_once '../../config.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Not logged in']);
    exit();
}

// Check if request is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid request method']);
    exit();
}

// Get and validate inputs
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
    $message_id = $conn->insert_id;
    
    // Update or create conversation
    $conv_check = $conn->prepare("
        SELECT id FROM chat_conversations 
        WHERE (participant1_id = ? AND participant2_id = ?) OR (participant1_id = ? AND participant2_id = ?)
    ");
    $conv_check->bind_param("iiii", $sender_id, $receiver_id, $receiver_id, $sender_id);
    $conv_check->execute();
    
    if ($conv_check->get_result()->num_rows > 0) {
        // Update existing conversation
        $update_conv = $conn->prepare("
            UPDATE chat_conversations 
            SET last_message = ?, last_message_time = NOW() 
            WHERE (participant1_id = ? AND participant2_id = ?) OR (participant1_id = ? AND participant2_id = ?)
        ");
        $update_conv->bind_param("siii", $message, $sender_id, $receiver_id, $receiver_id, $sender_id);
        $update_conv->execute();
    } else {
        // Create new conversation
        $insert_conv = $conn->prepare("
            INSERT INTO chat_conversations (participant1_id, participant2_id, last_message, last_message_time) 
            VALUES (?, ?, ?, NOW())
        ");
        $insert_conv->bind_param("iis", $sender_id, $receiver_id, $message);
        $insert_conv->execute();
    }
    
    // Create notification for receiver
    $sender_name = $_SESSION['user_name'] ?? 'Teacher';
    $short_message = strlen($message) > 50 ? substr($message, 0, 50) . '...' : $message;
    
    $notify = $conn->prepare("
        INSERT INTO notifications (user_id, title, message, type, link, created_at) 
        VALUES (?, 'New Message', CONCAT(?, ': ', ?), 'message', ?, NOW())
    ");
    $link = "/smart-school-lms/chat.php";
    $notify->bind_param("isss", $receiver_id, $sender_name, $short_message, $link);
    $notify->execute();
    
    echo json_encode([
        'success' => true, 
        'message_id' => $message_id,
        'message' => $message,
        'time' => date('h:i A')
    ]);
} else {
    echo json_encode(['success' => false, 'error' => 'Failed to send message: ' . $conn->error]);
}
?>