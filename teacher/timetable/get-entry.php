<?php
require_once '../../config.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';
requireRole('teacher');

header('Content-Type: application/json');

$id = intval($_GET['id'] ?? 0);

if (!$id) {
    echo json_encode(['error' => 'Invalid ID']);
    exit();
}

// Get teacher ID
$teacher_query = $conn->prepare("SELECT id FROM teachers WHERE user_id = ?");
$teacher_query->bind_param("i", $_SESSION['user_id']);
$teacher_query->execute();
$teacher = $teacher_query->get_result()->fetch_assoc();
$teacher_id = $teacher['id'] ?? 0;

$query = $conn->prepare("SELECT * FROM timetable WHERE id = ? AND teacher_id = ?");
$query->bind_param("ii", $id, $teacher_id);
$query->execute();
$result = $query->get_result()->fetch_assoc();

if ($result) {
    echo json_encode($result);
} else {
    echo json_encode(['error' => 'Entry not found']);
}
?>