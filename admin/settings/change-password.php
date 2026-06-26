<?php
require_once '../../config.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';
requireRole('admin');

$page_title = 'Change Password';
include '../../includes/header.php';
include '../../includes/sidebar.php';
include '../../includes/navbar.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    // Validate inputs
    if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
        $error = "All fields are required";
    } elseif ($new_password !== $confirm_password) {
        $error = "New password and confirm password do not match";
    } elseif (strlen($new_password) < 6) {
        $error = "New password must be at least 6 characters long";
    } else {
        // Get current user's password from database
        $query = $conn->prepare("SELECT password FROM users WHERE id = ?");
        $query->bind_param("i", $_SESSION['user_id']);
        $query->execute();
        $user = $query->get_result()->fetch_assoc();
        
        $stored_hash = $user['password'];
        
        // Try password_verify
        $password_valid = password_verify($current_password, $stored_hash);
        
        // Also check if stored is plain text (legacy)
        if (!$password_valid && $current_password === $stored_hash) {
            $password_valid = true;
            // Re-hash the password
            $new_hash = password_hash($current_password, PASSWORD_DEFAULT);
            $update_hash = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
            $update_hash->bind_param("si", $new_hash, $_SESSION['user_id']);
            $update_hash->execute();
        }
        
        if ($password_valid) {
            // Hash new password
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            
            // Update password
            $update = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
            $update->bind_param("si", $hashed_password, $_SESSION['user_id']);
            
            if ($update->execute()) {
                logActivity($_SESSION['user_id'], 'changed password');
                $success = "Password changed successfully!";
                
                // Force logout after password change
                echo '<script>
                    setTimeout(function() {
                        alert("Password changed successfully! You will be logged out.");
                        window.location.href = "' . SITE_URL . 'auth/logout.php";
                    }, 1500);
                </script>';
            } else {
                $error = "Failed to change password. Please try again.";
            }
        } else {
            $error = "Current password is incorrect. Please try again.";
        }
    }
}
?>

<div class="ml-64 mt-16 p-6">
    <div class="max-w-2xl mx-auto">
        <div class="mb-6">
            <h1 class="text-2xl font-bold text-gray-800">Change Password</h1>
            <p class="text-gray-500 mt-1">Update your account password</p>
        </div>

        <?php if($error): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg mb-4">
                <i class="fas fa-exclamation-circle mr-2"></i> <?php echo $error; ?>
            </div>
        <?php endif; ?>
        
        <?php if($success): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-lg mb-4">
                <i class="fas fa-check-circle mr-2"></i> <?php echo $success; ?>
            </div>
        <?php endif; ?>

        <div class="bg-white rounded-xl shadow-sm p-6">
            <form method="POST" id="passwordForm">
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Current Password *</label>
                    <input type="password" name="current_password" id="current_password" required 
                           class="w-full border rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <p class="text-xs text-gray-400 mt-1">Default: <strong>Admin@123</strong></p>
                </div>

                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">New Password *</label>
                    <input type="password" name="new_password" id="new_password" required 
                           class="w-full border rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>

                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Confirm New Password *</label>
                    <input type="password" name="confirm_password" id="confirm_password" required 
                           class="w-full border rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <div id="passwordMatch" class="mt-1 text-xs"></div>
                </div>

                <div class="flex justify-end space-x-3 mt-6 pt-4 border-t">
                    <a href="../dashboard.php" class="px-4 py-2 border rounded-lg hover:bg-gray-50">Cancel</a>
                    <button type="submit" class="px-6 py-2 bg-gradient-to-r from-blue-500 to-purple-600 text-white rounded-lg hover:shadow-lg transition-all">
                        <i class="fas fa-key mr-2"></i> Change Password
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Password match checker
document.getElementById('confirm_password').addEventListener('input', function() {
    const password = document.getElementById('new_password').value;
    const confirm = this.value;
    const matchDiv = document.getElementById('passwordMatch');
    
    if (confirm.length === 0) {
        matchDiv.textContent = '';
    } else if (password === confirm) {
        matchDiv.innerHTML = '<i class="fas fa-check-circle text-green-500 mr-1"></i> Passwords match';
        matchDiv.className = 'mt-1 text-xs text-green-600';
    } else {
        matchDiv.innerHTML = '<i class="fas fa-times-circle text-red-500 mr-1"></i> Passwords do not match';
        matchDiv.className = 'mt-1 text-xs text-red-600';
    }
});

// Form validation
document.getElementById('passwordForm').addEventListener('submit', function(e) {
    const password = document.getElementById('new_password').value;
    const confirm = document.getElementById('confirm_password').value;
    
    if (password !== confirm) {
        e.preventDefault();
        alert('Passwords do not match!');
        return false;
    }
    
    if (password.length < 6) {
        e.preventDefault();
        alert('Password must be at least 6 characters long!');
        return false;
    }
});
</script>

<?php include '../../includes/footer.php'; ?>