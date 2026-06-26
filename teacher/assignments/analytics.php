<?php
require_once '../../config.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';
requireRole('teacher');

$assignment_id = $_GET['id'] ?? 0;

if (!$assignment_id) {
    header('Location: index.php');
    exit();
}

// Get assignment details
$assignment_query = $conn->prepare("
    SELECT a.*, s.name as subject_name, c.name as class_name
    FROM assignments a
    JOIN subjects s ON a.subject_id = s.id
    JOIN classes c ON a.class_id = c.id
    WHERE a.id = ? AND a.created_by = ?
");
$assignment_query->bind_param("ii", $assignment_id, $_SESSION['user_id']);
$assignment_query->execute();
$assignment = $assignment_query->get_result()->fetch_assoc();

if (!$assignment) {
    header('Location: index.php');
    exit();
}

$page_title = 'Analytics - ' . $assignment['title'];
include '../../includes/header.php';
include '../../includes/sidebar.php';
include '../../includes/navbar.php';

// Get submission data for analytics
$data_query = $conn->prepare("
    SELECT 
        s.marks_obtained,
        s.submitted_at,
        s.is_late,
        CASE WHEN s.submitted_at > a.due_date THEN 1 ELSE 0 END as is_late_submission,
        TIMESTAMPDIFF(HOUR, a.due_date, s.submitted_at) as hours_late
    FROM submissions s
    JOIN assignments a ON s.assignment_id = a.id
    WHERE s.assignment_id = ? AND s.marks_obtained IS NOT NULL
");
$data_query->bind_param("i", $assignment_id);
$data_query->execute();
$submissions_data = $data_query->get_result();

$scores = [];
$late_hours = [];
$grade_counts = ['A' => 0, 'B' => 0, 'C' => 0, 'D' => 0, 'E' => 0, 'F' => 0];

while ($row = $submissions_data->fetch_assoc()) {
    $percentage = ($row['marks_obtained'] / $assignment['max_marks']) * 100;
    $scores[] = $percentage;
    
    if ($row['is_late_submission']) {
        $late_hours[] = $row['hours_late'];
    }
    
    if ($percentage >= 80) $grade_counts['A']++;
    elseif ($percentage >= 70) $grade_counts['B']++;
    elseif ($percentage >= 60) $grade_counts['C']++;
    elseif ($percentage >= 50) $grade_counts['D']++;
    elseif ($percentage >= 40) $grade_counts['E']++;
    else $grade_counts['F']++;
}

$avg_score = count($scores) > 0 ? round(array_sum($scores) / count($scores), 1) : 0;
$highest = count($scores) > 0 ? round(max($scores), 1) : 0;
$lowest = count($scores) > 0 ? round(min($scores), 1) : 0;
$pass_count = count(array_filter($scores, function($s) { return $s >= 50; }));
$pass_rate = count($scores) > 0 ? round(($pass_count / count($scores)) * 100, 1) : 0;

// Score distribution
$distribution = [
    '0-20' => 0, '21-40' => 0, '41-60' => 0, '61-80' => 0, '81-100' => 0
];
foreach ($scores as $score) {
    if ($score <= 20) $distribution['0-20']++;
    elseif ($score <= 40) $distribution['21-40']++;
    elseif ($score <= 60) $distribution['41-60']++;
    elseif ($score <= 80) $distribution['61-80']++;
    else $distribution['81-100']++;
}
?>

<div class="ml-64 mt-16 p-6">
    <div class="max-w-full mx-auto">
        <div class="mb-6">
            <h1 class="text-2xl font-bold text-gray-800">Assignment Analytics</h1>
            <p class="text-gray-500 mt-1"><?php echo htmlspecialchars($assignment['title']); ?> - <?php echo htmlspecialchars($assignment['class_name']); ?></p>
        </div>

        <!-- Key Metrics -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
            <div class="bg-white rounded-xl p-4 shadow-sm">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-500 text-sm">Average Score</p>
                        <p class="text-2xl font-bold"><?php echo $avg_score; ?>%</p>
                    </div>
                    <div class="w-10 h-10 bg-blue-100 rounded-full flex items-center justify-center">
                        <i class="fas fa-chart-line text-blue-600"></i>
                    </div>
                </div>
            </div>
            <div class="bg-white rounded-xl p-4 shadow-sm">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-500 text-sm">Highest Score</p>
                        <p class="text-2xl font-bold text-green-600"><?php echo $highest; ?>%</p>
                    </div>
                    <div class="w-10 h-10 bg-green-100 rounded-full flex items-center justify-center">
                        <i class="fas fa-trophy text-green-600"></i>
                    </div>
                </div>
            </div>
            <div class="bg-white rounded-xl p-4 shadow-sm">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-500 text-sm">Lowest Score</p>
                        <p class="text-2xl font-bold text-red-600"><?php echo $lowest; ?>%</p>
                    </div>
                    <div class="w-10 h-10 bg-red-100 rounded-full flex items-center justify-center">
                        <i class="fas fa-chart-line text-red-600"></i>
                    </div>
                </div>
            </div>
            <div class="bg-white rounded-xl p-4 shadow-sm">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-500 text-sm">Pass Rate</p>
                        <p class="text-2xl font-bold text-purple-600"><?php echo $pass_rate; ?>%</p>
                    </div>
                    <div class="w-10 h-10 bg-purple-100 rounded-full flex items-center justify-center">
                        <i class="fas fa-check-circle text-purple-600"></i>
                    </div>
                </div>
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

        <!-- Grade Breakdown Table -->
        <div class="bg-white rounded-xl shadow-sm overflow-hidden">
            <div class="px-6 py-4 border-b bg-gray-50">
                <h3 class="text-lg font-semibold">Grade Breakdown</h3>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-gray-50 border-b">
                        <tr>
                            <th class="px-6 py-3 text-left">Grade</th>
                            <th class="px-6 py-3 text-left">Percentage Range</th>
                            <th class="px-6 py-3 text-left">Number of Students</th>
                            <th class="px-6 py-3 text-left">Percentage</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <tr>
                            <td class="px-6 py-3"><span class="px-2 py-1 bg-green-100 text-green-700 rounded">A</span></td>
                            <td>80-100%</td>
                            <td><?php echo $grade_counts['A']; ?></td>
                            <td>
                                <div class="flex items-center">
                                    <div class="w-32 bg-gray-200 rounded-full h-2">
                                        <div class="bg-green-600 h-2 rounded-full" style="width: <?php echo count($scores) > 0 ? ($grade_counts['A'] / count($scores)) * 100 : 0; ?>%"></div>
                                    </div>
                                    <span class="ml-2 text-sm"><?php echo count($scores) > 0 ? round(($grade_counts['A'] / count($scores)) * 100, 1) : 0; ?>%</span>
                                </div>
                            </td>
                        </tr>
                        <tr>
                            <td class="px-6 py-3"><span class="px-2 py-1 bg-blue-100 text-blue-700 rounded">B</span></td>
                            <td>70-79%</td>
                            <td><?php echo $grade_counts['B']; ?></td>
                            <td><div class="flex items-center"><div class="w-32 bg-gray-200 rounded-full h-2"><div class="bg-blue-600 h-2 rounded-full" style="width: <?php echo count($scores) > 0 ? ($grade_counts['B'] / count($scores)) * 100 : 0; ?>%"></div></div><span class="ml-2 text-sm"><?php echo count($scores) > 0 ? round(($grade_counts['B'] / count($scores)) * 100, 1) : 0; ?>%</span></div></td>
                        </tr>
                        <tr>
                            <td class="px-6 py-3"><span class="px-2 py-1 bg-yellow-100 text-yellow-700 rounded">C</span></td>
                            <td>60-69%</td>
                            <td><?php echo $grade_counts['C']; ?></td>
                            <td><div class="flex items-center"><div class="w-32 bg-gray-200 rounded-full h-2"><div class="bg-yellow-600 h-2 rounded-full" style="width: <?php echo count($scores) > 0 ? ($grade_counts['C'] / count($scores)) * 100 : 0; ?>%"></div></div><span class="ml-2 text-sm"><?php echo count($scores) > 0 ? round(($grade_counts['C'] / count($scores)) * 100, 1) : 0; ?>%</span></div></td>
                        </tr>
                        <tr>
                            <td class="px-6 py-3"><span class="px-2 py-1 bg-orange-100 text-orange-700 rounded">D</span></td>
                            <td>50-59%</td>
                            <td><?php echo $grade_counts['D']; ?></td>
                            <td><div class="flex items-center"><div class="w-32 bg-gray-200 rounded-full h-2"><div class="bg-orange-600 h-2 rounded-full" style="width: <?php echo count($scores) > 0 ? ($grade_counts['D'] / count($scores)) * 100 : 0; ?>%"></div></div><span class="ml-2 text-sm"><?php echo count($scores) > 0 ? round(($grade_counts['D'] / count($scores)) * 100, 1) : 0; ?>%</span></div></td>
                        </tr>
                        <tr>
                            <td class="px-6 py-3"><span class="px-2 py-1 bg-red-100 text-red-700 rounded">E/F</span></td>
                            <td>Below 50%</td>
                            <td><?php echo $grade_counts['E'] + $grade_counts['F']; ?></td>
                            <td><div class="flex items-center"><div class="w-32 bg-gray-200 rounded-full h-2"><div class="bg-red-600 h-2 rounded-full" style="width: <?php echo count($scores) > 0 ? (($grade_counts['E'] + $grade_counts['F']) / count($scores)) * 100 : 0; ?>%"></div></div><span class="ml-2 text-sm"><?php echo count($scores) > 0 ? round((($grade_counts['E'] + $grade_counts['F']) / count($scores)) * 100, 1) : 0; ?>%</span></div></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="mt-4 flex justify-end">
            <a href="submissions.php?id=<?php echo $assignment_id; ?>" class="text-blue-600 hover:text-blue-800">← Back to Submissions</a>
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
            data: [<?php echo $distribution['0-20']; ?>, <?php echo $distribution['21-40']; ?>, <?php echo $distribution['41-60']; ?>, <?php echo $distribution['61-80']; ?>, <?php echo $distribution['81-100']; ?>],
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
        labels: ['A (80-100%)', 'B (70-79%)', 'C (60-69%)', 'D (50-59%)', 'E/F (Below 50%)'],
        datasets: [{
            data: [<?php echo $grade_counts['A']; ?>, <?php echo $grade_counts['B']; ?>, <?php echo $grade_counts['C']; ?>, <?php echo $grade_counts['D']; ?>, <?php echo $grade_counts['E'] + $grade_counts['F']; ?>],
            backgroundColor: ['#10b981', '#3b82f6', '#f59e0b', '#f97316', '#ef4444']
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