<?php
require_once '../../../config.php';
require_once '../../../includes/auth.php';
require_once '../../../includes/functions.php';
requireRole('academic');

$exam_id = $_GET['exam_id'] ?? 0;
$error = '';
$success = '';

if (!$exam_id) {
    header('Location: ../index.php');
    exit();
}

// Get exam details
$exam_query = $conn->prepare("SELECT * FROM exams WHERE id = ?");
$exam_query->bind_param("i", $exam_id);
$exam_query->execute();
$exam = $exam_query->get_result()->fetch_assoc();

// Get unapproved results
$results_query = $conn->prepare("
    SELECT er.*, s.admission_number, 
           CONCAT(u.first_name, ' ', u.last_name) as student_name,
           sub.name as subject_name, sub.code,
           c.name as class_name
    FROM exam_results er
    JOIN students s ON er.student_id = s.id
    JOIN users u ON s.user_id = u.id
    JOIN subjects sub ON er.subject_id = sub.id
    JOIN classes c ON s.class_id = c.id
    WHERE er.exam_id = ? AND er.is_approved = 0
    ORDER BY c.name, sub.name, u.first_name
");
$results_query->bind_param("i", $exam_id);
$results_query->execute();
$results = $results_query->get_result();

// Get summary statistics
$stats_query = $conn->prepare("
    SELECT 
        COUNT(DISTINCT student_id) as total_students,
        COUNT(DISTINCT subject_id) as total_subjects,
        AVG(marks_obtained) as average_score,
        COUNT(CASE WHEN marks_obtained >= 50 THEN 1 END) as passed,
        COUNT(CASE WHEN marks_obtained < 50 THEN 1 END) as failed
    FROM exam_results 
    WHERE exam_id = ? AND marks_obtained IS NOT NULL
");
$stats_query->bind_param("i", $exam_id);
$stats_query->execute();
$stats = $stats_query->get_result()->fetch_assoc();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $approve_all = isset($_POST['approve_all']);
    
    if ($approve_all) {
        $update = $conn->prepare("UPDATE exam_results SET is_approved = 1, approved_by = ?, approved_at = NOW() WHERE exam_id = ? AND is_approved = 0");
        $update->bind_param("ii", $_SESSION['user_id'], $exam_id);
    } else {
        $result_ids = $_POST['result_ids'] ?? [];
        if (!empty($result_ids)) {
            $placeholders = implode(',', array_fill(0, count($result_ids), '?'));
            $types = str_repeat('i', count($result_ids));
            $update = $conn->prepare("UPDATE exam_results SET is_approved = 1, approved_by = ?, approved_at = NOW() WHERE id IN ($placeholders)");
            $params = array_merge([$_SESSION['user_id']], $result_ids);
            $update->bind_param("i" . $types, ...$params);
        }
    }
    
    if (isset($update) && $update->execute()) {
        logActivity($_SESSION['user_id'], 'approved exam results', 'exam', $exam_id);
        $success = "Results approved successfully!";
        
        // Update exam published status
        $publish_exam = $conn->prepare("UPDATE exams SET is_published = 1 WHERE id = ?");
        $publish_exam->bind_param("i", $exam_id);
        $publish_exam->execute();
        
        header("Location: approve.php?exam_id=$exam_id&approved=1");
        exit();
    }
}
?>

<div class="ml-64 mt-16 p-6">
    <div class="max-w-6xl mx-auto">
        <div class="mb-6">
            <h1 class="text-2xl font-bold text-gray-800">Approve Exam Results</h1>
            <p class="text-gray-500 mt-1"><?php echo htmlspecialchars($exam['name']); ?> - <?php echo ucfirst($exam['term']); ?> <?php echo $exam['year']; ?></p>
        </div>

        <?php if(isset($_GET['approved'])): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-lg mb-4">
                Results approved successfully! Students can now view their results.
            </div>
        <?php endif; ?>

        <!-- Summary Cards -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
            <div class="bg-white rounded-xl p-4 shadow-sm">
                <p class="text-gray-500 text-sm">Total Students</p>
                <p class="text-2xl font-bold"><?php echo $stats['total_students'] ?? 0; ?></p>
            </div>
            <div class="bg-white rounded-xl p-4 shadow-sm">
                <p class="text-gray-500 text-sm">Average Score</p>
                <p class="text-2xl font-bold"><?php echo round($stats['average_score'] ?? 0, 2); ?>%</p>
            </div>
            <div class="bg-white rounded-xl p-4 shadow-sm">
                <p class="text-gray-500 text-sm">Passed</p>
                <p class="text-2xl font-bold text-green-600"><?php echo $stats['passed'] ?? 0; ?></p>
            </div>
            <div class="bg-white rounded-xl p-4 shadow-sm">
                <p class="text-gray-500 text-sm">Failed</p>
                <p class="text-2xl font-bold text-red-600"><?php echo $stats['failed'] ?? 0; ?></p>
            </div>
        </div>

        <?php if ($results && $results->num_rows > 0): ?>
            <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4 mb-4">
                <div class="flex items-center justify-between">
                    <div>
                        <i class="fas fa-exclamation-triangle text-yellow-600 mr-2"></i>
                        <span class="text-yellow-800"><?php echo $results->num_rows; ?> results pending approval</span>
                    </div>
                    <form method="POST" onsubmit="return confirm('Approve ALL pending results? This action cannot be undone.')">
                        <button type="submit" name="approve_all" value="1" class="bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700">
                            <i class="fas fa-check-double mr-2"></i> Approve All
                        </button>
                    </form>
                </div>
            </div>

            <form method="POST" id="approveForm">
                <div class="bg-white rounded-xl shadow-sm overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="w-full">
                            <thead class="bg-gray-50 border-b">
                                <tr>
                                    <th class="px-4 py-3 text-left">
                                        <input type="checkbox" id="selectAll" class="rounded">
                                    </th>
                                    <th class="px-4 py-3 text-left">Student</th>
                                    <th class="px-4 py-3 text-left">Class</th>
                                    <th class="px-4 py-3 text-left">Subject</th>
                                    <th class="px-4 py-3 text-left">Marks</th>
                                    <th class="px-4 py-3 text-left">Grade</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200">
                                <?php while($row = $results->fetch_assoc()): ?>
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-4 py-3">
                                            <input type="checkbox" name="result_ids[]" value="<?php echo $row['id']; ?>" class="result-checkbox rounded">
                                        </td>
                                        <td class="px-4 py-3">
                                            <div>
                                                <p class="font-medium"><?php echo htmlspecialchars($row['student_name']); ?></p>
                                                <p class="text-xs text-gray-500"><?php echo $row['admission_number']; ?></p>
                                            </div>
                                        </td>
                                        <td class="px-4 py-3"><?php echo $row['class_name']; ?></td>
                                        <td class="px-4 py-3"><?php echo htmlspecialchars($row['subject_name']); ?></td>
                                        <td class="px-4 py-3">
                                            <span class="font-semibold <?php echo $row['marks_obtained'] >= 50 ? 'text-green-600' : 'text-red-600'; ?>">
                                                <?php echo $row['marks_obtained']; ?>%
                                            </span>
                                        </td>
                                        <td class="px-4 py-3">
                                            <span class="px-2 py-1 text-xs rounded-full <?php echo $row['grade'] == 'A' ? 'bg-green-100 text-green-700' : ($row['grade'] == 'F' ? 'bg-red-100 text-red-700' : 'bg-blue-100 text-blue-700'); ?>">
                                                Grade <?php echo $row['grade']; ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                
                <div class="mt-4 flex justify-end space-x-3">
                    <a href="../index.php" class="px-4 py-2 border rounded-lg hover:bg-gray-50">Cancel</a>
                    <button type="submit" class="px-6 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700">
                        <i class="fas fa-check mr-2"></i> Approve Selected
                    </button>
                </div>
            </form>
        <?php else: ?>
            <div class="bg-white rounded-xl shadow-sm p-12 text-center">
                <i class="fas fa-check-circle text-6xl text-green-300 mb-4"></i>
                <h3 class="text-xl font-semibold text-gray-600">All Results Approved</h3>
                <p class="text-gray-400 mt-2">All results for this exam have been approved</p>
                <a href="../index.php" class="inline-block mt-4 text-blue-600 hover:text-blue-800">← Back to Exams</a>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
document.getElementById('selectAll')?.addEventListener('change', function() {
    document.querySelectorAll('.result-checkbox').forEach(cb => cb.checked = this.checked);
});
</script>

<?php include '../../../includes/footer.php'; ?>