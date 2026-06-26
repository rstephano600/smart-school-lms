<?php
require_once '../../config.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';
requireRole('teacher');

$submission_id = $_GET['id'] ?? 0;
$assignment_id = $_GET['assignment_id'] ?? 0;

if (!$submission_id) {
    header('Location: index.php');
    exit();
}

// Get submission details
$sub_query = $conn->prepare("
    SELECT s.*, a.title as assignment_title, a.max_marks, a.rubric,
           CONCAT(u.first_name, ' ', u.last_name) as student_name,
           st.admission_number
    FROM submissions s
    JOIN assignments a ON s.assignment_id = a.id
    JOIN students st ON s.student_id = st.id
    JOIN users u ON st.user_id = u.id
    WHERE s.id = ? AND a.created_by = ?
");
$sub_query->bind_param("ii", $submission_id, $_SESSION['user_id']);
$sub_query->execute();
$submission = $sub_query->get_result()->fetch_assoc();

if (!$submission) {
    header('Location: index.php');
    exit();
}

$page_title = 'Grade Submission - ' . $submission['assignment_title'];
include '../../includes/header.php';
include '../../includes/sidebar.php';
include '../../includes/navbar.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $marks = floatval($_POST['marks']);
    $feedback = sanitizeInput($_POST['feedback']);
    $teacher_notes = sanitizeInput($_POST['teacher_notes']);
    
    if ($marks > $submission['max_marks']) {
        $error = "Marks cannot exceed maximum marks (" . $submission['max_marks'] . ")";
    } else {
        $percentage = ($marks / $submission['max_marks']) * 100;
        
        // Calculate grade
        if ($percentage >= 80) $grade = 'A';
        elseif ($percentage >= 70) $grade = 'B';
        elseif ($percentage >= 60) $grade = 'C';
        elseif ($percentage >= 50) $grade = 'D';
        elseif ($percentage >= 40) $grade = 'E';
        else $grade = 'F';
        
        $update = $conn->prepare("
            UPDATE submissions 
            SET marks_obtained = ?, feedback = ?, teacher_notes = ?, graded_by = ?, graded_at = NOW() 
            WHERE id = ?
        ");
        $update->bind_param("dssii", $marks, $feedback, $teacher_notes, $_SESSION['user_id'], $submission_id);
        
        if ($update->execute()) {
            logActivity($_SESSION['user_id'], 'graded submission', 'submission', $submission_id);
            $success = "Grade submitted successfully!";
            
            // Create notification for student
            $notify = $conn->prepare("
                INSERT INTO notifications (user_id, title, message, type, link) 
                SELECT user_id, 'Assignment Graded', CONCAT('Your submission for \"', ?, '\" has been graded. You scored ', ?, '/', ?, ' (Grade: ', ?, ')'), 'assignment', ?
                FROM students s
                JOIN users u ON s.user_id = u.id
                WHERE s.id = ?
            ");
            $link = "/smart-school-lms/student/assignments/view.php?id=" . $assignment_id;
            $notify->bind_param("sdiiss", $submission['assignment_title'], $marks, $submission['max_marks'], $grade, $link, $submission['student_id']);
            $notify->execute();
            
            // Refresh submission data
            $sub_query->execute();
            $submission = $sub_query->get_result()->fetch_assoc();
        } else {
            $error = "Failed to save grade: " . $conn->error;
        }
    }
}
?>

<div class="ml-64 mt-16 p-6">
    <div class="max-w-4xl mx-auto">
        <div class="mb-6">
            <h1 class="text-2xl font-bold text-gray-800">Grade Submission</h1>
            <p class="text-gray-500 mt-1"><?php echo htmlspecialchars($submission['assignment_title']); ?></p>
        </div>

        <?php if($error): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg mb-4"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <?php if($success): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-lg mb-4"><?php echo $success; ?></div>
        <?php endif; ?>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Left Column - Student Info & Submission -->
            <div class="lg:col-span-2 space-y-6">
                <!-- Student Info Card -->
                <div class="bg-white rounded-xl shadow-sm p-6">
                    <h3 class="text-lg font-semibold mb-4 flex items-center">
                        <i class="fas fa-user-graduate text-blue-500 mr-2"></i> Student Information
                    </h3>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <p class="text-sm text-gray-500">Student Name</p>
                            <p class="font-semibold"><?php echo htmlspecialchars($submission['student_name']); ?></p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-500">Admission Number</p>
                            <p class="font-semibold"><?php echo $submission['admission_number']; ?></p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-500">Submitted On</p>
                            <p class="font-semibold"><?php echo date('M d, Y h:i A', strtotime($submission['submitted_at'])); ?></p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-500">Maximum Marks</p>
                            <p class="font-semibold"><?php echo $submission['max_marks']; ?></p>
                        </div>
                    </div>
                </div>

                <!-- Submission Content -->
                <?php if($submission['submission_text']): ?>
                <div class="bg-white rounded-xl shadow-sm p-6">
                    <h3 class="text-lg font-semibold mb-3 flex items-center">
                        <i class="fas fa-file-alt text-green-500 mr-2"></i> Student's Submission
                    </h3>
                    <div class="bg-gray-50 p-4 rounded-lg">
                        <?php echo nl2br(htmlspecialchars($submission['submission_text'])); ?>
                    </div>
                </div>
                <?php endif; ?>

                <?php if($submission['attachment_url']): ?>
                <div class="bg-white rounded-xl shadow-sm p-6">
                    <h3 class="text-lg font-semibold mb-3 flex items-center">
                        <i class="fas fa-paperclip text-purple-500 mr-2"></i> Attached File
                    </h3>
                    <a href="../../<?php echo $submission['attachment_url']; ?>" target="_blank" 
                       class="inline-flex items-center px-4 py-2 bg-blue-50 text-blue-600 rounded-lg hover:bg-blue-100">
                        <i class="fas fa-download mr-2"></i> Download Attachment
                    </a>
                </div>
                <?php endif; ?>

                <!-- Rubric -->
                <?php if($submission['rubric']): ?>
                <div class="bg-white rounded-xl shadow-sm p-6">
                    <h3 class="text-lg font-semibold mb-3 flex items-center">
                        <i class="fas fa-list-check text-yellow-500 mr-2"></i> Marking Rubric
                    </h3>
                    <div class="bg-gray-50 p-4 rounded-lg text-sm">
                        <?php echo nl2br(htmlspecialchars($submission['rubric'])); ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <!-- Right Column - Grading Form -->
            <div class="lg:col-span-1">
                <div class="bg-white rounded-xl shadow-sm p-6 sticky top-20">
                    <h3 class="text-lg font-semibold mb-4 flex items-center">
                        <i class="fas fa-star text-yellow-500 mr-2"></i> Grade Assignment
                    </h3>
                    
                    <?php if($submission['marks_obtained'] !== null): ?>
                        <div class="mb-4 p-3 bg-green-50 rounded-lg">
                            <p class="text-sm text-green-600">Currently Graded</p>
                            <p class="text-2xl font-bold text-green-700"><?php echo $submission['marks_obtained']; ?> / <?php echo $submission['max_marks']; ?></p>
                        </div>
                    <?php endif; ?>

                    <form method="POST">
                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Marks Obtained *</label>
                            <input type="number" name="marks" required 
                                   value="<?php echo htmlspecialchars($submission['marks_obtained'] ?? ''); ?>"
                                   step="0.5" min="0" max="<?php echo $submission['max_marks']; ?>"
                                   class="w-full border rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500">
                            <div class="flex justify-between text-xs text-gray-500 mt-1">
                                <span>Min: 0</span>
                                <span>Max: <?php echo $submission['max_marks']; ?></span>
                            </div>
                        </div>

                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Feedback for Student</label>
                            <textarea name="feedback" rows="4" 
                                      placeholder="Provide constructive feedback..."
                                      class="w-full border rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500"><?php echo htmlspecialchars($submission['feedback'] ?? ''); ?></textarea>
                        </div>

                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Private Teacher Notes</label>
                            <textarea name="teacher_notes" rows="3" 
                                      placeholder="Internal notes (only visible to you)..."
                                      class="w-full border rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500"><?php echo htmlspecialchars($submission['teacher_notes'] ?? ''); ?></textarea>
                            <p class="text-xs text-gray-500 mt-1">These notes are only visible to you</p>
                        </div>

                        <div class="flex space-x-3 mt-6 pt-4 border-t">
                            <a href="submissions.php?id=<?php echo $assignment_id; ?>" class="flex-1 text-center px-4 py-2 border rounded-lg hover:bg-gray-50">Cancel</a>
                            <button type="submit" class="flex-1 px-6 py-2 bg-gradient-to-r from-blue-500 to-purple-600 text-white rounded-lg hover:shadow-lg transition-all">
                                <i class="fas fa-save mr-2"></i> Save Grade
                            </button>
                        </div>
                    </form>

                    <!-- Grade Preview -->
                    <div class="mt-4 p-3 bg-gray-50 rounded-lg">
                        <p class="text-sm text-gray-600 mb-2">Grade Preview:</p>
                        <div class="flex justify-between items-center">
                            <span class="text-sm">Percentage:</span>
                            <span class="font-semibold" id="previewPercentage">0%</span>
                        </div>
                        <div class="flex justify-between items-center mt-1">
                            <span class="text-sm">Grade:</span>
                            <span class="font-bold text-lg" id="previewGrade">-</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Live grade preview
const marksInput = document.querySelector('input[name="marks"]');
const maxMarks = <?php echo $submission['max_marks']; ?>;
const previewPercentage = document.getElementById('previewPercentage');
const previewGrade = document.getElementById('previewGrade');

function updatePreview() {
    let marks = parseFloat(marksInput.value) || 0;
    if (marks > maxMarks) {
        marks = maxMarks;
        marksInput.value = maxMarks;
    }
    const percentage = (marks / maxMarks) * 100;
    previewPercentage.textContent = percentage.toFixed(1) + '%';
    
    let grade = '';
    if (percentage >= 80) grade = 'A';
    else if (percentage >= 70) grade = 'B';
    else if (percentage >= 60) grade = 'C';
    else if (percentage >= 50) grade = 'D';
    else if (percentage >= 40) grade = 'E';
    else grade = 'F';
    
    previewGrade.textContent = grade;
    
    // Color based on grade
    if (grade === 'A') previewGrade.className = 'font-bold text-lg text-green-600';
    else if (grade === 'B') previewGrade.className = 'font-bold text-lg text-blue-600';
    else if (grade === 'C') previewGrade.className = 'font-bold text-lg text-yellow-600';
    else previewGrade.className = 'font-bold text-lg text-red-600';
}

marksInput.addEventListener('input', updatePreview);
updatePreview();
</script>

<?php include '../../includes/footer.php'; ?>