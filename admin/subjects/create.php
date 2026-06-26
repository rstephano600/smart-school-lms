<?php
require_once '../../config.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';
requireRole('admin');

$page_title = 'Add New Subject';
include '../../includes/header.php';
include '../../includes/sidebar.php';
include '../../includes/navbar.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = sanitizeInput($_POST['name']);
    $code = strtoupper(sanitizeInput($_POST['code']));
    $description = sanitizeInput($_POST['description']);
    $is_core = isset($_POST['is_core']) ? 1 : 0;
    
    // Validate
    if (empty($name) || empty($code)) {
        $error = "Subject name and code are required";
    } else {
        // Check if code exists
        $check = $conn->prepare("SELECT id FROM subjects WHERE code = ?");
        $check->bind_param("s", $code);
        $check->execute();
        if ($check->get_result()->num_rows > 0) {
            $error = "Subject code already exists";
        } else {
            $query = "INSERT INTO subjects (name, code, description, is_core) VALUES (?, ?, ?, ?)";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("sssi", $name, $code, $description, $is_core);
            
            if ($stmt->execute()) {
                $subject_id = $conn->insert_id;
                logActivity($_SESSION['user_id'], 'created new subject', 'subject', $subject_id);
                $success = "Subject created successfully!";
                
                // Clear form
                $_POST = [];
            } else {
                $error = "Failed to create subject: " . $conn->error;
            }
        }
    }
}
?>

<div class="ml-64 mt-16 p-6">
    <div class="max-w-2xl mx-auto">
        <div class="mb-6">
            <h1 class="text-2xl font-bold text-gray-800">Add New Subject</h1>
            <p class="text-gray-500 mt-1">Create a new subject for the school curriculum</p>
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
                    <label class="block text-sm font-medium text-gray-700 mb-1">Subject Name *</label>
                    <input type="text" name="name" required 
                           value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>"
                           placeholder="e.g., Mathematics, English, Physics"
                           class="w-full border rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>

                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Subject Code *</label>
                    <input type="text" name="code" required 
                           value="<?php echo htmlspecialchars($_POST['code'] ?? ''); ?>"
                           placeholder="e.g., MATH, ENG, PHY"
                           class="w-full border rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <p class="text-xs text-gray-500 mt-1">Unique identifier for the subject (e.g., MATH, BIO, CHEM)</p>
                </div>

                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                    <textarea name="description" rows="3" 
                              placeholder="Brief description of the subject"
                              class="w-full border rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"><?php echo htmlspecialchars($_POST['description'] ?? ''); ?></textarea>
                </div>

                <div class="mb-4">
                    <label class="flex items-center">
                        <input type="checkbox" name="is_core" <?php echo isset($_POST['is_core']) ? 'checked' : ''; ?>
                               class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                        <span class="ml-2 text-sm text-gray-700">Core Subject (compulsory for all students)</span>
                    </label>
                </div>

                <div class="flex justify-end space-x-3 mt-6 pt-4 border-t">
                    <a href="index.php" class="px-4 py-2 border rounded-lg hover:bg-gray-50">Cancel</a>
                    <button type="submit" class="px-6 py-2 bg-gradient-to-r from-blue-500 to-purple-600 text-white rounded-lg hover:shadow-lg">
                        <i class="fas fa-save mr-2"></i> Create Subject
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>