<?php
require_once '../../config.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';
requireRole('student');

$page_title = 'My Discussion Groups';
include '../../includes/header.php';
include '../../includes/sidebar.php';
include '../../includes/navbar.php';

// Get student ID
$student_query = $conn->prepare("
    SELECT s.id, s.class_id, c.name as class_name
    FROM students s
    JOIN classes c ON s.class_id = c.id
    WHERE s.user_id = ?
");
$student_query->bind_param("i", $_SESSION['user_id']);
$student_query->execute();
$student = $student_query->get_result()->fetch_assoc();

if (!$student) {
    echo "<div class='ml-64 mt-16 p-6'><div class='alert alert-danger'>Student record not found!</div></div>";
    include '../../includes/footer.php';
    exit();
}

$student_id = $student['id'];
$class_id = $student['class_id'];

// Get groups for this student's class
$groups = $conn->prepare("
    SELECT g.*, 
           c.name as class_name,
           CONCAT(u.first_name, ' ', u.last_name) as teacher_name,
           (SELECT COUNT(*) FROM group_members WHERE group_id = g.id) as member_count,
           (SELECT COUNT(*) FROM group_messages WHERE group_id = g.id) as message_count,
           (SELECT MAX(created_at) FROM group_messages WHERE group_id = g.id) as last_activity,
           (SELECT COUNT(*) FROM group_members WHERE group_id = g.id AND student_id = ?) as is_member
    FROM discussion_groups g
    JOIN classes c ON g.class_id = c.id
    JOIN teachers t ON g.teacher_id = t.id
    JOIN users u ON t.user_id = u.id
    WHERE g.class_id = ? AND g.is_active = 1
    ORDER BY is_member DESC, g.created_at DESC
");
$groups->bind_param("ii", $student_id, $class_id);
$groups->execute();
$groups = $groups->get_result();

// Handle joining a group
if (isset($_GET['join']) && is_numeric($_GET['join'])) {
    $group_id = $_GET['join'];
    
    // Check if already a member
    $check = $conn->prepare("SELECT id FROM group_members WHERE group_id = ? AND student_id = ?");
    $check->bind_param("ii", $group_id, $student_id);
    $check->execute();
    if ($check->get_result()->num_rows === 0) {
        $join = $conn->prepare("INSERT INTO group_members (group_id, student_id) VALUES (?, ?)");
        $join->bind_param("ii", $group_id, $student_id);
        $join->execute();
        $_SESSION['success'] = "You joined the group successfully!";
    } else {
        $_SESSION['error'] = "You are already a member of this group!";
    }
    header("Location: index.php");
    exit();
}

// Handle leaving a group
if (isset($_GET['leave']) && is_numeric($_GET['leave'])) {
    $group_id = $_GET['leave'];
    $leave = $conn->prepare("DELETE FROM group_members WHERE group_id = ? AND student_id = ?");
    $leave->bind_param("ii", $group_id, $student_id);
    $leave->execute();
    $_SESSION['success'] = "You left the group successfully!";
    header("Location: index.php");
    exit();
}

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
    box-shadow: 0 8px 25px rgba(0,0,0,0.1);
}
.group-card.member {
    border: 2px solid #3b82f6;
}
.group-card .status-badge {
    font-size: 0.7rem;
    padding: 0.25rem 0.75rem;
    border-radius: 9999px;
}
.group-card .status-active {
    background: #d1fae5;
    color: #065f46;
}
.group-card .status-inactive {
    background: #fee2e2;
    color: #991b1b;
}
.group-card .member-badge {
    background: #dbeafe;
    color: #1e40af;
    font-size: 0.7rem;
    padding: 0.2rem 0.6rem;
    border-radius: 9999px;
}
</style>

<div class="ml-64 mt-16 p-6 bg-gray-50 min-h-screen">
    <div class="max-w-6xl mx-auto">
        <!-- Header -->
        <div class="mb-6">
            <h1 class="text-2xl font-bold text-gray-800">💬 My Discussion Groups</h1>
            <p class="text-gray-500 mt-1">Join discussions with your classmates and teachers</p>
            <div class="mt-2 text-sm text-gray-500">
                <i class="fas fa-graduation-cap mr-1"></i> Class: <?php echo htmlspecialchars($student['class_name']); ?>
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

        <!-- Groups Grid -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            <?php if ($groups && $groups->num_rows > 0): ?>
                <?php while($group = $groups->fetch_assoc()): 
                    $is_member = $group['is_member'] > 0;
                    
                    // ✅ FIX: Use timeAgo function safely
                    if (!function_exists('timeAgo')) {
                        function timeAgo($timestamp) {
                            if (empty($timestamp)) return 'No activity';
                            if (is_string($timestamp)) $timestamp = strtotime($timestamp);
                            $diff = time() - $timestamp;
                            if ($diff < 60) return 'Just now';
                            if ($diff < 3600) return floor($diff / 60) . 'm ago';
                            if ($diff < 86400) return floor($diff / 3600) . 'h ago';
                            if ($diff < 604800) return floor($diff / 86400) . 'd ago';
                            if ($diff < 2592000) return floor($diff / 604800) . 'w ago';
                            return date('M d, Y', $timestamp);
                        }
                    }
                    
                    $last_activity = $group['last_activity'] ? timeAgo($group['last_activity']) : 'No messages yet';
                ?>
                    <div class="group-card <?php echo $is_member ? 'member' : ''; ?> bg-white rounded-xl shadow-sm overflow-hidden">
                        <!-- Header -->
                        <div class="bg-gradient-to-r from-blue-500 to-purple-600 p-4 text-white">
                            <div class="flex justify-between items-start">
                                <div class="flex-1">
                                    <h3 class="text-lg font-bold"><?php echo htmlspecialchars($group['name']); ?></h3>
                                    <p class="text-blue-100 text-sm"><?php echo htmlspecialchars($group['class_name']); ?></p>
                                </div>
                                <?php if($is_member): ?>
                                    <span class="member-badge">
                                        <i class="fas fa-check mr-1"></i> Member
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="p-4">
                            <!-- Description -->
                            <p class="text-sm text-gray-600 mb-3 line-clamp-2">
                                <?php echo htmlspecialchars($group['description'] ?: 'No description provided'); ?>
                            </p>
                            
                            <!-- Teacher -->
                            <p class="text-xs text-gray-500 mb-2">
                                <i class="fas fa-user-tie mr-1"></i> Teacher: <?php echo htmlspecialchars($group['teacher_name']); ?>
                            </p>
                            
                            <!-- Stats -->
                            <div class="flex justify-between text-sm text-gray-500 border-t pt-3">
                                <span><i class="fas fa-users mr-1"></i> <?php echo $group['member_count']; ?> members</span>
                                <span><i class="fas fa-comments mr-1"></i> <?php echo $group['message_count']; ?> messages</span>
                                <span><i class="fas fa-clock mr-1"></i> <?php echo $last_activity; ?></span>
                            </div>
                            
                            <!-- Actions -->
                            <div class="mt-4 flex gap-2 pt-3 border-t">
                                <?php if($is_member): ?>
                                    <a href="view.php?id=<?php echo $group['id']; ?>" 
                                       class="flex-1 bg-blue-600 text-white px-4 py-2 rounded-lg text-sm hover:bg-blue-700 transition text-center">
                                        <i class="fas fa-comment mr-1"></i> View Discussion
                                    </a>
                                    <a href="?leave=<?php echo $group['id']; ?>" 
                                       onclick="return confirm('Leave this group?')"
                                       class="bg-red-500 text-white px-3 py-2 rounded-lg text-sm hover:bg-red-600 transition">
                                        <i class="fas fa-sign-out-alt"></i>
                                    </a>
                                <?php else: ?>
                                    <a href="?join=<?php echo $group['id']; ?>" 
                                       class="flex-1 bg-green-600 text-white px-4 py-2 rounded-lg text-sm hover:bg-green-700 transition text-center">
                                        <i class="fas fa-plus mr-1"></i> Join Group
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="col-span-full">
                    <div class="bg-white rounded-xl shadow-sm p-12 text-center">
                        <i class="fas fa-comments text-6xl text-gray-300 mb-4"></i>
                        <h3 class="text-xl font-semibold text-gray-600">No Discussion Groups Available</h3>
                        <p class="text-gray-400 mt-2">Your teacher hasn't created any discussion groups for your class yet.</p>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>