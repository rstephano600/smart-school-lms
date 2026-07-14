<?php
require_once '../../config.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';
requireRole('student');

$group_id = $_GET['id'] ?? 0;

if (!$group_id) {
    header('Location: index.php');
    exit();
}

// Get student ID
$student_query = $conn->prepare("
    SELECT s.id, s.class_id
    FROM students s
    WHERE s.user_id = ?
");
$student_query->bind_param("i", $_SESSION['user_id']);
$student_query->execute();
$student = $student_query->get_result()->fetch_assoc();

if (!$student) {
    header('Location: index.php');
    exit();
}

$student_id = $student['id'];
$class_id = $student['class_id'];

// Get group details and verify access
$group_query = $conn->prepare("
    SELECT g.*, c.name as class_name,
           CONCAT(u.first_name, ' ', u.last_name) as teacher_name,
           (SELECT COUNT(*) FROM group_members WHERE group_id = g.id) as member_count
    FROM discussion_groups g
    JOIN classes c ON g.class_id = c.id
    JOIN teachers t ON g.teacher_id = t.id
    JOIN users u ON t.user_id = u.id
    WHERE g.id = ? AND g.class_id = ? AND g.is_active = 1
");
$group_query->bind_param("ii", $group_id, $class_id);
$group_query->execute();
$group = $group_query->get_result()->fetch_assoc();

if (!$group) {
    $_SESSION['error'] = "Group not found or not available!";
    header('Location: index.php');
    exit();
}

// Check if student is a member
$member_check = $conn->prepare("SELECT id FROM group_members WHERE group_id = ? AND student_id = ?");
$member_check->bind_param("ii", $group_id, $student_id);
$member_check->execute();
if ($member_check->get_result()->num_rows === 0) {
    $_SESSION['error'] = "You must join this group to view discussions!";
    header('Location: index.php');
    exit();
}

// Get messages
$messages = $conn->prepare("
    SELECT m.*, 
           CONCAT(u.first_name, ' ', u.last_name) as sender_name,
           u.role,
           u.id as sender_user_id
    FROM group_messages m
    JOIN users u ON m.sender_id = u.id
    WHERE m.group_id = ?
    ORDER BY m.created_at ASC
");
$messages->bind_param("i", $group_id);
$messages->execute();
$messages = $messages->get_result();

$page_title = 'Discussion - ' . $group['name'];
include '../../includes/header.php';
include '../../includes/sidebar.php';
include '../../includes/navbar.php';
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
                    <span><i class="fas fa-user-tie mr-1"></i> <?php echo htmlspecialchars($group['teacher_name']); ?></span>
                    <span class="text-gray-300">|</span>
                    <span><i class="fas fa-users mr-1"></i> <?php echo $group['member_count']; ?> members</span>
                    <span class="text-gray-300">|</span>
                    <span class="text-green-600"><i class="fas fa-circle text-xs mr-1"></i> Active</span>
                </p>
            </div>
            <a href="index.php" class="text-blue-600 hover:text-blue-800 flex items-center">
                <i class="fas fa-arrow-left mr-2"></i> Back to Groups
            </a>
        </div>

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

            <!-- Message Input -->
            <div class="p-4 border-t bg-white">
                <form method="POST" action="send-message.php" class="flex gap-3" id="messageForm">
                    <input type="hidden" name="group_id" value="<?php echo $group_id; ?>">
                    <input type="text" name="message" id="messageInput" 
                           placeholder="Type your message..." 
                           class="message-input flex-1" required>
                    <button type="submit" class="send-btn flex items-center gap-2" id="sendBtn">
                        <i class="fas fa-paper-plane"></i>
                        <span class="hidden sm:inline">Send</span>
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
// Auto-scroll to bottom
const chatContainer = document.getElementById('chatContainer');
chatContainer.scrollTop = chatContainer.scrollHeight;

// Track last message ID
let lastMessageId = 0;

// Update last message ID from DOM
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

// Handle form submission via AJAX
document.getElementById('messageForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const form = this;
    const formData = new FormData(form);
    const sendBtn = document.getElementById('sendBtn');
    const messageInput = document.getElementById('messageInput');
    
    sendBtn.disabled = true;
    sendBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Sending...';
    
    fetch(form.action, {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const initial = '<?php echo strtoupper(substr($_SESSION['user_name'] ?? 'S', 0, 1)); ?>';
            
            const messageDiv = document.createElement('div');
            messageDiv.className = 'flex justify-end';
            messageDiv.dataset.messageId = data.message_id || 0;
            messageDiv.innerHTML = `
                <div class="message-bubble message-sent">
                    <div>${data.message}</div>
                    <div class="message-time">${data.time}</div>
                </div>
                <div class="avatar-circle ml-2 mt-1" style="background: #4f46e5;">
                    ${initial}
                </div>
            `;
            chatContainer.appendChild(messageDiv);
            chatContainer.scrollTop = chatContainer.scrollHeight;
            
            messageInput.value = '';
            updateLastMessageId();
        } else {
            alert(data.error || 'Failed to send message');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Failed to send message. Please try again.');
    })
    .finally(() => {
        sendBtn.disabled = false;
        sendBtn.innerHTML = '<i class="fas fa-paper-plane"></i><span class="hidden sm:inline"> Send</span>';
        messageInput.focus();
    });
});

// Stop refresh when leaving page
window.addEventListener('beforeunload', function() {
    clearInterval(refreshInterval);
});
</script>

<?php include '../../includes/footer.php'; ?>