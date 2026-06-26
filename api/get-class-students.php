<?php
require_once '../config.php';
header('Content-Type: application/json');

$class_id = $_GET['class_id'] ?? 0;
$students = [];

if ($class_id) {
    $query = "SELECT s.id, s.admission_number, CONCAT(u.first_name, ' ', u.last_name) as name
              FROM students s
              JOIN users u ON s.user_id = u.id
              WHERE s.class_id = ?
              ORDER BY u.first_name";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $class_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $students[] = $row;
    }
}

echo json_encode(['students' => $students]);
?>