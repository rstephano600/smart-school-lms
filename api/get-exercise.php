<?php
require_once '../config.php';
require_once '../includes/auth.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Not logged in']);
    exit();
}

$exercise_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if (!$exercise_id) {
    echo json_encode(['success' => false, 'error' => 'Exercise ID required']);
    exit();
}

$query = $conn->prepare("SELECT * FROM coding_exercises WHERE id = ?");
$query->bind_param("i", $exercise_id);
$query->execute();
$exercise = $query->get_result()->fetch_assoc();

if (!$exercise) {
    echo json_encode(['success' => false, 'error' => 'Exercise not found']);
    exit();
}

// Decode test cases
$test_cases = json_decode($exercise['test_cases'], true);

echo json_encode([
    'success' => true,
    'title' => $exercise['title'],
    'description' => $exercise['description'],
    'language' => $exercise['language'],
    'starter_code' => $exercise['starter_code'],
    'solution_code' => $exercise['solution_code'],
    'test_cases' => $test_cases,
    'difficulty' => $exercise['difficulty']
]);
exit();
?>