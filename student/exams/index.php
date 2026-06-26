<?php
require_once '../../config.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';
requireRole('student');

$page_title = 'Online Exams & Quizzes';
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
$student_id = $student['id'];
$class_id = $student['class_id'];

// Get all published exams for this student's class
$exams = $conn->prepare("
    SELECT te.*, s.name as subject_name,
           (SELECT COUNT(*) FROM exam_questions WHERE exam_id = te.id) as total_questions,
           (SELECT COUNT(*) FROM exam_submissions WHERE exam_id = te.id AND student_id = ?) as has_submitted,
           (SELECT grade FROM exam_submissions WHERE exam_id = te.id AND student_id = ? ORDER BY id DESC LIMIT 1) as grade,
           (SELECT percentage FROM exam_submissions WHERE exam_id = te.id AND student_id = ? ORDER BY id DESC LIMIT 1) as percentage
    FROM teacher_exams te
    JOIN subjects s ON te.subject_id = s.id
    WHERE te.class_id = ? AND te.is_published = 1
    ORDER BY te.start_date ASC
");
$exams->bind_param("iiii", $student_id, $student_id, $student_id, $class_id);
$exams->execute();
$exams = $exams->get_result();
?>

<div class="ml-64 mt-16 p-6 bg-gray-50 min-h-screen">
    <div class="max-w-full mx-auto">
        <div class="mb-6">
            <h1 class="text-2xl font-bold text-gray-800">Online Exams & Quizzes</h1>
            <p class="text-gray-500 mt-1">Take your online examinations and quizzes</p>
        </div>

        <!-- Categories Tabs -->
        <div class="flex flex-wrap gap-2 mb-6">
            <button class="tab-btn active px-4 py-2 bg-blue-600 text-white rounded-lg" data-type="all">All Exams</button>
            <button class="tab-btn px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300" data-type="upcoming">Upcoming</button>
            <button class="tab-btn px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300" data-type="ongoing">Ongoing</button>
            <button class="tab-btn px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300" data-type="completed">Completed</button>
        </div>

        <!-- Exams Grid -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            <?php if ($exams && $exams->num_rows > 0): ?>
                <?php while($exam = $exams->fetch_assoc()): 
                    $today = date('Y-m-d');
                    $can_take = false;
                    $status_text = '';
                    $status_color = '';
                    $status_icon = '';
                    
                    // Check if already submitted
                    $has_submitted = $exam['has_submitted'] > 0;
                    
                    // Determine exam status
                    if ($exam['end_date'] < $today) {
                        $status_text = 'Closed';
                        $status_color = 'bg-gray-100 text-gray-700';
                        $status_icon = 'fa-lock';
                        $can_take = false;
                    } elseif ($exam['start_date'] > $today) {
                        $status_text = 'Upcoming';
                        $status_color = 'bg-blue-100 text-blue-700';
                        $status_icon = 'fa-clock';
                        $can_take = false;
                    } elseif ($has_submitted) {
                        $status_text = 'Completed';
                        $status_color = 'bg-purple-100 text-purple-700';
                        $status_icon = 'fa-check-circle';
                        $can_take = false;
                    } else {
                        $status_text = 'Available';
                        $status_color = 'bg-green-100 text-green-700';
                        $status_icon = 'fa-play-circle';
                        $can_take = true;
                    }
                ?>
                    <div class="exam-card bg-white rounded-xl shadow-sm hover:shadow-lg transition-all duration-300 overflow-hidden" data-status="<?php echo strtolower($status_text); ?>">
                        <div class="h-2 <?php echo $exam['exam_type'] == 'quiz' ? 'bg-purple-500' : 'bg-red-500'; ?>"></div>
                        <div class="p-5">
                            <div class="flex justify-between items-start">
                                <div>
                                    <span class="px-2 py-1 text-xs rounded-full <?php echo $status_color; ?>">
                                        <i class="fas <?php echo $status_icon; ?> mr-1"></i> <?php echo $status_text; ?>
                                    </span>
                                    <span class="ml-2 px-2 py-1 text-xs rounded-full bg-gray-100 text-gray-600">
                                        <?php echo ucfirst($exam['exam_type']); ?>
                                    </span>
                                </div>
                                <div class="text-right">
                                    <p class="text-xs text-gray-400">Duration</p>
                                    <p class="text-sm font-semibold"><?php echo $exam['duration_minutes']; ?> min</p>
                                </div>
                            </div>
                            
                            <h3 class="text-lg font-bold text-gray-800 mt-3"><?php echo htmlspecialchars($exam['title']); ?></h3>
                            <p class="text-sm text-gray-500 mt-1"><?php echo htmlspecialchars($exam['subject_name']); ?></p>
                            
                            <div class="mt-3 space-y-2">
                                <div class="flex justify-between text-sm">
                                    <span class="text-gray-500">Questions:</span>
                                    <span class="font-semibold"><?php echo $exam['total_questions'] ?? 0; ?></span>
                                </div>
                                <div class="flex justify-between text-sm">
                                    <span class="text-gray-500">Total Marks:</span>
                                    <span class="font-semibold"><?php echo $exam['total_marks']; ?></span>
                                </div>
                                <div class="flex justify-between text-sm">
                                    <span class="text-gray-500">Date:</span>
                                    <span class="font-semibold <?php echo $exam['end_date'] < $today ? 'text-red-600' : 'text-gray-600'; ?>">
                                        <?php echo date('M d, Y', strtotime($exam['start_date'])); ?>
                                    </span>
                                </div>
                            </div>
                            
                            <div class="mt-4">
                                <?php if ($can_take): ?>
                                    <a href="instructions.php?id=<?php echo $exam['id']; ?>" 
                                       class="block text-center bg-gradient-to-r from-blue-500 to-purple-600 text-white py-2 rounded-lg hover:shadow-lg transition-all">
                                        <i class="fas fa-play mr-2"></i> Start Exam
                                    </a>
                                <?php elseif ($has_submitted): ?>
                                    <a href="results.php?id=<?php echo $exam['id']; ?>" 
                                       class="block text-center bg-purple-600 text-white py-2 rounded-lg hover:bg-purple-700 transition-all">
                                        <i class="fas fa-chart-line mr-2"></i> View Results (<?php echo $exam['grade']; ?> - <?php echo round($exam['percentage'], 1); ?>%)
                                    </a>
                                <?php else: ?>
                                    <button disabled class="block text-center bg-gray-300 text-gray-500 py-2 rounded-lg cursor-not-allowed">
                                        <i class="fas fa-lock mr-2"></i> <?php echo $status_text == 'Upcoming' ? 'Coming Soon' : 'Not Available'; ?>
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="col-span-full">
                    <div class="bg-white rounded-xl shadow-sm p-12 text-center">
                        <i class="fas fa-pen-alt text-6xl text-gray-300 mb-4"></i>
                        <h3 class="text-xl font-semibold text-gray-600">No Exams Available</h3>
                        <p class="text-gray-400 mt-2">There are no exams or quizzes assigned to you at the moment</p>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
// Tab filtering
document.querySelectorAll('.tab-btn').forEach(btn => {
    btn.addEventListener('click', function() {
        const type = this.dataset.type;
        
        // Update active button style
        document.querySelectorAll('.tab-btn').forEach(b => {
            b.classList.remove('active', 'bg-blue-600', 'text-white');
            b.classList.add('bg-gray-200', 'text-gray-700');
        });
        this.classList.add('active', 'bg-blue-600', 'text-white');
        this.classList.remove('bg-gray-200', 'text-gray-700');
        
        // Filter exams
        document.querySelectorAll('.exam-card').forEach(card => {
            const status = card.dataset.status;
            if (type === 'all' || status === type) {
                card.style.display = 'block';
            } else {
                card.style.display = 'none';
            }
        });
    });
});
</script>

<?php include '../../includes/footer.php'; ?>