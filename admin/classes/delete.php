<?php
require_once '../../config.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';
requireRole('admin');

$class_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if (!$class_id) {
    header('Location: index.php?error=No class specified');
    exit();
}

// Get class details
$class_query = $conn->prepare("SELECT id, name FROM classes WHERE id = ?");
$class_query->bind_param("i", $class_id);
$class_query->execute();
$class = $class_query->get_result()->fetch_assoc();

if (!$class) {
    header('Location: index.php?error=Class not found');
    exit();
}

// Get statistics
$stats = [];
$student_count = $conn->query("SELECT COUNT(*) as count FROM students WHERE class_id = $class_id")->fetch_assoc()['count'] ?? 0;
$stream_count = $conn->query("SELECT COUNT(*) as count FROM streams WHERE class_id = $class_id")->fetch_assoc()['count'] ?? 0;
$timetable_count = $conn->query("SELECT COUNT(*) as count FROM timetable_entries WHERE class_id = $class_id")->fetch_assoc()['count'] ?? 0;
$assignment_count = $conn->query("SELECT COUNT(*) as count FROM assignments WHERE class_id = $class_id")->fetch_assoc()['count'] ?? 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm'])) {
    // Start transaction
    $conn->begin_transaction();
    
    try {
        // 1. Delete streams
        $conn->query("DELETE FROM streams WHERE class_id = $class_id");
        
        // 2. Delete timetable entries
        $conn->query("DELETE FROM timetable_entries WHERE class_id = $class_id");
        
        // 3. Delete assignments
        $conn->query("DELETE FROM assignments WHERE class_id = $class_id");
        
        // 4. Delete students (and their user accounts if they are only in this class)
        // First, get student IDs
        $student_ids = [];
        $student_result = $conn->query("SELECT id, user_id FROM students WHERE class_id = $class_id");
        while ($row = $student_result->fetch_assoc()) {
            $student_ids[] = $row['id'];
            // Delete student submissions
            $conn->query("DELETE FROM submissions WHERE student_id = " . $row['id']);
            // Delete exam submissions
            $conn->query("DELETE FROM exam_submissions WHERE student_id = " . $row['id']);
            // Delete attendance
            $conn->query("DELETE FROM attendance WHERE student_id = " . $row['id']);
        }
        
        // Delete students
        $conn->query("DELETE FROM students WHERE class_id = $class_id");
        
        // 5. Delete class
        $conn->query("DELETE FROM classes WHERE id = $class_id");
        
        // 6. Remove class teacher reference
        $conn->query("UPDATE teachers SET class_teacher_of = NULL WHERE class_teacher_of = $class_id");
        
        $conn->commit();
        
        logActivity($_SESSION['user_id'], 'deleted class with all associated data', 'class', $class_id);
        
        header('Location: index.php?deleted=1');
        exit();
    } catch (Exception $e) {
        $conn->rollback();
        header('Location: index.php?error=Failed to delete class: ' . $e->getMessage());
        exit();
    }
}

$page_title = 'Delete Class - ' . $class['name'];
include '../../includes/header.php';
include '../../includes/sidebar.php';
include '../../includes/navbar.php';
?>

<div class="ml-64 mt-16 p-6 bg-gray-50 min-h-screen">
    <div class="max-w-2xl mx-auto">
        <div class="mb-6">
            <h1 class="text-2xl font-bold text-gray-800">Delete Class</h1>
            <p class="text-gray-500 mt-1">Are you sure you want to delete this class?</p>
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
                        <p class="text-sm text-red-700">Deleting this class will also remove all associated data.</p>
                    </div>
                </div>
            </div>

            <!-- Class Details -->
            <div class="p-6">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                    <div class="p-4 bg-gray-50 rounded-lg">
                        <p class="text-sm text-gray-500">Class Name</p>
                        <p class="font-semibold text-gray-800"><?php echo htmlspecialchars($class['name']); ?></p>
                    </div>
                    <div class="p-4 bg-gray-50 rounded-lg">
                        <p class="text-sm text-gray-500">Class ID</p>
                        <p class="font-semibold text-gray-800">#<?php echo $class['id']; ?></p>
                    </div>
                </div>

                <!-- What will be deleted -->
                <div class="mb-6 p-4 bg-yellow-50 rounded-lg border border-yellow-200">
                    <h4 class="font-semibold text-yellow-800 mb-2">The following will be deleted:</h4>
                    <ul class="text-sm text-yellow-700 space-y-1 list-disc list-inside">
                        <li>Class: <strong><?php echo htmlspecialchars($class['name']); ?></strong></li>
                        <li>Students: <strong><?php echo $student_count; ?></strong> students</li>
                        <li>Streams: <strong><?php echo $stream_count; ?></strong> streams</li>
                        <li>Timetable entries: <strong><?php echo $timetable_count; ?></strong> entries</li>
                        <li>Assignments: <strong><?php echo $assignment_count; ?></strong> assignments</li>
                        <li>All student submissions and results</li>
                        <li>Attendance records</li>
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
                            <i class="fas fa-trash-alt mr-2"></i> Yes, Delete Class Permanently
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>