<?php
require_once '../../config.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';
requireRole('student');

$page_title = 'My Academic Results';
include '../../includes/header.php';
include '../../includes/sidebar.php';
include '../../includes/navbar.php';

// Get student ID
$student_query = $conn->prepare("
    SELECT s.id, s.class_id, c.name as class_name, s.admission_number,
           CONCAT(u.first_name, ' ', u.last_name) as student_name
    FROM students s
    JOIN users u ON s.user_id = u.id
    JOIN classes c ON s.class_id = c.id
    WHERE u.id = ?
");
$student_query->bind_param("i", $_SESSION['user_id']);
$student_query->execute();
$student = $student_query->get_result()->fetch_assoc();
$student_id = $student['id'];
$class_id = $student['class_id'];
$class_name = $student['class_name'];

// Get all exams taken by this student with results
$exams_query = $conn->prepare("
    SELECT es.*, te.title as exam_title, te.total_marks as exam_total,
           s.name as subject_name, te.subject_id,
           (SELECT COUNT(*) FROM exam_submissions WHERE exam_id = te.id) as total_students,
           (SELECT AVG(percentage) FROM exam_submissions WHERE exam_id = te.id AND percentage IS NOT NULL) as class_average,
           (SELECT COUNT(*) + 1 FROM exam_submissions es2 
            WHERE es2.exam_id = te.id AND es2.percentage > es.percentage) as rank_position
    FROM exam_submissions es
    JOIN teacher_exams te ON es.exam_id = te.id
    JOIN subjects s ON te.subject_id = s.id
    WHERE es.student_id = ?
    ORDER BY es.submitted_at DESC
");
$exams_query->bind_param("i", $student_id);
$exams_query->execute();
$exams = $exams_query->get_result();

// Calculate overall statistics
$total_exams = 0;
$total_score = 0;
$total_percentage = 0;
$passed_exams = 0;
$failed_exams = 0;
$exams_data = [];

while ($exam = $exams->fetch_assoc()) {
    $total_exams++;
    $total_percentage += $exam['percentage'];
    $total_score += $exam['total_score'];
    if ($exam['percentage'] >= 75) {
        $passed_exams++;
    } else {
        $failed_exams++;
    }
    $exams_data[] = $exam;
}

$overall_avg = $total_exams > 0 ? round($total_percentage / $total_exams, 1) : 0;
$pass_rate = $total_exams > 0 ? round(($passed_exams / $total_exams) * 100, 1) : 0;

// Function to get grade info
function getGradeInfoStudent($percentage) {
    if ($percentage === null) {
        return ['grade' => '-', 'status' => 'Not Taken', 'color' => 'bg-gray-100 text-gray-500'];
    }
    if ($percentage >= 75) {
        return ['grade' => 'A', 'status' => 'PASS', 'color' => 'bg-green-100 text-green-700', 'textColor' => 'text-green-600'];
    } elseif ($percentage >= 65) {
        return ['grade' => 'B', 'status' => 'PASS', 'color' => 'bg-blue-100 text-blue-700', 'textColor' => 'text-blue-600'];
    } elseif ($percentage >= 45) {
        return ['grade' => 'C', 'status' => 'PASS', 'color' => 'bg-cyan-100 text-cyan-700', 'textColor' => 'text-cyan-600'];
    } elseif ($percentage >= 30) {
        return ['grade' => 'D', 'status' => 'PASS', 'color' => 'bg-yellow-100 text-yellow-700', 'textColor' => 'text-yellow-600'];
    } else {
        return ['grade' => 'F', 'status' => 'FAIL', 'color' => 'bg-red-100 text-red-700', 'textColor' => 'text-red-600'];
    }
}

// Get subject-wise performance
$subject_performance = $conn->prepare("
    SELECT sub.name as subject_name,
           AVG(es.percentage) as avg_percentage,
           COUNT(es.id) as exam_count,
           MAX(es.percentage) as best_score,
           MIN(es.percentage) as worst_score
    FROM exam_submissions es
    JOIN teacher_exams te ON es.exam_id = te.id
    JOIN subjects sub ON te.subject_id = sub.id
    WHERE es.student_id = ?
    GROUP BY sub.id
    ORDER BY avg_percentage DESC
");
$subject_performance->bind_param("i", $student_id);
$subject_performance->execute();
$subject_stats = $subject_performance->get_result();

// Get class ranking
$rank_query = $conn->prepare("
    SELECT COUNT(DISTINCT es2.student_id) + 1 as position
    FROM exam_submissions es
    JOIN exam_submissions es2 ON es2.exam_id = es.exam_id
    WHERE es.student_id = ? AND es2.percentage > es.percentage
    ORDER BY es.submitted_at DESC LIMIT 1
");
$rank_query->bind_param("i", $student_id);
$rank_query->execute();
$class_rank = $rank_query->get_result()->fetch_assoc()['position'] ?? 1;
?>

<div class="ml-64 mt-16 p-6 bg-gray-50 min-h-screen">
    <div class="max-w-full mx-auto">
        <!-- Profile Header -->
        <div class="bg-gradient-to-r from-blue-600 to-purple-600 rounded-2xl p-6 mb-6 text-white">
            <div class="flex justify-between items-center">
                <div>
                    <h1 class="text-2xl font-bold">My Academic Results</h1>
                    <p class="text-blue-100 mt-1"><?php echo htmlspecialchars($student['student_name']); ?> - <?php echo htmlspecialchars($class_name); ?></p>
                    <p class="text-blue-100 text-sm">Admission: <?php echo $student['admission_number']; ?></p>
                </div>
                <div class="text-right">
                    <p class="text-sm opacity-75">Overall Performance</p>
                    <p class="text-3xl font-bold <?php echo $overall_avg >= 75 ? 'text-green-300' : 'text-yellow-300'; ?>">
                        <?php echo $overall_avg; ?>%
                    </p>
                    <p class="text-sm opacity-75">Class Rank: #<?php echo $class_rank; ?></p>
                </div>
            </div>
        </div>

        <!-- Overall Stats Cards -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4 mb-6">
            <div class="bg-white rounded-xl p-4 shadow-sm">
                <p class="text-gray-500 text-sm">Exams Taken</p>
                <p class="text-2xl font-bold text-blue-600"><?php echo $total_exams; ?></p>
            </div>
            <div class="bg-white rounded-xl p-4 shadow-sm">
                <p class="text-gray-500 text-sm">Average Score</p>
                <p class="text-2xl font-bold <?php echo $overall_avg >= 75 ? 'text-green-600' : ($overall_avg >= 50 ? 'text-yellow-600' : 'text-red-600'); ?>">
                    <?php echo $overall_avg; ?>%
                </p>
            </div>
            <div class="bg-white rounded-xl p-4 shadow-sm">
                <p class="text-gray-500 text-sm">Passed</p>
                <p class="text-2xl font-bold text-green-600"><?php echo $passed_exams; ?></p>
            </div>
            <div class="bg-white rounded-xl p-4 shadow-sm">
                <p class="text-gray-500 text-sm">Failed</p>
                <p class="text-2xl font-bold text-red-600"><?php echo $failed_exams; ?></p>
            </div>
            <div class="bg-white rounded-xl p-4 shadow-sm">
                <p class="text-gray-500 text-sm">Pass Rate</p>
                <p class="text-2xl font-bold text-purple-600"><?php echo $pass_rate; ?>%</p>
            </div>
        </div>

        <!-- Subject Performance -->
        <?php if ($subject_stats && $subject_stats->num_rows > 0): ?>
        <div class="bg-white rounded-xl shadow-sm overflow-hidden mb-6">
            <div class="px-6 py-4 border-b bg-gray-50">
                <h3 class="text-lg font-semibold">📊 Subject Performance</h3>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-gray-50 border-b">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Subject</th>
                            <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Exams</th>
                            <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Average</th>
                            <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Best</th>
                            <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Worst</th>
                            <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Grade</th>
                            <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <?php while($subject = $subject_stats->fetch_assoc()): 
                            $gradeInfo = getGradeInfoStudent($subject['avg_percentage']);
                        ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-4 py-3 font-medium"><?php echo htmlspecialchars($subject['subject_name']); ?></td>
                                <td class="px-4 py-3 text-center"><?php echo $subject['exam_count']; ?></td>
                                <td class="px-4 py-3 text-center font-semibold <?php echo $subject['avg_percentage'] >= 75 ? 'text-green-600' : 'text-red-600'; ?>">
                                    <?php echo round($subject['avg_percentage'], 1); ?>%
                                </td>
                                <td class="px-4 py-3 text-center text-green-600"><?php echo round($subject['best_score'], 1); ?>%</td>
                                <td class="px-4 py-3 text-center text-red-600"><?php echo round($subject['worst_score'], 1); ?>%</td>
                                <td class="px-4 py-3 text-center">
                                    <span class="px-3 py-1 text-sm font-bold rounded-full <?php echo $gradeInfo['color']; ?>">
                                        <?php echo $gradeInfo['grade']; ?>
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <span class="px-3 py-1 text-sm font-bold rounded-full <?php echo $subject['avg_percentage'] >= 75 ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'; ?>">
                                        <?php echo $subject['avg_percentage'] >= 75 ? '✅ PASS' : '❌ FAIL'; ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>

        <!-- Exam Results Table -->
        <div class="bg-white rounded-xl shadow-sm overflow-hidden">
            <div class="px-6 py-4 border-b bg-gray-50 flex justify-between items-center">
                <h3 class="text-lg font-semibold">📋 Exam Results</h3>
                <span class="text-sm text-gray-500">Showing <?php echo count($exams_data); ?> exams</span>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-gray-50 border-b">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">#</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Exam</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Subject</th>
                            <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Score</th>
                            <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Percentage</th>
                            <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Grade</th>
                            <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Status</th>
                            <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Class Avg</th>
                            <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Rank</th>
                            <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <?php 
                        $counter = 1;
                        foreach($exams_data as $exam): 
                            $gradeInfo = getGradeInfoStudent($exam['percentage']);
                            $is_pass = $exam['percentage'] >= 75;
                            $class_avg = round($exam['class_average'] ?? 0, 1);
                            $rank_pos = $exam['rank_position'] ?? '-';
                        ?>
                            <tr class="hover:bg-gray-50 transition-all <?php echo $is_pass ? 'bg-green-50' : 'bg-red-50'; ?>">
                                <td class="px-4 py-3 text-sm"><?php echo $counter++; ?></td>
                                <td class="px-4 py-3 font-medium"><?php echo htmlspecialchars($exam['exam_title']); ?></td>
                                <td class="px-4 py-3"><?php echo htmlspecialchars($exam['subject_name']); ?></td>
                                <td class="px-4 py-3 text-center">
                                    <span class="font-semibold"><?php echo $exam['total_score']; ?> / <?php echo $exam['exam_total']; ?></span>
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <span class="font-bold <?php echo $is_pass ? 'text-green-600' : 'text-red-600'; ?>">
                                        <?php echo round($exam['percentage'], 1); ?>%
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <span class="px-3 py-1 text-sm font-bold rounded-full <?php echo $gradeInfo['color']; ?>">
                                        <?php echo $exam['grade']; ?>
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <span class="px-3 py-1 text-sm font-bold rounded-full <?php echo $is_pass ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'; ?>">
                                        <?php echo $is_pass ? '✅ PASS' : '❌ FAIL'; ?>
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <span class="text-sm <?php echo $exam['percentage'] >= ($class_avg) ? 'text-green-600' : 'text-red-600'; ?>">
                                        <?php echo $class_avg; ?>%
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <span class="px-3 py-1 text-sm rounded-full <?php echo $rank_pos <= 3 ? 'bg-yellow-100 text-yellow-700' : 'bg-gray-100 text-gray-700'; ?>">
                                        #<?php echo $rank_pos; ?>
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <a href="../exams/results.php?id=<?php echo $exam['exam_id']; ?>" 
                                       class="text-blue-600 hover:text-blue-800 text-sm" title="View Details">
                                        <i class="fas fa-eye"></i>
                                    </a>
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
            <p class="text-xs text-gray-500 mt-2">* Passing mark is 75% and above (Grade A). Any score below 75% is considered FAIL.</p>
        </div>

        <div class="mt-4 flex justify-end">
            <button onclick="window.print()" class="bg-gray-600 text-white px-4 py-2 rounded-lg hover:bg-gray-700">
                <i class="fas fa-print mr-2"></i> Print Results
            </button>
        </div>
    </div>
</div>

<style>
@media print {
    .ml-64, .sidebar, .navbar, .no-print {
        display: none !important;
    }
    .ml-64 {
        margin-left: 0 !important;
    }
    body {
        background: white;
    }
}
</style>

<?php include '../../includes/footer.php'; ?>