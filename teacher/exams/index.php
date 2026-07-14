<?php
require_once '../../config.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';
requireRole('teacher');

$page_title = 'Exam Management';
include '../../includes/header.php';
include '../../includes/sidebar.php';
include '../../includes/navbar.php';

// Get teacher ID
$teacher_query = $conn->prepare("SELECT id FROM teachers WHERE user_id = ?");
$teacher_query->bind_param("i", $_SESSION['user_id']);
$teacher_query->execute();
$teacher = $teacher_query->get_result()->fetch_assoc();
$teacher_id = $teacher['id'] ?? 0;

// Check for success/error messages
$success_message = '';
$error_message = '';

if (isset($_GET['deleted'])) {
    $success_message = 'Exam deleted successfully!';
}
if (isset($_GET['published'])) {
    $success_message = 'Exam published successfully!';
}
if (isset($_GET['error'])) {
    $error_message = $_GET['error'];
}

// Get statistics
$stats = [];

// Total exams
$total_query = $conn->prepare("SELECT COUNT(*) as count FROM teacher_exams WHERE teacher_id = ?");
$total_query->bind_param("i", $teacher_id);
$total_query->execute();
$stats['total_exams'] = $total_query->get_result()->fetch_assoc()['count'];

// Upcoming exams
$upcoming_query = $conn->prepare("
    SELECT COUNT(*) as count FROM teacher_exams 
    WHERE teacher_id = ? AND start_date > CURDATE() AND is_published = 1
");
$upcoming_query->bind_param("i", $teacher_id);
$upcoming_query->execute();
$stats['upcoming_exams'] = $upcoming_query->get_result()->fetch_assoc()['count'];

// Active exams
$active_query = $conn->prepare("
    SELECT COUNT(*) as count FROM teacher_exams 
    WHERE teacher_id = ? AND start_date <= CURDATE() AND end_date >= CURDATE() AND is_published = 1
");
$active_query->bind_param("i", $teacher_id);
$active_query->execute();
$stats['active_exams'] = $active_query->get_result()->fetch_assoc()['count'];

// Pending grading
$pending_query = $conn->prepare("
    SELECT COUNT(*) as count FROM exam_submissions es
    JOIN teacher_exams te ON es.exam_id = te.id
    WHERE te.teacher_id = ? AND es.is_graded = 0 AND es.submitted_at IS NOT NULL
");
$pending_query->bind_param("i", $teacher_id);
$pending_query->execute();
$stats['pending_grading'] = $pending_query->get_result()->fetch_assoc()['count'];

// Get recent exams
$exams = $conn->prepare("
    SELECT te.*, s.name as subject_name, c.name as class_name,
           (SELECT COUNT(*) FROM exam_questions WHERE exam_id = te.id) as total_questions,
           (SELECT COUNT(*) FROM exam_submissions WHERE exam_id = te.id) as submissions_count
    FROM teacher_exams te
    JOIN subjects s ON te.subject_id = s.id
    JOIN classes c ON te.class_id = c.id
    WHERE te.teacher_id = ?
    ORDER BY te.created_at DESC
");
$exams->bind_param("i", $teacher_id);
$exams->execute();
$exams = $exams->get_result();
?>

<div class="ml-64 mt-16 p-6 bg-gray-50 min-h-screen">
    <div class="max-w-full mx-auto">
        <div class="mb-6 flex justify-between items-center">
            <div>
                <h1 class="text-2xl font-bold text-gray-800">Exam Management</h1>
                <p class="text-gray-500 mt-1">Create and manage examinations</p>
            </div>
            <a href="create.php" class="bg-gradient-to-r from-blue-500 to-purple-600 text-white px-6 py-2 rounded-lg hover:shadow-lg transition-all">
                <i class="fas fa-plus mr-2"></i> Create Exam
            </a>
        </div>

        <!-- Success/Error Messages -->
        <?php if($success_message): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-lg mb-4">
                <i class="fas fa-check-circle mr-2"></i> <?php echo $success_message; ?>
            </div>
        <?php endif; ?>
        
        <?php if($error_message): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg mb-4">
                <i class="fas fa-exclamation-circle mr-2"></i> <?php echo $error_message; ?>
            </div>
        <?php endif; ?>

        <!-- Stats Cards -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
            <div class="bg-white rounded-xl p-4 shadow-sm hover:shadow-md transition-all">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-500 text-sm">Total Exams</p>
                        <p class="text-2xl font-bold text-blue-600"><?php echo $stats['total_exams']; ?></p>
                    </div>
                    <div class="w-12 h-12 bg-blue-100 rounded-full flex items-center justify-center">
                        <i class="fas fa-pen-alt text-blue-500 text-xl"></i>
                    </div>
                </div>
            </div>
            <div class="bg-white rounded-xl p-4 shadow-sm hover:shadow-md transition-all">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-500 text-sm">Upcoming</p>
                        <p class="text-2xl font-bold text-yellow-600"><?php echo $stats['upcoming_exams']; ?></p>
                    </div>
                    <div class="w-12 h-12 bg-yellow-100 rounded-full flex items-center justify-center">
                        <i class="fas fa-clock text-yellow-500 text-xl"></i>
                    </div>
                </div>
            </div>
            <div class="bg-white rounded-xl p-4 shadow-sm hover:shadow-md transition-all">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-500 text-sm">Active</p>
                        <p class="text-2xl font-bold text-green-600"><?php echo $stats['active_exams']; ?></p>
                    </div>
                    <div class="w-12 h-12 bg-green-100 rounded-full flex items-center justify-center">
                        <i class="fas fa-play-circle text-green-500 text-xl"></i>
                    </div>
                </div>
            </div>
            <div class="bg-white rounded-xl p-4 shadow-sm hover:shadow-md transition-all">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-500 text-sm">Pending Grading</p>
                        <p class="text-2xl font-bold text-purple-600"><?php echo $stats['pending_grading']; ?></p>
                    </div>
                    <div class="w-12 h-12 bg-purple-100 rounded-full flex items-center justify-center">
                        <i class="fas fa-check-double text-purple-500 text-xl"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Exams Grid -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            <?php if ($exams && $exams->num_rows > 0): ?>
                <?php while($exam = $exams->fetch_assoc()): 
                    $status = '';
                    $status_color = '';
                    $today = date('Y-m-d');
                    if ($exam['start_date'] > $today) {
                        $status = 'Upcoming';
                        $status_color = 'bg-blue-100 text-blue-700';
                    } elseif ($exam['end_date'] < $today) {
                        $status = 'Completed';
                        $status_color = 'bg-gray-100 text-gray-700';
                    } else {
                        $status = 'Active';
                        $status_color = 'bg-green-100 text-green-700';
                    }
                    
                    // Check if published
                    $publish_status = $exam['is_published'] ? 'Published' : 'Draft';
                    $publish_color = $exam['is_published'] ? 'bg-green-100 text-green-700' : 'bg-yellow-100 text-yellow-700';
                ?>
                    <div class="bg-white rounded-xl shadow-sm hover:shadow-lg transition-all duration-300 overflow-hidden">
                        <div class="bg-gradient-to-r from-blue-500 to-purple-600 p-4">
                            <div class="flex justify-between items-center">
                                <div>
                                    <h3 class="text-white font-bold"><?php echo htmlspecialchars($exam['title']); ?></h3>
                                    <p class="text-blue-100 text-sm"><?php echo ucfirst($exam['exam_type']); ?></p>
                                </div>
                                <div class="flex flex-col items-end space-y-1">
                                    <span class="px-2 py-0.5 text-xs rounded-full <?php echo $status_color; ?> bg-white bg-opacity-20 text-white">
                                        <?php echo $status; ?>
                                    </span>
                                    <span class="px-2 py-0.5 text-xs rounded-full <?php echo $publish_color; ?> bg-white bg-opacity-20 text-white">
                                        <?php echo $publish_status; ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                        <div class="p-4">
                            <div class="space-y-2 text-sm">
                                <div class="flex justify-between">
                                    <span class="text-gray-500">Subject:</span>
                                    <span class="font-medium"><?php echo htmlspecialchars($exam['subject_name']); ?></span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-500">Class:</span>
                                    <span class="font-medium"><?php echo htmlspecialchars($exam['class_name']); ?></span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-500">Date:</span>
                                    <span class="font-medium"><?php echo date('M d', strtotime($exam['start_date'])); ?> - <?php echo date('M d', strtotime($exam['end_date'])); ?></span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-500">Duration:</span>
                                    <span class="font-medium"><?php echo $exam['duration_minutes']; ?> minutes</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-500">Total Marks:</span>
                                    <span class="font-medium"><?php echo $exam['total_marks']; ?></span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-500">Questions:</span>
                                    <span class="font-medium"><?php echo $exam['total_questions'] ?? 0; ?></span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-500">Submissions:</span>
                                    <span class="font-medium"><?php echo $exam['submissions_count'] ?? 0; ?></span>
                                </div>
                            </div>
                            <div class="flex justify-end space-x-2 mt-4 pt-3 border-t">
                                <a href="edit.php?id=<?php echo $exam['id']; ?>" class="text-blue-600 hover:text-blue-800 p-1" title="Edit Exam">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <a href="questions.php?id=<?php echo $exam['id']; ?>" class="text-green-600 hover:text-green-800 p-1" title="Manage Questions">
                                    <i class="fas fa-question-circle"></i>
                                </a>
                                <a href="submissions.php?id=<?php echo $exam['id']; ?>" class="text-purple-600 hover:text-purple-800 p-1" title="View Submissions">
                                    <i class="fas fa-file-alt"></i>
                                </a>
                                <a href="analytics.php?id=<?php echo $exam['id']; ?>" class="text-yellow-600 hover:text-yellow-800 p-1" title="View Analytics">
                                    <i class="fas fa-chart-line"></i>
                                </a>
                                <a href="monitor.php?id=<?php echo $exam['id']; ?>" class="text-indigo-600 hover:text-indigo-800 p-1" title="Live Monitor">
                                    <i class="fas fa-eye"></i>
                                </a>
                                <?php if(!$exam['is_published']): ?>
                                    <a href="publish.php?id=<?php echo $exam['id']; ?>&action=publish" class="text-green-600 hover:text-green-800 p-1" title="Publish Exam" onclick="return confirm('Publish this exam? Students will be able to see it.')">
                                        <i class="fas fa-check-circle"></i>
                                    </a>
                                <?php endif; ?>
                                <a href="delete.php?id=<?php echo $exam['id']; ?>" 
                                   class="text-red-600 hover:text-red-800 p-1" 
                                   title="Delete Exam"
                                   onclick="return confirmDelete('<?php echo addslashes($exam['title']); ?>')">
                                    <i class="fas fa-trash-alt"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="col-span-full">
                    <div class="bg-white rounded-xl shadow-sm p-12 text-center">
                        <i class="fas fa-pen-alt text-6xl text-gray-300 mb-4"></i>
                        <h3 class="text-xl font-semibold text-gray-600">No Exams Created</h3>
                        <p class="text-gray-400 mt-2">Click "Create Exam" to get started</p>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
function confirmDelete(title) {
    return confirm('⚠️ Are you sure you want to delete the exam "' + title + '"?\n\nThis action CANNOT be undone and will also delete:\n- All questions in this exam\n- All student submissions\n- All exam sessions\n\nDo you want to continue?');
}
</script>

<?php include '../../includes/footer.php'; ?>