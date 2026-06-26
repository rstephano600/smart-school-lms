<?php
require_once '../../config.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';
requireRole('student');

$page_title = 'Academic Results';
include '../../includes/header.php';
include '../../includes/sidebar.php';
include '../../includes/navbar.php';

// Get student ID
$student_query = $conn->prepare("SELECT id, class_id FROM students WHERE user_id = ?");
$student_query->bind_param("i", $_SESSION['user_id']);
$student_query->execute();
$student = $student_query->get_result()->fetch_assoc();
$student_id = $student['id'];

// Get exam results
$results = $conn->prepare("
    SELECT er.*, e.name as exam_name, e.term, e.year, sub.name as subject_name
    FROM exam_results er
    JOIN exams e ON er.exam_id = e.id
    JOIN subjects sub ON er.subject_id = sub.id
    WHERE er.student_id = ?
    ORDER BY e.year DESC, e.term DESC, sub.name
");
$results->bind_param("i", $student_id);
$results->execute();
$results = $results->get_result();

// Calculate overall performance by term
$term_performance = [];
while ($row = $results->fetch_assoc()) {
    $key = $row['year'] . '-' . $row['term'];
    if (!isset($term_performance[$key])) {
        $term_performance[$key] = ['total' => 0, 'count' => 0, 'year' => $row['year'], 'term' => $row['term']];
    }
    $term_performance[$key]['total'] += $row['marks_obtained'];
    $term_performance[$key]['count']++;
}
$results->data_seek(0);
?>

<div class="ml-64 mt-16 p-6">
    <div class="max-w-full mx-auto">
        <div class="mb-6 flex justify-between items-center">
            <div>
                <h1 class="text-2xl font-bold text-gray-800">Academic Results</h1>
                <p class="text-gray-500 mt-1">View your exam results and performance</p>
            </div>
            <a href="report-card.php" class="bg-gradient-to-r from-blue-500 to-purple-600 text-white px-4 py-2 rounded-lg hover:shadow-lg">
                <i class="fas fa-download mr-2"></i> Download Report Card
            </a>
        </div>

        <!-- Term Performance Cards -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 mb-6">
            <?php foreach($term_performance as $term_key => $term): 
                $avg = round($term['total'] / $term['count'], 1);
                $grade = $avg >= 80 ? 'A' : ($avg >= 70 ? 'B' : ($avg >= 60 ? 'C' : ($avg >= 50 ? 'D' : ($avg >= 40 ? 'E' : 'F'))));
                $grade_color = $grade == 'A' ? 'text-green-600' : ($grade == 'B' ? 'text-blue-600' : ($grade == 'C' ? 'text-yellow-600' : 'text-red-600'));
            ?>
                <div class="bg-white rounded-xl p-4 shadow-sm hover:shadow-md transition-all">
                    <div class="flex justify-between items-start">
                        <div>
                            <p class="text-gray-500 text-sm"><?php echo $term['year']; ?> - <?php echo ucfirst($term['term']); ?></p>
                            <p class="text-2xl font-bold <?php echo $grade_color; ?>"><?php echo $avg; ?>%</p>
                            <p class="text-lg font-semibold">Grade: <?php echo $grade; ?></p>
                        </div>
                        <div class="w-12 h-12 bg-gray-100 rounded-full flex items-center justify-center">
                            <i class="fas fa-chart-line text-gray-500 text-xl"></i>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Results Table -->
        <div class="bg-white rounded-xl shadow-sm overflow-hidden">
            <div class="px-6 py-4 border-b bg-gray-50">
                <h3 class="text-lg font-semibold">Detailed Results</h3>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-gray-50 border-b">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Exam</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Subject</th>
                            <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">Marks</th>
                            <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">Grade</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Remarks</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <?php if ($results && $results->num_rows > 0): ?>
                            <?php while($row = $results->fetch_assoc()): ?>
                                <tr class="hover:bg-gray-50">
                                    <td class="px-6 py-4"><?php echo htmlspecialchars($row['exam_name']); ?> (<?php echo ucfirst($row['term']); ?> <?php echo $row['year']; ?>)</td>
                                    <td class="px-6 py-4 font-medium"><?php echo htmlspecialchars($row['subject_name']); ?></td>
                                    <td class="px-6 py-4 text-center font-semibold"><?php echo $row['marks_obtained']; ?>%</td>
                                    <td class="px-6 py-4 text-center">
                                        <span class="px-2 py-1 text-xs rounded-full 
                                            <?php echo $row['grade'] == 'A' ? 'bg-green-100 text-green-700' : 
                                                     ($row['grade'] == 'B' ? 'bg-blue-100 text-blue-700' :
                                                     ($row['grade'] == 'C' ? 'bg-yellow-100 text-yellow-700' : 'bg-red-100 text-red-700')); ?>">
                                            <?php echo $row['grade']; ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 text-gray-600"><?php echo $row['remarks']; ?></td>
                                </td>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" class="px-6 py-12 text-center text-gray-500">
                                    <i class="fas fa-chart-line text-4xl mb-2 block"></i>
                                    No results available yet
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>