<?php
require_once '../../config.php';
require_once '../../includes/auth.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false]);
    exit();
}

$session_id = $_POST['session_id'] ?? 0;
$action = $_POST['action'] ?? '';

if (!$session_id) {
    echo json_encode(['success' => false]);
    exit();
}

// Log tab switch
if ($action == 'tab_switch') {
    $update = $conn->prepare("
        UPDATE student_exam_sessions 
        SET suspicious_activities = JSON_ARRAY_APPEND(
            IFNULL(suspicious_activities, JSON_ARRAY()), 
            '$', 
            JSON_OBJECT('type', 'tab_switch', 'time', NOW())
        )
        WHERE id = ?
    ");
    $update->bind_param("i", $session_id);
    $update->execute();
}

// Update last activity
if ($action == 'heartbeat') {
    $update = $conn->prepare("
        UPDATE student_exam_sessions 
        SET last_activity = NOW() 
        WHERE id = ?
    ");
    $update->bind_param("i", $session_id);
    $update->execute();
}

echo json_encode(['success' => true]);
?>