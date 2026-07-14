<?php
require_once '../../config.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';
requireRole('teacher');

$group_id = $_GET['id'] ?? 0;

if (!$group_id) {
    header('Location: index.php');
    exit();
}

// Get teacher ID
$teacher_query = $conn->prepare("SELECT id FROM teachers WHERE user_id = ?");
$teacher_query->bind_param("i", $_SESSION['user_id']);
$teacher_query->execute();
$teacher = $teacher_query->get_result()->fetch_assoc();
$teacher_id = $teacher['id'] ?? 0;

// Get group details
$group_query = $conn->prepare("
    SELECT g.*, c.name as class_name
    FROM discussion_groups g
    JOIN classes c ON g.class_id = c.id
    WHERE g.id = ? AND g.teacher_id = ?
");
$group_query->bind_param("ii", $group_id, $teacher_id);
$group_query->execute();
$group = $group_query->get_result()->fetch_assoc();

if (!$group) {
    $_SESSION['error'] = "Group not found or you don't have permission!";
    header('Location: index.php');
    exit();
}

// Handle adding members
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_students'])) {
    $student_ids = $_POST['student_ids'] ?? [];
    $added = 0;
    
    foreach ($student_ids as $student_id) {
        $check = $conn->prepare("SELECT id FROM group_members WHERE group_id = ? AND student_id = ?");
        $check->bind_param("ii", $group_id, $student_id);
        $check->execute();
        if ($check->get_result()->num_rows === 0) {
            $insert = $conn->prepare("INSERT INTO group_members (group_id, student_id) VALUES (?, ?)");
            $insert->bind_param("ii", $group_id, $student_id);
            if ($insert->execute()) {
                $added++;
            }
        }
    }
    
    $_SESSION['success'] = "$added student(s) added to the group!";
    header("Location: members.php?id=$group_id");
    exit();
}

// Handle removing member
if (isset($_GET['remove']) && is_numeric($_GET['remove'])) {
    $student_id = $_GET['remove'];
    $remove = $conn->prepare("DELETE FROM group_members WHERE group_id = ? AND student_id = ?");
    $remove->bind_param("ii", $group_id, $student_id);
    $remove->execute();
    $_SESSION['success'] = "Student removed from group!";
    header("Location: members.php?id=$group_id");
    exit();
}

// Get current members
$members = $conn->prepare("
    SELECT s.id, s.admission_number,
           CONCAT(u.first_name, ' ', u.last_name) as name,
           u.email, u.avatar,
           gm.joined_at
    FROM group_members gm
    JOIN students s ON gm.student_id = s.id
    JOIN users u ON s.user_id = u.id
    WHERE gm.group_id = ?
    ORDER BY gm.joined_at ASC
");
$members->bind_param("i", $group_id);
$members->execute();
$members = $members->get_result();

// Get available students (not in group)
$available = $conn->prepare("
    SELECT s.id, s.admission_number,
           CONCAT(u.first_name, ' ', u.last_name) as name,
           u.email
    FROM students s
    JOIN users u ON s.user_id = u.id
    WHERE s.class_id = ? 
    AND s.id NOT IN (
        SELECT student_id FROM group_members WHERE group_id = ?
    )
    ORDER BY u.first_name ASC
");
$available->bind_param("ii", $group['class_id'], $group_id);
$available->execute();
$available = $available->get_result();

$page_title = 'Members - ' . $group['name'];
include '../../includes/header.php';
include '../../includes/sidebar.php';
include '../../includes/navbar.php';

// Get messages
$success = $_SESSION['success'] ?? '';
$error = $_SESSION['error'] ?? '';
unset($_SESSION['success'], $_SESSION['error']);
?>

<style>
.member-avatar {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 600;
    font-size: 16px;
    color: white;
    flex-shrink: 0;
}
.member-card {
    transition: all 0.2s;
}
.member-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.05);
}
</style>

<div class="ml-64 mt-16 p-6 bg-gray-50 min-h-screen">
    <div class="max-w-5xl mx-auto">
        <!-- Header -->
        <div class="mb-6 flex flex-wrap justify-between items-center">
            <div>
                <h1 class="text-2xl font-bold text-gray-800">👥 Manage Members</h1>
                <p class="text-gray-500 mt-1">
                    <i class="fas fa-users mr-1"></i> 
                    <?php echo htmlspecialchars($group['name']); ?> - 
                    <?php echo htmlspecialchars($group['class_name']); ?>
                </p>
            </div>
            <div class="flex gap-3">
                <a href="view.php?id=<?php echo $group_id; ?>" class="text-blue-600 hover:text-blue-800 flex items-center">
                    <i class="fas fa-comment mr-1"></i> View Discussion
                </a>
                <a href="index.php" class="text-gray-600 hover:text-gray-800 flex items-center">
                    <i class="fas fa-arrow-left mr-1"></i> Back
                </a>
            </div>
        </div>

        <!-- Messages -->
        <?php if($success): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-lg mb-4">
                <i class="fas fa-check-circle mr-2"></i> <?php echo $success; ?>
            </div>
        <?php endif; ?>
        
        <?php if($error): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg mb-4">
                <i class="fas fa-exclamation-circle mr-2"></i> <?php echo $error; ?>
            </div>
        <?php endif; ?>

        <!-- Add Members Form -->
        <?php if($available->num_rows > 0): ?>
            <div class="bg-white rounded-xl shadow-sm p-6 mb-6">
                <h3 class="text-lg font-semibold text-gray-800 mb-4 flex items-center">
                    <i class="fas fa-user-plus mr-2 text-green-600"></i>
                    Add Students to Group
                </h3>
                <form method="POST" onsubmit="return confirm('Add selected students to this group?')">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3 mb-4 max-h-48 overflow-y-auto p-2">
                        <?php while($student = $available->fetch_assoc()): ?>
                            <label class="flex items-center gap-3 p-2 hover:bg-gray-50 rounded-lg cursor-pointer">
                                <input type="checkbox" name="student_ids[]" value="<?php echo $student['id']; ?>" 
                                       class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                <span class="text-sm">
                                    <?php echo htmlspecialchars($student['name']); ?>
                                    <span class="text-gray-400 text-xs block">
                                        <?php echo htmlspecialchars($student['admission_number']); ?>
                                    </span>
                                </span>
                            </label>
                        <?php endwhile; ?>
                    </div>
                    <button type="submit" name="add_students" 
                            class="bg-green-600 text-white px-6 py-2 rounded-lg hover:bg-green-700 transition flex items-center">
                        <i class="fas fa-plus mr-2"></i> Add Selected Students
                    </button>
                </form>
            </div>
        <?php else: ?>
            <div class="bg-gray-100 border border-gray-200 text-gray-600 px-4 py-3 rounded-lg mb-6">
                <i class="fas fa-info-circle mr-2"></i> 
                All students in this class are already members of this group.
            </div>
        <?php endif; ?>

        <!-- Current Members -->
        <div class="bg-white rounded-xl shadow-sm p-6">
            <h3 class="text-lg font-semibold text-gray-800 mb-4 flex items-center">
                <i class="fas fa-users mr-2 text-blue-600"></i>
                Current Members (<?php echo $members->num_rows; ?>)
            </h3>
            
            <?php if($members->num_rows > 0): ?>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <?php while($member = $members->fetch_assoc()):
                        $avatar_color = '#' . substr(md5($member['name']), 0, 6);
                        $initial = strtoupper(substr($member['name'], 0, 1));
                        $joined = date('M d, Y', strtotime($member['joined_at']));
                    ?>
                        <div class="member-card bg-gray-50 rounded-lg p-4 flex justify-between items-center">
                            <div class="flex items-center gap-3">
                                <div class="member-avatar" style="background: <?php echo $avatar_color; ?>">
                                    <?php echo $initial; ?>
                                </div>
                                <div>
                                    <p class="font-medium text-gray-800"><?php echo htmlspecialchars($member['name']); ?></p>
                                    <p class="text-xs text-gray-500">
                                        <i class="fas fa-id-card mr-1"></i> <?php echo htmlspecialchars($member['admission_number']); ?>
                                        <span class="mx-1">•</span>
                                        <i class="fas fa-calendar mr-1"></i> Joined: <?php echo $joined; ?>
                                    </p>
                                </div>
                            </div>
                            <a href="?remove=<?php echo $member['id']; ?>&id=<?php echo $group_id; ?>" 
                               onclick="return confirm('Remove this student from the group?')"
                               class="text-red-500 hover:text-red-700">
                                <i class="fas fa-user-minus"></i>
                            </a>
                        </div>
                    <?php endwhile; ?>
                </div>
            <?php else: ?>
                <div class="text-center py-8 text-gray-400">
                    <i class="fas fa-users text-4xl mb-3 block"></i>
                    <p class="text-lg">No members yet</p>
                    <p class="text-sm">Add students using the form above</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>