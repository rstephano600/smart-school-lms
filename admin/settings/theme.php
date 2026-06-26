<?php
require_once '../../config.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';
requireRole('admin');

$page_title = 'Theme Settings';
include '../../includes/header.php';
include '../../includes/sidebar.php';
include '../../includes/navbar.php';

$error = '';
$success = '';

// Get current settings
$query = "SELECT * FROM school_settings LIMIT 1";
$result = $conn->query($query);
$settings = $result->fetch_assoc();

// Get current theme settings
$theme = json_decode($settings['theme'] ?? '{}', true);
$default_theme = [
    'mode' => 'light',
    'primary_color' => '#3b82f6',
    'sidebar_color' => '#ffffff',
    'font_family' => 'Inter'
];

$theme = array_merge($default_theme, $theme);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'theme') {
    $theme_data = [
        'mode' => $_POST['mode'],
        'primary_color' => $_POST['primary_color'],
        'sidebar_color' => $_POST['sidebar_color'],
        'font_family' => $_POST['font_family']
    ];
    
    $theme_json = json_encode($theme_data);
    
    $query = "UPDATE school_settings SET theme = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("s", $theme_json);
    
    if ($stmt->execute()) {
        logActivity($_SESSION['user_id'], 'updated theme settings');
        $success = "Theme settings updated successfully!";
        $theme = $theme_data;
        
        // Set session for theme
        $_SESSION['theme_mode'] = $theme_data['mode'];
    } else {
        $error = "Failed to update theme settings";
    }
}

// Handle CSS generation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'generate_css') {
    $css_content = ":root {
        --primary-color: {$theme['primary_color']};
        --sidebar-color: {$theme['sidebar_color']};
        --font-family: {$theme['font_family']};
    }
    
    body {
        font-family: var(--font-family), sans-serif;
    }
    
    .bg-primary {
        background-color: var(--primary-color);
    }
    
    .text-primary {
        color: var(--primary-color);
    }
    
    .border-primary {
        border-color: var(--primary-color);
    }";
    
    file_put_contents('../../assets/css/theme.css', $css_content);
    $success = "Theme CSS generated successfully!";
}
?>

<div class="ml-64 mt-16 p-6">
    <div class="max-w-4xl mx-auto">
        <div class="mb-6">
            <h1 class="text-2xl font-bold text-gray-800">Theme Settings</h1>
            <p class="text-gray-500 mt-1">Customize the look and feel of your LMS</p>
        </div>

        <!-- Settings Tabs -->
        <div class="bg-white rounded-xl shadow-sm overflow-hidden mb-6">
            <div class="border-b">
                <nav class="flex flex-wrap">
                    <a href="general.php" class="px-6 py-3 text-gray-600 hover:text-gray-800">
                        <i class="fas fa-building mr-2"></i> General
                    </a>
                    <a href="theme.php" class="px-6 py-3 text-blue-600 border-b-2 border-blue-600 font-medium">
                        <i class="fas fa-palette mr-2"></i> Theme
                    </a>
                    <a href="email.php" class="px-6 py-3 text-gray-600 hover:text-gray-800">
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

                <!-- Live Preview -->
                <div class="mb-8 p-4 bg-gray-100 rounded-xl">
                    <h3 class="text-sm font-medium text-gray-700 mb-2">Live Preview</h3>
                    <div id="preview" class="p-4 bg-white rounded-lg shadow" style="border-top: 4px solid <?php echo $theme['primary_color']; ?>">
                        <div class="flex items-center space-x-3">
                            <div class="w-10 h-10 rounded-full" style="background-color: <?php echo $theme['primary_color']; ?>"></div>
                            <div>
                                <p class="font-semibold">Theme Preview</p>
                                <p class="text-sm text-gray-500">This is how your theme will look</p>
                            </div>
                        </div>
                        <div class="mt-3">
                            <button class="px-4 py-2 text-white rounded-lg" style="background-color: <?php echo $theme['primary_color']; ?>">
                                Sample Button
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Theme Settings Form -->
                <form method="POST" id="themeForm">
                    <input type="hidden" name="action" value="theme">
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Theme Mode</label>
                            <select name="mode" id="mode" class="w-full border rounded-lg px-3 py-2">
                                <option value="light" <?php echo $theme['mode'] == 'light' ? 'selected' : ''; ?>>Light Mode</option>
                                <option value="dark" <?php echo $theme['mode'] == 'dark' ? 'selected' : ''; ?>>Dark Mode</option>
                            </select>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Primary Color</label>
                            <div class="flex space-x-2">
                                <input type="color" name="primary_color" id="primary_color" 
                                       value="<?php echo $theme['primary_color']; ?>"
                                       class="w-16 h-10 border rounded cursor-pointer">
                                <input type="text" id="primary_color_hex" 
                                       value="<?php echo $theme['primary_color']; ?>"
                                       class="flex-1 border rounded-lg px-3 py-2">
                            </div>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Sidebar Color</label>
                            <div class="flex space-x-2">
                                <input type="color" name="sidebar_color" id="sidebar_color" 
                                       value="<?php echo $theme['sidebar_color']; ?>"
                                       class="w-16 h-10 border rounded cursor-pointer">
                                <input type="text" id="sidebar_color_hex" 
                                       value="<?php echo $theme['sidebar_color']; ?>"
                                       class="flex-1 border rounded-lg px-3 py-2">
                            </div>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Font Family</label>
                            <select name="font_family" id="font_family" class="w-full border rounded-lg px-3 py-2">
                                <option value="Inter" <?php echo $theme['font_family'] == 'Inter' ? 'selected' : ''; ?>>Inter</option>
                                <option value="Poppins" <?php echo $theme['font_family'] == 'Poppins' ? 'selected' : ''; ?>>Poppins</option>
                                <option value="Roboto" <?php echo $theme['font_family'] == 'Roboto' ? 'selected' : ''; ?>>Roboto</option>
                                <option value="Open Sans" <?php echo $theme['font_family'] == 'Open Sans' ? 'selected' : ''; ?>>Open Sans</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="flex justify-end space-x-3 mt-6 pt-4 border-t">
                        <button type="submit" class="px-6 py-2 bg-gradient-to-r from-blue-500 to-purple-600 text-white rounded-lg hover:shadow-lg">
                            <i class="fas fa-save mr-2"></i> Save Theme
                        </button>
                    </div>
                </form>
                
                <div class="mt-6 pt-4 border-t">
                    <form method="POST" class="inline">
                        <input type="hidden" name="action" value="generate_css">
                        <button type="submit" class="text-blue-600 hover:text-blue-800">
                            <i class="fas fa-code mr-1"></i> Generate Theme CSS
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Live preview update
const primaryColor = document.getElementById('primary_color');
const primaryHex = document.getElementById('primary_color_hex');
const sidebarColor = document.getElementById('sidebar_color');
const sidebarHex = document.getElementById('sidebar_color_hex');
const mode = document.getElementById('mode');
const preview = document.getElementById('preview');

function updatePreview() {
    const pColor = primaryColor.value;
    const sColor = sidebarColor.value;
    const modeValue = mode.value;
    
    preview.style.borderTopColor = pColor;
    const previewBox = preview.querySelector('.rounded-full');
    if (previewBox) previewBox.style.backgroundColor = pColor;
    const previewBtn = preview.querySelector('button');
    if (previewBtn) previewBtn.style.backgroundColor = pColor;
    
    if (modeValue === 'dark') {
        preview.style.backgroundColor = '#1a1a2e';
        preview.style.color = '#ffffff';
    } else {
        preview.style.backgroundColor = '#ffffff';
        preview.style.color = '#000000';
    }
}

primaryColor.addEventListener('input', function() {
    primaryHex.value = this.value;
    updatePreview();
});
primaryHex.addEventListener('input', function() {
    primaryColor.value = this.value;
    updatePreview();
});
sidebarColor.addEventListener('input', function() {
    sidebarHex.value = this.value;
    updatePreview();
});
sidebarHex.addEventListener('input', function() {
    sidebarColor.value = this.value;
    updatePreview();
});
mode.addEventListener('change', updatePreview);

updatePreview();
</script>

<?php include '../../includes/footer.php'; ?>