<?php
require_once '../../config.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';
requireRole('teacher');

$page_title = 'Group Discussions';
include '../../includes/header.php';
include '../../includes/sidebar.php';
include '../../includes/navbar.php';

// Get teacher ID
$teacher_query = $conn->prepare("SELECT id FROM teachers WHERE user_id = ?");
$teacher_query->bind_param("i", $_SESSION['user_id']);
$teacher_query->execute();
$teacher = $teacher_query->get_result()->fetch_assoc();
$teacher_id = $teacher['id'] ?? 0;

// Handle group deletion
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $group_id = $_GET['delete'];
    
    // Delete group members first
    $delete_members = $conn->prepare("DELETE FROM group_members WHERE group_id = ?");
    $delete_members->bind_param("i", $group_id);
    $delete_members->execute();
    
    // Delete group messages
    $delete_messages = $conn->prepare("DELETE FROM group_messages WHERE group_id = ?");
    $delete_messages->bind_param("i", $group_id);
    $delete_messages->execute();
    
    // Delete group
    $delete = $conn->prepare("DELETE FROM discussion_groups WHERE id = ? AND teacher_id = ?");
    $delete->bind_param("ii", $group_id, $teacher_id);
    $delete->execute();
    
    $_SESSION['success'] = "Group deleted successfully!";
    header('Location: index.php');
    exit();
}

// Handle group status toggle
if (isset($_GET['toggle']) && is_numeric($_GET['toggle'])) {
    $group_id = $_GET['toggle'];
    $toggle = $conn->prepare("UPDATE discussion_groups SET is_active = NOT is_active WHERE id = ? AND teacher_id = ?");
    $toggle->bind_param("ii", $group_id, $teacher_id);
    $toggle->execute();
    header('Location: index.php');
    exit();
}

// Get groups with stats
$groups = $conn->prepare("
    SELECT g.*, c.name as class_name,
           (SELECT COUNT(*) FROM group_members WHERE group_id = g.id) as member_count,
           (SELECT COUNT(*) FROM group_messages WHERE group_id = g.id) as message_count,
           (SELECT MAX(created_at) FROM group_messages WHERE group_id = g.id) as last_activity
    FROM discussion_groups g
    JOIN classes c ON g.class_id = c.id
    WHERE g.teacher_id = ?
    ORDER BY g.is_active DESC, g.created_at DESC
");
$groups->bind_param("i", $teacher_id);
$groups->execute();
$groups = $groups->get_result();

// Get classes for dropdown
$classes = $conn->prepare("
    SELECT DISTINCT c.id, c.name 
    FROM class_subject cs
    JOIN classes c ON cs.class_id = c.id
    WHERE cs.teacher_id = ?
");
$classes->bind_param("i", $teacher_id);
$classes->execute();
$classes = $classes->get_result();

// Get success/error messages
$success = $_SESSION['success'] ?? '';
$error = $_SESSION['error'] ?? '';
unset($_SESSION['success'], $_SESSION['error']);
?>

<style>
.group-card {
    transition: all 0.3s ease;
}
.group-card:hover {
    transform: translateY(-5px);
}
.status-badge {
    font-size: 0.7rem;
    padding: 0.25rem 0.75rem;
    border-radius: 9999px;
}
.status-active {
    background: #d1fae5;
    color: #065f46;
}
.status-inactive {
    background: #fee2e2;
    color: #991b1b;
}
.group-stats {
    display: flex;
    gap: 1.5rem;
    font-size: 0.875rem;
    color: #6b7280;
}
.group-stats i {
    margin-right: 0.25rem;
}
</style>

<div class="ml-64 mt-16 p-6 bg-gray-50 min-h-screen">
    <div class="max-w-full mx-auto">
        <!-- Header -->
        <div class="mb-6 flex flex-wrap justify-between items-center">
            <div>
                <h1 class="text-2xl font-bold text-gray-800">💬 Group Discussions</h1>
                <p class="text-gray-500 mt-1">Create and manage discussion groups for your students</p>
            </div>
            <div class="flex gap-3 mt-3 md:mt-0">
                <a href="create.php" class="bg-gradient-to-r from-blue-500 to-purple-600 text-white px-6 py-2 rounded-lg hover:shadow-lg transition flex items-center">
                    <i class="fas fa-plus mr-2"></i> Create Group
                </a>
            </div>
        </div>

        <!-- Messages -->
        <?php if($success): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-lg mb-4">
                <i class="fas fa-check-circle mr-2"></i> <?php echo $success; ?>
            </div>
        <?php endif; ?>
        
        <?php if($error): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg mb-4">
                <i class="fas fa-exclamation-circle mr-2"></i> <?php echo $error; ?>
            </div>
        <?php endif; ?>

        <!-- Stats Summary -->
        <?php 
        $total_groups = $groups->num_rows;
        $active_groups = 0;
        $total_members = 0;
        $total_messages = 0;
        
        // Reset pointer to count stats
        if ($total_groups > 0) {
            $groups->data_seek(0);
            while($stat = $groups->fetch_assoc()) {
                if($stat['is_active']) $active_groups++;
                $total_members += $stat['member_count'];
                $total_messages += $stat['message_count'];
            }
            $groups->data_seek(0);
        }
        ?>
        
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
            <div class="bg-white rounded-xl shadow-sm p-4 flex items-center gap-4">
                <div class="bg-blue-100 p-3 rounded-lg">
                    <i class="fas fa-users text-blue-600 text-xl"></i>
                </div>
                <div>
                    <p class="text-sm text-gray-500">Total Groups</p>
                    <p class="text-2xl font-bold"><?php echo $total_groups; ?></p>
                </div>
            </div>
            <div class="bg-white rounded-xl shadow-sm p-4 flex items-center gap-4">
                <div class="bg-green-100 p-3 rounded-lg">
                    <i class="fas fa-check-circle text-green-600 text-xl"></i>
                </div>
                <div>
                    <p class="text-sm text-gray-500">Active Groups</p>
                    <p class="text-2xl font-bold"><?php echo $active_groups; ?></p>
                </div>
            </div>
            <div class="bg-white rounded-xl shadow-sm p-4 flex items-center gap-4">
                <div class="bg-purple-100 p-3 rounded-lg">
                    <i class="fas fa-user-plus text-purple-600 text-xl"></i>
                </div>
                <div>
                    <p class="text-sm text-gray-500">Total Members</p>
                    <p class="text-2xl font-bold"><?php echo $total_members; ?></p>
                </div>
            </div>
            <div class="bg-white rounded-xl shadow-sm p-4 flex items-center gap-4">
                <div class="bg-yellow-100 p-3 rounded-lg">
                    <i class="fas fa-comments text-yellow-600 text-xl"></i>
                </div>
                <div>
                    <p class="text-sm text-gray-500">Total Messages</p>
                    <p class="text-2xl font-bold"><?php echo $total_messages; ?></p>
                </div>
            </div>
        </div>

        <!-- Groups Grid -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            <?php if ($groups && $groups->num_rows > 0): ?>
                <?php while($group = $groups->fetch_assoc()): ?>
                    <div class="bg-white rounded-xl shadow-sm hover:shadow-lg transition group-card overflow-hidden">
                        <!-- Header with status -->
                        <div class="bg-gradient-to-r <?php echo $group['is_active'] ? 'from-blue-500 to-purple-600' : 'from-gray-400 to-gray-600'; ?> p-4 text-white">
                            <div class="flex justify-between items-start">
                                <div class="flex-1">
                                    <h3 class="text-lg font-bold"><?php echo htmlspecialchars($group['name']); ?></h3>
                                    <p class="text-blue-100 text-sm"><?php echo htmlspecialchars($group['class_name']); ?></p>
                                </div>
                                <span class="status-badge <?php echo $group['is_active'] ? 'status-active' : 'status-inactive'; ?>">
                                    <?php echo $group['is_active'] ? 'Active' : 'Inactive'; ?>
                                </span>
                            </div>
                        </div>
                        
                        <div class="p-4">
                            <!-- Description -->
                            <p class="text-sm text-gray-600 mb-3 line-clamp-2">
                                <?php echo htmlspecialchars($group['description'] ?: 'No description provided'); ?>
                            </p>
                            
                            <!-- Stats -->
                            <div class="group-stats">
                                <span><i class="fas fa-users"></i> <?php echo $group['member_count']; ?></span>
                                <span><i class="fas fa-comments"></i> <?php echo $group['message_count']; ?></span>
                                <?php if($group['last_activity']): ?>
                                    <span><i class="fas fa-clock"></i> <?php echo timeAgo($group['last_activity']); ?></span>
                                <?php endif; ?>
                            </div>
                            
                            <!-- Actions -->
                            <div class="mt-4 flex flex-wrap justify-end gap-2 pt-3 border-t">
                                <a href="view.php?id=<?php echo $group['id']; ?>" 
                                   class="bg-blue-600 text-white px-3 py-1.5 rounded-lg text-sm hover:bg-blue-700 flex items-center">
                                    <i class="fas fa-eye mr-1"></i> View
                                </a>
                                <a href="members.php?id=<?php echo $group['id']; ?>" 
                                   class="bg-green-600 text-white px-3 py-1.5 rounded-lg text-sm hover:bg-green-700 flex items-center">
                                    <i class="fas fa-users mr-1"></i> Members
                                </a>
                                <a href="?toggle=<?php echo $group['id']; ?>" 
                                   class="<?php echo $group['is_active'] ? 'bg-yellow-600' : 'bg-emerald-600'; ?> text-white px-3 py-1.5 rounded-lg text-sm hover:opacity-80 flex items-center">
                                    <i class="fas <?php echo $group['is_active'] ? 'fa-pause' : 'fa-play'; ?> mr-1"></i>
                                    <?php echo $group['is_active'] ? 'Deactivate' : 'Activate'; ?>
                                </a>
                                <a href="?delete=<?php echo $group['id']; ?>" 
                                   onclick="return confirm('Delete this group and all its messages?')" 
                                   class="bg-red-600 text-white px-3 py-1.5 rounded-lg text-sm hover:bg-red-700 flex items-center">
                                    <i class="fas fa-trash-alt mr-1"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="col-span-full">
                    <div class="bg-white rounded-xl shadow-sm p-12 text-center">
                        <i class="fas fa-comments text-6xl text-gray-300 mb-4"></i>
                        <h3 class="text-xl font-semibold text-gray-600">No Discussion Groups</h3>
                        <p class="text-gray-400 mt-2">Click "Create Group" to start a discussion with your students</p>
                        <a href="create.php" class="mt-4 inline-block bg-gradient-to-r from-blue-500 to-purple-600 text-white px-6 py-2 rounded-lg hover:shadow-lg transition">
                            <i class="fas fa-plus mr-2"></i> Create Your First Group
                        </a>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>