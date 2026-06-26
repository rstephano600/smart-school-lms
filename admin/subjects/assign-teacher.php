<?php
require_once '../../config.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';
requireRole('admin');

$page_title = 'Assign Subject Teachers';
include '../../includes/header.php';
include '../../includes/sidebar.php';
include '../../includes/navbar.php';

$error = '';
$success = '';

// Handle deletion of assignment
if (isset($_GET['remove']) && is_numeric($_GET['remove'])) {
    $assignment_id = $_GET['remove'];
    $query = "DELETE FROM class_subject WHERE id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $assignment_id);
    if ($stmt->execute()) {
        logActivity($_SESSION['user_id'], 'removed subject assignment', 'class_subject', $assignment_id);
        echo '<script>showToast("Assignment removed successfully", "success");</script>';
    }
}

// Handle new assignment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'assign') {
    $class_id = intval($_POST['class_id']);
    $subject_id = intval($_POST['subject_id']);
    $teacher_id = intval($_POST['teacher_id']);
    $academic_year = date('Y');
    
    // Check if already assigned
    $check = $conn->prepare("SELECT id FROM class_subject WHERE class_id = ? AND subject_id = ? AND academic_year = ?");
    $check->bind_param("iii", $class_id, $subject_id, $academic_year);
    $check->execute();
    
    if ($check->get_result()->num_rows > 0) {
        $error = "This subject is already assigned to this class for the current academic year";
    } else {
        $query = "INSERT INTO class_subject (class_id, subject_id, teacher_id, academic_year) VALUES (?, ?, ?, ?)";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("iiii", $class_id, $subject_id, $teacher_id, $academic_year);
        
        if ($stmt->execute()) {
            logActivity($_SESSION['user_id'], 'assigned subject to class', 'class_subject', $conn->insert_id);
            $success = "Subject assigned successfully!";
        } else {
            $error = "Failed to assign subject: " . $conn->error;
        }
    }
}

// Get all data for dropdowns
$classes = $conn->query("SELECT id, name FROM classes ORDER BY name");
$subjects = $conn->query("SELECT id, name, code FROM subjects ORDER BY name");
$teachers = $conn->query("
    SELECT t.id, u.first_name, u.last_name, u.email 
    FROM teachers t 
    JOIN users u ON t.user_id = u.id 
    WHERE u.is_active = 1 
    ORDER BY u.first_name
");

// Get current assignments with details
$assignments = $conn->query("
    SELECT cs.*, 
           c.name as class_name,
           s.name as subject_name, s.code as subject_code,
           CONCAT(u.first_name, ' ', u.last_name) as teacher_name
    FROM class_subject cs
    JOIN classes c ON cs.class_id = c.id
    JOIN subjects s ON cs.subject_id = s.id
    JOIN teachers t ON cs.teacher_id = t.id
    JOIN users u ON t.user_id = u.id
    ORDER BY c.name, s.name
");
?>

<div class="ml-64 mt-16 p-6">
    <div class="max-w-6xl mx-auto">
        <div class="mb-6">
            <h1 class="text-2xl font-bold text-gray-800">Assign Subject Teachers</h1>
            <p class="text-gray-500 mt-1">Assign teachers to subjects for each class</p>
        </div>

        <?php if($error): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg mb-4"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <?php if($success): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-lg mb-4"><?php echo $success; ?></div>
        <?php endif; ?>

        <!-- Assignment Form -->
        <div class="bg-white rounded-xl shadow-sm p-6 mb-6">
            <h3 class="text-lg font-semibold mb-4">New Assignment</h3>
            <form method="POST" class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <input type="hidden" name="action" value="assign">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Select Class</label>
                    <select name="class_id" required class="w-full border rounded-lg px-3 py-2">
                        <option value="">Choose Class</option>
                        <?php 
                        $classes->data_seek(0);
                        while($class = $classes->fetch_assoc()): ?>
                            <option value="<?php echo $class['id']; ?>"><?php echo htmlspecialchars($class['name']); ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Select Subject</label>
                    <select name="subject_id" required class="w-full border rounded-lg px-3 py-2">
                        <option value="">Choose Subject</option>
                        <?php 
                        $subjects->data_seek(0);
                        while($subject = $subjects->fetch_assoc()): ?>
                            <option value="<?php echo $subject['id']; ?>">
                                <?php echo htmlspecialchars($subject['name'] . ' (' . $subject['code'] . ')'); ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Assign Teacher</label>
                    <select name="teacher_id" required class="w-full border rounded-lg px-3 py-2">
                        <option value="">Choose Teacher</option>
                        <?php 
                        $teachers->data_seek(0);
                        while($teacher = $teachers->fetch_assoc()): ?>
                            <option value="<?php echo $teacher['id']; ?>">
                                <?php echo htmlspecialchars($teacher['first_name'] . ' ' . $teacher['last_name']); ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="flex items-end">
                    <button type="submit" class="w-full bg-gradient-to-r from-blue-500 to-purple-600 text-white px-4 py-2 rounded-lg hover:shadow-lg">
                        <i class="fas fa-plus mr-2"></i> Assign
                    </button>
                </div>
            </form>
        </div>

        <!-- Current Assignments Table -->
        <div class="bg-white rounded-xl shadow-sm overflow-hidden">
            <div class="px-6 py-4 border-b bg-gray-50">
                <h3 class="text-lg font-semibold">Current Assignments</h3>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-gray-50 border-b">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Class</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Subject</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Teacher</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Academic Year</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <?php if ($assignments && $assignments->num_rows > 0): ?>
                            <?php while($assignment = $assignments->fetch_assoc()): ?>
                                <tr class="hover:bg-gray-50">
                                    <td class="px-6 py-4 font-medium"><?php echo htmlspecialchars($assignment['class_name']); ?></td>
                                    <td class="px-6 py-4">
                                        <span class="px-2 py-1 bg-blue-100 text-blue-700 rounded-full text-sm">
                                            <?php echo htmlspecialchars($assignment['subject_name']); ?>
                                        </span>
                                        <span class="text-gray-400 text-xs ml-1">(<?php echo $assignment['subject_code']; ?>)</span>
                                    </td>
                                    <td class="px-6 py-4"><?php echo htmlspecialchars($assignment['teacher_name']); ?></td>
                                    <td class="px-6 py-4 text-gray-500"><?php echo $assignment['academic_year']; ?></td>
                                    <td class="px-6 py-4">
                                        <a href="?remove=<?php echo $assignment['id']; ?>" 
                                           onclick="return confirm('Are you sure you want to remove this assignment?')"
                                           class="text-red-600 hover:text-red-800">
                                            <i class="fas fa-trash-alt"></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" class="px-6 py-12 text-center text-gray-500">
                                    <i class="fas fa-chalkboard text-4xl mb-2 block"></i>
                                    No subject assignments yet
                                 </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>