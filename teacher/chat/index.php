<?php
require_once '../../config.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';
requireRole('teacher');

$page_title = 'Messages';
include '../../includes/header.php';
include '../../includes/sidebar.php';
include '../../includes/navbar.php';

// Get teacher ID
$teacher_query = $conn->prepare("SELECT id FROM teachers WHERE user_id = ?");
$teacher_query->bind_param("i", $_SESSION['user_id']);
$teacher_query->execute();
$teacher = $teacher_query->get_result()->fetch_assoc();
$teacher_id = $teacher['id'] ?? 0;

// Get students for this teacher
$students = $conn->prepare("
    SELECT DISTINCT u.id, u.first_name, u.last_name, 'student' as role,
           (SELECT message FROM messages WHERE 
               (sender_id = ? AND receiver_id = u.id) OR 
               (sender_id = u.id AND receiver_id = ?) 
            ORDER BY created_at DESC LIMIT 1) as last_message,
           (SELECT created_at FROM messages WHERE 
               (sender_id = ? AND receiver_id = u.id) OR 
               (sender_id = u.id AND receiver_id = ?) 
            ORDER BY created_at DESC LIMIT 1) as last_time,
           (SELECT COUNT(*) FROM messages WHERE receiver_id = ? AND sender_id = u.id AND is_read = 0) as unread_count
    FROM students s
    JOIN users u ON s.user_id = u.id
    WHERE s.class_id IN (
        SELECT DISTINCT class_id FROM class_subject WHERE teacher_id = ?
    )
    ORDER BY last_time DESC
");
$students->bind_param("iiiiii", $_SESSION['user_id'], $_SESSION['user_id'], $_SESSION['user_id'], $_SESSION['user_id'], $_SESSION['user_id'], $teacher_id);
$students->execute();
$students = $students->get_result();

// Get selected user
$selected_user = isset($_GET['user']) ? intval($_GET['user']) : 0;
$chat_user = null;
$messages = [];

if ($selected_user > 0) {
    $user_query = $conn->prepare("SELECT id, first_name, last_name, role FROM users WHERE id = ?");
    $user_query->bind_param("i", $selected_user);
    $user_query->execute();
    $chat_user = $user_query->get_result()->fetch_assoc();
    
    // Mark messages as read
    $mark_read = $conn->prepare("UPDATE messages SET is_read = 1, read_at = NOW() WHERE sender_id = ? AND receiver_id = ? AND is_read = 0");
    $mark_read->bind_param("ii", $selected_user, $_SESSION['user_id']);
    $mark_read->execute();
    
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

<div class="ml-64 mt-16 p-6 bg-gray-50 min-h-screen">
    <div class="max-w-6xl mx-auto">
        <div class="mb-6">
            <h1 class="text-2xl font-bold text-gray-800">Messages</h1>
            <p class="text-gray-500 mt-1">Chat with your students</p>
        </div>

        <div class="bg-white rounded-xl shadow-sm overflow-hidden">
            <div class="grid grid-cols-1 md:grid-cols-3 h-[600px]">
                <!-- Contacts Sidebar -->
                <div class="border-r border-gray-200">
                    <div class="p-4 border-b bg-gray-50">
                        <h3 class="font-semibold">Students</h3>
                    </div>
                    <div class="overflow-y-auto h-[540px]">
                        <?php if ($students && $students->num_rows > 0): ?>
                            <?php while($student = $students->fetch_assoc()): 
                                $unread = $student['unread_count'] > 0;
                            ?>
                                <a href="?user=<?php echo $student['id']; ?>" 
                                   class="block p-3 hover:bg-gray-50 border-b transition-all <?php echo $selected_user == $student['id'] ? 'bg-blue-50 border-l-4 border-l-blue-500' : ''; ?>">
                                    <div class="flex items-center space-x-3">
                                        <div class="w-10 h-10 bg-gradient-to-r from-green-500 to-teal-600 rounded-full flex items-center justify-center text-white font-bold">
                                            <?php echo strtoupper(substr($student['first_name'], 0, 1) . substr($student['last_name'], 0, 1)); ?>
                                        </div>
                                        <div class="flex-1 min-w-0">
                                            <div class="flex justify-between items-center">
                                                <p class="font-medium text-gray-800 truncate">
                                                    <?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?>
                                                </p>
                                                <?php if($unread): ?>
                                                    <span class="bg-red-500 text-white text-xs rounded-full px-2 py-0.5 animate-pulse">
                                                        <?php echo $student['unread_count']; ?>
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                            <p class="text-xs text-gray-500">Student</p>
                                            <?php if($student['last_message']): ?>
                                                <p class="text-xs text-gray-400 truncate">
                                                    <?php echo htmlspecialchars(substr($student['last_message'], 0, 40)); ?>
                                                    <?php if($unread): ?>
                                                        <span class="text-blue-600 font-bold">●</span>
                                                    <?php endif; ?>
                                                </p>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </a>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <div class="p-8 text-center text-gray-500">
                                <i class="fas fa-users text-4xl mb-2 block"></i>
                                <p>No students available</p>
                                <p class="text-xs mt-1">Students will appear here once assigned</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Chat Area -->
                <div class="md:col-span-2 flex flex-col h-[600px]">
                    <?php if ($selected_user > 0 && $chat_user): ?>
                        <div class="p-4 border-b bg-gray-50 flex items-center justify-between">
                            <div class="flex items-center space-x-3">
                                <div class="w-10 h-10 bg-gradient-to-r from-green-500 to-teal-600 rounded-full flex items-center justify-center text-white font-bold">
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

                        <div class="flex-1 overflow-y-auto p-4 space-y-3 bg-gray-50" id="messagesArea">
                            <?php if ($messages && $messages->num_rows > 0): ?>
                                <?php while($msg = $messages->fetch_assoc()): 
                                    $is_me = $msg['sender_id'] == $_SESSION['user_id'];
                                    $is_read = $msg['is_read'] == 1;
                                ?>
                                    <div class="flex <?php echo $is_me ? 'justify-end' : 'justify-start'; ?>">
                                        <div class="max-w-[70%] <?php echo $is_me ? 'bg-blue-500 text-white' : 'bg-white text-gray-800 shadow-sm'; ?> rounded-lg px-4 py-2">
                                            <p class="text-sm"><?php echo nl2br(htmlspecialchars($msg['message'])); ?></p>
                                            <p class="text-xs <?php echo $is_me ? 'text-blue-200' : 'text-gray-400'; ?> mt-1 flex items-center space-x-1">
                                                <span><?php echo date('h:i A', strtotime($msg['created_at'])); ?></span>
                                                <?php if($is_me): ?>
                                                    <?php if($is_read): ?>
                                                        <span class="text-green-400"><i class="fas fa-check-double"></i> Read</span>
                                                    <?php else: ?>
                                                        <span class="text-gray-400"><i class="fas fa-check"></i> Sent</span>
                                                    <?php endif; ?>
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
                                        <p class="text-sm">Start a conversation with your student</p>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>

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
                                <p class="text-gray-500">Select a student to start chatting</p>
                                <p class="text-sm text-gray-400 mt-1">Click on any student on the left</p>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
const messagesArea = document.getElementById('messagesArea');
if (messagesArea) {
    messagesArea.scrollTop = messagesArea.scrollHeight;
}

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
                        <p class="text-xs text-blue-200 mt-1">Just now <i class="fas fa-check"></i></p>
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

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}
</script>

<?php include '../../includes/footer.php'; ?>