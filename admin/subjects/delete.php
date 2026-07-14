<?php
require_once '../../config.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';
requireRole('admin');

$subject_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if (!$subject_id) {
    header('Location: index.php?error=No subject specified');
    exit();
}

// Get subject details
$subject_query = $conn->prepare("SELECT id, name, code FROM subjects WHERE id = ?");
$subject_query->bind_param("i", $subject_id);
$subject_query->execute();
$subject = $subject_query->get_result()->fetch_assoc();

if (!$subject) {
    header('Location: index.php?error=Subject not found');
    exit();
}

// Get statistics
$stats = [];
$class_subject_count = $conn->query("SELECT COUNT(*) as count FROM class_subject WHERE subject_id = $subject_id")->fetch_assoc()['count'] ?? 0;
$exam_count = $conn->query("SELECT COUNT(*) as count FROM teacher_exams WHERE subject_id = $subject_id")->fetch_assoc()['count'] ?? 0;
$material_count = $conn->query("SELECT COUNT(*) as count FROM learning_materials WHERE subject_id = $subject_id")->fetch_assoc()['count'] ?? 0;
$assignment_count = $conn->query("SELECT COUNT(*) as count FROM assignments WHERE subject_id = $subject_id")->fetch_assoc()['count'] ?? 0;
$question_count = $conn->query("SELECT COUNT(*) as count FROM exam_questions WHERE subject_id = $subject_id")->fetch_assoc()['count'] ?? 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm'])) {
    // Start transaction
    $conn->begin_transaction();
    
    try {
        // 1. Delete class_subject entries
        $conn->query("DELETE FROM class_subject WHERE subject_id = $subject_id");
        
        // 2. Delete exam questions
        $conn->query("DELETE FROM exam_questions WHERE subject_id = $subject_id");
        
        // 3. Delete teacher exams
        $conn->query("DELETE FROM teacher_exams WHERE subject_id = $subject_id");
        
        // 4. Delete learning materials
        $conn->query("DELETE FROM learning_materials WHERE subject_id = $subject_id");
        
        // 5. Delete assignments
        $conn->query("DELETE FROM assignments WHERE subject_id = $subject_id");
        
        // 6. Delete marks
        $conn->query("DELETE FROM marks WHERE subject_id = $subject_id");
        
        // 7. Delete subject
        $conn->query("DELETE FROM subjects WHERE id = $subject_id");
        
        $conn->commit();
        
        logActivity($_SESSION['user_id'], 'deleted subject with all associated data', 'subject', $subject_id);
        
        header('Location: index.php?deleted=1');
        exit();
    } catch (Exception $e) {
        $conn->rollback();
        header('Location: index.php?error=Failed to delete subject: ' . $e->getMessage());
        exit();
    }
}

$page_title = 'Delete Subject - ' . $subject['name'];
include '../../includes/header.php';
include '../../includes/sidebar.php';
include '../../includes/navbar.php';
?>

<div class="ml-64 mt-16 p-6 bg-gray-50 min-h-screen">
    <div class="max-w-2xl mx-auto">
        <div class="mb-6">
            <h1 class="text-2xl font-bold text-gray-800">Delete Subject</h1>
            <p class="text-gray-500 mt-1">Are you sure you want to delete this subject?</p>
        </div>

        <div class="bg-white rounded-xl shadow-sm overflow-hidden">
            <div class="p-4 bg-red-50 border-b border-red-200">
                <div class="flex items-start space-x-3">
                    <div class="w-10 h-10 bg-red-100 rounded-full flex items-center justify-center flex-shrink-0">
                        <i class="fas fa-exclamation-triangle text-red-600 text-xl"></i>
                    </div>
                    <div>
                        <h3 class="font-bold text-red-800">⚠️ Warning: This action cannot be undone!</h3>
                        <p class="text-sm text-red-700">Deleting this subject will also remove all associated data.</p>
                    </div>
                </div>
            </div>

            <div class="p-6">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                    <div class="p-4 bg-gray-50 rounded-lg">
                        <p class="text-sm text-gray-500">Subject Name</p>
                        <p class="font-semibold text-gray-800"><?php echo htmlspecialchars($subject['name']); ?></p>
                    </div>
                    <div class="p-4 bg-gray-50 rounded-lg">
                        <p class="text-sm text-gray-500">Subject Code</p>
                        <p class="font-semibold text-gray-800"><?php echo htmlspecialchars($subject['code']); ?></p>
                    </div>
                </div>

                <div class="mb-6 p-4 bg-yellow-50 rounded-lg border border-yellow-200">
                    <h4 class="font-semibold text-yellow-800 mb-2">The following will be deleted:</h4>
                    <ul class="text-sm text-yellow-700 space-y-1 list-disc list-inside">
                        <li>Subject: <strong><?php echo htmlspecialchars($subject['name']); ?></strong></li>
                        <li>Class assignments: <strong><?php echo $class_subject_count; ?></strong> class-subject links</li>
                        <li>Exams: <strong><?php echo $exam_count; ?></strong> exams</li>
                        <li>Learning materials: <strong><?php echo $material_count; ?></strong> materials</li>
                        <li>Assignments: <strong><?php echo $assignment_count; ?></strong> assignments</li>
                        <li>Questions: <strong><?php echo $question_count; ?></strong> questions</li>
                        <li>All marks and results</li>
                    </ul>
                </div>

                <form method="POST">
                    <input type="hidden" name="confirm" value="yes">
                    
                    <div class="flex justify-end space-x-3 pt-4 border-t">
                        <a href="index.php" class="px-4 py-2 border rounded-lg hover:bg-gray-50 transition">
                            <i class="fas fa-arrow-left mr-2"></i> Cancel
                        </a>
                        <button type="submit" class="px-6 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition-all">
                            <i class="fas fa-trash-alt mr-2"></i> Yes, Delete Subject Permanently
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>