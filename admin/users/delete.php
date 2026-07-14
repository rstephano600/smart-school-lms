<?php
require_once '../../config.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';
requireRole('admin');

$user_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if (!$user_id) {
    header('Location: index.php?error=No user specified');
    exit();
}

// Prevent deleting own account
if ($user_id == $_SESSION['user_id']) {
    header('Location: index.php?error=You cannot delete your own account');
    exit();
}

// Get user details
$user_query = $conn->prepare("SELECT id, email, first_name, last_name, role FROM users WHERE id = ?");
$user_query->bind_param("i", $user_id);
$user_query->execute();
$user = $user_query->get_result()->fetch_assoc();

if (!$user) {
    header('Location: index.php?error=User not found');
    exit();
}

// Get role-based data
$role_data = [];
if ($user['role'] == 'student') {
    $student = $conn->query("SELECT * FROM students WHERE user_id = $user_id")->fetch_assoc();
    if ($student) {
        $role_data['admission_number'] = $student['admission_number'];
        $role_data['class_id'] = $student['class_id'];
        $role_data['submissions'] = $conn->query("SELECT COUNT(*) as count FROM submissions WHERE student_id = " . $student['id'])->fetch_assoc()['count'] ?? 0;
        $role_data['exam_submissions'] = $conn->query("SELECT COUNT(*) as count FROM exam_submissions WHERE student_id = " . $student['id'])->fetch_assoc()['count'] ?? 0;
        $role_data['attendance'] = $conn->query("SELECT COUNT(*) as count FROM attendance WHERE student_id = " . $student['id'])->fetch_assoc()['count'] ?? 0;
    }
} elseif ($user['role'] == 'teacher') {
    $teacher = $conn->query("SELECT * FROM teachers WHERE user_id = $user_id")->fetch_assoc();
    if ($teacher) {
        $role_data['employee_number'] = $teacher['employee_number'];
        $role_data['class_subjects'] = $conn->query("SELECT COUNT(*) as count FROM class_subject WHERE teacher_id = " . $teacher['id'])->fetch_assoc()['count'] ?? 0;
        $role_data['timetable'] = $conn->query("SELECT COUNT(*) as count FROM timetable_entries WHERE teacher_id = " . $teacher['id'])->fetch_assoc()['count'] ?? 0;
        $role_data['assignments'] = $conn->query("SELECT COUNT(*) as count FROM assignments WHERE created_by = $user_id")->fetch_assoc()['count'] ?? 0;
    }
} elseif ($user['role'] == 'parent') {
    $parent = $conn->query("SELECT * FROM parents WHERE user_id = $user_id")->fetch_assoc();
    if ($parent) {
        $role_data['children'] = $conn->query("SELECT COUNT(*) as count FROM parent_student WHERE parent_id = " . $parent['id'])->fetch_assoc()['count'] ?? 0;
    }
}

// Get activity count
$activity_count = $conn->query("SELECT COUNT(*) as count FROM activity_logs WHERE user_id = $user_id")->fetch_assoc()['count'] ?? 0;
$notification_count = $conn->query("SELECT COUNT(*) as count FROM notifications WHERE user_id = $user_id")->fetch_assoc()['count'] ?? 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm'])) {
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
        
        header('Location: index.php?deleted=1');
        exit();
    } catch (Exception $e) {
        $conn->rollback();
        header('Location: index.php?error=Failed to delete user: ' . $e->getMessage());
        exit();
    }
}

$page_title = 'Delete User - ' . $user['first_name'] . ' ' . $user['last_name'];
include '../../includes/header.php';
include '../../includes/sidebar.php';
include '../../includes/navbar.php';
?>

<div class="ml-64 mt-16 p-6 bg-gray-50 min-h-screen">
    <div class="max-w-2xl mx-auto">
        <div class="mb-6">
            <h1 class="text-2xl font-bold text-gray-800">Delete User Permanently</h1>
            <p class="text-gray-500 mt-1">Are you sure you want to permanently delete this user?</p>
        </div>

        <div class="bg-white rounded-xl shadow-sm overflow-hidden">
            <!-- Warning Banner -->
            <div class="p-4 bg-red-50 border-b border-red-200">
                <div class="flex items-start space-x-3">
                    <div class="w-10 h-10 bg-red-100 rounded-full flex items-center justify-center flex-shrink-0">
                        <i class="fas fa-exclamation-triangle text-red-600 text-xl"></i>
                    </div>
                    <div>
                        <h3 class="font-bold text-red-800">⚠️ DANGER: This action cannot be undone!</h3>
                        <p class="text-sm text-red-700">Deleting this user will permanently remove ALL their data from the system.</p>
                    </div>
                </div>
            </div>

            <!-- User Details -->
            <div class="p-6">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                    <div class="p-4 bg-gray-50 rounded-lg">
                        <p class="text-sm text-gray-500">User</p>
                        <p class="font-semibold text-gray-800"><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></p>
                    </div>
                    <div class="p-4 bg-gray-50 rounded-lg">
                        <p class="text-sm text-gray-500">Email</p>
                        <p class="font-semibold text-gray-800"><?php echo htmlspecialchars($user['email']); ?></p>
                    </div>
                    <div class="p-4 bg-gray-50 rounded-lg">
                        <p class="text-sm text-gray-500">Role</p>
                        <p class="font-semibold text-gray-800 capitalize"><?php echo $user['role']; ?></p>
                    </div>
                    <div class="p-4 bg-gray-50 rounded-lg">
                        <p class="text-sm text-gray-500">User ID</p>
                        <p class="font-semibold text-gray-800">#<?php echo $user['id']; ?></p>
                    </div>
                </div>

                <!-- What will be deleted -->
                <div class="mb-6 p-4 bg-yellow-50 rounded-lg border border-yellow-200">
                    <h4 class="font-semibold text-yellow-800 mb-2">The following will be deleted:</h4>
                    <ul class="text-sm text-yellow-700 space-y-1 list-disc list-inside">
                        <li>User account: <strong><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></strong></li>
                        <?php if($user['role'] == 'student'): ?>
                            <li>Student profile and admission records</li>
                            <li>All submissions: <strong><?php echo $role_data['submissions'] ?? 0; ?></strong></li>
                            <li>All exam submissions: <strong><?php echo $role_data['exam_submissions'] ?? 0; ?></strong></li>
                            <li>Attendance records: <strong><?php echo $role_data['attendance'] ?? 0; ?></strong></li>
                            <li>All marks and results</li>
                            <li>Report cards</li>
                            <li>Parent-student relationships</li>
                            <li>Learning progress</li>
                            <li>Study notes and bookmarks</li>
                            <li>Code submissions</li>
                        <?php elseif($user['role'] == 'teacher'): ?>
                            <li>Teacher profile and employment records</li>
                            <li>Class assignments: <strong><?php echo $role_data['class_subjects'] ?? 0; ?></strong></li>
                            <li>Timetable entries: <strong><?php echo $role_data['timetable'] ?? 0; ?></strong></li>
                            <li>Assignments created: <strong><?php echo $role_data['assignments'] ?? 0; ?></strong></li>
                            <li>Learning materials uploaded</li>
                            <li>Question bank questions</li>
                            <li>Teacher exams</li>
                            <li>Syllabus progress</li>
                        <?php elseif($user['role'] == 'parent'): ?>
                            <li>Parent profile</li>
                            <li>Parent-student relationships: <strong><?php echo $role_data['children'] ?? 0; ?></strong></li>
                        <?php endif; ?>
                        <li>Messages: <strong><?php echo $conn->query("SELECT COUNT(*) as count FROM messages WHERE sender_id = $user_id OR receiver_id = $user_id")->fetch_assoc()['count'] ?? 0; ?></strong></li>
                        <li>Notifications: <strong><?php echo $notification_count; ?></strong></li>
                        <li>Activity logs: <strong><?php echo $activity_count; ?></strong></li>
                    </ul>
                </div>

                <!-- Alternative - Deactivate instead -->
                <div class="mb-6 p-4 bg-blue-50 rounded-lg border border-blue-200">
                    <h4 class="font-semibold text-blue-800 mb-2">💡 Alternative: Deactivate User</h4>
                    <p class="text-sm text-blue-700">Instead of permanently deleting, you can deactivate this user. They will not be able to login but their data will be preserved.</p>
                    <a href="suspend.php?id=<?php echo $user_id; ?>&action=suspend" class="mt-2 inline-block bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 text-sm">
                        <i class="fas fa-user-slash mr-2"></i> Deactivate Instead
                    </a>
                </div>

                <!-- Confirmation Form -->
                <form method="POST">
                    <input type="hidden" name="confirm" value="yes">
                    
                    <div class="flex justify-end space-x-3 pt-4 border-t">
                        <a href="index.php" class="px-4 py-2 border rounded-lg hover:bg-gray-50 transition">
                            <i class="fas fa-arrow-left mr-2"></i> Cancel
                        </a>
                        <button type="submit" class="px-6 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition-all">
                            <i class="fas fa-trash-alt mr-2"></i> Yes, Delete Permanently
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>