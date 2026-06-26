<?php
require_once '../config.php';
header('Content-Type: application/json');

$class_id = $_GET['class_id'] ?? 0;
$subjects = [];

if ($class_id) {
    $query = "SELECT s.id, s.name, s.code 
              FROM subjects s
              JOIN class_subject cs ON s.id = cs.subject_id
              WHERE cs.class_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $class_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $subjects[] = $row;
    }
}

echo json_encode(['subjects' => $subjects]);
?>