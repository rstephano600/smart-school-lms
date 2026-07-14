<?php
require_once '../../config.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';
requireRole('admin');

$page_title = 'User Management';
include '../../includes/header.php';
include '../../includes/sidebar.php';
include '../../includes/navbar.php';

// ============================================
// HANDLE ACTIONS
// ============================================

// Handle user deletion (soft delete - deactivate)
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $user_id = intval($_GET['delete']);
    
    // Don't allow deleting own account
    if ($user_id == $_SESSION['user_id']) {
        echo '<script>showToast("You cannot deactivate your own account", "error");</script>';
    } else {
        $query = "UPDATE users SET is_active = 0 WHERE id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $user_id);
        if ($stmt->execute()) {
            logActivity($_SESSION['user_id'], 'deactivated user', 'user', $user_id);
            echo '<script>showToast("User deactivated successfully", "success");</script>';
        }
    }
}

// Handle user restore (activate)
if (isset($_GET['restore']) && is_numeric($_GET['restore'])) {
    $user_id = intval($_GET['restore']);
    $query = "UPDATE users SET is_active = 1 WHERE id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $user_id);
    if ($stmt->execute()) {
        logActivity($_SESSION['user_id'], 'activated user', 'user', $user_id);
        echo '<script>showToast("User activated successfully", "success");</script>';
    }
}

// Handle permanent delete
if (isset($_GET['permanent_delete']) && is_numeric($_GET['permanent_delete'])) {
    $user_id = intval($_GET['permanent_delete']);
    
    // Don't allow deleting own account
    if ($user_id == $_SESSION['user_id']) {
        echo '<script>showToast("You cannot delete your own account", "error");</script>';
    } else {
        // Get user role for confirmation
        $user_query = $conn->prepare("SELECT role, first_name, last_name FROM users WHERE id = ?");
        $user_query->bind_param("i", $user_id);
        $user_query->execute();
        $user = $user_query->get_result()->fetch_assoc();
        
        if ($user) {
            // Start transaction
            $conn->begin_transaction();
            
            try {
                // Delete notifications
                $conn->query("DELETE FROM notifications WHERE user_id = $user_id");
                
                // Delete messages
                $conn->query("DELETE FROM messages WHERE sender_id = $user_id OR receiver_id = $user_id");
                
                // Delete activity logs
                $conn->query("DELETE FROM activity_logs WHERE user_id = $user_id");
                
                // Delete role-specific data
                if ($user['role'] == 'student') {
                    $student = $conn->query("SELECT id FROM students WHERE user_id = $user_id")->fetch_assoc();
                    if ($student) {
                        $conn->query("DELETE FROM submissions WHERE student_id = " . $student['id']);
                        $conn->query("DELETE FROM exam_submissions WHERE student_id = " . $student['id']);
                        $conn->query("DELETE FROM attendance WHERE student_id = " . $student['id']);
                        $conn->query("DELETE FROM student_exam_answers WHERE student_id = " . $student['id']);
                        $conn->query("DELETE FROM student_exam_sessions WHERE student_id = " . $student['id']);
                        $conn->query("DELETE FROM student_bookmarks WHERE student_id = " . $student['id']);
                        $conn->query("DELETE FROM student_achievements WHERE student_id = " . $student['id']);
                        $conn->query("DELETE FROM learning_progress WHERE student_id = " . $student['id']);
                        $conn->query("DELETE FROM study_notes WHERE student_id = " . $student['id']);
                        $conn->query("DELETE FROM code_submissions WHERE student_id = " . $student['id']);
                        $conn->query("DELETE FROM exercise_results WHERE student_id = " . $student['id']);
                        $conn->query("DELETE FROM marks WHERE student_id = " . $student['id']);
                        $conn->query("DELETE FROM marks_summary WHERE student_id = " . $student['id']);
                        $conn->query("DELETE FROM exam_results WHERE student_id = " . $student['id']);
                        $conn->query("DELETE FROM report_cards WHERE student_id = " . $student['id']);
                        $conn->query("DELETE FROM parent_student WHERE student_id = " . $student['id']);
                        $conn->query("DELETE FROM students WHERE id = " . $student['id']);
                    }
                } elseif ($user['role'] == 'teacher') {
                    $teacher = $conn->query("SELECT id FROM teachers WHERE user_id = $user_id")->fetch_assoc();
                    if ($teacher) {
                        $conn->query("UPDATE classes SET class_teacher_id = NULL WHERE class_teacher_id = " . $teacher['id']);
                        $conn->query("DELETE FROM class_subject WHERE teacher_id = " . $teacher['id']);
                        $conn->query("DELETE FROM timetable_entries WHERE teacher_id = " . $teacher['id']);
                        $conn->query("DELETE FROM assignments WHERE created_by = $user_id");
                        $conn->query("DELETE FROM learning_materials WHERE uploaded_by = $user_id");
                        $conn->query("DELETE FROM question_bank WHERE teacher_id = " . $teacher['id']);
                        $conn->query("DELETE FROM teacher_exams WHERE teacher_id = " . $teacher['id']);
                        $conn->query("DELETE FROM syllabus_progress WHERE teacher_id = " . $teacher['id']);
                        $conn->query("DELETE FROM teachers WHERE id = " . $teacher['id']);
                    }
                } elseif ($user['role'] == 'parent') {
                    $parent = $conn->query("SELECT id FROM parents WHERE user_id = $user_id")->fetch_assoc();
                    if ($parent) {
                        $conn->query("DELETE FROM parent_student WHERE parent_id = " . $parent['id']);
                        $conn->query("DELETE FROM parents WHERE id = " . $parent['id']);
                    }
                }
                
                // Delete user
                $conn->query("DELETE FROM users WHERE id = $user_id");
                
                // Delete user_online_status
                $conn->query("DELETE FROM user_online_status WHERE user_id = $user_id");
                
                $conn->commit();
                
                logActivity($_SESSION['user_id'], 'permanently deleted user', 'user', $user_id);
                echo '<script>showToast("User permanently deleted successfully", "success");</script>';
            } catch (Exception $e) {
                $conn->rollback();
                echo '<script>showToast("Failed to delete user: ' . addslashes($e->getMessage()) . '", "error");</script>';
            }
        }
    }
}

// ============================================
// GET FILTERS
// ============================================
$role_filter = $_GET['role'] ?? '';
$status_filter = $_GET['status'] ?? '';
$search = $_GET['search'] ?? '';

// Build query
$query = "SELECT * FROM users WHERE 1=1";
$params = [];
$types = "";

if (!empty($role_filter)) {
    $query .= " AND role = ?";
    $params[] = $role_filter;
    $types .= "s";
}

if ($status_filter === 'active') {
    $query .= " AND is_active = 1";
} elseif ($status_filter === 'inactive') {
    $query .= " AND is_active = 0";
}

if (!empty($search)) {
    $query .= " AND (first_name LIKE ? OR last_name LIKE ? OR email LIKE ?)";
    $search_param = "%{$search}%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= "sss";
}

$query .= " ORDER BY created_at DESC";

// Execute query
$stmt = $conn->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$users = $stmt->get_result();
?>

<div class="ml-64 mt-16 p-6">
    <!-- Page Header -->
    <div class="flex justify-between items-center mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-800">User Management</h1>
            <p class="text-gray-500 mt-1">Manage all users in the system</p>
        </div>
        <div class="flex space-x-2">
            <a href="create.php" class="bg-gradient-to-r from-blue-500 to-purple-600 text-white px-6 py-2 rounded-lg hover:shadow-lg transition-all duration-300">
                <i class="fas fa-plus mr-2"></i> Add New User
            </a>
        </div>
    </div>

    <!-- Success/Error Messages -->
    <?php if(isset($_GET['deleted'])): ?>
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-lg mb-4">
            <i class="fas fa-check-circle mr-2"></i> User deleted successfully!
        </div>
    <?php endif; ?>
    
    <?php if(isset($_GET['suspended'])): ?>
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-lg mb-4">
            <i class="fas fa-check-circle mr-2"></i> User suspended successfully!
        </div>
    <?php endif; ?>
    
    <?php if(isset($_GET['activated'])): ?>
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-lg mb-4">
            <i class="fas fa-check-circle mr-2"></i> User activated successfully!
        </div>
    <?php endif; ?>

    <!-- Filters -->
    <div class="bg-white rounded-xl shadow-sm p-4 mb-6">
        <form method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Search</label>
                <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" 
                       placeholder="Name or email..." 
                       class="w-full border rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Role</label>
                <select name="role" class="w-full border rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <option value="">All Roles</option>
                    <option value="admin" <?php echo $role_filter == 'admin' ? 'selected' : ''; ?>>Admin</option>
                    <option value="academic" <?php echo $role_filter == 'academic' ? 'selected' : ''; ?>>Academic Office</option>
                    <option value="teacher" <?php echo $role_filter == 'teacher' ? 'selected' : ''; ?>>Teacher</option>
                    <option value="student" <?php echo $role_filter == 'student' ? 'selected' : ''; ?>>Student</option>
                    <option value="parent" <?php echo $role_filter == 'parent' ? 'selected' : ''; ?>>Parent</option>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                <select name="status" class="w-full border rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <option value="">All Status</option>
                    <option value="active" <?php echo $status_filter == 'active' ? 'selected' : ''; ?>>Active</option>
                    <option value="inactive" <?php echo $status_filter == 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                </select>
            </div>
            <div class="flex items-end">
                <button type="submit" class="w-full bg-gray-600 text-white px-4 py-2 rounded-lg hover:bg-gray-700">
                    <i class="fas fa-search mr-2"></i> Filter
                </button>
            </div>
        </form>
    </div>

    <!-- Users Table -->
    <div class="bg-white rounded-xl shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-gray-50 border-b">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">User</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Email</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Role</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Last Login</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    <?php if ($users && $users->num_rows > 0): ?>
                        <?php while($user = $users->fetch_assoc()): ?>
                        <tr class="hover:bg-gray-50 transition-all duration-200">
                            <td class="px-6 py-4">
                                <div class="flex items-center space-x-3">
                                    <div class="w-10 h-10 bg-gradient-to-r from-blue-500 to-purple-600 rounded-full flex items-center justify-center text-white font-semibold">
                                        <?php echo strtoupper(substr($user['first_name'], 0, 1) . substr($user['last_name'], 0, 1)); ?>
                                    </div>
                                    <div>
                                        <p class="font-medium text-gray-800"><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></p>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4 text-gray-600"><?php echo htmlspecialchars($user['email']); ?></td>
                            <td class="px-6 py-4">
                                <span class="px-2 py-1 text-xs rounded-full 
                                    <?php echo $user['role'] == 'admin' ? 'bg-red-100 text-red-700' : 
                                             ($user['role'] == 'teacher' ? 'bg-green-100 text-green-700' :
                                             ($user['role'] == 'student' ? 'bg-blue-100 text-blue-700' : 
                                             ($user['role'] == 'academic' ? 'bg-orange-100 text-orange-700' : 'bg-purple-100 text-purple-700'))); ?>">
                                    <?php echo ucfirst($user['role']); ?>
                                </span>
                            </td>
                            <td class="px-6 py-4">
                                <span class="px-2 py-1 text-xs rounded-full <?php echo $user['is_active'] ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'; ?>">
                                    <?php echo $user['is_active'] ? 'Active' : 'Inactive'; ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 text-gray-500 text-sm">
                                <?php echo $user['last_login'] ? date('M d, Y H:i', strtotime($user['last_login'])) : 'Never'; ?>
                            </td>
                            <td class="px-6 py-4">
                                <div class="flex space-x-2">
                                    <!-- Edit -->
                                    <a href="edit.php?id=<?php echo $user['id']; ?>" 
                                       class="text-blue-600 hover:text-blue-800" title="Edit User">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    
                                    <?php if($user['id'] != $_SESSION['user_id']): ?>
                                        
                                        <!-- Deactivate / Activate -->
                                        <?php if($user['is_active']): ?>
                                            <a href="?delete=<?php echo $user['id']; ?>" 
                                               onclick="return confirm('Deactivate this user? They will not be able to login.')"
                                               class="text-yellow-600 hover:text-yellow-800" title="Deactivate User">
                                                <i class="fas fa-user-slash"></i>
                                            </a>
                                        <?php else: ?>
                                            <a href="?restore=<?php echo $user['id']; ?>" 
                                               class="text-green-600 hover:text-green-800" title="Activate User">
                                                <i class="fas fa-user-check"></i>
                                            </a>
                                        <?php endif; ?>
                                        
                                        <!-- Permanent Delete -->
                                        <a href="?permanent_delete=<?php echo $user['id']; ?>" 
                                           onclick="return confirm('⚠️ PERMANENT DELETE: This will remove ALL data for this user including submissions, messages, and activity logs. This action CANNOT be undone! Continue?')"
                                           class="text-red-600 hover:text-red-800" title="Delete Permanently">
                                            <i class="fas fa-trash-alt"></i>
                                        </a>
                                        
                                    <?php endif; ?>
                                    
                                    <!-- Reset Password -->
                                    <a href="reset-password.php?id=<?php echo $user['id']; ?>" 
                                       class="text-purple-600 hover:text-purple-800" title="Reset Password">
                                        <i class="fas fa-key"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" class="px-6 py-12 text-center text-gray-500">
                                <i class="fas fa-users text-4xl mb-2 block"></i>
                                No users found
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
// Toast notification function
function showToast(message, type = 'success') {
    const toast = document.createElement('div');
    const colors = {
        success: 'bg-green-500',
        error: 'bg-red-500',
        warning: 'bg-yellow-500',
        info: 'bg-blue-500'
    };
    toast.className = `fixed bottom-4 right-4 ${colors[type] || 'bg-gray-700'} text-white px-6 py-3 rounded-lg shadow-lg z-50 transition-all duration-300`;
    toast.innerHTML = message;
    document.body.appendChild(toast);
    setTimeout(() => {
        toast.style.opacity = '0';
        setTimeout(() => toast.remove(), 300);
    }, 3000);
}
</script>

<?php include '../../includes/footer.php'; ?>