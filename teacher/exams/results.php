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

$page_title = 'Exam Results - ' . $exam['title'];
include '../../includes/header.php';
include '../../includes/sidebar.php';
include '../../includes/navbar.php';

// Get all students and their results
$results_query = $conn->prepare("
    SELECT 
        s.id as student_id,
        s.admission_number,
        CONCAT(u.first_name, ' ', u.last_name) as student_name,
        es.total_score,
        es.percentage,
        es.grade,
        es.submitted_at,
        es.is_graded,
        es.feedback,
        es.results_data
    FROM students s
    JOIN users u ON s.user_id = u.id
    LEFT JOIN exam_submissions es ON s.id = es.student_id AND es.exam_id = ?
    WHERE s.class_id = ?
    ORDER BY COALESCE(es.percentage, -1) DESC, u.first_name ASC
");
$results_query->bind_param("ii", $exam_id, $exam['class_id']);
$results_query->execute();
$results = $results_query->get_result();

// Calculate statistics
$total_students = 0;
$submitted_count = 0;
$passed_count = 0;
$failed_count = 0;
$total_percentage = 0;
$students_data = [];

while ($row = $results->fetch_assoc()) {
    $total_students++;
    $students_data[] = $row;
    if ($row['submitted_at']) {
        $submitted_count++;
        if ($row['percentage'] !== null) {
            $total_percentage += $row['percentage'];
            if ($row['percentage'] >= 75) {
                $passed_count++;
            } else {
                $failed_count++;
            }
        }
    }
}

$avg_percentage = $submitted_count > 0 ? round($total_percentage / $submitted_count, 1) : 0;
$pass_rate = $submitted_count > 0 ? round(($passed_count / $submitted_count) * 100, 1) : 0;

// Function to get grade and status
function getGradeInfo($percentage) {
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
?>

<div class="ml-64 mt-16 p-6 bg-gray-50 min-h-screen">
    <div class="max-w-full mx-auto">
        <div class="mb-6 flex justify-between items-center">
            <div>
                <h1 class="text-2xl font-bold text-gray-800">Exam Results</h1>
                <p class="text-gray-500 mt-1"><?php echo htmlspecialchars($exam['title']); ?> - <?php echo htmlspecialchars($exam['class_name']); ?></p>
                <p class="text-sm text-gray-400"><?php echo htmlspecialchars($exam['subject_name']); ?> • <?php echo $exam['total_marks']; ?> marks</p>
            </div>
            <div class="flex space-x-2">
                <button onclick="window.print()" class="bg-gray-600 text-white px-4 py-2 rounded-lg hover:bg-gray-700">
                    <i class="fas fa-print mr-2"></i> Print
                </button>
                <a href="export-results.php?id=<?php echo $exam_id; ?>" class="bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700">
                    <i class="fas fa-file-excel mr-2"></i> Export
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

        <!-- Results Table -->
        <div class="bg-white rounded-xl shadow-sm overflow-hidden">
            <div class="px-6 py-4 border-b bg-gray-50 flex justify-between items-center">
                <h3 class="text-lg font-semibold">Student Results</h3>
                <div class="flex items-center space-x-4 text-sm">
                    <span class="px-2 py-1 bg-green-100 text-green-700 rounded">🟢 Pass (≥75%)</span>
                    <span class="px-2 py-1 bg-red-100 text-red-700 rounded">🔴 Fail (&lt;75%)</span>
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
                            <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <?php 
                        $rank = 1;
                        $prev_score = -1;
                        $rank_counter = 1;
                        foreach($students_data as $index => $student): 
                            $gradeInfo = getGradeInfo($student['percentage']);
                            $is_submitted = $student['submitted_at'] ? true : false;
                            
                            // Calculate rank (skip if not submitted)
                            if ($is_submitted && $student['percentage'] !== null) {
                                if ($student['percentage'] != $prev_score) {
                                    $rank = $rank_counter;
                                }
                                $rank_counter++;
                                $prev_score = $student['percentage'];
                            } else {
                                $rank_display = '-';
                            }
                            
                            $rank_display = $is_submitted ? $rank : '-';
                            $row_class = $is_submitted ? ($student['percentage'] >= 75 ? 'bg-green-50' : 'bg-red-50') : '';
                        ?>
                            <tr class="hover:bg-gray-50 transition-all <?php echo $row_class; ?>">
                                <td class="px-4 py-3 text-sm"><?php echo $index + 1; ?></td>
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
                                        <span class="font-bold <?php echo $student['percentage'] >= 75 ? 'text-green-600' : 'text-red-600'; ?>">
                                            <?php echo $student['percentage']; ?>%
                                        </span>
                                    <?php else: ?>
                                        <span class="text-gray-400">-</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <?php if ($is_submitted && $student['grade']): ?>
                                        <span class="px-3 py-1 text-sm font-bold rounded-full <?php echo $gradeInfo['color']; ?>">
                                            <?php echo $student['grade']; ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-gray-400">-</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <?php if ($is_submitted && $student['percentage'] !== null): ?>
                                        <span class="px-3 py-1 text-sm font-bold rounded-full <?php echo $student['percentage'] >= 75 ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'; ?>">
                                            <?php echo $student['percentage'] >= 75 ? '✅ PASS' : '❌ FAIL'; ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="px-3 py-1 text-sm rounded-full bg-gray-100 text-gray-500">Not Taken</span>
                                    <?php endif; ?>
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
                                <td class="px-4 py-3 text-center">
                                    <?php if ($is_submitted): ?>
                                        <a href="view-submission.php?id=<?php echo $student['student_id']; ?>&exam_id=<?php echo $exam_id; ?>" 
                                           class="text-blue-600 hover:text-blue-800 text-sm" title="View Details">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <?php if (!$student['is_graded']): ?>
                                            <a href="../grade.php?id=<?php echo $student['student_id']; ?>&exam_id=<?php echo $exam_id; ?>" 
                                               class="text-green-600 hover:text-green-800 text-sm ml-2" title="Grade">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="text-gray-400 text-sm">-</span>
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
            <p class="text-xs text-gray-500 mt-2">* Passing mark is 75% and above (Grade A). Any score below 75% is considered FAIL.</p>
        </div>

        <!-- Back Button -->
        <div class="mt-4 flex justify-end">
            <a href="index.php" class="text-blue-600 hover:text-blue-800">
                <i class="fas fa-arrow-left mr-2"></i> Back to Exams
            </a>
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