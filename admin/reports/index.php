<?php
require_once '../../config.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';
requireRole('admin');

$page_title = 'Reports & Analytics';
include '../../includes/header.php';
include '../../includes/sidebar.php';
include '../../includes/navbar.php';

// Get statistics for dashboard
$stats = [];

// Total students
$query = "SELECT COUNT(*) as count FROM users WHERE role = 'student' AND is_active = 1";
$result = $conn->query($query);
$stats['total_students'] = $result->fetch_assoc()['count'];

// Total teachers
$query = "SELECT COUNT(*) as count FROM users WHERE role = 'teacher' AND is_active = 1";
$result = $conn->query($query);
$stats['total_teachers'] = $result->fetch_assoc()['count'];

// Total classes
$query = "SELECT COUNT(*) as count FROM classes";
$result = $conn->query($query);
$stats['total_classes'] = $result->fetch_assoc()['count'];

// Total subjects
$query = "SELECT COUNT(*) as count FROM subjects";
$result = $conn->query($query);
$stats['total_subjects'] = $result->fetch_assoc()['count'];

// Today's attendance
$query = "SELECT COUNT(DISTINCT student_id) as count FROM attendance WHERE date = CURDATE() AND status = 'present'";
$result = $conn->query($query);
$stats['attendance_today'] = $result->fetch_assoc()['count'];

// Average attendance this month
$query = "SELECT COUNT(*) as total_present, 
          (SELECT COUNT(DISTINCT student_id) FROM attendance WHERE MONTH(date) = MONTH(CURDATE())) as total_students
          FROM attendance WHERE MONTH(date) = MONTH(CURDATE()) AND status = 'present'";
$result = $conn->query($query);
$att_data = $result->fetch_assoc();
$stats['avg_attendance'] = $att_data['total_students'] > 0 ? round(($att_data['total_present'] / $att_data['total_students']) * 100) : 0;

// Get recent activities count
$query = "SELECT COUNT(*) as count FROM activity_logs WHERE DATE(created_at) = CURDATE()";
$result = $conn->query($query);
$stats['today_activities'] = $result->fetch_assoc()['count'];
?>

<div class="ml-64 mt-16 p-6">
    <!-- Page Header -->
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-800">Reports & Analytics</h1>
        <p class="text-gray-500 mt-1">View comprehensive reports and analytics about your school</p>
    </div>

    <!-- Stats Cards -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-6">
        <div class="bg-white rounded-xl p-6 shadow-sm hover:shadow-lg transition-all duration-300">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-gray-500 text-sm">Total Students</p>
                    <p class="text-3xl font-bold text-gray-800"><?php echo $stats['total_students']; ?></p>
                </div>
                <div class="w-12 h-12 bg-blue-100 rounded-full flex items-center justify-center">
                    <i class="fas fa-user-graduate text-blue-600 text-xl"></i>
                </div>
            </div>
        </div>
        
        <div class="bg-white rounded-xl p-6 shadow-sm hover:shadow-lg transition-all duration-300">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-gray-500 text-sm">Total Teachers</p>
                    <p class="text-3xl font-bold text-gray-800"><?php echo $stats['total_teachers']; ?></p>
                </div>
                <div class="w-12 h-12 bg-green-100 rounded-full flex items-center justify-center">
                    <i class="fas fa-chalkboard-user text-green-600 text-xl"></i>
                </div>
            </div>
        </div>
        
        <div class="bg-white rounded-xl p-6 shadow-sm hover:shadow-lg transition-all duration-300">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-gray-500 text-sm">Avg Attendance</p>
                    <p class="text-3xl font-bold text-gray-800"><?php echo $stats['avg_attendance']; ?>%</p>
                </div>
                <div class="w-12 h-12 bg-yellow-100 rounded-full flex items-center justify-center">
                    <i class="fas fa-calendar-check text-yellow-600 text-xl"></i>
                </div>
            </div>
        </div>
        
        <div class="bg-white rounded-xl p-6 shadow-sm hover:shadow-lg transition-all duration-300">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-gray-500 text-sm">Today's Activities</p>
                    <p class="text-3xl font-bold text-gray-800"><?php echo $stats['today_activities']; ?></p>
                </div>
                <div class="w-12 h-12 bg-purple-100 rounded-full flex items-center justify-center">
                    <i class="fas fa-history text-purple-600 text-xl"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Report Cards Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 mb-6">
        <!-- Attendance Report Card -->
        <div class="bg-white rounded-xl shadow-sm overflow-hidden hover:shadow-lg transition-all duration-300">
            <div class="bg-gradient-to-r from-blue-500 to-blue-600 p-4">
                <div class="flex items-center justify-between">
                    <div>
                        <h3 class="text-white font-semibold text-lg">Attendance Report</h3>
                        <p class="text-blue-100 text-sm">Track student attendance</p>
                    </div>
                    <i class="fas fa-calendar-check text-white text-3xl opacity-50"></i>
                </div>
            </div>
            <div class="p-4">
                <p class="text-gray-600 text-sm mb-4">View attendance statistics by class, date range, and generate detailed reports.</p>
                <div class="flex space-x-2">
                    <a href="attendance.php" class="flex-1 text-center bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition">
                        <i class="fas fa-chart-line mr-1"></i> View Report
                    </a>
                    <a href="export-attendance.php" class="text-blue-600 border border-blue-600 px-4 py-2 rounded-lg hover:bg-blue-50 transition">
                        <i class="fas fa-download"></i>
                    </a>
                </div>
            </div>
        </div>

        <!-- Performance Report Card -->
        <div class="bg-white rounded-xl shadow-sm overflow-hidden hover:shadow-lg transition-all duration-300">
            <div class="bg-gradient-to-r from-green-500 to-green-600 p-4">
                <div class="flex items-center justify-between">
                    <div>
                        <h3 class="text-white font-semibold text-lg">Performance Report</h3>
                        <p class="text-green-100 text-sm">Academic performance</p>
                    </div>
                    <i class="fas fa-chart-line text-white text-3xl opacity-50"></i>
                </div>
            </div>
            <div class="p-4">
                <p class="text-gray-600 text-sm mb-4">Analyze student performance, subject averages, and class rankings.</p>
                <div class="flex space-x-2">
                    <a href="performance.php" class="flex-1 text-center bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700 transition">
                        <i class="fas fa-chart-bar mr-1"></i> View Report
                    </a>
                    <a href="export-performance.php" class="text-green-600 border border-green-600 px-4 py-2 rounded-lg hover:bg-green-50 transition">
                        <i class="fas fa-download"></i>
                    </a>
                </div>
            </div>
        </div>

        <!-- Activity Logs Card -->
        <div class="bg-white rounded-xl shadow-sm overflow-hidden hover:shadow-lg transition-all duration-300">
            <div class="bg-gradient-to-r from-purple-500 to-purple-600 p-4">
                <div class="flex items-center justify-between">
                    <div>
                        <h3 class="text-white font-semibold text-lg">Activity Logs</h3>
                        <p class="text-purple-100 text-sm">System activities</p>
                    </div>
                    <i class="fas fa-history text-white text-3xl opacity-50"></i>
                </div>
            </div>
            <div class="p-4">
                <p class="text-gray-600 text-sm mb-4">Monitor all user activities, logins, and system changes.</p>
                <div class="flex space-x-2">
                    <a href="activity-logs.php" class="flex-1 text-center bg-purple-600 text-white px-4 py-2 rounded-lg hover:bg-purple-700 transition">
                        <i class="fas fa-list mr-1"></i> View Logs
                    </a>
                    <a href="export-logs.php" class="text-purple-600 border border-purple-600 px-4 py-2 rounded-lg hover:bg-purple-50 transition">
                        <i class="fas fa-download"></i>
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Charts Section -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Monthly Attendance Chart -->
        <div class="bg-white rounded-xl shadow-sm p-6">
            <h3 class="text-lg font-semibold mb-4">Monthly Attendance Trend</h3>
            <canvas id="attendanceTrendChart" height="250"></canvas>
        </div>

        <!-- Performance Distribution Chart -->
        <div class="bg-white rounded-xl shadow-sm p-6">
            <h3 class="text-lg font-semibold mb-4">Student Performance Distribution</h3>
            <canvas id="performanceChart" height="250"></canvas>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// Monthly Attendance Trend
const attendanceCtx = document.getElementById('attendanceTrendChart').getContext('2d');
new Chart(attendanceCtx, {
    type: 'line',
    data: {
        labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
        datasets: [{
            label: 'Attendance Rate (%)',
            data: [85, 87, 88, 86, 89, 90, 88, 87, 89, 91, 92, 90],
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
                position: 'bottom'
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                max: 100,
                ticks: {
                    callback: function(value) {
                        return value + '%';
                    }
                }
            }
        }
    }
});

// Performance Distribution
const performanceCtx = document.getElementById('performanceChart').getContext('2d');
new Chart(performanceCtx, {
    type: 'doughnut',
    data: {
        labels: ['Excellent (80-100%)', 'Good (60-79%)', 'Average (40-59%)', 'Poor (Below 40%)'],
        datasets: [{
            data: [25, 40, 25, 10],
            backgroundColor: ['#10b981', '#3b82f6', '#f59e0b', '#ef4444']
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: true,
        plugins: {
            legend: {
                position: 'bottom'
            }
        }
    }
});
</script>

<?php include '../../includes/footer.php'; ?>