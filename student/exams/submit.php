<?php
require_once '../../config.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';
requireRole('student');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit();
}

$exam_id = intval($_POST['exam_id'] ?? 0);
$answers = $_POST['answers'] ?? [];
$time_taken = intval($_POST['time_taken'] ?? 0);

if (!$exam_id) {
    header('Location: index.php?error=Invalid exam');
    exit();
}

// Get student ID
$student_query = $conn->prepare("SELECT id FROM students WHERE user_id = ?");
$student_query->bind_param("i", $_SESSION['user_id']);
$student_query->execute();
$student = $student_query->get_result()->fetch_assoc();
$student_id = $student['id'] ?? 0;

if (!$student_id) {
    header('Location: ../index.php?error=Student not found');
    exit();
}

// Check if already submitted
$check = $conn->prepare("SELECT id FROM exam_submissions WHERE exam_id = ? AND student_id = ?");
$check->bind_param("ii", $exam_id, $student_id);
$check->execute();
$existing = $check->get_result()->fetch_assoc();

if ($existing) {
    header("Location: results.php?id=" . $exam_id);
    exit();
}

// Get exam details
$exam_query = $conn->prepare("
    SELECT * FROM teacher_exams 
    WHERE id = ? AND is_published = 1
");
$exam_query->bind_param("i", $exam_id);
$exam_query->execute();
$exam = $exam_query->get_result()->fetch_assoc();

if (!$exam) {
    header('Location: index.php?error=Exam not found');
    exit();
}

// Prepare answers JSON
$answers_json = json_encode($answers);

// Insert submission
$insert = $conn->prepare("
    INSERT INTO exam_submissions (
        exam_id, 
        student_id, 
        answers, 
        started_at, 
        submitted_at,
        time_taken
    ) VALUES (?, ?, ?, NOW(), NOW(), ?)
");
$insert->bind_param("iisi", $exam_id, $student_id, $answers_json, $time_taken);

if ($insert->execute()) {
    $submission_id = $conn->insert_id;
    
    // ✅ AUTO-GRADE
    if (function_exists('autoGradeExam')) {
        $grade_result = autoGradeExam($submission_id);
    }
    
    header("Location: results.php?id=" . $exam_id . "&submitted=1");
    exit();
} else {
    header("Location: take.php?id=" . $exam_id . "&error=Failed to submit");
    exit();
}
?>