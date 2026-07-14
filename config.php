<?php
// =====================================================
// SMART SCHOOL LMS - CONFIGURATION FILE
// =====================================================

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// =====================================================
// ERROR REPORTING (Development Mode)
// =====================================================
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

// =====================================================
// DATABASE CONFIGURATION
// =====================================================
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'smart_school_lms');

// =====================================================
// DATABASE CONNECTION
// =====================================================
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Set charset to UTF-8
$conn->set_charset("utf8mb4");

// =====================================================
// SITE CONFIGURATION
// =====================================================
define('SITE_NAME', 'Smart School LMS');
define('SITE_URL', 'http://localhost/smart-school-lms/');
define('SITE_PATH', $_SERVER['DOCUMENT_ROOT'] . '/smart-school-lms/');

// =====================================================
// UPLOAD PATHS
// =====================================================
define('UPLOAD_PATH', SITE_PATH . 'uploads/');
define('MATERIALS_PATH', UPLOAD_PATH . 'materials/');
define('ASSIGNMENTS_PATH', UPLOAD_PATH . 'assignments/');
define('SUBMISSIONS_PATH', UPLOAD_PATH . 'submissions/');
define('PROFILE_PATH', UPLOAD_PATH . 'profile-pictures/');
define('LOGOS_PATH', UPLOAD_PATH . 'logos/');

// =====================================================
// TIMEZONE
// =====================================================
date_default_timezone_set('Africa/Dar_es_Salaam');

// =====================================================
// CREATE UPLOAD DIRECTORIES IF NOT EXIST
// =====================================================
$directories = [UPLOAD_PATH, MATERIALS_PATH, ASSIGNMENTS_PATH, SUBMISSIONS_PATH, PROFILE_PATH, LOGOS_PATH];
foreach ($directories as $dir) {
    if (!file_exists($dir)) {
        mkdir($dir, 0777, true);
    }
}

// =====================================================
// FUNCTION TO GET SCHOOL SETTINGS
// =====================================================
function getSchoolSettings($conn) {
    $query = "SELECT * FROM school_settings LIMIT 1";
    $result = $conn->query($query);
    if ($result && $result->num_rows > 0) {
        return $result->fetch_assoc();
    }
    return null;
}

// =====================================================
// FUNCTION TO GET USER BY ID
// =====================================================
function getUserById($conn, $user_id) {
    $query = $conn->prepare("SELECT * FROM users WHERE id = ?");
    $query->bind_param("i", $user_id);
    $query->execute();
    return $query->get_result()->fetch_assoc();
}

// =====================================================
// FUNCTION TO GET USER BY EMAIL
// =====================================================
function getUserByEmail($conn, $email) {
    $query = $conn->prepare("SELECT * FROM users WHERE email = ?");
    $query->bind_param("s", $email);
    $query->execute();
    return $query->get_result()->fetch_assoc();
}

// =====================================================
// FUNCTION TO LOG ACTIVITY (FIXED - uses global $conn)
// =====================================================
function logActivity($user_id, $action, $entity_type = null, $entity_id = null) {
    global $conn;
    
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    
    $query = $conn->prepare("
        INSERT INTO activity_logs (user_id, action, entity_type, entity_id, ip_address, user_agent, created_at) 
        VALUES (?, ?, ?, ?, ?, ?, NOW())
    ");
    $query->bind_param("ississ", $user_id, $action, $entity_type, $entity_id, $ip, $user_agent);
    return $query->execute();
}

// =====================================================
// FUNCTION TO GET UNREAD NOTIFICATIONS COUNT
// =====================================================
function getUnreadNotifications($conn, $user_id) {
    $query = $conn->prepare("SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = 0");
    $query->bind_param("i", $user_id);
    $query->execute();
    return $query->get_result()->fetch_assoc()['count'] ?? 0;
}

// =====================================================
// FUNCTION TO GET UNREAD MESSAGES COUNT
// =====================================================
function getUnreadMessages($conn, $user_id) {
    $query = $conn->prepare("SELECT COUNT(*) as count FROM messages WHERE receiver_id = ? AND is_read = 0");
    $query->bind_param("i", $user_id);
    $query->execute();
    return $query->get_result()->fetch_assoc()['count'] ?? 0;
}

// =====================================================
// FUNCTION TO SANITIZE INPUT
// =====================================================
function sanitizeInput($input) {
    return htmlspecialchars(strip_tags(trim($input)));
}

// =====================================================
// FUNCTION TO GENERATE RANDOM PASSWORD
// =====================================================
function generateRandomPassword($length = 10) {
    return substr(str_shuffle('abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%'), 0, $length);
}

// =====================================================
// FUNCTION TO GET TIME AGO
// =====================================================
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
    } elseif ($minutes <= 60) {
        return ($minutes == 1) ? "1 minute ago" : "$minutes minutes ago";
    } elseif ($hours <= 24) {
        return ($hours == 1) ? "1 hour ago" : "$hours hours ago";
    } elseif ($days <= 7) {
        return ($days == 1) ? "Yesterday" : "$days days ago";
    } elseif ($weeks <= 4.3) {
        return ($weeks == 1) ? "1 week ago" : "$weeks weeks ago";
    } elseif ($months <= 12) {
        return ($months == 1) ? "1 month ago" : "$months months ago";
    } else {
        return ($years == 1) ? "1 year ago" : "$years years ago";
    }
}

// =====================================================
// FUNCTION TO UPLOAD FILE - 25MB LIMIT
// =====================================================
function uploadFile($file, $target_dir, $allowed_types = ['pdf', 'docx', 'jpg', 'png', 'mp4', 'pptx', 'txt', 'zip', 'html', 'htm']) {
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
    
    // Check file size (25MB = 25 * 1024 * 1024)
    $max_size = 25 * 1024 * 1024;
    if ($file['size'] > $max_size) {
        return ['success' => false, 'error' => 'File too large. Maximum size is 25MB'];
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

// =====================================================
// GET SCHOOL SETTINGS FOR GLOBAL USE
// =====================================================
$school_settings = getSchoolSettings($conn);

// =====================================================
// SET DEFAULT TIMEZONE FOR SESSION
// =====================================================
if (!isset($_SESSION['timezone'])) {
    $_SESSION['timezone'] = 'Africa/Dar_es_Salaam';
}

// =====================================================
// ERROR HANDLING FOR DATABASE QUERIES
// =====================================================
function dbQuery($conn, $query, $params = null) {
    try {
        if ($params) {
            $stmt = $conn->prepare($query);
            if (!$stmt) {
                throw new Exception("Query preparation failed: " . $conn->error);
            }
            $types = '';
            $bind_values = [];
            foreach ($params as $key => $value) {
                if (is_int($value)) {
                    $types .= 'i';
                } elseif (is_double($value)) {
                    $types .= 'd';
                } elseif (is_string($value)) {
                    $types .= 's';
                } else {
                    $types .= 's';
                }
                $bind_values[] = $value;
            }
            $stmt->bind_param($types, ...$bind_values);
            $stmt->execute();
            $result = $stmt->get_result();
            $stmt->close();
            return $result;
        } else {
            $result = $conn->query($query);
            if (!$result) {
                throw new Exception("Query execution failed: " . $conn->error);
            }
            return $result;
        }
    } catch (Exception $e) {
        error_log("Database Error: " . $e->getMessage());
        return false;
    }
}

// =====================================================
// END OF CONFIG FILE
// =====================================================
?>