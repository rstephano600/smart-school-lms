<?php
require_once '../../config.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';
requireRole('student');

$assignment_id = $_GET['id'] ?? 0;

if (!$assignment_id) {
    header('Location: index.php');
    exit();
}

// Get student ID
$student_query = $conn->prepare("SELECT id FROM students WHERE user_id = ?");
$student_query->bind_param("i", $_SESSION['user_id']);
$student_query->execute();
$student = $student_query->get_result()->fetch_assoc();
$student_id = $student['id'];

// Get submission with feedback
$submission_query = $conn->prepare("
    SELECT s.*, a.title as assignment_title, a.max_marks,
           CONCAT(u.first_name, ' ', u.last_name) as teacher_name
    FROM submissions s
    JOIN assignments a ON s.assignment_id = a.id
    LEFT JOIN users u ON s.graded_by = u.id
    WHERE s.assignment_id = ? AND s.student_id = ?
");
$submission_query->bind_param("ii", $assignment_id, $student_id);
$submission_query->execute();
$submission = $submission_query->get_result()->fetch_assoc();

if (!$submission || $submission['marks_obtained'] === null) {
    header('Location: index.php');
    exit();
}

$percentage = ($submission['marks_obtained'] / $submission['max_marks']) * 100;
if ($percentage >= 80) $grade = 'A';
elseif ($percentage >= 70) $grade = 'B';
elseif ($percentage >= 60) $grade = 'C';
elseif ($percentage >= 50) $grade = 'D';
elseif ($percentage >= 40) $grade = 'E';
else $grade = 'F';

$page_title = 'Feedback - ' . $submission['assignment_title'];
include '../../includes/header.php';
include '../../includes/sidebar.php';
include '../../includes/navbar.php';
?>

<div class="ml-64 mt-16 p-6 bg-gray-50 min-h-screen">
    <div class="max-w-3xl mx-auto">
        <div class="bg-white rounded-xl shadow-sm overflow-hidden">
            <div class="bg-gradient-to-r from-green-500 to-teal-600 p-6 text-white">
                <h1 class="text-2xl font-bold">Assignment Feedback</h1>
                <p class="text-green-100 mt-1"><?php echo htmlspecialchars($submission['assignment_title']); ?></p>
            </div>
            
            <div class="p-6">
                <!-- Score Overview -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
                    <div class="text-center p-4 bg-gray-50 rounded-xl">
                        <p class="text-gray-500 text-sm">Your Score</p>
                        <p class="text-2xl font-bold text-blue-600"><?php echo $submission['marks_obtained']; ?> / <?php echo $submission['max_marks']; ?></p>
                    </div>
                    <div class="text-center p-4 bg-gray-50 rounded-xl">
                        <p class="text-gray-500 text-sm">Percentage</p>
                        <p class="text-2xl font-bold <?php echo $percentage >= 50 ? 'text-green-600' : 'text-red-600'; ?>">
                            <?php echo round($percentage, 1); ?>%
                        </p>
                    </div>
                    <div class="text-center p-4 bg-gray-50 rounded-xl">
                        <p class="text-gray-500 text-sm">Grade</p>
                        <p class="text-2xl font-bold text-purple-600"><?php echo $grade; ?></p>
                    </div>
                </div>

                <!-- Graded By -->
                <div class="mb-4 text-sm text-gray-500">
                    <i class="fas fa-user-check mr-1"></i> Graded by: <?php echo htmlspecialchars($submission['teacher_name'] ?? 'Teacher'); ?>
                    on <?php echo date('M d, Y h:i A', strtotime($submission['graded_at'])); ?>
                </div>

                <!-- Teacher Feedback -->
                <?php if($submission['feedback']): ?>
                <div class="mb-6 p-4 bg-blue-50 rounded-lg border border-blue-200">
                    <h3 class="font-semibold text-blue-800 mb-2">📝 Teacher's Feedback</h3>
                    <p class="text-gray-700"><?php echo nl2br(htmlspecialchars($submission['feedback'])); ?></p>
                </div>
                <?php endif; ?>

                <!-- Your Submission -->
                <div class="mb-6">
                    <h3 class="text-lg font-semibold text-gray-800 mb-2">📄 Your Submission</h3>
                    <?php if($submission['submission_text']): ?>
                        <div class="bg-gray-50 p-4 rounded-lg">
                            <?php echo nl2br(htmlspecialchars($submission['submission_text'])); ?>
                        </div>
                    <?php endif; ?>
                    <?php if($submission['attachment_url']): ?>
                        <div class="mt-3">
                            <a href="../../<?php echo $submission['attachment_url']; ?>" target="_blank" 
                               class="inline-flex items-center text-blue-600 hover:text-blue-800">
                                <i class="fas fa-file-download mr-1"></i> Download Your Submission
                            </a>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="flex justify-end">
                    <a href="index.php" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                        <i class="fas fa-arrow-left mr-2"></i> Back to Assignments
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>