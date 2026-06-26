<?php
require_once '../../config.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';
requireRole('teacher');

$page_title = 'Upload Material';
include '../../includes/header.php';
include '../../includes/sidebar.php';
include '../../includes/navbar.php';

// Get teacher ID
$teacher_query = $conn->prepare("SELECT id FROM teachers WHERE user_id = ?");
$teacher_query->bind_param("i", $_SESSION['user_id']);
$teacher_query->execute();
$teacher = $teacher_query->get_result()->fetch_assoc();
$teacher_id = $teacher['id'] ?? 0;

// Get classes and subjects taught by this teacher
$classes_subjects = $conn->prepare("
    SELECT DISTINCT c.id as class_id, c.name as class_name, 
           s.id as subject_id, s.name as subject_name
    FROM class_subject cs
    JOIN classes c ON cs.class_id = c.id
    JOIN subjects s ON cs.subject_id = s.id
    WHERE cs.teacher_id = ?
    ORDER BY c.name, s.name
");
$classes_subjects->bind_param("i", $teacher_id);
$classes_subjects->execute();
$teaching = $classes_subjects->get_result();

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = sanitizeInput($_POST['title']);
    $description = sanitizeInput($_POST['description']);
    $class_id = intval($_POST['class_id']);
    $subject_id = intval($_POST['subject_id']);
    $type = $_POST['type'];
    
    if (empty($title) || empty($class_id) || empty($subject_id)) {
        $error = "Please fill all required fields";
    } elseif (isset($_FILES['file']) && $_FILES['file']['error'] == 0) {
        // Upload file
        $upload_result = uploadFile($_FILES['file'], MATERIALS_PATH, ['pdf', 'docx', 'pptx', 'mp4', 'jpg', 'png']);
        
        if ($upload_result['success']) {
            $file_url = 'uploads/materials/' . $upload_result['filename'];
            
            $insert = $conn->prepare("
                INSERT INTO learning_materials (title, description, subject_id, class_id, file_url, type, uploaded_by) 
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            $insert->bind_param("ssiissi", $title, $description, $subject_id, $class_id, $file_url, $type, $_SESSION['user_id']);
            
            if ($insert->execute()) {
                logActivity($_SESSION['user_id'], 'uploaded learning material', 'learning_materials', $insert->insert_id);
                $success = "Material uploaded successfully!";
                $_POST = [];
            } else {
                $error = "Failed to save to database: " . $conn->error;
            }
        } else {
            $error = $upload_result['error'];
        }
    } else {
        $error = "Please select a file to upload";
    }
}
?>

<div class="ml-64 mt-16 p-6">
    <div class="max-w-2xl mx-auto">
        <div class="mb-6">
            <h1 class="text-2xl font-bold text-gray-800">Upload Learning Material</h1>
            <p class="text-gray-500 mt-1">Share notes, videos, presentations with your students</p>
        </div>

        <?php if($error): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg mb-4"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <?php if($success): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-lg mb-4"><?php echo $success; ?></div>
        <?php endif; ?>

        <div class="bg-white rounded-xl shadow-sm p-6">
            <form method="POST" enctype="multipart/form-data">
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Title *</label>
                    <input type="text" name="title" required value="<?php echo htmlspecialchars($_POST['title'] ?? ''); ?>"
                           class="w-full border rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>

                <div class="grid grid-cols-2 gap-4 mb-4">
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
                                <option value="<?php echo $class['id']; ?>" <?php echo ($_POST['class_id'] ?? '') == $class['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($class['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Subject *</label>
                        <select name="subject_id" id="subject_id" required class="w-full border rounded-lg px-3 py-2">
                            <option value="">Select Subject</option>
                        </select>
                    </div>
                </div>

                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Material Type *</label>
                    <select name="type" required class="w-full border rounded-lg px-3 py-2">
                        <option value="">Select Type</option>
                        <option value="note" <?php echo ($_POST['type'] ?? '') == 'note' ? 'selected' : ''; ?>>Notes (PDF/DOCX)</option>
                        <option value="video" <?php echo ($_POST['type'] ?? '') == 'video' ? 'selected' : ''; ?>>Video (MP4)</option>
                        <option value="presentation" <?php echo ($_POST['type'] ?? '') == 'presentation' ? 'selected' : ''; ?>>Presentation (PPTX/PDF)</option>
                        <option value="resource" <?php echo ($_POST['type'] ?? '') == 'resource' ? 'selected' : ''; ?>>Other Resources</option>
                    </select>
                </div>

                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                    <textarea name="description" rows="3" 
                              class="w-full border rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"><?php echo htmlspecialchars($_POST['description'] ?? ''); ?></textarea>
                </div>

                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">File *</label>
                    <input type="file" name="file" required 
                           accept=".pdf,.docx,.pptx,.mp4,.jpg,.png"
                           class="w-full border rounded-lg px-3 py-2">
                    <p class="text-xs text-gray-500 mt-1">Allowed: PDF, DOCX, PPTX, MP4, JPG, PNG (Max 10MB)</p>
                </div>

                <div class="flex justify-end space-x-3 mt-6 pt-4 border-t">
                    <a href="index.php" class="px-4 py-2 border rounded-lg hover:bg-gray-50">Cancel</a>
                    <button type="submit" class="px-6 py-2 bg-gradient-to-r from-blue-500 to-purple-600 text-white rounded-lg hover:shadow-lg">
                        <i class="fas fa-upload mr-2"></i> Upload Material
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

// Trigger change on page load if class is preselected
if (document.getElementById('class_id').value) {
    document.getElementById('class_id').dispatchEvent(new Event('change'));
}
</script>

<?php include '../../includes/footer.php'; ?>