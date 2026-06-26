<?php
require_once '../../config.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';
requireRole('admin');

$page_title = 'Announcements';
include '../../includes/header.php';
include '../../includes/sidebar.php';
include '../../includes/navbar.php';

// Handle announcement deletion
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $announcement_id = $_GET['delete'];
    
    $query = "DELETE FROM announcements WHERE id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $announcement_id);
    
    if ($stmt->execute()) {
        logActivity($_SESSION['user_id'], 'deleted announcement', 'announcement', $announcement_id);
        echo '<script>showToast("Announcement deleted successfully", "success");</script>';
    } else {
        echo '<script>showToast("Failed to delete announcement", "error");</script>';
    }
}

// Get all announcements
$query = "SELECT a.*, CONCAT(u.first_name, ' ', u.last_name) as creator_name 
          FROM announcements a 
          JOIN users u ON a.created_by = u.id 
          ORDER BY a.created_at DESC";
$announcements = $conn->query($query);
?>

<div class="ml-64 mt-16 p-6">
    <!-- Page Header -->
    <div class="flex justify-between items-center mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-800">Announcements</h1>
            <p class="text-gray-500 mt-1">Create and manage school announcements</p>
        </div>
        <a href="create.php" class="bg-gradient-to-r from-blue-500 to-purple-600 text-white px-6 py-2 rounded-lg hover:shadow-lg transition-all duration-300">
            <i class="fas fa-plus mr-2"></i> New Announcement
        </a>
    </div>

    <!-- Announcements List -->
    <div class="space-y-4">
        <?php if ($announcements && $announcements->num_rows > 0): ?>
            <?php while($announcement = $announcements->fetch_assoc()): ?>
                <div class="bg-white rounded-xl shadow-sm hover:shadow-md transition-all duration-300 overflow-hidden">
                    <div class="p-6">
                        <div class="flex justify-between items-start">
                            <div class="flex-1">
                                <div class="flex items-center space-x-3 mb-2">
                                    <div class="w-10 h-10 bg-gradient-to-r from-blue-500 to-purple-600 rounded-full flex items-center justify-center">
                                        <i class="fas fa-bullhorn text-white"></i>
                                    </div>
                                    <div>
                                        <h3 class="text-lg font-semibold text-gray-800"><?php echo htmlspecialchars($announcement['title']); ?></h3>
                                        <div class="flex items-center space-x-3 text-sm text-gray-500">
                                            <span><i class="fas fa-user mr-1"></i> <?php echo htmlspecialchars($announcement['creator_name']); ?></span>
                                            <span><i class="fas fa-clock mr-1"></i> <?php echo getTimeAgo($announcement['created_at']); ?></span>
                                            <?php if($announcement['expires_at']): ?>
                                                <span><i class="fas fa-calendar-times mr-1"></i> Expires: <?php echo date('M d, Y', strtotime($announcement['expires_at'])); ?></span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="mt-3 text-gray-600">
                                    <?php echo nl2br(htmlspecialchars($announcement['content'])); ?>
                                </div>
                                
                                <div class="mt-3">
                                    <span class="text-sm text-gray-500">Target:</span>
                                    <?php 
                                    $target_roles = json_decode($announcement['target_roles'], true);
                                    if ($target_roles && is_array($target_roles)):
                                        foreach($target_roles as $role):
                                    ?>
                                        <span class="ml-2 px-2 py-1 text-xs rounded-full 
                                            <?php echo $role == 'admin' ? 'bg-red-100 text-red-700' : 
                                                     ($role == 'teacher' ? 'bg-green-100 text-green-700' :
                                                     ($role == 'student' ? 'bg-blue-100 text-blue-700' : 'bg-purple-100 text-purple-700')); ?>">
                                            <?php echo ucfirst($role); ?>
                                        </span>
                                    <?php 
                                        endforeach;
                                    endif;
                                    ?>
                                </div>
                            </div>
                            
                            <div class="flex space-x-2">
                                <a href="?delete=<?php echo $announcement['id']; ?>" 
                                   onclick="return confirm('Are you sure you want to delete this announcement?')"
                                   class="text-red-600 hover:text-red-800 p-2">
                                    <i class="fas fa-trash-alt"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <div class="bg-white rounded-xl shadow-sm p-12 text-center">
                <i class="fas fa-bullhorn text-6xl text-gray-300 mb-4"></i>
                <h3 class="text-xl font-semibold text-gray-600">No Announcements Yet</h3>
                <p class="text-gray-400 mt-2">Click "New Announcement" to create your first announcement</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>