<?php
require_once '../../config.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';
requireRole('student');

session_start();

// Get student ID
$student_query = $conn->prepare("SELECT id FROM students WHERE user_id = ?");
$student_query->bind_param("i", $_SESSION['user_id']);
$student_query->execute();
$student_id = $student_query->get_result()->fetch_assoc()['id'];

// Get exam ID from POST
$exam_id = $_POST['exam_id'] ?? $_GET['id'] ?? 0;

if (!$exam_id) {
    header('Location: index.php');
    exit();
}

// Get exam details
$exam_query = $conn->prepare("
    SELECT te.*, 
           (SELECT COUNT(*) FROM exam_questions WHERE exam_id = te.id) as total_questions
    FROM teacher_exams te
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
if ($exam['start_date'] > $today || $exam['end_date'] < $today) {
    header('Location: index.php?error=Exam not available');
    exit();
}

// Check if already submitted
$check_submit = $conn->prepare("
    SELECT id FROM exam_submissions 
    WHERE exam_id = ? AND student_id = ? AND submitted_at IS NOT NULL
");
$check_submit->bind_param("ii", $exam_id, $student_id);
$check_submit->execute();
if ($check_submit->get_result()->num_rows > 0) {
    header('Location: results.php?id=' . $exam_id);
    exit();
}

// Get all questions
$questions = $conn->prepare("
    SELECT * FROM exam_questions 
    WHERE exam_id = ? 
    ORDER BY order_number
");
$questions->bind_param("i", $exam_id);
$questions->execute();
$questions = $questions->get_result();

$page_title = 'Taking Exam - ' . $exam['title'];
include '../../includes/header.php';
include '../../includes/sidebar.php';
include '../../includes/navbar.php';
?>

<div class="ml-64 mt-16 p-4 bg-gray-100 min-h-screen">
    <div class="max-w-4xl mx-auto">
        <!-- Exam Header with Timer -->
        <div class="bg-white rounded-xl shadow-sm p-4 mb-4 sticky top-16 z-10">
            <div class="flex justify-between items-center">
                <div>
                    <h1 class="text-lg font-bold"><?php echo htmlspecialchars($exam['title']); ?></h1>
                    <p class="text-xs text-gray-500">Answer all questions carefully</p>
                </div>
                <div class="text-right">
                    <p class="text-sm text-gray-500">Time Remaining</p>
                    <div class="timer text-2xl font-bold font-mono text-red-600" id="timer">00:00:00</div>
                </div>
            </div>
        </div>

        <!-- Questions Form -->
        <form id="examForm" method="POST" action="submit-exam.php">
            <input type="hidden" name="exam_id" value="<?php echo $exam_id; ?>">
            
            <div class="space-y-4">
                <?php 
                $question_number = 1;
                while($q = $questions->fetch_assoc()): 
                    $options = $q['options'] ? json_decode($q['options'], true) : [];
                ?>
                    <div class="question-card bg-white rounded-xl shadow-sm p-5">
                        <div class="flex justify-between items-start mb-3">
                            <h3 class="font-semibold text-gray-800">
                                Question <?php echo $question_number; ?>: 
                                <span class="font-normal"><?php echo htmlspecialchars($q['question_text']); ?></span>
                            </h3>
                            <span class="text-sm text-gray-500">[<?php echo $q['marks']; ?> marks]</span>
                        </div>
                        
                        <?php if($q['question_type'] == 'mcq' && !empty($options)): ?>
                            <div class="space-y-2 ml-4">
                                <?php foreach($options as $key => $option): ?>
                                    <label class="flex items-start space-x-3 p-2 hover:bg-gray-50 rounded-lg cursor-pointer">
                                        <input type="radio" name="answer[<?php echo $q['id']; ?>]" value="<?php echo $key; ?>" class="mt-1">
                                        <span class="text-sm"><?php echo $key . '. ' . htmlspecialchars($option); ?></span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        <?php elseif($q['question_type'] == 'truefalse'): ?>
                            <div class="space-y-2 ml-4">
                                <label class="flex items-center space-x-3 p-2 hover:bg-gray-50 rounded-lg cursor-pointer">
                                    <input type="radio" name="answer[<?php echo $q['id']; ?>]" value="true">
                                    <span class="text-sm">True</span>
                                </label>
                                <label class="flex items-center space-x-3 p-2 hover:bg-gray-50 rounded-lg cursor-pointer">
                                    <input type="radio" name="answer[<?php echo $q['id']; ?>]" value="false">
                                    <span class="text-sm">False</span>
                                </label>
                            </div>
                        <?php else: ?>
                            <div class="ml-4">
                                <textarea name="answer[<?php echo $q['id']; ?>]" rows="4" 
                                          class="w-full border rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500"
                                          placeholder="Type your answer here..."></textarea>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php 
                    $question_number++;
                endwhile; 
                ?>
            </div>
            
            <!-- Submit Button -->
            <div class="mt-6 flex justify-end">
                <button type="button" onclick="submitExam()" class="bg-red-600 text-white px-6 py-2 rounded-lg hover:bg-red-700">
                    <i class="fas fa-paper-plane mr-2"></i> Submit Exam
                </button>
            </div>
        </form>
    </div>
</div>

<script>
// Timer variables
let timeLeft = <?php echo $exam['duration_minutes'] * 60; ?>; // in seconds
let timerInterval;

// Format time as HH:MM:SS
function formatTime(seconds) {
    const hours = Math.floor(seconds / 3600);
    const minutes = Math.floor((seconds % 3600) / 60);
    const secs = seconds % 60;
    return `${hours.toString().padStart(2, '0')}:${minutes.toString().padStart(2, '0')}:${secs.toString().padStart(2, '0')}`;
}

// Update timer display
function updateTimer() {
    const timerElement = document.getElementById('timer');
    if (timerElement) {
        timerElement.textContent = formatTime(timeLeft);
        
        // Change color when less than 5 minutes
        if (timeLeft <= 300) {
            timerElement.classList.add('text-red-600');
        }
        
        // Auto-submit when time runs out
        if (timeLeft <= 0) {
            clearInterval(timerInterval);
            alert('Time is up! Your exam will be submitted automatically.');
            submitExam();
        }
    }
}

// Start timer
function startTimer() {
    updateTimer();
    timerInterval = setInterval(() => {
        if (timeLeft > 0) {
            timeLeft--;
            updateTimer();
        }
    }, 1000);
}

// Submit exam function
function submitExam() {
    if (confirm('Are you sure you want to submit your exam? You cannot change your answers after submission.')) {
        clearInterval(timerInterval);
        document.getElementById('examForm').submit();
    }
}

// Confirm before leaving page
window.addEventListener('beforeunload', function(e) {
    e.preventDefault();
    e.returnValue = 'Are you sure? Your exam progress will be lost.';
    return 'Are you sure? Your exam progress will be lost.';
});

// Start timer when page loads
startTimer();
</script>

<?php include '../../includes/footer.php'; ?>