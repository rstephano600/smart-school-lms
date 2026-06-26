<?php
require_once '../../config.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';
requireRole('admin');

$page_title = 'Attendance Report';
include '../../includes/header.php';
include '../../includes/sidebar.php';
include '../../includes/navbar.php';

// Get filter parameters
$class_id = $_GET['class_id'] ?? '';
$date_from = $_GET['date_from'] ?? date('Y-m-01');
$date_to = $_GET['date_to'] ?? date('Y-m-d');

// Get classes for filter
$classes = $conn->query("SELECT id, name FROM classes ORDER BY name");

// Build query
$query = "SELECT 
            s.id as student_id,
            CONCAT(u.first_name, ' ', u.last_name) as student_name,
            s.admission_number,
            c.name as class_name,
            COUNT(CASE WHEN a.status = 'present' THEN 1 END) as present_days,
            COUNT(CASE WHEN a.status = 'absent' THEN 1 END) as absent_days,
            COUNT(CASE WHEN a.status = 'late' THEN 1 END) as late_days,
            COUNT(CASE WHEN a.status = 'excused' THEN 1 END) as excused_days,
            COUNT(a.id) as total_days,
            ROUND((COUNT(CASE WHEN a.status = 'present' THEN 1 END) / COUNT(a.id)) * 100, 2) as attendance_percentage
          FROM students s
          JOIN users u ON s.user_id = u.id
          JOIN classes c ON s.class_id = c.id
          LEFT JOIN attendance a ON s.id = a.student_id AND a.date BETWEEN '$date_from' AND '$date_to'
          WHERE u.is_active = 1";

if (!empty($class_id)) {
    $query .= " AND s.class_id = $class_id";
}

$query .= " GROUP BY s.id ORDER BY attendance_percentage DESC";
$result = $conn->query($query);
$attendance_data = $result;
?>

<div class="ml-64 mt-16 p-6">
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-800">Attendance Report</h1>
        <p class="text-gray-500 mt-1">View detailed attendance statistics</p>
    </div>

    <!-- Filters -->
    <div class="bg-white rounded-xl shadow-sm p-4 mb-6">
        <form method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Class</label>
                <select name="class_id" class="w-full border rounded-lg px-3 py-2">
                    <option value="">All Classes</option>
                    <?php while($class = $classes->fetch_assoc()): ?>
                        <option value="<?php echo $class['id']; ?>" <?php echo $class_id == $class['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($class['name']); ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Date From</label>
                <input type="date" name="date_from" value="<?php echo $date_from; ?>" 
                       class="w-full border rounded-lg px-3 py-2">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Date To</label>
                <input type="date" name="date_to" value="<?php echo $date_to; ?>" 
                       class="w-full border rounded-lg px-3 py-2">
            </div>
            <div class="flex items-end">
                <button type="submit" class="w-full bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700">
                    <i class="fas fa-search mr-2"></i> Generate Report
                </button>
            </div>
        </form>
    </div>

    <!-- Summary Cards -->
    <?php
    // Calculate summary
    $total_students = 0;
    $avg_attendance = 0;
    $above_75 = 0;
    $below_75 = 0;
    
    if ($attendance_data && $attendance_data->num_rows > 0) {
        $attendance_data->data_seek(0);
        while($row = $attendance_data->fetch_assoc()) {
            $total_students++;
            $avg_attendance += $row['attendance_percentage'];
            if($row['attendance_percentage'] >= 75) {
                $above_75++;
            } else {
                $below_75++;
            }
        }
        $avg_attendance = $total_students > 0 ? round($avg_attendance / $total_students, 2) : 0;
        $attendance_data->data_seek(0);
    }
    ?>

    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
        <div class="bg-white rounded-xl p-4 shadow-sm">
            <p class="text-gray-500 text-sm">Total Students</p>
            <p class="text-2xl font-bold"><?php echo $total_students; ?></p>
        </div>
        <div class="bg-white rounded-xl p-4 shadow-sm">
            <p class="text-gray-500 text-sm">Average Attendance</p>
            <p class="text-2xl font-bold <?php echo $avg_attendance >= 75 ? 'text-green-600' : 'text-red-600'; ?>">
                <?php echo $avg_attendance; ?>%
            </p>
        </div>
        <div class="bg-white rounded-xl p-4 shadow-sm">
            <p class="text-gray-500 text-sm">Above 75%</p>
            <p class="text-2xl font-bold text-green-600"><?php echo $above_75; ?></p>
        </div>
        <div class="bg-white rounded-xl p-4 shadow-sm">
            <p class="text-gray-500 text-sm">Below 75%</p>
            <p class="text-2xl font-bold text-red-600"><?php echo $below_75; ?></p>
        </div>
    </div>

    <!-- Attendance Chart -->
    <div class="bg-white rounded-xl shadow-sm p-6 mb-6">
        <h3 class="text-lg font-semibold mb-4">Attendance Overview</h3>
        <canvas id="attendanceOverviewChart" height="100"></canvas>
    </div>

    <!-- Attendance Table -->
    <div class="bg-white rounded-xl shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-gray-50 border-b">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Admission No</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Student Name</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Class</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Present</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Absent</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Late</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Attendance %</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    <?php if ($attendance_data && $attendance_data->num_rows > 0): ?>
                        <?php while($row = $attendance_data->fetch_assoc()): ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4"><?php echo $row['admission_number']; ?></td>
                                <td class="px-6 py-4 font-medium"><?php echo htmlspecialchars($row['student_name']); ?></td>
                                <td class="px-6 py-4"><?php echo $row['class_name']; ?></td>
                                <td class="px-6 py-4 text-green-600"><?php echo $row['present_days']; ?></td>
                                <td class="px-6 py-4 text-red-600"><?php echo $row['absent_days']; ?></td>
                                <td class="px-6 py-4 text-yellow-600"><?php echo $row['late_days']; ?></td>
                                <td class="px-6 py-4 font-semibold <?php echo $row['attendance_percentage'] >= 75 ? 'text-green-600' : 'text-red-600'; ?>">
                                    <?php echo $row['attendance_percentage']; ?>%
                                </td>
                                <td class="px-6 py-4">
                                    <span class="px-2 py-1 text-xs rounded-full <?php echo $row['attendance_percentage'] >= 75 ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'; ?>">
                                        <?php echo $row['attendance_percentage'] >= 75 ? 'Good Standing' : 'Needs Improvement'; ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="8" class="px-6 py-12 text-center text-gray-500">
                                <i class="fas fa-chart-line text-4xl mb-2 block"></i>
                                No attendance data found for selected period
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// Attendance Overview Chart
const ctx = document.getElementById('attendanceOverviewChart').getContext('2d');
new Chart(ctx, {
    type: 'bar',
    data: {
        labels: ['Present', 'Absent', 'Late', 'Excused'],
        datasets: [{
            label: 'Total Days',
            data: [
                <?php 
                $total_present = 0;
                $total_absent = 0;
                $total_late = 0;
                $total_excused = 0;
                if ($attendance_data && $attendance_data->num_rows > 0) {
                    $attendance_data->data_seek(0);
                    while($row = $attendance_data->fetch_assoc()) {
                        $total_present += $row['present_days'];
                        $total_absent += $row['absent_days'];
                        $total_late += $row['late_days'];
                        $total_excused += $row['excused_days'];
                    }
                    $attendance_data->data_seek(0);
                }
                echo $total_present . ', ' . $total_absent . ', ' . $total_late . ', ' . $total_excused;
                ?>
            ],
            backgroundColor: ['#10b981', '#ef4444', '#f59e0b', '#6b7280']
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