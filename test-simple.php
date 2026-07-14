<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>Simple PHP Test</h1>";
echo "PHP Version: " . phpversion() . "<br>";
echo "Server: " . $_SERVER['SERVER_SOFTWARE'] . "<br>";

require_once 'config.php';

if ($conn) {
    echo "✅ Database connected!<br>";
    
    $result = $conn->query("SELECT COUNT(*) as count FROM users");
    if ($result) {
        $count = $result->fetch_assoc()['count'];
        echo "✅ Users in database: " . $count . "<br>";
    }
} else {
    echo "❌ Database connection failed!<br>";
}

echo "<br><a href='index.php'>Go to Login</a>";