<?php
require_once '../../config.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';
requireRole('student');

$page_title = 'Announcements';
include '../../includes/header.php';
include '../../includes/sidebar.php';
include '../../includes/navbar.php';

// Get student ID and class
$student_query = $conn->prepare("
    SELECT s.id, s.class_id, c.name as class_name
    FROM students s
    JOIN classes c ON s.class_id = c.id
    WHERE s.user_id = ?
");
$student_query->bind_param("i", $_SESSION['user_id']);
$student_query->execute();
$student = $student_query->get_result()->fetch_assoc();

if (!$student) {
    echo "<div class='ml-64 mt-16 p-6'><div class='alert alert-danger'>Student record not found!</div></div>";
    include '../../includes/footer.php';
    exit();
}

$student_id = $student['id'];
$class_id = $student['class_id'];
$class_name = $student['class_name'];

// Get announcements for this class
$announcements = $conn->prepare("
    SELECT a.*, 
           CONCAT(u.first_name, ' ', u.last_name) as teacher_name,
           (SELECT COUNT(*) FROM announcement_reads WHERE announcement_id = a.id AND student_id = ?) as is_read
    FROM teacher_announcements a
    JOIN teachers t ON a.teacher_id = t.id
    JOIN users u ON t.user_id = u.id
    WHERE a.class_id = ? AND a.is_published = 1
    ORDER BY a.priority = 'high' DESC, a.created_at DESC
");
$announcements->bind_param("ii", $student_id, $class_id);
$announcements->execute();
$announcements = $announcements->get_result();

// Mark as read when viewing
if (isset($_GET['mark_read']) && is_numeric($_GET['mark_read'])) {
    $ann_id = $_GET['mark_read'];
    $check = $conn->prepare("SELECT id FROM announcement_reads WHERE announcement_id = ? AND student_id = ?");
    $check->bind_param("ii", $ann_id, $student_id);
    $check->execute();
    if ($check->get_result()->num_rows === 0) {
        $mark = $conn->prepare("INSERT INTO announcement_reads (announcement_id, student_id) VALUES (?, ?)");
        $mark->bind_param("ii", $ann_id, $student_id);
        $mark->execute();
    }
    header("Location: index.php");
    exit();
}
?>

<style>
.announcement-card {
    transition: all 0.2s ease;
    border-left: 4px solid transparent;
}
.announcement-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.08);
}
.announcement-card.priority-high {
    border-left-color: #ef4444;
}
.announcement-card.priority-medium {
    border-left-color: #f59e0b;
}
.announcement-card.priority-low {
    border-left-color: #10b981;
}
.announcement-card.unread {
    background: #f0f7ff;
}
.priority-badge {
    font-size: 0.7rem;
    padding: 0.25rem 0.75rem;
    border-radius: 9999px;
}
.priority-high .priority-badge {
    background: #fee2e2;
    color: #991b1b;
}
.priority-medium .priority-badge {
    background: #fef3c7;
    color: #92400e;
}
.priority-low .priority-badge {
    background: #d1fae5;
    color: #065f46;
}
</style>

<div class="ml-64 mt-16 p-6 bg-gray-50 min-h-screen">
    <div class="max-w-4xl mx-auto">
        <!-- Header -->
        <div class="mb-6">
            <h1 class="text-2xl font-bold text-gray-800">📢 Announcements</h1>
            <p class="text-gray-500 mt-1">Important updates from your teachers</p>
            <div class="mt-2 text-sm text-gray-500">
                <i class="fas fa-graduation-cap mr-1"></i> Class: <?php echo htmlspecialchars($class_name); ?>
            </div>
        </div>

        <!-- Announcements List -->
        <?php if ($announcements && $announcements->num_rows > 0): ?>
            <div class="space-y-4">
                <?php while($ann = $announcements->fetch_assoc()): 
                    $is_unread = $ann['is_read'] == 0;
                    $priority_class = 'priority-' . $ann['priority'];
                ?>
                    <div class="announcement-card <?php echo $priority_class; ?> <?php echo $is_unread ? 'unread' : ''; ?> bg-white rounded-xl shadow-sm overflow-hidden">
                        <div class="p-6">
                            <div class="flex flex-wrap justify-between items-start gap-3">
                                <div class="flex-1">
                                    <div class="flex flex-wrap items-center gap-3 mb-2">
                                        <span class="priority-badge">
                                            <?php echo ucfirst($ann['priority']); ?>
                                        </span>
                                        <?php if($is_unread): ?>
                                            <span class="text-xs text-blue-600">
                                                <i class="fas fa-circle text-xs"></i> New
                                            </span>
                                        <?php endif; ?>
                                        <span class="text-xs text-gray-400">
                                            <i class="far fa-calendar mr-1"></i> 
                                            <?php echo date('M d, Y h:i A', strtotime($ann['created_at'])); ?>
                                        </span>
                                        <span class="text-xs text-gray-400">
                                            <i class="fas fa-user mr-1"></i> 
                                            <?php echo htmlspecialchars($ann['teacher_name']); ?>
                                        </span>
                                    </div>
                                    <h3 class="text-lg font-semibold text-gray-800"><?php echo htmlspecialchars($ann['title']); ?></h3>
                                    <div class="text-gray-600 mt-2 whitespace-pre-wrap">
                                        <?php echo nl2br(htmlspecialchars($ann['content'])); ?>
                                    </div>
                                </div>
                            </div>
                            <?php if($is_unread): ?>
                                <div class="mt-4 pt-3 border-t">
                                    <a href="?mark_read=<?php echo $ann['id']; ?>" class="text-sm text-blue-600 hover:text-blue-800">
                                        <i class="fas fa-check mr-1"></i> Mark as read
                                    </a>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>
        <?php else: ?>
            <div class="bg-white rounded-xl shadow-sm p-12 text-center">
                <i class="fas fa-bullhorn text-6xl text-gray-300 mb-4"></i>
                <h3 class="text-xl font-semibold text-gray-600">No Announcements</h3>
                <p class="text-gray-400 mt-2">Your teacher hasn't posted any announcements yet.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>