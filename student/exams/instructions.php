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
$student_query = $conn->prepare("SELECT id, class_id FROM students WHERE user_id = ?");
$student_query->bind_param("i", $_SESSION['user_id']);
$student_query->execute();
$student = $student_query->get_result()->fetch_assoc();
$student_id = $student['id'] ?? 0;
$class_id = $student['class_id'] ?? 0;

if (!$student_id) {
    header('Location: index.php?error=Student not found');
    exit();
}

// Check if already submitted
$check = $conn->prepare("SELECT id FROM exam_submissions WHERE exam_id = ? AND student_id = ?");
$check->bind_param("ii", $exam_id, $student_id);
$check->execute();
$existing = $check->get_result()->fetch_assoc();

if ($existing) {
    header("Location: results.php?id=" . $exam_id);
    exit();
}

// Get exam details
$exam_query = $conn->prepare("
    SELECT te.*, s.name as subject_name
    FROM teacher_exams te
    JOIN subjects s ON te.subject_id = s.id
    WHERE te.id = ? 
    AND te.class_id = ?
    AND te.is_published = 1
");
$exam_query->bind_param("ii", $exam_id, $class_id);
$exam_query->execute();
$exam = $exam_query->get_result()->fetch_assoc();

if (!$exam) {
    header('Location: index.php?error=Exam not found');
    exit();
}

// Check if exam is active
$today = date('Y-m-d');
if ($exam['start_date'] > $today) {
    header('Location: index.php?error=Exam has not started yet');
    exit();
}
if ($exam['end_date'] < $today) {
    header('Location: index.php?error=Exam has expired');
    exit();
}

// Get questions count
$questions_query = $conn->prepare("SELECT COUNT(*) as count FROM exam_questions WHERE exam_id = ?");
$questions_query->bind_param("i", $exam_id);
$questions_query->execute();
$question_count = $questions_query->get_result()->fetch_assoc()['count'] ?? 0;

if ($question_count == 0) {
    header('Location: index.php?error=No questions found');
    exit();
}

$page_title = 'Exam Instructions - ' . $exam['title'];
include '../../includes/header.php';
include '../../includes/sidebar.php';
include '../../includes/navbar.php';
?>

<style>
.instruction-card {
    background: white;
    border-radius: 16px;
    padding: 30px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.08);
}
.instruction-item {
    display: flex;
    align-items: flex-start;
    gap: 12px;
    padding: 10px 0;
    border-bottom: 1px solid #f3f4f6;
}
.instruction-item:last-child {
    border-bottom: none;
}
.instruction-icon {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    font-size: 14px;
}
.instruction-icon.green {
    background: #d1fae5;
    color: #065f46;
}
.instruction-icon.blue {
    background: #dbeafe;
    color: #1e40af;
}
.instruction-icon.yellow {
    background: #fef3c7;
    color: #92400e;
}
.instruction-icon.red {
    background: #fee2e2;
    color: #991b1b;
}
.instruction-icon.purple {
    background: #ede9fe;
    color: #5b21b6;
}
</style>

<div class="ml-64 mt-16 p-6 bg-gray-50 min-h-screen">
    <div class="max-w-3xl mx-auto">
        <!-- Exam Info -->
        <div class="bg-gradient-to-r from-blue-600 to-purple-600 rounded-2xl p-6 mb-6 text-white">
            <h1 class="text-2xl font-bold">📝 <?php echo htmlspecialchars($exam['title']); ?></h1>
            <p class="text-blue-100 mt-1"><?php echo htmlspecialchars($exam['subject_name']); ?></p>
            <div class="mt-3 flex flex-wrap gap-3">
                <div class="bg-white bg-opacity-20 rounded-lg px-3 py-1 text-sm">
                    <i class="fas fa-clock mr-1"></i> <?php echo $exam['duration_minutes']; ?> minutes
                </div>
                <div class="bg-white bg-opacity-20 rounded-lg px-3 py-1 text-sm">
                    <i class="fas fa-question-circle mr-1"></i> <?php echo $question_count; ?> questions
                </div>
                <div class="bg-white bg-opacity-20 rounded-lg px-3 py-1 text-sm">
                    <i class="fas fa-star mr-1"></i> <?php echo $exam['total_marks']; ?> marks
                </div>
            </div>
        </div>

        <!-- Instructions -->
        <div class="instruction-card mb-6">
            <h2 class="text-xl font-bold text-gray-800 mb-4">📋 Exam Instructions</h2>
            
            <div class="instruction-item">
                <div class="instruction-icon blue">
                    <i class="fas fa-clock"></i>
                </div>
                <div>
                    <p class="font-medium text-gray-800">Time Limit</p>
                    <p class="text-sm text-gray-600">You have <strong><?php echo $exam['duration_minutes']; ?> minutes</strong> to complete this exam. The timer will start when you begin.</p>
                </div>
            </div>
            
            <div class="instruction-item">
                <div class="instruction-icon green">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div>
                    <p class="font-medium text-gray-800">Answer All Questions</p>
                    <p class="text-sm text-gray-600">There are <strong><?php echo $question_count; ?> questions</strong> worth a total of <strong><?php echo $exam['total_marks']; ?> marks</strong>. Attempt all questions.</p>
                </div>
            </div>
            
            <div class="instruction-item">
                <div class="instruction-icon yellow">
                    <i class="fas fa-undo-alt"></i>
                </div>
                <div>
                    <p class="font-medium text-gray-800">No Backtracking</p>
                    <p class="text-sm text-gray-600">Once you move to the next question, you cannot go back. Answer each question carefully.</p>
                </div>
            </div>
            
            <div class="instruction-item">
                <div class="instruction-icon red">
                    <i class="fas fa-exclamation-triangle"></i>
                </div>
                <div>
                    <p class="font-medium text-gray-800">Important Notes</p>
                    <p class="text-sm text-gray-600">
                        • Do not refresh the page during the exam.<br>
                        • Your answers will be auto-saved when you submit.<br>
                        • Once submitted, you cannot retake the exam.
                    </p>
                </div>
            </div>
            
            <div class="instruction-item">
                <div class="instruction-icon purple">
                    <i class="fas fa-robot"></i>
                </div>
                <div>
                    <p class="font-medium text-gray-800">Auto-Grading</p>
                    <p class="text-sm text-gray-600">This exam will be <strong>automatically graded</strong> after submission. Results will be available immediately.</p>
                </div>
            </div>
            
            <?php if($exam['instructions']): ?>
                <div class="instruction-item">
                    <div class="instruction-icon blue">
                        <i class="fas fa-info-circle"></i>
                    </div>
                    <div>
                        <p class="font-medium text-gray-800">Teacher's Instructions</p>
                        <p class="text-sm text-gray-600"><?php echo nl2br(htmlspecialchars($exam['instructions'])); ?></p>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <!-- Start Button -->
        <div class="flex justify-between items-center">
            <a href="index.php" class="text-gray-600 hover:text-gray-800">
                <i class="fas fa-arrow-left mr-2"></i> Back to Exams
            </a>
            <a href="take.php?id=<?php echo $exam_id; ?>" 
               class="bg-gradient-to-r from-green-500 to-teal-600 text-white px-8 py-3 rounded-xl hover:shadow-lg transition text-lg font-semibold">
                <i class="fas fa-play mr-2"></i> Start Exam
            </a>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>