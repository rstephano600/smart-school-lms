<?php
require_once '../../config.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';
requireRole('teacher');

$exam_id = $_GET['id'] ?? 0;
if (!$exam_id) {
    header('Location: index.php');
    exit();
}

// Get teacher ID
$teacher_query = $conn->prepare("SELECT id FROM teachers WHERE user_id = ?");
$teacher_query->bind_param("i", $_SESSION['user_id']);
$teacher_query->execute();
$teacher = $teacher_query->get_result()->fetch_assoc();
$teacher_id = $teacher['id'] ?? 0;

// Get exam details
$exam_query = $conn->prepare("
    SELECT te.*, s.name as subject_name, c.name as class_name
    FROM teacher_exams te
    JOIN subjects s ON te.subject_id = s.id
    JOIN classes c ON te.class_id = c.id
    WHERE te.id = ? AND te.teacher_id = ?
");
$exam_query->bind_param("ii", $exam_id, $teacher_id);
$exam_query->execute();
$exam = $exam_query->get_result()->fetch_assoc();

if (!$exam) {
    header('Location: index.php');
    exit();
}

// Get submission statistics
$stats_query = $conn->prepare("
    SELECT 
        COUNT(*) as total_submissions,
        COUNT(CASE WHEN submitted_at IS NOT NULL THEN 1 END) as submitted,
        COUNT(CASE WHEN is_graded = 1 THEN 1 END) as graded,
        AVG(percentage) as avg_percentage,
        MAX(percentage) as highest_score,
        MIN(percentage) as lowest_score,
        COUNT(CASE WHEN percentage >= ? THEN 1 END) as passed,
        COUNT(CASE WHEN percentage < ? THEN 1 END) as failed
    FROM exam_submissions
    WHERE exam_id = ?
");
$pass_mark = $exam['passing_marks'];
$stats_query->bind_param("iii", $pass_mark, $pass_mark, $exam_id);
$stats_query->execute();
$stats = $stats_query->get_result()->fetch_assoc();

// Get student performance data for chart
$performance_query = $conn->prepare("
    SELECT percentage, grade
    FROM exam_submissions
    WHERE exam_id = ? AND submitted_at IS NOT NULL
    ORDER BY percentage DESC
");
$performance_query->bind_param("i", $exam_id);
$performance_query->execute();
$performance_data = $performance_query->get_result();

$scores = [];
$grades_count = ['A' => 0, 'B' => 0, 'C' => 0, 'D' => 0, 'E' => 0, 'S' => 0, 'F' => 0];
while ($row = $performance_data->fetch_assoc()) {
    $scores[] = $row['percentage'];
    $grade = $row['grade'];
    if (isset($grades_count[$grade])) $grades_count[$grade]++;
}

$page_title = 'Analytics - ' . $exam['title'];
include '../../includes/header.php';
include '../../includes/sidebar.php';
include '../../includes/navbar.php';
?>

<div class="ml-64 mt-16 p-6">
    <div class="max-w-full mx-auto">
        <div class="mb-6">
            <h1 class="text-2xl font-bold text-gray-800">Exam Analytics</h1>
            <p class="text-gray-500 mt-1"><?php echo htmlspecialchars($exam['title']); ?> - <?php echo htmlspecialchars($exam['class_name']); ?></p>
        </div>

        <!-- Statistics Cards -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-6">
            <div class="bg-white rounded-xl p-4 shadow-sm">
                <p class="text-gray-500 text-sm">Submissions</p>
                <p class="text-2xl font-bold"><?php echo $stats['submitted']; ?> / <?php echo $stats['total_submissions']; ?></p>
                <p class="text-xs text-gray-400"><?php echo $stats['total_submissions'] > 0 ? round(($stats['submitted'] / $stats['total_submissions']) * 100, 1) : 0; ?>% submission rate</p>
            </div>
            <div class="bg-white rounded-xl p-4 shadow-sm">
                <p class="text-gray-500 text-sm">Average Score</p>
                <p class="text-2xl font-bold"><?php echo round($stats['avg_percentage'] ?? 0, 1); ?>%</p>
            </div>
            <div class="bg-white rounded-xl p-4 shadow-sm">
                <p class="text-gray-500 text-sm">Pass Rate</p>
                <p class="text-2xl font-bold text-green-600"><?php echo $stats['submitted'] > 0 ? round(($stats['passed'] / $stats['submitted']) * 100, 1) : 0; ?>%</p>
            </div>
            <div class="bg-white rounded-xl p-4 shadow-sm">
                <p class="text-gray-500 text-sm">Highest/Lowest</p>
                <p class="text-lg font-bold"><span class="text-green-600"><?php echo round($stats['highest_score'] ?? 0, 1); ?>%</span> / <span class="text-red-600"><?php echo round($stats['lowest_score'] ?? 0, 1); ?>%</span></p>
            </div>
        </div>

        <!-- Charts Row -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
            <!-- Score Distribution Chart -->
            <div class="bg-white rounded-xl shadow-sm p-6">
                <h3 class="text-lg font-semibold mb-4">Score Distribution</h3>
                <canvas id="scoreChart" height="250"></canvas>
            </div>

            <!-- Grade Distribution Chart -->
            <div class="bg-white rounded-xl shadow-sm p-6">
                <h3 class="text-lg font-semibold mb-4">Grade Distribution</h3>
                <canvas id="gradeChart" height="250"></canvas>
            </div>
        </div>

        <!-- Top & Bottom Performers -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <div class="bg-white rounded-xl shadow-sm overflow-hidden">
                <div class="px-6 py-4 border-b bg-green-50">
                    <h3 class="text-lg font-semibold text-green-700">
                        <i class="fas fa-trophy mr-2"></i> Top Performers
                    </h3>
                </div>
                <div class="divide-y divide-gray-200">
                    <?php
                    $top_query = $conn->prepare("
                        SELECT es.percentage, es.grade, CONCAT(u.first_name, ' ', u.last_name) as student_name
                        FROM exam_submissions es
                        JOIN students s ON es.student_id = s.id
                        JOIN users u ON s.user_id = u.id
                        WHERE es.exam_id = ? AND es.submitted_at IS NOT NULL
                        ORDER BY es.percentage DESC
                        LIMIT 5
                    ");
                    $top_query->bind_param("i", $exam_id);
                    $top_query->execute();
                    $top_students = $top_query->get_result();
                    ?>
                    <?php if ($top_students->num_rows > 0): ?>
                        <?php while($student = $top_students->fetch_assoc()): ?>
                            <div class="p-4 flex justify-between items-center">
                                <div>
                                    <p class="font-medium"><?php echo htmlspecialchars($student['student_name']); ?></p>
                                    <p class="text-xs text-gray-500">Grade: <?php echo $student['grade']; ?></p>
                                </div>
                                <span class="text-xl font-bold text-green-600"><?php echo round($student['percentage'], 1); ?>%</span>
                            </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <div class="p-8 text-center text-gray-500">No data available</div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="bg-white rounded-xl shadow-sm overflow-hidden">
                <div class="px-6 py-4 border-b bg-red-50">
                    <h3 class="text-lg font-semibold text-red-700">
                        <i class="fas fa-exclamation-triangle mr-2"></i> Needs Improvement
                    </h3>
                </div>
                <div class="divide-y divide-gray-200">
                    <?php
                    $bottom_query = $conn->prepare("
                        SELECT es.percentage, es.grade, CONCAT(u.first_name, ' ', u.last_name) as student_name
                        FROM exam_submissions es
                        JOIN students s ON es.student_id = s.id
                        JOIN users u ON s.user_id = u.id
                        WHERE es.exam_id = ? AND es.submitted_at IS NOT NULL
                        ORDER BY es.percentage ASC
                        LIMIT 5
                    ");
                    $bottom_query->bind_param("i", $exam_id);
                    $bottom_query->execute();
                    $bottom_students = $bottom_query->get_result();
                    ?>
                    <?php if ($bottom_students->num_rows > 0): ?>
                        <?php while($student = $bottom_students->fetch_assoc()): ?>
                            <div class="p-4 flex justify-between items-center">
                                <div>
                                    <p class="font-medium"><?php echo htmlspecialchars($student['student_name']); ?></p>
                                    <p class="text-xs text-gray-500">Grade: <?php echo $student['grade']; ?></p>
                                </div>
                                <span class="text-xl font-bold text-red-600"><?php echo round($student['percentage'], 1); ?>%</span>
                            </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <div class="p-8 text-center text-gray-500">No data available</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// Score Distribution Chart
const scoreCtx = document.getElementById('scoreChart').getContext('2d');
new Chart(scoreCtx, {
    type: 'bar',
    data: {
        labels: ['0-20%', '21-40%', '41-60%', '61-80%', '81-100%'],
        datasets: [{
            label: 'Number of Students',
            data: [
                <?php echo count(array_filter($scores, function($s) { return $s >= 0 && $s <= 20; })); ?>,
                <?php echo count(array_filter($scores, function($s) { return $s > 20 && $s <= 40; })); ?>,
                <?php echo count(array_filter($scores, function($s) { return $s > 40 && $s <= 60; })); ?>,
                <?php echo count(array_filter($scores, function($s) { return $s > 60 && $s <= 80; })); ?>,
                <?php echo count(array_filter($scores, function($s) { return $s > 80 && $s <= 100; })); ?>
            ],
            backgroundColor: '#3b82f6',
            borderRadius: 8
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: true,
        plugins: { legend: { position: 'bottom' } }
    }
});

// Grade Distribution Chart
const gradeCtx = document.getElementById('gradeChart').getContext('2d');
new Chart(gradeCtx, {
    type: 'doughnut',
    data: {
        labels: ['A', 'B', 'C', 'D', 'E', 'S', 'F'],
        datasets: [{
            data: [
                <?php echo $grades_count['A']; ?>,
                <?php echo $grades_count['B']; ?>,
                <?php echo $grades_count['C']; ?>,
                <?php echo $grades_count['D']; ?>,
                <?php echo $grades_count['E']; ?>,
                <?php echo $grades_count['S']; ?>,
                <?php echo $grades_count['F']; ?>
            ],
            backgroundColor: ['#10b981', '#3b82f6', '#06b6d4', '#f59e0b', '#f97316', '#8b5cf6', '#ef4444']
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: true,
        plugins: { legend: { position: 'bottom' } }
    }
});
</script>

<?php include '../../includes/footer.php'; ?>