<?php
require_once '../../config.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';
requireRole('student');

$page_title = 'My Attendance';
include '../../includes/header.php';
include '../../includes/sidebar.php';
include '../../includes/navbar.php';

// Get student ID
$student_query = $conn->prepare("SELECT id FROM students WHERE user_id = ?");
$student_query->bind_param("i", $_SESSION['user_id']);
$student_query->execute();
$student = $student_query->get_result()->fetch_assoc();
$student_id = $student['id'];

// Get monthly attendance data
$months = [];
$attendance_data = [];

for ($i = 5; $i >= 0; $i--) {
    $month_date = date('Y-m-01', strtotime("-$i months"));
    $month_name = date('M Y', strtotime($month_date));
    $months[] = $month_name;
    
    $att_query = $conn->prepare("
        SELECT 
            COUNT(CASE WHEN status = 'present' THEN 1 END) as present,
            COUNT(*) as total
        FROM attendance
        WHERE student_id = ? AND DATE_FORMAT(date, '%Y-%m') = DATE_FORMAT(?, '%Y-%m')
    ");
    $att_query->bind_param("is", $student_id, $month_date);
    $att_query->execute();
    $att = $att_query->get_result()->fetch_assoc();
    $percentage = $att['total'] > 0 ? round(($att['present'] / $att['total']) * 100) : 0;
    $attendance_data[] = $percentage;
}

// Get recent attendance records
$recent_attendance = $conn->prepare("
    SELECT date, status, remark
    FROM attendance
    WHERE student_id = ?
    ORDER BY date DESC LIMIT 30
");
$recent_attendance->bind_param("i", $student_id);
$recent_attendance->execute();
$recent_attendance = $recent_attendance->get_result();

// Calculate overall attendance
$overall_query = $conn->prepare("
    SELECT 
        COUNT(CASE WHEN status = 'present' THEN 1 END) as present,
        COUNT(*) as total
    FROM attendance
    WHERE student_id = ?
");
$overall_query->bind_param("i", $student_id);
$overall_query->execute();
$overall = $overall_query->get_result()->fetch_assoc();
$overall_percentage = $overall['total'] > 0 ? round(($overall['present'] / $overall['total']) * 100) : 0;
?>

<div class="ml-64 mt-16 p-6">
    <div class="max-w-full mx-auto">
        <div class="mb-6">
            <h1 class="text-2xl font-bold text-gray-800">My Attendance</h1>
            <p class="text-gray-500 mt-1">Track your attendance record</p>
        </div>

        <!-- Overall Stats -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
            <div class="bg-white rounded-xl p-4 shadow-sm">
                <p class="text-gray-500 text-sm">Overall Attendance</p>
                <p class="text-3xl font-bold <?php echo $overall_percentage >= 80 ? 'text-green-600' : ($overall_percentage >= 60 ? 'text-yellow-600' : 'text-red-600'); ?>">
                    <?php echo $overall_percentage; ?>%
                </p>
                <div class="mt-2 w-full bg-gray-200 rounded-full h-2">
                    <div class="bg-blue-600 h-2 rounded-full" style="width: <?php echo $overall_percentage; ?>%"></div>
                </div>
                <p class="text-xs text-gray-500 mt-2">Present: <?php echo $overall['present']; ?> / Total: <?php echo $overall['total']; ?> days</p>
            </div>
            <div class="bg-white rounded-xl p-4 shadow-sm">
                <p class="text-gray-500 text-sm">This Month</p>
                <p class="text-3xl font-bold text-blue-600"><?php echo end($attendance_data); ?>%</p>
                <p class="text-xs text-gray-500 mt-2">Last 30 days performance</p>
            </div>
            <div class="bg-white rounded-xl p-4 shadow-sm">
                <p class="text-gray-500 text-sm">Attendance Status</p>
                <p class="text-xl font-semibold <?php echo $overall_percentage >= 80 ? 'text-green-600' : 'text-yellow-600'; ?>">
                    <?php echo $overall_percentage >= 80 ? 'Excellent' : ($overall_percentage >= 60 ? 'Good' : 'Needs Improvement'); ?>
                </p>
            </div>
        </div>

        <!-- Attendance Chart -->
        <div class="bg-white rounded-xl shadow-sm p-6 mb-6">
            <h3 class="text-lg font-semibold mb-4">Monthly Attendance Trend</h3>
            <canvas id="attendanceChart" height="100"></canvas>
        </div>

        <!-- Recent Attendance Records -->
        <div class="bg-white rounded-xl shadow-sm overflow-hidden">
            <div class="px-6 py-4 border-b bg-gray-50">
                <h3 class="text-lg font-semibold">Recent Attendance Records</h3>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-gray-50 border-b">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Remark</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <?php while($record = $recent_attendance->fetch_assoc()): ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4"><?php echo date('l, M d, Y', strtotime($record['date'])); ?></td>
                                <td class="px-6 py-4">
                                    <span class="px-2 py-1 text-xs rounded-full 
                                        <?php echo $record['status'] == 'present' ? 'bg-green-100 text-green-700' : 
                                                 ($record['status'] == 'absent' ? 'bg-red-100 text-red-700' :
                                                 ($record['status'] == 'late' ? 'bg-yellow-100 text-yellow-700' : 'bg-blue-100 text-blue-700')); ?>">
                                        <?php echo ucfirst($record['status']); ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-gray-500"><?php echo $record['remark'] ?: '-'; ?></td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
const ctx = document.getElementById('attendanceChart').getContext('2d');
new Chart(ctx, {
    type: 'line',
    data: {
        labels: <?php echo json_encode($months); ?>,
        datasets: [{
            label: 'Attendance Percentage',
            data: <?php echo json_encode($attendance_data); ?>,
            borderColor: '#3b82f6',
            backgroundColor: 'rgba(59, 130, 246, 0.1)',
            tension: 0.4,
            fill: true
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: true,
        scales: {
            y: {
                beginAtZero: true,
                max: 100,
                ticks: { callback: function(value) { return value + '%'; } }
            }
        },
        plugins: { legend: { position: 'bottom' } }
    }
});
</script>

<?php include '../../includes/footer.php'; ?>