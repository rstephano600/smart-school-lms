<?php
require_once '../../config.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';
requireRole('teacher');

$page_title = 'Announcements';
include '../../includes/header.php';
include '../../includes/sidebar.php';
include '../../includes/navbar.php';

// Get teacher ID
$teacher_query = $conn->prepare("SELECT id FROM teachers WHERE user_id = ?");
$teacher_query->bind_param("i", $_SESSION['user_id']);
$teacher_query->execute();
$teacher = $teacher_query->get_result()->fetch_assoc();
$teacher_id = $teacher['id'] ?? 0;

// Handle deletion
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $ann_id = $_GET['delete'];
    $delete = $conn->prepare("DELETE FROM teacher_announcements WHERE id = ? AND teacher_id = ?");
    $delete->bind_param("ii", $ann_id, $teacher_id);
    $delete->execute();
    $_SESSION['success'] = "Announcement deleted successfully!";
    header('Location: index.php');
    exit();
}

// Get announcements with stats
$announcements = $conn->prepare("
    SELECT a.*, c.name as class_name,
           (SELECT COUNT(*) FROM announcement_reads WHERE announcement_id = a.id) as read_count,
           (SELECT COUNT(*) FROM students WHERE class_id = a.class_id) as total_students
    FROM teacher_announcements a
    JOIN classes c ON a.class_id = c.id
    WHERE a.teacher_id = ?
    ORDER BY a.created_at DESC
");
$announcements->bind_param("i", $teacher_id);
$announcements->execute();
$announcements = $announcements->get_result();

$success = $_SESSION['success'] ?? '';
unset($_SESSION['success']);
?>

<style>
.announcement-card {
    transition: all 0.2s;
}
.announcement-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.05);
}
.priority-high {
    background: #fee2e2;
    color: #991b1b;
}
.priority-medium {
    background: #fef3c7;
    color: #92400e;
}
.priority-low {
    background: #d1fae5;
    color: #065f46;
}
</style>

<div class="ml-64 mt-16 p-6 bg-gray-50 min-h-screen">
    <div class="max-w-full mx-auto">
        <!-- Header -->
        <div class="mb-6 flex flex-wrap justify-between items-center">
            <div>
                <h1 class="text-2xl font-bold text-gray-800">📢 Announcements</h1>
                <p class="text-gray-500 mt-1">Create and manage class announcements</p>
            </div>
            <a href="create.php" class="bg-gradient-to-r from-blue-500 to-purple-600 text-white px-6 py-2 rounded-lg hover:shadow-lg transition flex items-center">
                <i class="fas fa-plus mr-2"></i> New Announcement
            </a>
        </div>

        <!-- Messages -->
        <?php if($success): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-lg mb-4">
                <i class="fas fa-check-circle mr-2"></i> <?php echo $success; ?>
            </div>
        <?php endif; ?>

        <!-- Announcements List -->
        <div class="space-y-4">
            <?php if ($announcements && $announcements->num_rows > 0): ?>
                <?php while($ann = $announcements->fetch_assoc()): 
                    $read_rate = $ann['total_students'] > 0 ? round(($ann['read_count'] / $ann['total_students']) * 100) : 0;
                    $priority_class = 'priority-' . $ann['priority'];
                ?>
                    <div class="announcement-card bg-white rounded-xl shadow-sm overflow-hidden">
                        <div class="p-6">
                            <div class="flex flex-wrap justify-between items-start gap-4">
                                <div class="flex-1">
                                    <div class="flex flex-wrap items-center gap-3 mb-2">
                                        <span class="px-2 py-1 text-xs rounded-full <?php echo $priority_class; ?>">
                                            <?php echo ucfirst($ann['priority']); ?>
                                        </span>
                                        <span class="text-xs text-gray-400">
                                            <i class="far fa-calendar mr-1"></i> 
                                            <?php echo date('M d, Y', strtotime($ann['created_at'])); ?>
                                        </span>
                                        <span class="text-xs text-gray-400">
                                            <i class="fas fa-graduation-cap mr-1"></i> 
                                            <?php echo htmlspecialchars($ann['class_name']); ?>
                                        </span>
                                    </div>
                                    <h3 class="text-lg font-semibold text-gray-800"><?php echo htmlspecialchars($ann['title']); ?></h3>
                                    <p class="text-gray-600 mt-2 line-clamp-3"><?php echo nl2br(htmlspecialchars($ann['content'])); ?></p>
                                </div>
                            </div>
                            
                            <!-- Stats -->
                            <div class="mt-4 pt-4 border-t flex flex-wrap justify-between items-center gap-3">
                                <div class="flex items-center gap-4 text-sm">
                                    <span class="text-gray-500">
                                        <i class="fas fa-eye mr-1"></i> 
                                        <?php echo $ann['read_count']; ?>/<?php echo $ann['total_students']; ?> read
                                    </span>
                                    <div class="flex items-center gap-2">
                                        <div class="w-24 bg-gray-200 rounded-full h-2">
                                            <div class="bg-blue-600 h-2 rounded-full" style="width: <?php echo $read_rate; ?>%"></div>
                                        </div>
                                        <span class="text-gray-500 text-xs"><?php echo $read_rate; ?>%</span>
                                    </div>
                                </div>
                                <div class="flex gap-2">
                                    <button onclick="viewAnnouncement(<?php echo $ann['id']; ?>)" 
                                            class="text-blue-600 hover:text-blue-800 text-sm px-3 py-1 hover:bg-blue-50 rounded-lg">
                                        <i class="fas fa-eye mr-1"></i> View
                                    </button>
                                    <a href="?delete=<?php echo $ann['id']; ?>" 
                                       onclick="return confirm('Delete this announcement?')" 
                                       class="text-red-600 hover:text-red-800 text-sm px-3 py-1 hover:bg-red-50 rounded-lg">
                                        <i class="fas fa-trash-alt mr-1"></i> Delete
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="bg-white rounded-xl shadow-sm p-12 text-center">
                    <i class="fas fa-bullhorn text-6xl text-gray-300 mb-4"></i>
                    <h3 class="text-xl font-semibold text-gray-600">No Announcements</h3>
                    <p class="text-gray-400 mt-2">Click "New Announcement" to create your first one</p>
                    <a href="create.php" class="mt-4 inline-block bg-gradient-to-r from-blue-500 to-purple-600 text-white px-6 py-2 rounded-lg hover:shadow-lg transition">
                        <i class="fas fa-plus mr-2"></i> Create First Announcement
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- View Modal -->
<div id="viewModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center">
    <div class="bg-white rounded-xl w-full max-w-2xl p-6 max-h-[80vh] overflow-y-auto">
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-xl font-semibold" id="modalTitle"></h3>
            <button onclick="closeViewModal()" class="text-gray-400 hover:text-gray-600">
                <i class="fas fa-times text-xl"></i>
            </button>
        </div>
        <div class="text-sm text-gray-500 mb-3" id="modalMeta"></div>
        <div class="prose max-w-none" id="modalContent"></div>
    </div>
</div>

<script>
function viewAnnouncement(id) {
    // Get announcement data from the page
    const cards = document.querySelectorAll('.announcement-card');
    for (const card of cards) {
        const link = card.querySelector('a[href*="delete"]');
        if (link) {
            const href = link.getAttribute('href');
            const annId = href.match(/delete=(\d+)/)?.[1];
            if (annId == id) {
                const title = card.querySelector('h3').textContent;
                const content = card.querySelector('p').textContent;
                const meta = card.querySelector('.text-xs.text-gray-400')?.textContent || '';
                
                document.getElementById('modalTitle').textContent = title;
                document.getElementById('modalContent').textContent = content;
                document.getElementById('modalMeta').textContent = meta;
                document.getElementById('viewModal').classList.remove('hidden');
                break;
            }
        }
    }
}

function closeViewModal() {
    document.getElementById('viewModal').classList.add('hidden');
}

// Close modal on outside click
document.getElementById('viewModal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeViewModal();
    }
});
</script>

<?php include '../../includes/footer.php'; ?>