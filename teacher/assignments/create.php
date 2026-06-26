<?php
ob_start(); // Add this at the very top
require_once '../../config.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';
requireRole('teacher');

$page_title = 'Create Assignment';
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
    $rubric = sanitizeInput($_POST['rubric']);
    $class_id = intval($_POST['class_id']);
    $subject_id = intval($_POST['subject_id']);
    $assignment_type = $_POST['assignment_type'];
    $topic = sanitizeInput($_POST['topic']);
    $academic_term = $_POST['academic_term'];
    $due_date = $_POST['due_date'] . ' ' . $_POST['due_time'];
    $max_marks = intval($_POST['max_marks']);
    $allow_late = isset($_POST['allow_late']) ? 1 : 0;
    $late_penalty = intval($_POST['late_penalty']);
    $max_file_size = intval($_POST['max_file_size']);
    $allowed_files = isset($_POST['allowed_files']) ? implode(',', $_POST['allowed_files']) : 'pdf,docx';
    $status = $_POST['status'];
    
    if (empty($title) || empty($class_id) || empty($subject_id) || empty($due_date)) {
        $error = "Please fill all required fields";
    } else {
        $attachment_url = null;
        
        // Handle file upload if any
        if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] == 0) {
            $upload_result = uploadFile($_FILES['attachment'], ASSIGNMENTS_PATH, ['pdf', 'docx', 'jpg', 'png', 'pptx']);
            if ($upload_result['success']) {
                $attachment_url = 'uploads/assignments/' . $upload_result['filename'];
            }
        }
        
        $insert = $conn->prepare("
            INSERT INTO assignments (
                title, description, instructions, rubric, subject_id, class_id, 
                assignment_type, topic, academic_term, due_date, max_marks, 
                allow_late_submission, late_penalty, max_file_size, allowed_file_types, 
                attachment_url, status, created_by, published_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        
        $insert->bind_param(
            "ssssiissssiiiiisss",
            $title, $description, $instructions, $rubric, $subject_id, $class_id,
            $assignment_type, $topic, $academic_term, $due_date, $max_marks,
            $allow_late, $late_penalty, $max_file_size, $allowed_files,
            $attachment_url, $status, $_SESSION['user_id']
        );
        
        if ($insert->execute()) {
            $assignment_id = $conn->insert_id;
            logActivity($_SESSION['user_id'], 'created assignment', 'assignment', $assignment_id);
            
            // Create notifications if published
            if ($status == 'published') {
                createAssignmentNotifications($conn, $assignment_id, $class_id, $title, $due_date);
            }
            
            // Clear output buffer and redirect
            ob_clean();
            header("Location: index.php?success=1");
            exit();
        } else {
            $error = "Failed to create assignment: " . $conn->error;
        }
    }
}

function createAssignmentNotifications($conn, $assignment_id, $class_id, $title, $due_date) {
    $notify = $conn->prepare("
        INSERT INTO notifications (user_id, title, message, type, link)
        SELECT u.id, 'New Assignment', CONCAT('New assignment \"', ?, '\" has been posted. Due date: ', DATE_FORMAT(?, '%M %d, %Y')), 'assignment', ?
        FROM students s
        JOIN users u ON s.user_id = u.id
        WHERE s.class_id = ?
    ");
    $link = "/smart-school-lms/student/assignments/view.php?id=" . $assignment_id;
    $notify->bind_param("sssi", $title, $due_date, $link, $class_id);
    $notify->execute();
}
?>

<div class="ml-64 mt-16 p-6">
    <div class="max-w-4xl mx-auto">
        <div class="mb-6">
            <h1 class="text-2xl font-bold text-gray-800">Create Assignment</h1>
            <p class="text-gray-500 mt-1">Create a new assignment for your students</p>
        </div>

        <?php if($error): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg mb-4"><?php echo $error; ?></div>
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
                            <label class="block text-sm font-medium text-gray-700 mb-1">Assignment Title *</label>
                            <input type="text" name="title" required value="<?php echo htmlspecialchars($_POST['title'] ?? ''); ?>"
                                   placeholder="e.g., Mathematics Homework - Chapter 1"
                                   class="w-full border rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Assignment Type</label>
                            <select name="assignment_type" class="w-full border rounded-lg px-3 py-2">
                                <option value="homework">📚 Homework</option>
                                <option value="classwork">📝 Classwork</option>
                                <option value="project">🎯 Project</option>
                                <option value="research">🔬 Research Task</option>
                                <option value="group">👥 Group Assignment</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Topic</label>
                            <input type="text" name="topic" value="<?php echo htmlspecialchars($_POST['topic'] ?? ''); ?>"
                                   placeholder="e.g., Algebra, Grammar, Photosynthesis"
                                   class="w-full border rounded-lg px-3 py-2">
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
                            <label class="block text-sm font-medium text-gray-700 mb-1">Subject *</label>
                            <select name="subject_id" id="subject_id" required class="w-full border rounded-lg px-3 py-2">
                                <option value="">Select Subject</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Academic Term</label>
                            <select name="academic_term" class="w-full border rounded-lg px-3 py-2">
                                <option value="term1">Term 1</option>
                                <option value="term2">Term 2</option>
                                <option value="term3">Term 3</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Description & Instructions -->
                <div class="border-b pb-4 mb-4">
                    <h3 class="text-lg font-semibold mb-4 flex items-center">
                        <i class="fas fa-file-alt text-green-500 mr-2"></i> Description & Instructions
                    </h3>
                    <div class="mb-3">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                        <textarea name="description" rows="3" 
                                  placeholder="Describe the assignment..."
                                  class="w-full border rounded-lg px-3 py-2"><?php echo htmlspecialchars($_POST['description'] ?? ''); ?></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Instructions for Students</label>
                        <textarea name="instructions" rows="4" 
                                  placeholder="Step-by-step instructions, submission guidelines..."
                                  class="w-full border rounded-lg px-3 py-2"><?php echo htmlspecialchars($_POST['instructions'] ?? ''); ?></textarea>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Rubric / Marking Criteria</label>
                        <textarea name="rubric" rows="3" 
                                  placeholder="e.g., Content: 10 marks, Grammar: 5 marks, Presentation: 5 marks..."
                                  class="w-full border rounded-lg px-3 py-2"><?php echo htmlspecialchars($_POST['rubric'] ?? ''); ?></textarea>
                    </div>
                </div>

                <!-- Deadline Settings -->
                <div class="border-b pb-4 mb-4">
                    <h3 class="text-lg font-semibold mb-4 flex items-center">
                        <i class="fas fa-calendar-alt text-yellow-500 mr-2"></i> Deadline Settings
                    </h3>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Due Date *</label>
                            <input type="date" name="due_date" required value="<?php echo $_POST['due_date'] ?? date('Y-m-d', strtotime('+7 days')); ?>"
                                   class="w-full border rounded-lg px-3 py-2">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Due Time *</label>
                            <input type="time" name="due_time" required value="<?php echo $_POST['due_time'] ?? '23:59'; ?>"
                                   class="w-full border rounded-lg px-3 py-2">
                        </div>
                    </div>
                    <div class="mt-3 flex items-center space-x-4">
                        <label class="flex items-center">
                            <input type="checkbox" name="allow_late" <?php echo isset($_POST['allow_late']) ? 'checked' : ''; ?> class="rounded">
                            <span class="ml-2 text-sm">Allow late submissions</span>
                        </label>
                        <label class="flex items-center">
                            <span class="text-sm">Late penalty (%)</span>
                            <input type="number" name="late_penalty" value="<?php echo $_POST['late_penalty'] ?? 10; ?>" class="ml-2 w-20 border rounded px-2 py-1">
                        </label>
                    </div>
                </div>

                <!-- Submission Settings -->
                <div class="border-b pb-4 mb-4">
                    <h3 class="text-lg font-semibold mb-4 flex items-center">
                        <i class="fas fa-upload text-purple-500 mr-2"></i> Submission Settings
                    </h3>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Maximum Marks</label>
                            <input type="number" name="max_marks" value="<?php echo $_POST['max_marks'] ?? 100; ?>" min="1"
                                   class="w-full border rounded-lg px-3 py-2">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Max File Size (MB)</label>
                            <input type="number" name="max_file_size" value="<?php echo $_POST['max_file_size'] ?? 10; ?>" min="1" max="50"
                                   class="w-full border rounded-lg px-3 py-2">
                        </div>
                    </div>
                    <div class="mt-3">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Allowed File Types</label>
                        <div class="flex flex-wrap gap-3">
                            <label><input type="checkbox" name="allowed_files[]" value="pdf" checked> PDF</label>
                            <label><input type="checkbox" name="allowed_files[]" value="docx"> DOCX</label>
                            <label><input type="checkbox" name="allowed_files[]" value="jpg"> JPG/PNG</label>
                            <label><input type="checkbox" name="allowed_files[]" value="mp4"> MP4</label>
                            <label><input type="checkbox" name="allowed_files[]" value="pptx"> PPTX</label>
                        </div>
                    </div>
                    <div class="mt-3">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Attachment (Optional)</label>
                        <input type="file" name="attachment" class="w-full border rounded-lg px-3 py-2">
                        <p class="text-xs text-gray-500 mt-1">You can attach reference materials, templates, or example files</p>
                    </div>
                </div>

                <!-- Publishing Settings -->
                <div class="mb-4">
                    <h3 class="text-lg font-semibold mb-4 flex items-center">
                        <i class="fas fa-globe text-blue-500 mr-2"></i> Publishing
                    </h3>
                    <div class="flex space-x-4">
                        <label class="flex items-center">
                            <input type="radio" name="status" value="published" checked class="mr-2">
                            <span class="font-medium">Publish Now</span>
                            <span class="text-xs text-gray-500 ml-2">Students can see immediately</span>
                        </label>
                        <label class="flex items-center">
                            <input type="radio" name="status" value="draft" class="mr-2">
                            <span class="font-medium">Save as Draft</span>
                            <span class="text-xs text-gray-500 ml-2">Visible only to you</span>
                        </label>
                    </div>
                </div>

                <div class="flex justify-end space-x-3 mt-6 pt-4 border-t">
                    <a href="index.php" class="px-4 py-2 border rounded-lg hover:bg-gray-50">Cancel</a>
                    <button type="submit" class="px-6 py-2 bg-gradient-to-r from-blue-500 to-purple-600 text-white rounded-lg hover:shadow-lg transition-all">
                        <i class="fas fa-save mr-2"></i> Create Assignment
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