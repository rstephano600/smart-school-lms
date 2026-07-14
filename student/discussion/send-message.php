<?php
require_once '../../config.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';
requireRole('student');

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid request method']);
    exit();
}

$group_id = intval($_POST['group_id'] ?? 0);
$message = trim($_POST['message'] ?? '');

if (!$group_id || empty($message)) {
    echo json_encode(['success' => false, 'error' => 'Message cannot be empty!']);
    exit();
}

// Get student ID
$student_query = $conn->prepare("SELECT id, class_id FROM students WHERE user_id = ?");
$student_query->bind_param("i", $_SESSION['user_id']);
$student_query->execute();
$student = $student_query->get_result()->fetch_assoc();

if (!$student) {
    echo json_encode(['success' => false, 'error' => 'Student not found!']);
    exit();
}

$student_id = $student['id'];
$class_id = $student['class_id'];

// Verify group exists and is active
$verify = $conn->prepare("SELECT id, is_active FROM discussion_groups WHERE id = ? AND class_id = ?");
$verify->bind_param("ii", $group_id, $class_id);
$verify->execute();
$group = $verify->get_result()->fetch_assoc();

if (!$group) {
    echo json_encode(['success' => false, 'error' => 'Group not found!']);
    exit();
}

if (!$group['is_active']) {
    echo json_encode(['success' => false, 'error' => 'This group is currently inactive!']);
    exit();
}

// Check if student is a member
$member_check = $conn->prepare("SELECT id FROM group_members WHERE group_id = ? AND student_id = ?");
$member_check->bind_param("ii", $group_id, $student_id);
$member_check->execute();
if ($member_check->get_result()->num_rows === 0) {
    echo json_encode(['success' => false, 'error' => 'You must join this group to send messages!']);
    exit();
}

// Insert message
$insert = $conn->prepare("
    INSERT INTO group_messages (group_id, sender_id, message)
    VALUES (?, ?, ?)
");
$insert->bind_param("iis", $group_id, $_SESSION['user_id'], $message);

if ($insert->execute()) {
    $message_id = $conn->insert_id;
    
    echo json_encode([
        'success' => true,
        'message' => $message,
        'message_id' => $message_id,
        'time' => date('h:i A'),
        'user' => $_SESSION['user_name'] ?? 'Student'
    ]);
} else {
    echo json_encode(['success' => false, 'error' => 'Failed to send message. Please try again.']);
}

exit();
?>