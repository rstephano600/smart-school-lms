<?php
require_once '../../config.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';
requireRole('teacher');

$page_title = 'Create Discussion Group';
include '../../includes/header.php';
include '../../includes/sidebar.php';
include '../../includes/navbar.php';

// Get teacher ID
$teacher_query = $conn->prepare("SELECT id FROM teachers WHERE user_id = ?");
$teacher_query->bind_param("i", $_SESSION['user_id']);
$teacher_query->execute();
$teacher = $teacher_query->get_result()->fetch_assoc();
$teacher_id = $teacher['id'] ?? 0;

// Get classes taught by this teacher
$classes = $conn->prepare("
    SELECT DISTINCT c.id, c.name 
    FROM class_subject cs
    JOIN classes c ON cs.class_id = c.id
    WHERE cs.teacher_id = ?
");
$classes->bind_param("i", $teacher_id);
$classes->execute();
$classes = $classes->get_result();

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $class_id = intval($_POST['class_id']);
    $name = sanitizeInput($_POST['name']);
    $description = sanitizeInput($_POST['description'] ?? '');
    
    // Validate
    if (empty($name)) {
        $error = "Group name is required!";
    } elseif (empty($class_id)) {
        $error = "Please select a class!";
    } else {
        // Check if group already exists
        $check = $conn->prepare("SELECT id FROM discussion_groups WHERE name = ? AND class_id = ? AND teacher_id = ?");
        $check->bind_param("sii", $name, $class_id, $teacher_id);
        $check->execute();
        if ($check->get_result()->num_rows > 0) {
            $error = "A group with this name already exists for this class!";
        } else {
            $insert = $conn->prepare("
                INSERT INTO discussion_groups (teacher_id, class_id, name, description, is_active)
                VALUES (?, ?, ?, ?, 1)
            ");
            $insert->bind_param("iiss", $teacher_id, $class_id, $name, $description);
            
            if ($insert->execute()) {
                $group_id = $conn->insert_id;
                
                // Log activity
                logActivity($_SESSION['user_id'], 'created discussion group', 'discussion_group', $group_id);
                
                $_SESSION['success'] = "Discussion group '$name' created successfully!";
                header('Location: index.php');
                exit();
            } else {
                $error = "Failed to create group. Please try again.";
            }
        }
    }
}
?>

<style>
.form-container {
    max-width: 600px;
    margin: 0 auto;
}
.form-label {
    font-weight: 500;
    color: #374151;
    margin-bottom: 0.25rem;
    display: block;
}
.form-control {
    width: 100%;
    border: 1px solid #d1d5db;
    border-radius: 0.5rem;
    padding: 0.5rem 0.75rem;
    transition: all 0.2s;
}
.form-control:focus {
    outline: none;
    border-color: #3b82f6;
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
}
.form-control:disabled {
    background-color: #f3f4f6;
    cursor: not-allowed;
}
</style>

<div class="ml-64 mt-16 p-6 bg-gray-50 min-h-screen">
    <div class="form-container">
        <!-- Header -->
        <div class="mb-6 flex justify-between items-center">
            <div>
                <h1 class="text-2xl font-bold text-gray-800">💬 Create Discussion Group</h1>
                <p class="text-gray-500 mt-1">Create a new discussion group for your students</p>
            </div>
            <a href="index.php" class="text-blue-600 hover:text-blue-800 flex items-center">
                <i class="fas fa-arrow-left mr-2"></i> Back
            </a>
        </div>

        <!-- Error/Success -->
        <?php if($error): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg mb-4 flex items-start">
                <i class="fas fa-exclamation-circle mt-1 mr-3"></i>
                <div><?php echo $error; ?></div>
            </div>
        <?php endif; ?>

        <!-- Form -->
        <div class="bg-white rounded-xl shadow-sm p-6">
            <form method="POST">
                <!-- Class Selection -->
                <div class="mb-5">
                    <label class="form-label">Class *</label>
                    <select name="class_id" required class="form-control">
                        <option value="">Select Class</option>
                        <?php while($class = $classes->fetch_assoc()): ?>
                            <option value="<?php echo $class['id']; ?>">
                                <?php echo htmlspecialchars($class['name']); ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                    <?php if($classes->num_rows == 0): ?>
                        <p class="text-sm text-red-500 mt-1">
                            <i class="fas fa-exclamation-triangle mr-1"></i> 
                            You are not assigned to any class yet. Contact administrator.
                        </p>
                    <?php endif; ?>
                </div>

                <!-- Group Name -->
                <div class="mb-5">
                    <label class="form-label">Group Name *</label>
                    <input type="text" name="name" required 
                           placeholder="e.g., COMPUTER SCIENCE"
                           class="form-control"
                           value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>">
                    <p class="text-sm text-gray-500 mt-1">Choose a descriptive name for your discussion group.</p>
                </div>

                <!-- Description -->
                <div class="mb-5">
                    <label class="form-label">Description</label>
                    <textarea name="description" rows="5" 
                              placeholder="Describe the purpose of this discussion group..."
                              class="form-control"><?php echo htmlspecialchars($_POST['description'] ?? ''); ?></textarea>
                    <p class="text-sm text-gray-500 mt-1">Optional: Explain what topics will be discussed.</p>
                </div>

                <!-- Tips -->
                <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-6">
                    <h4 class="text-sm font-semibold text-blue-800 flex items-center">
                        <i class="fas fa-lightbulb mr-2"></i> Tips for a Great Discussion Group
                    </h4>
                    <ul class="text-sm text-blue-700 mt-2 space-y-1">
                        <li>• <strong>Keep it focused</strong> - One subject per group works best</li>
                        <li>• <strong>Set clear expectations</strong> - Let students know the purpose</li>
                        <li>• <strong>Be active</strong> - Regular participation encourages engagement</li>
                    </ul>
                </div>

                <!-- Buttons -->
                <div class="flex justify-end space-x-3 pt-4 border-t">
                    <a href="index.php" class="px-6 py-2 border border-gray-300 rounded-lg hover:bg-gray-50 transition">
                        Cancel
                    </a>
                    <button type="submit" class="px-6 py-2 bg-gradient-to-r from-blue-500 to-purple-600 text-white rounded-lg hover:shadow-lg transition flex items-center" 
                            <?php echo $classes->num_rows == 0 ? 'disabled' : ''; ?>>
                        <i class="fas fa-plus mr-2"></i> Create Group
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>