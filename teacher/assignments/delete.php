<?php
require_once '../../config.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';
requireRole('teacher');

// Get assignment ID from URL
$assignment_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if (!$assignment_id) {
    header('Location: index.php?error=No assignment specified');
    exit();
}

// Get teacher ID
$teacher_query = $conn->prepare("SELECT id FROM teachers WHERE user_id = ?");
$teacher_query->bind_param("i", $_SESSION['user_id']);
$teacher_query->execute();
$teacher = $teacher_query->get_result()->fetch_assoc();
$teacher_id = $teacher['id'] ?? 0;

// Verify assignment belongs to this teacher
$check_query = $conn->prepare("
    SELECT a.id, a.title, a.class_id, a.subject_id,
           c.name as class_name, s.name as subject_name,
           (SELECT COUNT(*) FROM submissions WHERE assignment_id = a.id) as submissions_count
    FROM assignments a
    JOIN classes c ON a.class_id = c.id
    JOIN subjects s ON a.subject_id = s.id
    WHERE a.id = ? AND a.created_by = ?
");
$check_query->bind_param("ii", $assignment_id, $_SESSION['user_id']);
$check_query->execute();
$assignment = $check_query->get_result()->fetch_assoc();

if (!$assignment) {
    header('Location: index.php?error=Assignment not found or you don\'t have permission');
    exit();
}

// Handle confirmation
if (isset($_POST['confirm']) && $_POST['confirm'] === 'yes') {
    // Start transaction
    $conn->begin_transaction();
    
    try {
        // 1. Delete submissions first
        $delete_submissions = $conn->prepare("DELETE FROM submissions WHERE assignment_id = ?");
        $delete_submissions->bind_param("i", $assignment_id);
        $delete_submissions->execute();
        
        // 2. Delete assignment analytics
        $delete_analytics = $conn->prepare("DELETE FROM assignment_analytics WHERE assignment_id = ?");
        $delete_analytics->bind_param("i", $assignment_id);
        $delete_analytics->execute();
        
        // 3. Delete notifications related to this assignment
        $delete_notifications = $conn->prepare("DELETE FROM notifications WHERE link LIKE ?");
        $link_pattern = "%/assignments/view.php?id=" . $assignment_id . "%";
        $delete_notifications->bind_param("s", $link_pattern);
        $delete_notifications->execute();
        
        // 4. Delete assignment
        $delete_assignment = $conn->prepare("DELETE FROM assignments WHERE id = ? AND created_by = ?");
        $delete_assignment->bind_param("ii", $assignment_id, $_SESSION['user_id']);
        $delete_assignment->execute();
        
        $conn->commit();
        
        logActivity($_SESSION['user_id'], 'deleted assignment', 'assignment', $assignment_id);
        
        header('Location: index.php?deleted=1');
        exit();
    } catch (Exception $e) {
        $conn->rollback();
        header('Location: index.php?error=Failed to delete assignment: ' . $e->getMessage());
        exit();
    }
}

$page_title = 'Delete Assignment - ' . htmlspecialchars($assignment['title']);
include '../../includes/header.php';
include '../../includes/sidebar.php';
include '../../includes/navbar.php';
?>

<div class="ml-64 mt-16 p-6 bg-gray-50 min-h-screen">
    <div class="max-w-2xl mx-auto">
        <div class="mb-6 flex items-center">
            <a href="index.php" class="text-blue-600 hover:text-blue-800 mr-3">
                <i class="fas fa-arrow-left"></i>
            </a>
            <div>
                <h1 class="text-2xl font-bold text-gray-800">Delete Assignment</h1>
                <p class="text-gray-500 mt-1">Are you sure you want to delete this assignment?</p>
            </div>
        </div>

        <div class="bg-white rounded-xl shadow-sm overflow-hidden">
            <!-- Warning Banner -->
            <div class="p-4 bg-red-50 border-b border-red-200">
                <div class="flex items-start space-x-3">
                    <div class="w-10 h-10 bg-red-100 rounded-full flex items-center justify-center flex-shrink-0">
                        <i class="fas fa-exclamation-triangle text-red-600 text-xl"></i>
                    </div>
                    <div>
                        <h3 class="font-bold text-red-800">⚠️ Warning: This action cannot be undone!</h3>
                        <p class="text-sm text-red-700">Deleting this assignment will also remove all student submissions and feedback.</p>
                    </div>
                </div>
            </div>

            <!-- Assignment Details -->
            <div class="p-6">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                    <div class="p-4 bg-gray-50 rounded-lg">
                        <p class="text-sm text-gray-500">Assignment Title</p>
                        <p class="font-semibold text-gray-800"><?php echo htmlspecialchars($assignment['title']); ?></p>
                    </div>
                    <div class="p-4 bg-gray-50 rounded-lg">
                        <p class="text-sm text-gray-500">Class / Subject</p>
                        <p class="font-semibold text-gray-800">
                            <?php echo htmlspecialchars($assignment['class_name']); ?> / 
                            <?php echo htmlspecialchars($assignment['subject_name']); ?>
                        </p>
                    </div>
                    <div class="p-4 bg-gray-50 rounded-lg">
                        <p class="text-sm text-gray-500">Submissions</p>
                        <p class="font-semibold text-gray-800"><?php echo $assignment['submissions_count']; ?> submissions</p>
                    </div>
                    <div class="p-4 bg-gray-50 rounded-lg">
                        <p class="text-sm text-gray-500">Assignment ID</p>
                        <p class="font-semibold text-gray-800">#<?php echo $assignment['id']; ?></p>
                    </div>
                </div>

                <!-- What will be deleted -->
                <div class="mb-6 p-4 bg-yellow-50 rounded-lg border border-yellow-200">
                    <h4 class="font-semibold text-yellow-800 mb-2">The following will be deleted:</h4>
                    <ul class="text-sm text-yellow-700 space-y-1 list-disc list-inside">
                        <li>Assignment: <strong><?php echo htmlspecialchars($assignment['title']); ?></strong></li>
                        <li>All student submissions (<?php echo $assignment['submissions_count']; ?> submissions)</li>
                        <li>All grades and feedback</li>
                        <li>Assignment analytics data</li>
                    </ul>
                </div>

                <!-- Confirmation Form -->
                <form method="POST">
                    <input type="hidden" name="confirm" value="yes">
                    
                    <div class="flex justify-end space-x-3 pt-4 border-t">
                        <a href="index.php" class="px-4 py-2 border rounded-lg hover:bg-gray-50 transition">
                            <i class="fas fa-arrow-left mr-2"></i> Cancel
                        </a>
                        <button type="submit" class="px-6 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition-all">
                            <i class="fas fa-trash-alt mr-2"></i> Yes, Delete Assignment
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>