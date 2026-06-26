<?php
require_once '../../config.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';
requireRole('academic');

$page_title = 'Academic Analytics';
include '../../includes/header.php';
include '../../includes/sidebar.php';
include '../../includes/navbar.php';

// Get academic year and term
$school = $conn->query("SELECT academic_year, current_term FROM school_settings LIMIT 1")->fetch_assoc();
$academic_year = $school['academic_year'] ?? date('Y');
$current_term = $school['current_term'] ?? 'term1';

// Get overall statistics
$stats = [];

// Total students
$total_students = $conn->query("SELECT COUNT(*) as count FROM users WHERE role = 'student' AND is_active = 1")->fetch_assoc()['count'];

// Total teachers
$total_teachers = $conn->query("SELECT COUNT(*) as count FROM users WHERE role = 'teacher' AND is_active = 1")->fetch_assoc()['count'];

// Total classes
$total_classes = $conn->query("SELECT COUNT(*) as count FROM classes")->fetch_assoc()['count'];

// Total subjects
$total_subjects = $conn->query("SELECT COUNT(*) as count FROM subjects")->fetch_assoc()['count'];

// Performance by class
$class_performance = $conn->query("
    SELECT 
        c.id, c.name,
        COUNT(DISTINCT s.id) as student_count,
        AVG(er.marks_obtained) as avg_score,
        COUNT(CASE WHEN er.marks_obtained >= 50 THEN 1 END) as passed,
        COUNT(CASE WHEN er.marks_obtained < 50 THEN 1 END) as failed
    FROM classes c
    LEFT JOIN students s ON c.id = s.class_id
    LEFT JOIN exam_results er ON s.id = er.student_id
    GROUP BY c.id
    ORDER BY avg_score DESC
");

// Subject performance
$subject_performance = $conn->query("
    SELECT 
        s.id, s.name, s.code,
        AVG(er.marks_obtained) as avg_score,
        COUNT(DISTINCT er.student_id) as student_count,
        MAX(er.marks_obtained) as highest,
        MIN(er.marks_obtained) as lowest
    FROM subjects s
    LEFT JOIN exam_results er ON s.id = er.subject_id
    GROUP BY s.id
    ORDER BY avg_score DESC
    LIMIT 10
");

// Monthly enrollment trends (last 6 months)
$enrollment_trends = [];
for ($i = 5; $i >= 0; $i--) {
    $month = date('M', strtotime("-$i months"));
    $enrollment_trends['labels'][] = $month;
    $enrollment_trends['values'][] = rand(20, 60); // Sample - would be actual data
}

// Performance distribution
$performance_dist = [
    'excellent' => 0, // 80-100%
    'good' => 0,      // 60-79%
    'average' => 0,   // 40-59%
    'poor' => 0       // 0-39%
];

$dist_query = $conn->query("
    SELECT AVG(marks_obtained) as avg_score
    FROM exam_results
    GROUP BY student_id
");
while ($row = $dist_query->fetch_assoc()) {
    $score = $row['avg_score'];
    if ($score >= 80) $performance_dist['excellent']++;
    elseif ($score >= 60) $performance_dist['good']++;
    elseif ($score >= 40) $performance_dist['average']++;
    elseif ($score > 0) $performance_dist['poor']++;
}
?>

<div class="ml-64 mt-16 p-6">
    <div class="max-w-full mx-auto">
        <div class="mb-6">
            <h1 class="text-2xl font-bold text-gray-800">Academic Analytics Dashboard</h1>
            <p class="text-gray-500 mt-1">Comprehensive analytics and insights for academic performance</p>
        </div>

        <!-- Period Selector -->
        <div class="bg-white rounded-xl shadow-sm p-4 mb-6">
            <div class="flex items-center justify-between">
                <div>
                    <span class="text-gray-600">Academic Year: </span>
                    <span class="font-semibold"><?php echo $academic_year; ?></span>
                    <span class="text-gray-600 ml-4">Current Term: </span>
                    <span class="font-semibold"><?php echo ucfirst($current_term); ?></span>
                </div>
                <div class="flex space-x-2">
                    <select class="border rounded-lg px-3 py-1 text-sm">
                        <option>2024/2025</option>
                        <option selected>2025/2026</option>
                        <option>2026/2027</option>
                    </select>
                    <select class="border rounded-lg px-3 py-1 text-sm">
                        <option>Term 1</option>
                        <option selected>Term 2</option>
                        <option>Term 3</option>
                    </select>
                </div>
            </div>
        </div>

        <!-- Stats Cards -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-6">
            <div class="bg-white rounded-xl p-6 shadow-sm hover:shadow-lg transition-all">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-500 text-sm">Total Students</p>
                        <p class="text-3xl font-bold text-gray-800"><?php echo $total_students; ?></p>
                    </div>
                    <div class="w-12 h-12 bg-blue-100 rounded-full flex items-center justify-center">
                        <i class="fas fa-user-graduate text-blue-600 text-xl"></i>
                    </div>
                </div>
            </div>
            <div class="bg-white rounded-xl p-6 shadow-sm hover:shadow-lg transition-all">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-500 text-sm">Total Teachers</p>
                        <p class="text-3xl font-bold text-gray-800"><?php echo $total_teachers; ?></p>
                    </div>
                    <div class="w-12 h-12 bg-green-100 rounded-full flex items-center justify-center">
                        <i class="fas fa-chalkboard-user text-green-600 text-xl"></i>
                    </div>
                </div>
            </div>
            <div class="bg-white rounded-xl p-6 shadow-sm hover:shadow-lg transition-all">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-500 text-sm">Total Classes</p>
                        <p class="text-3xl font-bold text-gray-800"><?php echo $total_classes; ?></p>
                    </div>
                    <div class="w-12 h-12 bg-purple-100 rounded-full flex items-center justify-center">
                        <i class="fas fa-school text-purple-600 text-xl"></i>
                    </div>
                </div>
            </div>
            <div class="bg-white rounded-xl p-6 shadow-sm hover:shadow-lg transition-all">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-500 text-sm">Total Subjects</p>
                        <p class="text-3xl font-bold text-gray-800"><?php echo $total_subjects; ?></p>
                    </div>
                    <div class="w-12 h-12 bg-yellow-100 rounded-full flex items-center justify-center">
                        <i class="fas fa-book text-yellow-600 text-xl"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Charts Row 1 -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
            <!-- Enrollment Trends -->
            <div class="bg-white rounded-xl shadow-sm p-6">
                <h3 class="text-lg font-semibold mb-4">Student Enrollment Trends</h3>
                <canvas id="enrollmentChart" height="200"></canvas>
            </div>

            <!-- Performance Distribution -->
            <div class="bg-white rounded-xl shadow-sm p-6">
                <h3 class="text-lg font-semibold mb-4">Performance Distribution</h3>
                <canvas id="performanceDistChart" height="200"></canvas>
            </div>
        </div>

        <!-- Charts Row 2 -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
            <!-- Class Performance -->
            <div class="bg-white rounded-xl shadow-sm p-6">
                <h3 class="text-lg font-semibold mb-4">Class Performance Comparison</h3>
                <canvas id="classPerformanceChart" height="250"></canvas>
            </div>

            <!-- Subject Performance -->
            <div class="bg-white rounded-xl shadow-sm p-6">
                <h3 class="text-lg font-semibold mb-4">Top Performing Subjects</h3>
                <canvas id="subjectPerformanceChart" height="250"></canvas>
            </div>
        </div>

        <!-- Class Performance Table -->
        <div class="bg-white rounded-xl shadow-sm overflow-hidden mb-6">
            <div class="px-6 py-4 border-b bg-gray-50">
                <h3 class="text-lg font-semibold">Class Performance Summary</h3>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-gray-50 border-b">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Class</th>
                            <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">Students</th>
                            <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">Average Score</th>
                            <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">Passed</th>
                            <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">Failed</th>
                            <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">Pass Rate</th>
                            <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <?php while($class = $class_performance->fetch_assoc()): 
                            $pass_rate = $class['student_count'] > 0 ? round(($class['passed'] / $class['student_count']) * 100, 1) : 0;
                            $status_color = $pass_rate >= 80 ? 'bg-green-100 text-green-700' : ($pass_rate >= 50 ? 'bg-yellow-100 text-yellow-700' : 'bg-red-100 text-red-700');
                            $status_text = $pass_rate >= 80 ? 'Excellent' : ($pass_rate >= 50 ? 'Average' : 'Needs Improvement');
                        ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4 font-medium"><?php echo htmlspecialchars($class['name']); ?></td>
                                <td class="px-6 py-4 text-center"><?php echo $class['student_count']; ?></td>
                                <td class="px-6 py-4 text-center font-semibold"><?php echo round($class['avg_score'] ?? 0, 1); ?>%</td>
                                <td class="px-6 py-4 text-center text-green-600"><?php echo $class['passed'] ?? 0; ?></td>
                                <td class="px-6 py-4 text-center text-red-600"><?php echo $class['failed'] ?? 0; ?></td>
                                <td class="px-6 py-4 text-center font-semibold"><?php echo $pass_rate; ?>%</td>
                                <td class="px-6 py-4 text-center">
                                    <span class="px-2 py-1 text-xs rounded-full <?php echo $status_color; ?>">
                                        <?php echo $status_text; ?>
                                    </span>
                                 </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Top Subjects -->
        <div class="bg-white rounded-xl shadow-sm overflow-hidden">
            <div class="px-6 py-4 border-b bg-gray-50">
                <h3 class="text-lg font-semibold">Subject Performance Analysis</h3>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-gray-50 border-b">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Subject</th>
                            <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">Code</th>
                            <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">Students</th>
                            <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">Average</th>
                            <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">Highest</th>
                            <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">Lowest</th>
                            <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">Performance</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <?php while($subject = $subject_performance->fetch_assoc()): 
                            $avg = round($subject['avg_score'] ?? 0, 1);
                            $bar_width = ($avg / 100) * 100;
                            $bar_color = $avg >= 70 ? 'bg-green-500' : ($avg >= 50 ? 'bg-yellow-500' : 'bg-red-500');
                        ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4 font-medium"><?php echo htmlspecialchars($subject['name']); ?> </td>
                                <td class="px-6 py-4 text-center"><?php echo $subject['code']; ?> </td>
                                <td class="px-6 py-4 text-center"><?php echo $subject['student_count']; ?> </td>
                                <td class="px-6 py-4 text-center font-semibold"><?php echo $avg; ?>%</td>
                                <td class="px-6 py-4 text-center text-green-600"><?php echo round($subject['highest'] ?? 0, 1); ?>%</td>
                                <td class="px-6 py-4 text-center text-red-600"><?php echo round($subject['lowest'] ?? 0, 1); ?>%</td>
                                <td class="px-6 py-4">
                                    <div class="w-full bg-gray-200 rounded-full h-2">
                                        <div class="<?php echo $bar_color; ?> h-2 rounded-full" style="width: <?php echo $bar_width; ?>%"></div>
                                    </div>
                                </td>
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
// Enrollment Trends Chart
const enrollmentCtx = document.getElementById('enrollmentChart').getContext('2d');
new Chart(enrollmentCtx, {
    type: 'line',
    data: {
        labels: <?php echo json_encode($enrollment_trends['labels']); ?>,
        datasets: [{
            label: 'New Enrollments',
            data: <?php echo json_encode($enrollment_trends['values']); ?>,
            borderColor: '#3b82f6',
            backgroundColor: 'rgba(59, 130, 246, 0.1)',
            tension: 0.4,
            fill: true
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: true,
        plugins: { legend: { position: 'bottom' } }
    }
});

// Performance Distribution Chart
const distCtx = document.getElementById('performanceDistChart').getContext('2d');
new Chart(distCtx, {
    type: 'doughnut',
    data: {
        labels: ['Excellent (80-100%)', 'Good (60-79%)', 'Average (40-59%)', 'Poor (Below 40%)'],
        datasets: [{
            data: [<?php echo $performance_dist['excellent']; ?>, <?php echo $performance_dist['good']; ?>, <?php echo $performance_dist['average']; ?>, <?php echo $performance_dist['poor']; ?>],
            backgroundColor: ['#10b981', '#3b82f6', '#f59e0b', '#ef4444']
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: true,
        plugins: { legend: { position: 'bottom' } }
    }
});

// Class Performance Chart
const classCtx = document.getElementById('classPerformanceChart').getContext('2d');
<?php
$class_performance->data_seek(0);
$class_names = [];
$class_scores = [];
while($class = $class_performance->fetch_assoc()) {
    $class_names[] = $class['name'];
    $class_scores[] = round($class['avg_score'] ?? 0, 1);
}
?>
new Chart(classCtx, {
    type: 'bar',
    data: {
        labels: <?php echo json_encode($class_names); ?>,
        datasets: [{
            label: 'Average Score (%)',
            data: <?php echo json_encode($class_scores); ?>,
            backgroundColor: '#3b82f6',
            borderRadius: 8
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: true,
        scales: { y: { beginAtZero: true, max: 100 } },
        plugins: { legend: { position: 'bottom' } }
    }
});

// Subject Performance Chart
const subjectCtx = document.getElementById('subjectPerformanceChart').getContext('2d');
<?php
$subject_performance->data_seek(0);
$subject_names = [];
$subject_scores = [];
while($subject = $subject_performance->fetch_assoc()) {
    $subject_names[] = $subject['name'];
    $subject_scores[] = round($subject['avg_score'] ?? 0, 1);
}
?>
new Chart(subjectCtx, {
    type: 'bar',
    data: {
        labels: <?php echo json_encode($subject_names); ?>,
        datasets: [{
            label: 'Average Score (%)',
            data: <?php echo json_encode($subject_scores); ?>,
            backgroundColor: '#10b981',
            borderRadius: 8
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: true,
        scales: { y: { beginAtZero: true, max: 100 } },
        plugins: { legend: { position: 'bottom' } },
        indexAxis: 'y'
    }
});
</script>

<?php include '../../includes/footer.php'; ?>