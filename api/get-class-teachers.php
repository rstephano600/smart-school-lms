<?php
require_once '../config.php';
header('Content-Type: application/json');

$class_id = $_GET['class_id'] ?? 0;
$teachers = [];

if ($class_id) {
    $query = "SELECT DISTINCT t.id, CONCAT(u.first_name, ' ', u.last_name) as name
              FROM teachers t
              JOIN users u ON t.user_id = u.id
              JOIN class_subject cs ON t.id = cs.teacher_id
              WHERE cs.class_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $class_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $teachers[] = $row;
    }
}

echo json_encode(['teachers' => $teachers]);
?>