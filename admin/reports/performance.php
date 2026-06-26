<?php
require_once '../../config.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';
requireRole('admin');

$page_title = 'Performance Report';
include '../../includes/header.php';
include '../../includes/sidebar.php';
include '../../includes/navbar.php';

// Get filter parameters
$class_id = $_GET['class_id'] ?? '';
$exam_id = $_GET['exam_id'] ?? '';

// Get classes for filter
$classes = $conn->query("SELECT id, name FROM classes ORDER BY name");

// Get exams for filter
$exams = $conn->query("SELECT id, name, term, year FROM exams ORDER BY year DESC, term DESC");

// Build performance query
$query = "SELECT 
            s.id as student_id,
            CONCAT(u.first_name, ' ', u.last_name) as student_name,
            s.admission_number,
            c.name as class_name,
            AVG(er.marks_obtained) as average_score,
            MAX(CASE WHEN er.marks_obtained >= 80 THEN 1 ELSE 0 END) as has_excellent,
            MIN(CASE WHEN er.marks_obtained < 40 THEN 1 ELSE 0 END) as has_poor
          FROM students s
          JOIN users u ON s.user_id = u.id
          JOIN classes c ON s.class_id = c.id
          LEFT JOIN exam_results er ON s.id = er.student_id";

if (!empty($exam_id)) {
    $query .= " AND er.exam_id = $exam_id";
}

if (!empty($class_id)) {
    $query .= " AND s.class_id = $class_id";
}

$query .= " WHERE u.is_active = 1
          GROUP BY s.id
          ORDER BY average_score DESC";

$result = $conn->query($query);
$performance_data = $result;

// Calculate statistics
$total_students = 0;
$avg_score = 0;
$excellent_count = 0;
$poor_count = 0;

if ($performance_data && $performance_data->num_rows > 0) {
    $performance_data->data_seek(0);
    while($row = $performance_data->fetch_assoc()) {
        $total_students++;
        $avg_score += $row['average_score'];
        if($row['has_excellent']) $excellent_count++;
        if($row['has_poor']) $poor_count++;
    }
    $avg_score = $total_students > 0 ? round($avg_score / $total_students, 2) : 0;
    $performance_data->data_seek(0);
}
?>

<div class="ml-64 mt-16 p-6">
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-800">Academic Performance Report</h1>
        <p class="text-gray-500 mt-1">View student academic performance and progress</p>
    </div>

    <!-- Filters -->
    <div class="bg-white rounded-xl shadow-sm p-4 mb-6">
        <form method="GET" class="grid grid-cols-1 md:grid-cols-3 gap-4">
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
                <label class="block text-sm font-medium text-gray-700 mb-1">Exam</label>
                <select name="exam_id" class="w-full border rounded-lg px-3 py-2">
                    <option value="">All Exams</option>
                    <?php while($exam = $exams->fetch_assoc()): ?>
                        <option value="<?php echo $exam['id']; ?>" <?php echo $exam_id == $exam['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($exam['name'] . ' - ' . $exam['term'] . ' ' . $exam['year']); ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div class="flex items-end">
                <button type="submit" class="w-full bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700">
                    <i class="fas fa-search mr-2"></i> Generate Report
                </button>
            </div>
        </form>
    </div>

    <!-- Summary Cards -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
        <div class="bg-white rounded-xl p-4 shadow-sm">
            <p class="text-gray-500 text-sm">Total Students</p>
            <p class="text-2xl font-bold"><?php echo $total_students; ?></p>
        </div>
        <div class="bg-white rounded-xl p-4 shadow-sm">
            <p class="text-gray-500 text-sm">Average Score</p>
            <p class="text-2xl font-bold"><?php echo $avg_score; ?>%</p>
        </div>
        <div class="bg-white rounded-xl p-4 shadow-sm">
            <p class="text-gray-500 text-sm">Excellent (80%+)</p>
            <p class="text-2xl font-bold text-green-600"><?php echo $excellent_count; ?></p>
        </div>
        <div class="bg-white rounded-xl p-4 shadow-sm">
            <p class="text-gray-500 text-sm">Poor (Below 40%)</p>
            <p class="text-2xl font-bold text-red-600"><?php echo $poor_count; ?></p>
        </div>
    </div>

    <!-- Performance Chart -->
    <div class="bg-white rounded-xl shadow-sm p-6 mb-6">
        <h3 class="text-lg font-semibold mb-4">Performance Distribution</h3>
        <canvas id="performanceDistributionChart" height="100"></canvas>
    </div>

    <!-- Performance Table -->
    <div class="bg-white rounded-xl shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-gray-50 border-b">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Admission No</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Student Name</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Class</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Average Score</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Grade</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    <?php if ($performance_data && $performance_data->num_rows > 0): ?>
                        <?php while($row = $performance_data->fetch_assoc()): 
                            $score = $row['average_score'];
                            if($score >= 80) {
                                $grade = 'A';
                                $color = 'text-green-600';
                                $status = 'Excellent';
                                $status_color = 'bg-green-100 text-green-700';
                            } elseif($score >= 60) {
                                $grade = 'B';
                                $color = 'text-blue-600';
                                $status = 'Good';
                                $status_color = 'bg-blue-100 text-blue-700';
                            } elseif($score >= 40) {
                                $grade = 'C';
                                $color = 'text-yellow-600';
                                $status = 'Average';
                                $status_color = 'bg-yellow-100 text-yellow-700';
                            } else {
                                $grade = 'D';
                                $color = 'text-red-600';
                                $status = 'Poor';
                                $status_color = 'bg-red-100 text-red-700';
                            }
                        ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4"><?php echo $row['admission_number']; ?></td>
                                <td class="px-6 py-4 font-medium"><?php echo htmlspecialchars($row['student_name']); ?></td>
                                <td class="px-6 py-4"><?php echo $row['class_name']; ?></td>
                                <td class="px-6 py-4 font-semibold <?php echo $color; ?>"><?php echo round($score, 2); ?>%</td>
                                <td class="px-6 py-4 font-bold <?php echo $color; ?>"><?php echo $grade; ?></td>
                                <td class="px-6 py-4">
                                    <span class="px-2 py-1 text-xs rounded-full <?php echo $status_color; ?>">
                                        <?php echo $status; ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" class="px-6 py-12 text-center text-gray-500">
                                <i class="fas fa-chart-line text-4xl mb-2 block"></i>
                                No performance data found
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
// Performance Distribution Chart
const ctx = document.getElementById('performanceDistributionChart').getContext('2d');
new Chart(ctx, {
    type: 'doughnut',
    data: {
        labels: ['Excellent (80-100%)', 'Good (60-79%)', 'Average (40-59%)', 'Poor (Below 40%)'],
        datasets: [{
            data: [
                <?php 
                $excellent = 0;
                $good = 0;
                $average = 0;
                $poor = 0;
                if ($performance_data && $performance_data->num_rows > 0) {
                    $performance_data->data_seek(0);
                    while($row = $performance_data->fetch_assoc()) {
                        $score = $row['average_score'];
                        if($score >= 80) $excellent++;
                        elseif($score >= 60) $good++;
                        elseif($score >= 40) $average++;
                        else $poor++;
                    }
                    $performance_data->data_seek(0);
                }
                echo "$excellent, $good, $average, $poor";
                ?>
            ],
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