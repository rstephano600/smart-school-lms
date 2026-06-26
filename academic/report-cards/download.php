<?php
require_once '../../config.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';
requireRole('academic');

$rc_id = $_GET['id'] ?? 0;
if (!$rc_id) {
    header('Location: index.php');
    exit();
}

// Get report card data
$query = "
    SELECT rc.*, 
           s.admission_number,
           CONCAT(u.first_name, ' ', u.last_name) as student_name,
           c.name as class_name,
           c.id as class_id,
           e.name as exam_name, e.term, e.year,
           (SELECT CONCAT(tu.first_name, ' ', tu.last_name) 
            FROM teachers t 
            JOIN users tu ON t.user_id = tu.id 
            WHERE t.id = c.class_teacher_id) as class_teacher
    FROM report_cards rc
    JOIN students s ON rc.student_id = s.id
    JOIN users u ON s.user_id = u.id
    JOIN classes c ON s.class_id = c.id
    JOIN exams e ON rc.exam_id = e.id
    WHERE rc.id = ?
";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $rc_id);
$stmt->execute();
$report = $stmt->get_result()->fetch_assoc();

if (!$report) {
    header('Location: index.php');
    exit();
}

// Get results for this student and exam
$results_query = $conn->prepare("
    SELECT er.*, sub.name as subject_name
    FROM exam_results er
    JOIN subjects sub ON er.subject_id = sub.id
    WHERE er.student_id = ? AND er.exam_id = ?
    ORDER BY sub.name
");
$results_query->bind_param("ii", $report['student_id'], $report['exam_id']);
$results_query->execute();
$results = $results_query->get_result();

// Get school settings
$school = $conn->query("SELECT * FROM school_settings LIMIT 1")->fetch_assoc();

// Update downloaded status
$update = $conn->prepare("UPDATE report_cards SET is_downloaded = 1, downloaded_at = NOW() WHERE id = ?");
$update->bind_param("i", $rc_id);
$update->execute();

logActivity($_SESSION['user_id'], 'downloaded report card', 'report_card', $rc_id);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Report Card - <?php echo $report['student_name']; ?> | Smart School</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        @media print {
            .no-print { display: none; }
            body { background: white; margin: 0; padding: 0; }
            .report-container { margin: 0; padding: 0; }
            @page { size: portrait; margin: 1cm; }
        }
        .border-dashed-bottom {
            border-bottom: 2px dashed #e5e7eb;
        }
    </style>
</head>
<body class="bg-gray-100">
    <div class="container mx-auto px-4 py-8 report-container">
        <!-- Print Button -->
        <div class="text-right mb-4 no-print">
            <button onclick="window.print()" class="bg-blue-600 text-white px-6 py-2 rounded-lg hover:bg-blue-700">
                <i class="fas fa-print mr-2"></i> Print / Save as PDF
            </button>
            <a href="index.php" class="ml-2 bg-gray-600 text-white px-6 py-2 rounded-lg hover:bg-gray-700">
                <i class="fas fa-arrow-left mr-2"></i> Back
            </a>
        </div>

        <!-- Report Card -->
        <div class="bg-white shadow-xl rounded-lg overflow-hidden">
            <!-- Header -->
            <div class="bg-gradient-to-r from-blue-600 to-purple-600 text-white text-center py-6">
                <?php if($school['school_logo'] && file_exists('../../' . $school['school_logo'])): ?>
                    <img src="../../<?php echo $school['school_logo']; ?>" alt="Logo" class="h-16 mx-auto mb-3">
                <?php endif; ?>
                <h1 class="text-2xl font-bold"><?php echo htmlspecialchars($school['school_name']); ?></h1>
                <p class="text-blue-100"><?php echo htmlspecialchars($school['motto'] ?? 'Education for Excellence'); ?></p>
                <p class="text-sm mt-2"><?php echo htmlspecialchars($school['school_address']); ?></p>
                <p class="text-sm">Tel: <?php echo htmlspecialchars($school['school_phone']); ?> | Email: <?php echo htmlspecialchars($school['school_email']); ?></p>
            </div>

            <!-- Title -->
            <div class="border-b text-center py-3 bg-gray-50">
                <h2 class="text-xl font-bold text-gray-800">STUDENT PROGRESS REPORT</h2>
                <p class="text-gray-500"><?php echo htmlspecialchars($report['exam_name']); ?> - <?php echo ucfirst($report['term']); ?> <?php echo $report['year']; ?></p>
            </div>

            <!-- Student Info -->
            <div class="p-6 border-b">
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                    <div>
                        <p class="text-xs text-gray-500">Student Name</p>
                        <p class="font-semibold text-gray-800"><?php echo htmlspecialchars($report['student_name']); ?></p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-500">Admission Number</p>
                        <p class="font-semibold text-gray-800"><?php echo $report['admission_number']; ?></p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-500">Class</p>
                        <p class="font-semibold text-gray-800"><?php echo htmlspecialchars($report['class_name']); ?></p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-500">Report No</p>
                        <p class="font-semibold text-gray-800"><?php echo $report['report_no']; ?></p>
                    </div>
                </div>
            </div>

            <!-- Results Table -->
            <div class="p-6 border-b">
                <table class="w-full">
                    <thead class="bg-gray-100">
                        <tr>
                            <th class="px-4 py-2 text-left">Subject</th>
                            <th class="px-4 py-2 text-center">Marks (%)</th>
                            <th class="px-4 py-2 text-center">Grade</th>
                            <th class="px-4 py-2 text-center">Points</th>
                            <th class="px-4 py-2 text-left">Remarks</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <?php 
                        $total_marks = 0;
                        $total_points = 0;
                        $subject_count = 0;
                        while($result = $results->fetch_assoc()): 
                            $total_marks += $result['marks_obtained'];
                            $total_points += $result['points'];
                            $subject_count++;
                        ?>
                            <tr>
                                <td class="px-4 py-2 font-medium"><?php echo htmlspecialchars($result['subject_name']); ?></td>
                                <td class="px-4 py-2 text-center"><?php echo $result['marks_obtained']; ?>%</td>
                                <td class="px-4 py-2 text-center font-bold"><?php echo $result['grade']; ?></td>
                                <td class="px-4 py-2 text-center"><?php echo $result['points']; ?></td>
                                <td class="px-4 py-2"><?php echo $result['remarks']; ?></td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                    <tfoot class="bg-gray-50">
                        <tr>
                            <td class="px-4 py-2 font-bold">TOTAL</td>
                            <td class="px-4 py-2 text-center font-bold"><?php echo $total_marks; ?>%</td>
                            <td class="px-4 py-2 text-center"></td>
                            <td class="px-4 py-2 text-center font-bold"><?php echo $total_points; ?></td>
                            <td class="px-4 py-2"></td>
                        </tr>
                        <tr>
                            <td class="px-4 py-2 font-bold">AVERAGE</td>
                            <td colspan="4" class="px-4 py-2 font-bold"><?php echo $subject_count > 0 ? round($total_marks / $subject_count, 2) : 0; ?>%</td>
                        </tr>
                        <tr>
                            <td class="px-4 py-2 font-bold">DIVISION</td>
                            <td colspan="4" class="px-4 py-2">
                                <span class="font-bold <?php echo $report['division']; ?>">
                                    <?php echo $report['division']; ?>
                                </span>
                            </td>
                        </tr>
                        <tr>
                            <td class="px-4 py-2 font-bold">GRADE</td>
                            <td colspan="4" class="px-4 py-2 font-bold"><?php echo $report['grade']; ?></td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            <!-- Grading Scale -->
            <div class="p-6 border-b text-sm">
                <p class="font-semibold mb-2">Grading Scale:</p>
                <div class="grid grid-cols-2 md:grid-cols-5 gap-2 text-center text-xs">
                    <div class="p-1 bg-green-100 rounded">A: 80-100% (Excellent)</div>
                    <div class="p-1 bg-blue-100 rounded">B: 70-79% (Very Good)</div>
                    <div class="p-1 bg-cyan-100 rounded">C: 60-69% (Good)</div>
                    <div class="p-1 bg-yellow-100 rounded">D: 50-59% (Average)</div>
                    <div class="p-1 bg-red-100 rounded">F: Below 50% (Fail)</div>
                </div>
            </div>

            <!-- Comments -->
            <div class="p-6 border-b">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <p class="font-semibold text-gray-700 mb-2">Teacher's Comment</p>
                        <div class="bg-gray-50 p-3 rounded-lg min-h-[100px]">
                            <?php echo nl2br(htmlspecialchars($report['teacher_comment'] ?? 'No comment provided.')); ?>
                        </div>
                        <p class="text-right text-sm text-gray-500 mt-2">Class Teacher</p>
                    </div>
                    <div>
                        <p class="font-semibold text-gray-700 mb-2">Head of School's Remark</p>
                        <div class="bg-gray-50 p-3 rounded-lg min-h-[100px]">
                            <?php echo nl2br(htmlspecialchars($report['head_comment'] ?? 'No remark provided.')); ?>
                        </div>
                        <p class="text-right text-sm text-gray-500 mt-2">Head of School</p>
                    </div>
                </div>
            </div>

            <!-- Footer -->
            <div class="p-4 text-center text-gray-400 text-xs border-t">
                <p>Generated on: <?php echo date('F d, Y h:i A'); ?></p>
                <p>This is a computer-generated document. No signature required.</p>
            </div>
        </div>
    </div>
</body>
</html>