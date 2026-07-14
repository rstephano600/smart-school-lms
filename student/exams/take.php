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

// ✅ FIX: Check if already submitted
$check = $conn->prepare("SELECT id FROM exam_submissions WHERE exam_id = ? AND student_id = ?");
$check->bind_param("ii", $exam_id, $student_id);
$check->execute();
$existing = $check->get_result()->fetch_assoc();

if ($existing) {
    header("Location: results.php?id=" . $exam_id);
    exit();
}

// Get exam details - ✅ FIX: Make sure exam is published and belongs to student's class
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

// ✅ FIX: If exam not found, redirect to index with message
if (!$exam) {
    header('Location: index.php?error=Exam not found or not available');
    exit();
}

// ✅ FIX: Check if exam is active (within date range)
$today = date('Y-m-d');
if ($exam['start_date'] > $today) {
    header('Location: index.php?error=Exam has not started yet');
    exit();
}
if ($exam['end_date'] < $today) {
    header('Location: index.php?error=Exam has expired');
    exit();
}

// Get questions
$questions = $conn->prepare("
    SELECT * FROM exam_questions 
    WHERE exam_id = ? 
    ORDER BY order_number
");
$questions->bind_param("i", $exam_id);
$questions->execute();
$questions = $questions->get_result();

// ✅ FIX: Check if there are questions
if ($questions->num_rows === 0) {
    header('Location: index.php?error=No questions found for this exam');
    exit();
}

$total_questions = $questions->num_rows;
$total_marks = 0;
$questions->data_seek(0);
while ($q = $questions->fetch_assoc()) {
    $total_marks += $q['marks'];
}
$questions->data_seek(0);

$page_title = 'Take Exam - ' . $exam['title'];
include '../../includes/header.php';
include '../../includes/sidebar.php';
include '../../includes/navbar.php';

$error = $_GET['error'] ?? '';
?>

<style>
.exam-timer {
    font-size: 24px;
    font-weight: bold;
    color: #dc2626;
    font-family: monospace;
    background: #fef2f2;
    padding: 8px 16px;
    border-radius: 8px;
    border: 2px solid #fca5a5;
}
.question-number {
    background: #3b82f6;
    color: white;
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 14px;
    flex-shrink: 0;
}
.question-card {
    transition: all 0.2s ease;
}
.question-card:hover {
    border-color: #93c5fd;
}
</style>

<div class="ml-64 mt-16 p-6 bg-gray-50 min-h-screen">
    <div class="max-w-3xl mx-auto">
        <!-- Header -->
        <div class="bg-white rounded-xl shadow-sm p-6 mb-6">
            <div class="flex justify-between items-center flex-wrap">
                <div>
                    <h1 class="text-2xl font-bold text-gray-800">📝 <?php echo htmlspecialchars($exam['title']); ?></h1>
                    <p class="text-gray-500 mt-1"><?php echo htmlspecialchars($exam['subject_name']); ?></p>
                    <p class="text-sm text-gray-400">Questions: <?php echo $total_questions; ?> | Total Marks: <?php echo $total_marks; ?></p>
                </div>
                <div class="text-right">
                    <div class="exam-timer" id="timer"><?php echo $exam['duration_minutes']; ?>:00</div>
                    <p class="text-xs text-gray-500 mt-1">Time Remaining</p>
                </div>
            </div>
            <?php if($exam['instructions']): ?>
                <div class="mt-4 p-4 bg-blue-50 rounded-lg border border-blue-200">
                    <p class="text-sm text-blue-800"><i class="fas fa-info-circle mr-2"></i> <?php echo nl2br(htmlspecialchars($exam['instructions'])); ?></p>
                </div>
            <?php endif; ?>
        </div>

        <?php if($error): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg mb-4">
                <i class="fas fa-exclamation-circle mr-2"></i> <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <!-- Exam Form -->
        <form method="POST" action="submit.php" id="examForm" onsubmit="return confirmSubmit()">
            <input type="hidden" name="exam_id" value="<?php echo $exam_id; ?>">
            <input type="hidden" name="time_taken" id="time_taken" value="0">
            
            <div class="space-y-4">
                <?php 
                $counter = 1;
                while($q = $questions->fetch_assoc()): 
                    $options = !empty($q['options']) ? json_decode($q['options'], true) : [];
                ?>
                    <div class="bg-white rounded-xl shadow-sm p-6 question-card border border-transparent">
                        <div class="flex items-start gap-3">
                            <span class="question-number">Q<?php echo $counter; ?></span>
                            <div class="flex-1">
                                <div class="flex flex-wrap items-center gap-2 mb-2">
                                    <span class="px-2 py-0.5 text-xs rounded-full bg-gray-100 text-gray-600">
                                        <?php echo ucfirst(str_replace('_', ' ', $q['question_type'])); ?>
                                    </span>
                                    <span class="text-xs text-gray-400">Marks: <?php echo $q['marks']; ?></span>
                                    <?php if($q['topic']): ?>
                                        <span class="text-xs text-gray-400">Topic: <?php echo htmlspecialchars($q['topic']); ?></span>
                                    <?php endif; ?>
                                </div>
                                <p class="text-gray-800 font-medium"><?php echo nl2br(htmlspecialchars($q['question_text'])); ?></p>
                                
                                <!-- Answer Input -->
                                <div class="mt-3 ml-4">
                                    <?php if($q['question_type'] == 'mcq'): ?>
                                        <div class="space-y-2">
                                            <?php foreach($options as $key => $opt): ?>
                                                <label class="flex items-center gap-3 p-2 hover:bg-gray-50 rounded-lg cursor-pointer transition">
                                                    <input type="radio" name="answers[<?php echo $q['id']; ?>]" value="<?php echo $key; ?>" class="w-4 h-4 text-blue-600" required>
                                                    <span class="text-sm"><?php echo $key . ': ' . htmlspecialchars($opt); ?></span>
                                                </label>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php elseif($q['question_type'] == 'truefalse'): ?>
                                        <div class="space-y-2">
                                            <label class="flex items-center gap-3 p-2 hover:bg-gray-50 rounded-lg cursor-pointer transition">
                                                <input type="radio" name="answers[<?php echo $q['id']; ?>]" value="true" class="w-4 h-4 text-blue-600" required>
                                                <span class="text-sm">True</span>
                                            </label>
                                            <label class="flex items-center gap-3 p-2 hover:bg-gray-50 rounded-lg cursor-pointer transition">
                                                <input type="radio" name="answers[<?php echo $q['id']; ?>]" value="false" class="w-4 h-4 text-blue-600" required>
                                                <span class="text-sm">False</span>
                                            </label>
                                        </div>
                                    <?php elseif($q['question_type'] == 'fill_blanks'): ?>
                                        <input type="text" name="answers[<?php echo $q['id']; ?>]" 
                                               placeholder="Fill in the blank..." 
                                               class="w-full md:w-96 border rounded-lg px-4 py-2 focus:ring-2 focus:ring-blue-500 focus:border-transparent" required>
                                    <?php elseif($q['question_type'] == 'short_answer'): ?>
                                        <textarea name="answers[<?php echo $q['id']; ?>]" rows="3" 
                                                  placeholder="Type your short answer..." 
                                                  class="w-full border rounded-lg px-4 py-2 focus:ring-2 focus:ring-blue-500 focus:border-transparent" required></textarea>
                                    <?php elseif($q['question_type'] == 'essay'): ?>
                                        <textarea name="answers[<?php echo $q['id']; ?>]" rows="6" 
                                                  placeholder="Write your essay here..." 
                                                  class="w-full border rounded-lg px-4 py-2 focus:ring-2 focus:ring-blue-500 focus:border-transparent" required></textarea>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php 
                $counter++;
                endwhile; 
                ?>
            </div>

            <div class="mt-6 flex justify-end gap-3">
                <a href="index.php" class="px-6 py-2 border rounded-lg hover:bg-gray-50 transition">
                    <i class="fas fa-times mr-2"></i> Cancel
                </a>
                <button type="submit" class="px-6 py-2 bg-gradient-to-r from-green-500 to-teal-600 text-white rounded-lg hover:shadow-lg transition">
                    <i class="fas fa-paper-plane mr-2"></i> Submit Exam
                </button>
            </div>
        </form>
    </div>
</div>

<script>
// Timer
let timeLeft = <?php echo $exam['duration_minutes']; ?> * 60;
const timerDisplay = document.getElementById('timer');

function updateTimer() {
    const minutes = Math.floor(timeLeft / 60);
    const seconds = timeLeft % 60;
    timerDisplay.textContent = String(minutes).padStart(2, '0') + ':' + String(seconds).padStart(2, '0');
    
    if (timeLeft <= 60) {
        timerDisplay.style.color = '#dc2626';
        timerDisplay.style.animation = 'pulse 1s infinite';
    }
    
    if (timeLeft <= 0) {
        alert('⏰ Time is up! Your exam will be submitted automatically.');
        document.getElementById('examForm').submit();
    }
    
    timeLeft--;
    const totalTime = <?php echo $exam['duration_minutes']; ?> * 60;
    document.getElementById('time_taken').value = totalTime - timeLeft;
}

setInterval(updateTimer, 1000);

function confirmSubmit() {
    const totalQuestions = <?php echo $total_questions; ?>;
    let answered = 0;
    const form = document.getElementById('examForm');
    const inputs = form.querySelectorAll('input[type="radio"]:checked, input[type="text"], textarea');
    
    const questionIds = new Set();
    inputs.forEach(input => {
        if (input.name && input.name.startsWith('answers[')) {
            const value = input.value.trim();
            if (value !== '') {
                const match = input.name.match(/answers\[(\d+)\]/);
                if (match) {
                    questionIds.add(match[1]);
                }
            }
        }
    });
    answered = questionIds.size;
    
    if (answered < totalQuestions) {
        return confirm(
            '⚠️ You have only answered ' + answered + ' out of ' + totalQuestions + ' questions.\n\n' +
            'Unanswered questions will be marked as incorrect.\n\n' +
            'Are you sure you want to submit?'
        );
    }
    
    return confirm('✅ You have answered all ' + totalQuestions + ' questions.\n\nAre you sure you want to submit this exam?');
}

// Prevent accidental page refresh
window.addEventListener('beforeunload', function(e) {
    const form = document.getElementById('examForm');
    const answered = form.querySelectorAll('input[type="radio"]:checked, input[type="text"]:not([value=""]), textarea:not([value=""])');
    if (answered.length > 0) {
        e.preventDefault();
        e.returnValue = '⚠️ You have started the exam. Are you sure you want to leave? Your progress will be lost.';
    }
});

// Add pulse animation
const style = document.createElement('style');
style.textContent = `
    @keyframes pulse {
        0%, 100% { opacity: 1; }
        50% { opacity: 0.5; }
    }
`;
document.head.appendChild(style);
</script>

<?php include '../../includes/footer.php'; ?>