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

// Get assignment details
$assignment_query = $conn->prepare("
    SELECT a.*, s.name as subject_name
    FROM assignments a
    JOIN subjects s ON a.subject_id = s.id
    WHERE a.id = ? AND a.status = 'published'
");
$assignment_query->bind_param("i", $assignment_id);
$assignment_query->execute();
$assignment = $assignment_query->get_result()->fetch_assoc();

if (!$assignment) {
    header('Location: index.php');
    exit();
}

// Check if already submitted
$check_submit = $conn->prepare("SELECT id FROM submissions WHERE assignment_id = ? AND student_id = ?");
$check_submit->bind_param("ii", $assignment_id, $student_id);
$check_submit->execute();
if ($check_submit->get_result()->num_rows > 0) {
    header('Location: view.php?id=' . $assignment_id . '&error=already_submitted');
    exit();
}

// Check if overdue
if (strtotime($assignment['due_date']) < time() && !$assignment['allow_late_submission']) {
    header('Location: view.php?id=' . $assignment_id . '&error=submission_closed');
    exit();
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $submission_text = sanitizeInput($_POST['submission_text']);
    $is_late = strtotime($assignment['due_date']) < time() ? 1 : 0;
    
    $attachment_url = null;
    
    // Handle file upload
    if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] == 0) {
        // Get allowed file types from assignment or use defaults
        $allowed_types = ['pdf', 'docx', 'jpg', 'png', 'jpeg', 'txt', 'zip'];
        if (!empty($assignment['allowed_file_types'])) {
            $allowed_types = explode(',', $assignment['allowed_file_types']);
            $allowed_types = array_map('trim', $allowed_types);
        }
        
        // Define upload path
        $upload_path = '../../uploads/submissions/';
        
        $upload_result = uploadFile($_FILES['attachment'], $upload_path, $allowed_types);
        
        if ($upload_result['success']) {
            $attachment_url = 'uploads/submissions/' . $upload_result['filename'];
        } else {
            $error = $upload_result['error'];
        }
    }
    
    if (empty($error)) {
        $insert = $conn->prepare("
            INSERT INTO submissions (assignment_id, student_id, submission_text, attachment_url, submitted_at, is_late)
            VALUES (?, ?, ?, ?, NOW(), ?)
        ");
        $insert->bind_param("iissi", $assignment_id, $student_id, $submission_text, $attachment_url, $is_late);
        
        if ($insert->execute()) {
            // Create notification for teacher
            $teacher_query = $conn->prepare("
                INSERT INTO notifications (user_id, title, message, type, link)
                SELECT created_by, 'New Submission', CONCAT('New submission for \"', ?, '\" from student'), 'assignment', ?
                FROM assignments WHERE id = ?
            ");
            $notification_title = $assignment['title'];
            $notification_link = "/smart-school-lms/teacher/assignments/submissions.php?id=" . $assignment_id;
            $teacher_query->bind_param("ssi", $notification_title, $notification_link, $assignment_id);
            $teacher_query->execute();
            
            header("Location: view.php?id=$assignment_id&submitted=1");
            exit();
        } else {
            $error = "Failed to submit assignment: " . $conn->error;
        }
    }
}

$page_title = 'Submit Assignment - ' . $assignment['title'];
include '../../includes/header.php';
include '../../includes/sidebar.php';
include '../../includes/navbar.php';
?>

<div class="ml-64 mt-16 p-6 bg-gray-50 min-h-screen">
    <div class="max-w-3xl mx-auto">
        <div class="mb-6">
            <h1 class="text-2xl font-bold text-gray-800">Submit Assignment</h1>
            <p class="text-gray-500 mt-1"><?php echo htmlspecialchars($assignment['title']); ?> - <?php echo htmlspecialchars($assignment['subject_name']); ?></p>
        </div>

        <?php if($error): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg mb-4">
                <i class="fas fa-exclamation-circle mr-2"></i> <?php echo $error; ?>
            </div>
        <?php endif; ?>

        <?php if(isset($_GET['error'])): ?>
            <?php if($_GET['error'] == 'already_submitted'): ?>
                <div class="bg-yellow-100 border border-yellow-400 text-yellow-700 px-4 py-3 rounded-lg mb-4">
                    <i class="fas fa-info-circle mr-2"></i> You have already submitted this assignment.
                </div>
            <?php elseif($_GET['error'] == 'submission_closed'): ?>
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg mb-4">
                    <i class="fas fa-lock mr-2"></i> Submission deadline has passed.
                </div>
            <?php endif; ?>
        <?php endif; ?>

        <div class="bg-white rounded-xl shadow-sm p-6">
            <form method="POST" enctype="multipart/form-data">
                <!-- Assignment Info -->
                <div class="mb-6 p-4 bg-gray-50 rounded-lg">
                    <div class="grid grid-cols-2 gap-4 text-sm">
                        <div>
                            <span class="text-gray-500">Due Date:</span>
                            <span class="font-semibold"><?php echo date('M d, Y h:i A', strtotime($assignment['due_date'])); ?></span>
                        </div>
                        <div>
                            <span class="text-gray-500">Max Marks:</span>
                            <span class="font-semibold"><?php echo $assignment['max_marks']; ?></span>
                        </div>
                        <div class="col-span-2">
                            <span class="text-gray-500">Instructions:</span>
                            <p class="text-sm mt-1"><?php echo nl2br(htmlspecialchars($assignment['instructions'] ?: 'Follow the guidelines above.')); ?></p>
                        </div>
                    </div>
                </div>

                <!-- Submission Text -->
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Your Answer / Submission Text</label>
                    <textarea name="submission_text" rows="8" 
                              class="w-full border rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500"
                              placeholder="Type your answer here..."></textarea>
                </div>

                <!-- File Upload -->
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Upload File (Optional)</label>
                    <input type="file" name="attachment" class="w-full border rounded-lg px-3 py-2">
                    <p class="text-xs text-gray-500 mt-1">
                        Allowed file types: 
                        <?php 
                        $allowed = !empty($assignment['allowed_file_types']) ? $assignment['allowed_file_types'] : 'pdf, docx, jpg, png, txt, zip';
                        echo strtoupper($allowed); 
                        ?> 
                        (Max <?php echo $assignment['max_file_size'] ?? 10; ?>MB)
                    </p>
                </div>

                <div class="flex justify-end space-x-3 mt-6 pt-4 border-t">
                    <a href="view.php?id=<?php echo $assignment_id; ?>" class="px-4 py-2 border rounded-lg hover:bg-gray-50">Cancel</a>
                    <button type="submit" class="px-6 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700">
                        <i class="fas fa-paper-plane mr-2"></i> Submit Assignment
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>