<?php
require_once '../../config.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';
requireRole('teacher');

$page_title = 'Assignment Management';
include '../../includes/header.php';
include '../../includes/sidebar.php';
include '../../includes/navbar.php';

// Get teacher ID
$teacher_query = $conn->prepare("SELECT id FROM teachers WHERE user_id = ?");
$teacher_query->bind_param("i", $_SESSION['user_id']);
$teacher_query->execute();
$teacher_result = $teacher_query->get_result();
$teacher = $teacher_result->fetch_assoc();
$teacher_id = $teacher['id'] ?? 0;

// Get statistics - Fixed queries without subqueries that might cause issues
$stats = [];

// Total assignments
$total_query = "SELECT COUNT(*) as count FROM assignments WHERE created_by = " . intval($_SESSION['user_id']);
$total_result = $conn->query($total_query);
$stats['total'] = $total_result->fetch_assoc()['count'] ?? 0;

// Published assignments
$published_query = "SELECT COUNT(*) as count FROM assignments WHERE created_by = " . intval($_SESSION['user_id']) . " AND status = 'published'";
$published_result = $conn->query($published_query);
$stats['published'] = $published_result->fetch_assoc()['count'] ?? 0;

// Draft assignments
$draft_query = "SELECT COUNT(*) as count FROM assignments WHERE created_by = " . intval($_SESSION['user_id']) . " AND status = 'draft'";
$draft_result = $conn->query($draft_query);
$stats['draft'] = $draft_result->fetch_assoc()['count'] ?? 0;

// Pending submissions
$pending_query = "SELECT COUNT(DISTINCT s.id) as count 
                  FROM submissions s
                  JOIN assignments a ON s.assignment_id = a.id
                  WHERE a.created_by = " . intval($_SESSION['user_id']) . " AND s.marks_obtained IS NULL";
$pending_result = $conn->query($pending_query);
$stats['pending'] = $pending_result->fetch_assoc()['count'] ?? 0;

// Graded submissions
$graded_query = "SELECT COUNT(DISTINCT s.id) as count 
                 FROM submissions s
                 JOIN assignments a ON s.assignment_id = a.id
                 WHERE a.created_by = " . intval($_SESSION['user_id']) . " AND s.marks_obtained IS NOT NULL";
$graded_result = $conn->query($graded_query);
$stats['graded'] = $graded_result->fetch_assoc()['count'] ?? 0;

// Late submissions
$late_query = "SELECT COUNT(DISTINCT s.id) as count 
               FROM submissions s
               JOIN assignments a ON s.assignment_id = a.id
               WHERE a.created_by = " . intval($_SESSION['user_id']) . " AND s.submitted_at > a.due_date";
$late_result = $conn->query($late_query);
$stats['late'] = $late_result->fetch_assoc()['count'] ?? 0;

// Get upcoming deadlines
$deadlines_query = "SELECT a.*, s.name as subject_name, c.name as class_name,
                    (SELECT COUNT(*) FROM submissions WHERE assignment_id = a.id) as submissions_count
                    FROM assignments a
                    JOIN subjects s ON a.subject_id = s.id
                    JOIN classes c ON a.class_id = c.id
                    WHERE a.created_by = " . intval($_SESSION['user_id']) . " AND a.due_date > NOW() AND a.status = 'published'
                    ORDER BY a.due_date ASC LIMIT 5";
$deadlines = $conn->query($deadlines_query);

// Get recent assignments
$assignments_query = "SELECT a.*, s.name as subject_name, c.name as class_name,
                      (SELECT COUNT(*) FROM submissions WHERE assignment_id = a.id) as submissions_count,
                      (SELECT AVG(marks_obtained) FROM submissions WHERE assignment_id = a.id AND marks_obtained IS NOT NULL) as avg_score
                      FROM assignments a
                      JOIN subjects s ON a.subject_id = s.id
                      JOIN classes c ON a.class_id = c.id
                      WHERE a.created_by = " . intval($_SESSION['user_id']) . "
                      ORDER BY a.created_at DESC LIMIT 10";
$assignments = $conn->query($assignments_query);

// Get performance analytics
$performance_query = "SELECT 
    AVG(s.marks_obtained) as avg_score,
    MAX(s.marks_obtained) as highest,
    MIN(s.marks_obtained) as lowest,
    SUM(CASE WHEN s.marks_obtained >= 50 THEN 1 ELSE 0 END) as passed,
    SUM(CASE WHEN s.marks_obtained < 50 THEN 1 ELSE 0 END) as failed
    FROM submissions s
    JOIN assignments a ON s.assignment_id = a.id
    WHERE a.created_by = " . intval($_SESSION['user_id']) . " AND s.marks_obtained IS NOT NULL";
$performance_result = $conn->query($performance_query);
$performance_data = $performance_result->fetch_assoc();
?>

<div class="ml-64 mt-16 p-6">
    <div class="mb-6 flex justify-between items-center">
        <div>
            <h1 class="text-2xl font-bold text-gray-800">Assignment Management</h1>
            <p class="text-gray-500 mt-1">Create, manage, and grade student assignments</p>
        </div>
        <a href="create.php" class="bg-gradient-to-r from-blue-500 to-purple-600 text-white px-6 py-2 rounded-lg hover:shadow-lg transition-all">
            <i class="fas fa-plus mr-2"></i> Create Assignment
        </a>
    </div>

    <!-- Statistics Cards -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-6 gap-4 mb-6">
        <div class="bg-white rounded-xl p-4 shadow-sm hover:shadow-md transition-all">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-gray-500 text-xs">Total</p>
                    <p class="text-2xl font-bold"><?php echo $stats['total']; ?></p>
                </div>
                <i class="fas fa-tasks text-blue-500 text-2xl"></i>
            </div>
        </div>
        <div class="bg-white rounded-xl p-4 shadow-sm hover:shadow-md transition-all">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-gray-500 text-xs">Published</p>
                    <p class="text-2xl font-bold text-green-600"><?php echo $stats['published']; ?></p>
                </div>
                <i class="fas fa-check-circle text-green-500 text-2xl"></i>
            </div>
        </div>
        <div class="bg-white rounded-xl p-4 shadow-sm hover:shadow-md transition-all">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-gray-500 text-xs">Draft</p>
                    <p class="text-2xl font-bold text-yellow-600"><?php echo $stats['draft']; ?></p>
                </div>
                <i class="fas fa-edit text-yellow-500 text-2xl"></i>
            </div>
        </div>
        <div class="bg-white rounded-xl p-4 shadow-sm hover:shadow-md transition-all">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-gray-500 text-xs">Pending</p>
                    <p class="text-2xl font-bold text-orange-600"><?php echo $stats['pending']; ?></p>
                </div>
                <i class="fas fa-clock text-orange-500 text-2xl"></i>
            </div>
        </div>
        <div class="bg-white rounded-xl p-4 shadow-sm hover:shadow-md transition-all">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-gray-500 text-xs">Graded</p>
                    <p class="text-2xl font-bold text-purple-600"><?php echo $stats['graded']; ?></p>
                </div>
                <i class="fas fa-star text-purple-500 text-2xl"></i>
            </div>
        </div>
        <div class="bg-white rounded-xl p-4 shadow-sm hover:shadow-md transition-all">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-gray-500 text-xs">Late</p>
                    <p class="text-2xl font-bold text-red-600"><?php echo $stats['late']; ?></p>
                </div>
                <i class="fas fa-exclamation-triangle text-red-500 text-2xl"></i>
            </div>
        </div>
    </div>

    <!-- Performance Summary -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
        <div class="bg-gradient-to-r from-green-500 to-green-600 rounded-xl p-4 text-white">
            <p class="text-sm opacity-90">Average Score</p>
            <p class="text-2xl font-bold"><?php echo round($performance_data['avg_score'] ?? 0, 1); ?>%</p>
        </div>
        <div class="bg-gradient-to-r from-blue-500 to-blue-600 rounded-xl p-4 text-white">
            <p class="text-sm opacity-90">Highest Score</p>
            <p class="text-2xl font-bold"><?php echo round($performance_data['highest'] ?? 0, 1); ?>%</p>
        </div>
        <div class="bg-gradient-to-r from-yellow-500 to-yellow-600 rounded-xl p-4 text-white">
            <p class="text-sm opacity-90">Lowest Score</p>
            <p class="text-2xl font-bold"><?php echo round($performance_data['lowest'] ?? 0, 1); ?>%</p>
        </div>
        <div class="bg-gradient-to-r from-purple-500 to-purple-600 rounded-xl p-4 text-white">
            <p class="text-sm opacity-90">Pass Rate</p>
            <p class="text-2xl font-bold">
                <?php 
                $total_graded = ($performance_data['passed'] ?? 0) + ($performance_data['failed'] ?? 0);
                echo $total_graded > 0 ? round(($performance_data['passed'] / $total_graded) * 100, 1) : 0; ?>%
            </p>
        </div>
    </div>

    <!-- Upcoming Deadlines -->
    <?php if ($deadlines && $deadlines->num_rows > 0): ?>
    <div class="bg-white rounded-xl shadow-sm mb-6 overflow-hidden">
        <div class="px-6 py-4 border-b bg-yellow-50">
            <h3 class="font-semibold text-lg">
                <i class="fas fa-bell text-yellow-600 mr-2"></i> Upcoming Deadlines
            </h3>
        </div>
        <div class="divide-y">
            <?php while($deadline = $deadlines->fetch_assoc()): 
                $days_left = ceil((strtotime($deadline['due_date']) - time()) / (60 * 60 * 24));
                $color = $days_left <= 2 ? 'text-red-600' : ($days_left <= 5 ? 'text-yellow-600' : 'text-green-600');
            ?>
                <div class="p-4 flex justify-between items-center hover:bg-gray-50">
                    <div>
                        <p class="font-medium"><?php echo htmlspecialchars($deadline['title']); ?></p>
                        <p class="text-sm text-gray-500"><?php echo htmlspecialchars($deadline['class_name']); ?> • <?php echo htmlspecialchars($deadline['subject_name']); ?></p>
                    </div>
                    <div class="text-right">
                        <p class="text-sm <?php echo $color; ?> font-semibold">
                            <?php echo date('M d, Y', strtotime($deadline['due_date'])); ?>
                        </p>
                        <p class="text-xs text-gray-400"><?php echo $days_left; ?> days left • <?php echo $deadline['submissions_count']; ?> submitted</p>
                    </div>
                </div>
            <?php endwhile; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Assignments Table -->
    <div class="bg-white rounded-xl shadow-sm overflow-hidden">
        <div class="px-6 py-4 border-b bg-gray-50">
            <h3 class="font-semibold text-lg">All Assignments</h3>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-gray-50 border-b">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Assignment</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Class/Subject</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Due Date</th>
                        <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">Submissions</th>
                        <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">Avg Score</th>
                        <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">Status</th>
                        <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    <?php if ($assignments && $assignments->num_rows > 0): ?>
                        <?php while($ass = $assignments->fetch_assoc()): 
                            $is_due = strtotime($ass['due_date']) < time();
                            $status_color = $ass['status'] == 'published' ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-700';
                            $status_text = ucfirst($ass['status']);
                            $avg_score = round($ass['avg_score'] ?? 0, 1);
                        ?>
                            <tr class="hover:bg-gray-50 transition-all">
                                <td class="px-6 py-4">
                                    <div>
                                        <p class="font-medium text-gray-800"><?php echo htmlspecialchars($ass['title']); ?></p>
                                        <p class="text-xs text-gray-500">Type: <?php echo ucfirst($ass['assignment_type'] ?? 'homework'); ?></p>
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <p><?php echo htmlspecialchars($ass['class_name']); ?></p>
                                    <p class="text-xs text-gray-500"><?php echo htmlspecialchars($ass['subject_name']); ?></p>
                                </td>
                                <td class="px-6 py-4">
                                    <span class="<?php echo $is_due && $ass['status'] == 'published' ? 'text-red-600' : 'text-gray-600'; ?>">
                                        <?php echo date('M d, Y', strtotime($ass['due_date'])); ?>
                                    </span>
                                    <?php if($is_due && $ass['status'] == 'published'): ?>
                                        <p class="text-xs text-red-500">Overdue</p>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4 text-center">
                                    <span class="font-semibold"><?php echo $ass['submissions_count']; ?></span>
                                    <span class="text-gray-400 text-xs"> submitted</span>
                                </td>
                                <td class="px-6 py-4 text-center">
                                    <span class="font-semibold <?php echo $avg_score >= 70 ? 'text-green-600' : ($avg_score >= 50 ? 'text-yellow-600' : 'text-red-600'); ?>">
                                        <?php echo $avg_score > 0 ? $avg_score . '%' : '-'; ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-center">
                                    <span class="px-2 py-1 text-xs rounded-full <?php echo $status_color; ?>">
                                        <?php echo $status_text; ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-center">
                                    <div class="flex justify-center space-x-2">
                                        <a href="submissions.php?id=<?php echo $ass['id']; ?>" class="text-blue-600 hover:text-blue-800" title="View Submissions">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <a href="edit.php?id=<?php echo $ass['id']; ?>" class="text-green-600 hover:text-green-800" title="Edit">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <a href="analytics.php?id=<?php echo $ass['id']; ?>" class="text-purple-600 hover:text-purple-800" title="Analytics">
                                            <i class="fas fa-chart-line"></i>
                                        </a>
                                        <a href="delete.php?id=<?php echo $ass['id']; ?>" onclick="return confirm('Delete this assignment?')" class="text-red-600 hover:text-red-800" title="Delete">
                                            <i class="fas fa-trash-alt"></i>
                                        </a>
                                    </div>
                                </tr>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" class="px-6 py-12 text-center text-gray-500">
                                <i class="fas fa-tasks text-4xl mb-2 block"></i>
                                No assignments created yet. Click "Create Assignment" to get started.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>