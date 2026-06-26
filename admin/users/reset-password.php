<?php
require_once '../../config.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';
requireRole('admin');

$user_id = $_GET['id'] ?? 0;
if (!$user_id) {
    header('Location: index.php');
    exit();
}

// Get user data
$query = "SELECT * FROM users WHERE id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

if (!$user) {
    header('Location: index.php');
    exit();
}

$new_password = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $new_password = generateRandomPassword();
    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
    
    $query = "UPDATE users SET password = ? WHERE id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("si", $hashed_password, $user_id);
    
    if ($stmt->execute()) {
        logActivity($_SESSION['user_id'], 'reset password for user', 'user', $user_id);
        $success = "Password reset successfully!";
    } else {
        $error = "Failed to reset password";
    }
}

$page_title = 'Reset Password';
include '../../includes/header.php';
include '../../includes/sidebar.php';
include '../../includes/navbar.php';
?>

<div class="ml-64 mt-16 p-6">
    <div class="max-w-2xl mx-auto">
        <div class="mb-6">
            <h1 class="text-2xl font-bold text-gray-800">Reset Password</h1>
            <p class="text-gray-500 mt-1">Reset password for <?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></p>
        </div>

        <?php if($success): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-lg mb-4">
                <?php echo $success; ?>
                <div class="mt-2 p-2 bg-white rounded">
                    <strong>New Password:</strong> <?php echo $new_password; ?>
                </div>
                <p class="mt-2 text-sm">Please save this password and share it with the user.</p>
            </div>
        <?php endif; ?>

        <div class="bg-white rounded-xl shadow-sm p-6">
            <div class="text-center mb-6">
                <div class="w-16 h-16 bg-yellow-100 rounded-full flex items-center justify-center mx-auto mb-3">
                    <i class="fas fa-key text-yellow-600 text-2xl"></i>
                </div>
                <h3 class="text-lg font-semibold">Reset User Password</h3>
                <p class="text-gray-500 text-sm mt-1">This will generate a new random password for the user.</p>
            </div>
            
            <form method="POST">
                <div class="flex justify-center space-x-3">
                    <a href="index.php" class="px-4 py-2 border rounded-lg hover:bg-gray-50">Cancel</a>
                    <button type="submit" class="px-6 py-2 bg-yellow-500 text-white rounded-lg hover:bg-yellow-600">
                        <i class="fas fa-sync-alt mr-2"></i> Generate New Password
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>