<?php
require_once '../../config.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';
requireRole('teacher');

$group_id = intval($_GET['group_id'] ?? 0);
$last_id = intval($_GET['last_id'] ?? 0);

if (!$group_id) {
    exit();
}

// Get teacher ID
$teacher_query = $conn->prepare("SELECT id FROM teachers WHERE user_id = ?");
$teacher_query->bind_param("i", $_SESSION['user_id']);
$teacher_query->execute();
$teacher = $teacher_query->get_result()->fetch_assoc();
$teacher_id = $teacher['id'] ?? 0;

// Verify permission
$verify = $conn->prepare("SELECT id FROM discussion_groups WHERE id = ? AND teacher_id = ?");
$verify->bind_param("ii", $group_id, $teacher_id);
$verify->execute();
if ($verify->get_result()->num_rows === 0) {
    exit();
}

// Get new messages - ONLY messages with ID > last_id
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
    $is_teacher = $msg['sender_user_id'] == $_SESSION['user_id'];
    $avatar_color = '#' . substr(md5($msg['sender_name']), 0, 6);
    $initial = strtoupper(substr($msg['sender_name'], 0, 1));
?>
    <div class="flex <?php echo $is_teacher ? 'justify-end' : 'justify-start'; ?>" 
         data-message-id="<?php echo $msg['id']; ?>">
        <?php if(!$is_teacher): ?>
            <div class="avatar-circle mr-2 mt-1" style="background: <?php echo $avatar_color; ?>">
                <?php echo $initial; ?>
            </div>
        <?php endif; ?>
        <div class="message-bubble <?php echo $is_teacher ? 'message-sent' : 'message-received'; ?>">
            <?php if(!$is_teacher): ?>
                <div class="sender-name"><?php echo htmlspecialchars($msg['sender_name']); ?></div>
            <?php endif; ?>
            <div><?php echo nl2br(htmlspecialchars($msg['message'])); ?></div>
            <div class="message-time"><?php echo date('h:i A', strtotime($msg['created_at'])); ?></div>
        </div>
        <?php if($is_teacher): ?>
            <div class="avatar-circle ml-2 mt-1" style="background: #4f46e5;">
                <?php echo $initial; ?>
            </div>
        <?php endif; ?>
    </div>
<?php endwhile; ?>