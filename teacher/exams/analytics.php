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

// ============================================
// DOWNLOAD RESULTS AS CSV
// ============================================
if (isset($_GET['download'])) {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="exam_results_' . $exam['title'] . '_' . date('Y-m-d') . '.csv"');
    
    $output = fopen('php://output', 'w');
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    fputcsv($output, [
        'Rank', 'Admission No', 'Student Name', 'Score', 'Total Marks', 
        'Percentage', 'Grade', 'Status', 'Submitted At'
    ]);
    
    $download_query = $conn->prepare("
        SELECT 
            s.id as student_id,
            s.admission_number,
            CONCAT(u.first_name, ' ', u.last_name) as student_name,
            es.total_score,
            es.percentage,
            es.grade,
            es.submitted_at,
            es.is_graded
        FROM students s
        JOIN users u ON s.user_id = u.id
        LEFT JOIN exam_submissions es ON s.id = es.student_id AND es.exam_id = ?
        WHERE s.class_id = ?
        ORDER BY COALESCE(es.percentage, -1) DESC, u.first_name ASC
    ");
    $download_query->bind_param("ii", $exam_id, $exam['class_id']);
    $download_query->execute();
    $results = $download_query->get_result();
    
    $rank = 1;
    $prev_score = -1;
    $rank_counter = 1;
    
    while ($row = $results->fetch_assoc()) {
        $is_submitted = $row['submitted_at'] ? true : false;
        $percentage = $row['percentage'] ?? 0;
        
        // ✅ Calculate grade if empty
        $grade = $row['grade'] ?? '';
        if (empty($grade) || $grade == '0') {
            if ($percentage >= 75) $grade = 'A';
            elseif ($percentage >= 65) $grade = 'B';
            elseif ($percentage >= 45) $grade = 'C';
            elseif ($percentage >= 30) $grade = 'D';
            else $grade = 'F';
        }
        
        if ($is_submitted && $row['percentage'] !== null) {
            if ($row['percentage'] != $prev_score) {
                $rank = $rank_counter;
            }
            $rank_counter++;
            $prev_score = $row['percentage'];
        }
        
        $status = 'Not Taken';
        if ($is_submitted) {
            $status = ($percentage >= $exam['passing_marks']) ? 'PASS' : 'FAIL';
        }
        
        fputcsv($output, [
            $is_submitted ? $rank : '-',
            $row['admission_number'],
            $row['student_name'],
            $is_submitted ? ($row['total_score'] ?? 0) : '-',
            $exam['total_marks'],
            $is_submitted ? round($percentage, 1) . '%' : '-',
            $is_submitted ? $grade : '-',
            $status,
            $is_submitted ? date('Y-m-d H:i', strtotime($row['submitted_at'])) : '-'
        ]);
    }
    
    fclose($output);
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
    if (empty($grade) || $grade == '0') {
        $pct = $row['percentage'];
        if ($pct >= 75) $grade = 'A';
        elseif ($pct >= 65) $grade = 'B';
        elseif ($pct >= 45) $grade = 'C';
        elseif ($pct >= 30) $grade = 'D';
        else $grade = 'F';
    }
    if (isset($grades_count[$grade])) $grades_count[$grade]++;
}

// Get all students with results
$results_query = $conn->prepare("
    SELECT 
        s.id as student_id,
        s.admission_number,
        CONCAT(u.first_name, ' ', u.last_name) as student_name,
        es.total_score,
        es.percentage,
        es.grade,
        es.submitted_at,
        es.is_graded
    FROM students s
    JOIN users u ON s.user_id = u.id
    LEFT JOIN exam_submissions es ON s.id = es.student_id AND es.exam_id = ?
    WHERE s.class_id = ?
    ORDER BY COALESCE(es.percentage, -1) DESC, u.first_name ASC
");
$results_query->bind_param("ii", $exam_id, $exam['class_id']);
$results_query->execute();
$all_results = $results_query->get_result();

$total_students = 0;
$submitted_count = 0;
$passed_count = 0;
$failed_count = 0;
$students_data = [];

while ($row = $all_results->fetch_assoc()) {
    $total_students++;
    $students_data[] = $row;
    if ($row['submitted_at']) {
        $submitted_count++;
        if ($row['percentage'] !== null) {
            if ($row['percentage'] >= $exam['passing_marks']) {
                $passed_count++;
            } else {
                $failed_count++;
            }
        }
    }
}
$all_results->data_seek(0);

$avg_percentage = $submitted_count > 0 ? round($stats['avg_percentage'] ?? 0, 1) : 0;
$pass_rate = $submitted_count > 0 ? round(($passed_count / $submitted_count) * 100, 1) : 0;

$page_title = 'Analytics - ' . $exam['title'];
include '../../includes/header.php';
include '../../includes/sidebar.php';
include '../../includes/navbar.php';
?>

<style>
.grade-badge {
    padding: 3px 12px;
    border-radius: 20px;
    font-size: 13px;
    font-weight: 600;
}
.grade-a { background: #d1fae5; color: #065f46; }
.grade-b { background: #dbeafe; color: #1e40af; }
.grade-c { background: #fef3c7; color: #92400e; }
.grade-d { background: #fef3c7; color: #92400e; }
.grade-f { background: #fee2e2; color: #991b1b; }
</style>

<div class="ml-64 mt-16 p-6 bg-gray-50 min-h-screen">
    <div class="max-w-full mx-auto">
        <div class="mb-6 flex justify-between items-center flex-wrap">
            <div>
                <h1 class="text-2xl font-bold text-gray-800">📊 Exam Analytics</h1>
                <p class="text-gray-500 mt-1"><?php echo htmlspecialchars($exam['title']); ?> - <?php echo htmlspecialchars($exam['class_name']); ?></p>
                <p class="text-sm text-gray-400"><?php echo htmlspecialchars($exam['subject_name']); ?> • <?php echo $exam['total_marks']; ?> marks</p>
            </div>
            <div class="flex space-x-2 mt-3 md:mt-0">
                <a href="?id=<?php echo $exam_id; ?>&download=1" 
                   class="bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700 transition flex items-center">
                    <i class="fas fa-download mr-2"></i> Download Results
                </a>
                <button onclick="window.print()" class="bg-gray-600 text-white px-4 py-2 rounded-lg hover:bg-gray-700 transition flex items-center">
                    <i class="fas fa-print mr-2"></i> Print
                </button>
                <a href="index.php" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition flex items-center">
                    <i class="fas fa-arrow-left mr-2"></i> Back
                </a>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4 mb-6">
            <div class="bg-white rounded-xl p-4 shadow-sm">
                <p class="text-gray-500 text-sm">Total Students</p>
                <p class="text-2xl font-bold"><?php echo $total_students; ?></p>
            </div>
            <div class="bg-white rounded-xl p-4 shadow-sm">
                <p class="text-gray-500 text-sm">Submitted</p>
                <p class="text-2xl font-bold text-green-600"><?php echo $submitted_count; ?> / <?php echo $total_students; ?></p>
                <p class="text-xs text-gray-400"><?php echo $submitted_count > 0 ? round(($submitted_count / $total_students) * 100, 1) : 0; ?>% submission rate</p>
            </div>
            <div class="bg-white rounded-xl p-4 shadow-sm">
                <p class="text-gray-500 text-sm">Average Score</p>
                <p class="text-2xl font-bold text-blue-600"><?php echo $avg_percentage; ?>%</p>
            </div>
            <div class="bg-white rounded-xl p-4 shadow-sm">
                <p class="text-gray-500 text-sm">Passed</p>
                <p class="text-2xl font-bold text-green-600"><?php echo $passed_count; ?></p>
                <p class="text-xs text-gray-400"><?php echo $pass_rate; ?>% pass rate</p>
            </div>
            <div class="bg-white rounded-xl p-4 shadow-sm">
                <p class="text-gray-500 text-sm">Failed</p>
                <p class="text-2xl font-bold text-red-600"><?php echo $failed_count; ?></p>
                <p class="text-xs text-gray-400"><?php echo $submitted_count > 0 ? round(($failed_count / $submitted_count) * 100, 1) : 0; ?>% fail rate</p>
            </div>
        </div>

        <!-- Charts Row -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
            <div class="bg-white rounded-xl shadow-sm p-6">
                <h3 class="text-lg font-semibold mb-4">Score Distribution</h3>
                <canvas id="scoreChart" height="250"></canvas>
            </div>
            <div class="bg-white rounded-xl shadow-sm p-6">
                <h3 class="text-lg font-semibold mb-4">Grade Distribution</h3>
                <canvas id="gradeChart" height="250"></canvas>
            </div>
        </div>

        <!-- Full Results Table -->
        <div class="bg-white rounded-xl shadow-sm overflow-hidden">
            <div class="px-6 py-4 border-b bg-gray-50 flex justify-between items-center">
                <h3 class="text-lg font-semibold">📋 Student Results</h3>
                <div class="flex items-center space-x-4 text-sm">
                    <span class="px-2 py-1 bg-green-100 text-green-700 rounded">🟢 Pass</span>
                    <span class="px-2 py-1 bg-red-100 text-red-700 rounded">🔴 Fail</span>
                    <span class="px-2 py-1 bg-gray-100 text-gray-700 rounded">⚪ Not Taken</span>
                </div>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-gray-50 border-b">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">#</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Admission No</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Student Name</th>
                            <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Score</th>
                            <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Percentage</th>
                            <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Grade</th>
                            <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Status</th>
                            <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Rank</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <?php 
                        $rank = 1;
                        $prev_score = -1;
                        $rank_counter = 1;
                        $index = 1;
                        foreach($students_data as $student): 
                            $is_submitted = $student['submitted_at'] ? true : false;
                            $percentage = $student['percentage'] ?? 0;
                            
                            // ✅ Calculate rank
                            if ($is_submitted && $student['percentage'] !== null) {
                                if ($student['percentage'] != $prev_score) {
                                    $rank = $rank_counter;
                                }
                                $rank_counter++;
                                $prev_score = $student['percentage'];
                            }
                            
                            $rank_display = $is_submitted ? $rank : '-';
                            $row_class = $is_submitted ? ($percentage >= $exam['passing_marks'] ? 'bg-green-50' : 'bg-red-50') : '';
                            
                            // ✅ FIX: Calculate grade if empty
                            $grade = $student['grade'] ?? '';
                            if (empty($grade) || $grade == '0') {
                                if ($percentage >= 75) $grade = 'A';
                                elseif ($percentage >= 65) $grade = 'B';
                                elseif ($percentage >= 45) $grade = 'C';
                                elseif ($percentage >= 30) $grade = 'D';
                                else $grade = 'F';
                            }
                            $grade_class = 'grade-' . strtolower($grade);
                            
                            $status = 'Not Taken';
                            $status_class = 'bg-gray-100 text-gray-700';
                            if ($is_submitted) {
                                if ($percentage >= $exam['passing_marks']) {
                                    $status = '✅ PASS';
                                    $status_class = 'bg-green-100 text-green-700';
                                } else {
                                    $status = '❌ FAIL';
                                    $status_class = 'bg-red-100 text-red-700';
                                }
                            }
                        ?>
                            <tr class="hover:bg-gray-50 transition-all <?php echo $row_class; ?>">
                                <td class="px-4 py-3 text-sm"><?php echo $index++; ?></td>
                                <td class="px-4 py-3 text-sm"><?php echo htmlspecialchars($student['admission_number']); ?></td>
                                <td class="px-4 py-3 font-medium"><?php echo htmlspecialchars($student['student_name']); ?></td>
                                <td class="px-4 py-3 text-center">
                                    <?php if ($is_submitted && $student['total_score'] !== null): ?>
                                        <span class="font-semibold"><?php echo $student['total_score']; ?> / <?php echo $exam['total_marks']; ?></span>
                                    <?php else: ?>
                                        <span class="text-gray-400">-</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <?php if ($is_submitted && $student['percentage'] !== null): ?>
                                        <span class="font-bold <?php echo $percentage >= $exam['passing_marks'] ? 'text-green-600' : 'text-red-600'; ?>">
                                            <?php echo $percentage; ?>%
                                        </span>
                                    <?php else: ?>
                                        <span class="text-gray-400">-</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <?php if ($is_submitted): ?>
                                        <span class="grade-badge <?php echo $grade_class; ?>">
                                            <?php echo $grade; ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-gray-400">-</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <span class="px-3 py-1 text-sm rounded-full <?php echo $status_class; ?>">
                                        <?php echo $status; ?>
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-center font-bold">
                                    <?php if ($is_submitted && $student['percentage'] !== null): ?>
                                        <span class="px-3 py-1 text-sm rounded-full <?php echo $rank <= 3 ? 'bg-yellow-100 text-yellow-700' : 'bg-gray-100 text-gray-700'; ?>">
                                            #<?php echo $rank_display; ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-gray-400">-</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Grading Scale Reference -->
        <div class="mt-6 bg-white rounded-xl shadow-sm p-4">
            <h4 class="font-semibold text-gray-700 mb-3">📊 Grading Scale Reference</h4>
            <div class="grid grid-cols-2 md:grid-cols-5 gap-2 text-center text-sm">
                <div class="p-2 bg-green-100 rounded-lg">
                    <span class="font-bold text-green-700">A</span>
                    <span class="block text-xs text-gray-600">75-100%</span>
                    <span class="block text-xs text-green-600 font-bold">PASS</span>
                </div>
                <div class="p-2 bg-blue-100 rounded-lg">
                    <span class="font-bold text-blue-700">B</span>
                    <span class="block text-xs text-gray-600">65-74%</span>
                    <span class="block text-xs text-blue-600 font-bold">PASS</span>
                </div>
                <div class="p-2 bg-cyan-100 rounded-lg">
                    <span class="font-bold text-cyan-700">C</span>
                    <span class="block text-xs text-gray-600">45-64%</span>
                    <span class="block text-xs text-cyan-600 font-bold">PASS</span>
                </div>
                <div class="p-2 bg-yellow-100 rounded-lg">
                    <span class="font-bold text-yellow-700">D</span>
                    <span class="block text-xs text-gray-600">30-44%</span>
                    <span class="block text-xs text-yellow-600 font-bold">PASS</span>
                </div>
                <div class="p-2 bg-red-100 rounded-lg">
                    <span class="font-bold text-red-700">F</span>
                    <span class="block text-xs text-gray-600">0-29%</span>
                    <span class="block text-xs text-red-600 font-bold">FAIL</span>
                </div>
            </div>
            <p class="text-xs text-gray-500 mt-2 text-center">* Passing mark is <?php echo $exam['passing_marks']; ?>% and above</p>
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
        labels: ['A', 'B', 'C', 'D', 'F'],
        datasets: [{
            data: [
                <?php echo $grades_count['A']; ?>,
                <?php echo $grades_count['B']; ?>,
                <?php echo $grades_count['C']; ?>,
                <?php echo $grades_count['D']; ?>,
                <?php echo $grades_count['F']; ?>
            ],
            backgroundColor: ['#10b981', '#3b82f6', '#06b6d4', '#f59e0b', '#ef4444']
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