<?php
require_once '../../config.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';
requireRole('admin');

$page_title = 'Email Settings';
include '../../includes/header.php';
include '../../includes/sidebar.php';
include '../../includes/navbar.php';

$error = '';
$success = '';

// Email settings file
$email_config_file = '../../config/email.php';
$email_config = [];

if (file_exists($email_config_file)) {
    include $email_config_file;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'email') {
    $mail_host = sanitizeInput($_POST['mail_host']);
    $mail_port = intval($_POST['mail_port']);
    $mail_username = sanitizeInput($_POST['mail_username']);
    $mail_password = sanitizeInput($_POST['mail_password']);
    $mail_encryption = $_POST['mail_encryption'];
    $mail_from_address = sanitizeInput($_POST['mail_from_address']);
    $mail_from_name = sanitizeInput($_POST['mail_from_name']);
    
    // Create email config file content
    $config_content = "<?php
// Email Configuration
define('MAIL_HOST', '{$mail_host}');
define('MAIL_PORT', {$mail_port});
define('MAIL_USERNAME', '{$mail_username}');
define('MAIL_PASSWORD', '{$mail_password}');
define('MAIL_ENCRYPTION', '{$mail_encryption}');
define('MAIL_FROM_ADDRESS', '{$mail_from_address}');
define('MAIL_FROM_NAME', '{$mail_from_name}');
?>";
    
    // Ensure config directory exists
    if (!is_dir('../../config')) {
        mkdir('../../config', 0777, true);
    }
    
    if (file_put_contents($email_config_file, $config_content)) {
        logActivity($_SESSION['user_id'], 'updated email settings');
        $success = "Email settings saved successfully!";
    } else {
        $error = "Failed to save email settings";
    }
}

// Test email sending
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'test_email') {
    $test_email = sanitizeInput($_POST['test_email']);
    
    // Here you would implement actual email sending
    // For now, just show success message
    $success = "Test email sent to " . $test_email . " (Demo - Configure SMTP for actual sending)";
}
?>

<div class="ml-64 mt-16 p-6">
    <div class="max-w-4xl mx-auto">
        <div class="mb-6">
            <h1 class="text-2xl font-bold text-gray-800">Email Settings</h1>
            <p class="text-gray-500 mt-1">Configure email notifications and SMTP settings</p>
        </div>

        <!-- Settings Tabs -->
        <div class="bg-white rounded-xl shadow-sm overflow-hidden mb-6">
            <div class="border-b">
                <nav class="flex flex-wrap">
                    <a href="general.php" class="px-6 py-3 text-gray-600 hover:text-gray-800">
                        <i class="fas fa-building mr-2"></i> General
                    </a>
                    <a href="theme.php" class="px-6 py-3 text-gray-600 hover:text-gray-800">
                        <i class="fas fa-palette mr-2"></i> Theme
                    </a>
                    <a href="email.php" class="px-6 py-3 text-blue-600 border-b-2 border-blue-600 font-medium">
                        <i class="fas fa-envelope mr-2"></i> Email
                    </a>
                    <a href="backup.php" class="px-6 py-3 text-gray-600 hover:text-gray-800">
                        <i class="fas fa-database mr-2"></i> Backup
                    </a>
                </nav>
            </div>
            
            <div class="p-6">
                <?php if($error): ?>
                    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg mb-4"><?php echo $error; ?></div>
                <?php endif; ?>
                
                <?php if($success): ?>
                    <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-lg mb-4"><?php echo $success; ?></div>
                <?php endif; ?>

                <!-- Info Box -->
                <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-6">
                    <div class="flex items-start">
                        <i class="fas fa-info-circle text-blue-500 mt-1 mr-3"></i>
                        <div>
                            <p class="text-sm text-blue-800">Configure your SMTP settings to enable email notifications.</p>
                            <p class="text-xs text-blue-600 mt-1">For Gmail: Use smtp.gmail.com, Port 587, Enable "Less secure apps" or use App Password</p>
                        </div>
                    </div>
                </div>

                <!-- Email Settings Form -->
                <form method="POST">
                    <input type="hidden" name="action" value="email">
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">SMTP Host</label>
                            <input type="text" name="mail_host" 
                                   value="<?php echo defined('MAIL_HOST') ? MAIL_HOST : 'smtp.gmail.com'; ?>"
                                   placeholder="smtp.gmail.com"
                                   class="w-full border rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">SMTP Port</label>
                            <input type="number" name="mail_port" 
                                   value="<?php echo defined('MAIL_PORT') ? MAIL_PORT : '587'; ?>"
                                   placeholder="587"
                                   class="w-full border rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">SMTP Username</label>
                            <input type="text" name="mail_username" 
                                   value="<?php echo defined('MAIL_USERNAME') ? MAIL_USERNAME : ''; ?>"
                                   placeholder="your_email@gmail.com"
                                   class="w-full border rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">SMTP Password</label>
                            <input type="password" name="mail_password" 
                                   value="<?php echo defined('MAIL_PASSWORD') ? MAIL_PASSWORD : ''; ?>"
                                   placeholder="••••••••"
                                   class="w-full border rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Encryption</label>
                            <select name="mail_encryption" class="w-full border rounded-lg px-3 py-2">
                                <option value="tls" <?php echo (defined('MAIL_ENCRYPTION') && MAIL_ENCRYPTION == 'tls') ? 'selected' : ''; ?>>TLS</option>
                                <option value="ssl" <?php echo (defined('MAIL_ENCRYPTION') && MAIL_ENCRYPTION == 'ssl') ? 'selected' : ''; ?>>SSL</option>
                                <option value="none" <?php echo (defined('MAIL_ENCRYPTION') && MAIL_ENCRYPTION == 'none') ? 'selected' : ''; ?>>None</option>
                            </select>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">From Email Address</label>
                            <input type="email" name="mail_from_address" 
                                   value="<?php echo defined('MAIL_FROM_ADDRESS') ? MAIL_FROM_ADDRESS : ''; ?>"
                                   placeholder="noreply@yourschool.com"
                                   class="w-full border rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                        </div>
                        
                        <div class="md:col-span-2">
                            <label class="block text-sm font-medium text-gray-700 mb-1">From Name</label>
                            <input type="text" name="mail_from_name" 
                                   value="<?php echo defined('MAIL_FROM_NAME') ? MAIL_FROM_NAME : 'Smart School LMS'; ?>"
                                   placeholder="Smart School LMS"
                                   class="w-full border rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                        </div>
                    </div>
                    
                    <div class="flex justify-end mt-6 pt-4 border-t">
                        <button type="submit" class="px-6 py-2 bg-gradient-to-r from-blue-500 to-purple-600 text-white rounded-lg hover:shadow-lg">
                            <i class="fas fa-save mr-2"></i> Save Email Settings
                        </button>
                    </div>
                </form>
                
                <!-- Test Email Section -->
                <div class="mt-8 pt-6 border-t">
                    <h3 class="text-md font-semibold mb-4">Test Email Configuration</h3>
                    <form method="POST" class="flex items-center space-x-3">
                        <input type="hidden" name="action" value="test_email">
                        <input type="email" name="test_email" required 
                               placeholder="Enter email address to send test"
                               class="flex-1 border rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <button type="submit" class="bg-green-600 text-white px-6 py-2 rounded-lg hover:bg-green-700">
                            <i class="fas fa-paper-plane mr-2"></i> Send Test Email
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>