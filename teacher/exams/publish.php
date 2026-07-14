<?php
require_once '../../config.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';
requireRole('teacher');

$exam_id = $_GET['id'] ?? 0;
$action = $_GET['action'] ?? 'publish';

if (!$exam_id) {
    header('Location: index.php');
    exit();
}

// Get teacher ID
$teacher_query = $conn->prepare("SELECT id FROM teachers WHERE user_id = ?");
$teacher_query->bind_param("i", $_SESSION['user_id']);
$teacher_query->execute();
$teacher = $teacher_query->get_result()->fetch_assoc();
$teacher_id = $teacher['id'] ?? 0;

// Get exam details first
$exam_query = $conn->prepare("SELECT * FROM teacher_exams WHERE id = ? AND teacher_id = ?");
$exam_query->bind_param("ii", $exam_id, $teacher_id);
$exam_query->execute();
$exam = $exam_query->get_result()->fetch_assoc();

if (!$exam) {
    header('Location: index.php?error=Exam not found');
    exit();
}

// Check if exam has questions
$question_check = $conn->prepare("SELECT COUNT(*) as count FROM exam_questions WHERE exam_id = ?");
$question_check->bind_param("i", $exam_id);
$question_check->execute();
$question_count = $question_check->get_result()->fetch_assoc()['count'];

if ($action == 'publish') {
    if ($question_count == 0) {
        header("Location: questions.php?id=$exam_id&error=Please add questions before publishing");
        exit();
    }
    
    $update = $conn->prepare("UPDATE teacher_exams SET is_published = 1, is_active = 1 WHERE id = ? AND teacher_id = ?");
    $update->bind_param("ii", $exam_id, $teacher_id);
    
    if ($update->execute()) {
        // Create notifications for students in this class
        $notify = $conn->prepare("
            INSERT INTO notifications (user_id, title, message, type, link)
            SELECT u.id, 'New Exam Available', CONCAT('A new exam \"', ?, '\" has been published. Due date: ', DATE_FORMAT(?, '%M %d, %Y')), 'exam', ?
            FROM students s
            JOIN users u ON s.user_id = u.id
            WHERE s.class_id = ?
        ");
        // FIXED: Use SITE_URL instead of BASE_URL
        $link = SITE_URL . "student/exams/take.php?id=" . $exam_id;
        $notify->bind_param("sssi", $exam['title'], $exam['end_date'], $link, $exam['class_id']);
        $notify->execute();
        
        logActivity($_SESSION['user_id'], 'published exam', 'teacher_exams', $exam_id);
        header("Location: index.php?published=1");
        exit();
    } else {
        header("Location: index.php?error=Failed to publish exam");
        exit();
    }
} elseif ($action == 'unpublish') {
    $update = $conn->prepare("UPDATE teacher_exams SET is_published = 0, is_active = 0 WHERE id = ? AND teacher_id = ?");
    $update->bind_param("ii", $exam_id, $teacher_id);
    $update->execute();
    header("Location: index.php?success=Exam unpublished");
    exit();
} elseif ($action == 'close') {
    $update = $conn->prepare("UPDATE teacher_exams SET is_active = 0 WHERE id = ? AND teacher_id = ?");
    $update->bind_param("ii", $exam_id, $teacher_id);
    $update->execute();
    header("Location: index.php?success=Exam closed");
    exit();
}

exit();
?>