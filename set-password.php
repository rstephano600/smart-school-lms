<?php
require_once 'config.php';

// Password unayotaka kuweka
$new_password = 'Admin@123';
$hashed = password_hash($new_password, PASSWORD_DEFAULT);

echo "<h2>Set Admin Password</h2>";
echo "New Password: <strong>$new_password</strong><br>";
echo "Hash: <strong>$hashed</strong><br><br>";

// Update password
$update = $conn->prepare("UPDATE users SET password = ? WHERE email = 'admin@school.com'");
$update->bind_param("s", $hashed);

if ($update->execute()) {
    echo "✅ <span style='color:green;'>Password updated successfully!</span><br><br>";
    echo "<a href='index.php' style='background:blue;color:white;padding:10px 20px;text-decoration:none;border-radius:5px;'>Go to Login</a>";
} else {
    echo "❌ Error: " . $conn->error;
}