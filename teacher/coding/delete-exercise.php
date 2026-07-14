<?php
require_once '../../config.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';
requireRole('teacher');

$exercise_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if (!$exercise_id) {
    header('Location: exercises.php');
    exit();
}

$delete = $conn->prepare("DELETE FROM coding_exercises WHERE id = ?");
$delete->bind_param("i", $exercise_id);
$delete->execute();

header('Location: exercises.php?deleted=1');
exit();
?>