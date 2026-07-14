<?php
require_once '../../config.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';
requireRole('student');

$group_id = intval($_GET['group_id'] ?? 0);
$last_id = intval($_GET['last_id'] ?? 0);

if (!$group_id) {
    exit();
}

// Get student ID
$student_query = $conn->prepare("SELECT id, class_id FROM students WHERE user_id = ?");
$student_query->bind_param("i", $_SESSION['user_id']);
$student_query->execute();
$student = $student_query->get_result()->fetch_assoc();

if (!$student) {
    exit();
}

$student_id = $student['id'];
$class_id = $student['class_id'];

// Verify access
$verify = $conn->prepare("SELECT id FROM discussion_groups WHERE id = ? AND class_id = ? AND is_active = 1");
$verify->bind_param("ii", $group_id, $class_id);
$verify->execute();
if ($verify->get_result()->num_rows === 0) {
    exit();
}

// Check if student is a member
$member_check = $conn->prepare("SELECT id FROM group_members WHERE group_id = ? AND student_id = ?");
$member_check->bind_param("ii", $group_id, $student_id);
$member_check->execute();
if ($member_check->get_result()->num_rows === 0) {
    exit();
}

// Get new messages
$messages = $conn->prepare("
    SELECT m.*, 
           CONCAT(u.first_name, ' ', u.last_name) as sender_name,
           u.role,
           u.id as sender_user_id
    FROM group_messages m
    JOIN users u ON m.sender_id = u.id
    WHERE m.group_id = ? AND m.id > ?
    ORDER BY m.created_at ASC
");
$messages->bind_param("ii", $group_id, $last_id);
$messages->execute();
$result = $messages->get_result();

if ($result->num_rows === 0) {
    exit();
}

while($msg = $result->fetch_assoc()):
    $is_student = $msg['sender_user_id'] == $_SESSION['user_id'];
    $is_teacher = $msg['role'] == 'teacher';
    $avatar_color = '#' . substr(md5($msg['sender_name']), 0, 6);
    $initial = strtoupper(substr($msg['sender_name'], 0, 1));
?>
    <div class="flex <?php echo $is_student ? 'justify-end' : 'justify-start'; ?>" 
         data-message-id="<?php echo $msg['id']; ?>">
        <?php if(!$is_student): ?>
            <div class="avatar-circle mr-2 mt-1" style="background: <?php echo $avatar_color; ?>">
                <?php echo $initial; ?>
            </div>
        <?php endif; ?>
        <div class="message-bubble <?php echo $is_student ? 'message-sent' : 'message-received'; ?>">
            <?php if(!$is_student): ?>
                <div class="sender-name">
                    <?php echo htmlspecialchars($msg['sender_name']); ?>
                    <?php if($is_teacher): ?>
                        <span class="text-xs text-blue-600">(Teacher)</span>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            <div><?php echo nl2br(htmlspecialchars($msg['message'])); ?></div>
            <div class="message-time"><?php echo date('h:i A', strtotime($msg['created_at'])); ?></div>
        </div>
        <?php if($is_student): ?>
            <div class="avatar-circle ml-2 mt-1" style="background: #4f46e5;">
                <?php echo $initial; ?>
            </div>
        <?php endif; ?>
    </div>
<?php endwhile; ?>