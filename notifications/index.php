<?php
require_once '../config.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ' . SITE_URL . 'index.php');
    exit();
}

$page_title = 'Notifications';
include '../includes/header.php';
include '../includes/sidebar.php';
include '../includes/navbar.php';

$user_id = $_SESSION['user_id'];

// ============================================
// HELPER FUNCTION - Get notification icon
// ============================================
function getNotificationIcon($type) {
    $icons = [
        'assignment' => ['icon' => 'fa-tasks', 'color' => 'text-green-500'],
        'exam' => ['icon' => 'fa-pen-alt', 'color' => 'text-red-500'],
        'message' => ['icon' => 'fa-envelope', 'color' => 'text-blue-500'],
        'attendance' => ['icon' => 'fa-calendar-check', 'color' => 'text-yellow-500'],
        'announcement' => ['icon' => 'fa-bullhorn', 'color' => 'text-purple-500'],
        'result' => ['icon' => 'fa-chart-line', 'color' => 'text-indigo-500'],
        'default' => ['icon' => 'fa-bell', 'color' => 'text-gray-500']
    ];
    return $icons[$type] ?? $icons['default'];
}

// ============================================
// HANDLE ACTIONS - FIXED (without read_at)
// ============================================

// Mark single notification as read
if (isset($_GET['read']) && is_numeric($_GET['read'])) {
    $notif_id = intval($_GET['read']);
    $update = $conn->query("UPDATE notifications SET is_read = 1 WHERE id = $notif_id AND user_id = $user_id");
    header('Location: index.php');
    exit();
}

// Mark selected notifications as read (bulk)
if (isset($_POST['mark_read']) && isset($_POST['selected'])) {
    $selected = $_POST['selected'];
    if (!empty($selected) && is_array($selected)) {
        $ids = implode(',', array_map('intval', $selected));
        $update = $conn->query("UPDATE notifications SET is_read = 1 WHERE id IN ($ids) AND user_id = $user_id");
        header('Location: index.php?success=Selected notifications marked as read');
        exit();
    }
}

// Mark all as read
if (isset($_GET['mark_all_read'])) {
    $update = $conn->query("UPDATE notifications SET is_read = 1 WHERE user_id = $user_id AND is_read = 0");
    header('Location: index.php?success=All notifications marked as read');
    exit();
}

// Delete single notification
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $notif_id = intval($_GET['delete']);
    $delete = $conn->query("DELETE FROM notifications WHERE id = $notif_id AND user_id = $user_id");
    header('Location: index.php?success=Notification deleted');
    exit();
}

// Delete selected notifications (bulk)
if (isset($_POST['delete_selected']) && isset($_POST['selected'])) {
    $selected = $_POST['selected'];
    if (!empty($selected) && is_array($selected)) {
        $ids = implode(',', array_map('intval', $selected));
        $delete = $conn->query("DELETE FROM notifications WHERE id IN ($ids) AND user_id = $user_id");
        header('Location: index.php?success=Selected notifications deleted');
        exit();
    }
}

// Delete all notifications
if (isset($_GET['delete_all'])) {
    $delete = $conn->query("DELETE FROM notifications WHERE user_id = $user_id");
    header('Location: index.php?success=All notifications deleted');
    exit();
}

// ============================================
// GET NOTIFICATIONS
// ============================================
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$limit = 20;
$offset = ($page - 1) * $limit;

// Count total notifications
$count_result = $conn->query("SELECT COUNT(*) as total FROM notifications WHERE user_id = $user_id");
$total = $count_result->fetch_assoc()['total'] ?? 0;
$total_pages = $total > 0 ? ceil($total / $limit) : 1;

// Get notifications
$notifications = $conn->query("
    SELECT * FROM notifications 
    WHERE user_id = $user_id 
    ORDER BY created_at DESC 
    LIMIT $limit OFFSET $offset
");

// Get unread count
$unread_result = $conn->query("SELECT COUNT(*) as count FROM notifications WHERE user_id = $user_id AND is_read = 0");
$unread_count = $unread_result->fetch_assoc()['count'] ?? 0;
?>

<div class="ml-64 mt-16 p-6 bg-gray-50 min-h-screen">
    <div class="max-w-4xl mx-auto">
        <!-- Header -->
        <div class="mb-6 flex justify-between items-center">
            <div>
                <h1 class="text-2xl font-bold text-gray-800">Notifications</h1>
                <p class="text-gray-500 mt-1">Stay updated with your recent activities</p>
            </div>
            <div class="flex space-x-2">
                <?php if($total > 0): ?>
                    <button onclick="toggleSelectAll()" class="bg-gray-600 text-white px-4 py-2 rounded-lg hover:bg-gray-700 text-sm">
                        <i class="fas fa-check-double mr-2"></i> Select All
                    </button>
                    <?php if($unread_count > 0): ?>
                        <a href="?mark_all_read=1" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 text-sm">
                            <i class="fas fa-check-double mr-2"></i> Mark All Read
                        </a>
                    <?php endif; ?>
                    <a href="?delete_all=1" onclick="return confirm('Are you sure you want to delete ALL notifications? This cannot be undone.')" 
                       class="bg-red-600 text-white px-4 py-2 rounded-lg hover:bg-red-700 text-sm">
                        <i class="fas fa-trash-alt mr-2"></i> Delete All
                    </a>
                <?php endif; ?>
            </div>
        </div>

        <!-- Success/Error Messages -->
        <?php if(isset($_GET['success'])): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-lg mb-4">
                <i class="fas fa-check-circle mr-2"></i> <?php echo htmlspecialchars($_GET['success']); ?>
            </div>
        <?php endif; ?>

        <!-- Stats -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
            <div class="bg-white rounded-xl p-4 shadow-sm">
                <p class="text-gray-500 text-sm">Total Notifications</p>
                <p class="text-2xl font-bold"><?php echo $total; ?></p>
            </div>
            <div class="bg-white rounded-xl p-4 shadow-sm">
                <p class="text-gray-500 text-sm">Unread</p>
                <p class="text-2xl font-bold text-red-600"><?php echo $unread_count; ?></p>
            </div>
            <div class="bg-white rounded-xl p-4 shadow-sm">
                <p class="text-gray-500 text-sm">Read</p>
                <p class="text-2xl font-bold text-green-600"><?php echo $total - $unread_count; ?></p>
            </div>
        </div>

        <!-- Bulk Actions -->
        <?php if($total > 0): ?>
        <div class="bg-white rounded-xl shadow-sm p-4 mb-4">
            <form method="POST" id="bulkForm" class="flex flex-wrap items-center gap-3">
                <span class="text-sm text-gray-600 font-medium">Bulk Actions:</span>
                <button type="submit" name="mark_read" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 text-sm">
                    <i class="fas fa-check mr-2"></i> Mark Selected as Read
                </button>
                <button type="submit" name="delete_selected" class="bg-red-600 text-white px-4 py-2 rounded-lg hover:bg-red-700 text-sm" 
                        onclick="return confirm('Delete selected notifications?')">
                    <i class="fas fa-trash-alt mr-2"></i> Delete Selected
                </button>
                <span class="text-sm text-gray-400" id="selectedCount">0 selected</span>
            </form>
        </div>
        <?php endif; ?>

        <!-- Notifications List -->
        <div class="bg-white rounded-xl shadow-sm overflow-hidden">
            <?php if ($notifications && $notifications->num_rows > 0): ?>
                <div class="divide-y divide-gray-200">
                    <?php while($notif = $notifications->fetch_assoc()): 
                        $is_unread = $notif['is_read'] == 0;
                        $icon = getNotificationIcon($notif['type']);
                        $bg_class = $is_unread ? 'bg-blue-50 border-l-4 border-l-blue-500' : 'hover:bg-gray-50';
                    ?>
                        <div class="p-4 <?php echo $bg_class; ?> transition-all">
                            <div class="flex items-start">
                                <!-- Checkbox -->
                                <div class="mr-3 mt-1">
                                    <input type="checkbox" name="selected[]" value="<?php echo $notif['id']; ?>" 
                                           class="notification-checkbox rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                                           onchange="updateSelectedCount()">
                                </div>
                                <!-- Content -->
                                <div class="flex-1">
                                    <div class="flex items-start space-x-3">
                                        <div class="mt-1">
                                            <i class="fas <?php echo $icon['icon']; ?> <?php echo $icon['color']; ?> text-lg"></i>
                                        </div>
                                        <div>
                                            <p class="font-semibold text-gray-800 <?php echo $is_unread ? 'font-bold' : ''; ?>">
                                                <?php echo htmlspecialchars($notif['title']); ?>
                                            </p>
                                            <p class="text-sm text-gray-600 mt-1"><?php echo nl2br(htmlspecialchars($notif['message'])); ?></p>
                                            <p class="text-xs text-gray-400 mt-2">
                                                <i class="far fa-clock mr-1"></i> <?php echo getTimeAgo($notif['created_at']); ?>
                                                <?php if($is_unread): ?>
                                                    <span class="ml-2 px-2 py-0.5 bg-red-100 text-red-700 text-xs rounded-full">New</span>
                                                <?php endif; ?>
                                            </p>
                                        </div>
                                    </div>
                                </div>
                                <!-- Actions -->
                                <div class="flex space-x-2 ml-4 flex-shrink-0">
                                    <?php if($is_unread): ?>
                                        <a href="?read=<?php echo $notif['id']; ?>" class="text-blue-600 hover:text-blue-800" title="Mark as read">
                                            <i class="fas fa-check"></i>
                                        </a>
                                    <?php endif; ?>
                                    <?php if($notif['link']): ?>
                                        <a href="<?php echo $notif['link']; ?>" class="text-green-600 hover:text-green-800" title="View">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                    <?php endif; ?>
                                    <a href="?delete=<?php echo $notif['id']; ?>" onclick="return confirm('Delete this notification?')" class="text-red-600 hover:text-red-800" title="Delete">
                                        <i class="fas fa-trash-alt"></i>
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>
                
                <!-- Pagination -->
                <?php if($total_pages > 1): ?>
                <div class="px-6 py-4 border-t bg-gray-50 flex justify-center space-x-2">
                    <?php for($i = 1; $i <= $total_pages; $i++): ?>
                        <a href="?page=<?php echo $i; ?>" 
                           class="px-3 py-1 rounded <?php echo $i == $page ? 'bg-blue-600 text-white' : 'bg-gray-200 text-gray-700 hover:bg-gray-300'; ?>">
                            <?php echo $i; ?>
                        </a>
                    <?php endfor; ?>
                </div>
                <?php endif; ?>
            <?php else: ?>
                <div class="p-12 text-center">
                    <i class="fas fa-bell-slash text-6xl text-gray-300 mb-4"></i>
                    <h3 class="text-xl font-semibold text-gray-600">No Notifications</h3>
                    <p class="text-gray-400 mt-2">You're all caught up!</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
// ============================================
// NOTIFICATION FUNCTIONS
// ============================================

// Toggle select all checkboxes
function toggleSelectAll() {
    const checkboxes = document.querySelectorAll('.notification-checkbox');
    const allChecked = Array.from(checkboxes).every(cb => cb.checked);
    checkboxes.forEach(cb => cb.checked = !allChecked);
    updateSelectedCount();
}

// Update selected count
function updateSelectedCount() {
    const checkboxes = document.querySelectorAll('.notification-checkbox:checked');
    const countEl = document.getElementById('selectedCount');
    if (countEl) {
        countEl.textContent = checkboxes.length + ' selected';
    }
}

// Auto-select/unselect individual checkboxes
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.notification-checkbox').forEach(cb => {
        cb.addEventListener('change', updateSelectedCount);
    });
    // Initialize count
    updateSelectedCount();
});

// Confirm before bulk delete
document.querySelector('form#bulkForm')?.addEventListener('submit', function(e) {
    const checkboxes = document.querySelectorAll('.notification-checkbox:checked');
    if (checkboxes.length === 0) {
        e.preventDefault();
        alert('Please select at least one notification');
        return false;
    }
    
    // If deleting selected
    if (e.submitter && e.submitter.name === 'delete_selected') {
        if (!confirm('Delete ' + checkboxes.length + ' selected notifications?')) {
            e.preventDefault();
            return false;
        }
    }
});

console.log('✅ Notification system loaded');
console.log('📊 Total notifications: <?php echo $total; ?>');
console.log('🔴 Unread: <?php echo $unread_count; ?>');
</script>

<?php include '../includes/footer.php'; ?>