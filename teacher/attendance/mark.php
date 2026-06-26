<?php
require_once '../../config.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';
requireRole('teacher');

$page_title = 'Mark Attendance';
include '../../includes/header.php';
include '../../includes/sidebar.php';
include '../../includes/navbar.php';

// Get teacher ID
$teacher_query = $conn->prepare("SELECT id FROM teachers WHERE user_id = ?");
$teacher_query->bind_param("i", $_SESSION['user_id']);
$teacher_query->execute();
$teacher = $teacher_query->get_result()->fetch_assoc();
$teacher_id = $teacher['id'] ?? 0;

// Get classes taught by this teacher
$classes = $conn->prepare("
    SELECT DISTINCT c.id, c.name 
    FROM class_subject cs
    JOIN classes c ON cs.class_id = c.id
    WHERE cs.teacher_id = ?
");
$classes->bind_param("i", $teacher_id);
$classes->execute();
$classes = $classes->get_result();

$class_id = $_GET['class_id'] ?? '';
$date = $_GET['date'] ?? date('Y-m-d');
$students = [];
$attendance_data = [];

if ($class_id) {
    // Get students in this class
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
    
    // Get existing attendance for this date
    $att_query = $conn->prepare("
        SELECT student_id, status, remark 
        FROM attendance 
        WHERE class_id = ? AND date = ?
    ");
    $att_query->bind_param("is", $class_id, $date);
    $att_query->execute();
    $existing = $att_query->get_result();
    while ($row = $existing->fetch_assoc()) {
        $attendance_data[$row['student_id']] = $row;
    }
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $class_id = intval($_POST['class_id']);
    $date = $_POST['date'];
    $statuses = $_POST['status'] ?? [];
    $remarks = $_POST['remark'] ?? [];
    
    foreach ($statuses as $student_id => $status) {
        $remark = $remarks[$student_id] ?? '';
        
        // Check if attendance already exists
        $check = $conn->prepare("SELECT id FROM attendance WHERE student_id = ? AND date = ?");
        $check->bind_param("is", $student_id, $date);
        $check->execute();
        
        if ($check->get_result()->num_rows > 0) {
            // Update existing
            $update = $conn->prepare("
                UPDATE attendance SET status = ?, remark = ?, marked_by = ? 
                WHERE student_id = ? AND date = ?
            ");
            $update->bind_param("ssiis", $status, $remark, $_SESSION['user_id'], $student_id, $date);
            $update->execute();
        } else {
            // Insert new
            $insert = $conn->prepare("
                INSERT INTO attendance (student_id, class_id, date, status, remark, marked_by) 
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $insert->bind_param("iisssi", $student_id, $class_id, $date, $status, $remark, $_SESSION['user_id']);
            $insert->execute();
        }
    }
    
    logActivity($_SESSION['user_id'], 'marked attendance', 'class', $class_id);
    $success = "Attendance saved successfully!";
    
    // Refresh attendance data
    $att_query = $conn->prepare("
        SELECT student_id, status, remark 
        FROM attendance 
        WHERE class_id = ? AND date = ?
    ");
    $att_query->bind_param("is", $class_id, $date);
    $att_query->execute();
    $existing = $att_query->get_result();
    $attendance_data = [];
    while ($row = $existing->fetch_assoc()) {
        $attendance_data[$row['student_id']] = $row;
    }
}
?>

<div class="ml-64 mt-16 p-6">
    <div class="max-w-full mx-auto">
        <div class="mb-6">
            <h1 class="text-2xl font-bold text-gray-800">Mark Attendance</h1>
            <p class="text-gray-500 mt-1">Record student attendance for your classes</p>
        </div>

        <?php if($error): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg mb-4"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <?php if($success): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-lg mb-4"><?php echo $success; ?></div>
        <?php endif; ?>

        <!-- Filters -->
        <div class="bg-white rounded-xl shadow-sm p-4 mb-6">
            <form method="GET" class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Select Class</label>
                    <select name="class_id" class="w-full border rounded-lg px-3 py-2" onchange="this.form.submit()">
                        <option value="">-- Select Class --</option>
                        <?php while($class = $classes->fetch_assoc()): ?>
                            <option value="<?php echo $class['id']; ?>" <?php echo $class_id == $class['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($class['name']); ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Date</label>
                    <input type="date" name="date" value="<?php echo $date; ?>" 
                           class="w-full border rounded-lg px-3 py-2" onchange="this.form.submit()">
                </div>
                <div class="flex items-end">
                    <a href="report.php?class_id=<?php echo $class_id; ?>" class="w-full bg-gray-600 text-white px-4 py-2 rounded-lg text-center hover:bg-gray-700">
                        <i class="fas fa-chart-line mr-2"></i> View Report
                    </a>
                </div>
            </form>
        </div>

        <?php if ($class_id && $students && $students->num_rows > 0): ?>
            <div class="bg-white rounded-xl shadow-sm overflow-hidden">
                <form method="POST">
                    <input type="hidden" name="class_id" value="<?php echo $class_id; ?>">
                    <input type="hidden" name="date" value="<?php echo $date; ?>">
                    
                    <div class="overflow-x-auto">
                        <table class="w-full">
                            <thead class="bg-gray-50 border-b">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Admission No</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Student Name</th>
                                    <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">Status</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Remark</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200">
                                <?php while($student = $students->fetch_assoc()): 
                                    $current_status = $attendance_data[$student['id']]['status'] ?? '';
                                ?>
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-6 py-4"><?php echo $student['admission_number']; ?></td>
                                        <td class="px-6 py-4 font-medium"><?php echo htmlspecialchars($student['name']); ?></td>
                                        <td class="px-6 py-4 text-center">
                                            <div class="flex justify-center space-x-3">
                                                <label class="inline-flex items-center">
                                                    <input type="radio" name="status[<?php echo $student['id']; ?>]" value="present" 
                                                           <?php echo $current_status == 'present' ? 'checked' : ''; ?> class="form-radio text-green-600">
                                                    <span class="ml-1 text-sm text-green-600">Present</span>
                                                </label>
                                                <label class="inline-flex items-center">
                                                    <input type="radio" name="status[<?php echo $student['id']; ?>]" value="absent" 
                                                           <?php echo $current_status == 'absent' ? 'checked' : ''; ?> class="form-radio text-red-600">
                                                    <span class="ml-1 text-sm text-red-600">Absent</span>
                                                </label>
                                                <label class="inline-flex items-center">
                                                    <input type="radio" name="status[<?php echo $student['id']; ?>]" value="late" 
                                                           <?php echo $current_status == 'late' ? 'checked' : ''; ?> class="form-radio text-yellow-600">
                                                    <span class="ml-1 text-sm text-yellow-600">Late</span>
                                                </label>
                                                <label class="inline-flex items-center">
                                                    <input type="radio" name="status[<?php echo $student['id']; ?>]" value="excused" 
                                                           <?php echo $current_status == 'excused' ? 'checked' : ''; ?> class="form-radio text-blue-600">
                                                    <span class="ml-1 text-sm text-blue-600">Excused</span>
                                                </label>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4">
                                            <input type="text" name="remark[<?php echo $student['id']; ?>]" 
                                                   value="<?php echo htmlspecialchars($attendance_data[$student['id']]['remark'] ?? ''); ?>"
                                                   placeholder="Optional remark"
                                                   class="w-full border rounded-lg px-2 py-1 text-sm">
                                        </td>
                                    <tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <div class="p-4 border-t bg-gray-50 flex justify-end">
                        <button type="submit" class="bg-blue-600 text-white px-6 py-2 rounded-lg hover:bg-blue-700">
                            <i class="fas fa-save mr-2"></i> Save Attendance
                        </button>
                    </div>
                </form>
            </div>
        <?php elseif ($class_id): ?>
            <div class="bg-white rounded-xl shadow-sm p-12 text-center">
                <i class="fas fa-users text-6xl text-gray-300 mb-4"></i>
                <p class="text-gray-500">No students found in this class</p>
            </div>
        <?php else: ?>
            <div class="bg-white rounded-xl shadow-sm p-12 text-center">
                <i class="fas fa-calendar-check text-6xl text-gray-300 mb-4"></i>
                <p class="text-gray-500">Select a class to mark attendance</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>