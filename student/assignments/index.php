<?php
require_once '../../config.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';
requireRole('student');

$page_title = 'My Assignments';
include '../../includes/header.php';
include '../../includes/sidebar.php';
include '../../includes/navbar.php';

// Get student ID
$student_query = $conn->prepare("SELECT id, class_id FROM students WHERE user_id = ?");
$student_query->bind_param("i", $_SESSION['user_id']);
$student_query->execute();
$student = $student_query->get_result()->fetch_assoc();
$student_id = $student['id'];
$class_id = $student['class_id'];

// Get assignments for this class with submission status
$assignments = $conn->prepare("
    SELECT a.*, s.name as subject_name,
           (SELECT id FROM submissions WHERE assignment_id = a.id AND student_id = ?) as submitted,
           (SELECT marks_obtained FROM submissions WHERE assignment_id = a.id AND student_id = ?) as marks,
           (SELECT submitted_at FROM submissions WHERE assignment_id = a.id AND student_id = ?) as submitted_at
    FROM assignments a
    JOIN subjects s ON a.subject_id = s.id
    WHERE a.class_id = ? AND a.status = 'published'
    ORDER BY a.due_date ASC
");
$assignments->bind_param("iiii", $student_id, $student_id, $student_id, $class_id);
$assignments->execute();
$assignments = $assignments->get_result();
?>

<div class="ml-64 mt-16 p-6">
    <div class="max-w-full mx-auto">
        <div class="mb-6">
            <h1 class="text-2xl font-bold text-gray-800">My Assignments</h1>
            <p class="text-gray-500 mt-1">View and submit your assignments</p>
        </div>

        <!-- Assignments List -->
        <div class="space-y-4">
            <?php if ($assignments && $assignments->num_rows > 0): ?>
                <?php while($ass = $assignments->fetch_assoc()): 
                    $is_submitted = $ass['submitted'] ? true : false;
                    $is_late = $is_submitted && strtotime($ass['submitted_at']) > strtotime($ass['due_date']);
                    $is_overdue = !$is_submitted && strtotime($ass['due_date']) < time();
                    $is_graded = $ass['marks'] !== null;
                    
                    if ($is_submitted && $is_graded) {
                        $status = 'Graded';
                        $status_color = 'bg-green-100 text-green-700';
                        $status_icon = 'fa-check-circle';
                    } elseif ($is_submitted) {
                        $status = 'Submitted';
                        $status_color = 'bg-blue-100 text-blue-700';
                        $status_icon = 'fa-clock';
                    } elseif ($is_overdue) {
                        $status = 'Overdue';
                        $status_color = 'bg-red-100 text-red-700';
                        $status_icon = 'fa-exclamation-triangle';
                    } else {
                        $status = 'Pending';
                        $status_color = 'bg-yellow-100 text-yellow-700';
                        $status_icon = 'fa-hourglass-half';
                    }
                ?>
                    <div class="bg-white rounded-xl shadow-sm hover:shadow-md transition-all overflow-hidden">
                        <div class="p-5">
                            <div class="flex flex-wrap justify-between items-start gap-3">
                                <div class="flex-1">
                                    <div class="flex items-center space-x-2 mb-2">
                                        <span class="px-2 py-1 text-xs rounded-full <?php echo $status_color; ?>">
                                            <i class="fas <?php echo $status_icon; ?> mr-1"></i> <?php echo $status; ?>
                                        </span>
                                        <?php if($is_late): ?>
                                            <span class="px-2 py-1 text-xs rounded-full bg-red-100 text-red-700">
                                                <i class="fas fa-clock mr-1"></i> Late Submission
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                    <h3 class="text-lg font-semibold text-gray-800"><?php echo htmlspecialchars($ass['title']); ?></h3>
                                    <p class="text-sm text-gray-500"><?php echo htmlspecialchars($ass['subject_name']); ?></p>
                                    <?php if($ass['description']): ?>
                                        <p class="text-sm text-gray-600 mt-2"><?php echo htmlspecialchars(substr($ass['description'], 0, 150)); ?></p>
                                    <?php endif; ?>
                                </div>
                                <div class="text-right">
                                    <p class="text-sm font-medium <?php echo $is_overdue ? 'text-red-600' : 'text-gray-600'; ?>">
                                        <i class="fas fa-calendar-alt mr-1"></i> Due: <?php echo date('M d, Y', strtotime($ass['due_date'])); ?>
                                    </p>
                                    <?php if($is_graded): ?>
                                        <p class="text-lg font-bold text-green-600 mt-1">
                                            Score: <?php echo $ass['marks']; ?> / <?php echo $ass['max_marks']; ?>
                                        </p>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="mt-4 flex justify-end space-x-3">
                                <a href="view.php?id=<?php echo $ass['id']; ?>" class="text-blue-600 hover:text-blue-800">
                                    <i class="fas fa-eye mr-1"></i> View Details
                                </a>
                                <?php if(!$is_submitted && !$is_overdue): ?>
                                    <a href="submit.php?id=<?php echo $ass['id']; ?>" class="bg-green-600 text-white px-4 py-2 rounded-lg text-sm hover:bg-green-700">
                                        <i class="fas fa-upload mr-1"></i> Submit Assignment
                                    </a>
                                <?php elseif($is_submitted && !$is_graded): ?>
                                    <span class="text-yellow-600 text-sm">
                                        <i class="fas fa-clock mr-1"></i> Awaiting Grading
                                    </span>
                                <?php elseif($is_graded): ?>
                                    <a href="feedback.php?id=<?php echo $ass['id']; ?>" class="text-purple-600 hover:text-purple-800">
                                        <i class="fas fa-comment-dots mr-1"></i> View Feedback
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="bg-white rounded-xl shadow-sm p-12 text-center">
                    <i class="fas fa-tasks text-6xl text-gray-300 mb-4"></i>
                    <h3 class="text-xl font-semibold text-gray-600">No Assignments Yet</h3>
                    <p class="text-gray-400 mt-2">Check back later for new assignments</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>