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
    SELECT g.*, c.name as class_name,
           (SELECT COUNT(*) FROM group_members WHERE group_id = g.id) as member_count
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

// ============================================
// HANDLE MESSAGE SUBMISSION (NON-AJAX)
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['message'])) {
    $message = trim($_POST['message']);
    $group_id = intval($_POST['group_id']);
    
    if (!empty($message) && $group_id > 0) {
        // Verify group belongs to teacher
        $verify = $conn->prepare("SELECT id, is_active FROM discussion_groups WHERE id = ? AND teacher_id = ?");
        $verify->bind_param("ii", $group_id, $teacher_id);
        $verify->execute();
        $group_check = $verify->get_result()->fetch_assoc();
        
        if ($group_check && $group_check['is_active']) {
            $insert = $conn->prepare("INSERT INTO group_messages (group_id, sender_id, message) VALUES (?, ?, ?)");
            $insert->bind_param("iis", $group_id, $_SESSION['user_id'], $message);
            
            if ($insert->execute()) {
                $_SESSION['success'] = "Message sent successfully!";
            } else {
                $_SESSION['error'] = "Failed to send message: " . $conn->error;
            }
        } else {
            $_SESSION['error'] = "Group is inactive or you don't have permission!";
        }
    } else {
        $_SESSION['error'] = "Message cannot be empty!";
    }
    
    header("Location: view.php?id=" . $group_id);
    exit();
}

// Get messages with user info
$messages = $conn->prepare("
    SELECT m.*, 
           CONCAT(u.first_name, ' ', u.last_name) as sender_name,
           u.role,
           u.id as sender_user_id,
           u.avatar
    FROM group_messages m
    JOIN users u ON m.sender_id = u.id
    WHERE m.group_id = ?
    ORDER BY m.created_at ASC
");
$messages->bind_param("i", $group_id);
$messages->execute();
$messages = $messages->get_result();

// Get members
$members = $conn->prepare("
    SELECT s.id, CONCAT(u.first_name, ' ', u.last_name) as name, u.avatar
    FROM group_members gm
    JOIN students s ON gm.student_id = s.id
    JOIN users u ON s.user_id = u.id
    WHERE gm.group_id = ?
");
$members->bind_param("i", $group_id);
$members->execute();
$members = $members->get_result();

$page_title = 'Discussion - ' . $group['name'];
include '../../includes/header.php';
include '../../includes/sidebar.php';
include '../../includes/navbar.php';

// Get messages for display
$success = $_SESSION['success'] ?? '';
$error = $_SESSION['error'] ?? '';
unset($_SESSION['success'], $_SESSION['error']);

// Get last message ID for AJAX
$last_msg_id = 0;
if ($messages->num_rows > 0) {
    $messages->data_seek($messages->num_rows - 1);
    $last = $messages->fetch_assoc();
    $last_msg_id = $last['id'];
    $messages->data_seek(0);
}
?>

<style>
.chat-container {
    height: 450px;
    overflow-y: auto;
    padding: 16px;
    background: #f8fafc;
    border-radius: 12px;
}
.chat-container::-webkit-scrollbar {
    width: 6px;
}
.chat-container::-webkit-scrollbar-track {
    background: #e5e7eb;
    border-radius: 3px;
}
.chat-container::-webkit-scrollbar-thumb {
    background: #9ca3af;
    border-radius: 3px;
}
.message-bubble {
    max-width: 75%;
    padding: 10px 16px;
    border-radius: 16px;
    margin-bottom: 12px;
    word-wrap: break-word;
}
.message-sent {
    background: linear-gradient(135deg, #3b82f6, #6366f1);
    color: white;
    margin-left: auto;
    border-bottom-right-radius: 4px;
}
.message-received {
    background: white;
    color: #1f2937;
    border: 1px solid #e5e7eb;
    border-bottom-left-radius: 4px;
}
.message-received .sender-name {
    color: #4f46e5;
    font-weight: 600;
    font-size: 0.75rem;
    margin-bottom: 2px;
}
.message-time {
    font-size: 0.65rem;
    opacity: 0.7;
    margin-top: 4px;
}
.chat-header {
    background: linear-gradient(135deg, #3b82f6, #6366f1);
    color: white;
}
.message-input {
    border-radius: 9999px;
    padding: 0.75rem 1.5rem;
    border: 2px solid #e5e7eb;
    transition: all 0.2s;
    width: 100%;
}
.message-input:focus {
    outline: none;
    border-color: #6366f1;
    box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
}
.send-btn {
    border-radius: 9999px;
    padding: 0.75rem 1.5rem;
    background: linear-gradient(135deg, #3b82f6, #6366f1);
    color: white;
    border: none;
    transition: all 0.2s;
    white-space: nowrap;
}
.send-btn:hover {
    transform: scale(1.02);
    box-shadow: 0 4px 12px rgba(99, 102, 241, 0.4);
}
.send-btn:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}
.avatar-circle {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 600;
    font-size: 14px;
    color: white;
    flex-shrink: 0;
}
</style>

<div class="ml-64 mt-16 p-6 bg-gray-50 min-h-screen">
    <div class="max-w-5xl mx-auto">
        <!-- Header -->
        <div class="mb-6 flex flex-wrap justify-between items-center">
            <div>
                <h1 class="text-2xl font-bold text-gray-800">💬 <?php echo htmlspecialchars($group['name']); ?></h1>
                <p class="text-gray-500 mt-1 flex items-center gap-3">
                    <span><i class="fas fa-graduation-cap mr-1"></i> <?php echo htmlspecialchars($group['class_name']); ?></span>
                    <span class="text-gray-300">|</span>
                    <span><i class="fas fa-users mr-1"></i> <?php echo $group['member_count']; ?> members</span>
                    <span class="text-gray-300">|</span>
                    <span class="<?php echo $group['is_active'] ? 'text-green-600' : 'text-red-600'; ?>">
                        <i class="fas fa-circle text-xs mr-1"></i> <?php echo $group['is_active'] ? 'Active' : 'Inactive'; ?>
                    </span>
                </p>
            </div>
            <a href="index.php" class="text-blue-600 hover:text-blue-800 flex items-center">
                <i class="fas fa-arrow-left mr-2"></i> Back to Groups
            </a>
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

        <!-- Description -->
        <?php if($group['description']): ?>
            <div class="bg-blue-50 border border-blue-200 rounded-lg px-4 py-3 mb-4 text-blue-800 text-sm">
                <i class="fas fa-info-circle mr-2"></i> <?php echo htmlspecialchars($group['description']); ?>
            </div>
        <?php endif; ?>

        <!-- Chat Area -->
        <div class="bg-white rounded-xl shadow-sm overflow-hidden">
            <!-- Chat Header -->
            <div class="chat-header px-6 py-3 flex justify-between items-center">
                <div class="flex items-center gap-2">
                    <i class="fas fa-comments text-lg"></i>
                    <span class="font-semibold">Discussion</span>
                </div>
                <div class="flex items-center gap-4 text-sm">
                    <span class="text-blue-100">
                        <i class="fas fa-comment mr-1"></i> <?php echo $messages->num_rows; ?> messages
                    </span>
                </div>
            </div>
            
            <!-- Messages -->
            <div class="chat-container" id="chatContainer">
                <?php if($messages->num_rows > 0): 
                    while($msg = $messages->fetch_assoc()): 
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
                <?php endwhile; 
                else: ?>
                    <div class="flex items-center justify-center h-full">
                        <div class="text-center text-gray-400">
                            <i class="fas fa-comment-dots text-5xl mb-3 block"></i>
                            <p class="text-lg font-medium">No messages yet</p>
                            <p class="text-sm">Start the discussion by sending a message below!</p>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Message Input - USING STANDARD FORM (NOT AJAX) -->
            <div class="p-4 border-t bg-white">
                <form method="POST" action="view.php?id=<?php echo $group_id; ?>" class="flex gap-3" id="messageForm">
                    <input type="hidden" name="group_id" value="<?php echo $group_id; ?>">
                    <input type="text" name="message" id="messageInput" 
                           placeholder="Type your message..." 
                           class="message-input"
                           <?php echo !$group['is_active'] ? 'disabled' : ''; ?>
                           required>
                    <button type="submit" class="send-btn flex items-center gap-2" 
                            id="sendBtn"
                            <?php echo !$group['is_active'] ? 'disabled' : ''; ?>>
                        <i class="fas fa-paper-plane"></i>
                        <span>Send</span>
                    </button>
                </form>
                <?php if(!$group['is_active']): ?>
                    <p class="text-sm text-red-500 mt-2">
                        <i class="fas fa-exclamation-triangle mr-1"></i> 
                        This group is currently inactive. Students cannot send messages.
                    </p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
// Auto-scroll to bottom
const chatContainer = document.getElementById('chatContainer');
chatContainer.scrollTop = chatContainer.scrollHeight;

// Track last message ID
let lastMessageId = <?php echo $last_msg_id; ?>;

// Function to update last message ID from DOM
function updateLastMessageId() {
    const messages = chatContainer.querySelectorAll('[data-message-id]');
    if (messages.length > 0) {
        const lastMsg = messages[messages.length - 1];
        const id = parseInt(lastMsg.dataset.messageId) || 0;
        if (id > lastMessageId) {
            lastMessageId = id;
        }
    }
}

// Auto-refresh every 5 seconds
let refreshInterval = setInterval(function() {
    if (document.hidden) return;
    
    fetch('get-messages.php?group_id=<?php echo $group_id; ?>&last_id=' + lastMessageId)
        .then(response => response.text())
        .then(html => {
            if (html.trim()) {
                chatContainer.insertAdjacentHTML('beforeend', html);
                chatContainer.scrollTop = chatContainer.scrollHeight;
                updateLastMessageId();
            }
        })
        .catch(error => console.error('Error fetching messages:', error));
}, 5000);

// Update on page load
updateLastMessageId();

// Stop refresh when leaving page
window.addEventListener('beforeunload', function() {
    clearInterval(refreshInterval);
});
</script>

<?php include '../../includes/footer.php'; ?>