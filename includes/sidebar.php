<?php
// Dynamic sidebar based on user role
$current_page = basename($_SERVER['PHP_SELF']);
$current_dir = basename(dirname($_SERVER['PHP_SELF']));
$role = $_SESSION['role'] ?? 'guest';
$user_id = $_SESSION['user_id'] ?? 0;

// Base URL for links
// $base_url = '/smart-school-lms/';
$base_url = 'http://localhost/PROJECTS/smart-school-lms/smart-school-lms/';
// Get unread counts for badges
$unread_notifications = 0;
$unread_messages = 0;

if ($user_id > 0) {
    // Get unread notifications count
    $notif_query = $conn->prepare("SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = 0");
    $notif_query->bind_param("i", $user_id);
    $notif_query->execute();
    $unread_notifications = $notif_query->get_result()->fetch_assoc()['count'] ?? 0;
    
    // Get unread messages count
    $msg_query = $conn->prepare("SELECT COUNT(*) as count FROM messages WHERE receiver_id = ? AND is_read = 0");
    $msg_query->bind_param("i", $user_id);
    $msg_query->execute();
    $unread_messages = $msg_query->get_result()->fetch_assoc()['count'] ?? 0;
}

$menu_items = [];

switch($role) {
    case 'admin':
        $menu_items = [
            ['icon' => 'fas fa-tachometer-alt', 'label' => 'Dashboard', 'link' => $base_url . 'admin/dashboard.php', 'badge' => 0],
            ['icon' => 'fas fa-users', 'label' => 'Users', 'link' => $base_url . 'admin/users/index.php', 'badge' => 0],
            ['icon' => 'fas fa-chalkboard', 'label' => 'Classes', 'link' => $base_url . 'admin/classes/index.php', 'badge' => 0],
            ['icon' => 'fas fa-book', 'label' => 'Subjects', 'link' => $base_url . 'admin/subjects/index.php', 'badge' => 0],
            ['icon' => 'fas fa-bullhorn', 'label' => 'Announcements', 'link' => $base_url . 'admin/announcements/index.php', 'badge' => 0],
            ['icon' => 'fas fa-chart-line', 'label' => 'Reports', 'link' => $base_url . 'admin/reports/index.php', 'badge' => 0],
            ['icon' => 'fas fa-cog', 'label' => 'Settings', 'link' => $base_url . 'admin/settings/general.php', 'badge' => 0],
        ];
        break;
        
    case 'academic':
        $menu_items = [
            ['icon' => 'fas fa-tachometer-alt', 'label' => 'Dashboard', 'link' => $base_url . 'academic/dashboard.php', 'badge' => 0],
            ['icon' => 'fas fa-calendar-alt', 'label' => 'Timetable', 'link' => $base_url . 'academic/timetable/index.php', 'badge' => 0],
            ['icon' => 'fas fa-pen-alt', 'label' => 'Exams', 'link' => $base_url . 'academic/exams/index.php', 'badge' => 0],
            ['icon' => 'fas fa-file-alt', 'label' => 'Report Cards', 'link' => $base_url . 'academic/report-cards/index.php', 'badge' => 0],
            ['icon' => 'fas fa-chart-line', 'label' => 'Analytics', 'link' => $base_url . 'academic/analytics/index.php', 'badge' => 0],
            ['icon' => 'fas fa-tasks', 'label' => 'Syllabus', 'link' => $base_url . 'academic/syllabus/track.php', 'badge' => 0],
        ];
        break;
        
    case 'teacher':
    // Get unread chat messages
    $unread_messages = 0;
    $chat_query = $conn->prepare("SELECT COUNT(*) as count FROM messages WHERE receiver_id = ? AND is_read = 0");
    $chat_query->bind_param("i", $user_id);
    $chat_query->execute();
    $unread_messages = $chat_query->get_result()->fetch_assoc()['count'] ?? 0;
    
    $menu_items = [
        ['icon' => 'fas fa-tachometer-alt', 'label' => 'Dashboard', 'link' => $base_url . 'teacher/dashboard.php', 'badge' => 0],
        ['icon' => 'fas fa-folder-open', 'label' => 'Materials', 'link' => $base_url . 'teacher/materials/index.php', 'badge' => 0],
        ['icon' => 'fas fa-tasks', 'label' => 'Assignments', 'link' => $base_url . 'teacher/assignments/index.php', 'badge' => 0],
        ['icon' => 'fas fa-calendar-check', 'label' => 'Attendance', 'link' => $base_url . 'teacher/attendance/mark.php', 'badge' => 0],
        ['icon' => 'fas fa-star', 'label' => 'Marks', 'link' => $base_url . 'teacher/marks/index.php', 'badge' => 0],
        ['icon' => 'fas fa-pen-alt', 'label' => 'Exams', 'link' => $base_url . 'teacher/exams/index.php', 'badge' => 0],
        ['icon' => 'fas fa-comments', 'label' => 'Chat', 'link' => $base_url . 'teacher/chat/index.php', 'badge' => $unread_messages],
        ['icon' => 'fas fa-cog', 'label' => 'Settings', 'link' => $base_url . 'teacher/settings/change-password.php', 'badge' => 0],
    ];
    break;
        
    case 'student':
        // Get pending assignments count for student
        $pending_assignments = 0;
        $student = null;
        if ($user_id > 0) {
            $student_query = $conn->prepare("SELECT s.id FROM students s WHERE s.user_id = ?");
            $student_query->bind_param("i", $user_id);
            $student_query->execute();
            $student = $student_query->get_result()->fetch_assoc();
            if ($student) {
                $pending_query = $conn->prepare("SELECT COUNT(*) as count FROM submissions s WHERE s.student_id = ? AND s.marks_obtained IS NULL");
                $pending_query->bind_param("i", $student['id']);
                $pending_query->execute();
                $pending_assignments = $pending_query->get_result()->fetch_assoc()['count'] ?? 0;
            }
        }
        
        // Get pending exams count for student (exams not taken yet)
        $pending_exams = 0;
        if ($user_id > 0 && $student) {
            $exams_query = $conn->prepare("
                SELECT COUNT(*) as count 
                FROM teacher_exams te
                JOIN students s ON te.class_id = s.class_id
                WHERE s.user_id = ? 
                  AND te.is_published = 1 
                  AND te.start_date <= CURDATE() 
                  AND te.end_date >= CURDATE()
                  AND NOT EXISTS (
                      SELECT 1 FROM exam_submissions es 
                      WHERE es.exam_id = te.id AND es.student_id = s.id
                  )
            ");
            $exams_query->bind_param("i", $user_id);
            $exams_query->execute();
            $pending_exams = $exams_query->get_result()->fetch_assoc()['count'] ?? 0;
        }
        
        $menu_items = [
            ['icon' => 'fas fa-tachometer-alt', 'label' => 'Dashboard', 'link' => $base_url . 'student/dashboard.php', 'badge' => 0],
            ['icon' => 'fas fa-book-open', 'label' => 'Learning Materials', 'link' => $base_url . 'student/materials/index.php', 'badge' => 0],
            ['icon' => 'fas fa-pencil-alt', 'label' => 'Assignments', 'link' => $base_url . 'student/assignments/index.php', 'badge' => $pending_assignments],
            ['icon' => 'fas fa-pen-alt', 'label' => 'Exams', 'link' => $base_url . 'student/exams/index.php', 'badge' => $pending_exams],
            ['icon' => 'fas fa-calendar-alt', 'label' => 'Timetable', 'link' => $base_url . 'student/timetable/view.php', 'badge' => 0],
            ['icon' => 'fas fa-chart-line', 'label' => 'Results', 'link' => $base_url . 'student/results/index.php', 'badge' => 0],
            ['icon' => 'fas fa-user-check', 'label' => 'Attendance', 'link' => $base_url . 'student/attendance/view.php', 'badge' => 0],
            ['icon' => 'fas fa-comments', 'label' => 'Messages', 'link' => $base_url . 'student/chat/index.php', 'badge' => $unread_messages],
        ];
        break;
        
    case 'parent':
        // Get children with pending items
        $parent_pending = 0;
        if ($user_id > 0) {
            $children_query = $conn->prepare("
                SELECT COUNT(*) as count FROM parent_student ps
                JOIN students s ON ps.student_id = s.id
                JOIN submissions sub ON s.id = sub.student_id
                WHERE ps.parent_id = (SELECT id FROM parents WHERE user_id = ?) 
                AND sub.marks_obtained IS NULL
            ");
            $children_query->bind_param("i", $user_id);
            $children_query->execute();
            $parent_pending = $children_query->get_result()->fetch_assoc()['count'] ?? 0;
        }
        
        $menu_items = [
            ['icon' => 'fas fa-tachometer-alt', 'label' => 'Dashboard', 'link' => $base_url . 'parent/dashboard.php', 'badge' => 0],
            ['icon' => 'fas fa-child', 'label' => 'Child Progress', 'link' => $base_url . 'parent/child-progress.php', 'badge' => $parent_pending],
            ['icon' => 'fas fa-chart-line', 'label' => 'Results', 'link' => $base_url . 'parent/results.php', 'badge' => 0],
            ['icon' => 'fas fa-calendar-check', 'label' => 'Attendance', 'link' => $base_url . 'parent/attendance.php', 'badge' => 0],
            ['icon' => 'fas fa-comments', 'label' => 'Teacher Chat', 'link' => $base_url . 'parent/teacher-chat.php', 'badge' => $unread_messages],
        ];
        break;
}

// Function to check if link is active
function isActive($link) {
    $current_uri = $_SERVER['REQUEST_URI'];
    // Remove query parameters for comparison
    $current_uri = strtok($current_uri, '?');
    return strpos($current_uri, $link) !== false;
}
?>

<div class="w-64 bg-white shadow-lg min-h-screen fixed left-0 top-0 z-30 flex flex-col">
    <!-- Logo Area -->
    <div class="p-6 border-b">
        <div class="flex items-center space-x-3">
            <div class="w-10 h-10 bg-gradient-to-r from-blue-500 to-purple-600 rounded-xl flex items-center justify-center shadow-md">
                <i class="fas fa-graduation-cap text-white text-xl"></i>
            </div>
            <div>
                <h2 class="font-bold text-gray-800 text-lg">Smart School</h2>
                <p class="text-xs text-gray-500">LMS System</p>
            </div>
        </div>
    </div>
    
    <!-- User Info -->
    <div class="p-4 border-b bg-gradient-to-r from-blue-50 to-purple-50">
        <div class="flex items-center space-x-3">
            <div class="w-12 h-12 bg-gradient-to-r from-blue-500 to-purple-600 rounded-full flex items-center justify-center text-white font-bold text-lg shadow-md">
                <?php echo strtoupper(substr($_SESSION['user_name'] ?? 'U', 0, 1)); ?>
            </div>
            <div class="flex-1">
                <p class="font-semibold text-sm text-gray-800 truncate"><?php echo $_SESSION['user_name'] ?? 'User'; ?></p>
                <p class="text-xs text-gray-500 capitalize"><?php echo ucfirst($role); ?></p>
            </div>
        </div>
    </div>
    
    <!-- Navigation Menu -->
    <nav class="flex-1 overflow-y-auto py-4">
        <div class="px-3 space-y-1">
            <?php foreach($menu_items as $item): 
                $active = isActive($item['link']);
                $has_badge = isset($item['badge']) && $item['badge'] > 0;
            ?>
                <a href="<?php echo $item['link']; ?>" 
                   class="sidebar-item flex items-center justify-between px-3 py-3 rounded-xl transition-all duration-200 group
                   <?php echo $active ? 'bg-gradient-to-r from-blue-500 to-purple-600 text-white shadow-md' : 'text-gray-700 hover:bg-gray-100'; ?>">
                    <div class="flex items-center space-x-3">
                        <i class="<?php echo $item['icon']; ?> w-5 text-lg <?php echo $active ? 'text-white' : 'text-gray-500 group-hover:text-blue-500'; ?>"></i>
                        <span class="font-medium <?php echo $active ? 'text-white' : 'text-gray-700'; ?>"><?php echo $item['label']; ?></span>
                    </div>
                    <?php if($has_badge): ?>
                        <span class="bg-red-500 text-white text-xs font-bold rounded-full px-2 py-0.5 min-w-[20px] text-center animate-pulse">
                            <?php echo $item['badge'] > 99 ? '99+' : $item['badge']; ?>
                        </span>
                    <?php endif; ?>
                </a>
            <?php endforeach; ?>
        </div>
        
        <!-- Notification Section -->
        <div class="mt-6 px-3">
            <div class="border-t pt-4">
                <!-- Notifications -->
                <a href="<?php echo $base_url; ?>notifications/index.php" 
                   class="sidebar-item flex items-center justify-between px-3 py-3 rounded-xl transition-all duration-200 text-gray-700 hover:bg-gray-100">
                    <div class="flex items-center space-x-3">
                        <i class="fas fa-bell w-5 text-gray-500"></i>
                        <span>Notifications</span>
                    </div>
                    <?php if($unread_notifications > 0): ?>
                        <span class="bg-red-500 text-white text-xs font-bold rounded-full px-2 py-0.5"><?php echo $unread_notifications > 99 ? '99+' : $unread_notifications; ?></span>
                    <?php endif; ?>
                </a>
            </div>
        </div>
    </nav>
    
    <!-- Footer / Logout -->
    <div class="p-4 border-t">
        <a href="<?php echo $base_url; ?>auth/logout.php" 
           class="flex items-center justify-center space-x-2 px-4 py-3 rounded-xl bg-red-50 text-red-600 hover:bg-red-100 transition-all duration-200 group">
            <i class="fas fa-sign-out-alt"></i>
            <span class="font-medium">Logout</span>
        </a>
        <div class="text-center mt-3">
            <p class="text-xs text-gray-400">&copy; <?php echo date('Y'); ?> Smart School</p>
            <p class="text-xs text-gray-400">Version 2.0</p>
        </div>
    </div>
</div>

<style>
.sidebar-item {
    transition: all 0.2s ease;
}
.sidebar-item:hover {
    transform: translateX(4px);
}
.sidebar-item.active {
    background: linear-gradient(135deg, #3b82f6, #8b5cf6);
    box-shadow: 0 4px 10px rgba(59, 130, 246, 0.3);
}
.sidebar-item.active i,
.sidebar-item.active span {
    color: white !important;
}
@keyframes pulse {
    0%, 100% { transform: scale(1); }
    50% { transform: scale(1.05); }
}
.animate-pulse {
    animation: pulse 1.5s ease-in-out infinite;
}
</style>

<script>
document.querySelectorAll('.sidebar-item').forEach(item => {
    item.addEventListener('click', function() {
        document.querySelectorAll('.sidebar-item').forEach(i => {
            i.classList.remove('active', 'bg-gradient-to-r', 'from-blue-500', 'to-purple-600', 'text-white', 'shadow-md');
            i.classList.add('text-gray-700', 'hover:bg-gray-100');
            const icon = i.querySelector('i');
            if (icon) {
                icon.classList.remove('text-white');
                icon.classList.add('text-gray-500');
            }
            const span = i.querySelector('span:not(.badge)');
            if (span) {
                span.classList.remove('text-white');
            }
        });
        this.classList.add('active', 'bg-gradient-to-r', 'from-blue-500', 'to-purple-600', 'text-white', 'shadow-md');
        this.classList.remove('text-gray-700', 'hover:bg-gray-100');
        const icon = this.querySelector('i');
        if (icon) {
            icon.classList.remove('text-gray-500');
            icon.classList.add('text-white');
        }
        const span = this.querySelector('span:not(.badge)');
        if (span) {
            span.classList.add('text-white');
        }
    });
});
</script>