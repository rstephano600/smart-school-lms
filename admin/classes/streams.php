<?php
require_once '../../config.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';
requireRole('admin');

$page_title = 'Manage Streams';
include '../../includes/header.php';
include '../../includes/sidebar.php';
include '../../includes/navbar.php';

// Handle stream deletion
if (isset($_GET['delete_stream']) && is_numeric($_GET['delete_stream'])) {
    $stream_id = $_GET['delete_stream'];
    
    // Check if stream has students
    $check = $conn->prepare("SELECT COUNT(*) as count FROM students WHERE stream_id = ?");
    $check->bind_param("i", $stream_id);
    $check->execute();
    $result = $check->get_result();
    $has_students = $result->fetch_assoc()['count'] > 0;
    
    if (!$has_students) {
        $query = "DELETE FROM streams WHERE id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $stream_id);
        if ($stmt->execute()) {
            logActivity($_SESSION['user_id'], 'deleted stream', 'stream', $stream_id);
            echo '<script>showToast("Stream deleted successfully", "success");</script>';
        }
    } else {
        echo '<script>showToast("Cannot delete stream with enrolled students", "error");</script>';
    }
}

// Handle stream creation/update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'create') {
        $name = sanitizeInput($_POST['name']);
        $class_id = intval($_POST['class_id']);
        $code = sanitizeInput($_POST['code']);
        
        $query = "INSERT INTO streams (name, class_id, code) VALUES (?, ?, ?)";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("sis", $name, $class_id, $code);
        
        if ($stmt->execute()) {
            logActivity($_SESSION['user_id'], 'created stream', 'stream', $conn->insert_id);
            echo '<script>showToast("Stream created successfully", "success");</script>';
        } else {
            echo '<script>showToast("Failed to create stream", "error");</script>';
        }
    } elseif ($_POST['action'] === 'edit') {
        $stream_id = intval($_POST['stream_id']);
        $name = sanitizeInput($_POST['name']);
        $code = sanitizeInput($_POST['code']);
        
        $query = "UPDATE streams SET name = ?, code = ? WHERE id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("ssi", $name, $code, $stream_id);
        
        if ($stmt->execute()) {
            logActivity($_SESSION['user_id'], 'updated stream', 'stream', $stream_id);
            echo '<script>showToast("Stream updated successfully", "success");</script>';
        } else {
            echo '<script>showToast("Failed to update stream", "error");</script>';
        }
    }
}

// Get classes for dropdown
$classes = $conn->query("SELECT id, name FROM classes ORDER BY name");

// Get selected class filter
$selected_class = isset($_GET['class_id']) ? intval($_GET['class_id']) : 0;

// Get streams with class names
$query = "SELECT s.*, c.name as class_name 
          FROM streams s 
          JOIN classes c ON s.class_id = c.id";
if ($selected_class > 0) {
    $query .= " WHERE s.class_id = $selected_class";
}
$query .= " ORDER BY c.name, s.name";
$streams = $conn->query($query);
?>

<div class="ml-64 mt-16 p-6">
    <!-- Page Header -->
    <div class="flex justify-between items-center mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-800">Manage Streams</h1>
            <p class="text-gray-500 mt-1">Create and manage class streams (e.g., East, West, Science, Arts)</p>
        </div>
        <a href="index.php" class="bg-gray-600 text-white px-6 py-2 rounded-lg hover:bg-gray-700">
            <i class="fas fa-arrow-left mr-2"></i> Back to Classes
        </a>
    </div>

    <!-- Filter -->
    <div class="bg-white rounded-xl shadow-sm p-4 mb-6">
        <form method="GET" class="flex gap-4 items-end">
            <div class="flex-1">
                <label class="block text-sm font-medium text-gray-700 mb-1">Filter by Class</label>
                <select name="class_id" class="w-full border rounded-lg px-3 py-2" onchange="this.form.submit()">
                    <option value="0">All Classes</option>
                    <?php 
                    $classes->data_seek(0);
                    while($class = $classes->fetch_assoc()): ?>
                        <option value="<?php echo $class['id']; ?>" <?php echo $selected_class == $class['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($class['name']); ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>
        </form>
    </div>

    <!-- Create Stream Form -->
    <div class="bg-white rounded-xl shadow-sm p-6 mb-6">
        <h3 class="text-lg font-semibold mb-4">Add New Stream</h3>
        <form method="POST" class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <input type="hidden" name="action" value="create">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Class</label>
                <select name="class_id" required class="w-full border rounded-lg px-3 py-2">
                    <option value="">Select Class</option>
                    <?php 
                    $classes->data_seek(0);
                    while($class = $classes->fetch_assoc()): ?>
                        <option value="<?php echo $class['id']; ?>"><?php echo htmlspecialchars($class['name']); ?></option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Stream Name</label>
                <input type="text" name="name" required placeholder="e.g., East, Science, Arts"
                       class="w-full border rounded-lg px-3 py-2">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Stream Code</label>
                <input type="text" name="code" placeholder="e.g., EST, SCI, ART"
                       class="w-full border rounded-lg px-3 py-2">
            </div>
            <div class="md:col-span-3">
                <button type="submit" class="bg-gradient-to-r from-blue-500 to-purple-600 text-white px-6 py-2 rounded-lg">
                    <i class="fas fa-plus mr-2"></i> Add Stream
                </button>
            </div>
        </form>
    </div>

    <!-- Streams List -->
    <div class="bg-white rounded-xl shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-gray-50 border-b">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Class</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Stream Name</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Code</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    <?php if ($streams && $streams->num_rows > 0): ?>
                        <?php while($stream = $streams->fetch_assoc()): ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4 font-medium"><?php echo htmlspecialchars($stream['class_name']); ?></td>
                                <td class="px-6 py-4">
                                    <span class="px-2 py-1 bg-blue-100 text-blue-700 rounded-full text-sm">
                                        <?php echo htmlspecialchars($stream['name']); ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-gray-500"><?php echo htmlspecialchars($stream['code'] ?? '-'); ?></td>
                                <td class="px-6 py-4">
                                    <div class="flex space-x-2">
                                        <button onclick="editStream(<?php echo $stream['id']; ?>, '<?php echo htmlspecialchars($stream['name']); ?>', '<?php echo htmlspecialchars($stream['code']); ?>')"
                                                class="text-blue-600 hover:text-blue-800">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <a href="?delete_stream=<?php echo $stream['id']; ?>&class_id=<?php echo $selected_class; ?>" 
                                           onclick="return confirm('Are you sure you want to delete this stream?')"
                                           class="text-red-600 hover:text-red-800">
                                            <i class="fas fa-trash-alt"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="4" class="px-6 py-12 text-center text-gray-500">
                                <i class="fas fa-stream text-4xl mb-2 block"></i>
                                No streams found
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
             </table>
        </div>
    </div>
</div>

<!-- Edit Stream Modal -->
<div id="editModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 items-center justify-center">
    <div class="bg-white rounded-xl w-full max-w-md p-6">
        <h3 class="text-lg font-semibold mb-4">Edit Stream</h3>
        <form method="POST">
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="stream_id" id="edit_stream_id">
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">Stream Name</label>
                <input type="text" name="name" id="edit_name" required class="w-full border rounded-lg px-3 py-2">
            </div>
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">Stream Code</label>
                <input type="text" name="code" id="edit_code" class="w-full border rounded-lg px-3 py-2">
            </div>
            <div class="flex justify-end space-x-3">
                <button type="button" onclick="closeModal()" class="px-4 py-2 border rounded-lg hover:bg-gray-50">Cancel</button>
                <button type="submit" class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<script>
function editStream(id, name, code) {
    document.getElementById('edit_stream_id').value = id;
    document.getElementById('edit_name').value = name;
    document.getElementById('edit_code').value = code;
    document.getElementById('editModal').classList.remove('hidden');
}

function closeModal() {
    document.getElementById('editModal').classList.add('hidden');
}

// Close modal when clicking outside
document.getElementById('editModal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeModal();
    }
});
</script>

<?php include '../../includes/footer.php'; ?>