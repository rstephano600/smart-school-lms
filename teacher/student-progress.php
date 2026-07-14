<?php
require_once '../config.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
requireRole('teacher');

$student_id = $_GET['id'] ?? 0;

if (!$student_id) {
    header('Location: dashboard.php?error=No student selected');
    exit();
}

// Get student details
$student_query = $conn->prepare("
    SELECT u.id, u.first_name, u.last_name, u.email, u.phone, 
           u.total_logins, u.total_time_online, u.last_activity, u.last_login,
           u.created_at as registered_date,
           s.admission_number, s.date_of_birth, s.gender, s.address,
           c.id as class_id, c.name as class_name,
           (SELECT COUNT(*) FROM activity_logs WHERE user_id = u.id) as total_activities,
           (SELECT COUNT(*) FROM activity_logs WHERE user_id = u.id AND DATE(created_at) = CURDATE()) as today_activities,
           (SELECT COUNT(*) FROM activity_logs WHERE user_id = u.id AND WEEK(created_at) = WEEK(CURDATE())) as week_activities
    FROM users u
    JOIN students s ON u.id = s.user_id
    JOIN classes c ON s.class_id = c.id
    WHERE u.id = ?
");
$student_query->bind_param("i", $student_id);
$student_query->execute();
$student = $student_query->get_result()->fetch_assoc();

if (!$student) {
    header('Location: dashboard.php?error=Student not found');
    exit();
}

// Get teacher ID to verify access
$teacher_query = $conn->prepare("SELECT id FROM teachers WHERE user_id = ?");
$teacher_query->bind_param("i", $_SESSION['user_id']);
$teacher_query->execute();
$teacher = $teacher_query->get_result()->fetch_assoc();
$teacher_id = $teacher['id'] ?? 0;

// Verify teacher has access to this student
$access_query = $conn->prepare("
    SELECT COUNT(*) as count 
    FROM class_subject cs
    JOIN students s ON s.class_id = cs.class_id
    WHERE cs.teacher_id = ? AND s.user_id = ?
");
$access_query->bind_param("ii", $teacher_id, $student_id);
$access_query->execute();
$access = $access_query->get_result()->fetch_assoc()['count'];

if ($access == 0) {
    header('Location: dashboard.php?error=You do not have access to this student');
    exit();
}

// Get session history
$sessions = $conn->prepare("
    SELECT * FROM student_sessions 
    WHERE student_id = (SELECT id FROM students WHERE user_id = ?)
    ORDER BY session_start DESC LIMIT 20
");
$sessions->bind_param("i", $student_id);
$sessions->execute();
$sessions = $sessions->get_result();

// Get exam results
$exams = $conn->prepare("
    SELECT te.title, es.total_score, te.total_marks, es.percentage, es.grade, es.submitted_at,
           s.name as subject_name
    FROM exam_submissions es
    JOIN teacher_exams te ON es.exam_id = te.id
    JOIN subjects s ON te.subject_id = s.id
    WHERE es.student_id = (SELECT id FROM students WHERE user_id = ?)
    ORDER BY es.submitted_at DESC
");
$exams->bind_param("i", $student_id);
$exams->execute();
$exams = $exams->get_result();

// Get assignment submissions
$assignments = $conn->prepare("
    SELECT a.title, sub.name as subject_name, s.marks_obtained, a.max_marks, s.submitted_at,
           CASE WHEN s.marks_obtained IS NULL THEN 'Pending' 
                WHEN s.marks_obtained >= (a.max_marks * 0.5) THEN 'Passed' 
                ELSE 'Failed' END as status
    FROM submissions s
    JOIN assignments a ON s.assignment_id = a.id
    JOIN subjects sub ON a.subject_id = sub.id
    WHERE s.student_id = (SELECT id FROM students WHERE user_id = ?)
    ORDER BY s.submitted_at DESC LIMIT 10
");
$assignments->bind_param("i", $student_id);
$assignments->execute();
$assignments = $assignments->get_result();

// Get attendance
$attendance = $conn->prepare("
    SELECT date, status 
    FROM attendance 
    WHERE student_id = (SELECT id FROM students WHERE user_id = ?)
    ORDER BY date DESC LIMIT 20
");
$attendance->bind_param("i", $student_id);
$attendance->execute();
$attendance = $attendance->get_result();

// Calculate attendance percentage
$att_total = 0;
$att_present = 0;
while ($att = $attendance->fetch_assoc()) {
    $att_total++;
    if ($att['status'] == 'present') $att_present++;
}
$attendance->data_seek(0);
$attendance_percentage = $att_total > 0 ? round(($att_present / $att_total) * 100, 1) : 0;

$page_title = 'Student Progress - ' . $student['first_name'] . ' ' . $student['last_name'];
include '../includes/header.php';
include '../includes/sidebar.php';
include '../includes/navbar.php';
?>

<div class="ml-64 mt-16 p-6 bg-gray-50 min-h-screen">
    <div class="max-w-6xl mx-auto">
        <!-- Header -->
        <div class="mb-6 flex justify-between items-center">
            <div>
                <h1 class="text-2xl font-bold text-gray-800">Student Progress Report</h1>
                <p class="text-gray-500 mt-1"><?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?> - <?php echo htmlspecialchars($student['class_name']); ?></p>
            </div>
            <div class="flex space-x-2">
                <button onclick="window.print()" class="bg-gray-600 text-white px-4 py-2 rounded-lg hover:bg-gray-700">
                    <i class="fas fa-print mr-2"></i> Print
                </button>
                <a href="dashboard.php" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700">
                    <i class="fas fa-arrow-left mr-2"></i> Back
                </a>
            </div>
        </div>

        <!-- Student Profile Card -->
        <div class="bg-gradient-to-r from-blue-600 to-purple-600 rounded-2xl p-6 mb-6 text-white">
            <div class="flex items-start justify-between">
                <div>
                    <div class="flex items-center space-x-4">
                        <div class="w-16 h-16 bg-white bg-opacity-20 rounded-full flex items-center justify-center text-3xl font-bold">
                            <?php echo strtoupper(substr($student['first_name'], 0, 1) . substr($student['last_name'], 0, 1)); ?>
                        </div>
                        <div>
                            <h2 class="text-xl font-bold"><?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?></h2>
                            <p class="text-blue-100"><?php echo htmlspecialchars($student['admission_number']); ?> • <?php echo htmlspecialchars($student['class_name']); ?></p>
                        </div>
                    </div>
                    <div class="mt-4 flex flex-wrap gap-4 text-sm">
                        <div class="bg-white bg-opacity-20 rounded-lg px-3 py-1">
                            <i class="fas fa-envelope mr-1"></i> <?php echo htmlspecialchars($student['email']); ?>
                        </div>
                        <?php if($student['phone']): ?>
                            <div class="bg-white bg-opacity-20 rounded-lg px-3 py-1">
                                <i class="fas fa-phone mr-1"></i> <?php echo htmlspecialchars($student['phone']); ?>
                            </div>
                        <?php endif; ?>
                        <?php if($student['date_of_birth']): ?>
                            <div class="bg-white bg-opacity-20 rounded-lg px-3 py-1">
                                <i class="fas fa-calendar mr-1"></i> DOB: <?php echo date('M d, Y', strtotime($student['date_of_birth'])); ?>
                            </div>
                        <?php endif; ?>
                        <div class="bg-white bg-opacity-20 rounded-lg px-3 py-1">
                            <i class="fas fa-calendar-alt mr-1"></i> Registered: <?php echo date('M d, Y', strtotime($student['registered_date'])); ?>
                        </div>
                    </div>
                </div>
                <div class="text-right">
                    <div class="bg-white bg-opacity-20 rounded-lg px-4 py-2">
                        <p class="text-sm opacity-75">Current Status</p>
                        <p class="text-lg font-bold">
                            <?php if($student['last_activity'] && strtotime($student['last_activity']) > strtotime('-5 minutes')): ?>
                                <span class="text-green-300">● Online</span>
                            <?php else: ?>
                                <span class="text-gray-300">● Offline</span>
                            <?php endif; ?>
                        </p>
                        <p class="text-xs opacity-75">Last active: <?php echo $student['last_activity'] ? getTimeAgo($student['last_activity']) : 'Never'; ?></p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Stats Cards -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
            <div class="bg-white rounded-xl p-4 shadow-sm">
                <p class="text-gray-500 text-sm">Total Logins</p>
                <p class="text-2xl font-bold text-blue-600"><?php echo $student['total_logins']; ?></p>
            </div>
            <div class="bg-white rounded-xl p-4 shadow-sm">
                <p class="text-gray-500 text-sm">Total Time Online</p>
                <p class="text-2xl font-bold text-green-600">
                    <?php 
                    $hours = floor(($student['total_time_online'] ?? 0) / 60);
                    $mins = ($student['total_time_online'] ?? 0) % 60;
                    echo ($hours > 0 ? $hours . 'h ' : '') . $mins . 'm';
                    ?>
                </p>
            </div>
            <div class="bg-white rounded-xl p-4 shadow-sm">
                <p class="text-gray-500 text-sm">Attendance</p>
                <p class="text-2xl font-bold <?php echo $attendance_percentage >= 80 ? 'text-green-600' : 'text-yellow-600'; ?>">
                    <?php echo $attendance_percentage; ?>%
                </p>
            </div>
            <div class="bg-white rounded-xl p-4 shadow-sm">
                <p class="text-gray-500 text-sm">Avg Exam Score</p>
                <p class="text-2xl font-bold text-purple-600">
                    <?php 
                    $avg_score = 0;
                    $count = 0;
                    $exams->data_seek(0);
                    while($exam = $exams->fetch_assoc()) {
                        if ($exam['percentage'] !== null) {
                            $avg_score += $exam['percentage'];
                            $count++;
                        }
                    }
                    $exams->data_seek(0);
                    echo $count > 0 ? round($avg_score / $count, 1) . '%' : 'N/A';
                    ?>
                </p>
            </div>
        </div>

        <!-- Tabs -->
        <div class="bg-white rounded-xl shadow-sm overflow-hidden mb-6">
            <div class="border-b">
                <nav class="flex flex-wrap">
                    <button class="tab-btn active px-6 py-3 text-blue-600 border-b-2 border-blue-600 font-medium" data-tab="exams">
                        <i class="fas fa-chart-line mr-2"></i> Exams
                    </button>
                    <button class="tab-btn px-6 py-3 text-gray-600 hover:text-gray-800" data-tab="assignments">
                        <i class="fas fa-tasks mr-2"></i> Assignments
                    </button>
                    <button class="tab-btn px-6 py-3 text-gray-600 hover:text-gray-800" data-tab="attendance">
                        <i class="fas fa-calendar-check mr-2"></i> Attendance
                    </button>
                    <button class="tab-btn px-6 py-3 text-gray-600 hover:text-gray-800" data-tab="sessions">
                        <i class="fas fa-history mr-2"></i> Sessions
                    </button>
                </nav>
            </div>

            <!-- Tab Content -->
            <div class="p-6">
                <!-- Exams Tab -->
                <div id="tab-exams" class="tab-content">
                    <table class="w-full">
                        <thead class="bg-gray-50 border-b">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Exam</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Subject</th>
                                <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Score</th>
                                <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Percentage</th>
                                <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Grade</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Submitted</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            <?php if ($exams && $exams->num_rows > 0): ?>
                                <?php while($exam = $exams->fetch_assoc()): 
                                    $is_pass = ($exam['percentage'] ?? 0) >= 75;
                                ?>
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-4 py-3 font-medium"><?php echo htmlspecialchars($exam['title']); ?></td>
                                        <td class="px-4 py-3 text-gray-600"><?php echo htmlspecialchars($exam['subject_name']); ?></td>
                                        <td class="px-4 py-3 text-center"><?php echo $exam['total_score']; ?> / <?php echo $exam['total_marks']; ?></td>
                                        <td class="px-4 py-3 text-center font-bold <?php echo $is_pass ? 'text-green-600' : 'text-red-600'; ?>">
                                            <?php echo round($exam['percentage'] ?? 0, 1); ?>%
                                        </td>
                                        <td class="px-4 py-3 text-center">
                                            <span class="px-2 py-1 text-xs rounded-full <?php echo $is_pass ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'; ?>">
                                                <?php echo $exam['grade'] ?? 'F'; ?>
                                            </span>
                                        </td>
                                        <td class="px-4 py-3 text-sm text-gray-500"><?php echo date('M d, Y', strtotime($exam['submitted_at'])); ?></td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr><td colspan="6" class="px-4 py-8 text-center text-gray-500">No exam results available</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Assignments Tab -->
                <div id="tab-assignments" class="tab-content hidden">
                    <table class="w-full">
                        <thead class="bg-gray-50 border-b">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Assignment</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Subject</th>
                                <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Score</th>
                                <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Status</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Submitted</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            <?php if ($assignments && $assignments->num_rows > 0): ?>
                                <?php while($ass = $assignments->fetch_assoc()): ?>
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-4 py-3 font-medium"><?php echo htmlspecialchars($ass['title']); ?></td>
                                        <td class="px-4 py-3 text-gray-600"><?php echo htmlspecialchars($ass['subject_name']); ?></td>
                                        <td class="px-4 py-3 text-center">
                                            <?php if($ass['marks_obtained'] !== null): ?>
                                                <?php echo $ass['marks_obtained']; ?> / <?php echo $ass['max_marks']; ?>
                                            <?php else: ?>
                                                <span class="text-gray-400">Pending</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-4 py-3 text-center">
                                            <span class="px-2 py-1 text-xs rounded-full <?php echo $ass['status'] == 'Passed' ? 'bg-green-100 text-green-700' : ($ass['status'] == 'Pending' ? 'bg-yellow-100 text-yellow-700' : 'bg-red-100 text-red-700'); ?>">
                                                <?php echo $ass['status']; ?>
                                            </span>
                                        </td>
                                        <td class="px-4 py-3 text-sm text-gray-500"><?php echo $ass['submitted_at'] ? date('M d, Y', strtotime($ass['submitted_at'])) : 'Not submitted'; ?></td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr><td colspan="5" class="px-4 py-8 text-center text-gray-500">No assignments submitted</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Attendance Tab -->
                <div id="tab-attendance" class="tab-content hidden">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                        <div class="bg-green-50 rounded-lg p-4 text-center">
                            <p class="text-sm text-gray-600">Attendance Rate</p>
                            <p class="text-3xl font-bold <?php echo $attendance_percentage >= 80 ? 'text-green-600' : 'text-yellow-600'; ?>">
                                <?php echo $attendance_percentage; ?>%
                            </p>
                            <p class="text-xs text-gray-500"><?php echo $att_present; ?> present out of <?php echo $att_total; ?> days</p>
                        </div>
                        <div class="bg-gray-50 rounded-lg p-4 text-center">
                            <p class="text-sm text-gray-600">Recent Attendance</p>
                            <div class="flex flex-wrap gap-1 mt-2 justify-center">
                                <?php 
                                $attendance->data_seek(0);
                                $count = 0;
                                while($att = $attendance->fetch_assoc() && $count < 20): 
                                    $count++;
                                    $color = $att['status'] == 'present' ? 'bg-green-500' : ($att['status'] == 'absent' ? 'bg-red-500' : ($att['status'] == 'late' ? 'bg-yellow-500' : 'bg-blue-500'));
                                ?>
                                    <div class="w-6 h-6 rounded <?php echo $color; ?>" title="<?php echo date('M d', strtotime($att['date'])) . ': ' . ucfirst($att['status']); ?>"></div>
                                <?php endwhile; ?>
                            </div>
                        </div>
                    </div>
                    <table class="w-full">
                        <thead class="bg-gray-50 border-b">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            <?php 
                            $attendance->data_seek(0);
                            if ($attendance && $attendance->num_rows > 0): ?>
                                <?php while($att = $attendance->fetch_assoc()): ?>
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-4 py-3"><?php echo date('l, M d, Y', strtotime($att['date'])); ?></td>
                                        <td class="px-4 py-3">
                                            <span class="px-2 py-1 text-xs rounded-full <?php echo $att['status'] == 'present' ? 'bg-green-100 text-green-700' : ($att['status'] == 'absent' ? 'bg-red-100 text-red-700' : ($att['status'] == 'late' ? 'bg-yellow-100 text-yellow-700' : 'bg-blue-100 text-blue-700')); ?>">
                                                <?php echo ucfirst($att['status']); ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr><td colspan="2" class="px-4 py-8 text-center text-gray-500">No attendance records</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Sessions Tab -->
                <div id="tab-sessions" class="tab-content hidden">
                    <table class="w-full">
                        <thead class="bg-gray-50 border-b">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Started</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Ended</th>
                                <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Duration</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">IP Address</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            <?php if ($sessions && $sessions->num_rows > 0): ?>
                                <?php while($session = $sessions->fetch_assoc()): 
                                    $duration = $session['duration'] ?? 0;
                                    $hours = floor($duration / 60);
                                    $mins = $duration % 60;
                                ?>
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-4 py-3 text-sm"><?php echo date('M d, Y h:i A', strtotime($session['session_start'])); ?></td>
                                        <td class="px-4 py-3 text-sm">
                                            <?php echo $session['session_end'] ? date('M d, Y h:i A', strtotime($session['session_end'])) : 'Active'; ?>
                                        </td>
                                        <td class="px-4 py-3 text-center font-semibold">
                                            <?php echo ($hours > 0 ? $hours . 'h ' : '') . $mins . 'm'; ?>
                                        </td>
                                        <td class="px-4 py-3 text-sm text-gray-500"><?php echo $session['ip_address']; ?></td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr><td colspan="4" class="px-4 py-8 text-center text-gray-500">No session data available</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Activity Timeline -->
        <div class="bg-white rounded-xl shadow-sm overflow-hidden">
            <div class="px-6 py-4 border-b bg-gray-50">
                <h3 class="text-lg font-semibold">📋 Recent Activity Log</h3>
            </div>
            <div class="divide-y max-h-64 overflow-y-auto">
                <?php
                $activities = $conn->prepare("
                    SELECT action, entity_type, created_at 
                    FROM activity_logs 
                    WHERE user_id = ? 
                    ORDER BY created_at DESC LIMIT 20
                ");
                $activities->bind_param("i", $student_id);
                $activities->execute();
                $activities = $activities->get_result();
                ?>
                <?php if ($activities && $activities->num_rows > 0): ?>
                    <?php while($activity = $activities->fetch_assoc()): ?>
                        <div class="p-3 hover:bg-gray-50 flex items-center space-x-3">
                            <div class="w-8 h-8 bg-gray-100 rounded-full flex items-center justify-center">
                                <i class="fas fa-circle text-gray-400 text-xs"></i>
                            </div>
                            <div>
                                <p class="text-sm text-gray-700">
                                    <span class="font-medium"><?php echo ucfirst($activity['action']); ?></span>
                                    <?php if($activity['entity_type']): ?>
                                        <span class="text-gray-500">on <?php echo ucfirst($activity['entity_type']); ?></span>
                                    <?php endif; ?>
                                </p>
                                <p class="text-xs text-gray-400"><?php echo getTimeAgo($activity['created_at']); ?></p>
                            </div>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="p-8 text-center text-gray-500">No recent activity</div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
// Tab switching
document.querySelectorAll('.tab-btn').forEach(btn => {
    btn.addEventListener('click', function() {
        // Remove active from all tabs
        document.querySelectorAll('.tab-btn').forEach(b => {
            b.classList.remove('active', 'text-blue-600', 'border-b-2', 'border-blue-600');
            b.classList.add('text-gray-600');
        });
        
        // Add active to clicked tab
        this.classList.add('active', 'text-blue-600', 'border-b-2', 'border-blue-600');
        this.classList.remove('text-gray-600');
        
        // Hide all tab contents
        document.querySelectorAll('.tab-content').forEach(content => {
            content.classList.add('hidden');
        });
        
        // Show selected tab
        const tabId = this.dataset.tab;
        document.getElementById('tab-' + tabId).classList.remove('hidden');
    });
});

// Print function
function printReport() {
    window.print();
}
</script>

<style>
@media print {
    .ml-64, .sidebar, .navbar, .no-print {
        display: none !important;
    }
    .ml-64 {
        margin-left: 0 !important;
    }
    body {
        background: white;
    }
}
</style>

<?php include '../includes/footer.php'; ?>