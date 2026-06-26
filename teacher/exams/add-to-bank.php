<?php
require_once '../../config.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';
requireRole('teacher');

$page_title = 'Add Question to Bank';
include '../../includes/header.php';
include '../../includes/sidebar.php';
include '../../includes/navbar.php';

// Get teacher ID
$teacher_query = $conn->prepare("SELECT id FROM teachers WHERE user_id = ?");
$teacher_query->bind_param("i", $_SESSION['user_id']);
$teacher_query->execute();
$teacher = $teacher_query->get_result()->fetch_assoc();
$teacher_id = $teacher['id'] ?? 0;

// Get subjects taught by this teacher
$subjects = $conn->prepare("
    SELECT DISTINCT s.id, s.name, s.code
    FROM subjects s
    JOIN class_subject cs ON s.id = cs.subject_id
    WHERE cs.teacher_id = ?
    ORDER BY s.name
");
$subjects->bind_param("i", $teacher_id);
$subjects->execute();
$subjects = $subjects->get_result();

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $subject_id = intval($_POST['subject_id']);
    $question_text = sanitizeInput($_POST['question_text']);
    $question_type = $_POST['question_type'];
    $marks = intval($_POST['marks']);
    $topic = sanitizeInput($_POST['topic']);
    $difficulty = $_POST['difficulty'];
    
    $options = null;
    $correct_answer = null;
    
    if ($question_type == 'mcq') {
        $options_array = [
            'A' => sanitizeInput($_POST['option_a']),
            'B' => sanitizeInput($_POST['option_b']),
            'C' => sanitizeInput($_POST['option_c']),
            'D' => sanitizeInput($_POST['option_d'])
        ];
        $options = json_encode($options_array);
        $correct_answer = $_POST['correct_answer'];
    } elseif ($question_type == 'truefalse') {
        $correct_answer = $_POST['truefalse_answer'];
    } else {
        $correct_answer = sanitizeInput($_POST['correct_answer']);
    }
    
    $insert = $conn->prepare("
        INSERT INTO question_bank (teacher_id, subject_id, question_text, question_type, options, correct_answer, marks, topic, difficulty)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $insert->bind_param("iisssssis", $teacher_id, $subject_id, $question_text, $question_type, $options, $correct_answer, $marks, $topic, $difficulty);
    
    if ($insert->execute()) {
        logActivity($_SESSION['user_id'], 'added question to bank', 'question_bank', $insert->insert_id);
        $success = "Question added to bank successfully!";
        $_POST = [];
    } else {
        $error = "Failed to add question: " . $conn->error;
    }
}

$redirect_exam = isset($_GET['exam_id']) ? $_GET['exam_id'] : '';
?>

<div class="ml-64 mt-16 p-6">
    <div class="max-w-3xl mx-auto">
        <div class="mb-6">
            <h1 class="text-2xl font-bold text-gray-800">Add Question to Bank</h1>
            <p class="text-gray-500 mt-1">Save reusable questions for future exams</p>
        </div>

        <?php if($error): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg mb-4"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <?php if($success): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-lg mb-4"><?php echo $success; ?></div>
        <?php endif; ?>

        <div class="bg-white rounded-xl shadow-sm p-6">
            <form method="POST" id="questionForm">
                <?php if($redirect_exam): ?>
                    <input type="hidden" name="redirect_exam" value="<?php echo $redirect_exam; ?>">
                <?php endif; ?>
                
                <div class="grid grid-cols-2 gap-4 mb-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Subject *</label>
                        <select name="subject_id" required class="w-full border rounded-lg px-3 py-2">
                            <option value="">Select Subject</option>
                            <?php while($subject = $subjects->fetch_assoc()): ?>
                                <option value="<?php echo $subject['id']; ?>"><?php echo htmlspecialchars($subject['name']); ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Question Type *</label>
                        <select name="question_type" id="question_type" required class="w-full border rounded-lg px-3 py-2" onchange="toggleQuestionFields()">
                            <option value="mcq">Multiple Choice (MCQ)</option>
                            <option value="truefalse">True / False</option>
                            <option value="short_answer">Short Answer</option>
                            <option value="essay">Essay</option>
                            <option value="fill_blanks">Fill in the Blanks</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Difficulty</label>
                        <select name="difficulty" class="w-full border rounded-lg px-3 py-2">
                            <option value="easy">Easy</option>
                            <option value="medium" selected>Medium</option>
                            <option value="hard">Hard</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Topic</label>
                        <input type="text" name="topic" placeholder="e.g., Algebra, Grammar" class="w-full border rounded-lg px-3 py-2">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Marks</label>
                        <input type="number" name="marks" value="1" min="1" class="w-full border rounded-lg px-3 py-2">
                    </div>
                </div>

                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Question Text *</label>
                    <textarea name="question_text" rows="3" required class="w-full border rounded-lg px-3 py-2" placeholder="Type your question here..."></textarea>
                </div>

                <!-- MCQ Options -->
                <div id="mcq_fields" class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Answer Options</label>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="text-sm">Option A</label>
                            <input type="text" name="option_a" class="w-full border rounded-lg px-3 py-2">
                        </div>
                        <div>
                            <label class="text-sm">Option B</label>
                            <input type="text" name="option_b" class="w-full border rounded-lg px-3 py-2">
                        </div>
                        <div>
                            <label class="text-sm">Option C</label>
                            <input type="text" name="option_c" class="w-full border rounded-lg px-3 py-2">
                        </div>
                        <div>
                            <label class="text-sm">Option D</label>
                            <input type="text" name="option_d" class="w-full border rounded-lg px-3 py-2">
                        </div>
                    </div>
                    <div class="mt-3">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Correct Answer</label>
                        <select name="correct_answer" class="w-full border rounded-lg px-3 py-2">
                            <option value="A">A</option>
                            <option value="B">B</option>
                            <option value="C">C</option>
                            <option value="D">D</option>
                        </select>
                    </div>
                </div>

                <!-- True/False Fields -->
                <div id="truefalse_fields" class="mb-4" style="display:none;">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Correct Answer</label>
                    <select name="truefalse_answer" class="w-full border rounded-lg px-3 py-2">
                        <option value="true">True</option>
                        <option value="false">False</option>
                    </select>
                </div>

                <!-- Short Answer/Essay Fields -->
                <div id="answer_fields" class="mb-4" style="display:none;">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Expected Answer / Keywords</label>
                    <textarea name="correct_answer" rows="3" class="w-full border rounded-lg px-3 py-2" placeholder="Enter expected answer or keywords separated by commas..."></textarea>
                    <p class="text-xs text-gray-500 mt-1">For short answers, enter keywords to help with grading</p>
                </div>

                <div class="flex justify-end space-x-3 mt-6 pt-4 border-t">
                    <a href="question-bank.php<?php echo $redirect_exam ? '?exam_id=' . $redirect_exam : ''; ?>" class="px-4 py-2 border rounded-lg hover:bg-gray-50">Cancel</a>
                    <button type="submit" class="px-6 py-2 bg-gradient-to-r from-blue-500 to-purple-600 text-white rounded-lg hover:shadow-lg">
                        <i class="fas fa-save mr-2"></i> Save to Bank
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function toggleQuestionFields() {
    const type = document.getElementById('question_type').value;
    document.getElementById('mcq_fields').style.display = type === 'mcq' ? 'block' : 'none';
    document.getElementById('truefalse_fields').style.display = type === 'truefalse' ? 'block' : 'none';
    document.getElementById('answer_fields').style.display = (type === 'short_answer' || type === 'essay' || type === 'fill_blanks') ? 'block' : 'none';
}

toggleQuestionFields();
</script>

<?php include '../../includes/footer.php'; ?>