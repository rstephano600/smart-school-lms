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

$page_title = 'Edit Class';
include '../../includes/header.php';
include '../../includes/sidebar.php';
include '../../includes/navbar.php';

$error = '';
$success = '';

// Get teachers
$teachers = $conn->query("
    SELECT t.id, u.first_name, u.last_name 
    FROM teachers t 
    JOIN users u ON t.user_id = u.id 
    WHERE u.is_active = 1
    ORDER BY u.first_name
");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = sanitizeInput($_POST['name']);
    $code = strtoupper(sanitizeInput($_POST['code']));
    $capacity = intval($_POST['capacity']);
    $class_teacher_id = !empty($_POST['class_teacher_id']) ? intval($_POST['class_teacher_id']) : null;
    
    if (empty($name) || empty($code)) {
        $error = "Class name and code are required";
    } else {
        // Check if code exists for other classes
        $check = $conn->prepare("SELECT id FROM classes WHERE code = ? AND id != ?");
        $check->bind_param("si", $code, $class_id);
        $check->execute();
        if ($check->get_result()->num_rows > 0) {
            $error = "Class code already exists";
        } else {
            // Get old teacher id
            $old_teacher_id = $class['class_teacher_id'];
            
            $query = "UPDATE classes SET name = ?, code = ?, capacity = ?, class_teacher_id = ? WHERE id = ?";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("ssiii", $name, $code, $capacity, $class_teacher_id, $class_id);
            
            if ($stmt->execute()) {
                // Update old teacher's class_teacher_of
                if ($old_teacher_id) {
                    $update = $conn->prepare("UPDATE teachers SET class_teacher_of = NULL WHERE id = ?");
                    $update->bind_param("i", $old_teacher_id);
                    $update->execute();
                }
                
                // Update new teacher's class_teacher_of
                if ($class_teacher_id) {
                    $update = $conn->prepare("UPDATE teachers SET class_teacher_of = ? WHERE id = ?");
                    $update->bind_param("ii", $class_id, $class_teacher_id);
                    $update->execute();
                }
                
                logActivity($_SESSION['user_id'], 'updated class', 'class', $class_id);
                $success = "Class updated successfully!";
                
                // Refresh class data
                $stmt = $conn->prepare("SELECT * FROM classes WHERE id = ?");
                $stmt->bind_param("i", $class_id);
                $stmt->execute();
                $class = $stmt->get_result()->fetch_assoc();
            } else {
                $error = "Failed to update class: " . $conn->error;
            }
        }
    }
}
?>

<div class="ml-64 mt-16 p-6">
    <div class="max-w-2xl mx-auto">
        <div class="mb-6">
            <h1 class="text-2xl font-bold text-gray-800">Edit Class</h1>
            <p class="text-gray-500 mt-1">Update class information</p>
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
                    <label class="block text-sm font-medium text-gray-700 mb-1">Class Name *</label>
                    <input type="text" name="name" required value="<?php echo htmlspecialchars($class['name']); ?>"
                           class="w-full border rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>

                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Class Code *</label>
                    <input type="text" name="code" required value="<?php echo htmlspecialchars($class['code']); ?>"
                           class="w-full border rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>

                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Capacity</label>
                    <input type="number" name="capacity" value="<?php echo $class['capacity']; ?>"
                           class="w-full border rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>

                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Class Teacher</label>
                    <select name="class_teacher_id" class="w-full border rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <option value="">Select Class Teacher</option>
                        <?php while($teacher = $teachers->fetch_assoc()): ?>
                            <option value="<?php echo $teacher['id']; ?>"
                                <?php echo ($class['class_teacher_id'] == $teacher['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($teacher['first_name'] . ' ' . $teacher['last_name']); ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>

                <div class="flex justify-end space-x-3 mt-6 pt-4 border-t">
                    <a href="index.php" class="px-4 py-2 border rounded-lg hover:bg-gray-50">Cancel</a>
                    <button type="submit" class="px-6 py-2 bg-gradient-to-r from-blue-500 to-purple-600 text-white rounded-lg hover:shadow-lg">
                        <i class="fas fa-save mr-2"></i> Save Changes
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>