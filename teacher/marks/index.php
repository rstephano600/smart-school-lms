<?php
require_once '../../config.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';
requireRole('teacher');

$page_title = 'Marks Management';
include '../../includes/header.php';
include '../../includes/sidebar.php';
include '../../includes/navbar.php';

// Get teacher ID
$teacher_query = $conn->prepare("SELECT id FROM teachers WHERE user_id = ?");
$teacher_query->bind_param("i", $_SESSION['user_id']);
$teacher_query->execute();
$teacher_result = $teacher_query->get_result();
$teacher = $teacher_result->fetch_assoc();
$teacher_id = $teacher['id'] ?? 0;

// Get classes taught by this teacher
$classes = $conn->prepare("
    SELECT DISTINCT c.id, c.name 
    FROM class_subject cs
    JOIN classes c ON cs.class_id = c.id
    WHERE cs.teacher_id = ?
    ORDER BY c.name
");
$classes->bind_param("i", $teacher_id);
$classes->execute();
$classes_result = $classes->get_result();

// Get subjects taught by this teacher
$subjects = $conn->prepare("
    SELECT DISTINCT s.id, s.name, s.code
    FROM class_subject cs
    JOIN subjects s ON cs.subject_id = s.id
    WHERE cs.teacher_id = ?
    ORDER BY s.name
");
$subjects->bind_param("i", $teacher_id);
$subjects->execute();
$subjects_result = $subjects->get_result();

// Get filter parameters (no redirects, just default values)
$class_id = isset($_GET['class_id']) ? intval($_GET['class_id']) : 0;
$subject_id = isset($_GET['subject_id']) ? intval($_GET['subject_id']) : 0;
$term = isset($_GET['term']) ? $_GET['term'] : date('Y') . '-term1';
$academic_year = explode('-', $term)[0];
$term_value = explode('-', $term)[1] ?? 'term1';

// Get students for selected class
$students = [];
$marks_data = [];
$class_name = '';
$subject_name = '';

if ($class_id > 0 && $subject_id > 0) {
    // Get class name
    $class_query = $conn->prepare("SELECT name FROM classes WHERE id = ?");
    $class_query->bind_param("i", $class_id);
    $class_query->execute();
    $class_name_result = $class_query->get_result();
    $class_name = $class_name_result->fetch_assoc()['name'] ?? '';
    
    // Get subject name
    $subj_query = $conn->prepare("SELECT name FROM subjects WHERE id = ?");
    $subj_query->bind_param("i", $subject_id);
    $subj_query->execute();
    $subject_name_result = $subj_query->get_result();
    $subject_name = $subject_name_result->fetch_assoc()['name'] ?? '';
    
    // Get students
    $students_query = $conn->prepare("
        SELECT s.id, s.admission_number, CONCAT(u.first_name, ' ', u.last_name) as name
        FROM students s
        JOIN users u ON s.user_id = u.id
        WHERE s.class_id = ?
        ORDER BY u.first_name
    ");
    $students_query->bind_param("i", $class_id);
    $students_query->execute();
    $students = $students_query->get_result();
    
    // Get existing marks
    $marks_query = $conn->prepare("
        SELECT student_id, marks_obtained, max_marks, percentage, grade, assessment_type, remarks
        FROM marks
        WHERE class_id = ? AND subject_id = ? AND term = ? AND academic_year = ?
    ");
    $marks_query->bind_param("iiss", $class_id, $subject_id, $term_value, $academic_year);
    $marks_query->execute();
    $existing_marks = $marks_query->get_result();
    while ($row = $existing_marks->fetch_assoc()) {
        $marks_data[$row['student_id']] = $row;
    }
}

// Handle form submission
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_marks'])) {
    $class_id = intval($_POST['class_id']);
    $subject_id = intval($_POST['subject_id']);
    $term_value = $_POST['term_value'];
    $academic_year = intval($_POST['academic_year']);
    $assessment_type = $_POST['assessment_type'];
    $max_marks = intval($_POST['max_marks']);
    
    $student_ids = $_POST['student_id'] ?? [];
    $marks_obtained = $_POST['marks'] ?? [];
    $remarks_list = $_POST['remarks'] ?? [];
    
    foreach ($student_ids as $index => $student_id) {
        $marks = floatval($marks_obtained[$index] ?? 0);
        $remarks = sanitizeInput($remarks_list[$index] ?? '');
        
        $percentage = ($marks / $max_marks) * 100;
        
        // Calculate grade based on level
        $level_query = $conn->prepare("
            SELECT CASE 
                WHEN c.name LIKE 'Form 5%' OR c.name LIKE 'Form 6%' THEN 'alevel'
                ELSE 'olevel'
            END as level
            FROM students s
            JOIN classes c ON s.class_id = c.id
            WHERE s.id = ?
        ");
        $level_query->bind_param("i", $student_id);
        $level_query->execute();
        $level_result = $level_query->get_result();
        $level = $level_result->fetch_assoc()['level'] ?? 'olevel';
        
        if ($level == 'olevel') {
            if ($percentage >= 75) { $grade = 'A'; $points = 5; }
            elseif ($percentage >= 65) { $grade = 'B'; $points = 4; }
            elseif ($percentage >= 45) { $grade = 'C'; $points = 3; }
            elseif ($percentage >= 30) { $grade = 'D'; $points = 2; }
            else { $grade = 'F'; $points = 0; }
        } else {
            if ($percentage >= 80) { $grade = 'A'; $points = 5; }
            elseif ($percentage >= 70) { $grade = 'B'; $points = 4; }
            elseif ($percentage >= 60) { $grade = 'C'; $points = 3; }
            elseif ($percentage >= 50) { $grade = 'D'; $points = 2; }
            elseif ($percentage >= 40) { $grade = 'E'; $points = 1; }
            elseif ($percentage >= 35) { $grade = 'S'; $points = 0.5; }
            else { $grade = 'F'; $points = 0; }
        }
        
        // Check if mark already exists
        $check = $conn->prepare("
            SELECT id FROM marks 
            WHERE student_id = ? AND subject_id = ? AND class_id = ? AND term = ? AND academic_year = ? AND assessment_type = ?
        ");
        $check->bind_param("iiiiss", $student_id, $subject_id, $class_id, $term_value, $academic_year, $assessment_type);
        $check->execute();
        
        if ($check->get_result()->num_rows > 0) {
            // Update existing
            $update = $conn->prepare("
                UPDATE marks SET marks_obtained = ?, max_marks = ?, percentage = ?, grade = ?, points = ?, remarks = ?, recorded_by = ?
                WHERE student_id = ? AND subject_id = ? AND class_id = ? AND term = ? AND academic_year = ? AND assessment_type = ?
            ");
            $update->bind_param("ddddsiiiiiss", $marks, $max_marks, $percentage, $grade, $points, $remarks, $_SESSION['user_id'], 
                               $student_id, $subject_id, $class_id, $term_value, $academic_year, $assessment_type);
            $update->execute();
        } else {
            // Insert new
            $insert = $conn->prepare("
                INSERT INTO marks (student_id, subject_id, class_id, marks_obtained, max_marks, percentage, grade, points, 
                                   term, academic_year, assessment_type, remarks, recorded_by)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $insert->bind_param("iiiddddsiissi", $student_id, $subject_id, $class_id, $marks, $max_marks, $percentage, $grade, $points,
                               $term_value, $academic_year, $assessment_type, $remarks, $_SESSION['user_id']);
            $insert->execute();
        }
    }
    
    $success = "Marks saved successfully!";
    
    // Redirect to avoid form resubmission
    header("Location: index.php?class_id=$class_id&subject_id=$subject_id&term=" . $academic_year . "-" . $term_value . "&saved=1");
    exit();
}

// Check if just saved
$show_success = isset($_GET['saved']) ? true : false;
?>

<div class="ml-64 mt-16 p-6">
    <div class="max-w-full mx-auto">
        <div class="mb-6">
            <h1 class="text-2xl font-bold text-gray-800">Marks Management</h1>
            <p class="text-gray-500 mt-1">Record and manage student marks</p>
        </div>

        <?php if($error): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg mb-4"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <?php if($show_success): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-lg mb-4">Marks saved successfully!</div>
        <?php endif; ?>

        <!-- Filters -->
        <div class="bg-white rounded-xl shadow-sm p-4 mb-6">
            <form method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Class</label>
                    <select name="class_id" id="class_id" class="w-full border rounded-lg px-3 py-2">
                        <option value="0">Select Class</option>
                        <?php 
                        $classes_result->data_seek(0);
                        while($class = $classes_result->fetch_assoc()): ?>
                            <option value="<?php echo $class['id']; ?>" <?php echo $class_id == $class['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($class['name']); ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Subject</label>
                    <select name="subject_id" id="subject_id" class="w-full border rounded-lg px-3 py-2">
                        <option value="0">Select Subject</option>
                        <?php 
                        $subjects_result->data_seek(0);
                        while($subject = $subjects_result->fetch_assoc()): ?>
                            <option value="<?php echo $subject['id']; ?>" <?php echo $subject_id == $subject['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($subject['name']); ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Term</label>
                    <select name="term" class="w-full border rounded-lg px-3 py-2">
                        <option value="<?php echo date('Y'); ?>-term1" <?php echo $term == date('Y') . '-term1' ? 'selected' : ''; ?>><?php echo date('Y'); ?> - Term 1</option>
                        <option value="<?php echo date('Y'); ?>-term2" <?php echo $term == date('Y') . '-term2' ? 'selected' : ''; ?>><?php echo date('Y'); ?> - Term 2</option>
                        <option value="<?php echo date('Y'); ?>-term3" <?php echo $term == date('Y') . '-term3' ? 'selected' : ''; ?>><?php echo date('Y'); ?> - Term 3</option>
                        <option value="<?php echo date('Y')+1; ?>-term1" <?php echo $term == (date('Y')+1) . '-term1' ? 'selected' : ''; ?>><?php echo date('Y')+1; ?> - Term 1</option>
                    </select>
                </div>
                <div class="flex items-end">
                    <button type="submit" class="w-full bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700">
                        <i class="fas fa-search mr-2"></i> Load Students
                    </button>
                </div>
            </form>
        </div>

        <?php if ($class_id > 0 && $subject_id > 0 && $students && $students->num_rows > 0): ?>
            <!-- Info Bar -->
            <div class="bg-blue-50 rounded-xl p-3 mb-4 flex justify-between items-center">
                <div>
                    <i class="fas fa-info-circle text-blue-600 mr-2"></i>
                    <span class="text-sm">Enter marks for <strong><?php echo htmlspecialchars($subject_name); ?></strong> - <strong><?php echo htmlspecialchars($class_name); ?></strong></span>
                </div>
                <div class="text-sm text-gray-600">
                    Total Students: <strong><?php echo $students->num_rows; ?></strong>
                </div>
            </div>

            <!-- Marks Entry Form -->
            <div class="bg-white rounded-xl shadow-sm overflow-hidden">
                <form method="POST">
                    <input type="hidden" name="save_marks" value="1">
                    <input type="hidden" name="class_id" value="<?php echo $class_id; ?>">
                    <input type="hidden" name="subject_id" value="<?php echo $subject_id; ?>">
                    <input type="hidden" name="term_value" value="<?php echo $term_value; ?>">
                    <input type="hidden" name="academic_year" value="<?php echo $academic_year; ?>">
                    
                    <div class="p-4 border-b bg-gray-50 flex justify-between items-center">
                        <div class="flex items-center space-x-4">
                            <div>
                                <label class="text-sm font-medium">Assessment Type:</label>
                                <select name="assessment_type" class="ml-2 border rounded-lg px-2 py-1 text-sm">
                                    <option value="exam">📝 Examination</option>
                                    <option value="assignment">📚 Assignment</option>
                                    <option value="quiz">❓ Quiz</option>
                                    <option value="classwork">📖 Classwork</option>
                                    <option value="homework">🏠 Homework</option>
                                </select>
                            </div>
                            <div>
                                <label class="text-sm font-medium">Max Marks:</label>
                                <input type="number" name="max_marks" value="100" min="1" class="ml-2 w-24 border rounded-lg px-2 py-1 text-sm">
                            </div>
                        </div>
                        <button type="submit" class="bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700">
                            <i class="fas fa-save mr-2"></i> Save All Marks
                        </button>
                    </div>
                    
                    <div class="overflow-x-auto">
                        <table class="w-full">
                            <thead class="bg-gray-50 border-b">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Admission No</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Student Name</th>
                                    <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">Marks</th>
                                    <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">%</th>
                                    <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">Grade</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Remarks</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200">
                                <?php while($student = $students->fetch_assoc()): 
                                    $current_marks = $marks_data[$student['id']] ?? null;
                                    $marks_value = $current_marks['marks_obtained'] ?? '';
                                    $percentage = $current_marks['percentage'] ?? 0;
                                    $grade = $current_marks['grade'] ?? '';
                                    $remarks = $current_marks['remarks'] ?? '';
                                ?>
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-6 py-3"><?php echo $student['admission_number']; ?></td>
                                        <td class="px-6 py-3 font-medium"><?php echo htmlspecialchars($student['name']); ?></td>
                                        <td class="px-6 py-3 text-center">
                                            <input type="hidden" name="student_id[]" value="<?php echo $student['id']; ?>">
                                            <input type="number" name="marks[]" value="<?php echo $marks_value; ?>" 
                                                   step="0.5" min="0" class="w-24 border rounded-lg px-2 py-1 text-center marks-input">
                                        </td>
                                        <td class="px-6 py-3 text-center">
                                            <span class="percentage-display font-semibold"><?php echo $percentage > 0 ? round($percentage, 1) . '%' : '-'; ?></span>
                                        </td>
                                        <td class="px-6 py-3 text-center">
                                            <span class="grade-display px-2 py-1 text-xs rounded-full 
                                                <?php echo $grade == 'A' ? 'bg-green-100 text-green-700' : 
                                                         ($grade == 'B' ? 'bg-blue-100 text-blue-700' :
                                                         ($grade == 'C' ? 'bg-yellow-100 text-yellow-700' : 'bg-red-100 text-red-700')); ?>">
                                                <?php echo $grade ?: '-'; ?>
                                            </span>
                                        </td>
                                        <td class="px-6 py-3">
                                            <input type="text" name="remarks[]" value="<?php echo htmlspecialchars($remarks); ?>" 
                                                   placeholder="Optional" class="w-full border rounded-lg px-2 py-1 text-sm">
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </form>
            </div>
        <?php elseif ($class_id > 0 && $subject_id > 0): ?>
            <div class="bg-white rounded-xl shadow-sm p-12 text-center">
                <i class="fas fa-users text-6xl text-gray-300 mb-4"></i>
                <p class="text-gray-500">No students found in this class</p>
            </div>
        <?php elseif ($class_id > 0 && $subject_id == 0): ?>
            <div class="bg-white rounded-xl shadow-sm p-12 text-center">
                <i class="fas fa-book text-6xl text-gray-300 mb-4"></i>
                <p class="text-gray-500">Please select a subject</p>
            </div>
        <?php else: ?>
            <div class="bg-white rounded-xl shadow-sm p-12 text-center">
                <i class="fas fa-chart-line text-6xl text-gray-300 mb-4"></i>
                <p class="text-gray-500">Select a class and subject to enter marks</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
// Live calculation of percentage and grade
document.querySelectorAll('.marks-input').forEach(input => {
    input.addEventListener('input', function() {
        const marks = parseFloat(this.value) || 0;
        const percentage = marks;
        
        const row = this.closest('tr');
        const percentageSpan = row.querySelector('.percentage-display');
        const gradeSpan = row.querySelector('.grade-display');
        
        percentageSpan.textContent = percentage.toFixed(1) + '%';
        
        let grade = '';
        let gradeClass = '';
        
        if (percentage >= 75) { grade = 'A'; gradeClass = 'bg-green-100 text-green-700'; }
        else if (percentage >= 65) { grade = 'B'; gradeClass = 'bg-blue-100 text-blue-700'; }
        else if (percentage >= 45) { grade = 'C'; gradeClass = 'bg-yellow-100 text-yellow-700'; }
        else if (percentage >= 30) { grade = 'D'; gradeClass = 'bg-orange-100 text-orange-700'; }
        else { grade = 'F'; gradeClass = 'bg-red-100 text-red-700'; }
        
        gradeSpan.textContent = grade;
        gradeSpan.className = `grade-display px-2 py-1 text-xs rounded-full ${gradeClass}`;
    });
});
</script>

<script>
// Load subjects when class is selected - using JavaScript to avoid redirects
document.getElementById('class_id').addEventListener('change', function() {
    // This just helps - form submission will handle the actual filter
    // No redirects here
});
</script>

<?php include '../../includes/footer.php'; ?>