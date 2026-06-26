<?php
require_once '../../config.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';
requireRole('admin');

$page_title = 'Create New User';
include '../../includes/header.php';
include '../../includes/sidebar.php';
include '../../includes/navbar.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $first_name = sanitizeInput($_POST['first_name']);
    $last_name = sanitizeInput($_POST['last_name']);
    $email = sanitizeInput($_POST['email']);
    $role = $_POST['role'];
    $phone = sanitizeInput($_POST['phone'] ?? '');
    
    // Generate random password
    $plain_password = generateRandomPassword();
    $hashed_password = password_hash($plain_password, PASSWORD_DEFAULT);
    
    // Check if email exists
    $check = $conn->prepare("SELECT id FROM users WHERE email = ?");
    $check->bind_param("s", $email);
    $check->execute();
    if ($check->get_result()->num_rows > 0) {
        $error = "Email already exists!";
    } else {
        // Insert user
        $query = "INSERT INTO users (email, password, first_name, last_name, phone, role, is_active, is_verified) 
                  VALUES (?, ?, ?, ?, ?, ?, 1, 1)";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("ssssss", $email, $hashed_password, $first_name, $last_name, $phone, $role);
        
        if ($stmt->execute()) {
            $user_id = $conn->insert_id;
            
            // Insert role-specific data
            if ($role == 'student') {
                $admission_no = 'STU' . date('Y') . str_pad($user_id, 4, '0', STR_PAD_LEFT);
                $class_id = $_POST['class_id'] ?? null;
                $stream_id = $_POST['stream_id'] ?? null;
                
                $insert = $conn->prepare("INSERT INTO students (user_id, admission_number, class_id, stream_id) VALUES (?, ?, ?, ?)");
                $insert->bind_param("isii", $user_id, $admission_no, $class_id, $stream_id);
                $insert->execute();
            } elseif ($role == 'teacher') {
                $employee_no = 'TCH' . date('Y') . str_pad($user_id, 4, '0', STR_PAD_LEFT);
                $qualification = sanitizeInput($_POST['qualification'] ?? '');
                $specialization = sanitizeInput($_POST['specialization'] ?? '');
                
                $insert = $conn->prepare("INSERT INTO teachers (user_id, employee_number, qualification, specialization) VALUES (?, ?, ?, ?)");
                $insert->bind_param("isss", $user_id, $employee_no, $qualification, $specialization);
                $insert->execute();
            } elseif ($role == 'parent') {
                $occupation = sanitizeInput($_POST['occupation'] ?? '');
                $insert = $conn->prepare("INSERT INTO parents (user_id, occupation) VALUES (?, ?)");
                $insert->bind_param("is", $user_id, $occupation);
                $insert->execute();
            }
            
            logActivity($_SESSION['user_id'], 'created new user', 'user', $user_id);
            
            $success = "User created successfully!<br>Password: <strong>$plain_password</strong><br>
                       <small class='text-gray-500'>Please save this password and share with the user.</small>";
            
            // Clear form
            $_POST = [];
        } else {
            $error = "Failed to create user: " . $conn->error;
        }
    }
}

// Get classes for dropdown
$classes = $conn->query("SELECT id, name FROM classes ORDER BY name");
$streams = $conn->query("SELECT id, name, class_id FROM streams ORDER BY name");
?>

<div class="ml-64 mt-16 p-6">
    <div class="max-w-3xl mx-auto">
        <!-- Page Header -->
        <div class="mb-6">
            <h1 class="text-2xl font-bold text-gray-800">Create New User</h1>
            <p class="text-gray-500 mt-1">Add a new user to the system</p>
        </div>

        <!-- Success/Error Messages -->
        <?php if($error): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg mb-4">
                <?php echo $error; ?>
            </div>
        <?php endif; ?>
        
        <?php if($success): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-lg mb-4">
                <?php echo $success; ?>
            </div>
        <?php endif; ?>

        <!-- Create User Form -->
        <div class="bg-white rounded-xl shadow-sm p-6">
            <form method="POST" id="userForm">
                <div class="grid grid-cols-2 gap-4 mb-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">First Name *</label>
                        <input type="text" name="first_name" required 
                               value="<?php echo htmlspecialchars($_POST['first_name'] ?? ''); ?>"
                               class="w-full border rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Last Name *</label>
                        <input type="text" name="last_name" required 
                               value="<?php echo htmlspecialchars($_POST['last_name'] ?? ''); ?>"
                               class="w-full border rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                </div>

                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Email *</label>
                    <input type="email" name="email" required 
                           value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>"
                           class="w-full border rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>

                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Phone</label>
                    <input type="text" name="phone" 
                           value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>"
                           class="w-full border rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>

                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Role *</label>
                    <select name="role" id="role" required class="w-full border rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <option value="">Select Role</option>
                        <option value="admin" <?php echo (($_POST['role'] ?? '') == 'admin') ? 'selected' : ''; ?>>Administrator</option>
                        <option value="academic" <?php echo (($_POST['role'] ?? '') == 'academic') ? 'selected' : ''; ?>>Academic Office</option>
                        <option value="teacher" <?php echo (($_POST['role'] ?? '') == 'teacher') ? 'selected' : ''; ?>>Teacher</option>
                        <option value="student" <?php echo (($_POST['role'] ?? '') == 'student') ? 'selected' : ''; ?>>Student</option>
                        <option value="parent" <?php echo (($_POST['role'] ?? '') == 'parent') ? 'selected' : ''; ?>>Parent</option>
                    </select>
                </div>

                <!-- Student-specific fields -->
                <div id="studentFields" class="hidden">
                    <div class="grid grid-cols-2 gap-4 mb-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Class</label>
                            <select name="class_id" class="w-full border rounded-lg px-3 py-2">
                                <option value="">Select Class</option>
                                <?php while($class = $classes->fetch_assoc()): ?>
                                    <option value="<?php echo $class['id']; ?>"><?php echo $class['name']; ?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Stream</label>
                            <select name="stream_id" class="w-full border rounded-lg px-3 py-2">
                                <option value="">Select Stream</option>
                                <?php 
                                $streams->data_seek(0);
                                while($stream = $streams->fetch_assoc()): ?>
                                    <option value="<?php echo $stream['id']; ?>" data-class="<?php echo $stream['class_id']; ?>">
                                        <?php echo $stream['name']; ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Teacher-specific fields -->
                <div id="teacherFields" class="hidden">
                    <div class="grid grid-cols-2 gap-4 mb-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Qualification</label>
                            <input type="text" name="qualification" 
                                   class="w-full border rounded-lg px-3 py-2">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Specialization</label>
                            <input type="text" name="specialization" 
                                   class="w-full border rounded-lg px-3 py-2">
                        </div>
                    </div>
                </div>

                <!-- Parent-specific fields -->
                <div id="parentFields" class="hidden">
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Occupation</label>
                        <input type="text" name="occupation" 
                               class="w-full border rounded-lg px-3 py-2">
                    </div>
                </div>

                <div class="flex justify-end space-x-3 mt-6 pt-4 border-t">
                    <a href="index.php" class="px-4 py-2 border rounded-lg hover:bg-gray-50">Cancel</a>
                    <button type="submit" class="px-6 py-2 bg-gradient-to-r from-blue-500 to-purple-600 text-white rounded-lg hover:shadow-lg">
                        <i class="fas fa-save mr-2"></i> Create User
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Show/hide role-specific fields
const roleSelect = document.getElementById('role');
const studentFields = document.getElementById('studentFields');
const teacherFields = document.getElementById('teacherFields');
const parentFields = document.getElementById('parentFields');

function toggleRoleFields() {
    const role = roleSelect.value;
    
    studentFields.classList.add('hidden');
    teacherFields.classList.add('hidden');
    parentFields.classList.add('hidden');
    
    if (role === 'student') {
        studentFields.classList.remove('hidden');
    } else if (role === 'teacher') {
        teacherFields.classList.remove('hidden');
    } else if (role === 'parent') {
        parentFields.classList.remove('hidden');
    }
}

roleSelect.addEventListener('change', toggleRoleFields);
toggleRoleFields();
</script>

<?php include '../../includes/footer.php'; ?>