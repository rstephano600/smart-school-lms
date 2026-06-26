<?php
require_once '../../config.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';
requireRole('teacher');

$material_id = $_GET['id'] ?? 0;

if ($material_id) {
    // Get file path first
    $query = $conn->prepare("SELECT file_url FROM learning_materials WHERE id = ? AND uploaded_by = ?");
    $query->bind_param("ii", $material_id, $_SESSION['user_id']);
    $query->execute();
    $material = $query->get_result()->fetch_assoc();
    
    if ($material) {
        // Delete file from server
        $file_path = '../../' . $material['file_url'];
        if (file_exists($file_path)) {
            unlink($file_path);
        }
        
        // Delete from database
        $delete = $conn->prepare("DELETE FROM learning_materials WHERE id = ?");
        $delete->bind_param("i", $material_id);
        $delete->execute();
        
        logActivity($_SESSION['user_id'], 'deleted learning material', 'learning_materials', $material_id);
    }
}

header('Location: index.php');
exit();
?>