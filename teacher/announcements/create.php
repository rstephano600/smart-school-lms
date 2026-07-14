<?php
// ============================================
// START OUTPUT BUFFERING - FIX HEADER ERROR
// ============================================
ob_start();

require_once '../../config.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';
requireRole('teacher');

$page_title = 'Create Announcement';

// ============================================
// HEADER REDIRECT HANDLING - MUST BE BEFORE ANY HTML
// ============================================
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get teacher ID
    $teacher_query = $conn->prepare("SELECT id FROM teachers WHERE user_id = ?");
    $teacher_query->bind_param("i", $_SESSION['user_id']);
    $teacher_query->execute();
    $teacher = $teacher_query->get_result()->fetch_assoc();
    $teacher_id = $teacher['id'] ?? 0;
    
    $class_id = intval($_POST['class_id']);
    $title = sanitizeInput($_POST['title']);
    $content = sanitizeInput($_POST['content']);
    $priority = $_POST['priority'] ?? 'medium';
    
    if (empty($title) || empty($content) || empty($class_id)) {
        $error = "Please fill all required fields";
    } else {
        $insert = $conn->prepare("
            INSERT INTO teacher_announcements (teacher_id, class_id, title, content, priority)
            VALUES (?, ?, ?, ?, ?)
        ");
        $insert->bind_param("iisss", $teacher_id, $class_id, $title, $content, $priority);
        
        if ($insert->execute()) {
            $ann_id = $conn->insert_id;
            
            // Create notifications
            $notify = $conn->prepare("
                INSERT INTO notifications (user_id, title, message, type, link)
                SELECT u.id, 'New Announcement', CONCAT('New announcement from your teacher: ', LEFT(?, 50), '...'), 'announcement', ?
                FROM students s
                JOIN users u ON s.user_id = u.id
                WHERE s.class_id = ?
            ");
            $link = "/smart-school-lms/student/announcements/view.php?id=" . $ann_id;
            $notify->bind_param("ssi", $title, $link, $class_id);
            $notify->execute();
            
            // ✅ REDIRECT - This will work now with ob_start()
            $_SESSION['success'] = "Announcement published successfully!";
            header("Location: index.php");
            exit();
        } else {
            $error = "Failed to create announcement";
        }
    }
}

// ============================================
// INCLUDE HEADERS AFTER REDIRECT HANDLING
// ============================================
include '../../includes/header.php';
include '../../includes/sidebar.php';
include '../../includes/navbar.php';

// Get teacher ID
$teacher_query = $conn->prepare("SELECT id FROM teachers WHERE user_id = ?");
$teacher_query->bind_param("i", $_SESSION['user_id']);
$teacher_query->execute();
$teacher = $teacher_query->get_result()->fetch_assoc();
$teacher_id = $teacher['id'] ?? 0;

// Get classes
$classes = $conn->prepare("
    SELECT DISTINCT c.id, c.name 
    FROM class_subject cs
    JOIN classes c ON cs.class_id = c.id
    WHERE cs.teacher_id = ?
");
$classes->bind_param("i", $teacher_id);
$classes->execute();
$classes = $classes->get_result();

$success_msg = $_SESSION['success'] ?? '';
unset($_SESSION['success']);
?>

<style>
.form-container {
    max-width: 700px;
    margin: 0 auto;
}
.form-label {
    font-weight: 500;
    color: #374151;
    margin-bottom: 0.25rem;
    display: block;
}
.form-control {
    width: 100%;
    border: 1px solid #d1d5db;
    border-radius: 0.5rem;
    padding: 0.5rem 0.75rem;
    transition: all 0.2s;
}
.form-control:focus {
    outline: none;
    border-color: #3b82f6;
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
}
</style>

<div class="ml-64 mt-16 p-6 bg-gray-50 min-h-screen">
    <div class="form-container">
        <!-- Header -->
        <div class="mb-6">
            <h1 class="text-2xl font-bold text-gray-800">📢 Create Announcement</h1>
            <p class="text-gray-500 mt-1">Share important information with your students</p>
        </div>

        <!-- Error/Success -->
        <?php if($error): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg mb-4 flex items-start">
                <i class="fas fa-exclamation-circle mt-1 mr-3"></i>
                <div><?php echo $error; ?></div>
            </div>
        <?php endif; ?>
        
        <?php if($success_msg): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-lg mb-4 flex items-start">
                <i class="fas fa-check-circle mt-1 mr-3"></i>
                <div><?php echo $success_msg; ?></div>
            </div>
        <?php endif; ?>

        <!-- Form -->
        <div class="bg-white rounded-xl shadow-sm p-6">
            <form method="POST">
                <!-- Class Selection -->
                <div class="mb-4">
                    <label class="form-label">Class *</label>
                    <select name="class_id" required class="form-control">
                        <option value="">Select Class</option>
                        <?php while($class = $classes->fetch_assoc()): ?>
                            <option value="<?php echo $class['id']; ?>">
                                <?php echo htmlspecialchars($class['name']); ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>

                <!-- Title -->
                <div class="mb-4">
                    <label class="form-label">Title *</label>
                    <input type="text" name="title" required class="form-control" 
                           placeholder="Enter announcement title"
                           value="<?php echo htmlspecialchars($_POST['title'] ?? ''); ?>">
                </div>

                <!-- Content -->
                <div class="mb-4">
                    <label class="form-label">Content *</label>
                    <textarea name="content" rows="8" required class="form-control" 
                              placeholder="Write your announcement here..."><?php echo htmlspecialchars($_POST['content'] ?? ''); ?></textarea>
                </div>

                <!-- Priority -->
                <div class="mb-4">
                    <label class="form-label">Priority</label>
                    <select name="priority" class="form-control">
                        <option value="low">Low</option>
                        <option value="medium" selected>Medium</option>
                        <option value="high">High</option>
                    </select>
                    <p class="text-sm text-gray-500 mt-1">High priority announcements will be highlighted to students.</p>
                </div>

                <!-- Preview -->
                <div class="bg-gray-50 border border-gray-200 rounded-lg p-4 mb-6">
                    <h4 class="text-sm font-semibold text-gray-700 flex items-center">
                        <i class="fas fa-eye mr-2"></i> Preview
                    </h4>
                    <div id="preview" class="mt-2 text-sm text-gray-600">
                        <p class="text-gray-400">Your announcement will appear here...</p>
                    </div>
                </div>

                <!-- Buttons -->
                <div class="flex flex-wrap justify-end gap-3 pt-4 border-t">
                    <a href="index.php" class="px-6 py-2 border border-gray-300 rounded-lg hover:bg-gray-50 transition">
                        Cancel
                    </a>
                    <button type="submit" class="px-6 py-2 bg-gradient-to-r from-blue-500 to-purple-600 text-white rounded-lg hover:shadow-lg transition flex items-center">
                        <i class="fas fa-paper-plane mr-2"></i> Publish Announcement
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Live preview
const titleInput = document.querySelector('input[name="title"]');
const contentInput = document.querySelector('textarea[name="content"]');
const preview = document.getElementById('preview');

function updatePreview() {
    const title = titleInput.value || 'Untitled';
    const content = contentInput.value || 'No content yet...';
    preview.innerHTML = `
        <h4 class="font-semibold text-gray-800">${title}</h4>
        <p class="mt-1 whitespace-pre-wrap">${content.replace(/\n/g, '<br>')}</p>
    `;
}

if (titleInput && contentInput) {
    titleInput.addEventListener('input', updatePreview);
    contentInput.addEventListener('input', updatePreview);
}
</script>

<?php
include '../../includes/footer.php';

// ============================================
// FLUSH OUTPUT BUFFER
// ============================================
ob_end_flush();
?>