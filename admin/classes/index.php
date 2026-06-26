<?php
require_once '../../config.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';
requireRole('admin');

$page_title = 'Class Management';
include '../../includes/header.php';
include '../../includes/sidebar.php';
include '../../includes/navbar.php';

// Handle class deletion
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $class_id = $_GET['delete'];
    
    // Check if class has students
    $check = $conn->prepare("SELECT COUNT(*) as count FROM students WHERE class_id = ?");
    $check->bind_param("i", $class_id);
    $check->execute();
    $result = $check->get_result();
    $has_students = $result->fetch_assoc()['count'] > 0;
    
    if (!$has_students) {
        $query = "DELETE FROM classes WHERE id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $class_id);
        if ($stmt->execute()) {
            logActivity($_SESSION['user_id'], 'deleted class', 'class', $class_id);
            echo '<script>showToast("Class deleted successfully", "success");</script>';
        }
    } else {
        echo '<script>showToast("Cannot delete class with enrolled students", "error");</script>';
    }
}

// Get all classes with teacher names
$query = "SELECT c.*, 
          CONCAT(u.first_name, ' ', u.last_name) as teacher_name
          FROM classes c
          LEFT JOIN teachers t ON c.class_teacher_id = t.id
          LEFT JOIN users u ON t.user_id = u.id
          ORDER BY c.name";
$classes = $conn->query($query);
?>

<div class="ml-64 mt-16 p-6">
    <!-- Page Header -->
    <div class="flex justify-between items-center mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-800">Class Management</h1>
            <p class="text-gray-500 mt-1">Manage school classes and streams</p>
        </div>
        <div class="flex space-x-3">
            <a href="streams.php" class="bg-green-600 text-white px-6 py-2 rounded-lg hover:bg-green-700 transition-all duration-300">
                <i class="fas fa-stream mr-2"></i> Manage Streams
            </a>
            <a href="create.php" class="bg-gradient-to-r from-blue-500 to-purple-600 text-white px-6 py-2 rounded-lg hover:shadow-lg transition-all duration-300">
                <i class="fas fa-plus mr-2"></i> Add New Class
            </a>
        </div>
    </div>

    <!-- Classes Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        <?php if ($classes && $classes->num_rows > 0): ?>
            <?php while($class = $classes->fetch_assoc()): ?>
                <div class="bg-white rounded-xl shadow-sm hover:shadow-lg transition-all duration-300 overflow-hidden">
                    <div class="bg-gradient-to-r from-blue-500 to-purple-600 p-4">
                        <div class="flex justify-between items-center">
                            <div>
                                <h3 class="text-xl font-bold text-white"><?php echo htmlspecialchars($class['name']); ?></h3>
                                <p class="text-blue-100 text-sm">Code: <?php echo htmlspecialchars($class['code']); ?></p>
                            </div>
                            <div class="w-12 h-12 bg-white bg-opacity-20 rounded-full flex items-center justify-center">
                                <i class="fas fa-school text-white text-xl"></i>
                            </div>
                        </div>
                    </div>
                    
                    <div class="p-4">
                        <div class="space-y-3">
                            <div class="flex items-center justify-between">
                                <span class="text-gray-500 text-sm">Class Teacher:</span>
                                <span class="font-medium text-gray-700"><?php echo $class['teacher_name'] ?? 'Not Assigned'; ?></span>
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="text-gray-500 text-sm">Capacity:</span>
                                <span class="font-medium text-gray-700"><?php echo $class['capacity']; ?> students</span>
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="text-gray-500 text-sm">Streams:</span>
                                <a href="streams.php?class_id=<?php echo $class['id']; ?>" class="text-blue-600 hover:text-blue-800">
                                    View Streams <i class="fas fa-arrow-right ml-1"></i>
                                </a>
                            </div>
                        </div>
                        
                        <div class="flex justify-end space-x-2 mt-4 pt-3 border-t">
                            <a href="edit.php?id=<?php echo $class['id']; ?>" 
                               class="text-blue-600 hover:text-blue-800 p-2">
                                <i class="fas fa-edit"></i>
                            </a>
                            <a href="assign-teacher.php?id=<?php echo $class['id']; ?>" 
                               class="text-green-600 hover:text-green-800 p-2" title="Assign Teacher">
                                <i class="fas fa-chalkboard-user"></i>
                            </a>
                            <a href="?delete=<?php echo $class['id']; ?>" 
                               onclick="return confirm('Are you sure you want to delete this class?')"
                               class="text-red-600 hover:text-red-800 p-2">
                                <i class="fas fa-trash-alt"></i>
                            </a>
                        </div>
                    </div>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <div class="col-span-full">
                <div class="bg-white rounded-xl shadow-sm p-12 text-center">
                    <i class="fas fa-school text-6xl text-gray-300 mb-4"></i>
                    <h3 class="text-xl font-semibold text-gray-600">No Classes Yet</h3>
                    <p class="text-gray-400 mt-2">Click "Add New Class" to create your first class</p>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>