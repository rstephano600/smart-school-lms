<?php
require_once '../../config.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';
requireRole('teacher');

$exam_id = $_GET['id'] ?? 0;
if (!$exam_id) {
    header('Location: index.php');
    exit();
}

// Get teacher ID
$teacher_query = $conn->prepare("SELECT id FROM teachers WHERE user_id = ?");
$teacher_query->bind_param("i", $_SESSION['user_id']);
$teacher_query->execute();
$teacher = $teacher_query->get_result()->fetch_assoc();
$teacher_id = $teacher['id'] ?? 0;

// Get exam details
$exam_query = $conn->prepare("
    SELECT te.*, s.name as subject_name, c.name as class_name
    FROM teacher_exams te
    JOIN subjects s ON te.subject_id = s.id
    JOIN classes c ON te.class_id = c.id
    WHERE te.id = ? AND te.teacher_id = ?
");
$exam_query->bind_param("ii", $exam_id, $teacher_id);
$exam_query->execute();
$exam = $exam_query->get_result()->fetch_assoc();

if (!$exam) {
    header('Location: index.php');
    exit();
}

// Get active/live submissions (started but not submitted)
$live_query = $conn->prepare("
    SELECT es.*, 
           CONCAT(u.first_name, ' ', u.last_name) as student_name,
           s.admission_number,
           TIMESTAMPDIFF(MINUTE, es.started_at, NOW()) as time_elapsed
    FROM exam_submissions es
    JOIN students s ON es.student_id = s.id
    JOIN users u ON s.user_id = u.id
    WHERE es.exam_id = ? AND es.submitted_at IS NULL AND es.started_at IS NOT NULL
    ORDER BY es.started_at ASC
");
$live_query->bind_param("i", $exam_id);
$live_query->execute();
$live_students = $live_query->get_result();

$page_title = 'Live Monitoring - ' . $exam['title'];
include '../../includes/header.php';
include '../../includes/sidebar.php';
include '../../includes/navbar.php';
?>

<div class="ml-64 mt-16 p-6">
    <div class="max-w-full mx-auto">
        <div class="mb-6">
            <h1 class="text-2xl font-bold text-gray-800">Live Exam Monitoring</h1>
            <p class="text-gray-500 mt-1"><?php echo htmlspecialchars($exam['title']); ?> - Active: <?php echo $live_students->num_rows; ?> students</p>
        </div>

        <!-- Live Students Table -->
        <div class="bg-white rounded-xl shadow-sm overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-gray-50 border-b">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Student</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Admission No</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Started At</th>
                            <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">Time Elapsed</th>
                            <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">Remaining</th>
                            <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">Status</th>
                            <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <?php if ($live_students && $live_students->num_rows > 0): ?>
                            <?php while($student = $live_students->fetch_assoc()): 
                                $remaining = max(0, $exam['duration_minutes'] - $student['time_elapsed']);
                                $progress = ($student['time_elapsed'] / $exam['duration_minutes']) * 100;
                            ?>
                                <tr class="hover:bg-gray-50">
                                    <td class="px-6 py-4 font-medium"><?php echo htmlspecialchars($student['student_name']); ?></td>
                                    <td class="px-6 py-4"><?php echo $student['admission_number']; ?></td>
                                    <td class="px-6 py-4"><?php echo date('h:i A', strtotime($student['started_at'])); ?></td>
                                    <td class="px-6 py-4 text-center">
                                        <span class="font-mono"><?php echo floor($student['time_elapsed'] / 60); ?>:<?php echo str_pad($student['time_elapsed'] % 60, 2, '0', STR_PAD_LEFT); ?></span>
                                    </td>
                                    <td class="px-6 py-4 text-center">
                                        <div class="flex items-center space-x-2">
                                            <div class="w-24 bg-gray-200 rounded-full h-2">
                                                <div class="bg-blue-600 h-2 rounded-full" style="width: <?php echo $progress; ?>%"></div>
                                            </div>
                                            <span class="text-sm font-mono"><?php echo floor($remaining / 60); ?>:<?php echo str_pad($remaining % 60, 2, '0', STR_PAD_LEFT); ?></span>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 text-center">
                                        <span class="px-2 py-1 text-xs rounded-full bg-green-100 text-green-700">In Progress</span>
                                    </td>
                                    <td class="px-6 py-4 text-center">
                                        <button onclick="alert('View student progress - Coming soon')" class="text-blue-600 hover:text-blue-800">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" class="px-6 py-12 text-center text-gray-500">
                                    <i class="fas fa-users text-4xl mb-2 block"></i>
                                    No students currently taking this exam
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Auto-refresh script -->
        <script>
            setTimeout(function() {
                location.reload();
            }, 30000); // Refresh every 30 seconds
        </script>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>