<?php
require_once '../../config.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';
requireRole('admin');

$announcement_id = $_GET['id'] ?? 0;
if (!$announcement_id) {
    header('Location: index.php');
    exit();
}

// Get announcement data
$query = "SELECT * FROM announcements WHERE id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $announcement_id);
$stmt->execute();
$announcement = $stmt->get_result()->fetch_assoc();

if (!$announcement) {
    header('Location: index.php');
    exit();
}

$page_title = 'Edit Announcement';
include '../../includes/header.php';
include '../../includes/sidebar.php';
include '../../includes/navbar.php';

$error = '';
$success = '';
$target_roles_selected = json_decode($announcement['target_roles'], true);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = sanitizeInput($_POST['title']);
    $content = sanitizeInput($_POST['content']);
    $expires_at = !empty($_POST['expires_at']) ? $_POST['expires_at'] : null;
    $target_roles = isset($_POST['target_roles']) ? $_POST['target_roles'] : [];
    
    if (empty($title) || empty($content)) {
        $error = "Title and content are required";
    } elseif (empty($target_roles)) {
        $error = "Please select at least one target role";
    } else {
        $target_roles_json = json_encode($target_roles);
        
        $query = "UPDATE announcements SET title = ?, content = ?, target_roles = ?, expires_at = ? WHERE id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("ssssi", $title, $content, $target_roles_json, $expires_at, $announcement_id);
        
        if ($stmt->execute()) {
            logActivity($_SESSION['user_id'], 'updated announcement', 'announcement', $announcement_id);
            $success = "Announcement updated successfully!";
            
            // Refresh data
            $stmt = $conn->prepare("SELECT * FROM announcements WHERE id = ?");
            $stmt->bind_param("i", $announcement_id);
            $stmt->execute();
            $announcement = $stmt->get_result()->fetch_assoc();
            $target_roles_selected = json_decode($announcement['target_roles'], true);
        } else {
            $error = "Failed to update announcement: " . $conn->error;
        }
    }
}
?>

<div class="ml-64 mt-16 p-6">
    <div class="max-w-3xl mx-auto">
        <div class="mb-6">
            <h1 class="text-2xl font-bold text-gray-800">Edit Announcement</h1>
            <p class="text-gray-500 mt-1">Update your announcement</p>
        </div>

        <?php if($error): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg mb-4"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <?php if($success): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-lg mb-4"><?php echo $success; ?></div>
        <?php endif; ?>

        <div class="bg-white rounded-xl shadow-sm p-6">
            <form method="POST">
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Announcement Title *</label>
                    <input type="text" name="title" required value="<?php echo htmlspecialchars($announcement['title']); ?>"
                           class="w-full border rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>

                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Content *</label>
                    <textarea name="content" required rows="6" 
                              class="w-full border rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"><?php echo htmlspecialchars($announcement['content']); ?></textarea>
                </div>

                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Target Audience *</label>
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                        <label class="flex items-center space-x-2 p-3 border rounded-lg hover:bg-gray-50 cursor-pointer">
                            <input type="checkbox" name="target_roles[]" value="admin" 
                                   <?php echo in_array('admin', $target_roles_selected) ? 'checked' : ''; ?>
                                   class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                            <span>Admin</span>
                        </label>
                        <label class="flex items-center space-x-2 p-3 border rounded-lg hover:bg-gray-50 cursor-pointer">
                            <input type="checkbox" name="target_roles[]" value="academic" 
                                   <?php echo in_array('academic', $target_roles_selected) ? 'checked' : ''; ?>
                                   class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                            <span>Academic</span>
                        </label>
                        <label class="flex items-center space-x-2 p-3 border rounded-lg hover:bg-gray-50 cursor-pointer">
                            <input type="checkbox" name="target_roles[]" value="teacher" 
                                   <?php echo in_array('teacher', $target_roles_selected) ? 'checked' : ''; ?>
                                   class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                            <span>Teachers</span>
                        </label>
                        <label class="flex items-center space-x-2 p-3 border rounded-lg hover:bg-gray-50 cursor-pointer">
                            <input type="checkbox" name="target_roles[]" value="student" 
                                   <?php echo in_array('student', $target_roles_selected) ? 'checked' : ''; ?>
                                   class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                            <span>Students</span>
                        </label>
                        <label class="flex items-center space-x-2 p-3 border rounded-lg hover:bg-gray-50 cursor-pointer">
                            <input type="checkbox" name="target_roles[]" value="parent" 
                                   <?php echo in_array('parent', $target_roles_selected) ? 'checked' : ''; ?>
                                   class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                            <span>Parents</span>
                        </label>
                    </div>
                </div>

                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Expiration Date (Optional)</label>
                    <input type="date" name="expires_at" value="<?php echo $announcement['expires_at']; ?>"
                           class="w-full border rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>

                <div class="flex justify-end space-x-3 mt-6 pt-4 border-t">
                    <a href="index.php" class="px-4 py-2 border rounded-lg hover:bg-gray-50">Cancel</a>
                    <button type="submit" class="px-6 py-2 bg-gradient-to-r from-blue-500 to-purple-600 text-white rounded-lg hover:shadow-lg">
                        <i class="fas fa-save mr-2"></i> Update Announcement
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>