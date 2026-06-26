<?php
require_once '../config.php';
session_start();

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['html' => '<p class="text-red-500">Please login first</p>']);
    exit();
}

$query = isset($_GET['q']) ? $_GET['q'] : '';
$html = '';

if (strlen($query) > 2) {
    // Search users
    $search = "%{$query}%";
    $sql = "SELECT id, first_name, last_name, email, role FROM users 
            WHERE first_name LIKE ? OR last_name LIKE ? OR email LIKE ? 
            LIMIT 10";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sss", $search, $search, $search);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $html .= '<div class="space-y-2">';
        while ($row = $result->fetch_assoc()) {
            $html .= '<a href="#" class="block p-2 hover:bg-gray-50 rounded-lg">';
            $html .= '<p class="font-semibold">' . htmlspecialchars($row['first_name'] . ' ' . $row['last_name']) . '</p>';
            $html .= '<p class="text-xs text-gray-500">' . htmlspecialchars($row['email']) . ' (' . $row['role'] . ')</p>';
            $html .= '</a>';
        }
        $html .= '</div>';
    } else {
        $html = '<p class="text-gray-500 text-center py-4">No results found</p>';
    }
}

echo json_encode(['html' => $html]);
?>