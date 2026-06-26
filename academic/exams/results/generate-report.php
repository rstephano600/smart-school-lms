<?php
require_once '../../../config.php';
require_once '../../../includes/auth.php';
require_once '../../../includes/functions.php';
requireRole('academic');

$exam_id = $_GET['exam_id'] ?? 0;
$class_id = $_GET['class_id'] ?? 0;

if (!$exam_id) {
    header('Location: ../index.php');
    exit();
}

// Get exam details
$exam_query = $conn->prepare("SELECT * FROM exams WHERE id = ?");
$exam_query->bind_param("i", $exam_id);
$exam_query->execute();
$exam = $exam_query->get_result()->fetch_assoc();

// Get classes for filter
$classes = $conn->query("SELECT id, name FROM classes ORDER BY name");

// Build query for results
$query = "
    SELECT 
        s.id as student_id,
        s.admission_number,
        CONCAT(u.first_name, ' ', u.last_name) as student_name,
        c.name as class_name,
        sub.name as subject_name,
        sub.code as subject_code,
        er.marks_obtained,
        er.grade,
        er.points,
        er.remarks
    FROM exam_results er
    JOIN students s ON er.student_id = s.id
    JOIN users u ON s.user_id = u.id
    JOIN classes c ON s.class_id = c.id
    JOIN subjects sub ON er.subject_id = sub.id
    WHERE er.exam_id = ? AND er.is_approved = 1
";

if ($class_id) {
    $query .= " AND s.class_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ii", $exam_id, $class_id);
} else {
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $exam_id);
}

$stmt->execute();
$results = $stmt->get_result();

// Calculate per-student totals
$student_totals = [];
while ($row = $results->fetch_assoc()) {
    $sid = $row['student_id'];
    if (!isset($student_totals[$sid])) {
        $student_totals[$sid] = [
            'name' => $row['student_name'],
            'admission' => $row['admission_number'],
            'class' => $row['class_name'],
            'subjects' => [],
            'total_marks' => 0,
            'total_points' => 0,
            'subject_count' => 0
        ];
    }
    $student_totals[$sid]['subjects'][] = [
        'name' => $row['subject_name'],
        'marks' => $row['marks_obtained'],
        'grade' => $row['grade'],
        'points' => $row['points']
    ];
    $student_totals[$sid]['total_marks'] += $row['marks_obtained'];
    $student_totals[$sid]['total_points'] += $row['points'];
    $student_totals[$sid]['subject_count']++;
}

// Calculate averages and divisions
foreach ($student_totals as &$student) {
    $student['average'] = $student['subject_count'] > 0 ? round($student['total_marks'] / $student['subject_count'], 2) : 0;
    
    // Calculate division
    if ($student['average'] >= 70) {
        $student['division'] = 'I';
        $student['division_color'] = 'text-green-600';
    } elseif ($student['average'] >= 50) {
        $student['division'] = 'II';
        $student['division_color'] = 'text-blue-600';
    } elseif ($student['average'] >= 40) {
        $student['division'] = 'III';
        $student['division_color'] = 'text-yellow-600';
    } else {
        $student['division'] = 'IV';
        $student['division_color'] = 'text-red-600';
    }
}
?>

<div class="ml-64 mt-16 p-6">
    <div class="max-w-full mx-auto">
        <div class="mb-6 flex justify-between items-center">
            <div>
                <h1 class="text-2xl font-bold text-gray-800">Exam Results Report</h1>
                <p class="text-gray-500 mt-1"><?php echo htmlspecialchars($exam['name']); ?> - <?php echo ucfirst($exam['term']); ?> <?php echo $exam['year']; ?></p>
            </div>
            <div class="flex space-x-3">
                <button onclick="window.print()" class="bg-gray-600 text-white px-4 py-2 rounded-lg hover:bg-gray-700">
                    <i class="fas fa-print mr-2"></i> Print Report
                </button>
                <a href="export-results.php?exam_id=<?php echo $exam_id; ?>&class_id=<?php echo $class_id; ?>" class="bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700">
                    <i class="fas fa-file-excel mr-2"></i> Export Excel
                </a>
            </div>
        </div>

        <!-- Filter -->
        <div class="bg-white rounded-xl shadow-sm p-4 mb-6">
            <form method="GET" class="flex gap-4">
                <input type="hidden" name="exam_id" value="<?php echo $exam_id; ?>">
                <div class="flex-1">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Filter by Class</label>
                    <select name="class_id" class="w-full border rounded-lg px-3 py-2" onchange="this.form.submit()">
                        <option value="0">All Classes</option>
                        <?php while($class = $classes->fetch_assoc()): ?>
                            <option value="<?php echo $class['id']; ?>" <?php echo $class_id == $class['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($class['name']); ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
            </form>
        </div>

        <!-- Results Table -->
        <div class="bg-white rounded-xl shadow-sm overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-gradient-to-r from-blue-500 to-purple-600 text-white">
                        <tr>
                            <th class="px-4 py-3 text-left">Admission No</th>
                            <th class="px-4 py-3 text-left">Student Name</th>
                            <th class="px-4 py-3 text-left">Class</th>
                            <?php 
                            // Get unique subjects for headers
                            $subjects_list = [];
                            foreach ($student_totals as $student) {
                                foreach ($student['subjects'] as $subject) {
                                    if (!in_array($subject['name'], $subjects_list)) {
                                        $subjects_list[] = $subject['name'];
                                    }
                                }
                            }
                            foreach ($subjects_list as $subject): ?>
                                <th class="px-3 py-3 text-center"><?php echo htmlspecialchars($subject); ?></th>
                            <?php endforeach; ?>
                            <th class="px-4 py-3 text-center">Average</th>
                            <th class="px-4 py-3 text-center">Division</th>
                            <th class="px-4 py-3 text-center">Remarks</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <?php if (count($student_totals) > 0): ?>
                            <?php foreach($student_totals as $student): ?>
                                <tr class="hover:bg-gray-50">
                                    <td class="px-4 py-3"><?php echo $student['admission']; ?></td>
                                    <td class="px-4 py-3 font-medium"><?php echo htmlspecialchars($student['name']); ?></td>
                                    <td class="px-4 py-3"><?php echo $student['class']; ?></td>
                                    <?php foreach($subjects_list as $subject_name): 
                                        $found = false;
                                        foreach($student['subjects'] as $subject):
                                            if($subject['name'] == $subject_name):
                                                $found = true;
                                                $marks = $subject['marks'];
                                    ?>
                                        <td class="px-3 py-3 text-center">
                                            <span class="<?php echo $marks >= 50 ? 'text-green-600 font-semibold' : 'text-red-600'; ?>">
                                                <?php echo $marks; ?>%
                                            </span>
                                            <span class="text-xs text-gray-400 block"><?php echo $subject['grade']; ?></span>
                                        </td>
                                    <?php 
                                            endif;
                                        endforeach;
                                        if(!$found):
                                    ?>
                                        <td class="px-3 py-3 text-center text-gray-400">-</td>
                                    <?php endif; endforeach; ?>
                                    <td class="px-4 py-3 text-center font-bold">
                                        <?php echo $student['average']; ?>%
                                    </td>
                                    <td class="px-4 py-3 text-center">
                                        <span class="font-bold <?php echo $student['division_color']; ?>">
                                            <?php echo $student['division']; ?>
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-center">
                                        <span class="px-2 py-1 text-xs rounded-full <?php echo $student['average'] >= 50 ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'; ?>">
                                            <?php echo $student['average'] >= 50 ? 'PASS' : 'FAIL'; ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="<?php echo 3 + count($subjects_list) + 3; ?>" class="px-6 py-12 text-center text-gray-500">
                                    <i class="fas fa-chart-line text-4xl mb-2 block"></i>
                                    No results available for this exam
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- Summary -->
        <?php if (count($student_totals) > 0): 
            $pass_count = 0;
            $fail_count = 0;
            foreach($student_totals as $student) {
                if ($student['average'] >= 50) $pass_count++;
                else $fail_count++;
            }
        ?>
        <div class="mt-6 bg-white rounded-xl shadow-sm p-4">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div>
                    <p class="text-gray-500 text-sm">Total Students</p>
                    <p class="text-2xl font-bold"><?php echo count($student_totals); ?></p>
                </div>
                <div>
                    <p class="text-gray-500 text-sm">Passed</p>
                    <p class="text-2xl font-bold text-green-600"><?php echo $pass_count; ?></p>
                </div>
                <div>
                    <p class="text-gray-500 text-sm">Failed</p>
                    <p class="text-2xl font-bold text-red-600"><?php echo $fail_count; ?></p>
                </div>
                <div>
                    <p class="text-gray-500 text-sm">Pass Rate</p>
                    <p class="text-2xl font-bold text-blue-600"><?php echo count($student_totals) > 0 ? round(($pass_count / count($student_totals)) * 100, 1) : 0; ?>%</p>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<style>
@media print {
    .ml-64, .sidebar, .navbar, .no-print {
        display: none !important;
    }
    .ml-64 {
        margin-left: 0 !important;
    }
    body {
        background: white;
    }
    table {
        font-size: 10pt;
    }
}
</style>

<?php include '../../../includes/footer.php'; ?>