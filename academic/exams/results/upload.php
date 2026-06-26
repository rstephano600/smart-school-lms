<?php
require_once '../../../config.php';
require_once '../../../includes/auth.php';
require_once '../../../includes/functions.php';
requireRole('academic');

$exam_id = $_GET['exam_id'] ?? '';
$error = '';
$success = '';

// Function to get grade based on level
function getGradeByLevel($percentage, $level) {
    if ($level == 'olevel') {
        if ($percentage >= 75) return ['grade' => 'A', 'points' => 5, 'remarks' => 'Excellent'];
        elseif ($percentage >= 65) return ['grade' => 'B', 'points' => 4, 'remarks' => 'Very Good'];
        elseif ($percentage >= 45) return ['grade' => 'C', 'points' => 3, 'remarks' => 'Good'];
        elseif ($percentage >= 30) return ['grade' => 'D', 'points' => 2, 'remarks' => 'Satisfactory'];
        else return ['grade' => 'F', 'points' => 0, 'remarks' => 'Fail'];
    } else {
        if ($percentage >= 80) return ['grade' => 'A', 'points' => 5, 'remarks' => 'Excellent'];
        elseif ($percentage >= 70) return ['grade' => 'B', 'points' => 4, 'remarks' => 'Very Good'];
        elseif ($percentage >= 60) return ['grade' => 'C', 'points' => 3, 'remarks' => 'Good'];
        elseif ($percentage >= 50) return ['grade' => 'D', 'points' => 2, 'remarks' => 'Satisfactory'];
        elseif ($percentage >= 40) return ['grade' => 'E', 'points' => 1, 'remarks' => 'Average'];
        elseif ($percentage >= 35) return ['grade' => 'S', 'points' => 0.5, 'remarks' => 'Satisfactory'];
        else return ['grade' => 'F', 'points' => 0, 'remarks' => 'Fail'];
    }
}

// Get exams
$exams = $conn->query("SELECT id, name, term, year FROM exams ORDER BY year DESC, term DESC");

// Get student's class level function
function getStudentLevel($conn, $student_id) {
    $query = $conn->prepare("
        SELECT CASE 
            WHEN c.name LIKE 'Form 5%' OR c.name LIKE 'Form 6%' OR c.name LIKE 'F5%' OR c.name LIKE 'F6%' THEN 'alevel'
            ELSE 'olevel'
        END as level
        FROM students s
        JOIN classes c ON s.class_id = c.id
        WHERE s.id = ?
    ");
    $query->bind_param("i", $student_id);
    $query->execute();
    $result = $query->get_result();
    $row = $result->fetch_assoc();
    return $row['level'] ?? 'olevel';
}

// Rest of the upload results code with updated grading...
// (Continue with the existing upload.php but use the new grading function)

// When saving marks, calculate grade using:
// $level = getStudentLevel($conn, $student_id);
// $grade_info = getGradeByLevel($percentage, $level);
// $grade = $grade_info['grade'];
// $points = $grade_info['points'];
// $remarks = $grade_info['remarks'];