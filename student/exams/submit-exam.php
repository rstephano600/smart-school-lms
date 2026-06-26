<?php
require_once '../../config.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';
requireRole('student');

// Get student ID
$student_query = $conn->prepare("SELECT id FROM students WHERE user_id = ?");
$student_query->bind_param("i", $_SESSION['user_id']);
$student_query->execute();
$student_id = $student_query->get_result()->fetch_assoc()['id'];

$exam_id = $_POST['exam_id'] ?? 0;
$answers = $_POST['answer'] ?? [];

if (!$exam_id) {
    header('Location: index.php');
    exit();
}

// Get exam details
$exam_query = $conn->prepare("
    SELECT te.* FROM teacher_exams te WHERE te.id = ?
");
$exam_query->bind_param("i", $exam_id);
$exam_query->execute();
$exam = $exam_query->get_result()->fetch_assoc();

// Get all questions with correct answers for auto-grading
$questions = $conn->prepare("
    SELECT * FROM exam_questions WHERE exam_id = ?
");
$questions->bind_param("i", $exam_id);
$questions->execute();
$questions = $questions->get_result();

// Calculate score
$total_score = 0;
$total_possible = 0;
$answers_data = [];

while ($q = $questions->fetch_assoc()) {
    $total_possible += $q['marks'];
    $student_answer = $answers[$q['id']] ?? '';
    $answers_data[$q['id']] = $student_answer;
    
    // Auto-grade for MCQ and True/False
    if (in_array($q['question_type'], ['mcq', 'truefalse'])) {
        if (strtolower(trim($student_answer)) == strtolower(trim($q['correct_answer']))) {
            $total_score += $q['marks'];
        }
    }
}

$percentage = $total_possible > 0 ? ($total_score / $total_possible) * 100 : 0;

// Calculate grade based on level
$level_query = $conn->prepare("
    SELECT CASE 
        WHEN c.name LIKE 'Form 5%' OR c.name LIKE 'Form 6%' THEN 'alevel'
        ELSE 'olevel'
    END as level
    FROM students s
    JOIN classes c ON s.class_id = c.id
    WHERE s.id = ?
");
$level_query->bind_param("i", $student_id);
$level_query->execute();
$level = $level_query->get_result()->fetch_assoc()['level'] ?? 'olevel';

if ($level == 'olevel') {
    if ($percentage >= 75) $grade = 'A';
    elseif ($percentage >= 65) $grade = 'B';
    elseif ($percentage >= 45) $grade = 'C';
    elseif ($percentage >= 30) $grade = 'D';
    else $grade = 'F';
} else {
    if ($percentage >= 80) $grade = 'A';
    elseif ($percentage >= 70) $grade = 'B';
    elseif ($percentage >= 60) $grade = 'C';
    elseif ($percentage >= 50) $grade = 'D';
    elseif ($percentage >= 40) $grade = 'E';
    elseif ($percentage >= 35) $grade = 'S';
    else $grade = 'F';
}

// Save submission
$submit = $conn->prepare("
    INSERT INTO exam_submissions (exam_id, student_id, answers, total_score, percentage, grade, submitted_at, ip_address)
    VALUES (?, ?, ?, ?, ?, ?, NOW(), ?)
");
$answers_json = json_encode($answers_data);
$ip = $_SERVER['REMOTE_ADDR'];
$submit->bind_param("iisddss", $exam_id, $student_id, $answers_json, $total_score, $percentage, $grade, $ip);
$submit->execute();

// Create notification
$notify = $conn->prepare("
    INSERT INTO notifications (user_id, title, message, type, link)
    VALUES (?, 'Exam Submitted', CONCAT('You have submitted \"', ?, '\". Your score: ', ?, '/', ?, ' (', ?, '%)'), 'exam', ?)
");
$link = "/smart-school-lms/student/exams/results.php?id=" . $exam_id;
$notify->bind_param("issdds", $_SESSION['user_id'], $exam['title'], $total_score, $total_possible, round($percentage, 1), $link);
$notify->execute();

// Redirect to results page
header("Location: results.php?id=$exam_id&submitted=1");
exit();
?>