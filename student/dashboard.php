<?php
require_once '../config.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
requireRole('student');

$page_title = 'Student Dashboard';
include '../includes/header.php';
include '../includes/sidebar.php';
include '../includes/navbar.php';

// Get student ID and details
$student_query = $conn->prepare("
    SELECT s.*, c.name as class_name, 
           CONCAT(u.first_name, ' ', u.last_name) as full_name,
           u.avatar
    FROM students s
    JOIN users u ON s.user_id = u.id
    JOIN classes c ON s.class_id = c.id
    WHERE s.user_id = ?
");
$student_query->bind_param("i", $_SESSION['user_id']);
$student_query->execute();
$student = $student_query->get_result()->fetch_assoc();

if (!$student) {
    echo "<div class='ml-64 mt-16 p-6'><div class='alert alert-danger'>Student record not found!</div></div>";
    include '../includes/footer.php';
    exit();
}

$student_id = $student['id'];
$class_id = $student['class_id'];

// ============================================
// 1. STATISTICS
// ============================================

// Total assignments
$assignments_query = $conn->prepare("
    SELECT COUNT(*) as count 
    FROM assignments 
    WHERE class_id = ? AND status = 'published'
");
$assignments_query->bind_param("i", $class_id);
$assignments_query->execute();
$total_assignments = $assignments_query->get_result()->fetch_assoc()['count'] ?? 0;

// Completed assignments
$completed_query = $conn->prepare("
    SELECT COUNT(*) as count 
    FROM submissions 
    WHERE student_id = ? AND marks_obtained IS NOT NULL
");
$completed_query->bind_param("i", $student_id);
$completed_query->execute();
$completed_assignments = $completed_query->get_result()->fetch_assoc()['count'] ?? 0;

// Pending assignments
$pending_assignments = $total_assignments - $completed_assignments;

// Total exams taken
$exams_taken_query = $conn->prepare("
    SELECT COUNT(*) as count 
    FROM exam_submissions 
    WHERE student_id = ?
");
$exams_taken_query->bind_param("i", $student_id);
$exams_taken_query->execute();
$exams_taken = $exams_taken_query->get_result()->fetch_assoc()['count'] ?? 0;

// Average score
$avg_score_query = $conn->prepare("
    SELECT AVG(percentage) as avg_score 
    FROM exam_submissions 
    WHERE student_id = ?
");
$avg_score_query->bind_param("i", $student_id);
$avg_score_query->execute();
$avg_score = round($avg_score_query->get_result()->fetch_assoc()['avg_score'] ?? 0, 1);

// Attendance percentage
$attendance_query = $conn->prepare("
    SELECT 
        COUNT(*) as total_days,
        SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) as present_days
    FROM attendance 
    WHERE student_id = ?
");
$attendance_query->bind_param("i", $student_id);
$attendance_query->execute();
$attendance_data = $attendance_query->get_result()->fetch_assoc();
$total_days = $attendance_data['total_days'] ?? 0;
$present_days = $attendance_data['present_days'] ?? 0;
$attendance_percentage = $total_days > 0 ? round(($present_days / $total_days) * 100) : 0;

// ============================================
// 2. TODAY'S SCHEDULE
// ============================================
$today = strtolower(date('l'));
$today_schedule = $conn->prepare("
    SELECT te.*, s.name as subject_name, 
           CONCAT(u.first_name, ' ', u.last_name) as teacher_name
    FROM timetable_entries te
    JOIN subjects s ON te.subject_id = s.id
    JOIN teachers t ON te.teacher_id = t.id
    JOIN users u ON t.user_id = u.id
    WHERE te.class_id = ? AND te.day_of_week = ?
    ORDER BY te.start_time
");
$today_schedule->bind_param("is", $class_id, $today);
$today_schedule->execute();
$today_schedule = $today_schedule->get_result();

// ============================================
// 3. UPCOMING EXAMS
// ============================================
$upcoming_exams = $conn->prepare("
    SELECT te.*, sub.name as subject_name,
           CONCAT(u.first_name, ' ', u.last_name) as teacher_name
    FROM teacher_exams te
    JOIN subjects sub ON te.subject_id = sub.id
    JOIN teachers t ON te.teacher_id = t.id
    JOIN users u ON t.user_id = u.id
    WHERE te.class_id = ? 
    AND te.is_published = 1
    AND te.start_date >= CURDATE()
    ORDER BY te.start_date ASC
    LIMIT 5
");
$upcoming_exams->bind_param("i", $class_id);
$upcoming_exams->execute();
$upcoming_exams = $upcoming_exams->get_result();

// ============================================
// 4. UPCOMING ASSIGNMENTS
// ============================================
$upcoming_assignments = $conn->prepare("
    SELECT a.*, sub.name as subject_name
    FROM assignments a
    JOIN subjects sub ON a.subject_id = sub.id
    WHERE a.class_id = ? 
    AND a.status = 'published'
    AND a.due_date >= CURDATE()
    ORDER BY a.due_date ASC
    LIMIT 5
");
$upcoming_assignments->bind_param("i", $class_id);
$upcoming_assignments->execute();
$upcoming_assignments = $upcoming_assignments->get_result();

// ============================================
// 5. RECENT EXAM RESULTS
// ============================================
$recent_results = $conn->prepare("
    SELECT es.*, te.title as exam_title, sub.name as subject_name,
           te.total_marks
    FROM exam_submissions es
    JOIN teacher_exams te ON es.exam_id = te.id
    JOIN subjects sub ON te.subject_id = sub.id
    WHERE es.student_id = ?
    ORDER BY es.submitted_at DESC
    LIMIT 5
");
$recent_results->bind_param("i", $student_id);
$recent_results->execute();
$recent_results = $recent_results->get_result();

// ============================================
// 6. NOTIFICATIONS
// ============================================
$notifications = $conn->prepare("
    SELECT * FROM notifications 
    WHERE user_id = ? 
    ORDER BY created_at DESC 
    LIMIT 5
");
$notifications->bind_param("i", $_SESSION['user_id']);
$notifications->execute();
$notifications = $notifications->get_result();
$unread_count = getNotificationCount($_SESSION['user_id']);

// ============================================
// 7. DISCUSSION GROUPS (Student is member of)
// ============================================
$my_groups = $conn->prepare("
    SELECT g.*, c.name as class_name,
           CONCAT(u.first_name, ' ', u.last_name) as teacher_name,
           (SELECT COUNT(*) FROM group_messages WHERE group_id = g.id) as message_count,
           (SELECT MAX(created_at) FROM group_messages WHERE group_id = g.id) as last_activity
    FROM discussion_groups g
    JOIN group_members gm ON g.id = gm.group_id
    JOIN classes c ON g.class_id = c.id
    JOIN teachers t ON g.teacher_id = t.id
    JOIN users u ON t.user_id = u.id
    WHERE gm.student_id = ? AND g.is_active = 1
    ORDER BY last_activity DESC
    LIMIT 3
");
$my_groups->bind_param("i", $student_id);
$my_groups->execute();
$my_groups = $my_groups->get_result();

// ============================================
// 8. ANNOUNCEMENTS
// ============================================
$announcements = $conn->prepare("
    SELECT a.*, 
           CONCAT(u.first_name, ' ', u.last_name) as teacher_name
    FROM teacher_announcements a
    JOIN teachers t ON a.teacher_id = t.id
    JOIN users u ON t.user_id = u.id
    WHERE a.class_id = ? AND a.is_published = 1
    ORDER BY a.created_at DESC
    LIMIT 3
");
$announcements->bind_param("i", $class_id);
$announcements->execute();
$announcements = $announcements->get_result();
?>

<style>
.dashboard-card {
    transition: all 0.3s ease;
    border-radius: 16px;
}
.dashboard-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 12px 30px rgba(0,0,0,0.08);
}
.stat-card {
    background: white;
    border-radius: 16px;
    padding: 20px;
    transition: all 0.3s ease;
}
.stat-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 8px 25px rgba(0,0,0,0.08);
}
.stat-icon {
    width: 48px;
    height: 48px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 20px;
}
.notification-item {
    transition: all 0.2s ease;
    border-left: 3px solid transparent;
}
.notification-item:hover {
    background: #f8fafc;
}
.notification-item.unread {
    border-left-color: #3b82f6;
    background: #f0f7ff;
}
.notification-item.read {
    border-left-color: #9ca3af;
}
.grade-badge {
    padding: 2px 10px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
}
.grade-pass { background: #d1fae5; color: #065f46; }
.grade-fail { background: #fee2e2; color: #991b1b; }
.grade-a { background: #d1fae5; color: #065f46; }
.grade-b { background: #dbeafe; color: #1e40af; }
.grade-c { background: #fef3c7; color: #92400e; }
.grade-d { background: #fef3c7; color: #92400e; }
.grade-e { background: #fee2e2; color: #991b1b; }
.grade-f { background: #fee2e2; color: #991b1b; }
</style>

<div class="ml-64 mt-16 p-6 bg-gray-50 min-h-screen">
    <div class="max-w-7xl mx-auto">
        <!-- Welcome Section -->
        <div class="bg-gradient-to-r from-blue-600 to-purple-600 rounded-2xl p-6 mb-6 text-white">
            <div class="flex justify-between items-center flex-wrap">
                <div>
                    <h2 class="text-2xl font-bold">Welcome back, <?php echo htmlspecialchars($student['full_name']); ?>! 👋</h2>
                    <p class="text-blue-100 mt-1">
                        <i class="fas fa-graduation-cap mr-1"></i> 
                        <?php echo htmlspecialchars($student['class_name']); ?> • 
                        <span class="text-blue-200">ID: <?php echo htmlspecialchars($student['admission_number']); ?></span>
                    </p>
                    <div class="mt-3 flex flex-wrap gap-3">
                        <div class="bg-white bg-opacity-20 rounded-lg px-3 py-1 text-sm">
                            <i class="fas fa-calendar-alt mr-1"></i> <?php echo date('l, F j, Y'); ?>
                        </div>
                        <div class="bg-white bg-opacity-20 rounded-lg px-3 py-1 text-sm">
                            <i class="fas fa-clock mr-1"></i> <?php echo date('h:i A'); ?>
                        </div>
                        <?php if($unread_count > 0): ?>
                            <div class="bg-red-500 bg-opacity-80 rounded-lg px-3 py-1 text-sm animate-pulse">
                                <i class="fas fa-bell mr-1"></i> <?php echo $unread_count; ?> new notifications
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="hidden md:block">
                    <div class="w-20 h-20 bg-white bg-opacity-20 rounded-full flex items-center justify-center">
                        <i class="fas fa-user-graduate text-4xl"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Stats Cards -->
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
            <div class="stat-card">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-500 text-sm">Attendance</p>
                        <p class="text-2xl font-bold text-blue-600"><?php echo $attendance_percentage; ?>%</p>
                    </div>
                    <div class="stat-icon bg-blue-50">
                        <i class="fas fa-calendar-check text-blue-600"></i>
                    </div>
                </div>
                <div class="mt-2 w-full bg-gray-200 rounded-full h-1.5">
                    <div class="bg-blue-600 h-1.5 rounded-full" style="width: <?php echo $attendance_percentage; ?>%"></div>
                </div>
                <p class="text-xs text-gray-400 mt-1"><?php echo $present_days; ?> / <?php echo $total_days; ?> days</p>
            </div>
            <div class="stat-card">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-500 text-sm">Average Score</p>
                        <p class="text-2xl font-bold text-green-600"><?php echo $avg_score; ?>%</p>
                    </div>
                    <div class="stat-icon bg-green-50">
                        <i class="fas fa-chart-line text-green-600"></i>
                    </div>
                </div>
                <p class="text-xs text-gray-400 mt-1">Based on <?php echo $exams_taken; ?> exams</p>
            </div>
            <div class="stat-card">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-500 text-sm">Assignments</p>
                        <p class="text-2xl font-bold text-purple-600"><?php echo $completed_assignments; ?>/<?php echo $total_assignments; ?></p>
                    </div>
                    <div class="stat-icon bg-purple-50">
                        <i class="fas fa-tasks text-purple-600"></i>
                    </div>
                </div>
                <?php if($pending_assignments > 0): ?>
                    <p class="text-xs text-yellow-600 mt-1"><?php echo $pending_assignments; ?> pending</p>
                <?php else: ?>
                    <p class="text-xs text-green-600 mt-1">All completed ✅</p>
                <?php endif; ?>
            </div>
            <div class="stat-card">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-500 text-sm">Exams Taken</p>
                        <p class="text-2xl font-bold text-orange-600"><?php echo $exams_taken; ?></p>
                    </div>
                    <div class="stat-icon bg-orange-50">
                        <i class="fas fa-pen-alt text-orange-600"></i>
                    </div>
                </div>
                <p class="text-xs text-gray-400 mt-1">Total exams completed</p>
            </div>
        </div>

        <!-- Main Content Grid - Row 1 -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
            <!-- Today's Schedule -->
            <div class="bg-white rounded-xl shadow-sm overflow-hidden">
                <div class="p-4 border-b flex justify-between items-center">
                    <h3 class="font-semibold text-lg">
                        <i class="fas fa-calendar-day text-blue-500 mr-2"></i>
                        Today's Schedule
                    </h3>
                    <a href="../timetable/view.php" class="text-blue-600 text-sm hover:text-blue-800">View Full →</a>
                </div>
                <div class="divide-y max-h-64 overflow-y-auto">
                    <?php if ($today_schedule && $today_schedule->num_rows > 0): ?>
                        <?php while($class = $today_schedule->fetch_assoc()): ?>
                            <div class="p-4 hover:bg-gray-50">
                                <div class="flex justify-between items-center">
                                    <div>
                                        <p class="font-semibold"><?php echo htmlspecialchars($class['subject_name']); ?></p>
                                        <p class="text-sm text-gray-500"><?php echo htmlspecialchars($class['teacher_name']); ?></p>
                                    </div>
                                    <div class="text-right">
                                        <p class="text-sm font-medium text-blue-600">
                                            <?php echo date('h:i A', strtotime($class['start_time'])); ?> - 
                                            <?php echo date('h:i A', strtotime($class['end_time'])); ?>
                                        </p>
                                        <?php if($class['classroom']): ?>
                                            <p class="text-xs text-gray-400">Room: <?php echo $class['classroom']; ?></p>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <div class="p-8 text-center text-gray-500">
                            <i class="fas fa-calendar-day text-3xl mb-2 block"></i>
                            No classes scheduled for today 🎉
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Upcoming Exams -->
            <div class="bg-white rounded-xl shadow-sm overflow-hidden">
                <div class="p-4 border-b flex justify-between items-center">
                    <h3 class="font-semibold text-lg">
                        <i class="fas fa-clock text-red-500 mr-2"></i>
                        Upcoming Exams
                    </h3>
                    <a href="../exams/index.php" class="text-blue-600 text-sm hover:text-blue-800">View All →</a>
                </div>
                <div class="divide-y max-h-64 overflow-y-auto">
                    <?php if ($upcoming_exams && $upcoming_exams->num_rows > 0): ?>
                        <?php while($exam = $upcoming_exams->fetch_assoc()): ?>
                            <div class="p-4 hover:bg-gray-50">
                                <div class="flex justify-between items-center">
                                    <div>
                                        <p class="font-semibold"><?php echo htmlspecialchars($exam['title']); ?></p>
                                        <p class="text-sm text-gray-500"><?php echo htmlspecialchars($exam['subject_name']); ?></p>
                                        <p class="text-xs text-gray-400">By: <?php echo htmlspecialchars($exam['teacher_name']); ?></p>
                                    </div>
                                    <div class="text-right">
                                        <p class="text-sm font-medium text-red-600">
                                            <?php echo date('M d', strtotime($exam['start_date'])); ?>
                                        </p>
                                        <p class="text-xs text-gray-400">
                                            <?php echo $exam['duration_minutes']; ?> min
                                        </p>
                                    </div>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <div class="p-8 text-center text-gray-500">
                            <i class="fas fa-clock text-3xl mb-2 block"></i>
                            No upcoming exams
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Row 2: Assignments & Recent Results -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
            <!-- Upcoming Assignments -->
            <div class="bg-white rounded-xl shadow-sm overflow-hidden">
                <div class="p-4 border-b flex justify-between items-center">
                    <h3 class="font-semibold text-lg">
                        <i class="fas fa-tasks text-purple-500 mr-2"></i>
                        Upcoming Assignments
                    </h3>
                    <a href="../assignments/index.php" class="text-blue-600 text-sm hover:text-blue-800">View All →</a>
                </div>
                <div class="divide-y max-h-64 overflow-y-auto">
                    <?php if ($upcoming_assignments && $upcoming_assignments->num_rows > 0): ?>
                        <?php while($assignment = $upcoming_assignments->fetch_assoc()): 
                            $days_left = ceil((strtotime($assignment['due_date']) - time()) / 86400);
                            $is_urgent = $days_left <= 2;
                        ?>
                            <div class="p-4 hover:bg-gray-50">
                                <div class="flex justify-between items-center">
                                    <div>
                                        <p class="font-semibold"><?php echo htmlspecialchars($assignment['title']); ?></p>
                                        <p class="text-sm text-gray-500"><?php echo htmlspecialchars($assignment['subject_name']); ?></p>
                                    </div>
                                    <div class="text-right">
                                        <p class="text-sm <?php echo $is_urgent ? 'text-red-600 font-bold' : 'text-gray-500'; ?>">
                                            <?php if($is_urgent): ?>
                                                <i class="fas fa-exclamation-circle mr-1"></i>
                                            <?php endif; ?>
                                            <?php echo date('M d', strtotime($assignment['due_date'])); ?>
                                        </p>
                                        <p class="text-xs text-gray-400"><?php echo $days_left; ?> days left</p>
                                    </div>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <div class="p-8 text-center text-gray-500">
                            <i class="fas fa-tasks text-3xl mb-2 block"></i>
                            No upcoming assignments
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Recent Exam Results -->
            <div class="bg-white rounded-xl shadow-sm overflow-hidden">
                <div class="p-4 border-b flex justify-between items-center">
                    <h3 class="font-semibold text-lg">
                        <i class="fas fa-chart-bar text-yellow-500 mr-2"></i>
                        Recent Exam Results
                    </h3>
                    <a href="../results/dashboard.php" class="text-blue-600 text-sm hover:text-blue-800">View All →</a>
                </div>
                <div class="divide-y max-h-64 overflow-y-auto">
                    <?php if ($recent_results && $recent_results->num_rows > 0): ?>
                        <?php while($result = $recent_results->fetch_assoc()): 
                            $percentage = round($result['percentage'] ?? 0, 1);
                            $is_pass = $percentage >= 50;
                            $grade = $result['grade'] ?? 'F';
                            $grade_class = 'grade-' . strtolower($grade);
                        ?>
                            <div class="p-4 hover:bg-gray-50">
                                <div class="flex justify-between items-center">
                                    <div>
                                        <p class="font-semibold"><?php echo htmlspecialchars($result['exam_title']); ?></p>
                                        <p class="text-sm text-gray-500"><?php echo htmlspecialchars($result['subject_name']); ?></p>
                                        <p class="text-xs text-gray-400"><?php echo date('M d, Y', strtotime($result['submitted_at'])); ?></p>
                                    </div>
                                    <div class="text-right">
                                        <p class="text-lg font-bold <?php echo $is_pass ? 'text-green-600' : 'text-red-600'; ?>">
                                            <?php echo $percentage; ?>%
                                        </p>
                                        <span class="grade-badge <?php echo $grade_class; ?>">
                                            Grade: <?php echo $grade; ?>
                                        </span>
                                        <span class="text-xs <?php echo $is_pass ? 'text-green-600' : 'text-red-600'; ?> block">
                                            <?php echo $is_pass ? '✅ Pass' : '❌ Fail'; ?>
                                        </span>
                                    </div>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <div class="p-8 text-center text-gray-500">
                            <i class="fas fa-chart-bar text-3xl mb-2 block"></i>
                            No exam results yet
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Row 3: Discussion Groups & Announcements -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
            <!-- My Discussion Groups -->
            <div class="bg-white rounded-xl shadow-sm overflow-hidden">
                <div class="p-4 border-b flex justify-between items-center">
                    <h3 class="font-semibold text-lg">
                        <i class="fas fa-comments text-indigo-500 mr-2"></i>
                        My Discussion Groups
                    </h3>
                    <a href="discussion/index.php" class="text-blue-600 text-sm hover:text-blue-800">View All →</a>
                </div>
                <div class="divide-y max-h-64 overflow-y-auto">
                    <?php if ($my_groups && $my_groups->num_rows > 0): ?>
                        <?php while($group = $my_groups->fetch_assoc()): 
                            $last_activity = $group['last_activity'] ? timeAgo($group['last_activity']) : 'No activity';
                        ?>
                            <div class="p-4 hover:bg-gray-50">
                                <div class="flex justify-between items-center">
                                    <div>
                                        <p class="font-semibold"><?php echo htmlspecialchars($group['name']); ?></p>
                                        <p class="text-sm text-gray-500">Teacher: <?php echo htmlspecialchars($group['teacher_name']); ?></p>
                                        <p class="text-xs text-gray-400">
                                            <i class="fas fa-comment mr-1"></i> <?php echo $group['message_count']; ?> messages
                                        </p>
                                    </div>
                                    <div class="text-right">
                                        <p class="text-xs text-gray-400"><?php echo $last_activity; ?></p>
                                        <a href="discussion/view.php?id=<?php echo $group['id']; ?>" 
                                           class="text-blue-600 text-sm hover:text-blue-800">
                                            <i class="fas fa-eye mr-1"></i> View
                                        </a>
                                    </div>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <div class="p-8 text-center text-gray-500">
                            <i class="fas fa-comments text-3xl mb-2 block"></i>
                            No discussion groups yet
                            <p class="text-sm text-gray-400 mt-1">Join a group to start discussing!</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Announcements -->
            <div class="bg-white rounded-xl shadow-sm overflow-hidden">
                <div class="p-4 border-b flex justify-between items-center">
                    <h3 class="font-semibold text-lg">
                        <i class="fas fa-bullhorn text-pink-500 mr-2"></i>
                        Announcements
                    </h3>
                    <a href="announcements/index.php" class="text-blue-600 text-sm hover:text-blue-800">View All →</a>
                </div>
                <div class="divide-y max-h-64 overflow-y-auto">
                    <?php if ($announcements && $announcements->num_rows > 0): ?>
                        <?php while($ann = $announcements->fetch_assoc()): ?>
                            <div class="p-4 hover:bg-gray-50">
                                <div class="flex justify-between items-start">
                                    <div>
                                        <p class="font-semibold"><?php echo htmlspecialchars($ann['title']); ?></p>
                                        <p class="text-sm text-gray-600 line-clamp-2"><?php echo htmlspecialchars($ann['content']); ?></p>
                                        <p class="text-xs text-gray-400 mt-1">
                                            <i class="fas fa-user mr-1"></i> <?php echo htmlspecialchars($ann['teacher_name']); ?>
                                            <span class="mx-1">•</span>
                                            <i class="far fa-clock mr-1"></i> <?php echo timeAgo($ann['created_at']); ?>
                                        </p>
                                    </div>
                                    <span class="px-2 py-0.5 text-xs rounded-full <?php echo $ann['priority'] == 'high' ? 'bg-red-100 text-red-700' : 'bg-blue-100 text-blue-700'; ?>">
                                        <?php echo ucfirst($ann['priority']); ?>
                                    </span>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <div class="p-8 text-center text-gray-500">
                            <i class="fas fa-bullhorn text-3xl mb-2 block"></i>
                            No announcements
                            <p class="text-sm text-gray-400 mt-1">Check back later for updates</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Row 4: Notifications -->
        <div class="bg-white rounded-xl shadow-sm overflow-hidden mb-6">
            <div class="p-4 border-b flex justify-between items-center">
                <h3 class="font-semibold text-lg">
                    <i class="fas fa-bell text-yellow-500 mr-2"></i>
                    Notifications
                    <?php if($unread_count > 0): ?>
                        <span class="bg-red-500 text-white text-xs rounded-full px-2 py-0.5 ml-2 animate-pulse">
                            <?php echo $unread_count; ?> new
                        </span>
                    <?php endif; ?>
                </h3>
                <a href="../notifications/index.php" class="text-blue-600 text-sm hover:text-blue-800">View All →</a>
            </div>
            <div class="divide-y max-h-64 overflow-y-auto">
                <?php if ($notifications && $notifications->num_rows > 0): ?>
                    <?php while($notif = $notifications->fetch_assoc()): 
                        $is_unread = $notif['is_read'] == 0;
                    ?>
                        <div class="notification-item <?php echo $is_unread ? 'unread' : 'read'; ?> p-4 hover:bg-gray-50 transition">
                            <div class="flex justify-between items-start">
                                <div class="flex-1">
                                    <div class="flex items-center gap-2">
                                        <p class="font-medium text-sm <?php echo $is_unread ? 'text-gray-800' : 'text-gray-500'; ?>">
                                            <?php echo htmlspecialchars($notif['title']); ?>
                                        </p>
                                        <?php if($is_unread): ?>
                                            <span class="w-2 h-2 bg-blue-500 rounded-full flex-shrink-0"></span>
                                        <?php endif; ?>
                                    </div>
                                    <p class="text-sm text-gray-600 mt-0.5">
                                        <?php echo htmlspecialchars($notif['message']); ?>
                                    </p>
                                    <p class="text-xs text-gray-400 mt-1">
                                        <i class="far fa-clock mr-1"></i> <?php echo timeAgo($notif['created_at']); ?>
                                    </p>
                                </div>
                                <?php if($is_unread): ?>
                                    <a href="../notifications/mark-read.php?id=<?php echo $notif['id']; ?>&redirect=dashboard" 
                                       class="text-blue-500 hover:text-blue-700 text-xs ml-2 flex-shrink-0">
                                        Mark read
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="p-8 text-center text-gray-500">
                        <i class="fas fa-bell-slash text-4xl mb-3 block text-gray-300"></i>
                        <p class="text-lg font-medium">No notifications</p>
                        <p class="text-sm text-gray-400 mt-1">You're all caught up! 🎉</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Quick Links -->
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
            <a href="../materials/index.php" 
               class="bg-white rounded-xl shadow-sm p-4 text-center hover:shadow-md transition hover:bg-blue-50">
                <i class="fas fa-book-open text-2xl text-blue-600 mb-2 block"></i>
                <span class="text-sm font-medium text-gray-700">Learning Materials</span>
            </a>
            <a href="../coding/index.php" 
               class="bg-white rounded-xl shadow-sm p-4 text-center hover:shadow-md transition hover:bg-green-50">
                <i class="fas fa-code text-2xl text-green-600 mb-2 block"></i>
                <span class="text-sm font-medium text-gray-700">Coding Playground</span>
            </a>
            <a href="../attendance/view.php" 
               class="bg-white rounded-xl shadow-sm p-4 text-center hover:shadow-md transition hover:bg-yellow-50">
                <i class="fas fa-calendar-check text-2xl text-yellow-600 mb-2 block"></i>
                <span class="text-sm font-medium text-gray-700">My Attendance</span>
            </a>
            <a href="../chat/index.php" 
               class="bg-white rounded-xl shadow-sm p-4 text-center hover:shadow-md transition hover:bg-purple-50">
                <i class="fas fa-comment-dots text-2xl text-purple-600 mb-2 block"></i>
                <span class="text-sm font-medium text-gray-700">Messages</span>
            </a>
        </div>
    </div>
</div>

<script>
console.log('✅ Student Dashboard loaded successfully');
console.log('📊 Attendance: <?php echo $attendance_percentage; ?>%');
console.log('📈 Average Score: <?php echo $avg_score; ?>%');
console.log('🔔 Unread Notifications: <?php echo $unread_count; ?>');
</script>

<?php include '../includes/footer.php'; ?>