<?php
require_once '../../config.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';
requireRole('academic');

$exam_id = $_GET['id'] ?? 0;
$error = '';
$success = '';

if (!$exam_id) {
    header('Location: index.php');
    exit();
}

// Get exam details
$exam_query = $conn->prepare("SELECT * FROM exams WHERE id = ?");
$exam_query->bind_param("i", $exam_id);
$exam_query->execute();
$exam = $exam_query->get_result()->fetch_assoc();

if (!$exam) {
    header('Location: index.php');
    exit();
}

// Get classes and subjects for assignment
$classes = $conn->query("SELECT id, name FROM classes ORDER BY name");
$subjects = $conn->query("SELECT id, name, code FROM subjects ORDER BY name");

// Get existing schedule
$schedule_query = $conn->prepare("
    SELECT es.*, c.name as class_name, s.name as subject_name 
    FROM exam_schedule es
    JOIN classes c ON es.class_id = c.id
    JOIN subjects s ON es.subject_id = s.id
    WHERE es.exam_id = ?
    ORDER BY es.exam_date, es.start_time
");
$schedule_query->bind_param("i", $exam_id);
$schedule_query->execute();
$schedule = $schedule_query->get_result();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $class_id = intval($_POST['class_id']);
    $subject_id = intval($_POST['subject_id']);
    $exam_date = $_POST['exam_date'];
    $start_time = $_POST['start_time'];
    $end_time = $_POST['end_time'];
    $venue = sanitizeInput($_POST['venue']);
    $invigilator = sanitizeInput($_POST['invigilator']);
    
    $insert = $conn->prepare("INSERT INTO exam_schedule (exam_id, class_id, subject_id, exam_date, start_time, end_time, venue, invigilator) 
                               VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    $insert->bind_param("iiisssss", $exam_id, $class_id, $subject_id, $exam_date, $start_time, $end_time, $venue, $invigilator);
    
    if ($insert->execute()) {
        logActivity($_SESSION['user_id'], 'scheduled exam', 'exam_schedule', $insert->insert_id);
        $success = "Exam scheduled successfully!";
        header("Location: schedule.php?id=$exam_id&success=1");
        exit();
    } else {
        $error = "Failed to schedule: " . $conn->error;
    }
}

// Handle deletion
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $schedule_id = $_GET['delete'];
    $delete = $conn->prepare("DELETE FROM exam_schedule WHERE id = ?");
    $delete->bind_param("i", $schedule_id);
    $delete->execute();
    header("Location: schedule.php?id=$exam_id");
    exit();
}
?>

<div class="ml-64 mt-16 p-6">
    <div class="max-w-6xl mx-auto">
        <div class="mb-6">
            <h1 class="text-2xl font-bold text-gray-800">Exam Schedule</h1>
            <p class="text-gray-500 mt-1">Schedule: <?php echo htmlspecialchars($exam['name']); ?></p>
        </div>

        <?php if(isset($_GET['success'])): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-lg mb-4">Schedule saved successfully!</div>
        <?php endif; ?>
        
        <?php if($error): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg mb-4"><?php echo $error; ?></div>
        <?php endif; ?>

        <!-- Add Schedule Form -->
        <div class="bg-white rounded-xl shadow-sm p-6 mb-6">
            <h3 class="text-lg font-semibold mb-4">Add Exam Session</h3>
            <form method="POST" class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Class</label>
                    <select name="class_id" required class="w-full border rounded-lg px-3 py-2">
                        <option value="">Select Class</option>
                        <?php while($class = $classes->fetch_assoc()): ?>
                            <option value="<?php echo $class['id']; ?>"><?php echo htmlspecialchars($class['name']); ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Subject</label>
                    <select name="subject_id" required class="w-full border rounded-lg px-3 py-2">
                        <option value="">Select Subject</option>
                        <?php while($subject = $subjects->fetch_assoc()): ?>
                            <option value="<?php echo $subject['id']; ?>"><?php echo htmlspecialchars($subject['name']); ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Exam Date</label>
                    <input type="date" name="exam_date" required class="w-full border rounded-lg px-3 py-2">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Start Time</label>
                    <input type="time" name="start_time" required class="w-full border rounded-lg px-3 py-2">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">End Time</label>
                    <input type="time" name="end_time" required class="w-full border rounded-lg px-3 py-2">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Venue</label>
                    <input type="text" name="venue" placeholder="e.g., Hall A, Room 101" class="w-full border rounded-lg px-3 py-2">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Invigilator</label>
                    <input type="text" name="invigilator" placeholder="Teacher name" class="w-full border rounded-lg px-3 py-2">
                </div>
                <div class="flex items-end">
                    <button type="submit" class="w-full bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700">
                        <i class="fas fa-plus mr-2"></i> Add
                    </button>
                </div>
            </form>
        </div>

        <!-- Schedule List -->
        <div class="bg-white rounded-xl shadow-sm overflow-hidden">
            <div class="px-6 py-4 border-b bg-gray-50">
                <h3 class="text-lg font-semibold">Exam Schedule</h3>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-gray-50 border-b">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Time</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Class</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Subject</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Venue</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Invigilator</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <?php if ($schedule && $schedule->num_rows > 0): ?>
                            <?php while($item = $schedule->fetch_assoc()): ?>
                                <tr class="hover:bg-gray-50">
                                    <td class="px-6 py-4"><?php echo date('M d, Y', strtotime($item['exam_date'])); ?></td>
                                    <td class="px-6 py-4"><?php echo date('h:i A', strtotime($item['start_time'])); ?> - <?php echo date('h:i A', strtotime($item['end_time'])); ?></td>
                                    <td class="px-6 py-4 font-medium"><?php echo htmlspecialchars($item['class_name']); ?></td>
                                    <td class="px-6 py-4"><?php echo htmlspecialchars($item['subject_name']); ?></td>
                                    <td class="px-6 py-4"><?php echo $item['venue'] ?: '-'; ?></td>
                                    <td class="px-6 py-4"><?php echo $item['invigilator'] ?: '-'; ?></td>
                                    <td class="px-6 py-4">
                                        <a href="?id=<?php echo $exam_id; ?>&delete=<?php echo $item['id']; ?>" 
                                           onclick="return confirm('Remove this schedule?')"
                                           class="text-red-600 hover:text-red-800">
                                            <i class="fas fa-trash-alt"></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" class="px-6 py-12 text-center text-gray-500">
                                    <i class="fas fa-calendar-alt text-4xl mb-2 block"></i>
                                    No schedule added yet
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <div class="mt-4 flex justify-end">
            <a href="index.php" class="text-blue-600 hover:text-blue-800">← Back to Exams</a>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>