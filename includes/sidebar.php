<?php
// =====================================================
// SIDEBAR - SMART SCHOOL LMS (FULL COMPLETE)
// =====================================================

// Get current user info
$role = $_SESSION['role'] ?? 'guest';
$user_id = $_SESSION['user_id'] ?? 0;

// Base URL
$base_url = SITE_URL;

// Get unread counts
$unread_notifications = 0;
$unread_messages = 0;

if ($user_id > 0) {
    // Unread notifications
    $notif_query = $conn->prepare("SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = 0");
    $notif_query->bind_param("i", $user_id);
    $notif_query->execute();
    $unread_notifications = $notif_query->get_result()->fetch_assoc()['count'] ?? 0;
    
    // Unread messages
    $msg_query = $conn->prepare("SELECT COUNT(*) as count FROM messages WHERE receiver_id = ? AND is_read = 0");
    $msg_query->bind_param("i", $user_id);
    $msg_query->execute();
    $unread_messages = $msg_query->get_result()->fetch_assoc()['count'] ?? 0;
}

// Define menu items based on role
$menu_items = [];

switch($role) {
    case 'admin':
        $menu_items = [
            ['icon' => 'fas fa-tachometer-alt', 'label' => 'Dashboard', 'link' => $base_url . 'admin/dashboard.php'],
            ['icon' => 'fas fa-users', 'label' => 'Users', 'link' => $base_url . 'admin/users/index.php'],
            ['icon' => 'fas fa-chalkboard', 'label' => 'Classes', 'link' => $base_url . 'admin/classes/index.php'],
            ['icon' => 'fas fa-book', 'label' => 'Subjects', 'link' => $base_url . 'admin/subjects/index.php'],
            ['icon' => 'fas fa-bullhorn', 'label' => 'Announcements', 'link' => $base_url . 'admin/announcements/index.php'],
            ['icon' => 'fas fa-chart-line', 'label' => 'Reports', 'link' => $base_url . 'admin/reports/index.php'],
            ['icon' => 'fas fa-cog', 'label' => 'Settings', 'link' => $base_url . 'admin/settings/general.php'],
        ];
        break;
        
    case 'academic':
        $menu_items = [
            ['icon' => 'fas fa-tachometer-alt', 'label' => 'Dashboard', 'link' => $base_url . 'academic/dashboard.php'],
            ['icon' => 'fas fa-calendar-alt', 'label' => 'Timetable', 'link' => $base_url . 'academic/timetable/index.php'],
            ['icon' => 'fas fa-pen-alt', 'label' => 'Exams', 'link' => $base_url . 'academic/exams/index.php'],
            ['icon' => 'fas fa-file-alt', 'label' => 'Report Cards', 'link' => $base_url . 'academic/report-cards/index.php'],
            ['icon' => 'fas fa-chart-line', 'label' => 'Analytics', 'link' => $base_url . 'academic/analytics/index.php'],
            ['icon' => 'fas fa-tasks', 'label' => 'Syllabus', 'link' => $base_url . 'academic/syllabus/track.php'],
        ];
        break;
        
    case 'teacher':
        // Get teacher data for badges
        $teacher_data = null;
        if ($user_id > 0) {
            $teacher_query = $conn->prepare("SELECT id FROM teachers WHERE user_id = ?");
            $teacher_query->bind_param("i", $user_id);
            $teacher_query->execute();
            $teacher_data = $teacher_query->get_result()->fetch_assoc();
        }
        
        $menu_items = [
            ['icon' => 'fas fa-tachometer-alt', 'label' => 'Dashboard', 'link' => $base_url . 'teacher/dashboard.php'],
            ['icon' => 'fas fa-folder-open', 'label' => 'Materials', 'link' => $base_url . 'teacher/materials/index.php'],
            ['icon' => 'fas fa-tasks', 'label' => 'Assignments', 'link' => $base_url . 'teacher/assignments/index.php'],
            ['icon' => 'fas fa-calendar-check', 'label' => 'Attendance', 'link' => $base_url . 'teacher/attendance/mark.php'],
            ['icon' => 'fas fa-star', 'label' => 'Marks', 'link' => $base_url . 'teacher/marks/index.php'],
            ['icon' => 'fas fa-pen-alt', 'label' => 'Exams', 'link' => $base_url . 'teacher/exams/index.php'],
            // ✅ TIMETABLE - Teacher
            ['icon' => 'fas fa-calendar-alt', 'label' => 'Timetable', 'link' => $base_url . 'teacher/timetable/index.php'],
            ['icon' => 'fas fa-bullhorn', 'label' => 'Announcements', 'link' => $base_url . 'teacher/announcements/index.php'],
            ['icon' => 'fas fa-comments', 'label' => 'Group Discussions', 'link' => $base_url . 'teacher/discussion/index.php'],
            ['icon' => 'fas fa-code', 'label' => 'Coding Ground', 'link' => $base_url . 'teacher/coding/exercises.php'],
            ['icon' => 'fas fa-comment-dots', 'label' => 'Chat', 'link' => $base_url . 'teacher/chat/index.php'],
            ['icon' => 'fas fa-cog', 'label' => 'Settings', 'link' => $base_url . 'teacher/settings/change-password.php'],
        ];
        break;
        
    case 'student':
        // Get student data for badges
        $student_data = null;
        $pending_assignments = 0;
        $pending_exams = 0;
        
        if ($user_id > 0) {
            $student_query = $conn->prepare("SELECT id, class_id FROM students WHERE user_id = ?");
            $student_query->bind_param("i", $user_id);
            $student_query->execute();
            $student_data = $student_query->get_result()->fetch_assoc();
            
            if ($student_data) {
                // Pending assignments
                $pending_query = $conn->prepare("
                    SELECT COUNT(*) as count 
                    FROM submissions s
                    WHERE s.student_id = ? AND s.marks_obtained IS NULL
                ");
                $pending_query->bind_param("i", $student_data['id']);
                $pending_query->execute();
                $pending_assignments = $pending_query->get_result()->fetch_assoc()['count'] ?? 0;
                
                // Pending exams
                $exams_query = $conn->prepare("
                    SELECT COUNT(*) as count 
                    FROM teacher_exams te
                    WHERE te.class_id = ? 
                    AND te.is_published = 1 
                    AND te.start_date <= CURDATE() 
                    AND te.end_date >= CURDATE()
                    AND NOT EXISTS (
                        SELECT 1 FROM exam_submissions es 
                        WHERE es.exam_id = te.id AND es.student_id = ?
                    )
                ");
                $exams_query->bind_param("ii", $student_data['class_id'], $student_data['id']);
                $exams_query->execute();
                $pending_exams = $exams_query->get_result()->fetch_assoc()['count'] ?? 0;
            }
        }
        
        // ✅ STUDENT MENU ITEMS - With Timetable
        $menu_items = [
            ['icon' => 'fas fa-tachometer-alt', 'label' => 'Dashboard', 'link' => $base_url . 'student/dashboard.php'],
            ['icon' => 'fas fa-book-open', 'label' => 'Learning Materials', 'link' => $base_url . 'student/materials/index.php'],
            ['icon' => 'fas fa-pencil-alt', 'label' => 'Assignments', 'link' => $base_url . 'student/assignments/index.php', 'badge' => $pending_assignments],
            ['icon' => 'fas fa-pen-alt', 'label' => 'Exams', 'link' => $base_url . 'student/exams/index.php', 'badge' => $pending_exams],
            ['icon' => 'fas fa-bullhorn', 'label' => 'Announcements', 'link' => $base_url . 'student/announcements/index.php'],
            ['icon' => 'fas fa-comments', 'label' => 'Discussion Groups', 'link' => $base_url . 'student/discussion/index.php'],
            // ✅ TIMETABLE - Student
            ['icon' => 'fas fa-calendar-alt', 'label' => 'Timetable', 'link' => $base_url . 'student/timetable/view.php'],
            ['icon' => 'fas fa-chart-line', 'label' => 'Results', 'link' => $base_url . 'student/results/dashboard.php'],
            ['icon' => 'fas fa-user-check', 'label' => 'Attendance', 'link' => $base_url . 'student/attendance/view.php'],
            ['icon' => 'fas fa-code', 'label' => 'Coding Playground', 'link' => $base_url . 'student/coding/index.php'],
        ];
        break;
        
    case 'parent':
        $menu_items = [
            ['icon' => 'fas fa-tachometer-alt', 'label' => 'Dashboard', 'link' => $base_url . 'parent/dashboard.php'],
            ['icon' => 'fas fa-child', 'label' => 'Child Progress', 'link' => $base_url . 'parent/child-progress.php'],
            ['icon' => 'fas fa-chart-line', 'label' => 'Results', 'link' => $base_url . 'parent/results.php'],
            ['icon' => 'fas fa-calendar-check', 'label' => 'Attendance', 'link' => $base_url . 'parent/attendance.php'],
            ['icon' => 'fas fa-comments', 'label' => 'Teacher Chat', 'link' => $base_url . 'parent/teacher-chat.php'],
        ];
        break;
        
    default:
        $menu_items = [
            ['icon' => 'fas fa-tachometer-alt', 'label' => 'Dashboard', 'link' => $base_url . 'index.php'],
        ];
        break;
}

// Check if link is active
function isActive($link) {
    $current_uri = $_SERVER['REQUEST_URI'];
    $current_uri = strtok($current_uri, '?');
    $base = SITE_URL;
    $current_path = str_replace($base, '', $current_uri);
    $link_path = str_replace($base, '', $link);
    return strpos($current_path, $link_path) !== false;
}

// Helper function to get user initial
function getUserInitial() {
    $name = $_SESSION['user_name'] ?? 'User';
    $parts = explode(' ', $name);
    $initial = '';
    if (count($parts) >= 2) {
        $initial = strtoupper(substr($parts[0], 0, 1) . substr($parts[1], 0, 1));
    } else {
        $initial = strtoupper(substr($name, 0, 2));
    }
    return $initial;
}
?>

<!-- ===================================================== -->
<!-- SIDEBAR HTML -->
<!-- ===================================================== -->
<div class="w-64 bg-white shadow-lg min-h-screen fixed left-0 top-0 z-30 flex flex-col sidebar-scroll">
    <!-- Logo Area -->
    <div class="p-6 border-b">
        <div class="flex items-center space-x-3">
            <?php
            $logo_query = $conn->query("SELECT school_logo FROM school_settings LIMIT 1");
            $logo_row = $logo_query->fetch_assoc();
            $logo_path = '';
            if (!empty($logo_row['school_logo'])) {
                if (file_exists('../' . $logo_row['school_logo'])) {
                    $logo_path = '../' . $logo_row['school_logo'];
                } elseif (file_exists($logo_row['school_logo'])) {
                    $logo_path = $logo_row['school_logo'];
                }
            }
            ?>
            <?php if($logo_path && file_exists($logo_path)): ?>
                <img src="<?php echo $logo_path; ?>" alt="Logo" class="w-10 h-10 rounded-xl object-cover">
            <?php else: ?>
                <div class="w-10 h-10 bg-gradient-to-r from-blue-500 to-purple-600 rounded-xl flex items-center justify-center shadow-md">
                    <i class="fas fa-graduation-cap text-white text-xl"></i>
                </div>
            <?php endif; ?>
            <div>
                <h2 class="font-bold text-gray-800 text-lg"><?php echo SITE_NAME ?? 'Smart School'; ?></h2>
                <p class="text-xs text-gray-500">LMS System</p>
            </div>
        </div>
    </div>
    
    <!-- User Info -->
    <div class="p-4 border-b bg-gradient-to-r from-blue-50 to-purple-50">
        <div class="flex items-center space-x-3">
            <div class="w-12 h-12 bg-gradient-to-r from-blue-500 to-purple-600 rounded-full flex items-center justify-center text-white font-bold text-lg shadow-md">
                <?php echo getUserInitial(); ?>
            </div>
            <div class="flex-1 min-w-0">
                <p class="font-semibold text-sm text-gray-800 truncate"><?php echo $_SESSION['user_name'] ?? 'User'; ?></p>
                <p class="text-xs text-gray-500 capitalize"><?php echo ucfirst($role); ?></p>
            </div>
        </div>
    </div>
    
    <!-- Navigation Menu -->
    <nav class="flex-1 overflow-y-auto py-4 sidebar-scroll">
        <div class="px-3 space-y-1">
            <?php foreach($menu_items as $item): 
                $active = isActive($item['link']);
            ?>
                <a href="<?php echo $item['link']; ?>" 
                   class="sidebar-item flex items-center px-3 py-3 rounded-xl transition-all duration-200 group
                   <?php echo $active ? 'active' : 'text-gray-700 hover:bg-gray-100'; ?>">
                    <i class="<?php echo $item['icon']; ?> w-5 text-lg <?php echo $active ? 'text-white' : 'text-gray-500 group-hover:text-blue-500'; ?>"></i>
                    <span class="font-medium ml-3 <?php echo $active ? 'text-white' : 'text-gray-700'; ?>"><?php echo $item['label']; ?></span>
                    <?php if(isset($item['badge']) && $item['badge'] > 0): ?>
                        <span class="ml-auto bg-red-500 text-white text-xs font-bold rounded-full px-2 py-0.5 animate-pulse">
                            <?php echo $item['badge'] > 99 ? '99+' : $item['badge']; ?>
                        </span>
                    <?php endif; ?>
                </a>
            <?php endforeach; ?>
        </div>
        
        <!-- Notification Section - Moved Up -->
        <div class="mt-4 px-3">
            <div class="border-t pt-4">
                <!-- Notifications Link -->
                <a href="<?php echo $base_url; ?>notifications/index.php" 
                   class="sidebar-item flex items-center justify-between px-3 py-3 rounded-xl transition-all duration-200 text-gray-700 hover:bg-gray-100">
                    <div class="flex items-center space-x-3">
                        <i class="fas fa-bell w-5 text-gray-500"></i>
                        <span>Notifications</span>
                    </div>
                    <?php if($unread_notifications > 0): ?>
                        <span class="bg-red-500 text-white text-xs font-bold rounded-full px-2 py-0.5 animate-pulse">
                            <?php echo $unread_notifications > 99 ? '99+' : $unread_notifications; ?>
                        </span>
                    <?php else: ?>
                        <span class="text-xs text-gray-400">0</span>
                    <?php endif; ?>
                </a>
                
                <!-- Messages Link -->
                <a href="<?php echo $base_url . $role; ?>/chat/index.php" 
                   class="sidebar-item flex items-center justify-between px-3 py-3 rounded-xl transition-all duration-200 text-gray-700 hover:bg-gray-100">
                    <div class="flex items-center space-x-3">
                        <i class="fas fa-envelope w-5 text-gray-500"></i>
                        <span>Messages</span>
                    </div>
                    <?php if($unread_messages > 0): ?>
                        <span class="bg-blue-500 text-white text-xs font-bold rounded-full px-2 py-0.5 animate-pulse">
                            <?php echo $unread_messages > 99 ? '99+' : $unread_messages; ?>
                        </span>
                    <?php else: ?>
                        <span class="text-xs text-gray-400">0</span>
                    <?php endif; ?>
                </a>
            </div>
        </div>
    </nav>
    
    <!-- Footer / Logout -->
    <div class="p-4 border-t bg-gray-50">
        <a href="<?php echo $base_url; ?>auth/logout.php" 
           class="flex items-center justify-center space-x-2 px-4 py-3 rounded-xl bg-red-50 text-red-600 hover:bg-red-100 transition-all duration-200">
            <i class="fas fa-sign-out-alt"></i>
            <span class="font-medium">Logout</span>
        </a>
        <div class="text-center mt-3">
            <p class="text-xs text-gray-400">&copy; <?php echo date('Y'); ?> <?php echo SITE_NAME ?? 'Smart School'; ?></p>
            <p class="text-xs text-gray-400">Version 2.0</p>
        </div>
    </div>
</div>

<style>
/* Sidebar Styles */
.sidebar-scroll {
    scroll-behavior: smooth;
}

.sidebar-item {
    transition: all 0.2s ease;
    position: relative;
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

.sidebar-item.active::before {
    content: '';
    position: absolute;
    left: 0;
    top: 50%;
    transform: translateY(-50%);
    width: 4px;
    height: 60%;
    background: white;
    border-radius: 0 4px 4px 0;
}

/* Scrollbar styling */
.sidebar-scroll::-webkit-scrollbar {
    width: 4px;
}

.sidebar-scroll::-webkit-scrollbar-track {
    background: #f1f1f1;
    border-radius: 10px;
}

.sidebar-scroll::-webkit-scrollbar-thumb {
    background: #c7d2fe;
    border-radius: 10px;
}

.sidebar-scroll::-webkit-scrollbar-thumb:hover {
    background: #818cf8;
}

/* Badge animation */
@keyframes pulse-badge {
    0%, 100% { transform: scale(1); }
    50% { transform: scale(1.1); }
}

.animate-pulse {
    animation: pulse-badge 1.5s ease-in-out infinite;
}

/* Mobile responsive */
@media (max-width: 768px) {
    .w-64 {
        width: 72px;
    }
    .sidebar-item span {
        display: none;
    }
    .sidebar-item i {
        margin-right: 0;
    }
    .sidebar .sidebar-logo-text {
        display: none;
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    console.log('✅ Sidebar loaded successfully');
    console.log('📊 Unread notifications: <?php echo $unread_notifications; ?>');
    console.log('💬 Unread messages: <?php echo $unread_messages; ?>');
    console.log('👤 User role: <?php echo $role; ?>');
});
</script>