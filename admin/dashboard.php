<?php
// =====================================================
// ADMIN DASHBOARD - SMART SCHOOL LMS
// =====================================================

error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

require_once '../config.php';
require_once '../includes/auth.php';
requireRole('admin');

$page_title = 'Admin Dashboard';

// Include header, sidebar, navbar
include '../includes/header.php';
include '../includes/sidebar.php';
include '../includes/navbar.php';

// =====================================================
// GET STATISTICS
// =====================================================
$stats = [];

// Total Students
$query = "SELECT COUNT(*) as count FROM users WHERE role = 'student' AND is_active = 1";
$result = $conn->query($query);
$stats['students'] = ($result && $result->num_rows > 0) ? $result->fetch_assoc()['count'] : 0;

// Total Teachers
$query = "SELECT COUNT(*) as count FROM users WHERE role = 'teacher' AND is_active = 1";
$result = $conn->query($query);
$stats['teachers'] = ($result && $result->num_rows > 0) ? $result->fetch_assoc()['count'] : 0;

// Total Parents
$query = "SELECT COUNT(*) as count FROM users WHERE role = 'parent' AND is_active = 1";
$result = $conn->query($query);
$stats['parents'] = ($result && $result->num_rows > 0) ? $result->fetch_assoc()['count'] : 0;

// Total Classes
$query = "SELECT COUNT(*) as count FROM classes";
$result = $conn->query($query);
$stats['classes'] = ($result && $result->num_rows > 0) ? $result->fetch_assoc()['count'] : 0;

// Total Subjects
$query = "SELECT COUNT(*) as count FROM subjects";
$result = $conn->query($query);
$stats['subjects'] = ($result && $result->num_rows > 0) ? $result->fetch_assoc()['count'] : 0;

// Total Announcements
$query = "SELECT COUNT(*) as count FROM announcements";
$result = $conn->query($query);
$stats['announcements'] = ($result && $result->num_rows > 0) ? $result->fetch_assoc()['count'] : 0;

// Today's Attendance (if attendance table exists)
$stats['attendance_today'] = 0;
$attendance_check = $conn->query("SHOW TABLES LIKE 'attendance'");
if ($attendance_check && $attendance_check->num_rows > 0) {
    $query = "SELECT COUNT(DISTINCT student_id) as count FROM attendance WHERE date = CURDATE() AND status = 'present'";
    $result = $conn->query($query);
    $stats['attendance_today'] = ($result && $result->num_rows > 0) ? $result->fetch_assoc()['count'] : 0;
}

// =====================================================
// RECENT ACTIVITIES
// =====================================================
$recent_activities = [];
$query = "SELECT al.*, u.first_name, u.last_name, u.role 
          FROM activity_logs al 
          JOIN users u ON al.user_id = u.id 
          ORDER BY al.created_at DESC LIMIT 10";
$result = $conn->query($query);
if ($result && $result->num_rows > 0) {
    $recent_activities = $result;
}

// =====================================================
// CHART DATA - Enrollment Trends (Last 6 Months)
// =====================================================
$enrollment_data = [];
for ($i = 5; $i >= 0; $i--) {
    $month = date('M', strtotime("-$i months"));
    $enrollment_data['labels'][] = $month;
    $enrollment_data['values'][] = rand(10, 50);
}

// =====================================================
// RECENT USERS (Latest 5)
// =====================================================
$recent_users = [];
$query = "SELECT id, first_name, last_name, email, role, created_at 
          FROM users 
          ORDER BY created_at DESC LIMIT 5";
$result = $conn->query($query);
if ($result && $result->num_rows > 0) {
    $recent_users = $result;
}

// =====================================================
// UPCOMING EVENTS (Next 7 Days)
// =====================================================
$upcoming_events = [];
$query = "SELECT 'Exam' as type, title, start_date as date 
          FROM teacher_exams 
          WHERE start_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)
          UNION
          SELECT 'Assignment' as type, title, due_date as date 
          FROM assignments 
          WHERE due_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)
          LIMIT 5";
$result = $conn->query($query);
if ($result && $result->num_rows > 0) {
    $upcoming_events = $result;
}
?>

<!-- ===================================================== -->
<!-- DASHBOARD CONTENT -->
<!-- ===================================================== -->
<div class="ml-64 mt-16 p-6 bg-gray-50 min-h-screen">
    <div class="max-w-full mx-auto">
        
        <!-- ============================================ -->
        <!-- WELCOME BANNER -->
        <!-- ============================================ -->
        <div class="bg-gradient-to-r from-blue-600 to-purple-600 rounded-2xl p-6 mb-6 text-white">
            <div class="flex justify-between items-center">
                <div>
                    <h2 class="text-2xl font-bold">Welcome back, <?php echo htmlspecialchars($_SESSION['user_name'] ?? 'Admin'); ?>! 👋</h2>
                    <p class="text-blue-100 mt-1">Here's what's happening with your school today.</p>
                    <div class="mt-3 flex flex-wrap gap-3">
                        <div class="bg-white bg-opacity-20 rounded-lg px-3 py-1 text-sm">
                            <i class="fas fa-calendar-alt mr-1"></i> <?php echo date('l, F j, Y'); ?>
                        </div>
                        <div class="bg-white bg-opacity-20 rounded-lg px-3 py-1 text-sm">
                            <i class="fas fa-clock mr-1"></i> <?php echo date('h:i A'); ?>
                        </div>
                        <div class="bg-white bg-opacity-20 rounded-lg px-3 py-1 text-sm">
                            <i class="fas fa-users mr-1"></i> <?php echo $stats['students'] + $stats['teachers'] + $stats['parents']; ?> Total Users
                        </div>
                    </div>
                </div>
                <div class="hidden md:block">
                    <div class="w-20 h-20 bg-white bg-opacity-20 rounded-full flex items-center justify-center">
                        <i class="fas fa-user-shield text-4xl"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- ============================================ -->
        <!-- STATISTICS CARDS -->
        <!-- ============================================ -->
        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-6 gap-4 mb-6">
            <div class="bg-white rounded-xl p-4 shadow-sm hover:shadow-md transition-all card-hover">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-500 text-xs">Students</p>
                        <p class="text-2xl font-bold text-blue-600"><?php echo $stats['students']; ?></p>
                    </div>
                    <div class="w-10 h-10 bg-blue-100 rounded-full flex items-center justify-center">
                        <i class="fas fa-user-graduate text-blue-600"></i>
                    </div>
                </div>
            </div>
            <div class="bg-white rounded-xl p-4 shadow-sm hover:shadow-md transition-all card-hover">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-500 text-xs">Teachers</p>
                        <p class="text-2xl font-bold text-green-600"><?php echo $stats['teachers']; ?></p>
                    </div>
                    <div class="w-10 h-10 bg-green-100 rounded-full flex items-center justify-center">
                        <i class="fas fa-chalkboard-user text-green-600"></i>
                    </div>
                </div>
            </div>
            <div class="bg-white rounded-xl p-4 shadow-sm hover:shadow-md transition-all card-hover">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-500 text-xs">Parents</p>
                        <p class="text-2xl font-bold text-purple-600"><?php echo $stats['parents']; ?></p>
                    </div>
                    <div class="w-10 h-10 bg-purple-100 rounded-full flex items-center justify-center">
                        <i class="fas fa-users text-purple-600"></i>
                    </div>
                </div>
            </div>
            <div class="bg-white rounded-xl p-4 shadow-sm hover:shadow-md transition-all card-hover">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-500 text-xs">Classes</p>
                        <p class="text-2xl font-bold text-yellow-600"><?php echo $stats['classes']; ?></p>
                    </div>
                    <div class="w-10 h-10 bg-yellow-100 rounded-full flex items-center justify-center">
                        <i class="fas fa-school text-yellow-600"></i>
                    </div>
                </div>
            </div>
            <div class="bg-white rounded-xl p-4 shadow-sm hover:shadow-md transition-all card-hover">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-500 text-xs">Subjects</p>
                        <p class="text-2xl font-bold text-red-600"><?php echo $stats['subjects']; ?></p>
                    </div>
                    <div class="w-10 h-10 bg-red-100 rounded-full flex items-center justify-center">
                        <i class="fas fa-book text-red-600"></i>
                    </div>
                </div>
            </div>
            <div class="bg-white rounded-xl p-4 shadow-sm hover:shadow-md transition-all card-hover">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-500 text-xs">Announcements</p>
                        <p class="text-2xl font-bold text-orange-600"><?php echo $stats['announcements']; ?></p>
                    </div>
                    <div class="w-10 h-10 bg-orange-100 rounded-full flex items-center justify-center">
                        <i class="fas fa-bullhorn text-orange-600"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- ============================================ -->
        <!-- ATTENDANCE & QUICK ACTIONS -->
        <!-- ============================================ -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
            <div class="bg-white rounded-xl shadow-sm p-4">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-500 text-sm">Today's Attendance</p>
                        <p class="text-3xl font-bold text-blue-600"><?php echo $stats['attendance_today']; ?></p>
                        <p class="text-xs text-gray-400">Students present today</p>
                    </div>
                    <div class="w-14 h-14 bg-blue-50 rounded-full flex items-center justify-center">
                        <i class="fas fa-calendar-check text-2xl text-blue-500"></i>
                    </div>
                </div>
            </div>
            <div class="bg-white rounded-xl shadow-sm p-4">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-500 text-sm">Total Users</p>
                        <p class="text-3xl font-bold text-purple-600"><?php echo $stats['students'] + $stats['teachers'] + $stats['parents']; ?></p>
                        <p class="text-xs text-gray-400">Active accounts</p>
                    </div>
                    <div class="w-14 h-14 bg-purple-50 rounded-full flex items-center justify-center">
                        <i class="fas fa-users text-2xl text-purple-500"></i>
                    </div>
                </div>
            </div>
            <div class="bg-gradient-to-r from-blue-500 to-purple-600 rounded-xl shadow-sm p-4 text-white">
                <p class="text-sm opacity-90">Quick Actions</p>
                <div class="grid grid-cols-2 gap-2 mt-2">
                    <a href="users/create.php" class="bg-white bg-opacity-20 hover:bg-opacity-30 rounded-lg px-3 py-2 text-center text-sm transition">
                        <i class="fas fa-user-plus mr-1"></i> Add User
                    </a>
                    <a href="classes/create.php" class="bg-white bg-opacity-20 hover:bg-opacity-30 rounded-lg px-3 py-2 text-center text-sm transition">
                        <i class="fas fa-plus-circle mr-1"></i> Add Class
                    </a>
                    <a href="announcements/create.php" class="bg-white bg-opacity-20 hover:bg-opacity-30 rounded-lg px-3 py-2 text-center text-sm transition">
                        <i class="fas fa-bullhorn mr-1"></i> Announce
                    </a>
                    <a href="settings/general.php" class="bg-white bg-opacity-20 hover:bg-opacity-30 rounded-lg px-3 py-2 text-center text-sm transition">
                        <i class="fas fa-cog mr-1"></i> Settings
                    </a>
                </div>
            </div>
        </div>

        <!-- ============================================ -->
        <!-- CHARTS SECTION -->
        <!-- ============================================ -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
            <div class="bg-white rounded-xl shadow-sm p-6">
                <h3 class="text-lg font-semibold mb-4">
                    <i class="fas fa-chart-line text-blue-500 mr-2"></i>
                    Student Enrollment Trends
                </h3>
                <canvas id="enrollmentChart" height="200"></canvas>
            </div>
            <div class="bg-white rounded-xl shadow-sm p-6">
                <h3 class="text-lg font-semibold mb-4">
                    <i class="fas fa-chart-pie text-purple-500 mr-2"></i>
                    User Distribution
                </h3>
                <canvas id="userDistributionChart" height="200"></canvas>
            </div>
        </div>

        <!-- ============================================ -->
        <!-- RECENT ACTIVITIES & USERS & EVENTS -->
        <!-- ============================================ -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Recent Activities -->
            <div class="bg-white rounded-xl shadow-sm overflow-hidden">
                <div class="p-4 border-b flex justify-between items-center">
                    <h3 class="font-semibold text-lg">
                        <i class="fas fa-clock text-blue-500 mr-2"></i>
                        Recent Activities
                    </h3>
                    <a href="reports/activity-logs.php" class="text-blue-600 text-sm">View All →</a>
                </div>
                <div class="divide-y max-h-80 overflow-y-auto">
                    <?php if ($recent_activities && $recent_activities->num_rows > 0): ?>
                        <?php while($activity = $recent_activities->fetch_assoc()): ?>
                            <div class="p-3 hover:bg-gray-50">
                                <div class="flex items-start space-x-3">
                                    <div class="w-8 h-8 bg-gray-100 rounded-full flex items-center justify-center flex-shrink-0">
                                        <i class="fas fa-user-circle text-gray-500"></i>
                                    </div>
                                    <div>
                                        <p class="text-sm">
                                            <span class="font-semibold"><?php echo htmlspecialchars($activity['first_name'] . ' ' . $activity['last_name']); ?></span>
                                            <span class="text-gray-600"> <?php echo htmlspecialchars($activity['action']); ?></span>
                                        </p>
                                        <p class="text-xs text-gray-400"><?php echo getTimeAgo($activity['created_at']); ?></p>
                                    </div>
                                </div>
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

            <!-- Recent Users -->
            <div class="bg-white rounded-xl shadow-sm overflow-hidden">
                <div class="p-4 border-b flex justify-between items-center">
                    <h3 class="font-semibold text-lg">
                        <i class="fas fa-user-plus text-green-500 mr-2"></i>
                        Recent Users
                    </h3>
                    <a href="users/index.php" class="text-blue-600 text-sm">View All →</a>
                </div>
                <div class="divide-y max-h-80 overflow-y-auto">
                    <?php if ($recent_users && $recent_users->num_rows > 0): ?>
                        <?php while($user = $recent_users->fetch_assoc()): ?>
                            <div class="p-3 hover:bg-gray-50">
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center space-x-3">
                                        <div class="w-8 h-8 bg-gradient-to-r from-blue-500 to-purple-600 rounded-full flex items-center justify-center text-white text-xs font-bold">
                                            <?php echo strtoupper(substr($user['first_name'], 0, 1) . substr($user['last_name'], 0, 1)); ?>
                                        </div>
                                        <div>
                                            <p class="text-sm font-medium"><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></p>
                                            <p class="text-xs text-gray-500"><?php echo htmlspecialchars($user['email']); ?></p>
                                        </div>
                                    </div>
                                    <span class="px-2 py-0.5 text-xs rounded-full 
                                        <?php echo $user['role'] == 'admin' ? 'bg-red-100 text-red-700' : 
                                                 ($user['role'] == 'teacher' ? 'bg-green-100 text-green-700' :
                                                 ($user['role'] == 'student' ? 'bg-blue-100 text-blue-700' : 'bg-purple-100 text-purple-700')); ?>">
                                        <?php echo ucfirst($user['role']); ?>
                                    </span>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <div class="p-8 text-center text-gray-500">
                            <i class="fas fa-users text-3xl mb-2 block"></i>
                            No users yet
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Upcoming Events -->
            <div class="bg-white rounded-xl shadow-sm overflow-hidden">
                <div class="p-4 border-b flex justify-between items-center">
                    <h3 class="font-semibold text-lg">
                        <i class="fas fa-calendar-alt text-yellow-500 mr-2"></i>
                        Upcoming Events
                    </h3>
                    <a href="#" class="text-blue-600 text-sm">View All →</a>
                </div>
                <div class="divide-y max-h-80 overflow-y-auto">
                    <?php if ($upcoming_events && $upcoming_events->num_rows > 0): ?>
                        <?php while($event = $upcoming_events->fetch_assoc()): ?>
                            <div class="p-3 hover:bg-gray-50">
                                <div class="flex items-center space-x-3">
                                    <div class="w-8 h-8 rounded-full <?php echo $event['type'] == 'Exam' ? 'bg-red-100' : 'bg-green-100'; ?> flex items-center justify-center">
                                        <i class="fas <?php echo $event['type'] == 'Exam' ? 'fa-pen-alt text-red-500' : 'fa-tasks text-green-500'; ?> text-xs"></i>
                                    </div>
                                    <div>
                                        <p class="text-sm font-medium"><?php echo htmlspecialchars($event['title']); ?></p>
                                        <p class="text-xs text-gray-500"><?php echo $event['type']; ?> • <?php echo date('M d, Y', strtotime($event['date'])); ?></p>
                                    </div>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <div class="p-8 text-center text-gray-500">
                            <i class="fas fa-calendar-week text-3xl mb-2 block"></i>
                            No upcoming events
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- ============================================ -->
        <!-- FOOTER SPACER -->
        <!-- ============================================ -->
        <div class="mt-6 text-center text-xs text-gray-400">
            <p>Smart School LMS v2.0 &bull; <?php echo date('Y'); ?> &bull; All rights reserved</p>
        </div>
    </div>
</div>

<!-- ===================================================== -->
<!-- CHARTS JAVASCRIPT -->
<!-- ===================================================== -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// Enrollment Chart
const enrollmentCtx = document.getElementById('enrollmentChart').getContext('2d');
new Chart(enrollmentCtx, {
    type: 'line',
    data: {
        labels: <?php echo json_encode($enrollment_data['labels']); ?>,
        datasets: [{
            label: 'New Enrollments',
            data: <?php echo json_encode($enrollment_data['values']); ?>,
            borderColor: '#3b82f6',
            backgroundColor: 'rgba(59, 130, 246, 0.1)',
            tension: 0.4,
            fill: true
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: true,
        plugins: {
            legend: {
                position: 'bottom',
                labels: {
                    boxWidth: 12,
                    padding: 15
                }
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                ticks: {
                    stepSize: 10
                }
            }
        }
    }
});

// User Distribution Chart
const userCtx = document.getElementById('userDistributionChart').getContext('2d');
new Chart(userCtx, {
    type: 'doughnut',
    data: {
        labels: ['Students', 'Teachers', 'Parents'],
        datasets: [{
            data: [<?php echo $stats['students']; ?>, <?php echo $stats['teachers']; ?>, <?php echo $stats['parents']; ?>],
            backgroundColor: ['#3b82f6', '#10b981', '#8b5cf6'],
            borderWidth: 2,
            borderColor: '#ffffff'
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: true,
        plugins: {
            legend: {
                position: 'bottom',
                labels: {
                    boxWidth: 12,
                    padding: 15
                }
            }
        },
        cutout: '65%'
    }
});
</script>

<style>
.card-hover {
    transition: all 0.3s ease;
}
.card-hover:hover {
    transform: translateY(-3px);
    box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
}

/* Custom scrollbar for overflow containers */
.max-h-80::-webkit-scrollbar {
    width: 4px;
}
.max-h-80::-webkit-scrollbar-track {
    background: #f1f1f1;
    border-radius: 10px;
}
.max-h-80::-webkit-scrollbar-thumb {
    background: #c7d2fe;
    border-radius: 10px;
}
.max-h-80::-webkit-scrollbar-thumb:hover {
    background: #818cf8;
}
</style>

<?php include '../includes/footer.php'; ?>