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

$page_title = 'Add Questions - ' . $exam['title'];
include '../../includes/header.php';
include '../../includes/sidebar.php';
include '../../includes/navbar.php';

// Get existing questions
$questions = $conn->prepare("
    SELECT * FROM exam_questions WHERE exam_id = ? ORDER BY order_number
");
$questions->bind_param("i", $exam_id);
$questions->execute();
$questions = $questions->get_result();

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
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
    
    // Get current max order
    $order_query = $conn->prepare("SELECT MAX(order_number) as max_order FROM exam_questions WHERE exam_id = ?");
    $order_query->bind_param("i", $exam_id);
    $order_query->execute();
    $max_order = $order_query->get_result()->fetch_assoc()['max_order'] ?? 0;
    $order_number = $max_order + 1;
    
    $insert = $conn->prepare("
        INSERT INTO exam_questions (exam_id, question_text, question_type, options, correct_answer, marks, topic, difficulty, order_number)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $insert->bind_param("issssisis", $exam_id, $question_text, $question_type, $options, $correct_answer, $marks, $topic, $difficulty, $order_number);
    
    if ($insert->execute()) {
        $success = "Question added successfully!";
        $_POST = [];
        // Refresh questions list
        $questions = $conn->prepare("SELECT * FROM exam_questions WHERE exam_id = ? ORDER BY order_number");
        $questions->bind_param("i", $exam_id);
        $questions->execute();
        $questions = $questions->get_result();
    } else {
        $error = "Failed to add question: " . $conn->error;
    }
}

// Handle question deletion
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $qid = $_GET['delete'];
    $delete = $conn->prepare("DELETE FROM exam_questions WHERE id = ? AND exam_id = ?");
    $delete->bind_param("ii", $qid, $exam_id);
    $delete->execute();
    header("Location: questions.php?id=$exam_id");
    exit();
}
?>

<div class="ml-64 mt-16 p-6">
    <div class="max-w-4xl mx-auto">
        <div class="mb-6">
            <h1 class="text-2xl font-bold text-gray-800">Add Questions</h1>
            <p class="text-gray-500 mt-1"><?php echo htmlspecialchars($exam['title']); ?> - <?php echo htmlspecialchars($exam['subject_name']); ?> (Total Marks: <?php echo $exam['total_marks']; ?>)</p>
        </div>

        <?php if($error): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg mb-4"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <?php if($success): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-lg mb-4"><?php echo $success; ?></div>
        <?php endif; ?>

        <!-- Current Questions Summary -->
        <?php if ($questions && $questions->num_rows > 0): ?>
            <div class="bg-white rounded-xl shadow-sm p-4 mb-6">
                <h3 class="font-semibold mb-2">Questions Summary (<?php echo $questions->num_rows; ?> questions)</h3>
                <?php 
                $total_marks = 0;
                $questions->data_seek(0);
                while($q = $questions->fetch_assoc()) {
                    $total_marks += $q['marks'];
                }
                $remaining_marks = $exam['total_marks'] - $total_marks;
                ?>
                <p>Total marks added: <strong><?php echo $total_marks; ?></strong> / <?php echo $exam['total_marks']; ?></p>
                <p>Remaining marks: <strong class="<?php echo $remaining_marks >= 0 ? 'text-green-600' : 'text-red-600'; ?>"><?php echo $remaining_marks; ?></strong></p>
                <?php $questions->data_seek(0); ?>
            </div>
        <?php endif; ?>

        <!-- Add Question Form -->
        <div class="bg-white rounded-xl shadow-sm p-6 mb-6">
            <h3 class="text-lg font-semibold mb-4">Add New Question</h3>
            <form method="POST" id="questionForm">
                <div class="grid grid-cols-2 gap-4 mb-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Question Type</label>
                        <select name="question_type" id="question_type" class="w-full border rounded-lg px-3 py-2" onchange="toggleQuestionFields()">
                            <option value="mcq">Multiple Choice (MCQ)</option>
                            <option value="truefalse">True / False</option>
                            <option value="short_answer">Short Answer</option>
                            <option value="essay">Essay</option>
                            <option value="fill_blanks">Fill in the Blanks</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Difficulty Level</label>
                        <select name="difficulty" class="w-full border rounded-lg px-3 py-2">
                            <option value="easy">Easy</option>
                            <option value="medium">Medium</option>
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
                    <label class="block text-sm font-medium text-gray-700 mb-1">Question Text</label>
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
                    <label class="block text-sm font-medium text-gray-700 mb-1">Expected Answer (for auto-grading reference)</label>
                    <textarea name="correct_answer" rows="3" class="w-full border rounded-lg px-3 py-2" placeholder="Keywords to look for..."></textarea>
                    <p class="text-xs text-gray-500 mt-1">For short answers, enter keywords separated by commas</p>
                </div>

                <div class="flex justify-end space-x-3 mt-6 pt-4 border-t">
                    <a href="index.php" class="px-4 py-2 border rounded-lg hover:bg-gray-50">Done</a>
                    <button type="submit" class="px-6 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700">
                        <i class="fas fa-plus mr-2"></i> Add Question
                    </button>
                </div>
            </form>
        </div>

        <!-- Questions List -->
        <?php if ($questions && $questions->num_rows > 0): ?>
            <div class="bg-white rounded-xl shadow-sm overflow-hidden">
                <div class="px-6 py-4 border-b bg-gray-50">
                    <h3 class="text-lg font-semibold">Questions List</h3>
                </div>
                <div class="divide-y divide-gray-200">
                    <?php 
                    $questions->data_seek(0);
                    $counter = 1;
                    while($q = $questions->fetch_assoc()): 
                    ?>
                        <div class="p-4 hover:bg-gray-50">
                            <div class="flex justify-between items-start">
                                <div class="flex-1">
                                    <p class="font-medium"><?php echo $counter++; ?>. <?php echo htmlspecialchars($q['question_text']); ?></p>
                                    <div class="flex items-center space-x-3 mt-1 text-xs text-gray-500">
                                        <span class="px-2 py-0.5 rounded-full bg-gray-100"><?php echo ucfirst($q['question_type']); ?></span>
                                        <span>Marks: <?php echo $q['marks']; ?></span>
                                        <span>Difficulty: <?php echo ucfirst($q['difficulty']); ?></span>
                                        <?php if($q['topic']): ?>
                                            <span>Topic: <?php echo htmlspecialchars($q['topic']); ?></span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="flex space-x-2">
                                    <a href="?id=<?php echo $exam_id; ?>&delete=<?php echo $q['id']; ?>" 
                                       onclick="return confirm('Delete this question?')"
                                       class="text-red-600 hover:text-red-800">
                                        <i class="fas fa-trash-alt"></i>
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>
            </div>
            
            <div class="mt-4 flex justify-end">
                <a href="publish.php?id=<?php echo $exam_id; ?>" class="bg-gradient-to-r from-green-500 to-teal-600 text-white px-6 py-2 rounded-lg hover:shadow-lg">
                    <i class="fas fa-check-circle mr-2"></i> Publish Exam
                </a>
            </div>
        <?php endif; ?>
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