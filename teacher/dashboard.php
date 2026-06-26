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

// Get classes taught by this teacher
$classes_query = $conn->prepare("
    SELECT DISTINCT c.id, c.name 
    FROM class_subject cs
    JOIN classes c ON cs.class_id = c.id
    WHERE cs.teacher_id = ?
");
$classes_query->bind_param("i", $teacher_id);
$classes_query->execute();
$classes = $classes_query->get_result();

// Get statistics
$stats = [];

// Total students across all classes
$students_query = $conn->prepare("
    SELECT COUNT(DISTINCT s.id) as count
    FROM students s
    JOIN class_subject cs ON s.class_id = cs.class_id
    WHERE cs.teacher_id = ?
");
$students_query->bind_param("i", $teacher_id);
$students_query->execute();
$stats['total_students'] = $students_query->get_result()->fetch_assoc()['count'];

// Total assignments created
$assignments_query = $conn->prepare("
    SELECT COUNT(*) as count FROM assignments WHERE created_by = ?
");
$assignments_query->bind_param("i", $_SESSION['user_id']);
$assignments_query->execute();
$stats['total_assignments'] = $assignments_query->get_result()->fetch_assoc()['count'];

// Total exams created
$exams_query = $conn->prepare("
    SELECT COUNT(*) as count FROM teacher_exams WHERE teacher_id = ?
");
$exams_query->bind_param("i", $teacher_id);
$exams_query->execute();
$stats['total_exams'] = $exams_query->get_result()->fetch_assoc()['count'];

// Pending submissions to grade
$pending_query = $conn->prepare("
    SELECT COUNT(*) as count 
    FROM submissions s
    JOIN assignments a ON s.assignment_id = a.id
    WHERE a.created_by = ? AND s.marks_obtained IS NULL
");
$pending_query->bind_param("i", $_SESSION['user_id']);
$pending_query->execute();
$stats['pending_grading'] = $pending_query->get_result()->fetch_assoc()['count'];

// Pending exam grading
$exam_pending_query = $conn->prepare("
    SELECT COUNT(*) as count 
    FROM exam_submissions es
    JOIN teacher_exams te ON es.exam_id = te.id
    WHERE te.teacher_id = ? AND es.is_graded = 0 AND es.submitted_at IS NOT NULL
");
$exam_pending_query->bind_param("i", $teacher_id);
$exam_pending_query->execute();
$stats['exam_pending_grading'] = $exam_pending_query->get_result()->fetch_assoc()['count'];

// Today's classes from timetable
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

// Recent assignments
$recent_assignments = $conn->prepare("
    SELECT * FROM assignments 
    WHERE created_by = ? 
    ORDER BY created_at DESC LIMIT 5
");
$recent_assignments->bind_param("i", $_SESSION['user_id']);
$recent_assignments->execute();
$recent_assignments = $recent_assignments->get_result();

// Recent exams
$recent_exams = $conn->prepare("
    SELECT te.*, s.name as subject_name, c.name as class_name
    FROM teacher_exams te
    JOIN subjects s ON te.subject_id = s.id
    JOIN classes c ON te.class_id = c.id
    WHERE te.teacher_id = ?
    ORDER BY te.created_at DESC LIMIT 5
");
$recent_exams->bind_param("i", $teacher_id);
$recent_exams->execute();
$recent_exams = $recent_exams->get_result();

// Pending submissions list
$pending_list = $conn->prepare("
    SELECT s.*, a.title as assignment_title, 
           CONCAT(u.first_name, ' ', u.last_name) as student_name
    FROM submissions s
    JOIN assignments a ON s.assignment_id = a.id
    JOIN students st ON s.student_id = st.id
    JOIN users u ON st.user_id = u.id
    WHERE a.created_by = ? AND s.marks_obtained IS NULL
    ORDER BY s.submitted_at DESC LIMIT 10
");
$pending_list->bind_param("i", $_SESSION['user_id']);
$pending_list->execute();
$pending_list = $pending_list->get_result();

// Pending exam submissions list
$exam_pending_list = $conn->prepare("
    SELECT es.*, te.title as exam_title, 
           CONCAT(u.first_name, ' ', u.last_name) as student_name
    FROM exam_submissions es
    JOIN teacher_exams te ON es.exam_id = te.id
    JOIN students st ON es.student_id = st.id
    JOIN users u ON st.user_id = u.id
    WHERE te.teacher_id = ? AND es.is_graded = 0 AND es.submitted_at IS NOT NULL
    ORDER BY es.submitted_at DESC LIMIT 10
");
$exam_pending_list->bind_param("i", $teacher_id);
$exam_pending_list->execute();
$exam_pending_list = $exam_pending_list->get_result();

// Upcoming exams
$upcoming_exams = $conn->prepare("
    SELECT te.*, s.name as subject_name, c.name as class_name
    FROM teacher_exams te
    JOIN subjects s ON te.subject_id = s.id
    JOIN classes c ON te.class_id = c.id
    WHERE te.teacher_id = ? AND te.start_date >= CURDATE() AND te.is_published = 1
    ORDER BY te.start_date ASC LIMIT 5
");
$upcoming_exams->bind_param("i", $teacher_id);
$upcoming_exams->execute();
$upcoming_exams = $upcoming_exams->get_result();
?>

<div class="ml-64 mt-16 p-6">
    <!-- Welcome Section -->
    <div class="bg-gradient-to-r from-blue-500 to-purple-600 rounded-2xl p-6 mb-6 text-white">
        <h2 class="text-2xl font-bold">Welcome back, <?php echo htmlspecialchars($_SESSION['user_name']); ?>!</h2>
        <p class="mt-2">Manage your classes, assignments, exams, and student progress</p>
    </div>

    <!-- Stats Cards -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4 mb-6">
        <div class="bg-white rounded-xl p-4 shadow-sm hover:shadow-lg transition-all">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-gray-500 text-xs">My Students</p>
                    <p class="text-2xl font-bold"><?php echo $stats['total_students']; ?></p>
                </div>
                <i class="fas fa-users text-blue-500 text-2xl"></i>
            </div>
        </div>
        <div class="bg-white rounded-xl p-4 shadow-sm hover:shadow-lg transition-all">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-gray-500 text-xs">Assignments</p>
                    <p class="text-2xl font-bold"><?php echo $stats['total_assignments']; ?></p>
                </div>
                <i class="fas fa-tasks text-green-500 text-2xl"></i>
            </div>
        </div>
        <div class="bg-white rounded-xl p-4 shadow-sm hover:shadow-lg transition-all">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-gray-500 text-xs">Exams Created</p>
                    <p class="text-2xl font-bold"><?php echo $stats['total_exams']; ?></p>
                </div>
                <i class="fas fa-pen-alt text-purple-500 text-2xl"></i>
            </div>
        </div>
        <div class="bg-white rounded-xl p-4 shadow-sm hover:shadow-lg transition-all">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-gray-500 text-xs">Pending Grading</p>
                    <p class="text-2xl font-bold text-yellow-600"><?php echo $stats['pending_grading'] + $stats['exam_pending_grading']; ?></p>
                </div>
                <i class="fas fa-clock text-yellow-500 text-2xl"></i>
            </div>
        </div>
        <div class="bg-white rounded-xl p-4 shadow-sm hover:shadow-lg transition-all">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-gray-500 text-xs">My Classes</p>
                    <p class="text-2xl font-bold"><?php echo $classes->num_rows; ?></p>
                </div>
                <i class="fas fa-chalkboard text-purple-500 text-2xl"></i>
            </div>
        </div>
    </div>

    <!-- Today's Schedule & Pending Tasks -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
        <!-- Today's Schedule -->
        <div class="bg-white rounded-xl shadow-sm">
            <div class="p-4 border-b flex justify-between items-center">
                <h3 class="font-semibold text-lg">
                    <i class="fas fa-calendar-day text-blue-500 mr-2"></i>
                    Today's Schedule (<?php echo ucfirst($today); ?>)
                </h3>
                <a href="attendance/mark.php" class="text-blue-600 text-sm">Mark Attendance →</a>
            </div>
            <div class="divide-y">
                <?php if ($today_schedule && $today_schedule->num_rows > 0): ?>
                    <?php while($class = $today_schedule->fetch_assoc()): ?>
                        <div class="p-4 hover:bg-gray-50">
                            <div class="flex justify-between items-start">
                                <div>
                                    <p class="font-semibold"><?php echo htmlspecialchars($class['subject_name']); ?></p>
                                    <p class="text-sm text-gray-500"><?php echo htmlspecialchars($class['class_name']); ?></p>
                                    <p class="text-xs text-gray-400 mt-1">
                                        <i class="fas fa-clock mr-1"></i>
                                        <?php echo date('h:i A', strtotime($class['start_time'])); ?> - 
                                        <?php echo date('h:i A', strtotime($class['end_time'])); ?>
                                    </p>
                                </div>
                                <div class="flex space-x-2">
                                    <a href="attendance/mark.php?class_id=<?php echo $class['class_id']; ?>" 
                                       class="text-green-600 hover:text-green-800 text-sm">
                                        <i class="fas fa-check-circle"></i>
                                    </a>
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

        <!-- Pending Submissions & Exams -->
        <div class="bg-white rounded-xl shadow-sm">
            <div class="p-4 border-b flex justify-between items-center">
                <h3 class="font-semibold text-lg">
                    <i class="fas fa-clock text-yellow-500 mr-2"></i>
                    Pending Grading
                </h3>
                <a href="assignments/index.php" class="text-blue-600 text-sm">View All →</a>
            </div>
            <div class="divide-y max-h-80 overflow-y-auto">
                <?php if ($pending_list && $pending_list->num_rows > 0): ?>
                    <?php while($sub = $pending_list->fetch_assoc()): ?>
                        <div class="p-3 hover:bg-gray-50">
                            <div class="flex justify-between items-center">
                                <div>
                                    <p class="font-medium text-sm">📝 <?php echo htmlspecialchars($sub['assignment_title']); ?></p>
                                    <p class="text-xs text-gray-500"><?php echo htmlspecialchars($sub['student_name']); ?> • <?php echo getTimeAgo($sub['submitted_at']); ?></p>
                                </div>
                                <a href="assignments/grade.php?id=<?php echo $sub['id']; ?>" 
                                   class="bg-blue-600 text-white px-2 py-1 rounded text-xs hover:bg-blue-700">
                                    Grade
                                </a>
                            </div>
                        </div>
                    <?php endwhile; ?>
                <?php endif; ?>
                <?php if ($exam_pending_list && $exam_pending_list->num_rows > 0): ?>
                    <?php while($exam = $exam_pending_list->fetch_assoc()): ?>
                        <div class="p-3 hover:bg-gray-50">
                            <div class="flex justify-between items-center">
                                <div>
                                    <p class="font-medium text-sm">📝 <?php echo htmlspecialchars($exam['exam_title']); ?></p>
                                    <p class="text-xs text-gray-500"><?php echo htmlspecialchars($exam['student_name']); ?> • <?php echo getTimeAgo($exam['submitted_at']); ?></p>
                                </div>
                                <a href="exams/grade.php?id=<?php echo $exam['id']; ?>&exam_id=<?php echo $exam['exam_id']; ?>" 
                                   class="bg-purple-600 text-white px-2 py-1 rounded text-xs hover:bg-purple-700">
                                    Grade Exam
                                </a>
                            </div>
                        </div>
                    <?php endwhile; ?>
                <?php endif; ?>
                <?php if ($pending_list->num_rows == 0 && $exam_pending_list->num_rows == 0): ?>
                    <div class="p-8 text-center text-gray-500">
                        <i class="fas fa-check-circle text-3xl mb-2 block text-green-500"></i>
                        No pending submissions to grade
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="grid grid-cols-2 md:grid-cols-5 gap-3 mb-6">
        <a href="materials/upload.php" class="bg-blue-50 hover:bg-blue-100 rounded-xl p-3 text-center transition">
            <i class="fas fa-upload text-blue-600 text-xl mb-1 block"></i>
            <span class="font-semibold text-xs">Upload Materials</span>
        </a>
        <a href="assignments/create.php" class="bg-green-50 hover:bg-green-100 rounded-xl p-3 text-center transition">
            <i class="fas fa-plus-circle text-green-600 text-xl mb-1 block"></i>
            <span class="font-semibold text-xs">Create Assignment</span>
        </a>
        <a href="attendance/mark.php" class="bg-yellow-50 hover:bg-yellow-100 rounded-xl p-3 text-center transition">
            <i class="fas fa-calendar-check text-yellow-600 text-xl mb-1 block"></i>
            <span class="font-semibold text-xs">Mark Attendance</span>
        </a>
        <a href="exams/create.php" class="bg-purple-50 hover:bg-purple-100 rounded-xl p-3 text-center transition">
            <i class="fas fa-pen-alt text-purple-600 text-xl mb-1 block"></i>
            <span class="font-semibold text-xs">Create Exam</span>
        </a>
        <a href="exams/index.php" class="bg-red-50 hover:bg-red-100 rounded-xl p-3 text-center transition">
            <i class="fas fa-chart-line text-red-600 text-xl mb-1 block"></i>
            <span class="font-semibold text-xs">View Exams</span>
        </a>
    </div>

    <!-- Recent Assignments & Exams -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Recent Assignments -->
        <div class="bg-white rounded-xl shadow-sm">
            <div class="p-4 border-b flex justify-between items-center">
                <h3 class="font-semibold text-lg">Recent Assignments</h3>
                <a href="assignments/index.php" class="text-blue-600 text-sm">View All →</a>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-gray-50 border-b text-xs">
                        <tr>
                            <th class="px-3 py-2 text-left">Title</th>
                            <th class="px-3 py-2 text-left">Due Date</th>
                            <th class="px-3 py-2 text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <?php if ($recent_assignments && $recent_assignments->num_rows > 0): ?>
                            <?php while($ass = $recent_assignments->fetch_assoc()): ?>
                                <tr class="hover:bg-gray-50 text-sm">
                                    <td class="px-3 py-2"><?php echo htmlspecialchars(substr($ass['title'], 0, 30)); ?></td>
                                    <td class="px-3 py-2"><?php echo date('M d', strtotime($ass['due_date'])); ?></td>
                                    <td class="px-3 py-2 text-center">
                                        <a href="assignments/submissions.php?id=<?php echo $ass['id']; ?>" class="text-blue-600 hover:text-blue-800">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="3" class="px-3 py-4 text-center text-gray-400 text-sm">No assignments yet</td><ee
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Recent Exams -->
        <div class="bg-white rounded-xl shadow-sm">
            <div class="p-4 border-b flex justify-between items-center">
                <h3 class="font-semibold text-lg">Recent Exams</h3>
                <a href="exams/index.php" class="text-blue-600 text-sm">View All →</a>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-gray-50 border-b text-xs">
                        <tr>
                            <th class="px-3 py-2 text-left">Title</th>
                            <th class="px-3 py-2 text-left">Class</th>
                            <th class="px-3 py-2 text-left">Date</th>
                            <th class="px-3 py-2 text-center">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <?php if ($recent_exams && $recent_exams->num_rows > 0): ?>
                            <?php while($exam = $recent_exams->fetch_assoc()): 
                                $status = '';
                                $status_class = '';
                                $today_date = date('Y-m-d');
                                if ($exam['start_date'] > $today_date) {
                                    $status = 'Upcoming';
                                    $status_class = 'text-blue-600';
                                } elseif ($exam['end_date'] < $today_date) {
                                    $status = 'Completed';
                                    $status_class = 'text-gray-600';
                                } else {
                                    $status = 'Active';
                                    $status_class = 'text-green-600';
                                }
                            ?>
                                <tr class="hover:bg-gray-50 text-sm">
                                    <td class="px-3 py-2"><?php echo htmlspecialchars(substr($exam['title'], 0, 25)); ?></td>
                                    <td class="px-3 py-2"><?php echo htmlspecialchars($exam['class_name']); ?></td>
                                    <td class="px-3 py-2"><?php echo date('M d', strtotime($exam['start_date'])); ?></td>
                                    <td class="px-3 py-2 text-center">
                                        <span class="text-xs <?php echo $status_class; ?>"><?php echo $status; ?></span>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="4" class="px-3 py-4 text-center text-gray-400 text-sm">No exams created yet</td><ee
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <!-- Upcoming Exams Section -->
    <?php if ($upcoming_exams && $upcoming_exams->num_rows > 0): ?>
    <div class="mt-6 bg-white rounded-xl shadow-sm">
        <div class="p-4 border-b bg-yellow-50">
            <h3 class="font-semibold text-lg">
                <i class="fas fa-bell text-yellow-600 mr-2"></i> Upcoming Exams
            </h3>
        </div>
        <div class="divide-y">
            <?php while($exam = $upcoming_exams->fetch_assoc()): ?>
                <div class="p-3 flex justify-between items-center">
                    <div>
                        <p class="font-medium"><?php echo htmlspecialchars($exam['title']); ?></p>
                        <p class="text-xs text-gray-500"><?php echo htmlspecialchars($exam['class_name']); ?> • <?php echo htmlspecialchars($exam['subject_name']); ?></p>
                    </div>
                    <div class="text-right">
                        <p class="text-sm font-semibold text-yellow-600"><?php echo date('M d, Y', strtotime($exam['start_date'])); ?></p>
                        <p class="text-xs text-gray-400"><?php echo $exam['duration_minutes']; ?> minutes</p>
                    </div>
                </div>
            <?php endwhile; ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php include '../includes/footer.php'; ?>