<?php
require_once '../../config.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';
requireRole('academic');

$page_title = 'Generate Report Card';
include '../../includes/header.php';
include '../../includes/sidebar.php';
include '../../includes/navbar.php';

$error = '';
$success = '';
$preview_data = null;

// Get classes
$classes = $conn->query("SELECT id, name FROM classes ORDER BY name");

// Get exams
$exams = $conn->query("SELECT id, name, term, year FROM exams WHERE is_published = 1 ORDER BY year DESC, term DESC");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $exam_id = intval($_POST['exam_id']);
    $class_id = intval($_POST['class_id']);
    $student_id = intval($_POST['student_id'] ?? 0);
    $teacher_comment = sanitizeInput($_POST['teacher_comment'] ?? '');
    $head_comment = sanitizeInput($_POST['head_comment'] ?? '');
    
    // Get school settings
    $school = $conn->query("SELECT * FROM school_settings LIMIT 1")->fetch_assoc();
    
    // Get exam details
    $exam_query = $conn->prepare("SELECT * FROM exams WHERE id = ?");
    $exam_query->bind_param("i", $exam_id);
    $exam_query->execute();
    $exam = $exam_query->get_result()->fetch_assoc();
    
    // Get student results
    $results_query = $conn->prepare("
        SELECT er.*, s.name as subject_name, s.code
        FROM exam_results er
        JOIN subjects s ON er.subject_id = s.id
        WHERE er.student_id = ? AND er.exam_id = ? AND er.is_approved = 1
        ORDER BY s.name
    ");
    $results_query->bind_param("ii", $student_id, $exam_id);
    $results_query->execute();
    $results = $results_query->get_result();
    
    if ($results->num_rows == 0) {
        $error = "No approved results found for this student/exam combination.";
    } else {
        // Calculate totals
        $total_marks = 0;
        $total_points = 0;
        $subject_count = 0;
        $subject_data = [];
        
        while ($row = $results->fetch_assoc()) {
            $total_marks += $row['marks_obtained'];
            $total_points += $row['points'];
            $subject_count++;
            $subject_data[] = $row;
        }
        
        $average = $subject_count > 0 ? round($total_marks / $subject_count, 2) : 0;
        
        // Calculate division
        if ($average >= 70) {
            $division = 'First Division (Distinction)';
            $division_class = 'text-green-600';
        } elseif ($average >= 60) {
            $division = 'Second Division';
            $division_class = 'text-blue-600';
        } elseif ($average >= 50) {
            $division = 'Third Division';
            $division_class = 'text-yellow-600';
        } elseif ($average >= 40) {
            $division = 'Pass';
            $division_class = 'text-orange-600';
        } else {
            $division = 'Fail';
            $division_class = 'text-red-600';
        }
        
        $grade = $average >= 80 ? 'A' : ($average >= 70 ? 'B' : ($average >= 60 ? 'C' : ($average >= 50 ? 'D' : ($average >= 40 ? 'E' : 'F'))));
        
        // Prepare preview data
        $preview_data = [
            'school' => $school,
            'exam' => $exam,
            'student' => null,
            'results' => $subject_data,
            'total_marks' => $total_marks,
            'total_points' => $total_points,
            'subject_count' => $subject_count,
            'average' => $average,
            'division' => $division,
            'division_class' => $division_class,
            'grade' => $grade,
            'teacher_comment' => $teacher_comment,
            'head_comment' => $head_comment
        ];
        
        // Get student details
        $student_query = $conn->prepare("
            SELECT s.*, 
                   CONCAT(u.first_name, ' ', u.last_name) as name,
                   c.name as class_name,
                   (SELECT CONCAT(tu.first_name, ' ', tu.last_name) 
                    FROM teachers t 
                    JOIN users tu ON t.user_id = tu.id 
                    WHERE t.id = c.class_teacher_id) as class_teacher
            FROM students s
            JOIN users u ON s.user_id = u.id
            JOIN classes c ON s.class_id = c.id
            WHERE s.id = ?
        ");
        $student_query->bind_param("i", $student_id);
        $student_query->execute();
        $preview_data['student'] = $student_query->get_result()->fetch_assoc();
        
        // If confirmed, save to database
        if (isset($_POST['confirm']) && $_POST['confirm'] == '1') {
            // Generate unique report card number
            $report_no = 'RC' . date('Y') . str_pad($student_id, 4, '0', STR_PAD_LEFT) . str_pad($exam_id, 2, '0', STR_PAD_LEFT);
            
            $insert = $conn->prepare("
                INSERT INTO report_cards (student_id, exam_id, report_no, total_marks, average_score, grade, division, teacher_comment, head_comment, generated_by) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $insert->bind_param("iisdsssssi", $student_id, $exam_id, $report_no, $total_marks, $average, $grade, $division, $teacher_comment, $head_comment, $_SESSION['user_id']);
            
            if ($insert->execute()) {
                $rc_id = $conn->insert_id;
                logActivity($_SESSION['user_id'], 'generated report card', 'report_card', $rc_id);
                $success = "Report card generated successfully!";
                header("Location: download.php?id=$rc_id&new=1");
                exit();
            } else {
                $error = "Failed to save report card: " . $conn->error;
            }
        }
    }
}
?>

<div class="ml-64 mt-16 p-6">
    <div class="max-w-6xl mx-auto">
        <div class="mb-6">
            <h1 class="text-2xl font-bold text-gray-800">Generate Report Card</h1>
            <p class="text-gray-500 mt-1">Create a comprehensive report card for students</p>
        </div>

        <?php if($error): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg mb-4"><?php echo $error; ?></div>
        <?php endif; ?>

        <!-- Selection Form -->
        <div class="bg-white rounded-xl shadow-sm p-6 mb-6">
            <form method="POST" id="selectionForm">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Select Exam *</label>
                        <select name="exam_id" id="exam_id" required class="w-full border rounded-lg px-3 py-2">
                            <option value="">Choose Exam</option>
                            <?php while($exam = $exams->fetch_assoc()): ?>
                                <option value="<?php echo $exam['id']; ?>">
                                    <?php echo htmlspecialchars($exam['name'] . ' - ' . $exam['term'] . ' ' . $exam['year']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Select Class *</label>
                        <select name="class_id" id="class_id" required class="w-full border rounded-lg px-3 py-2">
                            <option value="">Choose Class</option>
                            <?php while($class = $classes->fetch_assoc()): ?>
                                <option value="<?php echo $class['id']; ?>"><?php echo htmlspecialchars($class['name']); ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Select Student *</label>
                        <select name="student_id" id="student_id" required class="w-full border rounded-lg px-3 py-2">
                            <option value="">Choose Student</option>
                        </select>
                    </div>
                </div>
                
                <div class="mt-4">
                    <button type="submit" name="preview" value="1" class="w-full bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700">
                        <i class="fas fa-eye mr-2"></i> Load Report Card
                    </button>
                </div>
            </form>
        </div>

        <!-- Report Card Preview -->
        <?php if ($preview_data): ?>
            <div class="bg-white rounded-xl shadow-lg overflow-hidden">
                <!-- Report Card Header -->
                <div class="bg-gradient-to-r from-blue-600 to-purple-600 text-white p-6 text-center">
                    <h2 class="text-2xl font-bold"><?php echo htmlspecialchars($preview_data['school']['school_name']); ?></h2>
                    <p class="text-blue-100"><?php echo htmlspecialchars($preview_data['school']['motto'] ?? 'Education for Excellence'); ?></p>
                    <p class="text-sm mt-2"><?php echo htmlspecialchars($preview_data['school']['school_address']); ?></p>
                    <p class="text-sm">Tel: <?php echo htmlspecialchars($preview_data['school']['school_phone']); ?> | Email: <?php echo htmlspecialchars($preview_data['school']['school_email']); ?></p>
                </div>

                <!-- Report Card Title -->
                <div class="border-b p-4 text-center bg-gray-50">
                    <h3 class="text-xl font-bold text-gray-800">STUDENT REPORT CARD</h3>
                    <p class="text-gray-500"><?php echo htmlspecialchars($preview_data['exam']['name']); ?> - <?php echo ucfirst($preview_data['exam']['term']); ?> <?php echo $preview_data['exam']['year']; ?></p>
                </div>

                <!-- Student Info -->
                <div class="p-6 border-b">
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                        <div>
                            <p class="text-sm text-gray-500">Student Name</p>
                            <p class="font-semibold"><?php echo htmlspecialchars($preview_data['student']['name']); ?></p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-500">Admission No</p>
                            <p class="font-semibold"><?php echo $preview_data['student']['admission_number']; ?></p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-500">Class</p>
                            <p class="font-semibold"><?php echo htmlspecialchars($preview_data['student']['class_name']); ?></p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-500">Class Teacher</p>
                            <p class="font-semibold"><?php echo htmlspecialchars($preview_data['student']['class_teacher'] ?? 'Not Assigned'); ?></p>
                        </div>
                    </div>
                </div>

                <!-- Results Table -->
                <div class="p-6 border-b">
                    <table class="w-full">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-2 text-left">Subject</th>
                                <th class="px-4 py-2 text-center">Marks (%)</th>
                                <th class="px-4 py-2 text-center">Grade</th>
                                <th class="px-4 py-2 text-center">Points</th>
                                <th class="px-4 py-2 text-left">Remarks</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            <?php foreach($preview_data['results'] as $result): ?>
                                <tr>
                                    <td class="px-4 py-2 font-medium"><?php echo htmlspecialchars($result['subject_name']); ?></td>
                                    <td class="px-4 py-2 text-center"><?php echo $result['marks_obtained']; ?>%</td>
                                    <td class="px-4 py-2 text-center font-bold"><?php echo $result['grade']; ?></td>
                                    <td class="px-4 py-2 text-center"><?php echo $result['points']; ?></td>
                                    <td class="px-4 py-2"><?php echo $result['remarks']; ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot class="bg-gray-50 font-semibold">
                            <tr>
                                <td class="px-4 py-2">TOTAL</td>
                                <td class="px-4 py-2 text-center"><?php echo $preview_data['total_marks']; ?>%</td>
                                <td class="px-4 py-2 text-center"><?php echo $preview_data['grade']; ?></td>
                                <td class="px-4 py-2 text-center"><?php echo $preview_data['total_points']; ?></td>
                                <td class="px-4 py-2"></td>
                            </tr>
                            <tr>
                                <td class="px-4 py-2">AVERAGE</td>
                                <td colspan="4" class="px-4 py-2"><?php echo $preview_data['average']; ?>%</td>
                            </tr>
                            <tr>
                                <td class="px-4 py-2">DIVISION</td>
                                <td colspan="4" class="px-4 py-2">
                                    <span class="font-bold <?php echo $preview_data['division_class']; ?>"><?php echo $preview_data['division']; ?></span>
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </div>

                <!-- Comments Section -->
                <div class="p-6 border-b">
                    <form method="POST" id="confirmForm">
                        <input type="hidden" name="exam_id" value="<?php echo $_POST['exam_id']; ?>">
                        <input type="hidden" name="class_id" value="<?php echo $_POST['class_id']; ?>">
                        <input type="hidden" name="student_id" value="<?php echo $_POST['student_id']; ?>">
                        <input type="hidden" name="confirm" value="1">
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Teacher's Comment</label>
                                <textarea name="teacher_comment" rows="3" class="w-full border rounded-lg px-3 py-2" placeholder="Academic performance, strengths, areas for improvement..."><?php echo htmlspecialchars($_POST['teacher_comment'] ?? ''); ?></textarea>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Head of School's Remark</label>
                                <textarea name="head_comment" rows="3" class="w-full border rounded-lg px-3 py-2" placeholder="Overall assessment and recommendations..."><?php echo htmlspecialchars($_POST['head_comment'] ?? ''); ?></textarea>
                            </div>
                        </div>
                        
                        <div class="flex justify-end space-x-3 mt-6">
                            <a href="generate.php" class="px-4 py-2 border rounded-lg hover:bg-gray-50">Cancel</a>
                            <button type="submit" class="px-6 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700">
                                <i class="fas fa-save mr-2"></i> Generate & Save Report Card
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
// Load students when class is selected
document.getElementById('class_id').addEventListener('change', function() {
    const classId = this.value;
    const studentSelect = document.getElementById('student_id');
    
    if (classId) {
        fetch(`../../api/get-class-students.php?class_id=${classId}`)
            .then(response => response.json())
            .then(data => {
                studentSelect.innerHTML = '<option value="">Choose Student</option>';
                data.students.forEach(student => {
                    studentSelect.innerHTML += `<option value="${student.id}">${student.name} (${student.admission_number})</option>`;
                });
            })
            .catch(error => console.error('Error:', error));
    } else {
        studentSelect.innerHTML = '<option value="">Choose Student</option>';
    }
});
</script>

<?php include '../../includes/footer.php'; ?>