<?php
require_once '../../config.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';
requireRole('academic');

$page_title = 'Add Timetable Entry';
include '../../includes/header.php';
include '../../includes/sidebar.php';
include '../../includes/navbar.php';

$error = '';
$success = '';

// Get data for dropdowns
$classes = $conn->query("SELECT id, name FROM classes ORDER BY name");
$days = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $class_id = intval($_POST['class_id']);
    $subject_id = intval($_POST['subject_id']);
    $teacher_id = intval($_POST['teacher_id']);
    $day_of_week = $_POST['day_of_week'];
    $start_time = $_POST['start_time'];
    $end_time = $_POST['end_time'];
    $classroom = sanitizeInput($_POST['classroom']);
    
    // Check for conflicts
    $check = $conn->prepare("SELECT id FROM timetable_entries 
                             WHERE class_id = ? AND day_of_week = ? AND 
                             ((start_time <= ? AND end_time > ?) OR (start_time < ? AND end_time >= ?))");
    $check->bind_param("isssss", $class_id, $day_of_week, $start_time, $start_time, $end_time, $end_time);
    $check->execute();
    
    if ($check->get_result()->num_rows > 0) {
        $error = "Time slot conflict! Another class is scheduled at this time.";
    } else {
        $query = "INSERT INTO timetable_entries (class_id, subject_id, teacher_id, day_of_week, start_time, end_time, classroom) 
                  VALUES (?, ?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("iiissss", $class_id, $subject_id, $teacher_id, $day_of_week, $start_time, $end_time, $classroom);
        
        if ($stmt->execute()) {
            logActivity($_SESSION['user_id'], 'added timetable entry', 'timetable', $conn->insert_id);
            $success = "Timetable entry added successfully!";
            $_POST = [];
        } else {
            $error = "Failed to add entry: " . $conn->error;
        }
    }
}

// Get subjects and teachers based on selected class via AJAX
?>

<div class="ml-64 mt-16 p-6">
    <div class="max-w-2xl mx-auto">
        <div class="mb-6">
            <h1 class="text-2xl font-bold text-gray-800">Add Timetable Entry</h1>
            <p class="text-gray-500 mt-1">Schedule a class for a specific day and time</p>
        </div>

        <?php if($error): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg mb-4"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <?php if($success): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-lg mb-4"><?php echo $success; ?></div>
        <?php endif; ?>

        <div class="bg-white rounded-xl shadow-sm p-6">
            <form method="POST" id="timetableForm">
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Class *</label>
                    <select name="class_id" id="class_id" required class="w-full border rounded-lg px-3 py-2">
                        <option value="">Select Class</option>
                        <?php while($class = $classes->fetch_assoc()): ?>
                            <option value="<?php echo $class['id']; ?>" <?php echo ($_POST['class_id'] ?? '') == $class['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($class['name']); ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>

                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Subject *</label>
                    <select name="subject_id" id="subject_id" required class="w-full border rounded-lg px-3 py-2">
                        <option value="">Select Subject</option>
                    </select>
                </div>

                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Teacher *</label>
                    <select name="teacher_id" id="teacher_id" required class="w-full border rounded-lg px-3 py-2">
                        <option value="">Select Teacher</option>
                    </select>
                </div>

                <div class="grid grid-cols-2 gap-4 mb-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Day *</label>
                        <select name="day_of_week" required class="w-full border rounded-lg px-3 py-2">
                            <option value="">Select Day</option>
                            <?php foreach($days as $day): ?>
                                <option value="<?php echo $day; ?>" <?php echo ($_POST['day_of_week'] ?? '') == $day ? 'selected' : ''; ?>>
                                    <?php echo ucfirst($day); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Classroom</label>
                        <input type="text" name="classroom" value="<?php echo htmlspecialchars($_POST['classroom'] ?? ''); ?>"
                               placeholder="e.g., Room 101, Lab 2"
                               class="w-full border rounded-lg px-3 py-2">
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-4 mb-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Start Time *</label>
                        <input type="time" name="start_time" required value="<?php echo htmlspecialchars($_POST['start_time'] ?? '08:00'); ?>"
                               class="w-full border rounded-lg px-3 py-2">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">End Time *</label>
                        <input type="time" name="end_time" required value="<?php echo htmlspecialchars($_POST['end_time'] ?? '09:00'); ?>"
                               class="w-full border rounded-lg px-3 py-2">
                    </div>
                </div>

                <div class="flex justify-end space-x-3 mt-6 pt-4 border-t">
                    <a href="index.php" class="px-4 py-2 border rounded-lg hover:bg-gray-50">Cancel</a>
                    <button type="submit" class="px-6 py-2 bg-gradient-to-r from-blue-500 to-purple-600 text-white rounded-lg hover:shadow-lg">
                        <i class="fas fa-save mr-2"></i> Save Entry
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.getElementById('class_id').addEventListener('change', function() {
    const classId = this.value;
    if (classId) {
        // Fetch subjects for this class
        fetch(`../../api/get-class-subjects.php?class_id=${classId}`)
            .then(response => response.json())
            .then(data => {
                const subjectSelect = document.getElementById('subject_id');
                subjectSelect.innerHTML = '<option value="">Select Subject</option>';
                data.subjects.forEach(subject => {
                    subjectSelect.innerHTML += `<option value="${subject.id}">${subject.name}</option>`;
                });
            });
        
        // Fetch teachers for this class
        fetch(`../../api/get-class-teachers.php?class_id=${classId}`)
            .then(response => response.json())
            .then(data => {
                const teacherSelect = document.getElementById('teacher_id');
                teacherSelect.innerHTML = '<option value="">Select Teacher</option>';
                data.teachers.forEach(teacher => {
                    teacherSelect.innerHTML += `<option value="${teacher.id}">${teacher.name}</option>`;
                });
            });
    }
});
</script>

<?php include '../../includes/footer.php'; ?>