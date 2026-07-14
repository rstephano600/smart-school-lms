<?php
require_once '../../config.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';
requireRole('teacher');

// ============================================
// ✅ FIX: Get exam ID from URL or use default
// ============================================
$exam_id = isset($_GET['id']) ? intval($_GET['id']) : 21;

// ✅ CHECK IF EXAM EXISTS
$check_exam = $conn->prepare("SELECT id, title, subject_id, class_id, total_marks, is_published FROM teacher_exams WHERE id = ?");
$check_exam->bind_param("i", $exam_id);
$check_exam->execute();
$exam = $check_exam->get_result()->fetch_assoc();

if (!$exam) {
    die("❌ Exam ID $exam_id does not exist! <a href='index.php'>Go back</a>");
}

// Get teacher ID
$teacher_query = $conn->prepare("SELECT id FROM teachers WHERE user_id = ?");
$teacher_query->bind_param("i", $_SESSION['user_id']);
$teacher_query->execute();
$teacher = $teacher_query->get_result()->fetch_assoc();
$teacher_id = $teacher['id'] ?? 0;

$page_title = 'Manage Questions - ' . $exam['title'];
include '../../includes/header.php';
include '../../includes/sidebar.php';
include '../../includes/navbar.php';

// Get questions
$questions = $conn->prepare("
    SELECT * FROM exam_questions WHERE exam_id = ? ORDER BY order_number
");
$questions->bind_param("i", $exam_id);
$questions->execute();
$questions = $questions->get_result();

$error = '';
$success = '';

// Handle question deletion
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $qid = intval($_GET['delete']);
    $delete = $conn->prepare("DELETE FROM exam_questions WHERE id = ? AND exam_id = ?");
    $delete->bind_param("ii", $qid, $exam_id);
    if ($delete->execute()) {
        // ✅ FIXED: Simple reorder without SET @counter
        $reorder = $conn->prepare("
            UPDATE exam_questions 
            SET order_number = order_number - 1 
            WHERE exam_id = ? AND order_number > (
                SELECT * FROM (SELECT order_number FROM exam_questions WHERE id = ?) as tmp
            )
        ");
        $reorder->bind_param("ii", $exam_id, $qid);
        $reorder->execute();
        
        $success = "Question deleted successfully!";
        // Refresh questions
        $questions = $conn->prepare("SELECT * FROM exam_questions WHERE exam_id = ? ORDER BY order_number");
        $questions->bind_param("i", $exam_id);
        $questions->execute();
        $questions = $questions->get_result();
    }
}

// Load question for editing
$edit_question = null;
$edit_id = isset($_GET['edit']) ? intval($_GET['edit']) : 0;

if ($edit_id > 0) {
    $edit_query = $conn->prepare("SELECT * FROM exam_questions WHERE id = ? AND exam_id = ?");
    $edit_query->bind_param("ii", $edit_id, $exam_id);
    $edit_query->execute();
    $edit_question = $edit_query->get_result()->fetch_assoc();
}

// ============================================
// HANDLE FORM SUBMISSION
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $question_text = trim($_POST['question_text'] ?? '');
    $question_type = $_POST['question_type'] ?? 'mcq';
    $marks = intval($_POST['marks'] ?? 1);
    $topic = sanitizeInput($_POST['topic'] ?? '');
    $difficulty = $_POST['difficulty'] ?? 'medium';
    $edit_id = intval($_POST['edit_id'] ?? 0);
    
    $options = null;
    $correct_answer = '';
    
    if ($question_type == 'mcq') {
        $option_a = trim($_POST['option_a'] ?? '');
        $option_b = trim($_POST['option_b'] ?? '');
        $option_c = trim($_POST['option_c'] ?? '');
        $option_d = trim($_POST['option_d'] ?? '');
        
        $options_array = [
            'A' => $option_a,
            'B' => $option_b,
            'C' => $option_c,
            'D' => $option_d
        ];
        $options = json_encode($options_array);
        
        if (isset($_POST['correct_answer']) && !empty($_POST['correct_answer'])) {
            $correct_answer = strtoupper(trim($_POST['correct_answer']));
        } else {
            $correct_answer = 'A';
        }
        
    } elseif ($question_type == 'truefalse') {
        $correct_answer = isset($_POST['truefalse_answer']) ? $_POST['truefalse_answer'] : 'true';
    } else {
        $correct_answer = sanitizeInput($_POST['correct_answer'] ?? '');
    }
    
    if (empty($correct_answer)) {
        $correct_answer = 'A';
    }
    
    if (empty($question_text)) {
        $error = "Question text is required!";
    } elseif ($edit_id > 0) {
        // Update existing question
        $update = $conn->prepare("
            UPDATE exam_questions SET 
                question_text = ?, question_type = ?, options = ?, 
                correct_answer = ?, marks = ?, topic = ?, difficulty = ?
            WHERE id = ? AND exam_id = ?
        ");
        $update->bind_param("sssssisi", 
            $question_text, $question_type, $options, 
            $correct_answer, $marks, $topic, $difficulty,
            $edit_id, $exam_id
        );
        
        if ($update->execute()) {
            $success = "✅ Question updated successfully!";
            $edit_id = 0;
            $edit_question = null;
            $questions = $conn->prepare("SELECT * FROM exam_questions WHERE exam_id = ? ORDER BY order_number");
            $questions->bind_param("i", $exam_id);
            $questions->execute();
            $questions = $questions->get_result();
        } else {
            $error = "❌ Failed to update question: " . $conn->error;
        }
    } else {
        // ✅ ADD NEW QUESTION
        $order_query = $conn->prepare("SELECT MAX(order_number) as max_order FROM exam_questions WHERE exam_id = ?");
        $order_query->bind_param("i", $exam_id);
        $order_query->execute();
        $max_order = $order_query->get_result()->fetch_assoc()['max_order'] ?? 0;
        $order_number = $max_order + 1;
        
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
            $success = "✅ Question added successfully!";
            // Refresh questions
            $questions = $conn->prepare("SELECT * FROM exam_questions WHERE exam_id = ? ORDER BY order_number");
            $questions->bind_param("i", $exam_id);
            $questions->execute();
            $questions = $questions->get_result();
        } else {
            $error = "❌ Failed to add question: " . $conn->error;
        }
    }
}
?>

<!-- ============================================ -->
<!-- HTML SECTION -->
<!-- ============================================ -->
<div class="ml-64 mt-16 p-6 bg-gray-50 min-h-screen">
    <div class="max-w-4xl mx-auto">
        <!-- Header -->
        <div class="mb-6 flex justify-between items-center">
            <div>
                <h1 class="text-2xl font-bold text-gray-800">📝 Manage Questions</h1>
                <p class="text-gray-500 mt-1"><?php echo htmlspecialchars($exam['title']); ?></p>
                <p class="text-sm text-gray-400">Total Marks: <?php echo $exam['total_marks']; ?> | Questions: <?php echo $questions->num_rows; ?></p>
                <p class="text-sm text-blue-600">Exam ID: <?php echo $exam_id; ?></p>
            </div>
            <div class="flex space-x-2">
                <a href="question-bank.php?exam_id=<?php echo $exam_id; ?>" class="bg-indigo-600 text-white px-4 py-2 rounded-lg hover:bg-indigo-700">
                    <i class="fas fa-database mr-2"></i> Question Bank
                </a>
                <a href="index.php" class="bg-gray-600 text-white px-4 py-2 rounded-lg hover:bg-gray-700">
                    <i class="fas fa-arrow-left mr-2"></i> Back
                </a>
            </div>
        </div>

        <!-- Messages -->
        <?php if($error): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg mb-4">
                <i class="fas fa-exclamation-circle mr-2"></i> <?php echo $error; ?>
            </div>
        <?php endif; ?>
        
        <?php if($success): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-lg mb-4">
                <i class="fas fa-check-circle mr-2"></i> <?php echo $success; ?>
            </div>
        <?php endif; ?>

        <!-- Stats -->
        <div class="bg-white rounded-xl shadow-sm p-4 mb-6">
            <div class="grid grid-cols-3 gap-4 text-center">
                <div>
                    <p class="text-gray-500 text-sm">Total Questions</p>
                    <p class="text-2xl font-bold text-blue-600"><?php echo $questions->num_rows; ?></p>
                </div>
                <div>
                    <p class="text-gray-500 text-sm">Total Marks</p>
                    <p class="text-2xl font-bold text-green-600">
                        <?php 
                        $total_marks = 0;
                        $questions->data_seek(0);
                        while($q = $questions->fetch_assoc()) {
                            $total_marks += $q['marks'];
                        }
                        $questions->data_seek(0);
                        echo $total_marks;
                        ?>
                    </p>
                </div>
                <div>
                    <p class="text-gray-500 text-sm">Status</p>
                    <p class="text-lg font-semibold <?php echo $exam['is_published'] ? 'text-green-600' : 'text-yellow-600'; ?>">
                        <?php echo $exam['is_published'] ? '✅ Published' : '📝 Draft'; ?>
                    </p>
                </div>
            </div>
        </div>

        <!-- Add/Edit Question Form -->
        <div class="bg-white rounded-xl shadow-sm p-6 mb-6">
            <h3 class="text-lg font-semibold mb-4">
                <?php echo $edit_question ? '✏️ Edit Question' : '➕ Add New Question'; ?>
            </h3>
            <form method="POST">
                <input type="hidden" name="edit_id" value="<?php echo $edit_id; ?>">
                
                <div class="grid grid-cols-2 gap-4 mb-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Question Type *</label>
                        <select name="question_type" id="question_type" required class="w-full border rounded-lg px-3 py-2" onchange="toggleFields()">
                            <option value="mcq" <?php echo ($edit_question && $edit_question['question_type'] == 'mcq') ? 'selected' : ''; ?>>Multiple Choice (MCQ)</option>
                            <option value="truefalse" <?php echo ($edit_question && $edit_question['question_type'] == 'truefalse') ? 'selected' : ''; ?>>True / False</option>
                            <option value="short_answer" <?php echo ($edit_question && $edit_question['question_type'] == 'short_answer') ? 'selected' : ''; ?>>Short Answer</option>
                            <option value="essay" <?php echo ($edit_question && $edit_question['question_type'] == 'essay') ? 'selected' : ''; ?>>Essay</option>
                            <option value="fill_blanks" <?php echo ($edit_question && $edit_question['question_type'] == 'fill_blanks') ? 'selected' : ''; ?>>Fill in the Blanks</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Difficulty Level</label>
                        <select name="difficulty" class="w-full border rounded-lg px-3 py-2">
                            <option value="easy" <?php echo ($edit_question && $edit_question['difficulty'] == 'easy') ? 'selected' : ''; ?>>Easy</option>
                            <option value="medium" <?php echo ($edit_question && $edit_question['difficulty'] == 'medium') ? 'selected' : ''; ?>>Medium</option>
                            <option value="hard" <?php echo ($edit_question && $edit_question['difficulty'] == 'hard') ? 'selected' : ''; ?>>Hard</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Topic</label>
                        <input type="text" name="topic" value="<?php echo $edit_question ? htmlspecialchars($edit_question['topic']) : ''; ?>" placeholder="e.g., Algebra" class="w-full border rounded-lg px-3 py-2">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Marks *</label>
                        <input type="number" name="marks" value="<?php echo $edit_question ? $edit_question['marks'] : 1; ?>" min="1" required class="w-full border rounded-lg px-3 py-2">
                    </div>
                </div>

                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Question Text *</label>
                    <textarea name="question_text" rows="4" required class="w-full border rounded-lg px-3 py-2" placeholder="Type your question here..."><?php echo $edit_question ? htmlspecialchars($edit_question['question_text']) : ''; ?></textarea>
                </div>

                <!-- MCQ Options -->
                <div id="mcq_fields" class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Answer Options *</label>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="text-sm font-medium">Option A</label>
                            <input type="text" name="option_a" 
                                   value="<?php 
                                       if ($edit_question && $edit_question['options']) {
                                           $opts = json_decode($edit_question['options'], true);
                                           echo htmlspecialchars($opts['A'] ?? '');
                                       }
                                   ?>" 
                                   class="w-full border rounded-lg px-3 py-2" required>
                        </div>
                        <div>
                            <label class="text-sm font-medium">Option B</label>
                            <input type="text" name="option_b" 
                                   value="<?php 
                                       if ($edit_question && $edit_question['options']) {
                                           $opts = json_decode($edit_question['options'], true);
                                           echo htmlspecialchars($opts['B'] ?? '');
                                       }
                                   ?>" 
                                   class="w-full border rounded-lg px-3 py-2" required>
                        </div>
                        <div>
                            <label class="text-sm font-medium">Option C</label>
                            <input type="text" name="option_c" 
                                   value="<?php 
                                       if ($edit_question && $edit_question['options']) {
                                           $opts = json_decode($edit_question['options'], true);
                                           echo htmlspecialchars($opts['C'] ?? '');
                                       }
                                   ?>" 
                                   class="w-full border rounded-lg px-3 py-2" required>
                        </div>
                        <div>
                            <label class="text-sm font-medium">Option D</label>
                            <input type="text" name="option_d" 
                                   value="<?php 
                                       if ($edit_question && $edit_question['options']) {
                                           $opts = json_decode($edit_question['options'], true);
                                           echo htmlspecialchars($opts['D'] ?? '');
                                       }
                                   ?>" 
                                   class="w-full border rounded-lg px-3 py-2" required>
                        </div>
                    </div>
                    <div class="mt-3">
                        <label class="block text-sm font-medium text-red-600 mb-1">⚠️ Correct Answer *</label>
                        <select name="correct_answer" id="correct_answer" class="w-full border rounded-lg px-3 py-2">
                            <option value="A" <?php echo ($edit_question && $edit_question['correct_answer'] == 'A') ? 'selected' : ''; ?>>A</option>
                            <option value="B" <?php echo ($edit_question && $edit_question['correct_answer'] == 'B') ? 'selected' : ''; ?>>B</option>
                            <option value="C" <?php echo ($edit_question && $edit_question['correct_answer'] == 'C') ? 'selected' : ''; ?>>C</option>
                            <option value="D" <?php echo ($edit_question && $edit_question['correct_answer'] == 'D') ? 'selected' : ''; ?>>D</option>
                        </select>
                        <p class="text-xs text-gray-500 mt-1">Select the correct answer for this question.</p>
                    </div>
                </div>

                <!-- True/False Fields -->
                <div id="truefalse_fields" class="mb-4" style="display:none;">
                    <label class="block text-sm font-medium text-red-600 mb-1">⚠️ Correct Answer *</label>
                    <select name="truefalse_answer" class="w-full border rounded-lg px-3 py-2">
                        <option value="true" <?php echo ($edit_question && $edit_question['correct_answer'] == 'true') ? 'selected' : ''; ?>>True</option>
                        <option value="false" <?php echo ($edit_question && $edit_question['correct_answer'] == 'false') ? 'selected' : ''; ?>>False</option>
                    </select>
                </div>

                <!-- Short Answer/Essay/Fill Blanks Fields -->
                <div id="answer_fields" class="mb-4" style="display:none;">
                    <label class="block text-sm font-medium text-red-600 mb-1">⚠️ Expected Answer / Keywords *</label>
                    <textarea name="correct_answer" rows="3" class="w-full border rounded-lg px-3 py-2" placeholder="Enter expected answer or keywords separated by commas..." required><?php echo $edit_question ? htmlspecialchars($edit_question['correct_answer']) : ''; ?></textarea>
                </div>

                <div class="flex justify-end space-x-3 mt-6 pt-4 border-t">
                    <?php if($edit_question): ?>
                        <a href="questions.php" class="px-4 py-2 border rounded-lg hover:bg-gray-50">
                            <i class="fas fa-times mr-2"></i> Cancel Edit
                        </a>
                    <?php endif; ?>
                    <button type="submit" class="px-6 py-2 bg-gradient-to-r from-blue-500 to-purple-600 text-white rounded-lg hover:shadow-lg transition">
                        <i class="fas fa-save mr-2"></i> <?php echo $edit_question ? 'Update Question' : 'Add Question'; ?>
                    </button>
                </div>
            </form>
        </div>

        <!-- Questions List -->
        <?php if ($questions && $questions->num_rows > 0): ?>
            <div class="bg-white rounded-xl shadow-sm overflow-hidden">
                <div class="px-6 py-4 border-b bg-gray-50 flex justify-between items-center">
                    <h3 class="text-lg font-semibold">📝 Questions List</h3>
                    <span class="text-sm text-gray-500"><?php echo $questions->num_rows; ?> questions</span>
                </div>
                <div class="divide-y divide-gray-200">
                    <?php 
                    $counter = 1;
                    while($q = $questions->fetch_assoc()): 
                        $display_text = nl2br(htmlspecialchars($q['question_text']));
                        $correct_ans = $q['correct_answer'] ?? 'Not set';
                    ?>
                        <div class="p-4 hover:bg-gray-50 transition-all">
                            <div class="flex justify-between items-start">
                                <div class="flex-1">
                                    <div class="flex flex-wrap items-center gap-2 mb-2">
                                        <span class="font-semibold text-gray-700">Q<?php echo $counter++; ?>.</span>
                                        <span class="px-2 py-0.5 text-xs rounded-full bg-blue-100 text-blue-700">
                                            <?php echo ucfirst(str_replace('_', ' ', $q['question_type'])); ?>
                                        </span>
                                        <span class="px-2 py-0.5 text-xs rounded-full bg-yellow-100 text-yellow-700">
                                            <?php echo ucfirst($q['difficulty']); ?>
                                        </span>
                                        <span class="text-xs text-gray-400">Marks: <?php echo $q['marks']; ?></span>
                                        <span class="px-2 py-0.5 text-xs rounded-full bg-green-100 text-green-700">
                                            ✓ Correct: <?php echo htmlspecialchars($correct_ans); ?>
                                        </span>
                                    </div>
                                    <div class="text-gray-800"><?php echo $display_text; ?></div>
                                    <?php if($q['question_type'] == 'mcq' && $q['options']): 
                                        $options = json_decode($q['options'], true);
                                    ?>
                                        <div class="mt-2 text-sm text-gray-600 ml-4">
                                            <ul class="list-disc list-inside ml-2">
                                                <?php foreach($options as $key => $opt): ?>
                                                    <li class="text-xs <?php echo $q['correct_answer'] == $key ? 'text-green-600 font-semibold' : 'text-gray-600'; ?>">
                                                        <?php echo $key . ': ' . htmlspecialchars($opt); ?>
                                                        <?php if($q['correct_answer'] == $key): ?>
                                                            <span class="text-green-500">✓ Correct</span>
                                                        <?php endif; ?>
                                                    </li>
                                                <?php endforeach; ?>
                                            </ul>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div class="flex space-x-2 ml-4 flex-shrink-0">
                                    <a href="?edit=<?php echo $q['id']; ?>" 
                                       class="text-blue-600 hover:text-blue-800 p-1" title="Edit">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <a href="?delete=<?php echo $q['id']; ?>" 
                                       onclick="return confirm('Delete this question?')"
                                       class="text-red-600 hover:text-red-800 p-1" title="Delete">
                                        <i class="fas fa-trash-alt"></i>
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>
            </div>
            
            <div class="mt-4 flex justify-end space-x-3">
                <?php if(!$exam['is_published']): ?>
                    <a href="publish.php?id=<?php echo $exam_id; ?>&action=publish" 
                       onclick="return confirm('Publish this exam?')"
                       class="bg-gradient-to-r from-green-500 to-teal-600 text-white px-6 py-2 rounded-lg hover:shadow-lg transition">
                        <i class="fas fa-check-circle mr-2"></i> Publish Exam
                    </a>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="bg-white rounded-xl shadow-sm p-12 text-center">
                <i class="fas fa-question-circle text-6xl text-gray-300 mb-4"></i>
                <h3 class="text-xl font-semibold text-gray-600">No Questions Added</h3>
                <p class="text-gray-400 mt-2">Start adding questions using the form above</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
function toggleFields() {
    const type = document.getElementById('question_type').value;
    document.getElementById('mcq_fields').style.display = type === 'mcq' ? 'block' : 'none';
    document.getElementById('truefalse_fields').style.display = type === 'truefalse' ? 'block' : 'none';
    document.getElementById('answer_fields').style.display = (type === 'short_answer' || type === 'essay' || type === 'fill_blanks') ? 'block' : 'none';
}

document.addEventListener('DOMContentLoaded', function() {
    toggleFields();
    document.getElementById('question_type').addEventListener('change', toggleFields);
});
</script>

<?php include '../../includes/footer.php'; ?>