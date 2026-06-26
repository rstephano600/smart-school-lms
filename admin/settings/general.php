<?php
require_once '../../config.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';
requireRole('admin');

$page_title = 'General Settings';
include '../../includes/header.php';
include '../../includes/sidebar.php';
include '../../includes/navbar.php';

$error = '';
$success = '';

// Get current settings
$query = "SELECT * FROM school_settings LIMIT 1";
$result = $conn->query($query);
$settings = $result->fetch_assoc();

if (!$settings) {
    // Insert default settings if not exists
    $insert = "INSERT INTO school_settings (school_name, school_address, school_phone, school_email, motto, academic_year, current_term) 
               VALUES ('Smart School', 'Dar es Salaam, Tanzania', '+255 123 456 789', 'info@smartschool.com', 'Education for Excellence', " . date('Y') . ", 'term1')";
    $conn->query($insert);
    
    $result = $conn->query($query);
    $settings = $result->fetch_assoc();
}

// Handle logo upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_logo'])) {
    if (isset($_FILES['school_logo']) && $_FILES['school_logo']['error'] == 0) {
        $allowed = ['jpg', 'jpeg', 'png', 'svg', 'gif'];
        $upload_result = uploadFile($_FILES['school_logo'], '../../uploads/logos/', $allowed);
        
        if ($upload_result['success']) {
            $logo_path = 'uploads/logos/' . $upload_result['filename'];
            $update = $conn->prepare("UPDATE school_settings SET school_logo = ?");
            $update->bind_param("s", $logo_path);
            if ($update->execute()) {
                $success = "School logo updated successfully!";
                // Refresh settings
                $result = $conn->query("SELECT * FROM school_settings LIMIT 1");
                $settings = $result->fetch_assoc();
            } else {
                $error = "Failed to save logo path to database";
            }
        } else {
            $error = $upload_result['error'];
        }
    } else {
        $error = "Please select a file to upload";
    }
}

// Handle remove logo
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['remove_logo'])) {
    $update = $conn->prepare("UPDATE school_settings SET school_logo = NULL");
    if ($update->execute()) {
        $success = "School logo removed successfully!";
        $result = $conn->query("SELECT * FROM school_settings LIMIT 1");
        $settings = $result->fetch_assoc();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'general') {
    $school_name = sanitizeInput($_POST['school_name']);
    $school_address = sanitizeInput($_POST['school_address']);
    $school_phone = sanitizeInput($_POST['school_phone']);
    $school_email = sanitizeInput($_POST['school_email']);
    $motto = sanitizeInput($_POST['motto']);
    $academic_year = intval($_POST['academic_year']);
    $current_term = $_POST['current_term'];
    
    $query = "UPDATE school_settings SET 
              school_name = ?, school_address = ?, school_phone = ?, 
              school_email = ?, motto = ?, academic_year = ?, current_term = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("sssssis", $school_name, $school_address, $school_phone, $school_email, $motto, $academic_year, $current_term);
    
    if ($stmt->execute()) {
        logActivity($_SESSION['user_id'], 'updated general settings');
        $success = "General settings updated successfully!";
        
        // Refresh settings
        $result = $conn->query("SELECT * FROM school_settings LIMIT 1");
        $settings = $result->fetch_assoc();
    } else {
        $error = "Failed to update settings: " . $conn->error;
    }
}
?>

<div class="ml-64 mt-16 p-6">
    <div class="max-w-4xl mx-auto">
        <div class="mb-6">
            <h1 class="text-2xl font-bold text-gray-800">General Settings</h1>
            <p class="text-gray-500 mt-1">Configure your school information and system preferences</p>
        </div>

        <!-- Settings Tabs -->
        <div class="bg-white rounded-xl shadow-sm overflow-hidden mb-6">
            <div class="border-b">
                <nav class="flex flex-wrap">
                    <a href="general.php" class="px-6 py-3 text-blue-600 border-b-2 border-blue-600 font-medium">
                        <i class="fas fa-building mr-2"></i> General
                    </a>
                    <a href="theme.php" class="px-6 py-3 text-gray-600 hover:text-gray-800">
                        <i class="fas fa-palette mr-2"></i> Theme
                    </a>
                    <a href="email.php" class="px-6 py-3 text-gray-600 hover:text-gray-800">
                        <i class="fas fa-envelope mr-2"></i> Email
                    </a>
                    <a href="backup.php" class="px-6 py-3 text-gray-600 hover:text-gray-800">
                        <i class="fas fa-database mr-2"></i> Backup
                    </a>
                    <a href="change-password.php" class="px-6 py-3 text-gray-600 hover:text-gray-800">
                        <i class="fas fa-key mr-2"></i> Password
                    </a>
                </nav>
            </div>
            
            <div class="p-6">
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

                <!-- School Logo Section -->
                <div class="mb-8 pb-8 border-b">
                    <h3 class="text-lg font-semibold mb-4">School Logo</h3>
                    <div class="flex flex-col md:flex-row items-center space-y-4 md:space-y-0 md:space-x-6">
                        <div class="w-32 h-32 bg-gray-100 rounded-xl flex items-center justify-center overflow-hidden border-2 border-dashed border-gray-300">
                            <?php 
                            $logo_path = '';
                            if (!empty($settings['school_logo'])) {
                                if (file_exists('../../' . $settings['school_logo'])) {
                                    $logo_path = '../../' . $settings['school_logo'];
                                } elseif (file_exists($settings['school_logo'])) {
                                    $logo_path = $settings['school_logo'];
                                }
                            }
                            ?>
                            <?php if($logo_path && file_exists($logo_path)): ?>
                                <img src="<?php echo $logo_path; ?>" alt="School Logo" class="w-full h-full object-cover">
                            <?php else: ?>
                                <div class="text-center text-gray-400">
                                    <i class="fas fa-school text-4xl mb-1 block"></i>
                                    <span class="text-xs">No Logo</span>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div>
                            <form method="POST" enctype="multipart/form-data" class="flex flex-col space-y-2">
                                <input type="file" name="school_logo" accept="image/*" class="text-sm">
                                <div class="flex space-x-2">
                                    <button type="submit" name="upload_logo" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 text-sm">
                                        <i class="fas fa-upload mr-2"></i> Upload Logo
                                    </button>
                                    <?php if($logo_path && file_exists($logo_path)): ?>
                                        <button type="submit" name="remove_logo" class="bg-red-600 text-white px-4 py-2 rounded-lg hover:bg-red-700 text-sm" onclick="return confirm('Remove logo?')">
                                            <i class="fas fa-times mr-2"></i> Remove
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </form>
                            <p class="text-xs text-gray-500 mt-2">Recommended size: 200x200px. Allowed: JPG, PNG, SVG</p>
                        </div>
                    </div>
                </div>

                <!-- General Settings Form -->
                <form method="POST">
                    <input type="hidden" name="action" value="general">
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">School Name *</label>
                            <input type="text" name="school_name" required 
                                   value="<?php echo htmlspecialchars($settings['school_name']); ?>"
                                   class="w-full border rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">School Email *</label>
                            <input type="email" name="school_email" required 
                                   value="<?php echo htmlspecialchars($settings['school_email']); ?>"
                                   class="w-full border rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">School Phone</label>
                            <input type="text" name="school_phone" 
                                   value="<?php echo htmlspecialchars($settings['school_phone']); ?>"
                                   class="w-full border rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">School Motto</label>
                            <input type="text" name="motto" 
                                   value="<?php echo htmlspecialchars($settings['motto']); ?>"
                                   class="w-full border rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                        </div>
                        
                        <div class="md:col-span-2">
                            <label class="block text-sm font-medium text-gray-700 mb-1">School Address</label>
                            <textarea name="school_address" rows="2" 
                                      class="w-full border rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"><?php echo htmlspecialchars($settings['school_address']); ?></textarea>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Academic Year</label>
                            <select name="academic_year" class="w-full border rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                                <?php for($year = 2020; $year <= 2030; $year++): ?>
                                    <option value="<?php echo $year; ?>" <?php echo ($settings['academic_year'] ?? date('Y')) == $year ? 'selected' : ''; ?>>
                                        <?php echo $year . '/' . ($year + 1); ?>
                                    </option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Current Term</label>
                            <select name="current_term" class="w-full border rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                                <option value="term1" <?php echo ($settings['current_term'] ?? 'term1') == 'term1' ? 'selected' : ''; ?>>Term 1</option>
                                <option value="term2" <?php echo ($settings['current_term'] ?? 'term1') == 'term2' ? 'selected' : ''; ?>>Term 2</option>
                                <option value="term3" <?php echo ($settings['current_term'] ?? 'term1') == 'term3' ? 'selected' : ''; ?>>Term 3</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="flex justify-end mt-6 pt-4 border-t">
                        <button type="submit" class="px-6 py-2 bg-gradient-to-r from-blue-500 to-purple-600 text-white rounded-lg hover:shadow-lg transition-all">
                            <i class="fas fa-save mr-2"></i> Save Settings
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
// Preview logo before upload
document.querySelector('input[name="school_logo"]').addEventListener('change', function(e) {
    const file = this.files[0];
    if (file) {
        const reader = new FileReader();
        reader.onload = function(e) {
            const preview = document.querySelector('.w-32.h-32 img, .w-32.h-32 div');
            const container = document.querySelector('.w-32.h-32');
            if (preview) {
                if (preview.tagName === 'IMG') {
                    preview.src = e.target.result;
                } else {
                    container.innerHTML = `<img src="${e.target.result}" alt="Logo Preview" class="w-full h-full object-cover">`;
                }
            }
        };
        reader.readAsDataURL(file);
    }
});
</script>

<?php include '../../includes/footer.php'; ?>