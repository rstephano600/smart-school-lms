<?php
require_once '../../config.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';
requireRole('student');

$assignment_id = $_GET['id'] ?? 0;

if (!$assignment_id) {
    header('Location: index.php');
    exit();
}

// Get student ID
$student_query = $conn->prepare("SELECT id FROM students WHERE user_id = ?");
$student_query->bind_param("i", $_SESSION['user_id']);
$student_query->execute();
$student_result = $student_query->get_result();
$student = $student_result->fetch_assoc();
$student_id = $student['id'] ?? 0;

// Get assignment details - FIXED: Correct number of bind parameters
$assignment_query = $conn->prepare("
    SELECT a.*, s.name as subject_name, c.name as class_name,
           (SELECT id FROM submissions WHERE assignment_id = a.id AND student_id = ?) as submission_id,
           (SELECT marks_obtained FROM submissions WHERE assignment_id = a.id AND student_id = ?) as marks_obtained,
           (SELECT feedback FROM submissions WHERE assignment_id = a.id AND student_id = ?) as feedback,
           (SELECT submitted_at FROM submissions WHERE assignment_id = a.id AND student_id = ?) as submitted_at
    FROM assignments a
    JOIN subjects s ON a.subject_id = s.id
    JOIN classes c ON a.class_id = c.id
    WHERE a.id = ? AND a.status = 'published'
");
// 5 question marks (?, ?, ?, ?, ?) - 5 bind variables
$assignment_query->bind_param("iiiii", $student_id, $student_id, $student_id, $student_id, $assignment_id);
$assignment_query->execute();
$assignment = $assignment_query->get_result()->fetch_assoc();

if (!$assignment) {
    header('Location: index.php');
    exit();
}

$is_submitted = $assignment['submission_id'] ? true : false;
$is_graded = $assignment['marks_obtained'] !== null;
$is_late = $is_submitted && strtotime($assignment['submitted_at']) > strtotime($assignment['due_date']);
$is_overdue = !$is_submitted && strtotime($assignment['due_date']) < time();

$page_title = 'Assignment Details - ' . $assignment['title'];
include '../../includes/header.php';
include '../../includes/sidebar.php';
include '../../includes/navbar.php';
?>

<div class="ml-64 mt-16 p-6 bg-gray-50 min-h-screen">
    <div class="max-w-4xl mx-auto">
        <!-- Assignment Header -->
        <div class="bg-white rounded-xl shadow-sm overflow-hidden mb-6">
            <div class="bg-gradient-to-r from-blue-600 to-purple-600 p-6 text-white">
                <h1 class="text-2xl font-bold"><?php echo htmlspecialchars($assignment['title']); ?></h1>
                <p class="text-blue-100 mt-1"><?php echo htmlspecialchars($assignment['subject_name']); ?> | <?php echo htmlspecialchars($assignment['class_name']); ?></p>
                <div class="flex flex-wrap gap-3 mt-4">
                    <div class="bg-white bg-opacity-20 rounded-lg px-3 py-1 text-sm">
                        <i class="fas fa-calendar-alt mr-1"></i> Due: <?php echo date('M d, Y h:i A', strtotime($assignment['due_date'])); ?>
                    </div>
                    <div class="bg-white bg-opacity-20 rounded-lg px-3 py-1 text-sm">
                        <i class="fas fa-star mr-1"></i> Max Marks: <?php echo $assignment['max_marks']; ?>
                    </div>
                    <div class="bg-white bg-opacity-20 rounded-lg px-3 py-1 text-sm">
                        <i class="fas fa-clock mr-1"></i> Type: <?php echo ucfirst($assignment['assignment_type'] ?? 'homework'); ?>
                    </div>
                </div>
            </div>
            
            <div class="p-6">
                <!-- Status Badge -->
                <div class="mb-4">
                    <?php if($is_submitted && $is_graded): ?>
                        <span class="px-3 py-1 rounded-full text-sm bg-green-100 text-green-700">
                            <i class="fas fa-check-circle mr-1"></i> Graded
                        </span>
                    <?php elseif($is_submitted): ?>
                        <span class="px-3 py-1 rounded-full text-sm bg-blue-100 text-blue-700">
                            <i class="fas fa-clock mr-1"></i> Submitted - Awaiting Grading
                        </span>
                    <?php elseif($is_overdue): ?>
                        <span class="px-3 py-1 rounded-full text-sm bg-red-100 text-red-700">
                            <i class="fas fa-exclamation-triangle mr-1"></i> Overdue
                        </span>
                    <?php else: ?>
                        <span class="px-3 py-1 rounded-full text-sm bg-yellow-100 text-yellow-700">
                            <i class="fas fa-hourglass-half mr-1"></i> Pending Submission
                        </span>
                    <?php endif; ?>
                    
                    <?php if($is_late): ?>
                        <span class="ml-2 px-3 py-1 rounded-full text-sm bg-orange-100 text-orange-700">
                            <i class="fas fa-clock mr-1"></i> Late Submission
                        </span>
                    <?php endif; ?>
                </div>

                <!-- Description -->
                <div class="mb-6">
                    <h3 class="text-lg font-semibold text-gray-800 mb-2">📝 Description</h3>
                    <div class="bg-gray-50 p-4 rounded-lg">
                        <?php echo nl2br(htmlspecialchars($assignment['description'] ?: 'No description provided.')); ?>
                    </div>
                </div>

                <!-- Instructions -->
                <?php if($assignment['instructions']): ?>
                <div class="mb-6">
                    <h3 class="text-lg font-semibold text-gray-800 mb-2">📋 Instructions</h3>
                    <div class="bg-blue-50 p-4 rounded-lg">
                        <?php echo nl2br(htmlspecialchars($assignment['instructions'])); ?>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Rubric -->
                <?php if($assignment['rubric']): ?>
                <div class="mb-6">
                    <h3 class="text-lg font-semibold text-gray-800 mb-2">📊 Marking Rubric</h3>
                    <div class="bg-yellow-50 p-4 rounded-lg">
                        <?php echo nl2br(htmlspecialchars($assignment['rubric'])); ?>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Attachment -->
                <?php if($assignment['attachment_url']): ?>
                <div class="mb-6">
                    <h3 class="text-lg font-semibold text-gray-800 mb-2">📎 Reference Materials</h3>
                    <a href="../../<?php echo $assignment['attachment_url']; ?>" target="_blank" 
                       class="inline-flex items-center px-4 py-2 bg-gray-100 rounded-lg hover:bg-gray-200">
                        <i class="fas fa-download mr-2"></i> Download Attachment
                    </a>
                </div>
                <?php endif; ?>

                <!-- Results if graded -->
                <?php if($is_submitted && $is_graded): ?>
                <div class="mb-6 p-4 bg-green-50 rounded-lg border border-green-200">
                    <h3 class="text-lg font-semibold text-green-800 mb-3">📊 Your Results</h3>
                    <div class="grid grid-cols-2 md:grid-cols-3 gap-4">
                        <div>
                            <p class="text-sm text-gray-600">Marks Obtained</p>
                            <p class="text-2xl font-bold text-green-600"><?php echo $assignment['marks_obtained']; ?> / <?php echo $assignment['max_marks']; ?></p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600">Percentage</p>
                            <p class="text-2xl font-bold text-blue-600"><?php echo round(($assignment['marks_obtained'] / $assignment['max_marks']) * 100, 1); ?>%</p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600">Submitted On</p>
                            <p class="text-md font-semibold"><?php echo date('M d, Y h:i A', strtotime($assignment['submitted_at'])); ?></p>
                        </div>
                    </div>
                    <?php if($assignment['feedback']): ?>
                        <div class="mt-3 p-3 bg-white rounded-lg">
                            <p class="text-sm font-medium text-gray-700">Teacher's Feedback:</p>
                            <p class="text-sm text-gray-600"><?php echo nl2br(htmlspecialchars($assignment['feedback'])); ?></p>
                        </div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>

                <!-- Action Buttons -->
                <div class="flex justify-end space-x-3 mt-6 pt-4 border-t">
                    <a href="index.php" class="px-4 py-2 border rounded-lg hover:bg-gray-50">
                        <i class="fas fa-arrow-left mr-2"></i> Back
                    </a>
                    <?php if(!$is_submitted && !$is_overdue): ?>
                        <a href="submit.php?id=<?php echo $assignment_id; ?>" 
                           class="px-6 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700">
                            <i class="fas fa-upload mr-2"></i> Submit Assignment
                        </a>
                    <?php elseif($is_submitted && !$is_graded): ?>
                        <button disabled class="px-6 py-2 bg-gray-400 text-white rounded-lg cursor-not-allowed">
                            <i class="fas fa-clock mr-2"></i> Awaiting Grading
                        </button>
                    <?php elseif($is_overdue && !$is_submitted): ?>
                        <button disabled class="px-6 py-2 bg-red-400 text-white rounded-lg cursor-not-allowed">
                            <i class="fas fa-lock mr-2"></i> Submission Closed
                        </button>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>