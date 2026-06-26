<?php
require_once '../../config.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';
requireRole('admin');

$class_id = $_GET['id'] ?? 0;
if (!$class_id) {
    header('Location: index.php');
    exit();
}

// Get class data
$query = "SELECT * FROM classes WHERE id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $class_id);
$stmt->execute();
$class = $stmt->get_result()->fetch_assoc();

if (!$class) {
    header('Location: index.php');
    exit();
}

$page_title = 'Assign Class Teacher';
include '../../includes/header.php';
include '../../includes/sidebar.php';
include '../../includes/navbar.php';

$error = '';
$success = '';

// Get available teachers
$teachers = $conn->query("
    SELECT t.id, u.first_name, u.last_name, u.email, 
           CASE WHEN t.class_teacher_of IS NULL THEN 'Available' ELSE 'Assigned to another class' END as status
    FROM teachers t 
    JOIN users u ON t.user_id = u.id 
    WHERE u.is_active = 1
    ORDER BY u.first_name
");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $teacher_id = !empty($_POST['teacher_id']) ? intval($_POST['teacher_id']) : null;
    
    // Get old teacher id
    $old_teacher_id = $class['class_teacher_id'];
    
    // Update class
    $query = "UPDATE classes SET class_teacher_id = ? WHERE id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ii", $teacher_id, $class_id);
    
    if ($stmt->execute()) {
        // Clear old teacher's assignment
        if ($old_teacher_id) {
            $update = $conn->prepare("UPDATE teachers SET class_teacher_of = NULL WHERE id = ?");
            $update->bind_param("i", $old_teacher_id);
            $update->execute();
        }
        
        // Set new teacher's assignment
        if ($teacher_id) {
            $update = $conn->prepare("UPDATE teachers SET class_teacher_of = ? WHERE id = ?");
            $update->bind_param("ii", $class_id, $teacher_id);
            $update->execute();
        }
        
        logActivity($_SESSION['user_id'], 'assigned class teacher', 'class', $class_id);
        $success = "Class teacher assigned successfully!";
        
        // Refresh class data
        $stmt = $conn->prepare("SELECT * FROM classes WHERE id = ?");
        $stmt->bind_param("i", $class_id);
        $stmt->execute();
        $class = $stmt->get_result()->fetch_assoc();
    } else {
        $error = "Failed to assign teacher: " . $conn->error;
    }
}
?>

<div class="ml-64 mt-16 p-6">
    <div class="max-w-2xl mx-auto">
        <div class="mb-6">
            <h1 class="text-2xl font-bold text-gray-800">Assign Class Teacher</h1>
            <p class="text-gray-500 mt-1">Assign a teacher to be responsible for <?php echo htmlspecialchars($class['name']); ?></p>
        </div>

        <?php if($error): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg mb-4"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <?php if($success): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-lg mb-4"><?php echo $success; ?></div>
        <?php endif; ?>

        <div class="bg-white rounded-xl shadow-sm p-6">
            <form method="POST">
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Current Class Teacher</label>
                    <div class="p-3 bg-gray-50 rounded-lg">
                        <?php
                        if ($class['class_teacher_id']) {
                            $current = $conn->prepare("
                                SELECT CONCAT(u.first_name, ' ', u.last_name) as name 
                                FROM teachers t 
                                JOIN users u ON t.user_id = u.id 
                                WHERE t.id = ?
                            ");
                            $current->bind_param("i", $class['class_teacher_id']);
                            $current->execute();
                            $current_result = $current->get_result();
                            $current_teacher = $current_result->fetch_assoc();
                            echo htmlspecialchars($current_teacher['name'] ?? 'Unknown');
                        } else {
                            echo '<span class="text-gray-500">No teacher assigned</span>';
                        }
                        ?>
                    </div>
                </div>

                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Select New Teacher</label>
                    <select name="teacher_id" class="w-full border rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <option value="">-- Remove Current Teacher --</option>
                        <?php while($teacher = $teachers->fetch_assoc()): ?>
                            <option value="<?php echo $teacher['id']; ?>"
                                <?php echo ($class['class_teacher_id'] == $teacher['id']) ? 'selected' : ''; ?>
                                <?php echo $teacher['status'] != 'Available' && $class['class_teacher_id'] != $teacher['id'] ? 'disabled' : ''; ?>>
                                <?php echo htmlspecialchars($teacher['first_name'] . ' ' . $teacher['last_name']); ?>
                                (<?php echo $teacher['status']; ?>)
                            </option>
                        <?php endwhile; ?>
                    </select>
                    <p class="text-xs text-gray-500 mt-1">Only available teachers can be assigned</p>
                </div>

                <div class="flex justify-end space-x-3 mt-6 pt-4 border-t">
                    <a href="index.php" class="px-4 py-2 border rounded-lg hover:bg-gray-50">Cancel</a>
                    <button type="submit" class="px-6 py-2 bg-gradient-to-r from-blue-500 to-purple-600 text-white rounded-lg hover:shadow-lg">
                        <i class="fas fa-save mr-2"></i> Assign Teacher
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>