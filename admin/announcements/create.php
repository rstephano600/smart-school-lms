<?php
require_once '../../config.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';
requireRole('admin');

$page_title = 'Create Announcement';
include '../../includes/header.php';
include '../../includes/sidebar.php';
include '../../includes/navbar.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = sanitizeInput($_POST['title']);
    $content = sanitizeInput($_POST['content']);
    $expires_at = !empty($_POST['expires_at']) ? $_POST['expires_at'] : null;
    $target_roles = isset($_POST['target_roles']) ? $_POST['target_roles'] : [];
    
    // Validate
    if (empty($title) || empty($content)) {
        $error = "Title and content are required";
    } elseif (empty($target_roles)) {
        $error = "Please select at least one target role";
    } else {
        // Convert target roles to JSON
        $target_roles_json = json_encode($target_roles);
        
        $query = "INSERT INTO announcements (title, content, target_roles, created_by, expires_at) 
                  VALUES (?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("sssis", $title, $content, $target_roles_json, $_SESSION['user_id'], $expires_at);
        
        if ($stmt->execute()) {
            $announcement_id = $conn->insert_id;
            
            // Create notifications for targeted users
            createNotificationsForAnnouncement($conn, $announcement_id, $title, $content, $target_roles);
            
            logActivity($_SESSION['user_id'], 'created announcement', 'announcement', $announcement_id);
            $success = "Announcement created and notifications sent successfully!";
            
            // Clear form
            $_POST = [];
        } else {
            $error = "Failed to create announcement: " . $conn->error;
        }
    }
}

// Function to create notifications for targeted users
function createNotificationsForAnnouncement($conn, $announcement_id, $title, $content, $target_roles) {
    // Build query to get users with selected roles
    $placeholders = implode(',', array_fill(0, count($target_roles), '?'));
    $query = "SELECT id FROM users WHERE role IN ($placeholders) AND is_active = 1";
    $stmt = $conn->prepare($query);
    
    $types = str_repeat('s', count($target_roles));
    $stmt->bind_param($types, ...$target_roles);
    $stmt->execute();
    $users = $stmt->get_result();
    
    // Insert notification for each user
    $notification_query = "INSERT INTO notifications (user_id, title, message, type, link) VALUES (?, ?, ?, 'announcement', ?)";
    $notification_stmt = $conn->prepare($notification_query);
    
    while ($user = $users->fetch_assoc()) {
        $link = BASE_URL . "announcements/view.php?id=" . $announcement_id;
        $notification_stmt->bind_param("isss", $user['id'], $title, $content, $link);
        $notification_stmt->execute();
    }
}
?>

<div class="ml-64 mt-16 p-6">
    <div class="max-w-3xl mx-auto">
        <div class="mb-6">
            <h1 class="text-2xl font-bold text-gray-800">Create Announcement</h1>
            <p class="text-gray-500 mt-1">Create a new announcement for students, teachers, or parents</p>
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
            <form method="POST" id="announcementForm">
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Announcement Title *</label>
                    <input type="text" name="title" required 
                           value="<?php echo htmlspecialchars($_POST['title'] ?? ''); ?>"
                           placeholder="e.g., Mid-Term Examinations, School Holiday, Parent Meeting"
                           class="w-full border rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>

                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Content *</label>
                    <textarea name="content" required rows="6" 
                              placeholder="Write your announcement details here..."
                              class="w-full border rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"><?php echo htmlspecialchars($_POST['content'] ?? ''); ?></textarea>
                </div>

                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Target Audience *</label>
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                        <label class="flex items-center space-x-2 p-3 border rounded-lg hover:bg-gray-50 cursor-pointer">
                            <input type="checkbox" name="target_roles[]" value="admin" 
                                   <?php echo (isset($_POST['target_roles']) && in_array('admin', $_POST['target_roles'])) ? 'checked' : ''; ?>
                                   class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                            <span><i class="fas fa-user-shield text-red-500"></i> Admin</span>
                        </label>
                        <label class="flex items-center space-x-2 p-3 border rounded-lg hover:bg-gray-50 cursor-pointer">
                            <input type="checkbox" name="target_roles[]" value="academic" 
                                   <?php echo (isset($_POST['target_roles']) && in_array('academic', $_POST['target_roles'])) ? 'checked' : ''; ?>
                                   class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                            <span><i class="fas fa-calendar-alt text-orange-500"></i> Academic</span>
                        </label>
                        <label class="flex items-center space-x-2 p-3 border rounded-lg hover:bg-gray-50 cursor-pointer">
                            <input type="checkbox" name="target_roles[]" value="teacher" 
                                   <?php echo (isset($_POST['target_roles']) && in_array('teacher', $_POST['target_roles'])) ? 'checked' : ''; ?>
                                   class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                            <span><i class="fas fa-chalkboard-user text-green-500"></i> Teachers</span>
                        </label>
                        <label class="flex items-center space-x-2 p-3 border rounded-lg hover:bg-gray-50 cursor-pointer">
                            <input type="checkbox" name="target_roles[]" value="student" 
                                   <?php echo (isset($_POST['target_roles']) && in_array('student', $_POST['target_roles'])) ? 'checked' : ''; ?>
                                   class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                            <span><i class="fas fa-user-graduate text-blue-500"></i> Students</span>
                        </label>
                        <label class="flex items-center space-x-2 p-3 border rounded-lg hover:bg-gray-50 cursor-pointer">
                            <input type="checkbox" name="target_roles[]" value="parent" 
                                   <?php echo (isset($_POST['target_roles']) && in_array('parent', $_POST['target_roles'])) ? 'checked' : ''; ?>
                                   class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                            <span><i class="fas fa-users text-purple-500"></i> Parents</span>
                        </label>
                    </div>
                </div>

                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Expiration Date (Optional)</label>
                    <input type="date" name="expires_at" 
                           value="<?php echo htmlspecialchars($_POST['expires_at'] ?? ''); ?>"
                           class="w-full border rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <p class="text-xs text-gray-500 mt-1">If set, this announcement will expire on this date</p>
                </div>

                <div class="flex justify-end space-x-3 mt-6 pt-4 border-t">
                    <a href="index.php" class="px-4 py-2 border rounded-lg hover:bg-gray-50">Cancel</a>
                    <button type="submit" class="px-6 py-2 bg-gradient-to-r from-blue-500 to-purple-600 text-white rounded-lg hover:shadow-lg transition-all duration-300">
                        <i class="fas fa-paper-plane mr-2"></i> Publish Announcement
                    </button>
                </div>
            </form>
        </div>
        
        <!-- Preview Section -->
        <div class="mt-6">
            <div class="bg-gray-50 rounded-xl p-4">
                <p class="text-sm text-gray-500 mb-2"><i class="fas fa-eye mr-1"></i> Preview</p>
                <div id="preview" class="bg-white rounded-lg p-4 border">
                    <p class="text-gray-400 text-center">Fill the form above to see preview</p>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Live preview
const titleInput = document.querySelector('input[name="title"]');
const contentTextarea = document.querySelector('textarea[name="content"]');
const previewDiv = document.getElementById('preview');

function updatePreview() {
    const title = titleInput.value || 'Announcement Title';
    const content = contentTextarea.value || 'Announcement content will appear here...';
    
    previewDiv.innerHTML = `
        <div class="bg-gradient-to-r from-blue-50 to-purple-50 rounded-lg p-4">
            <h3 class="text-lg font-semibold text-gray-800 mb-2">${escapeHtml(title)}</h3>
            <p class="text-gray-600">${escapeHtml(content).replace(/\n/g, '<br>')}</p>
            <div class="mt-3 pt-2 border-t text-xs text-gray-400">
                <i class="fas fa-clock mr-1"></i> Will be sent immediately
            </div>
        </div>
    `;
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

titleInput.addEventListener('input', updatePreview);
contentTextarea.addEventListener('input', updatePreview);
</script>

<?php include '../../includes/footer.php'; ?>