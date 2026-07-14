<?php
require_once '../config.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
requireRole('teacher');

$page_title = 'Teacher Dashboard';
include '../includes/header.php';
include '../includes/sidebar.php';
include '../includes/navbar.php';

// Get teacher ID
$teacher_query = $conn->prepare("SELECT id FROM teachers WHERE user_id = ?");
$teacher_query->bind_param("i", $_SESSION['user_id']);
$teacher_query->execute();
$teacher = $teacher_query->get_result()->fetch_assoc();
$teacher_id = $teacher['id'] ?? 0;

// Get classes
$classes_query = $conn->prepare("
    SELECT DISTINCT c.id, c.name, c.code
    FROM class_subject cs
    JOIN classes c ON cs.class_id = c.id
    WHERE cs.teacher_id = ?
");
$classes_query->bind_param("i", $teacher_id);
$classes_query->execute();
$classes = $classes_query->get_result();
$class_ids = [];
while($cls = $classes->fetch_assoc()) {
    $class_ids[] = $cls['id'];
}
$classes->data_seek(0);

// Statistics
$total_students = 0;
if (!empty($class_ids)) {
    $ids = implode(',', $class_ids);
    $students_query = $conn->query("SELECT COUNT(DISTINCT s.id) as count FROM students s WHERE s.class_id IN ($ids)");
    $total_students = $students_query->fetch_assoc()['count'] ?? 0;
}

// Online students
$online_query = $conn->prepare("
    SELECT COUNT(*) as count 
    FROM users 
    WHERE last_activity > DATE_SUB(NOW(), INTERVAL 5 MINUTE) 
    AND role = 'student'
");
$online_query->execute();
$online_count = $online_query->get_result()->fetch_assoc()['count'] ?? 0;

// Pending grading
$pending_query = $conn->prepare("
    SELECT COUNT(*) as count 
    FROM submissions s
    JOIN assignments a ON s.assignment_id = a.id
    WHERE a.created_by = ? AND s.marks_obtained IS NULL
");
$pending_query->bind_param("i", $_SESSION['user_id']);
$pending_query->execute();
$pending_grading = $pending_query->get_result()->fetch_assoc()['count'] ?? 0;

// Total assignments
$assignments_query = $conn->prepare("SELECT COUNT(*) as count FROM assignments WHERE created_by = ?");
$assignments_query->bind_param("i", $_SESSION['user_id']);
$assignments_query->execute();
$total_assignments = $assignments_query->get_result()->fetch_assoc()['count'] ?? 0;

// Total exams
$exams_query = $conn->prepare("SELECT COUNT(*) as count FROM teacher_exams WHERE teacher_id = ?");
$exams_query->bind_param("i", $teacher_id);
$exams_query->execute();
$total_exams = $exams_query->get_result()->fetch_assoc()['count'] ?? 0;

// Total discussion groups
$groups_query = $conn->prepare("SELECT COUNT(*) as count FROM discussion_groups WHERE teacher_id = ?");
$groups_query->bind_param("i", $teacher_id);
$groups_query->execute();
$total_groups = $groups_query->get_result()->fetch_assoc()['count'] ?? 0;

// Total announcements
$announcements_query = $conn->prepare("SELECT COUNT(*) as count FROM teacher_announcements WHERE teacher_id = ?");
$announcements_query->bind_param("i", $teacher_id);
$announcements_query->execute();
$total_announcements = $announcements_query->get_result()->fetch_assoc()['count'] ?? 0;

// Today's schedule
$today = strtolower(date('l'));
$today_classes = $conn->prepare("
    SELECT te.*, s.name as subject_name, c.name as class_name, c.id as class_id
    FROM timetable_entries te
    JOIN subjects s ON te.subject_id = s.id
    JOIN classes c ON te.class_id = c.id
    WHERE te.teacher_id = ? AND te.day_of_week = ?
    ORDER BY te.start_time
");
$today_classes->bind_param("is", $teacher_id, $today);
$today_classes->execute();
$today_schedule = $today_classes->get_result();

// Recent activities
$recent_activities = $conn->prepare("
    SELECT 
        al.*, 
        u.first_name, 
        u.last_name, 
        u.role,
        gm.group_id,
        g.name as group_name,
        CASE 
            WHEN al.action LIKE '%message%' THEN 'message'
            WHEN al.action LIKE '%login%' THEN 'login'
            WHEN al.action LIKE '%exam%' THEN 'exam'
            WHEN al.action LIKE '%assignment%' THEN 'assignment'
            ELSE 'other'
        END as activity_type
    FROM activity_logs al
    JOIN users u ON al.user_id = u.id
    LEFT JOIN group_messages gm ON al.entity_type = 'group_message' AND al.entity_id = gm.id
    LEFT JOIN discussion_groups g ON gm.group_id = g.id
    ORDER BY al.created_at DESC 
    LIMIT 20
");
$recent_activities->execute();
$recent_activities = $recent_activities->get_result();

// Online students
$online_students = $conn->prepare("
    SELECT u.id, u.first_name, u.last_name, u.last_activity,
           s.admission_number, c.name as class_name,
           TIMESTAMPDIFF(MINUTE, u.last_activity, NOW()) as minutes_ago
    FROM users u
    JOIN students s ON u.id = s.user_id
    JOIN classes c ON s.class_id = c.id
    WHERE u.last_activity > DATE_SUB(NOW(), INTERVAL 5 MINUTE)
    AND u.role = 'student'
    AND c.id IN (SELECT DISTINCT class_id FROM class_subject WHERE teacher_id = ?)
    ORDER BY u.last_activity DESC
");
$online_students->bind_param("i", $teacher_id);
$online_students->execute();
$online_students = $online_students->get_result();

// Student progress
$student_progress = $conn->prepare("
    SELECT 
        u.id as user_id,
        CONCAT(u.first_name, ' ', u.last_name) as student_name,
        s.admission_number,
        c.name as class_name,
        MAX(al.created_at) as last_activity,
        COUNT(al.id) as total_activities,
        (SELECT COUNT(*) FROM activity_logs WHERE user_id = u.id AND DATE(created_at) = CURDATE()) as today_activities,
        (SELECT COUNT(*) FROM group_messages WHERE sender_id = u.id AND DATE(created_at) = CURDATE()) as messages_today
    FROM users u
    JOIN students s ON u.id = s.user_id
    JOIN classes c ON s.class_id = c.id
    LEFT JOIN activity_logs al ON u.id = al.user_id
    WHERE c.id IN (SELECT DISTINCT class_id FROM class_subject WHERE teacher_id = ?)
    GROUP BY u.id
    ORDER BY last_activity DESC
    LIMIT 20
");
$student_progress->bind_param("i", $teacher_id);
$student_progress->execute();
$student_progress = $student_progress->get_result();

// Exam results
$exam_results_timeline = $conn->prepare("
    SELECT 
        s.id as student_id,
        CONCAT(u.first_name, ' ', u.last_name) as student_name,
        te.id as exam_id,
        te.title as exam_title,
        es.percentage,
        es.grade,
        es.submitted_at,
        sub.name as subject_name
    FROM exam_submissions es
    JOIN students s ON es.student_id = s.id
    JOIN users u ON s.user_id = u.id
    JOIN teacher_exams te ON es.exam_id = te.id
    JOIN subjects sub ON te.subject_id = sub.id
    WHERE te.teacher_id = ?
    ORDER BY es.submitted_at DESC
    LIMIT 20
");
$exam_results_timeline->bind_param("i", $teacher_id);
$exam_results_timeline->execute();
$exam_results_timeline = $exam_results_timeline->get_result();

// Latest discussion groups
$latest_groups = $conn->prepare("
    SELECT g.*, c.name as class_name,
           (SELECT COUNT(*) FROM group_members WHERE group_id = g.id) as member_count,
           (SELECT COUNT(*) FROM group_messages WHERE group_id = g.id) as message_count
    FROM discussion_groups g
    JOIN classes c ON g.class_id = c.id
    WHERE g.teacher_id = ?
    ORDER BY g.created_at DESC
    LIMIT 6
");
$latest_groups->bind_param("i", $teacher_id);
$latest_groups->execute();
$latest_groups = $latest_groups->get_result();

// Latest announcements
$latest_announcements = $conn->prepare("
    SELECT a.*, c.name as class_name
    FROM teacher_announcements a
    JOIN classes c ON a.class_id = c.id
    WHERE a.teacher_id = ?
    ORDER BY a.created_at DESC
    LIMIT 5
");
$latest_announcements->bind_param("i", $teacher_id);
$latest_announcements->execute();
$latest_announcements = $latest_announcements->get_result();

// getTimeAgo is defined in config.php
?>

<style>
.dashboard-card {
    transition: all 0.2s ease;
}
.dashboard-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 25px rgba(0,0,0,0.08);
}
.view-btn {
    transition: all 0.2s ease;
}
.view-btn:hover {
    transform: scale(1.05);
}
.quick-action-btn {
    transition: all 0.2s ease;
}
.quick-action-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
}
</style>

<div class="ml-64 mt-16 p-6 bg-gray-50 min-h-screen">
    <div class="max-w-full mx-auto">
        <!-- Welcome Section -->
        <div class="bg-gradient-to-r from-blue-600 to-purple-600 rounded-2xl p-6 mb-6 text-white">
            <div class="flex justify-between items-center flex-wrap">
                <div>
                    <h2 class="text-2xl font-bold">Welcome back, <?php echo htmlspecialchars($_SESSION['user_name']); ?>! 👋</h2>
                    <p class="text-blue-100 mt-1">Manage your classes, assignments, exams, and student progress</p>
                    <div class="mt-3 flex flex-wrap gap-3">
                        <div class="bg-white bg-opacity-20 rounded-lg px-3 py-1 text-sm">
                            <i class="fas fa-calendar-alt mr-1"></i> <?php echo date('l, F j, Y'); ?>
                        </div>
                        <div class="bg-white bg-opacity-20 rounded-lg px-3 py-1 text-sm">
                            <i class="fas fa-clock mr-1"></i> <?php echo date('h:i A'); ?>
                        </div>
                        <div class="bg-white bg-opacity-20 rounded-lg px-3 py-1 text-sm">
                            <i class="fas fa-users mr-1"></i> <?php echo $online_count; ?> students online now
                        </div>
                    </div>
                </div>
                <div class="hidden md:block">
                    <div class="w-20 h-20 bg-white bg-opacity-20 rounded-full flex items-center justify-center">
                        <i class="fas fa-chalkboard-user text-4xl"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Stats Cards -->
        <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4 mb-6">
            <div class="dashboard-card bg-white rounded-xl p-4 shadow-sm">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-500 text-sm">Students</p>
                        <p class="text-2xl font-bold text-blue-600"><?php echo $total_students; ?></p>
                    </div>
                    <div class="w-12 h-12 bg-blue-100 rounded-full flex items-center justify-center">
                        <i class="fas fa-users text-blue-600 text-xl"></i>
                    </div>
                </div>
            </div>
            <div class="dashboard-card bg-white rounded-xl p-4 shadow-sm">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-500 text-sm">Online Now</p>
                        <p class="text-2xl font-bold text-green-600"><?php echo $online_count; ?></p>
                    </div>
                    <div class="w-12 h-12 bg-green-100 rounded-full flex items-center justify-center">
                        <i class="fas fa-circle text-green-600 text-xl"></i>
                    </div>
                </div>
            </div>
            <div class="dashboard-card bg-white rounded-xl p-4 shadow-sm">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-500 text-sm">Assignments</p>
                        <p class="text-2xl font-bold text-purple-600"><?php echo $total_assignments; ?></p>
                    </div>
                    <div class="w-12 h-12 bg-purple-100 rounded-full flex items-center justify-center">
                        <i class="fas fa-tasks text-purple-600 text-xl"></i>
                    </div>
                </div>
            </div>
            <div class="dashboard-card bg-white rounded-xl p-4 shadow-sm">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-500 text-sm">Exams</p>
                        <p class="text-2xl font-bold text-red-600"><?php echo $total_exams; ?></p>
                    </div>
                    <div class="w-12 h-12 bg-red-100 rounded-full flex items-center justify-center">
                        <i class="fas fa-pen-alt text-red-600 text-xl"></i>
                    </div>
                </div>
            </div>
            <div class="dashboard-card bg-white rounded-xl p-4 shadow-sm">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-500 text-sm">Groups</p>
                        <p class="text-2xl font-bold text-indigo-600"><?php echo $total_groups; ?></p>
                    </div>
                    <div class="w-12 h-12 bg-indigo-100 rounded-full flex items-center justify-center">
                        <i class="fas fa-comments text-indigo-600 text-xl"></i>
                    </div>
                </div>
            </div>
            <div class="dashboard-card bg-white rounded-xl p-4 shadow-sm">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-500 text-sm">Pending</p>
                        <p class="text-2xl font-bold text-yellow-600"><?php echo $pending_grading; ?></p>
                    </div>
                    <div class="w-12 h-12 bg-yellow-100 rounded-full flex items-center justify-center">
                        <i class="fas fa-clock text-yellow-600 text-xl"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="grid grid-cols-3 md:grid-cols-6 lg:grid-cols-8 gap-3 mb-6">
            <a href="materials/upload.php" class="quick-action-btn bg-blue-50 hover:bg-blue-100 rounded-xl p-3 text-center transition">
                <i class="fas fa-upload text-blue-600 text-xl mb-1 block"></i>
                <span class="text-xs font-medium">Upload</span>
            </a>
            <a href="assignments/create.php" class="quick-action-btn bg-green-50 hover:bg-green-100 rounded-xl p-3 text-center transition">
                <i class="fas fa-plus-circle text-green-600 text-xl mb-1 block"></i>
                <span class="text-xs font-medium">Assignment</span>
            </a>
            <a href="attendance/mark.php" class="quick-action-btn bg-yellow-50 hover:bg-yellow-100 rounded-xl p-3 text-center transition">
                <i class="fas fa-calendar-check text-yellow-600 text-xl mb-1 block"></i>
                <span class="text-xs font-medium">Attendance</span>
            </a>
            <a href="exams/create.php" class="quick-action-btn bg-purple-50 hover:bg-purple-100 rounded-xl p-3 text-center transition">
                <i class="fas fa-pen-alt text-purple-600 text-xl mb-1 block"></i>
                <span class="text-xs font-medium">Exam</span>
            </a>
            <a href="discussion/create.php" class="quick-action-btn bg-indigo-50 hover:bg-indigo-100 rounded-xl p-3 text-center transition">
                <i class="fas fa-plus text-indigo-600 text-xl mb-1 block"></i>
                <span class="text-xs font-medium">New Group</span>
            </a>
            <a href="announcements/create.php" class="quick-action-btn bg-pink-50 hover:bg-pink-100 rounded-xl p-3 text-center transition">
                <i class="fas fa-bullhorn text-pink-600 text-xl mb-1 block"></i>
                <span class="text-xs font-medium">Announce</span>
            </a>
            <a href="coding/exercises.php" class="quick-action-btn bg-cyan-50 hover:bg-cyan-100 rounded-xl p-3 text-center transition">
                <i class="fas fa-code text-cyan-600 text-xl mb-1 block"></i>
                <span class="text-xs font-medium">Coding</span>
            </a>
            <a href="chat/index.php" class="quick-action-btn bg-rose-50 hover:bg-rose-100 rounded-xl p-3 text-center transition">
                <i class="fas fa-comment-dots text-rose-600 text-xl mb-1 block"></i>
                <span class="text-xs font-medium">Chat</span>
            </a>
        </div>

        <!-- Today's Schedule & Online Students -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
            <div class="bg-white rounded-xl shadow-sm overflow-hidden">
                <div class="p-4 border-b flex justify-between items-center">
                    <h3 class="font-semibold text-lg">
                        <i class="fas fa-calendar-day text-blue-500 mr-2"></i>
                        Today's Schedule
                    </h3>
                    <a href="../timetable/index.php" class="text-blue-600 text-sm hover:text-blue-800">View All →</a>
                </div>
                <div class="divide-y max-h-64 overflow-y-auto">
                    <?php if ($today_schedule && $today_schedule->num_rows > 0): ?>
                        <?php while($class = $today_schedule->fetch_assoc()): ?>
                            <div class="p-4 hover:bg-gray-50">
                                <div class="flex justify-between items-center">
                                    <div>
                                        <p class="font-semibold"><?php echo htmlspecialchars($class['subject_name']); ?></p>
                                        <p class="text-sm text-gray-500"><?php echo htmlspecialchars($class['class_name']); ?></p>
                                    </div>
                                    <div class="text-right">
                                        <p class="text-sm font-medium text-blue-600">
                                            <?php echo date('h:i A', strtotime($class['start_time'])); ?> - 
                                            <?php echo date('h:i A', strtotime($class['end_time'])); ?>
                                        </p>
                                    </div>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <div class="p-8 text-center text-gray-500">
                            <i class="fas fa-calendar-day text-3xl mb-2 block"></i>
                            No classes scheduled for today
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="bg-white rounded-xl shadow-sm overflow-hidden">
                <div class="p-4 border-b flex justify-between items-center bg-green-50">
                    <h3 class="font-semibold text-lg">
                        <i class="fas fa-circle text-green-500 mr-2"></i>
                        Online Students
                    </h3>
                    <span class="text-xs text-gray-500">Auto-refresh</span>
                </div>
                <div class="divide-y max-h-64 overflow-y-auto" id="onlineStudents">
                    <?php if ($online_students && $online_students->num_rows > 0): ?>
                        <?php while($student = $online_students->fetch_assoc()): ?>
                            <div class="p-4 hover:bg-gray-50 flex items-center justify-between">
                                <div class="flex items-center space-x-3">
                                    <div class="w-8 h-8 bg-green-100 rounded-full flex items-center justify-center">
                                        <i class="fas fa-user text-green-600"></i>
                                    </div>
                                    <div>
                                        <p class="font-medium text-sm"><?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?></p>
                                        <p class="text-xs text-gray-500"><?php echo htmlspecialchars($student['class_name']); ?></p>
                                    </div>
                                </div>
                                <div class="text-right">
                                    <span class="px-2 py-0.5 bg-green-100 text-green-700 text-xs rounded-full">
                                        <i class="fas fa-circle text-green-500 text-[6px] mr-1"></i> Online
                                    </span>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <div class="p-8 text-center text-gray-500">
                            <i class="fas fa-users text-3xl mb-2 block"></i>
                            No students currently online
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- ============================================ -->
        <!-- DISCUSSION GROUPS SECTION - FIXED LINKS -->
        <!-- ============================================ -->
        <div class="bg-white rounded-xl shadow-sm overflow-hidden mb-6">
            <div class="p-4 border-b flex justify-between items-center">
                <h3 class="font-semibold text-lg">
                    <i class="fas fa-comments text-indigo-500 mr-2"></i>
                    My Discussion Groups
                </h3>
                <a href="discussion/index.php" class="text-blue-600 text-sm hover:text-blue-800">View All →</a>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 p-4">
                <?php if ($latest_groups && $latest_groups->num_rows > 0): ?>
                    <?php while($group = $latest_groups->fetch_assoc()): ?>
                        <div class="border rounded-lg p-4 hover:shadow-md transition">
                            <div class="flex justify-between items-start">
                                <div>
                                    <h4 class="font-semibold"><?php echo htmlspecialchars($group['name']); ?></h4>
                                    <p class="text-xs text-gray-500"><?php echo htmlspecialchars($group['class_name']); ?></p>
                                </div>
                                <span class="text-xs text-gray-400"><?php echo $group['member_count']; ?> members</span>
                            </div>
                            <div class="mt-3 flex justify-between items-center">
                                <span class="text-xs text-gray-400">
                                    <i class="fas fa-comment mr-1"></i> <?php echo $group['message_count']; ?> messages
                                </span>
                                <!-- ✅ FIXED: Link to discussion view -->
                                <a href="discussion/view.php?id=<?php echo $group['id']; ?>" 
                                   class="view-btn bg-blue-600 text-white px-3 py-1 rounded-lg text-sm hover:bg-blue-700 transition">
                                    <i class="fas fa-eye mr-1"></i> View
                                </a>
                            </div>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="col-span-full text-center text-gray-500 py-6">
                        <i class="fas fa-comments text-3xl mb-2 block"></i>
                        No discussion groups yet. <a href="discussion/create.php" class="text-blue-600">Create one now</a>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Student Progress & Exam Results -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
            <div class="bg-white rounded-xl shadow-sm overflow-hidden">
                <div class="p-4 border-b flex justify-between items-center">
                    <h3 class="font-semibold text-lg">
                        <i class="fas fa-chart-line text-purple-500 mr-2"></i>
                        Student Progress
                    </h3>
                    <a href="student-progress.php" class="text-blue-600 text-sm hover:text-blue-800">View All →</a>
                </div>
                <div class="divide-y max-h-80 overflow-y-auto">
                    <?php if ($student_progress && $student_progress->num_rows > 0): ?>
                        <?php while($student = $student_progress->fetch_assoc()): 
                            $last_active = $student['last_activity'] ? getTimeAgo($student['last_activity']) : 'Never';
                        ?>
                            <div class="p-4 hover:bg-gray-50">
                                <div class="flex justify-between items-start">
                                    <div>
                                        <p class="font-medium text-sm"><?php echo htmlspecialchars($student['student_name']); ?></p>
                                        <p class="text-xs text-gray-500"><?php echo htmlspecialchars($student['admission_number']); ?> • <?php echo htmlspecialchars($student['class_name']); ?></p>
                                        <div class="flex flex-wrap gap-3 mt-1 text-xs text-gray-400">
                                            <span><i class="fas fa-sign-in-alt mr-1"></i> <?php echo $student['today_activities'] ?? 0; ?> today</span>
                                            <span><i class="fas fa-comment mr-1"></i> <?php echo $student['messages_today'] ?? 0; ?> messages</span>
                                            <span><i class="fas fa-history mr-1"></i> <?php echo $last_active; ?></span>
                                        </div>
                                    </div>
                                    <a href="student-progress.php?id=<?php echo $student['user_id']; ?>" 
                                       class="view-btn bg-blue-600 text-white px-3 py-1 rounded-lg text-sm hover:bg-blue-700 transition">
                                        <i class="fas fa-eye mr-1"></i> View
                                    </a>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <div class="p-8 text-center text-gray-500">
                            <i class="fas fa-chart-line text-3xl mb-2 block"></i>
                            No student activity data available
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="bg-white rounded-xl shadow-sm overflow-hidden">
                <div class="p-4 border-b flex justify-between items-center">
                    <h3 class="font-semibold text-lg">
                        <i class="fas fa-chart-bar text-yellow-500 mr-2"></i>
                        Exam Results Timeline
                    </h3>
                    <a href="exams/index.php" class="text-blue-600 text-sm hover:text-blue-800">View All →</a>
                </div>
                <div class="divide-y max-h-80 overflow-y-auto">
                    <?php if ($exam_results_timeline && $exam_results_timeline->num_rows > 0): ?>
                        <?php while($result = $exam_results_timeline->fetch_assoc()): 
                            $percentage = round($result['percentage'] ?? 0, 1);
                            $is_pass = $percentage >= 50;
                        ?>
                            <div class="p-4 hover:bg-gray-50">
                                <div class="flex justify-between items-center">
                                    <div>
                                        <p class="font-medium text-sm"><?php echo htmlspecialchars($result['student_name']); ?></p>
                                        <p class="text-xs text-gray-500"><?php echo htmlspecialchars($result['exam_title']); ?> • <?php echo htmlspecialchars($result['subject_name']); ?></p>
                                        <p class="text-xs text-gray-400"><?php echo date('M d, Y', strtotime($result['submitted_at'])); ?></p>
                                    </div>
                                    <div class="text-right">
                                        <p class="text-lg font-bold <?php echo $is_pass ? 'text-green-600' : 'text-red-600'; ?>">
                                            <?php echo $percentage; ?>%
                                        </p>
                                        <span class="px-2 py-0.5 text-xs rounded-full <?php echo $is_pass ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'; ?>">
                                            <?php echo $is_pass ? '✅ Pass' : '❌ Fail'; ?>
                                        </span>
                                    </div>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <div class="p-8 text-center text-gray-500">
                            <i class="fas fa-chart-bar text-3xl mb-2 block"></i>
                            No exam results available yet
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Recent Activities -->
        <div class="bg-white rounded-xl shadow-sm overflow-hidden">
            <div class="p-4 border-b flex justify-between items-center">
                <h3 class="font-semibold text-lg">
                    <i class="fas fa-history text-gray-500 mr-2"></i>
                    Recent Activities
                </h3>
            </div>
            <div class="divide-y max-h-60 overflow-y-auto">
                <?php if ($recent_activities && $recent_activities->num_rows > 0): ?>
                    <?php while($activity = $recent_activities->fetch_assoc()): 
                        $is_clickable = $activity['activity_type'] == 'message' && $activity['group_id'];
                    ?>
                        <div class="p-3 hover:bg-gray-50 flex items-center space-x-3 <?php echo $is_clickable ? 'cursor-pointer' : ''; ?>"
                             <?php if($is_clickable): ?>
                             onclick="window.location.href='discussion/view.php?id=<?php echo $activity['group_id']; ?>'"
                             <?php endif; ?>>
                            <div class="w-8 h-8 bg-gray-100 rounded-full flex items-center justify-center">
                                <i class="fas fa-user-circle text-gray-500"></i>
                            </div>
                            <div class="flex-1">
                                <p class="text-sm">
                                    <span class="font-semibold"><?php echo htmlspecialchars($activity['first_name'] . ' ' . $activity['last_name']); ?></span>
                                    <span class="text-gray-600"> <?php echo htmlspecialchars($activity['action']); ?></span>
                                    <?php if($activity['activity_type'] == 'message' && $activity['group_name']): ?>
                                        <span class="text-blue-600 text-xs">
                                            in <?php echo htmlspecialchars($activity['group_name']); ?>
                                        </span>
                                    <?php endif; ?>
                                </p>
                                <p class="text-xs text-gray-400"><?php echo getTimeAgo($activity['created_at']); ?></p>
                            </div>
                            <?php if($is_clickable): ?>
                                <a href="discussion/view.php?id=<?php echo $activity['group_id']; ?>" 
                                   class="text-blue-600 text-sm hover:text-blue-800">
                                    <i class="fas fa-arrow-right"></i>
                                </a>
                            <?php endif; ?>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="p-8 text-center text-gray-500">
                        <i class="fas fa-inbox text-3xl mb-2 block"></i>
                        No recent activities
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
function refreshOnlineStudents() {
    const container = document.getElementById('onlineStudents');
    if (container) {
        fetch('get-online-students.php')
            .then(response => response.text())
            .then(data => {
                container.innerHTML = data;
            })
            .catch(error => console.error('Error refreshing online students:', error));
    }
}
setInterval(refreshOnlineStudents, 30000);
</script>

<?php include '../includes/footer.php'; ?>