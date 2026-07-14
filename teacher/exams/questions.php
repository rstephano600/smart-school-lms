<?php
require_once '../../config.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';
requireRole('teacher');

$exam_id = isset($_GET['id']) ? intval($_GET['id']) : 1;

// Check if exam exists
$check = $conn->prepare("SELECT id, title FROM teacher_exams WHERE id = ?");
$check->bind_param("i", $exam_id);
$check->execute();
$exam = $check->get_result()->fetch_assoc();

if (!$exam) {
    die("❌ Exam not found! <a href='create.php'>Create a new exam</a>");
}

$page_title = 'Manage Questions - ' . $exam['title'];
include '../../includes/header.php';
include '../../includes/sidebar.php';
include '../../includes/navbar.php';

$error = '';
$success = '';

// ============================================
// HANDLE FORM SUBMISSION
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $question_text = trim($_POST['question_text'] ?? '');
    $question_type = $_POST['question_type'] ?? 'mcq';
    $marks = intval($_POST['marks'] ?? 1);
    $topic = trim($_POST['topic'] ?? '');
    $difficulty = $_POST['difficulty'] ?? 'medium';
    
    $options = null;
    $correct_answer = '';
    
    if ($question_type == 'mcq') {
        // Get options
        $option_a = trim($_POST['option_a'] ?? '');
        $option_b = trim($_POST['option_b'] ?? '');
        $option_c = trim($_POST['option_c'] ?? '');
        $option_d = trim($_POST['option_d'] ?? '');
        $option_e = trim($_POST['option_e'] ?? '');
        $option_f = trim($_POST['option_f'] ?? '');
        
        $options_array = [];
        if (!empty($option_a)) $options_array['A'] = $option_a;
        if (!empty($option_b)) $options_array['B'] = $option_b;
        if (!empty($option_c)) $options_array['C'] = $option_c;
        if (!empty($option_d)) $options_array['D'] = $option_d;
        if (!empty($option_e)) $options_array['E'] = $option_e;
        if (!empty($option_f)) $options_array['F'] = $option_f;
        
        $options = json_encode($options_array);
        
        if (isset($_POST['correct_answer']) && !empty($_POST['correct_answer'])) {
            $correct_answer = strtoupper(trim($_POST['correct_answer']));
        } else {
            $correct_answer = 'A';
        }
        
    } elseif ($question_type == 'truefalse') {
        // ✅ FIX: Get true/false answer
        if (isset($_POST['truefalse_answer']) && !empty($_POST['truefalse_answer'])) {
            $correct_answer = $_POST['truefalse_answer'];
        } else {
            $correct_answer = 'true';
        }
        
    } elseif ($question_type == 'short_answer' || $question_type == 'essay' || $question_type == 'fill_blanks') {
        $correct_answer = sanitizeInput($_POST['correct_answer'] ?? '');
    }
    
    // Ensure correct_answer is NEVER empty
    if (empty($correct_answer)) {
        $correct_answer = 'A';
    }
    
    // Get order number
    $order_query = $conn->prepare("SELECT MAX(order_number) as max_order FROM exam_questions WHERE exam_id = ?");
    $order_query->bind_param("i", $exam_id);
    $order_query->execute();
    $max_order = $order_query->get_result()->fetch_assoc()['max_order'] ?? 0;
    $order_number = $max_order + 1;
    
    // Insert
    $insert = $conn->prepare("
        INSERT INTO exam_questions (
            exam_id, question_text, question_type, options, 
            correct_answer, marks, topic, difficulty, order_number
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $insert->bind_param(
        "issssissi",
        $exam_id,
        $question_text,
        $question_type,
        $options,
        $correct_answer,
        $marks,
        $topic,
        $difficulty,
        $order_number
    );
    
    if ($insert->execute()) {
        $success = "✅ Question added successfully! Correct answer: " . $correct_answer;
    } else {
        $error = "❌ Failed: " . $conn->error;
    }
}

// Get questions
$questions = $conn->prepare("SELECT * FROM exam_questions WHERE exam_id = ? ORDER BY order_number");
$questions->bind_param("i", $exam_id);
$questions->execute();
$questions = $questions->get_result();

// Question types
$question_types = [
    'mcq' => 'Multiple Choice',
    'truefalse' => 'True / False',
    'short_answer' => 'Short Answer',
    'essay' => 'Essay',
    'fill_blanks' => 'Fill in the Blank'
];
?>

<div class="ml-64 mt-16 p-6 bg-gray-50 min-h-screen">
    <div class="max-w-4xl mx-auto">
        <div class="mb-6">
            <h1 class="text-2xl font-bold">📝 <?php echo htmlspecialchars($exam['title']); ?></h1>
            <p class="text-gray-500">Exam ID: <?php echo $exam_id; ?> | Questions: <?php echo $questions->num_rows; ?></p>
        </div>

        <?php if($error): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg mb-4"><?php echo $error; ?></div>
        <?php endif; ?>
        <?php if($success): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-lg mb-4"><?php echo $success; ?></div>
        <?php endif; ?>

        <!-- ADD QUESTION FORM -->
        <div class="bg-white rounded-xl shadow-sm p-6 mb-6">
            <h3 class="text-lg font-semibold mb-4">➕ Add New Question</h3>
            <form method="POST">
                <div class="mb-4">
                    <label class="block text-sm font-medium mb-1">Question Text *</label>
                    <textarea name="question_text" rows="3" required class="w-full border rounded-lg px-3 py-2" placeholder="Type your question..."></textarea>
                </div>

                <div class="grid grid-cols-2 gap-4 mb-4">
                    <div>
                        <label class="block text-sm font-medium mb-1">Question Type</label>
                        <select name="question_type" id="question_type" class="w-full border rounded-lg px-3 py-2" onchange="toggleFields()">
                            <option value="mcq">Multiple Choice</option>
                            <option value="truefalse">True / False</option>
                            <option value="short_answer">Short Answer</option>
                            <option value="essay">Essay</option>
                            <option value="fill_blanks">Fill in the Blank</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-1">Marks</label>
                        <input type="number" name="marks" value="1" min="1" class="w-full border rounded-lg px-3 py-2">
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-1">Topic</label>
                        <input type="text" name="topic" placeholder="Topic" class="w-full border rounded-lg px-3 py-2">
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-1">Difficulty</label>
                        <select name="difficulty" class="w-full border rounded-lg px-3 py-2">
                            <option value="easy">Easy</option>
                            <option value="medium" selected>Medium</option>
                            <option value="hard">Hard</option>
                        </select>
                    </div>
                </div>

                <!-- MCQ Options -->
                <div id="options_fields" class="mb-4">
                    <label class="block text-sm font-medium mb-1">Answer Options</label>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="text-sm">Option A</label>
                            <input type="text" name="option_a" placeholder="Option A" class="w-full border rounded-lg px-3 py-2">
                        </div>
                        <div>
                            <label class="text-sm">Option B</label>
                            <input type="text" name="option_b" placeholder="Option B" class="w-full border rounded-lg px-3 py-2">
                        </div>
                        <div>
                            <label class="text-sm">Option C</label>
                            <input type="text" name="option_c" placeholder="Option C" class="w-full border rounded-lg px-3 py-2">
                        </div>
                        <div>
                            <label class="text-sm">Option D</label>
                            <input type="text" name="option_d" placeholder="Option D" class="w-full border rounded-lg px-3 py-2">
                        </div>
                        <div>
                            <label class="text-sm">Option E</label>
                            <input type="text" name="option_e" placeholder="Option E" class="w-full border rounded-lg px-3 py-2">
                        </div>
                        <div>
                            <label class="text-sm">Option F</label>
                            <input type="text" name="option_f" placeholder="Option F" class="w-full border rounded-lg px-3 py-2">
                        </div>
                    </div>
                </div>

                <!-- Correct Answer - MCQ -->
                <div id="correct_answer_mcq" class="mb-4">
                    <label class="block text-sm font-medium text-red-600 mb-1">⚠️ Correct Answer *</label>
                    <select name="correct_answer" class="w-full border-2 border-red-300 rounded-lg px-3 py-2">
                        <option value="">-- Select --</option>
                        <option value="A">A</option>
                        <option value="B">B</option>
                        <option value="C">C</option>
                        <option value="D">D</option>
                        <option value="E">E</option>
                        <option value="F">F</option>
                    </select>
                    <p class="text-xs text-red-500 mt-1">⚠️ You MUST select the correct answer!</p>
                </div>

                <!-- True/False -->
                <div id="truefalse_fields" class="mb-4" style="display:none;">
                    <label class="block text-sm font-medium text-red-600 mb-1">⚠️ Correct Answer *</label>
                    <select name="truefalse_answer" class="w-full border-2 border-red-300 rounded-lg px-3 py-2">
                        <option value="">-- Select --</option>
                        <option value="true">✅ True</option>
                        <option value="false">❌ False</option>
                    </select>
                    <p class="text-xs text-red-500 mt-1">⚠️ You MUST select True or False!</p>
                </div>

                <!-- Short Answer / Essay / Fill in the Blank -->
                <div id="answer_text_fields" class="mb-4" style="display:none;">
                    <label class="block text-sm font-medium text-red-600 mb-1">Expected Answer</label>
                    <textarea name="correct_answer" rows="3" class="w-full border rounded-lg px-3 py-2" placeholder="Enter expected answer..."></textarea>
                    <p class="text-xs text-gray-500 mt-1">For short answer, enter keywords separated by commas</p>
                </div>

                <button type="submit" class="w-full bg-gradient-to-r from-blue-500 to-purple-600 text-white py-3 rounded-lg hover:shadow-lg transition">
                    <i class="fas fa-save mr-2"></i> Add Question
                </button>
            </form>
        </div>

        <!-- QUESTIONS LIST -->
        <?php if ($questions->num_rows > 0): ?>
            <div class="bg-white rounded-xl shadow-sm overflow-hidden">
                <div class="px-6 py-4 border-b bg-gray-50 flex justify-between items-center">
                    <h3 class="text-lg font-semibold">📝 Questions List</h3>
                    <span class="text-sm text-gray-500"><?php echo $questions->num_rows; ?> questions</span>
                </div>
                <div class="divide-y">
                    <?php 
                    $counter = 1;
                    while($q = $questions->fetch_assoc()): 
                        $type_label = $question_types[$q['question_type']] ?? $q['question_type'];
                        $correct_ans = $q['correct_answer'] ?? 'Not set';
                        $has_correct = !empty($correct_ans) && $correct_ans != '';
                    ?>
                        <div class="p-4 hover:bg-gray-50 <?php echo !$has_correct ? 'bg-red-50 border-l-4 border-red-500' : ''; ?>">
                            <div class="flex justify-between items-center">
                                <div>
                                    <span class="font-semibold">Q<?php echo $counter++; ?>.</span>
                                    <span class="ml-2"><?php echo htmlspecialchars($q['question_text']); ?></span>
                                    <span class="ml-2 text-xs text-gray-400">(Marks: <?php echo $q['marks']; ?>)</span>
                                    <span class="ml-2 text-xs text-gray-400"><?php echo $type_label; ?></span>
                                    <?php if($has_correct): ?>
                                        <span class="ml-2 px-2 py-0.5 text-xs rounded-full bg-green-100 text-green-700">
                                            ✓ Correct: <?php echo htmlspecialchars($correct_ans); ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="ml-2 px-2 py-0.5 text-xs rounded-full bg-red-100 text-red-700 animate-pulse">
                                            ⚠️ NO CORRECT ANSWER!
                                        </span>
                                    <?php endif; ?>
                                </div>
                                <div>
                                    <a href="?id=<?php echo $exam_id; ?>&edit=<?php echo $q['id']; ?>" class="text-blue-600 hover:text-blue-800 mr-2">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <a href="?id=<?php echo $exam_id; ?>&delete=<?php echo $q['id']; ?>" 
                                       onclick="return confirm('Delete this question?')"
                                       class="text-red-600 hover:text-red-800">
                                        <i class="fas fa-trash-alt"></i>
                                    </a>
                                </div>
                            </div>
                            <?php if(!$has_correct): ?>
                                <div class="mt-1 text-xs text-red-600">
                                    ⚠️ This question has NO correct answer! Please edit and select one.
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endwhile; ?>
                </div>
            </div>
            
            <div class="mt-4 flex justify-end">
                <a href="publish.php?id=<?php echo $exam_id; ?>&action=publish" 
                   onclick="return confirm('Publish this exam?')"
                   class="bg-green-600 text-white px-6 py-2 rounded-lg hover:bg-green-700">
                    <i class="fas fa-check-circle mr-2"></i> Publish Exam
                </a>
            </div>
        <?php else: ?>
            <div class="bg-white rounded-xl shadow-sm p-12 text-center">
                <i class="fas fa-question-circle text-6xl text-gray-300 mb-4"></i>
                <h3 class="text-xl text-gray-600">No Questions Added</h3>
                <p class="text-gray-400">Add your first question using the form above</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
function toggleFields() {
    const type = document.getElementById('question_type').value;
    
    // Hide all
    document.getElementById('options_fields').style.display = 'none';
    document.getElementById('correct_answer_mcq').style.display = 'none';
    document.getElementById('truefalse_fields').style.display = 'none';
    document.getElementById('answer_text_fields').style.display = 'none';
    
    // Show based on type
    if (type === 'mcq') {
        document.getElementById('options_fields').style.display = 'block';
        document.getElementById('correct_answer_mcq').style.display = 'block';
    } else if (type === 'truefalse') {
        document.getElementById('truefalse_fields').style.display = 'block';
    } else if (type === 'short_answer' || type === 'essay' || type === 'fill_blanks') {
        document.getElementById('answer_text_fields').style.display = 'block';
    }
}

document.addEventListener('DOMContentLoaded', function() {
    toggleFields();
    document.getElementById('question_type').addEventListener('change', toggleFields);
});
</script>

<?php include '../../includes/footer.php'; ?>