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
    $current_password = trim($_POST['current_password']);
    $new_password = trim($_POST['new_password']);
    $confirm_password = trim($_POST['confirm_password']);
    
    // Validate inputs
    if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
        $error = "All fields are required";
    } elseif ($new_password !== $confirm_password) {
        $error = "New password and confirm password do not match";
    } elseif (strlen($new_password) < 6) {
        $error = "New password must be at least 6 characters long";
    } else {
        // Get current user's password hash from database
        $query = $conn->prepare("SELECT password FROM users WHERE id = ?");
        $query->bind_param("i", $_SESSION['user_id']);
        $query->execute();
        $result = $query->get_result();
        $user = $result->fetch_assoc();
        
        if (!$user) {
            $error = "User not found";
        } else {
            $stored_hash = $user['password'];
            
            // Verify current password using password_verify()
            $password_valid = password_verify($current_password, $stored_hash);
            
            // If password_verify fails, check if stored is plain text (for migration)
            if (!$password_valid && $current_password === $stored_hash) {
                // This is a plain text password, re-hash it
                $new_hash = password_hash($current_password, PASSWORD_DEFAULT);
                $update_hash = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
                $update_hash->bind_param("si", $new_hash, $_SESSION['user_id']);
                $update_hash->execute();
                $stored_hash = $new_hash;
                $password_valid = true;
            }
            
            if ($password_valid) {
                // Hash the new password
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                
                // Update password in database
                $update = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
                $update->bind_param("si", $hashed_password, $_SESSION['user_id']);
                
                if ($update->execute()) {
                    logActivity($_SESSION['user_id'], 'changed password');
                    $success = "Password changed successfully! You will be logged out.";
                    
                    // Force logout after password change
                    echo '<script>
                        alert("Password changed successfully! You will be logged out.");
                        setTimeout(function() {
                            window.location.href = "' . SITE_URL . 'auth/logout.php";
                        }, 2000);
                    </script>';
                } else {
                    $error = "Failed to change password. Please try again.";
                }
            } else {
                $error = "Current password is incorrect. Please try again.";
            }
        }
    }
}
?>

<div class="ml-64 mt-16 p-6">
    <div class="max-w-2xl mx-auto">
        <div class="mb-6">
            <h1 class="text-2xl font-bold text-gray-800">Change Password</h1>
            <p class="text-gray-500 mt-1">Update your account password</p>
            <p class="text-sm text-gray-400 mt-2">Default password: <strong>Admin@123</strong></p>
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
                </div>

                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">New Password *</label>
                    <input type="password" name="new_password" id="new_password" required 
                           class="w-full border rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <div class="mt-1 text-xs text-gray-500">
                        <span id="passwordStrength"></span>
                    </div>
                </div>

                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Confirm New Password *</label>
                    <input type="password" name="confirm_password" id="confirm_password" required 
                           class="w-full border rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <div id="passwordMatch" class="mt-1 text-xs"></div>
                </div>

                <div class="flex justify-end space-x-3 mt-6 pt-4 border-t">
                    <a href="../dashboard.php" class="px-4 py-2 border rounded-lg hover:bg-gray-50">Cancel</a>
                    <button type="submit" class="px-6 py-2 bg-gradient-to-r from-blue-500 to-purple-600 text-white rounded-lg hover:shadow-lg">
                        <i class="fas fa-key mr-2"></i> Change Password
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Password strength checker
document.getElementById('new_password').addEventListener('input', function() {
    const password = this.value;
    const strengthSpan = document.getElementById('passwordStrength');
    
    let strength = 0;
    if (password.length >= 6) strength++;
    if (/[A-Z]/.test(password)) strength++;
    if (/[a-z]/.test(password)) strength++;
    if (/[0-9]/.test(password)) strength++;
    
    let label = '';
    let color = '';
    
    if (password.length === 0) {
        label = '';
    } else if (strength <= 2) {
        label = 'Weak';
        color = 'text-red-500';
    } else if (strength === 3) {
        label = 'Good';
        color = 'text-yellow-500';
    } else {
        label = 'Strong';
        color = 'text-green-500';
    }
    
    strengthSpan.textContent = label;
    strengthSpan.className = color;
});

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