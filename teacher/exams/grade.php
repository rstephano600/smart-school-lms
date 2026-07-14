<?php
require_once '../../config.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';
requireRole('teacher');

$submission_id = $_GET['id'] ?? 0;
$exam_id = $_GET['exam_id'] ?? 0;

if (!$submission_id) {
    header('Location: index.php');
    exit();
}

// Get submission details
$sub_query = $conn->prepare("
    SELECT es.*, 
           CONCAT(u.first_name, ' ', u.last_name) as student_name,
           s.admission_number,
           s.id as student_id
    FROM exam_submissions es
    JOIN students s ON es.student_id = s.id
    JOIN users u ON s.user_id = u.id
    WHERE es.id = ?
");
$sub_query->bind_param("i", $submission_id);
$sub_query->execute();
$submission = $sub_query->get_result()->fetch_assoc();

if (!$submission) {
    header('Location: index.php');
    exit();
}

// Get exam details
$exam_query = $conn->prepare("
    SELECT te.*, sub.name as subject_name
    FROM teacher_exams te
    JOIN subjects sub ON te.subject_id = sub.id
    WHERE te.id = ?
");
$exam_query->bind_param("i", $submission['exam_id']);
$exam_query->execute();
$exam = $exam_query->get_result()->fetch_assoc();

// Get student's class to determine level (O-Level or A-Level)
$class_query = $conn->prepare("
    SELECT c.name, 
           CASE 
               WHEN c.name LIKE 'Form 5%' OR c.name LIKE 'Form 6%' OR c.name LIKE 'F5%' OR c.name LIKE 'F6%' THEN 'alevel'
               ELSE 'olevel'
           END as level
    FROM students s
    JOIN classes c ON s.class_id = c.id
    WHERE s.id = ?
");
$class_query->bind_param("i", $submission['student_id']);
$class_query->execute();
$class_info = $class_query->get_result()->fetch_assoc();
$student_level = $class_info['level'] ?? 'olevel';

// Get questions
$questions = $conn->prepare("
    SELECT * FROM exam_questions WHERE exam_id = ? ORDER BY order_number
");
$questions->bind_param("i", $submission['exam_id']);
$questions->execute();
$questions = $questions->get_result();

$answers = json_decode($submission['answers'], true);
$page_title = 'Grade Exam - ' . $submission['student_name'];
include '../../includes/header.php';
include '../../includes/sidebar.php';
include '../../includes/navbar.php';

$error = '';
$success = '';

// Function to get grade based on level
function calculateGrade($percentage, $level) {
    if ($level == 'olevel') {
        // Form 1-4 (O-Level)
        if ($percentage >= 75) {
            return ['grade' => 'A', 'points' => 5, 'division' => 'First Division (Distinction)', 'status' => 'Excellent'];
        } elseif ($percentage >= 65) {
            return ['grade' => 'B', 'points' => 4, 'division' => 'Second Division', 'status' => 'Very Good'];
        } elseif ($percentage >= 45) {
            return ['grade' => 'C', 'points' => 3, 'division' => 'Third Division', 'status' => 'Good'];
        } elseif ($percentage >= 30) {
            return ['grade' => 'D', 'points' => 2, 'division' => 'Pass', 'status' => 'Satisfactory'];
        } else {
            return ['grade' => 'F', 'points' => 0, 'division' => 'Fail', 'status' => 'Needs Improvement'];
        }
    } else {
        // Form 5-6 (A-Level)
        if ($percentage >= 80) {
            return ['grade' => 'A', 'points' => 5, 'division' => 'First Division (Excellent)', 'status' => 'Excellent'];
        } elseif ($percentage >= 70) {
            return ['grade' => 'B', 'points' => 4, 'division' => 'Second Division', 'status' => 'Very Good'];
        } elseif ($percentage >= 60) {
            return ['grade' => 'C', 'points' => 3, 'division' => 'Third Division', 'status' => 'Good'];
        } elseif ($percentage >= 50) {
            return ['grade' => 'D', 'points' => 2, 'division' => 'Pass', 'status' => 'Satisfactory'];
        } elseif ($percentage >= 40) {
            return ['grade' => 'E', 'points' => 1, 'division' => 'Subsidiary', 'status' => 'Average'];
        } elseif ($percentage >= 35) {
            return ['grade' => 'S', 'points' => 0.5, 'division' => 'Satisfactory', 'status' => 'Satisfactory'];
        } else {
            return ['grade' => 'F', 'points' => 0, 'division' => 'Fail', 'status' => 'Fail'];
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $scores = $_POST['score'] ?? [];
    $feedback = sanitizeInput($_POST['feedback']);
    
    $total_score = 0;
    $question_scores = [];
    
    // Calculate total score from individual question scores
    $questions->data_seek(0);
    while ($q = $questions->fetch_assoc()) {
        $score = floatval($scores[$q['id']] ?? 0);
        $total_score += $score;
        $question_scores[$q['id']] = $score;
    }
    
    $percentage = ($total_score / $exam['total_marks']) * 100;
    
    // Calculate grade based on student's level
    $grade_info = calculateGrade($percentage, $student_level);
    
    // Update submission with grading
    $update = $conn->prepare("
        UPDATE exam_submissions 
        SET total_score = ?, percentage = ?, grade = ?, is_graded = 1, graded_by = ?, graded_at = NOW(), feedback = ?
        WHERE id = ?
    ");
    $update->bind_param("ddsssi", $total_score, $percentage, $grade_info['grade'], $_SESSION['user_id'], $feedback, $submission_id);
    
    if ($update->execute()) {
        logActivity($_SESSION['user_id'], 'graded exam submission', 'exam_submission', $submission_id);
        $success = "Exam graded successfully!";
        
        // Create notification for student
        $notify = $conn->prepare("
            INSERT INTO notifications (user_id, title, message, type, link) 
            SELECT user_id, 'Exam Graded', CONCAT('Your exam \"', ?, '\" has been graded. You scored ', ?, ' out of ', ?, ' (', ?, '%) - Grade: ', ?), 'exam', ?
            FROM students s
            JOIN users u ON s.user_id = u.id
            WHERE s.id = ?
        ");
        $link = BASE_URL . "student/exams/results.php?id=" . $exam['id'];
        $notify->bind_param("sdddssi", $exam['title'], $total_score, $exam['total_marks'], round($percentage, 1), $grade_info['grade'], $link, $submission['student_id']);
        $notify->execute();
        
        // Refresh submission data
        $sub_query = $conn->prepare("
            SELECT es.*, 
                   CONCAT(u.first_name, ' ', u.last_name) as student_name,
                   s.admission_number
            FROM exam_submissions es
            JOIN students s ON es.student_id = s.id
            JOIN users u ON s.user_id = u.id
            WHERE es.id = ?
        ");
        $sub_query->bind_param("i", $submission_id);
        $sub_query->execute();
        $submission = $sub_query->get_result()->fetch_assoc();
    } else {
        $error = "Failed to save grade: " . $conn->error;
    }
}

// Re-fetch questions for display
$questions->data_seek(0);
?>

<div class="ml-64 mt-16 p-6">
    <div class="max-w-4xl mx-auto">
        <div class="mb-6">
            <h1 class="text-2xl font-bold text-gray-800">Grade Exam Submission</h1>
            <p class="text-gray-500 mt-1">
                <?php echo htmlspecialchars($exam['title']); ?> - 
                <?php echo htmlspecialchars($submission['student_name']); ?> (<?php echo $submission['admission_number']; ?>)
            </p>
            <p class="text-sm text-gray-400">
                Level: <?php echo $student_level == 'olevel' ? 'O-Level (Form 1-4)' : 'A-Level (Form 5-6)'; ?>
            </p>
        </div>

        <?php if($error): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg mb-4"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <?php if($success): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-lg mb-4"><?php echo $success; ?></div>
        <?php endif; ?>

        <!-- Grading Scale Reference -->
        <div class="bg-gray-100 rounded-xl p-4 mb-6">
            <h3 class="font-semibold text-sm mb-2">Grading Scale Reference - <?php echo $student_level == 'olevel' ? 'O-Level (Form 1-4)' : 'A-Level (Form 5-6)'; ?></h3>
            <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-<?php echo $student_level == 'olevel' ? '5' : '7'; ?> gap-2 text-center text-xs">
                <?php if($student_level == 'olevel'): ?>
                    <div class="bg-green-100 p-1 rounded"><strong>A</strong><br>75-100%</div>
                    <div class="bg-blue-100 p-1 rounded"><strong>B</strong><br>65-74%</div>
                    <div class="bg-yellow-100 p-1 rounded"><strong>C</strong><br>45-64%</div>
                    <div class="bg-orange-100 p-1 rounded"><strong>D</strong><br>30-44%</div>
                    <div class="bg-red-100 p-1 rounded"><strong>F</strong><br>0-29%</div>
                <?php else: ?>
                    <div class="bg-green-100 p-1 rounded"><strong>A</strong><br>80-100%</div>
                    <div class="bg-blue-100 p-1 rounded"><strong>B</strong><br>70-79%</div>
                    <div class="bg-cyan-100 p-1 rounded"><strong>C</strong><br>60-69%</div>
                    <div class="bg-yellow-100 p-1 rounded"><strong>D</strong><br>50-59%</div>
                    <div class="bg-orange-100 p-1 rounded"><strong>E</strong><br>40-49%</div>
                    <div class="bg-purple-100 p-1 rounded"><strong>S</strong><br>35-39%</div>
                    <div class="bg-red-100 p-1 rounded"><strong>F</strong><br>0-34%</div>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($submission['is_graded']): ?>
            <!-- Already Graded - Display Results -->
            <div class="bg-white rounded-xl shadow-sm p-6 mb-6">
                <h3 class="text-lg font-semibold mb-4">Grading Results</h3>
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                    <div class="text-center p-3 bg-gray-50 rounded-lg">
                        <p class="text-gray-500 text-sm">Total Score</p>
                        <p class="text-2xl font-bold"><?php echo $submission['total_score']; ?> / <?php echo $exam['total_marks']; ?></p>
                    </div>
                    <div class="text-center p-3 bg-gray-50 rounded-lg">
                        <p class="text-gray-500 text-sm">Percentage</p>
                        <p class="text-2xl font-bold"><?php echo round($submission['percentage'], 1); ?>%</p>
                    </div>
                    <div class="text-center p-3 bg-gray-50 rounded-lg">
                        <p class="text-gray-500 text-sm">Grade</p>
                        <p class="text-3xl font-bold text-blue-600"><?php echo $submission['grade']; ?></p>
                    </div>
                    <div class="text-center p-3 bg-gray-50 rounded-lg">
                        <p class="text-gray-500 text-sm">Status</p>
                        <p class="px-2 py-1 text-sm rounded-full <?php echo ($submission['percentage'] ?? 0) >= 50 ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'; ?>">
                            <?php echo ($submission['percentage'] ?? 0) >= 50 ? 'PASS' : 'FAIL'; ?>
                        </p>
                    </div>
                </div>
                <?php if($submission['feedback']): ?>
                    <div class="mt-4 p-3 bg-gray-50 rounded-lg">
                        <p class="text-gray-500 text-sm">Teacher's Feedback</p>
                        <p><?php echo nl2br(htmlspecialchars($submission['feedback'])); ?></p>
                    </div>
                <?php endif; ?>
                <div class="mt-4 flex justify-end">
                    <a href="submissions.php?id=<?php echo $exam_id; ?>" class="text-blue-600 hover:text-blue-800">← Back to Submissions</a>
                </div>
            </div>
        <?php else: ?>
            <!-- Grading Form -->
            <form method="POST" id="gradingForm">
                <div class="bg-white rounded-xl shadow-sm overflow-hidden mb-6">
                    <div class="overflow-x-auto">
                        <table class="w-full">
                            <thead class="bg-gray-50 border-b">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">#</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Question</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Type</th>
                                    <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Student Answer</th>
                                    <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Max Marks</th>
                                    <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Score</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200">
                                <?php 
                                $counter = 1;
                                while($q = $questions->fetch_assoc()): 
                                    $student_answer = $answers[$q['id']] ?? 'Not answered';
                                    if (is_array($student_answer)) {
                                        $student_answer = implode(', ', $student_answer);
                                    }
                                ?>
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-4 py-3"><?php echo $counter++; ?></td>
                                        <td class="px-4 py-3">
                                            <p class="font-medium"><?php echo htmlspecialchars($q['question_text']); ?></p>
                                            <?php if($q['question_type'] == 'mcq' && $q['options']): 
                                                $options = json_decode($q['options'], true);
                                            ?>
                                                <div class="text-xs text-gray-500 mt-1">
                                                    Correct: <?php echo $q['correct_answer']; ?>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-4 py-3">
                                            <span class="px-2 py-1 text-xs rounded-full bg-gray-100">
                                                <?php echo ucfirst(str_replace('_', ' ', $q['question_type'])); ?>
                                            </span>
                                        </td>
                                        <td class="px-4 py-3">
                                            <div class="max-w-xs">
                                                <?php if($q['question_type'] == 'mcq' && $q['options']): 
                                                    $options = json_decode($q['options'], true);
                                                ?>
                                                    <p class="text-sm">Selected: <strong><?php echo $student_answer; ?></strong></p>
                                                    <?php if(isset($options[$student_answer])): ?>
                                                        <p class="text-xs text-gray-500"><?php echo htmlspecialchars($options[$student_answer]); ?></p>
                                                    <?php endif; ?>
                                                <?php else: ?>
                                                    <p class="text-sm"><?php echo nl2br(htmlspecialchars($student_answer)); ?></p>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td class="px-4 py-3 text-center font-semibold"><?php echo $q['marks']; ?></td>
                                        <td class="px-4 py-3 text-center">
                                            <input type="number" name="score[<?php echo $q['id']; ?>]" 
                                                   value="0" min="0" max="<?php echo $q['marks']; ?>" 
                                                   step="0.5" class="w-20 border rounded-lg px-2 py-1 text-center score-input"
                                                   data-max="<?php echo $q['marks']; ?>">
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                            <tfoot class="bg-gray-50">
                                <tr>
                                    <td colspan="4" class="px-4 py-3 text-right font-semibold">Total:</td>
                                    <td class="px-4 py-3 text-center font-semibold" id="maxTotal">
                                        <?php 
                                        $total_max = 0;
                                        $questions->data_seek(0);
                                        while($q = $questions->fetch_assoc()) {
                                            $total_max += $q['marks'];
                                        }
                                        $questions->data_seek(0);
                                        echo $total_max;
                                        ?>
                                    </td>
                                    <td class="px-4 py-3 text-center">
                                        <span id="totalScore" class="font-bold text-lg">0</span>
                                        <span class="text-gray-500"> / <?php echo $total_max; ?></span>
                                    </td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>

                <!-- Feedback -->
                <div class="bg-white rounded-xl shadow-sm p-6 mb-6">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Feedback to Student</label>
                    <textarea name="feedback" rows="4" class="w-full border rounded-lg px-3 py-2" 
                              placeholder="Provide constructive feedback to the student..."></textarea>
                </div>

                <!-- Preview Grade -->
                <div class="bg-white rounded-xl shadow-sm p-6 mb-6">
                    <h3 class="text-lg font-semibold mb-4">Grade Preview</h3>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div class="text-center p-3 bg-gray-50 rounded-lg">
                            <p class="text-gray-500 text-sm">Percentage</p>
                            <p class="text-2xl font-bold" id="previewPercentage">0%</p>
                        </div>
                        <div class="text-center p-3 bg-gray-50 rounded-lg">
                            <p class="text-gray-500 text-sm">Grade</p>
                            <p class="text-3xl font-bold text-blue-600" id="previewGrade">-</p>
                        </div>
                        <div class="text-center p-3 bg-gray-50 rounded-lg">
                            <p class="text-gray-500 text-sm">Division</p>
                            <p class="text-lg font-semibold" id="previewDivision">-</p>
                        </div>
                    </div>
                </div>

                <div class="flex justify-end space-x-3">
                    <a href="submissions.php?id=<?php echo $exam_id; ?>" class="px-4 py-2 border rounded-lg hover:bg-gray-50">Cancel</a>
                    <button type="submit" class="px-6 py-2 bg-gradient-to-r from-blue-500 to-purple-600 text-white rounded-lg hover:shadow-lg">
                        <i class="fas fa-save mr-2"></i> Save Grade
                    </button>
                </div>
            </form>
        <?php endif; ?>
    </div>
</div>

<script>
// Calculate total score and preview grade in real-time
document.querySelectorAll('.score-input').forEach(input => {
    input.addEventListener('input', function() {
        let total = 0;
        let maxTotal = <?php echo $total_max; ?>;
        
        document.querySelectorAll('.score-input').forEach(inp => {
            let val = parseFloat(inp.value) || 0;
            let max = parseFloat(inp.dataset.max) || 0;
            if (val > max) {
                inp.value = max;
                val = max;
            }
            total += val;
        });
        
        document.getElementById('totalScore').textContent = total;
        let percentage = (total / maxTotal) * 100;
        document.getElementById('previewPercentage').textContent = percentage.toFixed(1) + '%';
        
        // Calculate grade based on student level (PHP will handle actual grading)
        // This is just preview - actual grade is calculated in PHP
        let grade = '';
        let division = '';
        let level = '<?php echo $student_level; ?>';
        
        if (level === 'olevel') {
            if (percentage >= 75) { grade = 'A'; division = 'First Division (Distinction)'; }
            else if (percentage >= 65) { grade = 'B'; division = 'Second Division'; }
            else if (percentage >= 45) { grade = 'C'; division = 'Third Division'; }
            else if (percentage >= 30) { grade = 'D'; division = 'Pass'; }
            else { grade = 'F'; division = 'Fail'; }
        } else {
            if (percentage >= 80) { grade = 'A'; division = 'First Division (Excellent)'; }
            else if (percentage >= 70) { grade = 'B'; division = 'Second Division'; }
            else if (percentage >= 60) { grade = 'C'; division = 'Third Division'; }
            else if (percentage >= 50) { grade = 'D'; division = 'Pass'; }
            else if (percentage >= 40) { grade = 'E'; division = 'Subsidiary'; }
            else if (percentage >= 35) { grade = 'S'; division = 'Satisfactory'; }
            else { grade = 'F'; division = 'Fail'; }
        }
        
        document.getElementById('previewGrade').textContent = grade;
        document.getElementById('previewDivision').textContent = division;
    });
});

// Validate scores don't exceed max marks
document.querySelectorAll('.score-input').forEach(input => {
    input.addEventListener('change', function() {
        let max = parseFloat(this.dataset.max);
        let val = parseFloat(this.value);
        if (val > max) {
            this.value = max;
        }
        if (val < 0) {
            this.value = 0;
        }
        // Trigger calculation
        this.dispatchEvent(new Event('input'));
    });
});
</script>

<?php include '../../includes/footer.php'; ?>