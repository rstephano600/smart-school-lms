<?php
// config.php - Database configuration and constants
session_start();

// Error reporting for development
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Database credentials
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'smart_school_lms');

// Create connection
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Set charset
$conn->set_charset("utf8mb4");

// Site configuration
define('SITE_NAME', 'Smart School LMS');
define('SITE_URL', 'http://localhost/PROJECTS/smart-school-lms/smart-school-lms/');
define('SITE_PATH', $_SERVER['DOCUMENT_ROOT'] . '/PROJECTS/smart-school-lms/smart-school-lms/');

// Upload paths
define('UPLOAD_PATH', SITE_PATH . 'uploads/');
define('MATERIALS_PATH', UPLOAD_PATH . 'materials/');
define('ASSIGNMENTS_PATH', UPLOAD_PATH . 'assignments/');
define('SUBMISSIONS_PATH', UPLOAD_PATH . 'submissions/');
define('PROFILE_PATH', UPLOAD_PATH . 'profile-pictures/');

// Timezone
date_default_timezone_set('Africa/Dar_es_Salaam');

// Create upload directories if not exist
$directories = [UPLOAD_PATH, MATERIALS_PATH, ASSIGNMENTS_PATH, SUBMISSIONS_PATH, PROFILE_PATH];
foreach ($directories as $dir) {
    if (!file_exists($dir)) {
        mkdir($dir, 0777, true);
    }
}
?>