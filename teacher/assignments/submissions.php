<?php
require_once '../../config.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';
requireRole('teacher');

$assignment_id = $_GET['id'] ?? 0;

if (!$assignment_id) {
    header('Location: index.php');
    exit();
}

// Get assignment details
$assignment_query = $conn->prepare("
    SELECT a.*, s.name as subject_name, c.name as class_name
    FROM assignments a
    JOIN subjects s ON a.subject_id = s.id
    JOIN classes c ON a.class_id = c.id
    WHERE a.id = ? AND a.created_by = ?
");
$assignment_query->bind_param("ii", $assignment_id, $_SESSION['user_id']);
$assignment_query->execute();
$assignment = $assignment_query->get_result()->fetch_assoc();

if (!$assignment) {
    header('Location: index.php');
    exit();
}

$page_title = 'Submissions - ' . $assignment['title'];
include '../../includes/header.php';
include '../../includes/sidebar.php';
include '../../includes/navbar.php';

// Get all students in the class
$students_query = $conn->prepare("
    SELECT s.id, s.admission_number, CONCAT(u.first_name, ' ', u.last_name) as name
    FROM students s
    JOIN users u ON s.user_id = u.id
    WHERE s.class_id = ?
    ORDER BY u.first_name
");
$students_query->bind_param("i", $assignment['class_id']);
$students_query->execute();
$all_students = $students_query->get_result();

// Get submissions
$submissions = $conn->prepare("
    SELECT s.*, 
           CONCAT(u.first_name, ' ', u.last_name) as student_name,
           u.email,
           st.admission_number,
           CASE WHEN s.submitted_at > a.due_date THEN 1 ELSE 0 END as is_late
    FROM submissions s
    RIGHT JOIN students st ON s.student_id = st.id
    JOIN users u ON st.user_id = u.id
    JOIN assignments a ON s.assignment_id = a.id
    WHERE a.id = ?
    ORDER BY u.first_name
");
$submissions->bind_param("i", $assignment_id);
$submissions->execute();
$submissions = $submissions->get_result();

// Calculate statistics
$stats = [
    'total' => 0,
    'submitted' => 0,
    'graded' => 0,
    'pending' => 0,
    'late' => 0,
    'avg_score' => 0
];

$scores = [];
while ($sub = $submissions->fetch_assoc()) {
    $stats['total']++;
    if ($sub['submitted_at']) {
        $stats['submitted']++;
        if ($sub['marks_obtained'] !== null) {
            $stats['graded']++;
            $scores[] = $sub['marks_obtained'];
        } else {
            $stats['pending']++;
        }
        if ($sub['is_late']) $stats['late']++;
    }
}
$stats['avg_score'] = count($scores) > 0 ? round(array_sum($scores) / count($scores), 1) : 0;
$submissions->data_seek(0);
?>

<div class="ml-64 mt-16 p-6">
    <div class="max-w-full mx-auto">
        <div class="mb-6">
            <h1 class="text-2xl font-bold text-gray-800">Assignment Submissions</h1>
            <p class="text-gray-500 mt-1"><?php echo htmlspecialchars($assignment['title']); ?> - <?php echo htmlspecialchars($assignment['class_name']); ?> | <?php echo htmlspecialchars($assignment['subject_name']); ?></p>
            <p class="text-sm text-gray-400">Due: <?php echo date('M d, Y h:i A', strtotime($assignment['due_date'])); ?></p>
        </div>

        <!-- Statistics Cards -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4 mb-6">
            <div class="bg-white rounded-xl p-3 shadow-sm text-center">
                <p class="text-gray-500 text-xs">Total Students</p>
                <p class="text-xl font-bold"><?php echo $stats['total']; ?></p>
            </div>
            <div class="bg-green-50 rounded-xl p-3 shadow-sm text-center">
                <p class="text-green-600 text-xs">Submitted</p>
                <p class="text-xl font-bold text-green-700"><?php echo $stats['submitted']; ?></p>
            </div>
            <div class="bg-blue-50 rounded-xl p-3 shadow-sm text-center">
                <p class="text-blue-600 text-xs">Graded</p>
                <p class="text-xl font-bold text-blue-700"><?php echo $stats['graded']; ?></p>
            </div>
            <div class="bg-yellow-50 rounded-xl p-3 shadow-sm text-center">
                <p class="text-yellow-600 text-xs">Pending</p>
                <p class="text-xl font-bold text-yellow-700"><?php echo $stats['pending']; ?></p>
            </div>
            <div class="bg-red-50 rounded-xl p-3 shadow-sm text-center">
                <p class="text-red-600 text-xs">Late</p>
                <p class="text-xl font-bold text-red-700"><?php echo $stats['late']; ?></p>
            </div>
        </div>

        <!-- Submissions Table -->
        <div class="bg-white rounded-xl shadow-sm overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-gray-50 border-b">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Student</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Admission No</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Submitted On</th>
                            <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">Marks</th>
                            <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">Grade</th>
                            <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <?php while($sub = $submissions->fetch_assoc()): 
                            $has_submitted = $sub['submitted_at'] ? true : false;
                            $is_late = $sub['is_late'];
                            $percentage = $sub['marks_obtained'] ? ($sub['marks_obtained'] / $assignment['max_marks']) * 100 : 0;
                            $grade = '';
                            if ($percentage >= 80) $grade = 'A';
                            elseif ($percentage >= 70) $grade = 'B';
                            elseif ($percentage >= 60) $grade = 'C';
                            elseif ($percentage >= 50) $grade = 'D';
                            elseif ($percentage >= 40) $grade = 'E';
                            else $grade = 'F';
                        ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4">
                                    <div>
                                        <p class="font-medium text-gray-800"><?php echo htmlspecialchars($sub['student_name']); ?></p>
                                        <p class="text-xs text-gray-500"><?php echo $sub['email']; ?></p>
                                    </div>
                                </td>
                                <td class="px-6 py-4"><?php echo $sub['admission_number']; ?></td>
                                <td class="px-6 py-4">
                                    <?php if($has_submitted): ?>
                                        <span class="px-2 py-1 text-xs rounded-full <?php echo $is_late ? 'bg-red-100 text-red-700' : 'bg-green-100 text-green-700'; ?>">
                                            <?php echo $is_late ? 'Late' : 'On Time'; ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="px-2 py-1 text-xs rounded-full bg-gray-100 text-gray-700">Not Submitted</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4">
                                    <?php echo $has_submitted ? date('M d, Y h:i A', strtotime($sub['submitted_at'])) : '-'; ?>
                                </td>
                                <td class="px-6 py-4 text-center">
                                    <?php if($sub['marks_obtained'] !== null): ?>
                                        <span class="font-semibold"><?php echo $sub['marks_obtained']; ?> / <?php echo $assignment['max_marks']; ?></span>
                                        <span class="text-xs text-gray-400">(<?php echo round($percentage, 1); ?>%)</span>
                                    <?php else: ?>
                                        <span class="text-gray-400">-</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4 text-center">
                                    <?php if($sub['marks_obtained'] !== null): ?>
                                        <span class="px-2 py-1 text-xs rounded-full font-bold 
                                            <?php echo $grade == 'A' ? 'bg-green-100 text-green-700' : 
                                                     ($grade == 'B' ? 'bg-blue-100 text-blue-700' :
                                                     ($grade == 'C' ? 'bg-yellow-100 text-yellow-700' : 'bg-red-100 text-red-700')); ?>">
                                            <?php echo $grade; ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-gray-400">-</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4 text-center">
                                    <?php if($has_submitted): ?>
                                        <div class="flex justify-center space-x-2">
                                            <?php if($sub['attachment_url']): ?>
                                                <a href="../../<?php echo $sub['attachment_url']; ?>" target="_blank" class="text-blue-600 hover:text-blue-800" title="Download">
                                                    <i class="fas fa-download"></i>
                                                </a>
                                            <?php endif; ?>
                                            <a href="grade.php?id=<?php echo $sub['id']; ?>&assignment_id=<?php echo $assignment_id; ?>" 
                                               class="<?php echo $sub['marks_obtained'] !== null ? 'text-green-600' : 'bg-blue-600 text-white px-3 py-1 rounded-lg text-sm hover:bg-blue-700'; ?>" 
                                               title="<?php echo $sub['marks_obtained'] !== null ? 'Edit Grade' : 'Grade'; ?>">
                                                <?php if($sub['marks_obtained'] !== null): ?>
                                                    <i class="fas fa-edit"></i>
                                                <?php else: ?>
                                                    Grade
                                                <?php endif; ?>
                                            </a>
                                        </div>
                                    <?php else: ?>
                                        <span class="text-gray-400">-</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <div class="mt-4 flex justify-between">
            <a href="index.php" class="text-blue-600 hover:text-blue-800">← Back to Assignments</a>
            <?php if($stats['pending'] > 0): ?>
                <a href="grade-all.php?id=<?php echo $assignment_id; ?>" class="bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700">
                    <i class="fas fa-check-double mr-2"></i> Grade All Pending
                </a>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>