<?php
require_once '../../config.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';
requireRole('student');

$page_title = 'My Exams';
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

// Get exams for this student's class
$exams = $conn->prepare("
    SELECT te.*, s.name as subject_name,
           (SELECT COUNT(*) FROM exam_questions WHERE exam_id = te.id) as total_questions,
           (SELECT id FROM exam_submissions WHERE exam_id = te.id AND student_id = ?) as submission_id,
           (SELECT submitted_at FROM exam_submissions WHERE exam_id = te.id AND student_id = ?) as submitted_at,
           (SELECT percentage FROM exam_submissions WHERE exam_id = te.id AND student_id = ?) as percentage
    FROM teacher_exams te
    JOIN subjects s ON te.subject_id = s.id
    WHERE te.class_id = ? 
    AND te.is_published = 1
    ORDER BY te.start_date ASC, te.created_at DESC
");
$exams->bind_param("iiii", $student_id, $student_id, $student_id, $class_id);
$exams->execute();
$exams = $exams->get_result();

// Get exam statistics
$total_exams = $exams->num_rows;
$completed = 0;
$pending = 0;

$exams->data_seek(0);
while ($exam = $exams->fetch_assoc()) {
    if ($exam['submission_id']) {
        $completed++;
    } else {
        $pending++;
    }
}
$exams->data_seek(0);
?>

<style>
.exam-card {
    transition: all 0.3s ease;
}
.exam-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 8px 25px rgba(0,0,0,0.08);
}
.status-badge {
    font-size: 0.7rem;
    padding: 0.25rem 0.75rem;
    border-radius: 9999px;
}
.status-upcoming {
    background: #dbeafe;
    color: #1e40af;
}
.status-active {
    background: #d1fae5;
    color: #065f46;
}
.status-completed {
    background: #fef3c7;
    color: #92400e;
}
.status-published {
    background: #d1fae5;
    color: #065f46;
}
.status-draft {
    background: #fee2e2;
    color: #991b1b;
}
</style>

<div class="ml-64 mt-16 p-6 bg-gray-50 min-h-screen">
    <div class="max-w-6xl mx-auto">
        <!-- Header -->
        <div class="mb-6">
            <h1 class="text-2xl font-bold text-gray-800">📝 My Exams</h1>
            <p class="text-gray-500 mt-1">View and take your assigned exams</p>
            <div class="mt-3 flex flex-wrap gap-3">
                <div class="bg-white rounded-xl px-4 py-2 shadow-sm">
                    <span class="text-sm text-gray-500">Total Exams</span>
                    <span class="ml-2 font-bold text-blue-600"><?php echo $total_exams; ?></span>
                </div>
                <div class="bg-white rounded-xl px-4 py-2 shadow-sm">
                    <span class="text-sm text-gray-500">Completed</span>
                    <span class="ml-2 font-bold text-green-600"><?php echo $completed; ?></span>
                </div>
                <div class="bg-white rounded-xl px-4 py-2 shadow-sm">
                    <span class="text-sm text-gray-500">Pending</span>
                    <span class="ml-2 font-bold text-yellow-600"><?php echo $pending; ?></span>
                </div>
            </div>
        </div>

        <!-- Exams Grid -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            <?php if ($exams && $exams->num_rows > 0): ?>
                <?php while($exam = $exams->fetch_assoc()): 
                    $today = date('Y-m-d');
                    $is_submitted = $exam['submission_id'] ? true : false;
                    
                    // Determine exam status
                    if ($is_submitted) {
                        $status = 'Completed';
                        $status_class = 'status-completed';
                        $status_icon = '✅';
                    } elseif ($exam['start_date'] > $today) {
                        $status = 'Upcoming';
                        $status_class = 'status-upcoming';
                        $status_icon = '⏳';
                    } else {
                        $status = 'Active';
                        $status_class = 'status-active';
                        $status_icon = '🟢';
                    }
                    
                    $can_take = !$is_submitted && $exam['start_date'] <= $today && $exam['end_date'] >= $today;
                    $is_expired = $exam['end_date'] < $today;
                ?>
                    <div class="exam-card bg-white rounded-xl shadow-sm overflow-hidden">
                        <!-- Card Header -->
                        <div class="p-4 border-b">
                            <div class="flex justify-between items-start">
                                <div>
                                    <h3 class="font-bold text-gray-800"><?php echo htmlspecialchars($exam['title']); ?></h3>
                                    <p class="text-sm text-gray-500"><?php echo htmlspecialchars($exam['subject_name']); ?></p>
                                </div>
                                <span class="status-badge <?php echo $status_class; ?>">
                                    <?php echo $status_icon; ?> <?php echo $status; ?>
                                </span>
                            </div>
                        </div>
                        
                        <!-- Card Body -->
                        <div class="p-4 space-y-2 text-sm">
                            <div class="flex justify-between">
                                <span class="text-gray-500">Questions:</span>
                                <span class="font-medium"><?php echo $exam['total_questions'] ?? 0; ?></span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-500">Total Marks:</span>
                                <span class="font-medium"><?php echo $exam['total_marks']; ?></span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-500">Duration:</span>
                                <span class="font-medium"><?php echo $exam['duration_minutes']; ?> minutes</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-500">Start:</span>
                                <span class="font-medium"><?php echo date('M d, Y', strtotime($exam['start_date'])); ?></span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-500">End:</span>
                                <span class="font-medium"><?php echo date('M d, Y', strtotime($exam['end_date'])); ?></span>
                            </div>
                            
                            <?php if($is_submitted && $exam['percentage'] !== null): ?>
                                <div class="mt-2 pt-2 border-t">
                                    <div class="flex justify-between items-center">
                                        <span class="text-gray-500">Score:</span>
                                        <span class="font-bold <?php echo $exam['percentage'] >= 75 ? 'text-green-600' : 'text-red-600'; ?>">
                                            <?php echo round($exam['percentage'], 1); ?>%
                                        </span>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Card Footer -->
                        <div class="p-4 border-t bg-gray-50">
                            <?php if($is_submitted): ?>
                                <a href="results.php?id=<?php echo $exam['id']; ?>" 
                                   class="block text-center bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition">
                                    <i class="fas fa-eye mr-2"></i> View Results
                                </a>
                            <?php elseif($is_expired): ?>
                                <div class="text-center text-gray-400 text-sm py-2">
                                    <i class="fas fa-clock mr-2"></i> Exam Expired
                                </div>
                            <?php elseif($can_take): ?>
                                <!-- ✅ FIX: Correct link to take exam -->
                                <a href="take.php?id=<?php echo $exam['id']; ?>" 
                                   class="block text-center bg-gradient-to-r from-green-500 to-teal-600 text-white px-4 py-2 rounded-lg hover:shadow-lg transition">
                                    <i class="fas fa-play mr-2"></i> Start Exam
                                </a>
                            <?php elseif($exam['start_date'] > $today): ?>
                                <div class="text-center text-blue-600 text-sm py-2">
                                    <i class="fas fa-clock mr-2"></i> Starts <?php echo date('M d', strtotime($exam['start_date'])); ?>
                                </div>
                            <?php else: ?>
                                <div class="text-center text-gray-400 text-sm py-2">
                                    <i class="fas fa-hourglass-end mr-2"></i> Not Available
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="col-span-full">
                    <div class="bg-white rounded-xl shadow-sm p-12 text-center">
                        <i class="fas fa-pen-alt text-6xl text-gray-300 mb-4"></i>
                        <h3 class="text-xl font-semibold text-gray-600">No Exams Available</h3>
                        <p class="text-gray-400 mt-2">Your teacher hasn't assigned any exams to your class yet.</p>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>