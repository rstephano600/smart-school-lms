<?php
require_once '../../config.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';
requireRole('student');

$exam_id = $_GET['id'] ?? 0;

if (!$exam_id) {
    header('Location: index.php');
    exit();
}

// Get student ID
$student_query = $conn->prepare("SELECT id FROM students WHERE user_id = ?");
$student_query->bind_param("i", $_SESSION['user_id']);
$student_query->execute();
$student_id = $student_query->get_result()->fetch_assoc()['id'];

// Get submission
$submission_query = $conn->prepare("
    SELECT es.*, te.title as exam_title, te.total_marks as exam_total, te.subject_id,
           s.name as subject_name
    FROM exam_submissions es
    JOIN teacher_exams te ON es.exam_id = te.id
    JOIN subjects s ON te.subject_id = s.id
    WHERE es.exam_id = ? AND es.student_id = ?
    ORDER BY es.submitted_at DESC LIMIT 1
");
$submission_query->bind_param("ii", $exam_id, $student_id);
$submission_query->execute();
$submission = $submission_query->get_result()->fetch_assoc();

if (!$submission) {
    header('Location: index.php');
    exit();
}

$page_title = 'Exam Results - ' . $submission['exam_title'];
include '../../includes/header.php';
include '../../includes/sidebar.php';
include '../../includes/navbar.php';
?>

<div class="ml-64 mt-16 p-6 bg-gray-50 min-h-screen">
    <div class="max-w-4xl mx-auto">
        <!-- Result Card -->
        <div class="bg-white rounded-2xl shadow-sm overflow-hidden mb-6">
            <div class="bg-gradient-to-r from-green-500 to-teal-600 p-6 text-white text-center">
                <i class="fas fa-check-circle text-5xl mb-3"></i>
                <h1 class="text-2xl font-bold">Exam Completed!</h1>
                <p class="text-green-100 mt-1"><?php echo htmlspecialchars($submission['exam_title']); ?> - <?php echo htmlspecialchars($submission['subject_name']); ?></p>
            </div>
            
            <div class="p-6">
                <!-- Score Overview -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
                    <div class="text-center p-4 bg-gray-50 rounded-xl">
                        <p class="text-gray-500 text-sm">Your Score</p>
                        <p class="text-3xl font-bold text-blue-600"><?php echo $submission['total_score']; ?> / <?php echo $submission['exam_total']; ?></p>
                    </div>
                    <div class="text-center p-4 bg-gray-50 rounded-xl">
                        <p class="text-gray-500 text-sm">Percentage</p>
                        <p class="text-3xl font-bold <?php echo $submission['percentage'] >= 50 ? 'text-green-600' : 'text-red-600'; ?>">
                            <?php echo round($submission['percentage'], 1); ?>%
                        </p>
                    </div>
                    <div class="text-center p-4 bg-gray-50 rounded-xl">
                        <p class="text-gray-500 text-sm">Grade</p>
                        <p class="text-3xl font-bold text-purple-600"><?php echo $submission['grade']; ?></p>
                    </div>
                </div>

                <div class="text-center">
                    <a href="index.php" class="inline-block bg-blue-600 text-white px-6 py-2 rounded-lg hover:bg-blue-700">
                        <i class="fas fa-arrow-left mr-2"></i> Back to Exams
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>