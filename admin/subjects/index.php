<?php
require_once '../../config.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';
requireRole('admin');

$page_title = 'Subject Management';
include '../../includes/header.php';
include '../../includes/sidebar.php';
include '../../includes/navbar.php';

// Handle subject deletion
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $subject_id = $_GET['delete'];
    
    // Check if subject is used in class_subject
    $check = $conn->prepare("SELECT COUNT(*) as count FROM class_subject WHERE subject_id = ?");
    $check->bind_param("i", $subject_id);
    $check->execute();
    $result = $check->get_result();
    $is_used = $result->fetch_assoc()['count'] > 0;
    
    if (!$is_used) {
        $query = "DELETE FROM subjects WHERE id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $subject_id);
        if ($stmt->execute()) {
            logActivity($_SESSION['user_id'], 'deleted subject', 'subject', $subject_id);
            echo '<script>showToast("Subject deleted successfully", "success");</script>';
        } else {
            echo '<script>showToast("Failed to delete subject", "error");</script>';
        }
    } else {
        echo '<script>showToast("Cannot delete subject assigned to classes", "error");</script>';
    }
}

// Get search and filter
$search = $_GET['search'] ?? '';
$filter_core = $_GET['core'] ?? '';

// Build query
$query = "SELECT * FROM subjects WHERE 1=1";
if (!empty($search)) {
    $query .= " AND (name LIKE '%$search%' OR code LIKE '%$search%')";
}
if ($filter_core === 'core') {
    $query .= " AND is_core = 1";
} elseif ($filter_core === 'elective') {
    $query .= " AND is_core = 0";
}
$query .= " ORDER BY is_core DESC, name ASC";
$subjects = $conn->query($query);
?>

<div class="ml-64 mt-16 p-6">
    <!-- Page Header -->
    <div class="flex justify-between items-center mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-800">Subject Management</h1>
            <p class="text-gray-500 mt-1">Manage school subjects and assign teachers to classes</p>
        </div>
        <div class="flex space-x-3">
            <a href="assign-teacher.php" class="bg-green-600 text-white px-6 py-2 rounded-lg hover:bg-green-700 transition-all duration-300">
                <i class="fas fa-chalkboard-user mr-2"></i> Assign Teachers
            </a>
            <a href="create.php" class="bg-gradient-to-r from-blue-500 to-purple-600 text-white px-6 py-2 rounded-lg hover:shadow-lg transition-all duration-300">
                <i class="fas fa-plus mr-2"></i> Add Subject
            </a>
        </div>
    </div>

    <!-- Filters -->
    <div class="bg-white rounded-xl shadow-sm p-4 mb-6">
        <form method="GET" class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Search</label>
                <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" 
                       placeholder="Subject name or code..." 
                       class="w-full border rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Type</label>
                <select name="core" class="w-full border rounded-lg px-3 py-2">
                    <option value="">All Subjects</option>
                    <option value="core" <?php echo $filter_core == 'core' ? 'selected' : ''; ?>>Core Subjects</option>
                    <option value="elective" <?php echo $filter_core == 'elective' ? 'selected' : ''; ?>>Elective Subjects</option>
                </select>
            </div>
            <div class="flex items-end">
                <button type="submit" class="w-full bg-gray-600 text-white px-4 py-2 rounded-lg hover:bg-gray-700">
                    <i class="fas fa-search mr-2"></i> Filter
                </button>
            </div>
        </form>
    </div>

    <!-- Subjects Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        <?php if ($subjects && $subjects->num_rows > 0): ?>
            <?php while($subject = $subjects->fetch_assoc()): ?>
                <div class="bg-white rounded-xl shadow-sm hover:shadow-lg transition-all duration-300 overflow-hidden">
                    <div class="p-4 border-b <?php echo $subject['is_core'] ? 'bg-gradient-to-r from-blue-500 to-purple-600' : 'bg-gray-100'; ?>">
                        <div class="flex justify-between items-center">
                            <div>
                                <h3 class="text-xl font-bold <?php echo $subject['is_core'] ? 'text-white' : 'text-gray-800'; ?>">
                                    <?php echo htmlspecialchars($subject['name']); ?>
                                </h3>
                                <p class="<?php echo $subject['is_core'] ? 'text-blue-100' : 'text-gray-500'; ?> text-sm">
                                    Code: <?php echo htmlspecialchars($subject['code']); ?>
                                </p>
                            </div>
                            <div class="w-12 h-12 <?php echo $subject['is_core'] ? 'bg-white bg-opacity-20' : 'bg-gray-200'; ?> rounded-full flex items-center justify-center">
                                <i class="fas fa-book <?php echo $subject['is_core'] ? 'text-white' : 'text-gray-600'; ?> text-xl"></i>
                            </div>
                        </div>
                    </div>
                    
                    <div class="p-4">
                        <div class="space-y-3">
                            <div class="flex items-center justify-between">
                                <span class="text-gray-500 text-sm">Status:</span>
                                <span class="px-2 py-1 text-xs rounded-full <?php echo $subject['is_core'] ? 'bg-blue-100 text-blue-700' : 'bg-gray-100 text-gray-700'; ?>">
                                    <?php echo $subject['is_core'] ? 'Core Subject' : 'Elective Subject'; ?>
                                </span>
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="text-gray-500 text-sm">Description:</span>
                                <span class="text-gray-700 text-sm truncate max-w-[150px]">
                                    <?php echo htmlspecialchars($subject['description'] ?? 'No description'); ?>
                                </span>
                            </div>
                        </div>
                        
                        <div class="flex justify-end space-x-2 mt-4 pt-3 border-t">
                            <a href="edit.php?id=<?php echo $subject['id']; ?>" 
                               class="text-blue-600 hover:text-blue-800 p-2" title="Edit">
                                <i class="fas fa-edit"></i>
                            </a>
                            <a href="view-assignments.php?id=<?php echo $subject['id']; ?>" 
                               class="text-green-600 hover:text-green-800 p-2" title="View Class Assignments">
                                <i class="fas fa-chalkboard"></i>
                            </a>
                            <a href="?delete=<?php echo $subject['id']; ?>" 
                               onclick="return confirm('Are you sure you want to delete this subject?')"
                               class="text-red-600 hover:text-red-800 p-2" title="Delete">
                                <i class="fas fa-trash-alt"></i>
                            </a>
                        </div>
                    </div>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <div class="col-span-full">
                <div class="bg-white rounded-xl shadow-sm p-12 text-center">
                    <i class="fas fa-book text-6xl text-gray-300 mb-4"></i>
                    <h3 class="text-xl font-semibold text-gray-600">No Subjects Yet</h3>
                    <p class="text-gray-400 mt-2">Click "Add Subject" to create your first subject</p>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>