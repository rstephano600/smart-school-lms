<?php
require_once '../../config.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';
requireRole('teacher');

$page_title = 'Create Examination';
include '../../includes/header.php';
include '../../includes/sidebar.php';
include '../../includes/navbar.php';

// Get teacher ID
$teacher_query = $conn->prepare("SELECT id FROM teachers WHERE user_id = ?");
$teacher_query->bind_param("i", $_SESSION['user_id']);
$teacher_query->execute();
$teacher = $teacher_query->get_result()->fetch_assoc();
$teacher_id = $teacher['id'] ?? 0;

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
    $subject_id = intval($_POST['subject_id']);
    $class_id = intval($_POST['class_id']);
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
    $exam_password = !empty($_POST['exam_password']) ? password_hash($_POST['exam_password'], PASSWORD_DEFAULT) : null;
    $max_attempts = intval($_POST['max_attempts']);
    
    $insert = $conn->prepare("
        INSERT INTO teacher_exams (
            teacher_id, subject_id, class_id, title, description, instructions,
            exam_type, term, academic_year, total_marks, passing_marks, duration_minutes,
            start_date, start_time, end_date, end_time, allow_late_submission, late_penalty,
            randomize_questions, randomize_answers, disable_copy_paste, fullscreen_required,
            prevent_tab_switch, exam_password, max_attempts, is_published
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0)
    ");
    
    $insert->bind_param(
        "iiisssssiiiiisssiiiiiiisi",
        $teacher_id, $subject_id, $class_id, $title, $description, $instructions,
        $exam_type, $term, $academic_year, $total_marks, $passing_marks, $duration_minutes,
        $start_date, $start_time, $end_date, $end_time, $allow_late, $late_penalty,
        $randomize_q, $randomize_a, $disable_copy, $fullscreen, $prevent_tab, $exam_password, $max_attempts
    );
    
    if ($insert->execute()) {
        $exam_id = $conn->insert_id;
        logActivity($_SESSION['user_id'], 'created exam', 'teacher_exams', $exam_id);
        header("Location: questions.php?id=$exam_id&success=1");
        exit();
    } else {
        $error = "Failed to create exam: " . $conn->error;
    }
}
?>

<div class="ml-64 mt-16 p-6 bg-gray-50 min-h-screen">
    <div class="max-w-4xl mx-auto">
        <div class="mb-6">
            <h1 class="text-2xl font-bold text-gray-800">Create Examination</h1>
            <p class="text-gray-500 mt-1">Set up a new exam with full configuration</p>
        </div>

        <?php if($error): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg mb-4">
                <i class="fas fa-exclamation-circle mr-2"></i> <?php echo $error; ?>
            </div>
        <?php endif; ?>

        <div class="bg-white rounded-xl shadow-sm p-6">
            <form method="POST" enctype="multipart/form-data">
                <!-- Basic Information -->
                <div class="border-b pb-4 mb-4">
                    <h3 class="text-lg font-semibold mb-4 flex items-center">
                        <i class="fas fa-info-circle text-blue-500 mr-2"></i> Basic Information
                    </h3>
                    <div class="grid grid-cols-2 gap-4">
                        <div class="col-span-2">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Exam Title *</label>
                            <input type="text" name="title" required value="<?php echo htmlspecialchars($_POST['title'] ?? ''); ?>"
                                   placeholder="e.g., Mid-Term Mathematics, Biology Quiz"
                                   class="w-full border rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Subject *</label>
                            <select name="subject_id" id="subject_id" required class="w-full border rounded-lg px-3 py-2">
                                <option value="">Select Subject</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Class *</label>
                            <select name="class_id" id="class_id" required class="w-full border rounded-lg px-3 py-2">
                                <option value="">Select Class</option>
                                <?php 
                                $teaching->data_seek(0);
                                $classes_list = [];
                                while($item = $teaching->fetch_assoc()):
                                    if (!in_array($item['class_id'], array_column($classes_list, 'id'))):
                                        $classes_list[] = ['id' => $item['class_id'], 'name' => $item['class_name']];
                                    endif;
                                endwhile;
                                foreach($classes_list as $class): ?>
                                    <option value="<?php echo $class['id']; ?>"><?php echo htmlspecialchars($class['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Exam Type</label>
                            <select name="exam_type" class="w-full border rounded-lg px-3 py-2">
                                <option value="quiz">📝 Quiz</option>
                                <option value="midterm">📚 Midterm</option>
                                <option value="monthly">📊 Monthly Test</option>
                                <option value="final">🎯 Final Exam</option>
                                <option value="mock">📋 Mock Exam</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Term</label>
                            <select name="term" class="w-full border rounded-lg px-3 py-2">
                                <option value="term1">Term 1</option>
                                <option value="term2">Term 2</option>
                                <option value="term3">Term 3</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Academic Year</label>
                            <select name="academic_year" class="w-full border rounded-lg px-3 py-2">
                                <?php for($y = 2020; $y <= 2030; $y++): ?>
                                    <option value="<?php echo $y; ?>" <?php echo $y == date('Y') ? 'selected' : ''; ?>><?php echo $y; ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        <div class="col-span-2">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                            <textarea name="description" rows="3" class="w-full border rounded-lg px-3 py-2" placeholder="Brief description of the exam..."><?php echo htmlspecialchars($_POST['description'] ?? ''); ?></textarea>
                        </div>
                        <div class="col-span-2">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Instructions for Students</label>
                            <textarea name="instructions" rows="3" class="w-full border rounded-lg px-3 py-2" placeholder="Exam rules, allowed materials, time management tips..."><?php echo htmlspecialchars($_POST['instructions'] ?? ''); ?></textarea>
                        </div>
                    </div>
                </div>

                <!-- Marking Settings -->
                <div class="border-b pb-4 mb-4">
                    <h3 class="text-lg font-semibold mb-4 flex items-center">
                        <i class="fas fa-star text-yellow-500 mr-2"></i> Marking & Grading
                    </h3>
                    <div class="grid grid-cols-3 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Total Marks</label>
                            <input type="number" name="total_marks" value="<?php echo $_POST['total_marks'] ?? 100; ?>" min="1" class="w-full border rounded-lg px-3 py-2">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Passing Marks</label>
                            <input type="number" name="passing_marks" value="<?php echo $_POST['passing_marks'] ?? 40; ?>" min="0" class="w-full border rounded-lg px-3 py-2">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Duration (minutes)</label>
                            <input type="number" name="duration_minutes" value="<?php echo $_POST['duration_minutes'] ?? 60; ?>" min="1" class="w-full border rounded-lg px-3 py-2">
                        </div>
                    </div>
                </div>

                <!-- Scheduling -->
                <div class="border-b pb-4 mb-4">
                    <h3 class="text-lg font-semibold mb-4 flex items-center">
                        <i class="fas fa-calendar-alt text-green-500 mr-2"></i> Exam Schedule
                    </h3>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Start Date</label>
                            <input type="date" name="start_date" value="<?php echo $_POST['start_date'] ?? date('Y-m-d'); ?>" class="w-full border rounded-lg px-3 py-2">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Start Time</label>
                            <input type="time" name="start_time" value="<?php echo $_POST['start_time'] ?? '09:00'; ?>" class="w-full border rounded-lg px-3 py-2">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">End Date</label>
                            <input type="date" name="end_date" value="<?php echo $_POST['end_date'] ?? date('Y-m-d', strtotime('+7 days')); ?>" class="w-full border rounded-lg px-3 py-2">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">End Time</label>
                            <input type="time" name="end_time" value="<?php echo $_POST['end_time'] ?? '17:00'; ?>" class="w-full border rounded-lg px-3 py-2">
                        </div>
                    </div>
                    <div class="mt-3 flex items-center space-x-4">
                        <label class="flex items-center">
                            <input type="checkbox" name="allow_late" class="rounded">
                            <span class="ml-2 text-sm">Allow late submissions</span>
                        </label>
                        <label class="flex items-center">
                            <span class="text-sm">Late penalty (%)</span>
                            <input type="number" name="late_penalty" value="10" class="ml-2 w-20 border rounded px-2 py-1">
                        </label>
                    </div>
                </div>

                <!-- Security Settings -->
                <div class="border-b pb-4 mb-4">
                    <h3 class="text-lg font-semibold mb-4 flex items-center">
                        <i class="fas fa-shield-alt text-red-500 mr-2"></i> Security & Anti-Cheating
                    </h3>
                    <div class="grid grid-cols-2 gap-4">
                        <label class="flex items-center">
                            <input type="checkbox" name="randomize_questions" class="rounded">
                            <span class="ml-2 text-sm">Randomize questions</span>
                        </label>
                        <label class="flex items-center">
                            <input type="checkbox" name="randomize_answers" class="rounded">
                            <span class="ml-2 text-sm">Randomize answer choices</span>
                        </label>
                        <label class="flex items-center">
                            <input type="checkbox" name="disable_copy_paste" class="rounded">
                            <span class="ml-2 text-sm">Disable copy/paste</span>
                        </label>
                        <label class="flex items-center">
                            <input type="checkbox" name="fullscreen_required" class="rounded">
                            <span class="ml-2 text-sm">Fullscreen mode required</span>
                        </label>
                        <label class="flex items-center">
                            <input type="checkbox" name="prevent_tab_switch" class="rounded">
                            <span class="ml-2 text-sm">Prevent tab switching</span>
                        </label>
                    </div>
                    <div class="mt-3">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Exam Password (Optional)</label>
                        <input type="text" name="exam_password" placeholder="Leave empty for no password" class="w-full border rounded-lg px-3 py-2">
                    </div>
                    <div class="mt-3">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Max Attempts</label>
                        <select name="max_attempts" class="w-full border rounded-lg px-3 py-2">
                            <option value="1">1 attempt only</option>
                            <option value="2">2 attempts</option>
                            <option value="3">3 attempts</option>
                            <option value="0">Unlimited</option>
                        </select>
                    </div>
                </div>

                <div class="flex justify-end space-x-3 mt-6 pt-4 border-t">
                    <a href="index.php" class="px-4 py-2 border rounded-lg hover:bg-gray-50">
                        <i class="fas fa-arrow-left mr-2"></i> Cancel
                    </a>
                    <button type="submit" class="px-6 py-2 bg-gradient-to-r from-blue-500 to-purple-600 text-white rounded-lg hover:shadow-lg transition-all">
                        <i class="fas fa-save mr-2"></i> Create Exam & Add Questions
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Load subjects when class is selected
document.getElementById('class_id').addEventListener('change', function() {
    const classId = this.value;
    const subjectSelect = document.getElementById('subject_id');
    
    if (classId) {
        <?php 
        $teaching->data_seek(0);
        $subjects_by_class = [];
        while($item = $teaching->fetch_assoc()) {
            $subjects_by_class[$item['class_id']][] = ['id' => $item['subject_id'], 'name' => $item['subject_name']];
        }
        ?>
        const subjectsByClass = <?php echo json_encode($subjects_by_class); ?>;
        
        subjectSelect.innerHTML = '<option value="">Select Subject</option>';
        if (subjectsByClass[classId]) {
            subjectsByClass[classId].forEach(subject => {
                subjectSelect.innerHTML += `<option value="${subject.id}">${subject.name}</option>`;
            });
        }
    } else {
        subjectSelect.innerHTML = '<option value="">Select Subject</option>';
    }
});

// Trigger on page load
if (document.getElementById('class_id').value) {
    document.getElementById('class_id').dispatchEvent(new Event('change'));
}
</script>

<?php include '../../includes/footer.php'; ?>