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

// Get teacher ID
$teacher_query = $conn->prepare("SELECT id FROM teachers WHERE user_id = ?");
$teacher_query->bind_param("i", $_SESSION['user_id']);
$teacher_query->execute();
$teacher = $teacher_query->get_result()->fetch_assoc();
$teacher_id = $teacher['id'] ?? 0;

// Get exam details
$exam_query = $conn->prepare("
    SELECT te.*, s.name as subject_name, c.name as class_name
    FROM teacher_exams te
    JOIN subjects s ON te.subject_id = s.id
    JOIN classes c ON te.class_id = c.id
    WHERE te.id = ? AND te.teacher_id = ?
");
$exam_query->bind_param("ii", $exam_id, $teacher_id);
$exam_query->execute();
$exam = $exam_query->get_result()->fetch_assoc();

if (!$exam) {
    header('Location: index.php');
    exit();
}

// Get classes and subjects taught
$teaching = $conn->prepare("
    SELECT DISTINCT c.id as class_id, c.name as class_name, 
           s.id as subject_id, s.name as subject_name
    FROM class_subject cs
    JOIN classes c ON cs.class_id = c.id
    JOIN subjects s ON cs.subject_id = s.id
    WHERE cs.teacher_id = ?
    ORDER BY c.name, s.name
");
$teaching->bind_param("i", $teacher_id);
$teaching->execute();
$teaching = $teaching->get_result();

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = sanitizeInput($_POST['title']);
    $description = sanitizeInput($_POST['description']);
    $instructions = sanitizeInput($_POST['instructions']);
    $exam_type = $_POST['exam_type'];
    $term = $_POST['term'];
    $academic_year = intval($_POST['academic_year']);
    $total_marks = intval($_POST['total_marks']);
    $passing_marks = intval($_POST['passing_marks']);
    $duration_minutes = intval($_POST['duration_minutes']);
    $start_date = $_POST['start_date'];
    $start_time = $_POST['start_time'];
    $end_date = $_POST['end_date'];
    $end_time = $_POST['end_time'];
    $allow_late = isset($_POST['allow_late']) ? 1 : 0;
    $late_penalty = intval($_POST['late_penalty']);
    $randomize_q = isset($_POST['randomize_questions']) ? 1 : 0;
    $randomize_a = isset($_POST['randomize_answers']) ? 1 : 0;
    $disable_copy = isset($_POST['disable_copy_paste']) ? 1 : 0;
    $fullscreen = isset($_POST['fullscreen_required']) ? 1 : 0;
    $prevent_tab = isset($_POST['prevent_tab_switch']) ? 1 : 0;
    $max_attempts = intval($_POST['max_attempts']);
    
    $update = $conn->prepare("
        UPDATE teacher_exams SET 
            title = ?, description = ?, instructions = ?, exam_type = ?, term = ?, 
            academic_year = ?, total_marks = ?, passing_marks = ?, duration_minutes = ?,
            start_date = ?, start_time = ?, end_date = ?, end_time = ?,
            allow_late_submission = ?, late_penalty = ?, randomize_questions = ?,
            randomize_answers = ?, disable_copy_paste = ?, fullscreen_required = ?,
            prevent_tab_switch = ?, max_attempts = ?
        WHERE id = ? AND teacher_id = ?
    ");
    
    $update->bind_param(
        "sssssiiiiisssiiiiiiiiii",
        $title, $description, $instructions, $exam_type, $term,
        $academic_year, $total_marks, $passing_marks, $duration_minutes,
        $start_date, $start_time, $end_date, $end_time,
        $allow_late, $late_penalty, $randomize_q,
        $randomize_a, $disable_copy, $fullscreen, $prevent_tab,
        $max_attempts, $exam_id, $teacher_id
    );
    
    if ($update->execute()) {
        logActivity($_SESSION['user_id'], 'updated exam', 'teacher_exams', $exam_id);
        $success = "Exam updated successfully!";
        
        // Refresh exam data
        $exam_query->execute();
        $exam = $exam_query->get_result()->fetch_assoc();
    } else {
        $error = "Failed to update exam: " . $conn->error;
    }
}

$page_title = 'Edit Exam - ' . $exam['title'];
include '../../includes/header.php';
include '../../includes/sidebar.php';
include '../../includes/navbar.php';
?>

<div class="ml-64 mt-16 p-6 bg-gray-50 min-h-screen">
    <div class="max-w-3xl mx-auto">
        <div class="mb-6 flex justify-between items-center">
            <div>
                <h1 class="text-2xl font-bold text-gray-800">✏️ Edit Examination</h1>
                <p class="text-gray-500 mt-1">Update exam settings</p>
            </div>
            <a href="index.php" class="text-blue-600 hover:text-blue-800">
                <i class="fas fa-arrow-left mr-2"></i> Back
            </a>
        </div>

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

        <div class="bg-white rounded-xl shadow-sm p-6">
            <form method="POST">
                <!-- Basic Information -->
                <div class="border-b pb-4 mb-4">
                    <h3 class="text-lg font-semibold mb-4">Basic Information</h3>
                    <div class="grid grid-cols-2 gap-4">
                        <div class="col-span-2">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Exam Title *</label>
                            <input type="text" name="title" required value="<?php echo htmlspecialchars($exam['title']); ?>"
                                   class="w-full border rounded-lg px-3 py-2">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Class</label>
                            <input type="text" value="<?php echo htmlspecialchars($exam['class_name']); ?>" disabled
                                   class="w-full border rounded-lg px-3 py-2 bg-gray-50">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Subject</label>
                            <input type="text" value="<?php echo htmlspecialchars($exam['subject_name']); ?>" disabled
                                   class="w-full border rounded-lg px-3 py-2 bg-gray-50">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Exam Type</label>
                            <select name="exam_type" class="w-full border rounded-lg px-3 py-2">
                                <option value="quiz" <?php echo $exam['exam_type'] == 'quiz' ? 'selected' : ''; ?>>Quiz</option>
                                <option value="midterm" <?php echo $exam['exam_type'] == 'midterm' ? 'selected' : ''; ?>>Midterm</option>
                                <option value="monthly" <?php echo $exam['exam_type'] == 'monthly' ? 'selected' : ''; ?>>Monthly Test</option>
                                <option value="final" <?php echo $exam['exam_type'] == 'final' ? 'selected' : ''; ?>>Final Exam</option>
                                <option value="mock" <?php echo $exam['exam_type'] == 'mock' ? 'selected' : ''; ?>>Mock Exam</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Term</label>
                            <select name="term" class="w-full border rounded-lg px-3 py-2">
                                <option value="term1" <?php echo $exam['term'] == 'term1' ? 'selected' : ''; ?>>Term 1</option>
                                <option value="term2" <?php echo $exam['term'] == 'term2' ? 'selected' : ''; ?>>Term 2</option>
                                <option value="term3" <?php echo $exam['term'] == 'term3' ? 'selected' : ''; ?>>Term 3</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Academic Year</label>
                            <select name="academic_year" class="w-full border rounded-lg px-3 py-2">
                                <?php for($y = 2020; $y <= 2030; $y++): ?>
                                    <option value="<?php echo $y; ?>" <?php echo $exam['academic_year'] == $y ? 'selected' : ''; ?>><?php echo $y; ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        <div class="col-span-2">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                            <textarea name="description" rows="2" class="w-full border rounded-lg px-3 py-2"><?php echo htmlspecialchars($exam['description']); ?></textarea>
                        </div>
                        <div class="col-span-2">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Instructions</label>
                            <textarea name="instructions" rows="3" class="w-full border rounded-lg px-3 py-2"><?php echo htmlspecialchars($exam['instructions']); ?></textarea>
                        </div>
                    </div>
                </div>

                <!-- Marking Settings -->
                <div class="border-b pb-4 mb-4">
                    <h3 class="text-lg font-semibold mb-4">Marking & Grading</h3>
                    <div class="grid grid-cols-3 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Total Marks</label>
                            <input type="number" name="total_marks" value="<?php echo $exam['total_marks']; ?>" class="w-full border rounded-lg px-3 py-2">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Passing Marks</label>
                            <input type="number" name="passing_marks" value="<?php echo $exam['passing_marks']; ?>" class="w-full border rounded-lg px-3 py-2">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Duration (minutes)</label>
                            <input type="number" name="duration_minutes" value="<?php echo $exam['duration_minutes']; ?>" class="w-full border rounded-lg px-3 py-2">
                        </div>
                    </div>
                </div>

                <!-- Scheduling -->
                <div class="border-b pb-4 mb-4">
                    <h3 class="text-lg font-semibold mb-4">Exam Schedule</h3>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Start Date</label>
                            <input type="date" name="start_date" value="<?php echo $exam['start_date']; ?>" class="w-full border rounded-lg px-3 py-2">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Start Time</label>
                            <input type="time" name="start_time" value="<?php echo $exam['start_time']; ?>" class="w-full border rounded-lg px-3 py-2">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">End Date</label>
                            <input type="date" name="end_date" value="<?php echo $exam['end_date']; ?>" class="w-full border rounded-lg px-3 py-2">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">End Time</label>
                            <input type="time" name="end_time" value="<?php echo $exam['end_time']; ?>" class="w-full border rounded-lg px-3 py-2">
                        </div>
                    </div>
                    <div class="mt-3 flex items-center space-x-4">
                        <label class="flex items-center">
                            <input type="checkbox" name="allow_late" <?php echo $exam['allow_late_submission'] ? 'checked' : ''; ?> class="rounded">
                            <span class="ml-2 text-sm">Allow late submissions</span>
                        </label>
                        <label class="flex items-center">
                            <span class="text-sm">Late penalty (%)</span>
                            <input type="number" name="late_penalty" value="<?php echo $exam['late_penalty']; ?>" class="ml-2 w-20 border rounded px-2 py-1">
                        </label>
                    </div>
                </div>

                <!-- Security Settings -->
                <div class="border-b pb-4 mb-4">
                    <h3 class="text-lg font-semibold mb-4">Security & Anti-Cheating</h3>
                    <div class="grid grid-cols-2 gap-4">
                        <label class="flex items-center">
                            <input type="checkbox" name="randomize_questions" <?php echo $exam['randomize_questions'] ? 'checked' : ''; ?> class="rounded">
                            <span class="ml-2 text-sm">Randomize questions</span>
                        </label>
                        <label class="flex items-center">
                            <input type="checkbox" name="randomize_answers" <?php echo $exam['randomize_answers'] ? 'checked' : ''; ?> class="rounded">
                            <span class="ml-2 text-sm">Randomize answer choices</span>
                        </label>
                        <label class="flex items-center">
                            <input type="checkbox" name="disable_copy_paste" <?php echo $exam['disable_copy_paste'] ? 'checked' : ''; ?> class="rounded">
                            <span class="ml-2 text-sm">Disable copy/paste</span>
                        </label>
                        <label class="flex items-center">
                            <input type="checkbox" name="fullscreen_required" <?php echo $exam['fullscreen_required'] ? 'checked' : ''; ?> class="rounded">
                            <span class="ml-2 text-sm">Fullscreen mode required</span>
                        </label>
                        <label class="flex items-center">
                            <input type="checkbox" name="prevent_tab_switch" <?php echo $exam['prevent_tab_switch'] ? 'checked' : ''; ?> class="rounded">
                            <span class="ml-2 text-sm">Prevent tab switching</span>
                        </label>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Max Attempts</label>
                            <select name="max_attempts" class="w-full border rounded-lg px-3 py-2">
                                <option value="1" <?php echo $exam['max_attempts'] == 1 ? 'selected' : ''; ?>>1 attempt only</option>
                                <option value="2" <?php echo $exam['max_attempts'] == 2 ? 'selected' : ''; ?>>2 attempts</option>
                                <option value="3" <?php echo $exam['max_attempts'] == 3 ? 'selected' : ''; ?>>3 attempts</option>
                                <option value="0" <?php echo $exam['max_attempts'] == 0 ? 'selected' : ''; ?>>Unlimited</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="flex justify-end space-x-3 mt-6 pt-4 border-t">
                    <a href="index.php" class="px-4 py-2 border rounded-lg hover:bg-gray-50">
                        <i class="fas fa-times mr-2"></i> Cancel
                    </a>
                    <a href="questions.php?id=<?php echo $exam_id; ?>" class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700">
                        <i class="fas fa-question-circle mr-2"></i> Manage Questions
                    </a>
                    <button type="submit" class="px-6 py-2 bg-gradient-to-r from-blue-500 to-purple-600 text-white rounded-lg hover:shadow-lg transition">
                        <i class="fas fa-save mr-2"></i> Save Changes
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>