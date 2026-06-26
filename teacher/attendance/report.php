<?php
require_once '../../config.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';
requireRole('teacher');

$class_id = $_GET['class_id'] ?? '';
$month = $_GET['month'] ?? date('Y-m');

if (!$class_id) {
    header('Location: mark.php');
    exit();
}

$page_title = 'Attendance Report';
include '../../includes/header.php';
include '../../includes/sidebar.php';
include '../../includes/navbar.php';

// Get class name
$class_query = $conn->prepare("SELECT name FROM classes WHERE id = ?");
$class_query->bind_param("i", $class_id);
$class_query->execute();
$class = $class_query->get_result()->fetch_assoc();

// Get students
$students_query = $conn->prepare("
    SELECT s.id, s.admission_number, CONCAT(u.first_name, ' ', u.last_name) as name
    FROM students s
    JOIN users u ON s.user_id = u.id
    WHERE s.class_id = ?
    ORDER BY u.first_name
");
$students_query->bind_param("i", $class_id);
$students_query->execute();
$students = $students_query->get_result();

// Get days in month
$days_in_month = date('t', strtotime($month . '-01'));
$dates = [];
for ($i = 1; $i <= $days_in_month; $i++) {
    $date = $month . '-' . str_pad($i, 2, '0', STR_PAD_LEFT);
    $dates[] = $date;
}

// Get attendance data
$attendance_data = [];
foreach ($students as $student) {
    $att_query = $conn->prepare("
        SELECT date, status 
        FROM attendance 
        WHERE student_id = ? AND date BETWEEN ? AND ?
    ");
    $end_date = date('Y-m-t', strtotime($month . '-01'));
    $att_query->bind_param("iss", $student['id'], $month . '-01', $end_date);
    $att_query->execute();
    $result = $att_query->get_result();
    while ($row = $result->fetch_assoc()) {
        $attendance_data[$student['id']][$row['date']] = $row['status'];
    }
}

// Calculate statistics
$student_stats = [];
$students->data_seek(0);
while ($student = $students->fetch_assoc()) {
    $present = 0;
    $absent = 0;
    $late = 0;
    $excused = 0;
    
    foreach ($dates as $date) {
        $status = $attendance_data[$student['id']][$date] ?? '';
        switch ($status) {
            case 'present': $present++; break;
            case 'absent': $absent++; break;
            case 'late': $late++; break;
            case 'excused': $excused++; break;
        }
    }
    
    $total_days = $present + $absent + $late + $excused;
    $percentage = $total_days > 0 ? round(($present / $total_days) * 100, 1) : 0;
    
    $student_stats[] = [
        'id' => $student['id'],
        'name' => $student['name'],
        'admission' => $student['admission_number'],
        'present' => $present,
        'absent' => $absent,
        'late' => $late,
        'excused' => $excused,
        'percentage' => $percentage
    ];
}
?>

<div class="ml-64 mt-16 p-6">
    <div class="max-w-full mx-auto">
        <div class="mb-6 flex justify-between items-center">
            <div>
                <h1 class="text-2xl font-bold text-gray-800">Attendance Report</h1>
                <p class="text-gray-500 mt-1"><?php echo htmlspecialchars($class['name']); ?> - <?php echo date('F Y', strtotime($month . '-01')); ?></p>
            </div>
            <div>
                <form method="GET" class="flex space-x-3">
                    <input type="hidden" name="class_id" value="<?php echo $class_id; ?>">
                    <input type="month" name="month" value="<?php echo $month; ?>" class="border rounded-lg px-3 py-2">
                    <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700">
                        <i class="fas fa-calendar-alt mr-2"></i> Change Month
                    </button>
                </form>
            </div>
        </div>

        <!-- Summary Cards -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
            <div class="bg-white rounded-xl p-4 shadow-sm">
                <p class="text-gray-500 text-sm">Total Students</p>
                <p class="text-2xl font-bold"><?php echo count($student_stats); ?></p>
            </div>
            <div class="bg-white rounded-xl p-4 shadow-sm">
                <p class="text-gray-500 text-sm">Overall Attendance</p>
                <p class="text-2xl font-bold text-green-600">
                    <?php 
                    $avg_percentage = array_sum(array_column($student_stats, 'percentage')) / (count($student_stats) ?: 1);
                    echo round($avg_percentage, 1); ?>%
                </p>
            </div>
            <div class="bg-white rounded-xl p-4 shadow-sm">
                <p class="text-gray-500 text-sm">Total Present Days</p>
                <p class="text-2xl font-bold"><?php echo array_sum(array_column($student_stats, 'present')); ?></p>
            </div>
            <div class="bg-white rounded-xl p-4 shadow-sm">
                <p class="text-gray-500 text-sm">Total Absent Days</p>
                <p class="text-2xl font-bold text-red-600"><?php echo array_sum(array_column($student_stats, 'absent')); ?></p>
            </div>
        </div>

        <!-- Attendance Table -->
        <div class="bg-white rounded-xl shadow-sm overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-gray-50 border-b">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Admission No</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Student Name</th>
                            <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Present</th>
                            <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Absent</th>
                            <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Late</th>
                            <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Excused</th>
                            <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Attendance %</th>
                            <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <?php foreach($student_stats as $stats): ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-4 py-3"><?php echo $stats['admission']; ?></td>
                                <td class="px-4 py-3 font-medium"><?php echo htmlspecialchars($stats['name']); ?></td>
                                <td class="px-4 py-3 text-center text-green-600"><?php echo $stats['present']; ?></td>
                                <td class="px-4 py-3 text-center text-red-600"><?php echo $stats['absent']; ?></td>
                                <td class="px-4 py-3 text-center text-yellow-600"><?php echo $stats['late']; ?></td>
                                <td class="px-4 py-3 text-center text-blue-600"><?php echo $stats['excused']; ?></td>
                                <td class="px-4 py-3 text-center font-semibold <?php echo $stats['percentage'] >= 80 ? 'text-green-600' : ($stats['percentage'] >= 60 ? 'text-yellow-600' : 'text-red-600'); ?>">
                                    <?php echo $stats['percentage']; ?>%
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <span class="px-2 py-1 text-xs rounded-full <?php echo $stats['percentage'] >= 80 ? 'bg-green-100 text-green-700' : ($stats['percentage'] >= 60 ? 'bg-yellow-100 text-yellow-700' : 'bg-red-100 text-red-700'); ?>">
                                        <?php echo $stats['percentage'] >= 80 ? 'Good' : ($stats['percentage'] >= 60 ? 'Average' : 'Poor'); ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <div class="mt-4 text-right">
            <a href="mark.php?class_id=<?php echo $class_id; ?>" class="text-blue-600 hover:text-blue-800">
                ← Back to Mark Attendance
            </a>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>