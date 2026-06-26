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

// Get exam details
$exam_query = $conn->prepare("
    SELECT te.*, s.name as subject_name,
           (SELECT COUNT(*) FROM exam_questions WHERE exam_id = te.id) as total_questions
    FROM teacher_exams te
    JOIN subjects s ON te.subject_id = s.id
    WHERE te.id = ? AND te.is_published = 1
");
$exam_query->bind_param("i", $exam_id);
$exam_query->execute();
$exam = $exam_query->get_result()->fetch_assoc();

if (!$exam) {
    header('Location: index.php');
    exit();
}

// Check if exam is available
$today = date('Y-m-d');
if ($exam['start_date'] > $today) {
    header('Location: index.php?error=Exam not started yet');
    exit();
}
if ($exam['end_date'] < $today) {
    header('Location: index.php?error=Exam has expired');
    exit();
}

// Check if already completed
$check_completed = $conn->prepare("
    SELECT id FROM exam_submissions 
    WHERE exam_id = ? AND student_id = ?
");
$check_completed->bind_param("ii", $exam_id, $student_id);
$check_completed->execute();
if ($check_completed->get_result()->num_rows > 0) {
    header('Location: results.php?id=' . $exam_id);
    exit();
}

$page_title = 'Exam Instructions - ' . $exam['title'];
include '../../includes/header.php';
include '../../includes/sidebar.php';
include '../../includes/navbar.php';
?>

<div class="ml-64 mt-16 p-6 bg-gray-50 min-h-screen">
    <div class="max-w-3xl mx-auto">
        <!-- Exam Header -->
        <div class="bg-gradient-to-r from-blue-600 to-purple-600 rounded-2xl p-6 mb-6 text-white">
            <h1 class="text-2xl font-bold"><?php echo htmlspecialchars($exam['title']); ?></h1>
            <p class="text-blue-100 mt-1"><?php echo htmlspecialchars($exam['subject_name']); ?></p>
            <div class="flex flex-wrap gap-4 mt-4">
                <div class="bg-white bg-opacity-20 rounded-lg px-3 py-1">
                    <i class="fas fa-clock mr-1"></i> Duration: <?php echo $exam['duration_minutes']; ?> minutes
                </div>
                <div class="bg-white bg-opacity-20 rounded-lg px-3 py-1">
                    <i class="fas fa-question-circle mr-1"></i> Questions: <?php echo $exam['total_questions']; ?>
                </div>
                <div class="bg-white bg-opacity-20 rounded-lg px-3 py-1">
                    <i class="fas fa-star mr-1"></i> Total Marks: <?php echo $exam['total_marks']; ?>
                </div>
            </div>
        </div>

        <!-- Instructions Card -->
        <div class="bg-white rounded-xl shadow-sm p-6 mb-6">
            <h2 class="text-xl font-bold text-gray-800 mb-4">📋 Exam Instructions</h2>
            
            <div class="space-y-4">
                <div class="flex items-start space-x-3">
                    <div class="w-6 h-6 bg-blue-100 rounded-full flex items-center justify-center text-blue-600 text-sm font-bold">1</div>
                    <p>Read each question carefully before answering.</p>
                </div>
                <div class="flex items-start space-x-3">
                    <div class="w-6 h-6 bg-blue-100 rounded-full flex items-center justify-center text-blue-600 text-sm font-bold">2</div>
                    <p>Once you submit, you cannot change your answers.</p>
                </div>
                <div class="flex items-start space-x-3">
                    <div class="w-6 h-6 bg-blue-100 rounded-full flex items-center justify-center text-blue-600 text-sm font-bold">3</div>
                    <p>The exam will auto-submit when the timer reaches zero.</p>
                </div>
                <div class="flex items-start space-x-3">
                    <div class="w-6 h-6 bg-blue-100 rounded-full flex items-center justify-center text-blue-600 text-sm font-bold">4</div>
                    <p>Do not refresh the page during the exam.</p>
                </div>
                <div class="flex items-start space-x-3">
                    <div class="w-6 h-6 bg-blue-100 rounded-full flex items-center justify-center text-blue-600 text-sm font-bold">5</div>
                    <p>Make sure you have a stable internet connection.</p>
                </div>
            </div>

            <?php if($exam['instructions']): ?>
                <div class="mt-6 p-4 bg-yellow-50 rounded-lg border border-yellow-200">
                    <h3 class="font-semibold text-yellow-800 mb-2">📝 Additional Instructions</h3>
                    <p class="text-sm text-yellow-700"><?php echo nl2br(htmlspecialchars($exam['instructions'])); ?></p>
                </div>
            <?php endif; ?>
        </div>

        <!-- Start Button -->
        <div class="text-center">
            <form method="POST" action="take.php">
                <input type="hidden" name="exam_id" value="<?php echo $exam_id; ?>">
                <button type="submit" class="bg-gradient-to-r from-green-500 to-teal-600 text-white px-8 py-3 rounded-xl text-lg font-semibold hover:shadow-lg transition-all" onclick="return confirm('Are you ready to start the exam? The timer will begin immediately.');">
                    <i class="fas fa-play mr-2"></i> I Understand, Start Exam Now
                </button>
            </form>
            <p class="text-xs text-gray-400 mt-3">By starting this exam, you agree to follow all instructions</p>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>