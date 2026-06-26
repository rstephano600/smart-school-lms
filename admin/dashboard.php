<?php
require_once '../config.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
requireRole('admin');

$page_title = 'Admin Dashboard';
include '../includes/header.php';
include '../includes/sidebar.php';
include '../includes/navbar.php';

// Get statistics with error handling
$stats = [];

// Total students
$query = "SELECT COUNT(*) as count FROM users WHERE role = 'student' AND is_active = 1";
$result = $conn->query($query);
$stats['students'] = ($result && $result->num_rows > 0) ? $result->fetch_assoc()['count'] : 0;

// Total teachers
$query = "SELECT COUNT(*) as count FROM users WHERE role = 'teacher' AND is_active = 1";
$result = $conn->query($query);
$stats['teachers'] = ($result && $result->num_rows > 0) ? $result->fetch_assoc()['count'] : 0;

// Total parents
$query = "SELECT COUNT(*) as count FROM users WHERE role = 'parent' AND is_active = 1";
$result = $conn->query($query);
$stats['parents'] = ($result && $result->num_rows > 0) ? $result->fetch_assoc()['count'] : 0;

// Total classes
$query = "SELECT COUNT(*) as count FROM classes";
$result = $conn->query($query);
$stats['classes'] = ($result && $result->num_rows > 0) ? $result->fetch_assoc()['count'] : 0;

// Total subjects
$query = "SELECT COUNT(*) as count FROM subjects";
$result = $conn->query($query);
$stats['subjects'] = ($result && $result->num_rows > 0) ? $result->fetch_assoc()['count'] : 0;

// Recent activities
$recent_activities = [];
$query = "SELECT al.*, u.first_name, u.last_name, u.role 
          FROM activity_logs al 
          JOIN users u ON al.user_id = u.id 
          ORDER BY al.created_at DESC LIMIT 5";
$result = $conn->query($query);
if ($result && $result->num_rows > 0) {
    $recent_activities = $result;
}

// Get chart data for enrollment (last 6 months)
$enrollment_data = [];
for ($i = 5; $i >= 0; $i--) {
    $month = date('M', strtotime("-$i months"));
    $enrollment_data['labels'][] = $month;
    $enrollment_data['values'][] = rand(10, 50); // Sample data
}
?>

<div class="ml-64 mt-16 p-6">
    <!-- Welcome Section -->
    <div class="bg-gradient-to-r from-blue-500 to-purple-600 rounded-2xl p-6 mb-6 text-white">
        <h2 class="text-2xl font-bold">Welcome back, <?php echo htmlspecialchars($_SESSION['user_name'] ?? 'Admin'); ?>!</h2>
        <p class="mt-2">Here's what's happening with your school today.</p>
    </div>
    
    <!-- Stats Cards -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-6 mb-6">
        <div class="bg-white rounded-xl p-6 shadow-sm hover:shadow-lg transition-all duration-300 card-hover">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-gray-500 text-sm">Total Students</p>
                    <p class="text-3xl font-bold text-gray-800"><?php echo $stats['students']; ?></p>
                </div>
                <div class="w-12 h-12 bg-blue-100 rounded-full flex items-center justify-center">
                    <i class="fas fa-user-graduate text-blue-600 text-xl"></i>
                </div>
            </div>
        </div>
        
        <div class="bg-white rounded-xl p-6 shadow-sm hover:shadow-lg transition-all duration-300 card-hover">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-gray-500 text-sm">Total Teachers</p>
                    <p class="text-3xl font-bold text-gray-800"><?php echo $stats['teachers']; ?></p>
                </div>
                <div class="w-12 h-12 bg-green-100 rounded-full flex items-center justify-center">
                    <i class="fas fa-chalkboard-user text-green-600 text-xl"></i>
                </div>
            </div>
        </div>
        
        <div class="bg-white rounded-xl p-6 shadow-sm hover:shadow-lg transition-all duration-300 card-hover">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-gray-500 text-sm">Total Parents</p>
                    <p class="text-3xl font-bold text-gray-800"><?php echo $stats['parents']; ?></p>
                </div>
                <div class="w-12 h-12 bg-purple-100 rounded-full flex items-center justify-center">
                    <i class="fas fa-users text-purple-600 text-xl"></i>
                </div>
            </div>
        </div>
        
        <div class="bg-white rounded-xl p-6 shadow-sm hover:shadow-lg transition-all duration-300 card-hover">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-gray-500 text-sm">Total Classes</p>
                    <p class="text-3xl font-bold text-gray-800"><?php echo $stats['classes']; ?></p>
                </div>
                <div class="w-12 h-12 bg-yellow-100 rounded-full flex items-center justify-center">
                    <i class="fas fa-school text-yellow-600 text-xl"></i>
                </div>
            </div>
        </div>
        
        <div class="bg-white rounded-xl p-6 shadow-sm hover:shadow-lg transition-all duration-300 card-hover">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-gray-500 text-sm">Total Subjects</p>
                    <p class="text-3xl font-bold text-gray-800"><?php echo $stats['subjects']; ?></p>
                </div>
                <div class="w-12 h-12 bg-red-100 rounded-full flex items-center justify-center">
                    <i class="fas fa-book text-red-600 text-xl"></i>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Charts Section -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
        <div class="bg-white rounded-xl p-6 shadow-sm">
            <h3 class="text-lg font-semibold mb-4">Student Enrollment Trends</h3>
            <canvas id="enrollmentChart" height="200"></canvas>
        </div>
        
        <div class="bg-white rounded-xl p-6 shadow-sm">
            <h3 class="text-lg font-semibold mb-4">User Distribution</h3>
            <canvas id="userDistributionChart" height="200"></canvas>
        </div>
    </div>
    
    <!-- Recent Activities -->
    <div class="bg-white rounded-xl shadow-sm">
        <div class="p-6 border-b">
            <h3 class="text-lg font-semibold">Recent Activities</h3>
        </div>
        <div class="p-6">
            <div class="space-y-4">
                <?php if ($recent_activities && $recent_activities->num_rows > 0): ?>
                    <?php while($activity = $recent_activities->fetch_assoc()): ?>
                    <div class="flex items-center space-x-3 p-3 hover:bg-gray-50 rounded-lg transition-all duration-200">
                        <div class="w-10 h-10 bg-gray-100 rounded-full flex items-center justify-center">
                            <i class="fas fa-user-circle text-gray-600"></i>
                        </div>
                        <div class="flex-1">
                            <p class="text-sm">
                                <span class="font-semibold"><?php echo htmlspecialchars($activity['first_name'] . ' ' . $activity['last_name']); ?></span>
                                <span class="text-gray-600"> <?php echo htmlspecialchars($activity['action']); ?></span>
                            </p>
                            <p class="text-xs text-gray-500"><?php echo getTimeAgo($activity['created_at']); ?></p>
                        </div>
                    </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <p class="text-gray-500 text-center py-4">No recent activities yet.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

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
                position: 'bottom'
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
            backgroundColor: ['#3b82f6', '#10b981', '#8b5cf6']
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

<style>
.card-hover {
    transition: all 0.3s ease;
}
.card-hover:hover {
    transform: translateY(-5px);
}
</style>

<?php include '../includes/footer.php'; ?>