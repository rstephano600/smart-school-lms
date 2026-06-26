<?php
require_once '../../config.php';
require_once '../../includes/auth.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Not logged in']);
    exit();
}

// Get student ID
$student_query = $conn->prepare("SELECT id FROM students WHERE user_id = ?");
$student_query->bind_param("i", $_SESSION['user_id']);
$student_query->execute();
$student_id = $student_query->get_result()->fetch_assoc()['id'];

$exam_id = $_POST['exam_id'] ?? 0;
$question_id = $_POST['question_id'] ?? 0;
$answer = $_POST['answer'] ?? '';

if (!$exam_id || !$question_id) {
    echo json_encode(['success' => false, 'error' => 'Invalid data']);
    exit();
}

// Save or update answer
$query = $conn->prepare("
    INSERT INTO student_exam_answers (student_id, exam_id, question_id, answer, is_saved, saved_at)
    VALUES (?, ?, ?, ?, 1, NOW())
    ON DUPLICATE KEY UPDATE 
    answer = VALUES(answer), is_saved = 1, saved_at = NOW()
");
$query->bind_param("iiis", $student_id, $exam_id, $question_id, $answer);

if ($query->execute()) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'error' => $conn->error]);
}
?>