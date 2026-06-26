<?php
require_once '../../config.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';
requireRole('student');

$page_title = 'Messages';
include '../../includes/header.php';
include '../../includes/sidebar.php';
include '../../includes/navbar.php';

// Get student ID
$student_query = $conn->prepare("
    SELECT s.id, CONCAT(u.first_name, ' ', u.last_name) as student_name
    FROM students s
    JOIN users u ON s.user_id = u.id
    WHERE u.id = ?
");
$student_query->bind_param("i", $_SESSION['user_id']);
$student_query->execute();
$student = $student_query->get_result()->fetch_assoc();
$student_id = $student['id'];

// Get all teachers for this student's class
$teachers = $conn->prepare("
    SELECT DISTINCT u.id, u.first_name, u.last_name, 'teacher' as role,
           (SELECT message FROM messages WHERE 
               (sender_id = ? AND receiver_id = u.id) OR 
               (sender_id = u.id AND receiver_id = ?) 
            ORDER BY created_at DESC LIMIT 1) as last_message,
           (SELECT created_at FROM messages WHERE 
               (sender_id = ? AND receiver_id = u.id) OR 
               (sender_id = u.id AND receiver_id = ?) 
            ORDER BY created_at DESC LIMIT 1) as last_time,
           (SELECT COUNT(*) FROM messages WHERE receiver_id = ? AND sender_id = u.id AND is_read = 0) as unread_count
    FROM class_subject cs
    JOIN teachers t ON cs.teacher_id = t.id
    JOIN users u ON t.user_id = u.id
    WHERE cs.class_id = (SELECT class_id FROM students WHERE id = ?)
    ORDER BY last_time DESC
");
$teachers->bind_param("iiiiii", $_SESSION['user_id'], $_SESSION['user_id'], $_SESSION['user_id'], $_SESSION['user_id'], $_SESSION['user_id'], $student_id);
$teachers->execute();
$teachers = $teachers->get_result();

// Get selected user
$selected_user = isset($_GET['user']) ? intval($_GET['user']) : 0;
$chat_user = null;
$messages = [];

if ($selected_user > 0) {
    // Get user info
    $user_query = $conn->prepare("SELECT id, first_name, last_name, role FROM users WHERE id = ?");
    $user_query->bind_param("i", $selected_user);
    $user_query->execute();
    $chat_user = $user_query->get_result()->fetch_assoc();
    
    // Mark messages as read
    $mark_read = $conn->prepare("UPDATE messages SET is_read = 1, read_at = NOW() WHERE sender_id = ? AND receiver_id = ? AND is_read = 0");
    $mark_read->bind_param("ii", $selected_user, $_SESSION['user_id']);
    $mark_read->execute();
    
    // Get messages between student and teacher
    $messages_query = $conn->prepare("
        SELECT * FROM messages 
        WHERE (sender_id = ? AND receiver_id = ?) OR (sender_id = ? AND receiver_id = ?)
        ORDER BY created_at ASC
    ");
    $messages_query->bind_param("iiii", $_SESSION['user_id'], $selected_user, $selected_user, $_SESSION['user_id']);
    $messages_query->execute();
    $messages = $messages_query->get_result();
}
?>

<div class="ml-64 mt-16 p-6">
    <div class="max-w-6xl mx-auto">
        <div class="mb-6">
            <h1 class="text-2xl font-bold text-gray-800">Messages</h1>
            <p class="text-gray-500 mt-1">Chat with your teachers</p>
        </div>

        <div class="bg-white rounded-xl shadow-sm overflow-hidden">
            <div class="grid grid-cols-1 md:grid-cols-3 h-[600px]">
                <!-- Contacts Sidebar -->
                <div class="border-r border-gray-200">
                    <div class="p-4 border-b bg-gray-50">
                        <h3 class="font-semibold">Teachers</h3>
                    </div>
                    <div class="overflow-y-auto h-[540px]">
                        <?php if ($teachers && $teachers->num_rows > 0): ?>
                            <?php while($teacher = $teachers->fetch_assoc()): ?>
                                <a href="?user=<?php echo $teacher['id']; ?>" 
                                   class="block p-3 hover:bg-gray-50 border-b transition-all <?php echo $selected_user == $teacher['id'] ? 'bg-blue-50 border-l-4 border-l-blue-500' : ''; ?>">
                                    <div class="flex items-center space-x-3">
                                        <div class="w-10 h-10 bg-gradient-to-r from-blue-500 to-purple-600 rounded-full flex items-center justify-center text-white font-bold">
                                            <?php echo strtoupper(substr($teacher['first_name'], 0, 1) . substr($teacher['last_name'], 0, 1)); ?>
                                        </div>
                                        <div class="flex-1 min-w-0">
                                            <div class="flex justify-between items-center">
                                                <p class="font-medium text-gray-800 truncate">
                                                    <?php echo htmlspecialchars($teacher['first_name'] . ' ' . $teacher['last_name']); ?>
                                                </p>
                                                <?php if($teacher['unread_count'] > 0): ?>
                                                    <span class="bg-red-500 text-white text-xs rounded-full px-2 py-0.5"><?php echo $teacher['unread_count']; ?></span>
                                                <?php endif; ?>
                                            </div>
                                            <p class="text-xs text-gray-500">Teacher</p>
                                            <?php if($teacher['last_message']): ?>
                                                <p class="text-xs text-gray-400 truncate"><?php echo htmlspecialchars(substr($teacher['last_message'], 0, 40)); ?></p>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </a>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <div class="p-8 text-center text-gray-500">
                                <i class="fas fa-chalkboard-user text-4xl mb-2 block"></i>
                                <p>No teachers available</p>
                                <p class="text-xs mt-1">Teachers will appear here once assigned</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Chat Area -->
                <div class="md:col-span-2 flex flex-col h-[600px]">
                    <?php if ($selected_user > 0 && $chat_user): ?>
                        <!-- Chat Header -->
                        <div class="p-4 border-b bg-gray-50 flex items-center justify-between">
                            <div class="flex items-center space-x-3">
                                <div class="w-10 h-10 bg-gradient-to-r from-blue-500 to-purple-600 rounded-full flex items-center justify-center text-white font-bold">
                                    <?php echo strtoupper(substr($chat_user['first_name'], 0, 1) . substr($chat_user['last_name'], 0, 1)); ?>
                                </div>
                                <div>
                                    <p class="font-semibold"><?php echo htmlspecialchars($chat_user['first_name'] . ' ' . $chat_user['last_name']); ?></p>
                                    <p class="text-xs text-gray-500"><?php echo ucfirst($chat_user['role']); ?></p>
                                </div>
                            </div>
                            <div class="text-xs text-gray-400">
                                <i class="fas fa-lock mr-1"></i> Encrypted
                            </div>
                        </div>

                        <!-- Messages Area -->
                        <div class="flex-1 overflow-y-auto p-4 space-y-3 bg-gray-50" id="messagesArea">
                            <?php if ($messages && $messages->num_rows > 0): ?>
                                <?php while($msg = $messages->fetch_assoc()): 
                                    $is_me = $msg['sender_id'] == $_SESSION['user_id'];
                                ?>
                                    <div class="flex <?php echo $is_me ? 'justify-end' : 'justify-start'; ?>">
                                        <div class="max-w-[70%] <?php echo $is_me ? 'bg-blue-500 text-white' : 'bg-white text-gray-800 shadow-sm'; ?> rounded-lg px-4 py-2">
                                            <p class="text-sm"><?php echo nl2br(htmlspecialchars($msg['message'])); ?></p>
                                            <p class="text-xs <?php echo $is_me ? 'text-blue-200' : 'text-gray-400'; ?> mt-1">
                                                <?php echo date('h:i A', strtotime($msg['created_at'])); ?>
                                                <?php if($is_me && $msg['is_read']): ?>
                                                    <i class="fas fa-check-double ml-1"></i>
                                                <?php elseif($is_me): ?>
                                                    <i class="fas fa-check ml-1"></i>
                                                <?php endif; ?>
                                            </p>
                                        </div>
                                    </div>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <div class="flex items-center justify-center h-full">
                                    <div class="text-center text-gray-400">
                                        <i class="fas fa-comment-dots text-5xl mb-3"></i>
                                        <p>No messages yet</p>
                                        <p class="text-sm">Start a conversation with your teacher</p>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- Message Input -->
                        <div class="p-4 border-t bg-white">
                            <form id="chatForm" class="flex space-x-2">
                                <input type="hidden" name="receiver_id" value="<?php echo $selected_user; ?>">
                                <input type="text" name="message" id="messageInput" 
                                       placeholder="Type your message..." 
                                       autocomplete="off"
                                       class="flex-1 border rounded-full px-5 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                                <button type="submit" id="sendBtn" class="bg-blue-600 text-white px-5 py-2 rounded-full hover:bg-blue-700 transition-all">
                                    <i class="fas fa-paper-plane"></i>
                                </button>
                            </form>
                        </div>
                    <?php else: ?>
                        <div class="flex-1 flex items-center justify-center">
                            <div class="text-center">
                                <i class="fas fa-comments text-6xl text-gray-300 mb-4"></i>
                                <p class="text-gray-500">Select a teacher to start chatting</p>
                                <p class="text-sm text-gray-400 mt-1">Click on any teacher on the left</p>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
@keyframes fadeIn {
    from { opacity: 0; transform: translateY(10px); }
    to { opacity: 1; transform: translateY(0); }
}
.animate-fadeIn {
    animation: fadeIn 0.3s ease-out;
}
</style>

<script>
// Scroll to bottom of messages
const messagesArea = document.getElementById('messagesArea');
if (messagesArea) {
    messagesArea.scrollTop = messagesArea.scrollHeight;
}

// Send message via AJAX
const chatForm = document.getElementById('chatForm');
if (chatForm) {
    chatForm.addEventListener('submit', function(e) {
        e.preventDefault();
        
        const formData = new FormData(this);
        const messageInput = document.getElementById('messageInput');
        const message = messageInput.value.trim();
        const sendBtn = document.getElementById('sendBtn');
        
        if (!message) return;
        
        sendBtn.disabled = true;
        sendBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
        
        fetch('send.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const messagesArea = document.getElementById('messagesArea');
                const newMessage = document.createElement('div');
                newMessage.className = 'flex justify-end animate-fadeIn';
                newMessage.innerHTML = `
                    <div class="max-w-[70%] bg-blue-500 text-white rounded-lg px-4 py-2">
                        <p class="text-sm">${escapeHtml(message)}</p>
                        <p class="text-xs text-blue-200 mt-1">Just now <i class="fas fa-check ml-1"></i></p>
                    </div>
                `;
                messagesArea.appendChild(newMessage);
                messagesArea.scrollTop = messagesArea.scrollHeight;
                messageInput.value = '';
                
                const emptyState = messagesArea.querySelector('.flex.items-center.justify-center');
                if (emptyState) emptyState.remove();
            } else {
                alert('Failed to send message: ' + data.error);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Failed to send message');
        })
        .finally(() => {
            sendBtn.disabled = false;
            sendBtn.innerHTML = '<i class="fas fa-paper-plane"></i>';
        });
    });
}

// Auto-refresh messages every 5 seconds
let lastMessageId = 0;
const selectedUser = <?php echo $selected_user ?: 0; ?>;

if (selectedUser) {
    function refreshMessages() {
        fetch(`get-messages.php?user=${selectedUser}&last_id=${lastMessageId}`)
            .then(response => response.json())
            .then(data => {
                if (data.success && data.messages && data.messages.length > 0) {
                    const messagesArea = document.getElementById('messagesArea');
                    data.messages.forEach(msg => {
                        const isMe = msg.sender_id == <?php echo $_SESSION['user_id']; ?>;
                        const messageDiv = document.createElement('div');
                        messageDiv.className = `flex ${isMe ? 'justify-end' : 'justify-start'} animate-fadeIn`;
                        messageDiv.innerHTML = `
                            <div class="max-w-[70%] ${isMe ? 'bg-blue-500 text-white' : 'bg-white text-gray-800 shadow-sm'} rounded-lg px-4 py-2">
                                <p class="text-sm">${escapeHtml(msg.message)}</p>
                                <p class="text-xs ${isMe ? 'text-blue-200' : 'text-gray-400'} mt-1">${msg.time}</p>
                            </div>
                        `;
                        messagesArea.appendChild(messageDiv);
                        lastMessageId = msg.id;
                    });
                    messagesArea.scrollTop = messagesArea.scrollHeight;
                }
            })
            .catch(error => console.error('Error refreshing messages:', error));
    }
    
    setInterval(refreshMessages, 5000);
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Press Enter to send
document.getElementById('messageInput')?.addEventListener('keypress', function(e) {
    if (e.key === 'Enter' && !e.shiftKey) {
        e.preventDefault();
        document.getElementById('sendBtn')?.click();
    }
});
</script>

<?php include '../../includes/footer.php'; ?>