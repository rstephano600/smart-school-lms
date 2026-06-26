<?php
require_once 'config.php';

$email = 'teacher@school.com';
$new_password = 'Admin@123';

echo "<h2>Fix Teacher Password</h2>";
echo "Email: <strong>$email</strong><br>";
echo "New Password: <strong>$new_password</strong><br><br>";

// Hash password
$hashed = password_hash($new_password, PASSWORD_DEFAULT);

// Update teacher password
$update = $conn->prepare("UPDATE users SET password = ? WHERE email = ?");
$update->bind_param("ss", $hashed, $email);

if ($update->execute()) {
    echo "✅ <span style='color:green;'>Password updated successfully!</span><br><br>";
    
    // Verify
    $verify = $conn->prepare("SELECT password FROM users WHERE email = ?");
    $verify->bind_param("s", $email);
    $verify->execute();
    $user = $verify->get_result()->fetch_assoc();
    
    if (password_verify($new_password, $user['password'])) {
        echo "✅ <span style='color:green;'>Password verification: SUCCESS!</span><br>";
    } else {
        echo "❌ <span style='color:red;'>Password verification: FAILED!</span><br>";
    }
    
    echo "<br><br><a href='index.php' style='background:blue;color:white;padding:10px 20px;text-decoration:none;border-radius:5px;'>Go to Login</a>";
} else {
    echo "❌ Error: " . $conn->error;
}