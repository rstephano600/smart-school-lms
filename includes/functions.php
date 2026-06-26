<?php
// includes/functions.php - Full working code

function sanitizeInput($input) {
    return htmlspecialchars(strip_tags(trim($input)));
}

function generateRandomPassword($length = 10) {
    return substr(str_shuffle('abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%'), 0, $length);
}

function getTimeAgo($timestamp) {
    if (empty($timestamp)) return 'Just now';
    
    $time_ago = strtotime($timestamp);
    $current_time = time();
    $time_difference = $current_time - $time_ago;
    $seconds = $time_difference;
    
    $minutes = round($seconds / 60);
    $hours = round($seconds / 3600);
    $days = round($seconds / 86400);
    $weeks = round($seconds / 604800);
    $months = round($seconds / 2629440);
    $years = round($seconds / 31553280);
    
    if ($seconds <= 60) {
        return "Just Now";
    } else if ($minutes <= 60) {
        return ($minutes == 1) ? "1 minute ago" : "$minutes minutes ago";
    } else if ($hours <= 24) {
        return ($hours == 1) ? "1 hour ago" : "$hours hours ago";
    } else if ($days <= 7) {
        return ($days == 1) ? "Yesterday" : "$days days ago";
    } else if ($weeks <= 4.3) {
        return ($weeks == 1) ? "1 week ago" : "$weeks weeks ago";
    } else if ($months <= 12) {
        return ($months == 1) ? "1 month ago" : "$months months ago";
    } else {
        return ($years == 1) ? "1 year ago" : "$years years ago";
    }
}

function getNotificationCount($user_id) {
    global $conn;
    
    // Check if connection exists
    if (!isset($conn) || $conn->connect_error) {
        return 0;
    }
    
    $query = "SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = 0";
    $stmt = $conn->prepare($query);
    if (!$stmt) {
        return 0;
    }
    
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $data = $result->fetch_assoc();
    $stmt->close();
    
    return $data['count'] ?? 0;
}

function getUnreadMessagesCount($user_id) {
    global $conn;
    
    // Check if connection exists
    if (!isset($conn) || $conn->connect_error) {
        return 0;
    }
    
    $query = "SELECT COUNT(*) as count FROM messages WHERE receiver_id = ? AND is_read = 0";
    $stmt = $conn->prepare($query);
    if (!$stmt) {
        return 0;
    }
    
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $data = $result->fetch_assoc();
    $stmt->close();
    
    return $data['count'] ?? 0;
}

function uploadFile($file, $target_dir, $allowed_types = ['pdf', 'docx', 'jpg', 'png', 'mp4', 'pptx', 'txt', 'zip']) {
    // Check if file was uploaded without error
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $errors = [
            UPLOAD_ERR_INI_SIZE => 'File is too large (server limit)',
            UPLOAD_ERR_FORM_SIZE => 'File is too large (form limit)',
            UPLOAD_ERR_PARTIAL => 'File was only partially uploaded',
            UPLOAD_ERR_NO_FILE => 'No file was uploaded',
            UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
            UPLOAD_ERR_EXTENSION => 'File upload stopped by extension'
        ];
        $error_message = $errors[$file['error']] ?? 'Unknown upload error';
        return ['success' => false, 'error' => $error_message];
    }
    
    // Get file extension
    $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    
    // Check if file type is allowed
    if (!in_array($file_extension, $allowed_types)) {
        return ['success' => false, 'error' => 'File type not allowed. Allowed types: ' . implode(', ', $allowed_types)];
    }
    
    // Check file size (default 10MB = 10 * 1024 * 1024)
    $max_size = 10 * 1024 * 1024;
    if ($file['size'] > $max_size) {
        return ['success' => false, 'error' => 'File too large. Maximum size is 10MB'];
    }
    
    // Create unique filename
    $new_filename = time() . '_' . bin2hex(random_bytes(8)) . '.' . $file_extension;
    
    // Ensure target directory exists
    if (!file_exists($target_dir)) {
        mkdir($target_dir, 0777, true);
    }
    
    $target_path = $target_dir . $new_filename;
    
    // Move uploaded file
    if (move_uploaded_file($file['tmp_name'], $target_path)) {
        return ['success' => true, 'filename' => $new_filename, 'path' => $target_path];
    }
    
    return ['success' => false, 'error' => 'Failed to save file. Check directory permissions.'];
}