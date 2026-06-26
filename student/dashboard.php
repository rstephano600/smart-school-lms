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
           CONCAT(u.first_name, ' ', u.last_name) as student_name,
           u.email, u.phone, u.avatar
    FROM students s
    JOIN users u ON s.user_id = u.id
    JOIN classes c ON s.class_id = c.id
    WHERE u.id = ?
");
$student_query->bind_param("i", $_SESSION['user_id']);
$student_query->execute();
$student = $student_query->get_result()->fetch_assoc();
$student_id = $student['id'];

// ============================================
// 1. Today's Classes
// ============================================
$today = strtolower(date('l'));
$today_classes = $conn->prepare("
    SELECT te.*, s.name as subject_name, 
           CONCAT(u.first_name, ' ', u.last_name) as teacher_name
    FROM timetable_entries te
    JOIN subjects s ON te.subject_id = s.id
    JOIN teachers t ON te.teacher_id = t.id
    JOIN users u ON t.user_id = u.id
    WHERE te.class_id = ? AND te.day_of_week = ?
    ORDER BY te.start_time
");
$today_classes->bind_param("is", $student['class_id'], $today);
$today_classes->execute();
$today_classes = $today_classes->get_result();

// ============================================
// 2. Upcoming Assignments
// ============================================
$upcoming_assignments = $conn->prepare("
    SELECT a.*, s.name as subject_name,
           (SELECT id FROM submissions WHERE assignment_id = a.id AND student_id = ?) as submitted
    FROM assignments a
    JOIN subjects s ON a.subject_id = s.id
    WHERE a.class_id = ? AND a.due_date > NOW() AND a.status = 'published'
    ORDER BY a.due_date ASC LIMIT 5
");
$upcoming_assignments->bind_param("ii", $student_id, $student['class_id']);
$upcoming_assignments->execute();
$upcoming_assignments = $upcoming_assignments->get_result();

// ============================================
// 3. UPCOMING EXAMS (Active + Upcoming)
// ============================================
$today_date = date('Y-m-d');

// Upcoming exams (not started yet)
$upcoming_exams = $conn->prepare("
    SELECT te.*, s.name as subject_name,
           'upcoming' as exam_status,
           DATEDIFF(te.start_date, CURDATE()) as days_left
    FROM teacher_exams te
    JOIN subjects s ON te.subject_id = s.id
    WHERE te.class_id = ? AND te.start_date > CURDATE() AND te.is_published = 1
    ORDER BY te.start_date ASC LIMIT 5
");
$upcoming_exams->bind_param("i", $student['class_id']);
$upcoming_exams->execute();
$upcoming_exams = $upcoming_exams->get_result();

// Active exams (available now)
$active_exams = $conn->prepare("
    SELECT te.*, s.name as subject_name,
           'active' as exam_status,
           TIMESTAMPDIFF(MINUTE, NOW(), CONCAT(te.end_date, ' ', te.end_time)) as minutes_remaining,
           (SELECT COUNT(*) FROM exam_submissions WHERE exam_id = te.id AND student_id = ?) as already_submitted
    FROM teacher_exams te
    JOIN subjects s ON te.subject_id = s.id
    WHERE te.class_id = ? 
      AND te.start_date <= CURDATE() 
      AND te.end_date >= CURDATE() 
      AND te.is_published = 1
    ORDER BY te.end_date ASC
");
$active_exams->bind_param("ii", $student_id, $student['class_id']);
$active_exams->execute();
$active_exams = $active_exams->get_result();

// Completed exams (already taken)
$completed_exams = $conn->prepare("
    SELECT te.*, s.name as subject_name, es.total_score, es.percentage, es.grade, es.submitted_at,
           'completed' as exam_status
    FROM exam_submissions es
    JOIN teacher_exams te ON es.exam_id = te.id
    JOIN subjects s ON te.subject_id = s.id
    WHERE es.student_id = ? AND te.class_id = ?
    ORDER BY es.submitted_at DESC LIMIT 5
");
$completed_exams->bind_param("ii", $student_id, $student['class_id']);
$completed_exams->execute();
$completed_exams = $completed_exams->get_result();

// ============================================
// 4. Attendance Percentage
// ============================================
$attendance_query = $conn->prepare("
    SELECT 
        COUNT(CASE WHEN status = 'present' THEN 1 END) as present,
        COUNT(*) as total
    FROM attendance
    WHERE student_id = ?
");
$attendance_query->bind_param("i", $student_id);
$attendance_query->execute();
$att_data = $attendance_query->get_result()->fetch_assoc();
$attendance_percentage = $att_data['total'] > 0 ? round(($att_data['present'] / $att_data['total']) * 100) : 0;

// ============================================
// 5. Recent Notifications
// ============================================
$notifications = $conn->prepare("
    SELECT * FROM notifications 
    WHERE user_id = ? 
    ORDER BY created_at DESC LIMIT 5
");
$notifications->bind_param("i", $_SESSION['user_id']);
$notifications->execute();
$notifications = $notifications->get_result();

// ============================================
// 6. Academic Progress Chart Data
// ============================================
$avg_score = 0;
$avg_query = $conn->prepare("
    SELECT AVG(percentage) as avg_score
    FROM exam_submissions
    WHERE student_id = ? AND is_graded = 1
");
$avg_query->bind_param("i", $student_id);
$avg_query->execute();
$avg_score = round($avg_query->get_result()->fetch_assoc()['avg_score'] ?? 0, 1);

// Subject-wise performance for chart
$subject_performance = $conn->prepare("
    SELECT sub.name, AVG(er.marks_obtained) as avg_score
    FROM exam_results er
    JOIN subjects sub ON er.subject_id = sub.id
    WHERE er.student_id = ?
    GROUP BY sub.id
    ORDER BY avg_score DESC
    LIMIT 6
");
$subject_performance->bind_param("i", $student_id);
$subject_performance->execute();
$subject_performance = $subject_performance->get_result();

// ============================================
// 7. Learning Progress Tracker
// ============================================
$progress_query = $conn->prepare("
    SELECT AVG(percentage) as avg_progress
    FROM learning_progress
    WHERE student_id = ?
");
$progress_query->bind_param("i", $student_id);
$progress_query->execute();
$learning_progress = round($progress_query->get_result()->fetch_assoc()['avg_progress'] ?? 0);

// Topics completed vs pending
$topics_completed = 0;
$topics_total = 0;
$topics_query = $conn->prepare("
    SELECT COUNT(*) as total, 
           SUM(CASE WHEN completed = 1 THEN 1 ELSE 0 END) as completed
    FROM learning_progress
    WHERE student_id = ?
");
$topics_query->bind_param("i", $student_id);
$topics_query->execute();
$topics_data = $topics_query->get_result()->fetch_assoc();
$topics_completed = $topics_data['completed'] ?? 0;
$topics_total = $topics_data['total'] ?? 0;

// ============================================
// 8. Calendar Events (Upcoming events this week)
// ============================================
$calendar_events = [];
$events_query = $conn->prepare("
    SELECT 'Exam' as type, title, start_date as date 
    FROM teacher_exams 
    WHERE class_id = ? AND start_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)
    UNION
    SELECT 'Assignment' as type, title, due_date as date 
    FROM assignments 
    WHERE class_id = ? AND due_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)
    LIMIT 5
");
$events_query->bind_param("ii", $student['class_id'], $student['class_id']);
$events_query->execute();
$calendar_events = $events_query->get_result();

// ============================================
// 9. Performance Summary
// ============================================
$performance_summary = [
    'excellent' => 0, 'good' => 0, 'average' => 0, 'poor' => 0
];
$summary_query = $conn->prepare("
    SELECT er.grade, COUNT(*) as count
    FROM exam_results er
    WHERE er.student_id = ?
    GROUP BY er.grade
");
$summary_query->bind_param("i", $student_id);
$summary_query->execute();
$grades = $summary_query->get_result();
while ($grade = $grades->fetch_assoc()) {
    if (in_array($grade['grade'], ['A'])) $performance_summary['excellent'] += $grade['count'];
    elseif (in_array($grade['grade'], ['B'])) $performance_summary['good'] += $grade['count'];
    elseif (in_array($grade['grade'], ['C', 'D'])) $performance_summary['average'] += $grade['count'];
    else $performance_summary['poor'] += $grade['count'];
}
?>

<div class="ml-64 mt-16 p-6 bg-gray-50 min-h-screen">
    <div class="max-w-full mx-auto">
        <!-- ============================================ -->
        <!-- WELCOME MESSAGE & STUDENT PROFILE CARD -->
        <!-- ============================================ -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
            <!-- Welcome Message -->
            <div class="lg:col-span-2 bg-gradient-to-r from-blue-600 to-purple-600 rounded-2xl p-6 text-white">
                <h2 class="text-2xl font-bold">Welcome back, <?php echo htmlspecialchars($student['student_name']); ?>! 👋</h2>
                <p class="text-blue-100 mt-1">Class <?php echo htmlspecialchars($student['class_name']); ?> • Admission: <?php echo $student['admission_number']; ?></p>
                <div class="mt-4 flex flex-wrap gap-3">
                    <div class="bg-white bg-opacity-20 rounded-lg px-3 py-1 text-sm">
                        <i class="fas fa-calendar-alt mr-1"></i> <?php echo date('l, F j, Y'); ?>
                    </div>
                    <div class="bg-white bg-opacity-20 rounded-lg px-3 py-1 text-sm">
                        <i class="fas fa-clock mr-1"></i> <?php echo date('h:i A'); ?>
                    </div>
                    <div class="bg-white bg-opacity-20 rounded-lg px-3 py-1 text-sm">
                        <i class="fas fa-graduation-cap mr-1"></i> Term <?php echo ceil(date('n') / 4); ?>
                    </div>
                </div>
            </div>
            
            <!-- Student Profile Card -->
            <div class="bg-white rounded-2xl shadow-sm p-4 flex items-center space-x-4">
                <div class="w-16 h-16 bg-gradient-to-r from-blue-500 to-purple-600 rounded-full flex items-center justify-center text-white text-2xl font-bold shadow-md">
                    <?php echo strtoupper(substr($student['student_name'], 0, 1)); ?>
                </div>
                <div>
                    <p class="text-gray-500 text-xs">Student ID</p>
                    <p class="font-semibold"><?php echo $student['admission_number']; ?></p>
                    <p class="text-gray-500 text-xs mt-1">Email</p>
                    <p class="text-sm"><?php echo htmlspecialchars($student['email']); ?></p>
                </div>
            </div>
        </div>

        <!-- ============================================ -->
        <!-- STATS CARDS -->
        <!-- ============================================ -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
            <div class="bg-white rounded-xl p-4 shadow-sm">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-500 text-sm">Attendance</p>
                        <p class="text-2xl font-bold <?php echo $attendance_percentage >= 80 ? 'text-green-600' : ($attendance_percentage >= 60 ? 'text-yellow-600' : 'text-red-600'); ?>">
                            <?php echo $attendance_percentage; ?>%
                        </p>
                    </div>
                    <div class="w-10 h-10 bg-blue-100 rounded-full flex items-center justify-center">
                        <i class="fas fa-calendar-check text-blue-600"></i>
                    </div>
                </div>
                <div class="mt-2 w-full bg-gray-200 rounded-full h-1.5">
                    <div class="bg-blue-600 h-1.5 rounded-full" style="width: <?php echo $attendance_percentage; ?>%"></div>
                </div>
            </div>
            <div class="bg-white rounded-xl p-4 shadow-sm">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-500 text-sm">Average Score</p>
                        <p class="text-2xl font-bold text-purple-600"><?php echo $avg_score; ?>%</p>
                    </div>
                    <div class="w-10 h-10 bg-purple-100 rounded-full flex items-center justify-center">
                        <i class="fas fa-chart-line text-purple-600"></i>
                    </div>
                </div>
            </div>
            <div class="bg-white rounded-xl p-4 shadow-sm">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-500 text-sm">Learning Progress</p>
                        <p class="text-2xl font-bold text-green-600"><?php echo $learning_progress; ?>%</p>
                    </div>
                    <div class="w-10 h-10 bg-green-100 rounded-full flex items-center justify-center">
                        <i class="fas fa-book-open text-green-600"></i>
                    </div>
                </div>
                <div class="mt-2 w-full bg-gray-200 rounded-full h-1.5">
                    <div class="bg-green-600 h-1.5 rounded-full" style="width: <?php echo $learning_progress; ?>%"></div>
                </div>
            </div>
            <div class="bg-white rounded-xl p-4 shadow-sm">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-500 text-sm">Topics Completed</p>
                        <p class="text-2xl font-bold text-orange-600"><?php echo $topics_completed; ?>/<?php echo $topics_total; ?></p>
                    </div>
                    <div class="w-10 h-10 bg-orange-100 rounded-full flex items-center justify-center">
                        <i class="fas fa-check-circle text-orange-600"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- ============================================ -->
        <!-- MAIN CONTENT GRID -->
        <!-- ============================================ -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- LEFT COLUMN - Today's Classes & Assignments -->
            <div class="lg:col-span-2 space-y-6">
                <!-- Today's Classes -->
                <div class="bg-white rounded-xl shadow-sm overflow-hidden">
                    <div class="p-4 border-b flex justify-between items-center">
                        <h3 class="font-semibold text-lg">
                            <i class="fas fa-calendar-day text-blue-500 mr-2"></i>
                            Today's Classes
                        </h3>
                        <a href="timetable/index.php" class="text-blue-600 text-sm">View Full Timetable →</a>
                    </div>
                    <div class="divide-y">
                        <?php if ($today_classes && $today_classes->num_rows > 0): ?>
                            <?php while($class = $today_classes->fetch_assoc()): ?>
                                <div class="p-4 hover:bg-gray-50 transition-all">
                                    <div class="flex justify-between items-center">
                                        <div>
                                            <p class="font-semibold"><?php echo htmlspecialchars($class['subject_name']); ?></p>
                                            <p class="text-sm text-gray-500">Teacher: <?php echo htmlspecialchars($class['teacher_name']); ?></p>
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
                                No classes scheduled for today
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Upcoming Assignments -->
                <div class="bg-white rounded-xl shadow-sm overflow-hidden">
                    <div class="p-4 border-b flex justify-between items-center">
                        <h3 class="font-semibold text-lg">
                            <i class="fas fa-tasks text-green-500 mr-2"></i>
                            Upcoming Assignments
                        </h3>
                        <a href="assignments/index.php" class="text-blue-600 text-sm">View All →</a>
                    </div>
                    <div class="divide-y">
                        <?php if ($upcoming_assignments && $upcoming_assignments->num_rows > 0): ?>
                            <?php while($ass = $upcoming_assignments->fetch_assoc()): 
                                $days_left = ceil((strtotime($ass['due_date']) - time()) / (60 * 60 * 24));
                                $is_submitted = $ass['submitted'] ? true : false;
                            ?>
                                <div class="p-4 hover:bg-gray-50 transition-all">
                                    <div class="flex justify-between items-start">
                                        <div>
                                            <p class="font-semibold"><?php echo htmlspecialchars($ass['title']); ?></p>
                                            <p class="text-sm text-gray-500"><?php echo htmlspecialchars($ass['subject_name']); ?></p>
                                        </div>
                                        <div class="text-right">
                                            <p class="text-sm font-medium <?php echo $days_left <= 2 ? 'text-red-600' : 'text-gray-600'; ?>">
                                                Due: <?php echo date('M d, Y', strtotime($ass['due_date'])); ?>
                                            </p>
                                            <p class="text-xs text-gray-400"><?php echo $days_left; ?> days left</p>
                                            <?php if($is_submitted): ?>
                                                <span class="text-xs text-green-600">✓ Submitted</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <div class="mt-2">
                                        <a href="assignments/view.php?id=<?php echo $ass['id']; ?>" class="text-blue-600 text-sm hover:underline">View Details →</a>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <div class="p-8 text-center text-gray-500">
                                <i class="fas fa-check-circle text-3xl mb-2 block text-green-500"></i>
                                No upcoming assignments! Great job!
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Academic Progress Chart -->
                <div class="bg-white rounded-xl shadow-sm p-4">
                    <h3 class="font-semibold text-sm mb-3">
                        <i class="fas fa-chart-pie text-purple-500 mr-2"></i>
                        Subject Performance
                    </h3>
                    <canvas id="subjectChart" height="180"></canvas>
                </div>
            </div>

            <!-- RIGHT COLUMN - Exams & Notifications -->
            <div class="space-y-6">
                <!-- ============================================ -->
                <!-- EXAMS MODULE - Upcoming Exams -->
                <!-- ============================================ -->
                <div class="bg-white rounded-xl shadow-sm overflow-hidden">
                    <div class="p-4 border-b flex justify-between items-center">
                        <h3 class="font-semibold text-lg">
                            <i class="fas fa-pen-alt text-red-500 mr-2"></i>
                            Upcoming Exams
                        </h3>
                        <a href="exams/index.php" class="text-blue-600 text-sm">View All →</a>
                    </div>
                    <div class="divide-y">
                        <?php if ($upcoming_exams && $upcoming_exams->num_rows > 0): ?>
                            <?php while($exam = $upcoming_exams->fetch_assoc()): ?>
                                <div class="p-3 hover:bg-gray-50">
                                    <div class="flex justify-between items-start">
                                        <div>
                                            <p class="font-medium text-sm"><?php echo htmlspecialchars($exam['title']); ?></p>
                                            <p class="text-xs text-gray-500"><?php echo htmlspecialchars($exam['subject_name']); ?></p>
                                            <p class="text-xs text-blue-600 mt-1">
                                                <i class="fas fa-calendar mr-1"></i> <?php echo date('M d, Y', strtotime($exam['start_date'])); ?>
                                                <?php if($exam['days_left'] > 0): ?>
                                                    <span class="text-gray-400">(in <?php echo $exam['days_left']; ?> days)</span>
                                                <?php endif; ?>
                                            </p>
                                        </div>
                                        <div class="text-right">
                                            <span class="text-xs text-gray-400"><?php echo $exam['duration_minutes']; ?> min</span>
                                            <div class="mt-1">
                                                <a href="exams/instructions.php?id=<?php echo $exam['id']; ?>" 
                                                   class="text-xs bg-blue-600 text-white px-2 py-1 rounded hover:bg-blue-700">
                                                    View Details
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <div class="p-6 text-center text-gray-500">
                                <i class="fas fa-calendar-check text-2xl mb-2 block"></i>
                                No upcoming exams
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Active Exams (Available Now) -->
                <?php if ($active_exams && $active_exams->num_rows > 0): ?>
                <div class="bg-gradient-to-r from-green-50 to-teal-50 rounded-xl shadow-sm overflow-hidden border border-green-200">
                    <div class="p-4 border-b border-green-200 bg-green-100">
                        <h3 class="font-semibold text-lg text-green-700">
                            <i class="fas fa-play-circle text-green-600 mr-2"></i>
                            Active Exams Available Now!
                        </h3>
                    </div>
                    <div class="divide-y divide-green-100">
                        <?php while($exam = $active_exams->fetch_assoc()): 
                            $minutes_remaining = $exam['minutes_remaining'] ?? 0;
                            $hours = floor($minutes_remaining / 60);
                            $mins = $minutes_remaining % 60;
                        ?>
                            <div class="p-3 hover:bg-green-50">
                                <div class="flex justify-between items-start">
                                    <div>
                                        <p class="font-medium text-sm text-green-800"><?php echo htmlspecialchars($exam['title']); ?></p>
                                        <p class="text-xs text-gray-600"><?php echo htmlspecialchars($exam['subject_name']); ?></p>
                                        <p class="text-xs text-red-600 mt-1">
                                            <i class="fas fa-hourglass-half mr-1"></i> 
                                            Time remaining: <?php echo $hours > 0 ? $hours . 'h ' : ''; ?><?php echo $mins; ?> min
                                        </p>
                                    </div>
                                    <div class="text-right">
                                        <?php if($exam['already_submitted'] == 0): ?>
                                            <a href="exams/instructions.php?id=<?php echo $exam['id']; ?>" 
                                               class="inline-block bg-green-600 text-white px-3 py-1 rounded-lg text-sm font-semibold hover:bg-green-700 animate-pulse">
                                                <i class="fas fa-play mr-1"></i> Take Exam Now!
                                            </a>
                                        <?php else: ?>
                                            <span class="text-xs text-gray-400">Already submitted</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Completed Exams (Results) -->
                <?php if ($completed_exams && $completed_exams->num_rows > 0): ?>
                <div class="bg-white rounded-xl shadow-sm overflow-hidden">
                    <div class="p-4 border-b flex justify-between items-center">
                        <h3 class="font-semibold text-lg">
                            <i class="fas fa-chart-line text-purple-500 mr-2"></i>
                            Recent Exam Results
                        </h3>
                        <a href="exams/results.php" class="text-blue-600 text-sm">View All →</a>
                    </div>
                    <div class="divide-y">
                        <?php while($exam = $completed_exams->fetch_assoc()): ?>
                            <div class="p-3 hover:bg-gray-50">
                                <div class="flex justify-between items-center">
                                    <div>
                                        <p class="font-medium text-sm"><?php echo htmlspecialchars($exam['title']); ?></p>
                                        <p class="text-xs text-gray-500"><?php echo htmlspecialchars($exam['subject_name']); ?></p>
                                    </div>
                                    <div class="text-right">
                                        <p class="text-sm font-bold <?php echo $exam['percentage'] >= 50 ? 'text-green-600' : 'text-red-600'; ?>">
                                            <?php echo round($exam['percentage'], 1); ?>%
                                        </p>
                                        <p class="text-xs text-gray-400">Grade: <?php echo $exam['grade']; ?></p>
                                    </div>
                                </div>
                                <div class="mt-2">
                                    <a href="exams/results.php?id=<?php echo $exam['exam_id']; ?>" 
                                       class="text-blue-600 text-xs hover:underline">View Details →</a>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Recent Notifications -->
                <div class="bg-white rounded-xl shadow-sm overflow-hidden">
                    <div class="p-4 border-b">
                        <h3 class="font-semibold text-lg">
                            <i class="fas fa-bell text-yellow-500 mr-2"></i>
                            Recent Notifications
                        </h3>
                    </div>
                    <div class="divide-y max-h-64 overflow-y-auto">
                        <?php if ($notifications && $notifications->num_rows > 0): ?>
                            <?php while($notif = $notifications->fetch_assoc()): ?>
                                <div class="p-3 hover:bg-gray-50">
                                    <p class="font-medium text-sm"><?php echo htmlspecialchars($notif['title']); ?></p>
                                    <p class="text-xs text-gray-500"><?php echo htmlspecialchars(substr($notif['message'], 0, 60)); ?></p>
                                    <p class="text-xs text-gray-400 mt-1"><?php echo getTimeAgo($notif['created_at']); ?></p>
                                </div>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <div class="p-6 text-center text-gray-500">
                                <i class="fas fa-bell-slash text-2xl mb-2 block"></i>
                                No new notifications
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Calendar & Events -->
                <div class="bg-white rounded-xl shadow-sm overflow-hidden">
                    <div class="p-4 border-b">
                        <h3 class="font-semibold text-lg">
                            <i class="fas fa-calendar-alt text-blue-500 mr-2"></i>
                            Calendar & Events
                        </h3>
                    </div>
                    <div class="divide-y">
                        <?php if ($calendar_events && $calendar_events->num_rows > 0): ?>
                            <?php while($event = $calendar_events->fetch_assoc()): ?>
                                <div class="p-3 hover:bg-gray-50">
                                    <div class="flex items-start space-x-2">
                                        <div class="w-8 h-8 rounded-full <?php echo $event['type'] == 'Exam' ? 'bg-red-100' : 'bg-green-100'; ?> flex items-center justify-center">
                                            <i class="fas <?php echo $event['type'] == 'Exam' ? 'fa-pen-alt text-red-500' : 'fa-tasks text-green-500'; ?> text-xs"></i>
                                        </div>
                                        <div>
                                            <p class="text-sm font-medium"><?php echo htmlspecialchars($event['title']); ?></p>
                                            <p class="text-xs text-gray-500"><?php echo $event['type']; ?> • <?php echo date('M d', strtotime($event['date'])); ?></p>
                                        </div>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <div class="p-6 text-center text-gray-500">
                                <i class="fas fa-calendar-week text-2xl mb-2 block"></i>
                                No events this week
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Learning Progress Tracker -->
                <div class="bg-white rounded-xl shadow-sm p-4">
                    <h3 class="font-semibold text-sm mb-3">
                        <i class="fas fa-chart-simple text-green-500 mr-2"></i>
                        Learning Progress Tracker
                    </h3>
                    <div class="text-center">
                        <div class="relative inline-block">
                            <svg class="w-24 h-24">
                                <circle class="text-gray-200" stroke-width="8" stroke="currentColor" fill="transparent" r="40" cx="48" cy="48"/>
                                <circle class="text-green-500" stroke-width="8" stroke-dasharray="<?php echo (251.2 * $learning_progress) / 100; ?>" stroke-dashoffset="0" stroke-linecap="round" stroke="currentColor" fill="transparent" r="40" cx="48" cy="48" transform="rotate(-90 48 48)"/>
                            </svg>
                            <span class="absolute top-1/2 left-1/2 transform -translate-x-1/2 -translate-y-1/2 text-xl font-bold"><?php echo $learning_progress; ?>%</span>
                        </div>
                        <p class="text-xs text-gray-500 mt-2">Overall course completion</p>
                        <a href="progress/index.php" class="text-blue-600 text-xs mt-2 inline-block">View Details →</a>
                    </div>
                </div>
            </div>
        </div>

        <!-- ============================================ -->
        <!-- QUICK LINKS / MODULE ACCESS -->
        <!-- ============================================ -->
        <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-7 gap-3 mt-6">
            <a href="materials/index.php" class="bg-blue-50 hover:bg-blue-100 rounded-xl p-3 text-center transition">
                <i class="fas fa-book-open text-blue-600 text-xl mb-1 block"></i>
                <span class="text-xs font-semibold">Materials</span>
            </a>
            <a href="assignments/index.php" class="bg-green-50 hover:bg-green-100 rounded-xl p-3 text-center transition">
                <i class="fas fa-tasks text-green-600 text-xl mb-1 block"></i>
                <span class="text-xs font-semibold">Assignments</span>
            </a>
            <a href="exams/index.php" class="bg-red-50 hover:bg-red-100 rounded-xl p-3 text-center transition">
                <i class="fas fa-pen-alt text-red-600 text-xl mb-1 block"></i>
                <span class="text-xs font-semibold">Exams</span>
            </a>
            <a href="results/index.php" class="bg-purple-50 hover:bg-purple-100 rounded-xl p-3 text-center transition">
                <i class="fas fa-chart-line text-purple-600 text-xl mb-1 block"></i>
                <span class="text-xs font-semibold">Results</span>
            </a>
            <a href="attendance/index.php" class="bg-yellow-50 hover:bg-yellow-100 rounded-xl p-3 text-center transition">
                <i class="fas fa-calendar-check text-yellow-600 text-xl mb-1 block"></i>
                <span class="text-xs font-semibold">Attendance</span>
            </a>
            <a href="timetable/index.php" class="bg-indigo-50 hover:bg-indigo-100 rounded-xl p-3 text-center transition">
                <i class="fas fa-calendar-alt text-indigo-600 text-xl mb-1 block"></i>
                <span class="text-xs font-semibold">Timetable</span>
            </a>
            <a href="communication/index.php" class="bg-pink-50 hover:bg-pink-100 rounded-xl p-3 text-center transition">
                <i class="fas fa-comments text-pink-600 text-xl mb-1 block"></i>
                <span class="text-xs font-semibold">Messages</span>
            </a>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// Subject Performance Chart
const subjectLabels = [];
const subjectScores = [];

<?php while($subj = $subject_performance->fetch_assoc()): ?>
subjectLabels.push('<?php echo addslashes($subj['name']); ?>');
subjectScores.push(<?php echo round($subj['avg_score'], 1); ?>);
<?php endwhile; ?>

const ctx = document.getElementById('subjectChart').getContext('2d');
new Chart(ctx, {
    type: 'bar',
    data: {
        labels: subjectLabels,
        datasets: [{
            label: 'Average Score (%)',
            data: subjectScores,
            backgroundColor: '#8b5cf6',
            borderRadius: 8
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: true,
        scales: {
            y: { beginAtZero: true, max: 100 }
        },
        plugins: {
            legend: { position: 'bottom' }
        }
    }
});
</script>

<?php include '../includes/footer.php'; ?>