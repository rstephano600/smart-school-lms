<?php
// Disable error reporting for clean JSON
error_reporting(0);
ini_set('display_errors', 0);

require_once '../../config.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

// Clear any previous output
ob_clean();

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

// Check if receiver exists
$check_user = $conn->prepare("SELECT id, role FROM users WHERE id = ?");
$check_user->bind_param("i", $receiver_id);
$check_user->execute();
$receiver = $check_user->get_result()->fetch_assoc();

if (!$receiver) {
    echo json_encode(['success' => false, 'error' => 'Receiver not found']);
    exit();
}

// Insert message
$insert = $conn->prepare("
    INSERT INTO messages (sender_id, receiver_id, message, created_at) 
    VALUES (?, ?, ?, NOW())
");
$insert->bind_param("iis", $sender_id, $receiver_id, $message);

if ($insert->execute()) {
    $message_id = $conn->insert_id;
    
    // Update conversation
    $conv_check = $conn->prepare("
        SELECT id FROM chat_conversations 
        WHERE (participant1_id = ? AND participant2_id = ?) OR (participant1_id = ? AND participant2_id = ?)
    ");
    $conv_check->bind_param("iiii", $sender_id, $receiver_id, $receiver_id, $sender_id);
    $conv_check->execute();
    $conv_result = $conv_check->get_result();
    
    if ($conv_result->num_rows > 0) {
        $update_conv = $conn->prepare("
            UPDATE chat_conversations 
            SET last_message = ?, last_message_time = NOW() 
            WHERE (participant1_id = ? AND participant2_id = ?) OR (participant1_id = ? AND participant2_id = ?)
        ");
        $update_conv->bind_param("siiii", $message, $sender_id, $receiver_id, $receiver_id, $sender_id);
        $update_conv->execute();
    } else {
        $insert_conv = $conn->prepare("
            INSERT INTO chat_conversations (participant1_id, participant2_id, last_message, last_message_time) 
            VALUES (?, ?, ?, NOW())
        ");
        $insert_conv->bind_param("iis", $sender_id, $receiver_id, $message);
        $insert_conv->execute();
    }
    
    // Get sender name
    $name_query = $conn->prepare("SELECT first_name, last_name FROM users WHERE id = ?");
    $name_query->bind_param("i", $sender_id);
    $name_query->execute();
    $sender = $name_query->get_result()->fetch_assoc();
    $sender_name = $sender['first_name'] . ' ' . $sender['last_name'];
    
    // Create notification
    $short_message = strlen($message) > 50 ? substr($message, 0, 50) . '...' : $message;
    
    $notify = $conn->prepare("
        INSERT INTO notifications (user_id, title, message, type, link, created_at) 
        VALUES (?, 'New Message', CONCAT(?, ': ', ?), 'message', ?, NOW())
    ");
    $link = '/smart-school-lms/teacher/chat/index.php';
    $notify->bind_param("isss", $receiver_id, $sender_name, $short_message, $link);
    $notify->execute();
    
    echo json_encode([
        'success' => true,
        'message_id' => $message_id,
        'message' => $message,
        'time' => date('h:i A')
    ]);
} else {
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $conn->error]);
}
exit();
?>