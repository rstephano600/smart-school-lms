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

// Get exam details
$exam_query = $conn->prepare("
    SELECT te.*, s.name as subject_name, c.name as class_name
    FROM teacher_exams te
    JOIN subjects s ON te.subject_id = s.id
    JOIN classes c ON te.class_id = c.id
    WHERE te.id = ? AND te.teacher_id = (SELECT id FROM teachers WHERE user_id = ?)
");
$exam_query->bind_param("ii", $exam_id, $_SESSION['user_id']);
$exam_query->execute();
$exam = $exam_query->get_result()->fetch_assoc();

if (!$exam) {
    header('Location: index.php');
    exit();
}

$page_title = 'Submissions - ' . $exam['title'];
include '../../includes/header.php';
include '../../includes/sidebar.php';
include '../../includes/navbar.php';

// Get submissions
$submissions = $conn->prepare("
    SELECT es.*, 
           CONCAT(u.first_name, ' ', u.last_name) as student_name,
           s.admission_number
    FROM exam_submissions es
    JOIN students s ON es.student_id = s.id
    JOIN users u ON s.user_id = u.id
    WHERE es.exam_id = ?
    ORDER BY es.submitted_at DESC
");
$submissions->bind_param("i", $exam_id);
$submissions->execute();
$submissions = $submissions->get_result();

// Calculate statistics
$stats = [
    'total' => 0,
    'submitted' => 0,
    'graded' => 0,
    'avg_score' => 0,
    'passed' => 0,
    'failed' => 0
];

$scores = [];
while ($sub = $submissions->fetch_assoc()) {
    $stats['total']++;
    if ($sub['submitted_at']) $stats['submitted']++;
    if ($sub['is_graded']) {
        $stats['graded']++;
        $scores[] = $sub['percentage'];
        if ($sub['percentage'] >= $exam['passing_marks']) $stats['passed']++;
        else $stats['failed']++;
    }
}

$stats['avg_score'] = count($scores) > 0 ? round(array_sum($scores) / count($scores), 1) : 0;
$submissions->data_seek(0);
?>

<div class="ml-64 mt-16 p-6">
    <div class="max-w-full mx-auto">
        <div class="mb-6">
            <h1 class="text-2xl font-bold text-gray-800">Exam Submissions</h1>
            <p class="text-gray-500 mt-1"><?php echo htmlspecialchars($exam['title']); ?> - <?php echo htmlspecialchars($exam['class_name']); ?></p>
        </div>

        <!-- Statistics Cards -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
            <div class="bg-white rounded-xl p-4 shadow-sm">
                <p class="text-gray-500 text-sm">Total Students</p>
                <p class="text-2xl font-bold"><?php echo $stats['total']; ?></p>
            </div>
            <div class="bg-white rounded-xl p-4 shadow-sm">
                <p class="text-gray-500 text-sm">Submitted</p>
                <p class="text-2xl font-bold text-green-600"><?php echo $stats['submitted']; ?></p>
            </div>
            <div class="bg-white rounded-xl p-4 shadow-sm">
                <p class="text-gray-500 text-sm">Graded</p>
                <p class="text-2xl font-bold text-blue-600"><?php echo $stats['graded']; ?></p>
            </div>
            <div class="bg-white rounded-xl p-4 shadow-sm">
                <p class="text-gray-500 text-sm">Average Score</p>
                <p class="text-2xl font-bold"><?php echo $stats['avg_score']; ?>%</p>
            </div>
        </div>

        <!-- Submissions Table -->
        <div class="bg-white rounded-xl shadow-sm overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-gray-50 border-b">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Student</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Admission No</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Submitted At</th>
                            <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">Score</th>
                            <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">Percentage</th>
                            <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">Status</th>
                            <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <?php if ($submissions && $submissions->num_rows > 0): ?>
                            <?php while($sub = $submissions->fetch_assoc()): ?>
                                <tr class="hover:bg-gray-50">
                                    <td class="px-6 py-4 font-medium"><?php echo htmlspecialchars($sub['student_name']); ?></td>
                                    <td class="px-6 py-4"><?php echo $sub['admission_number']; ?></td>
                                    <td class="px-6 py-4">
                                        <?php echo $sub['submitted_at'] ? date('M d, Y h:i A', strtotime($sub['submitted_at'])) : 'Not submitted'; ?>
                                    </td>
                                    <td class="px-6 py-4 text-center">
                                        <?php echo $sub['total_score'] !== null ? $sub['total_score'] . ' / ' . $exam['total_marks'] : '-'; ?>
                                    </td>
                                    <td class="px-6 py-4 text-center">
                                        <?php if($sub['percentage'] !== null): ?>
                                            <span class="font-semibold <?php echo $sub['percentage'] >= $exam['passing_marks'] ? 'text-green-600' : 'text-red-600'; ?>">
                                                <?php echo $sub['percentage']; ?>%
                                            </span>
                                        <?php else: ?>
                                            -
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-6 py-4 text-center">
                                        <?php if($sub['is_graded']): ?>
                                            <span class="px-2 py-1 text-xs rounded-full bg-green-100 text-green-700">Graded</span>
                                        <?php elseif($sub['submitted_at']): ?>
                                            <span class="px-2 py-1 text-xs rounded-full bg-yellow-100 text-yellow-700">Pending</span>
                                        <?php else: ?>
                                            <span class="px-2 py-1 text-xs rounded-full bg-gray-100 text-gray-700">Not Started</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-6 py-4 text-center">
                                        <?php if($sub['submitted_at'] && !$sub['is_graded']): ?>
                                            <a href="grade.php?id=<?php echo $sub['id']; ?>&exam_id=<?php echo $exam_id; ?>" 
                                               class="bg-blue-600 text-white px-3 py-1 rounded-lg text-sm hover:bg-blue-700">
                                                Grade
                                            </a>
                                        <?php elseif($sub['is_graded']): ?>
                                            <a href="view-submission.php?id=<?php echo $sub['id']; ?>" 
                                               class="text-blue-600 hover:text-blue-800">
                                                <i class="fas fa-eye"></i> View
                                            </a>
                                        <?php else: ?>
                                            -
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" class="px-6 py-12 text-center text-gray-500">
                                    <i class="fas fa-inbox text-4xl mb-2 block"></i>
                                    No submissions yet
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>