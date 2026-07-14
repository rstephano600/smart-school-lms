<?php
require_once '../../config.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';
requireRole('teacher');

// Get exam ID from URL
$exam_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if (!$exam_id) {
    header('Location: index.php?error=No exam specified');
    exit();
}

// Get teacher ID
$teacher_query = $conn->prepare("SELECT id FROM teachers WHERE user_id = ?");
$teacher_query->bind_param("i", $_SESSION['user_id']);
$teacher_query->execute();
$teacher = $teacher_query->get_result()->fetch_assoc();
$teacher_id = $teacher['id'] ?? 0;

// Verify exam belongs to this teacher
$check_query = $conn->prepare("SELECT id, title, is_published FROM teacher_exams WHERE id = ? AND teacher_id = ?");
$check_query->bind_param("ii", $exam_id, $teacher_id);
$check_query->execute();
$exam = $check_query->get_result()->fetch_assoc();

if (!$exam) {
    header('Location: index.php?error=Exam not found or you don\'t have permission');
    exit();
}

// Check if exam is published - warn user
$is_published = $exam['is_published'] == 1;

// Handle confirmation
if (isset($_POST['confirm']) && $_POST['confirm'] === 'yes') {
    // Start transaction
    $conn->begin_transaction();
    
    try {
        // Delete exam questions first
        $delete_questions = $conn->prepare("DELETE FROM exam_questions WHERE exam_id = ?");
        $delete_questions->bind_param("i", $exam_id);
        $delete_questions->execute();
        
        // Delete exam submissions
        $delete_submissions = $conn->prepare("DELETE FROM exam_submissions WHERE exam_id = ?");
        $delete_submissions->bind_param("i", $exam_id);
        $delete_submissions->execute();
        
        // Delete exam sessions
        $delete_sessions = $conn->prepare("DELETE FROM student_exam_sessions WHERE exam_id = ?");
        $delete_sessions->bind_param("i", $exam_id);
        $delete_sessions->execute();
        
        // Delete exam
        $delete_exam = $conn->prepare("DELETE FROM teacher_exams WHERE id = ? AND teacher_id = ?");
        $delete_exam->bind_param("ii", $exam_id, $teacher_id);
        $delete_exam->execute();
        
        $conn->commit();
        
        logActivity($_SESSION['user_id'], 'deleted exam', 'teacher_exams', $exam_id);
        
        header('Location: index.php?deleted=1');
        exit();
    } catch (Exception $e) {
        $conn->rollback();
        header('Location: index.php?error=Failed to delete exam: ' . urlencode($e->getMessage()));
        exit();
    }
}

$page_title = 'Delete Exam - ' . htmlspecialchars($exam['title']);
include '../../includes/header.php';
include '../../includes/sidebar.php';
include '../../includes/navbar.php';
?>

<div class="ml-64 mt-16 p-6">
    <div class="max-w-2xl mx-auto">
        <div class="mb-6">
            <h1 class="text-2xl font-bold text-gray-800">🗑️ Delete Exam</h1>
            <p class="text-gray-500 mt-1">Are you sure you want to delete this exam?</p>
        </div>

        <div class="bg-white rounded-xl shadow-sm p-6">
            <!-- Warning -->
            <div class="mb-6 p-4 <?php echo $is_published ? 'bg-red-50 border border-red-300' : 'bg-yellow-50 border border-yellow-300'; ?> rounded-lg">
                <div class="flex items-center mb-3">
                    <div class="w-10 h-10 <?php echo $is_published ? 'bg-red-100' : 'bg-yellow-100'; ?> rounded-full flex items-center justify-center mr-3">
                        <i class="fas <?php echo $is_published ? 'fa-exclamation-triangle text-red-600' : 'fa-exclamation-circle text-yellow-600'; ?> text-xl"></i>
                    </div>
                    <div>
                        <h3 class="font-bold <?php echo $is_published ? 'text-red-800' : 'text-yellow-800'; ?>">
                            <?php echo $is_published ? '⚠️ This exam has been published!' : '⚠️ Warning: This action cannot be undone!'; ?>
                        </h3>
                        <p class="text-sm <?php echo $is_published ? 'text-red-600' : 'text-yellow-700'; ?>">
                            <?php if($is_published): ?>
                                Students may have already started or submitted this exam.
                            <?php endif; ?>
                            Deleting will permanently remove all questions, submissions, and sessions.
                        </p>
                    </div>
                </div>
            </div>

            <!-- Exam Details -->
            <div class="mb-6 p-4 bg-gray-50 rounded-lg">
                <h3 class="font-semibold mb-3">📋 Exam Details:</h3>
                <div class="grid grid-cols-2 gap-3 text-sm">
                    <div>
                        <span class="text-gray-500">Title:</span>
                        <span class="font-medium"><?php echo htmlspecialchars($exam['title']); ?></span>
                    </div>
                    <div>
                        <span class="text-gray-500">ID:</span>
                        <span class="font-medium">#<?php echo $exam['id']; ?></span>
                    </div>
                    <div>
                        <span class="text-gray-500">Status:</span>
                        <span class="px-2 py-0.5 text-xs rounded-full <?php echo $is_published ? 'bg-green-100 text-green-700' : 'bg-yellow-100 text-yellow-700'; ?>">
                            <?php echo $is_published ? '✅ Published' : '📝 Draft'; ?>
                        </span>
                    </div>
                    <div>
                        <span class="text-gray-500">Created:</span>
                        <span class="font-medium"><?php echo date('M d, Y', strtotime($exam['created_at'] ?? 'now')); ?></span>
                    </div>
                </div>
            </div>

            <!-- What will be deleted -->
            <div class="mb-6 p-4 bg-gray-50 rounded-lg">
                <h4 class="font-semibold text-sm mb-2">🗑️ This will delete:</h4>
                <ul class="text-sm text-gray-600 space-y-1 list-disc list-inside">
                    <li>All questions in this exam</li>
                    <li>All student submissions</li>
                    <li>All student exam sessions</li>
                    <li>The exam itself</li>
                </ul>
            </div>

            <!-- Confirmation Form -->
            <form method="POST">
                <input type="hidden" name="confirm" value="yes">
                
                <div class="flex justify-end space-x-3 mt-6 pt-4 border-t">
                    <a href="index.php" class="px-4 py-2 border rounded-lg hover:bg-gray-50 transition">
                        <i class="fas fa-arrow-left mr-2"></i> Cancel
                    </a>
                    <button type="submit" class="px-6 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition-all">
                        <i class="fas fa-trash-alt mr-2"></i> Yes, Delete Exam
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>