<?php
require_once '../../config.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';
requireRole('teacher');

$page_title = 'Question Bank';
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

// Get filter parameters
$subject_filter = $_GET['subject_id'] ?? '';
$topic_filter = $_GET['topic'] ?? '';
$type_filter = $_GET['type'] ?? '';
$difficulty_filter = $_GET['difficulty'] ?? '';
$search_filter = $_GET['search'] ?? '';

// Build query for question bank
$query = "SELECT qb.*, s.name as subject_name 
          FROM question_bank qb
          JOIN subjects s ON qb.subject_id = s.id
          WHERE qb.teacher_id = ?";
$params = [$teacher_id];
$types = "i";

if ($subject_filter) {
    $query .= " AND qb.subject_id = ?";
    $params[] = $subject_filter;
    $types .= "i";
}
if ($topic_filter) {
    $query .= " AND qb.topic LIKE ?";
    $params[] = "%$topic_filter%";
    $types .= "s";
}
if ($type_filter) {
    $query .= " AND qb.question_type = ?";
    $params[] = $type_filter;
    $types .= "s";
}
if ($difficulty_filter) {
    $query .= " AND qb.difficulty = ?";
    $params[] = $difficulty_filter;
    $types .= "s";
}
if ($search_filter) {
    $query .= " AND (qb.question_text LIKE ? OR qb.topic LIKE ?)";
    $params[] = "%$search_filter%";
    $params[] = "%$search_filter%";
    $types .= "ss";
}

$query .= " ORDER BY qb.created_at DESC";

$stmt = $conn->prepare($query);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$questions = $stmt->get_result();

// Handle deletion
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $qid = $_GET['delete'];
    $delete = $conn->prepare("DELETE FROM question_bank WHERE id = ? AND teacher_id = ?");
    $delete->bind_param("ii", $qid, $teacher_id);
    $delete->execute();
    header("Location: question-bank.php");
    exit();
}

// Handle add to exam
if (isset($_GET['add_to_exam']) && is_numeric($_GET['add_to_exam']) && isset($_GET['exam_id'])) {
    $question_id = $_GET['add_to_exam'];
    $exam_id = $_GET['exam_id'];
    
    // Get question from bank
    $get_q = $conn->prepare("SELECT * FROM question_bank WHERE id = ? AND teacher_id = ?");
    $get_q->bind_param("ii", $question_id, $teacher_id);
    $get_q->execute();
    $bank_q = $get_q->get_result()->fetch_assoc();
    
    if ($bank_q) {
        // Get current max order
        $order_query = $conn->prepare("SELECT MAX(order_number) as max_order FROM exam_questions WHERE exam_id = ?");
        $order_query->bind_param("i", $exam_id);
        $order_query->execute();
        $max_order = $order_query->get_result()->fetch_assoc()['max_order'] ?? 0;
        $order_number = $max_order + 1;
        
        // Insert into exam questions
        $insert = $conn->prepare("
            INSERT INTO exam_questions (exam_id, question_text, question_type, options, correct_answer, marks, topic, difficulty, order_number)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $insert->bind_param("issssisii", 
            $exam_id, $bank_q['question_text'], $bank_q['question_type'], 
            $bank_q['options'], $bank_q['correct_answer'], $bank_q['marks'], 
            $bank_q['topic'], $bank_q['difficulty'], $order_number
        );
        $insert->execute();
        header("Location: questions.php?id=$exam_id&success=Question added from bank");
        exit();
    }
}

// Get unique topics for filter
$topics = $conn->prepare("
    SELECT DISTINCT topic FROM question_bank 
    WHERE teacher_id = ? AND topic IS NOT NULL AND topic != ''
    ORDER BY topic
");
$topics->bind_param("i", $teacher_id);
$topics->execute();
$topics = $topics->get_result();
?>

<div class="ml-64 mt-16 p-6">
    <div class="max-w-full mx-auto">
        <div class="mb-6 flex justify-between items-center">
            <div>
                <h1 class="text-2xl font-bold text-gray-800">Question Bank</h1>
                <p class="text-gray-500 mt-1">Manage reusable questions for your exams</p>
            </div>
            <a href="add-to-bank.php" class="bg-gradient-to-r from-blue-500 to-purple-600 text-white px-6 py-2 rounded-lg hover:shadow-lg">
                <i class="fas fa-plus mr-2"></i> Add Question to Bank
            </a>
        </div>

        <!-- Filters -->
        <div class="bg-white rounded-xl shadow-sm p-4 mb-6">
            <form method="GET" class="grid grid-cols-1 md:grid-cols-5 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Subject</label>
                    <select name="subject_id" class="w-full border rounded-lg px-3 py-2" onchange="this.form.submit()">
                        <option value="">All Subjects</option>
                        <?php while($subject = $subjects->fetch_assoc()): ?>
                            <option value="<?php echo $subject['id']; ?>" <?php echo $subject_filter == $subject['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($subject['name']); ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Topic</label>
                    <select name="topic" class="w-full border rounded-lg px-3 py-2" onchange="this.form.submit()">
                        <option value="">All Topics</option>
                        <?php while($topic = $topics->fetch_assoc()): ?>
                            <option value="<?php echo htmlspecialchars($topic['topic']); ?>" <?php echo $topic_filter == $topic['topic'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($topic['topic']); ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Question Type</label>
                    <select name="type" class="w-full border rounded-lg px-3 py-2" onchange="this.form.submit()">
                        <option value="">All Types</option>
                        <option value="mcq" <?php echo $type_filter == 'mcq' ? 'selected' : ''; ?>>Multiple Choice</option>
                        <option value="truefalse" <?php echo $type_filter == 'truefalse' ? 'selected' : ''; ?>>True/False</option>
                        <option value="short_answer" <?php echo $type_filter == 'short_answer' ? 'selected' : ''; ?>>Short Answer</option>
                        <option value="essay" <?php echo $type_filter == 'essay' ? 'selected' : ''; ?>>Essay</option>
                        <option value="fill_blanks" <?php echo $type_filter == 'fill_blanks' ? 'selected' : ''; ?>>Fill in Blanks</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Difficulty</label>
                    <select name="difficulty" class="w-full border rounded-lg px-3 py-2" onchange="this.form.submit()">
                        <option value="">All Levels</option>
                        <option value="easy" <?php echo $difficulty_filter == 'easy' ? 'selected' : ''; ?>>Easy</option>
                        <option value="medium" <?php echo $difficulty_filter == 'medium' ? 'selected' : ''; ?>>Medium</option>
                        <option value="hard" <?php echo $difficulty_filter == 'hard' ? 'selected' : ''; ?>>Hard</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Search</label>
                    <input type="text" name="search" value="<?php echo htmlspecialchars($search_filter); ?>" 
                           placeholder="Search questions..." class="w-full border rounded-lg px-3 py-2">
                </div>
                <div class="md:col-span-5 flex justify-end">
                    <button type="submit" class="bg-gray-600 text-white px-4 py-2 rounded-lg hover:bg-gray-700">
                        <i class="fas fa-search mr-2"></i> Filter
                    </button>
                    <a href="question-bank.php" class="ml-2 bg-gray-300 text-gray-700 px-4 py-2 rounded-lg hover:bg-gray-400">
                        <i class="fas fa-sync-alt mr-2"></i> Reset
                    </a>
                </div>
            </form>
        </div>

        <!-- Statistics -->
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
            <div class="bg-white rounded-xl p-3 text-center shadow-sm">
                <p class="text-gray-500 text-xs">Total Questions</p>
                <p class="text-xl font-bold"><?php echo $questions->num_rows; ?></p>
            </div>
            <div class="bg-white rounded-xl p-3 text-center shadow-sm">
                <p class="text-gray-500 text-xs">MCQ Questions</p>
                <p class="text-xl font-bold text-blue-600">
                    <?php 
                    $mcq_count = 0;
                    $questions->data_seek(0);
                    while($q = $questions->fetch_assoc()) {
                        if($q['question_type'] == 'mcq') $mcq_count++;
                    }
                    $questions->data_seek(0);
                    echo $mcq_count;
                    ?>
                </p>
            </div>
            <div class="bg-white rounded-xl p-3 text-center shadow-sm">
                <p class="text-gray-500 text-xs">Auto-Gradable</p>
                <p class="text-xl font-bold text-green-600">
                    <?php 
                    $auto_count = 0;
                    $questions->data_seek(0);
                    while($q = $questions->fetch_assoc()) {
                        if(in_array($q['question_type'], ['mcq', 'truefalse', 'fill_blanks'])) $auto_count++;
                    }
                    $questions->data_seek(0);
                    echo $auto_count;
                    ?>
                </p>
            </div>
            <div class="bg-white rounded-xl p-3 text-center shadow-sm">
                <p class="text-gray-500 text-xs">Times Used</p>
                <p class="text-xl font-bold text-purple-600">
                    <?php 
                    $total_used = 0;
                    $questions->data_seek(0);
                    while($q = $questions->fetch_assoc()) {
                        $total_used += $q['times_used'];
                    }
                    $questions->data_seek(0);
                    echo $total_used;
                    ?>
                </p>
            </div>
        </div>

        <!-- Questions List -->
        <div class="bg-white rounded-xl shadow-sm overflow-hidden">
            <div class="px-6 py-4 border-b bg-gray-50">
                <h3 class="text-lg font-semibold">Question Bank</h3>
            </div>
            <div class="divide-y divide-gray-200">
                <?php if ($questions && $questions->num_rows > 0): ?>
                    <?php while($q = $questions->fetch_assoc()): ?>
                        <div class="p-4 hover:bg-gray-50">
                            <div class="flex justify-between items-start">
                                <div class="flex-1">
                                    <div class="flex items-center space-x-2 mb-2">
                                        <span class="px-2 py-0.5 text-xs rounded-full 
                                            <?php echo $q['question_type'] == 'mcq' ? 'bg-blue-100 text-blue-700' : 
                                                     ($q['question_type'] == 'truefalse' ? 'bg-green-100 text-green-700' :
                                                     ($q['question_type'] == 'essay' ? 'bg-purple-100 text-purple-700' : 'bg-gray-100 text-gray-700')); ?>">
                                            <?php echo ucfirst(str_replace('_', ' ', $q['question_type'])); ?>
                                        </span>
                                        <span class="px-2 py-0.5 text-xs rounded-full 
                                            <?php echo $q['difficulty'] == 'easy' ? 'bg-green-100 text-green-700' : 
                                                     ($q['difficulty'] == 'medium' ? 'bg-yellow-100 text-yellow-700' : 'bg-red-100 text-red-700'); ?>">
                                            <?php echo ucfirst($q['difficulty']); ?>
                                        </span>
                                        <span class="text-xs text-gray-400">Marks: <?php echo $q['marks']; ?></span>
                                        <span class="text-xs text-gray-400">Used: <?php echo $q['times_used']; ?> times</span>
                                    </div>
                                    <p class="font-medium text-gray-800"><?php echo htmlspecialchars($q['question_text']); ?></p>
                                    <?php if($q['topic']): ?>
                                        <p class="text-xs text-gray-500 mt-1">Topic: <?php echo htmlspecialchars($q['topic']); ?></p>
                                    <?php endif; ?>
                                    <?php if($q['question_type'] == 'mcq' && $q['options']): 
                                        $options = json_decode($q['options'], true);
                                    ?>
                                        <div class="mt-2 text-sm text-gray-600">
                                            <p class="text-xs text-gray-400">Options:</p>
                                            <ul class="list-disc list-inside ml-2">
                                                <?php foreach($options as $key => $opt): ?>
                                                    <li class="text-xs"><?php echo $key . ': ' . htmlspecialchars($opt); ?></li>
                                                <?php endforeach; ?>
                                            </ul>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div class="flex space-x-2 ml-4">
                                    <?php if(isset($_GET['exam_id'])): ?>
                                        <a href="?add_to_exam=<?php echo $q['id']; ?>&exam_id=<?php echo $_GET['exam_id']; ?>" 
                                           class="bg-green-600 text-white px-3 py-1 rounded-lg text-sm hover:bg-green-700"
                                           title="Add to Current Exam">
                                            <i class="fas fa-plus"></i> Add
                                        </a>
                                    <?php endif; ?>
                                    <a href="edit-bank-question.php?id=<?php echo $q['id']; ?>" class="text-blue-600 hover:text-blue-800 p-1">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <a href="?delete=<?php echo $q['id']; ?>" onclick="return confirm('Delete this question from bank?')" class="text-red-600 hover:text-red-800 p-1">
                                        <i class="fas fa-trash-alt"></i>
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="p-12 text-center">
                        <i class="fas fa-database text-6xl text-gray-300 mb-4"></i>
                        <h3 class="text-xl font-semibold text-gray-600">No Questions in Bank</h3>
                        <p class="text-gray-400 mt-2">Click "Add Question to Bank" to start building your question bank</p>
                        <a href="add-to-bank.php" class="inline-block mt-4 text-blue-600 hover:text-blue-800">
                            <i class="fas fa-plus mr-1"></i> Add Question
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <?php if(isset($_GET['exam_id'])): ?>
            <div class="mt-4 flex justify-end">
                <a href="questions.php?id=<?php echo $_GET['exam_id']; ?>" class="text-blue-600 hover:text-blue-800">
                    ← Back to Exam Questions
                </a>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>