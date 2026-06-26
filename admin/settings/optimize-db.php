<?php
header('Content-Type: application/json');
require_once '../../config.php';
require_once '../../includes/auth.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

$tables = [];
$result = $conn->query("SHOW TABLES");
while ($row = $result->fetch_row()) {
    $conn->query("OPTIMIZE TABLE " . $row[0]);
}

echo json_encode(['success' => true]);
?>