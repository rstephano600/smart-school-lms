<?php
require_once '../../config.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';
requireRole('academic');

$page_title = 'Examination Management';
include '../../includes/header.php';
include '../../includes/sidebar.php';
include '../../includes/navbar.php';

// Handle exam deletion
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $exam_id = $_GET['delete'];
    $query = "DELETE FROM exams WHERE id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $exam_id);
    if ($stmt->execute()) {
        logActivity($_SESSION['user_id'], 'deleted exam', 'exam', $exam_id);
        echo '<script>showToast("Exam deleted successfully", "success");</script>';
    }
}

// Get all exams
$query = "SELECT * FROM exams ORDER BY year DESC, term DESC, start_date DESC";
$exams = $conn->query($query);
?>

<div class="ml-64 mt-16 p-6">
    <div class="flex justify-between items-center mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-800">Examination Management</h1>
            <p class="text-gray-500 mt-1">Create and manage school examinations</p>
        </div>
        <a href="create.php" class="bg-gradient-to-r from-blue-500 to-purple-600 text-white px-6 py-2 rounded-lg hover:shadow-lg">
            <i class="fas fa-plus mr-2"></i> Create Exam
        </a>
    </div>

    <!-- Exams List -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        <?php if ($exams && $exams->num_rows > 0): ?>
            <?php while($exam = $exams->fetch_assoc()): ?>
                <div class="bg-white rounded-xl shadow-sm hover:shadow-lg transition-all duration-300 overflow-hidden">
                    <div class="bg-gradient-to-r from-blue-500 to-purple-600 p-4">
                        <div class="flex justify-between items-center">
                            <div>
                                <h3 class="text-xl font-bold text-white"><?php echo htmlspecialchars($exam['name']); ?></h3>
                                <p class="text-blue-100 text-sm"><?php echo ucfirst($exam['type']); ?></p>
                            </div>
                            <div class="w-12 h-12 bg-white bg-opacity-20 rounded-full flex items-center justify-center">
                                <i class="fas fa-pen-alt text-white text-xl"></i>
                            </div>
                        </div>
                    </div>
                    <div class="p-4">
                        <div class="space-y-2 text-sm">
                            <div class="flex justify-between">
                                <span class="text-gray-500">Term:</span>
                                <span class="font-medium"><?php echo ucfirst($exam['term']); ?></span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-500">Year:</span>
                                <span class="font-medium"><?php echo $exam['year']; ?></span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-500">Period:</span>
                                <span class="font-medium"><?php echo date('M d', strtotime($exam['start_date'])); ?> - <?php echo date('M d', strtotime($exam['end_date'])); ?></span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-500">Status:</span>
                                <span class="px-2 py-1 text-xs rounded-full <?php echo $exam['is_published'] ? 'bg-green-100 text-green-700' : 'bg-yellow-100 text-yellow-700'; ?>">
                                    <?php echo $exam['is_published'] ? 'Published' : 'Draft'; ?>
                                </span>
                            </div>
                        </div>
                        <div class="flex justify-end space-x-2 mt-4 pt-3 border-t">
                            <a href="schedule.php?id=<?php echo $exam['id']; ?>" class="text-green-600 hover:text-green-800 p-2" title="Schedule">
                                <i class="fas fa-calendar-alt"></i>
                            </a>
                            <a href="results/upload.php?exam_id=<?php echo $exam['id']; ?>" class="text-blue-600 hover:text-blue-800 p-2" title="Upload Results">
                                <i class="fas fa-upload"></i>
                            </a>
                            <a href="edit.php?id=<?php echo $exam['id']; ?>" class="text-yellow-600 hover:text-yellow-800 p-2" title="Edit">
                                <i class="fas fa-edit"></i>
                            </a>
                            <a href="?delete=<?php echo $exam['id']; ?>" onclick="return confirm('Delete this exam?')" class="text-red-600 hover:text-red-800 p-2" title="Delete">
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
                    <h3 class="text-xl font-semibold text-gray-600">No Exams Created Yet</h3>
                    <p class="text-gray-400 mt-2">Click "Create Exam" to get started</p>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>