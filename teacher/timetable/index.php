<?php
require_once '../../config.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';
requireRole('teacher');

$page_title = 'My Timetable';
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
$classes_query = $conn->prepare("
    SELECT DISTINCT c.id, c.name, c.code
    FROM class_subject cs
    JOIN classes c ON cs.class_id = c.id
    WHERE cs.teacher_id = ?
");
$classes_query->bind_param("i", $teacher_id);
$classes_query->execute();
$classes = $classes_query->get_result();

// Get subjects taught by this teacher
$subjects_query = $conn->prepare("
    SELECT DISTINCT s.id, s.name, s.code
    FROM class_subject cs
    JOIN subjects s ON cs.subject_id = s.id
    WHERE cs.teacher_id = ?
");
$subjects_query->bind_param("i", $teacher_id);
$subjects_query->execute();
$subjects = $subjects_query->get_result();

// Handle form submission
$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $class_id = intval($_POST['class_id'] ?? 0);
    $subject_id = intval($_POST['subject_id'] ?? 0);
    $day_of_week = $_POST['day_of_week'] ?? '';
    $start_time = $_POST['start_time'] ?? '';
    $end_time = $_POST['end_time'] ?? '';
    $classroom = sanitizeInput($_POST['classroom'] ?? '');
    $timetable_id = intval($_POST['timetable_id'] ?? 0);
    
    if ($class_id && $subject_id && $day_of_week && $start_time && $end_time) {
        // Check if time slot already exists
        $check = $conn->prepare("
            SELECT id FROM timetable 
            WHERE class_id = ? AND day_of_week = ? 
            AND ((start_time <= ? AND end_time > ?) OR (start_time < ? AND end_time >= ?))
            AND id != ?
        ");
        $check->bind_param("issssii", $class_id, $day_of_week, $start_time, $start_time, $end_time, $end_time, $timetable_id);
        $check->execute();
        
        if ($check->get_result()->num_rows > 0) {
            $message = 'This time slot is already taken!';
            $message_type = 'danger';
        } else {
            if ($timetable_id > 0) {
                // Update existing
                $update = $conn->prepare("
                    UPDATE timetable 
                    SET class_id = ?, subject_id = ?, day_of_week = ?, 
                        start_time = ?, end_time = ?, classroom = ?
                    WHERE id = ? AND teacher_id = ?
                ");
                $update->bind_param("iissssii", $class_id, $subject_id, $day_of_week, $start_time, $end_time, $classroom, $timetable_id, $teacher_id);
                if ($update->execute()) {
                    $message = 'Timetable entry updated successfully!';
                    $message_type = 'success';
                }
            } else {
                // Insert new
                $insert = $conn->prepare("
                    INSERT INTO timetable (class_id, subject_id, teacher_id, day_of_week, start_time, end_time, classroom)
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                ");
                $insert->bind_param("iiissss", $class_id, $subject_id, $teacher_id, $day_of_week, $start_time, $end_time, $classroom);
                if ($insert->execute()) {
                    $message = 'Timetable entry added successfully!';
                    $message_type = 'success';
                }
            }
        }
    } else {
        $message = 'Please fill all required fields!';
        $message_type = 'danger';
    }
}

// Handle deletion
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $id = $_GET['delete'];
    $delete = $conn->prepare("DELETE FROM timetable WHERE id = ? AND teacher_id = ?");
    $delete->bind_param("ii", $id, $teacher_id);
    if ($delete->execute()) {
        $message = 'Timetable entry deleted successfully!';
        $message_type = 'success';
    }
}

// Get timetable entries for this teacher
$timetable = $conn->prepare("
    SELECT t.*, c.name as class_name, s.name as subject_name, s.code as subject_code,
           DAYNAME(CONCAT('2024-01-', 
               CASE t.day_of_week
                   WHEN 'monday' THEN '1'
                   WHEN 'tuesday' THEN '2'
                   WHEN 'wednesday' THEN '3'
                   WHEN 'thursday' THEN '4'
                   WHEN 'friday' THEN '5'
                   WHEN 'saturday' THEN '6'
               END
           )) as day_name
    FROM timetable t
    JOIN classes c ON t.class_id = c.id
    JOIN subjects s ON t.subject_id = s.id
    WHERE t.teacher_id = ?
    ORDER BY FIELD(t.day_of_week, 'monday','tuesday','wednesday','thursday','friday','saturday'), t.start_time
");
$timetable->bind_param("i", $teacher_id);
$timetable->execute();
$timetable = $timetable->get_result();

// Get entry for editing
$edit_entry = null;
if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $id = $_GET['edit'];
    $edit_query = $conn->prepare("SELECT * FROM timetable WHERE id = ? AND teacher_id = ?");
    $edit_query->bind_param("ii", $id, $teacher_id);
    $edit_query->execute();
    $edit_entry = $edit_query->get_result()->fetch_assoc();
}

$days = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'];
$day_labels = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
?>

<style>
.timetable-grid {
    display: grid;
    grid-template-columns: 100px repeat(6, 1fr);
    gap: 1px;
    background: #e5e7eb;
    border-radius: 12px;
    overflow: hidden;
}
.timetable-cell {
    background: white;
    padding: 12px 8px;
    min-height: 80px;
    font-size: 13px;
}
.timetable-cell.header {
    background: #f3f4f6;
    font-weight: 600;
    text-align: center;
}
.timetable-cell.time {
    background: #f9fafb;
    font-weight: 500;
    text-align: center;
    display: flex;
    align-items: center;
    justify-content: center;
}
.timetable-cell .subject {
    font-weight: 600;
    color: #1f2937;
}
.timetable-cell .class-name {
    font-size: 11px;
    color: #6b7280;
}
.timetable-cell .room {
    font-size: 10px;
    color: #9ca3af;
}
.timetable-cell .actions {
    margin-top: 4px;
    display: flex;
    gap: 4px;
}
.timetable-cell .actions a {
    font-size: 10px;
    padding: 2px 6px;
    border-radius: 4px;
    text-decoration: none;
}
.btn-edit {
    background: #dbeafe;
    color: #1e40af;
}
.btn-delete {
    background: #fee2e2;
    color: #991b1b;
}
.btn-edit:hover { background: #bfdbfe; }
.btn-delete:hover { background: #fecaca; }
</style>

<div class="ml-64 mt-16 p-6 bg-gray-50 min-h-screen">
    <div class="max-w-7xl mx-auto">
        <!-- Header -->
        <div class="mb-6 flex flex-wrap justify-between items-center">
            <div>
                <h1 class="text-2xl font-bold text-gray-800">📅 My Timetable</h1>
                <p class="text-gray-500 mt-1">Manage your weekly teaching schedule</p>
            </div>
            <button onclick="openModal()" class="bg-gradient-to-r from-blue-500 to-purple-600 text-white px-6 py-2 rounded-lg hover:shadow-lg transition">
                <i class="fas fa-plus mr-2"></i> Add Entry
            </button>
        </div>

        <!-- Messages -->
        <?php if($message): ?>
            <div class="bg-<?php echo $message_type; ?>-100 border border-<?php echo $message_type; ?>-400 text-<?php echo $message_type; ?>-700 px-4 py-3 rounded-lg mb-4">
                <i class="fas fa-<?php echo $message_type == 'success' ? 'check-circle' : 'exclamation-circle'; ?> mr-2"></i>
                <?php echo $message; ?>
            </div>
        <?php endif; ?>

        <!-- Timetable Grid -->
        <div class="bg-white rounded-xl shadow-sm overflow-hidden">
            <div class="p-4 border-b">
                <h3 class="font-semibold text-lg">Weekly Schedule</h3>
                <p class="text-sm text-gray-500">Current academic year: <?php echo date('Y'); ?></p>
            </div>
            <div class="p-4 overflow-x-auto">
                <div class="timetable-grid min-w-[700px]">
                    <!-- Header -->
                    <div class="timetable-cell header">Time</div>
                    <?php foreach($day_labels as $label): ?>
                        <div class="timetable-cell header"><?php echo $label; ?></div>
                    <?php endforeach; ?>

                    <!-- Time slots (8am - 4pm) -->
                    <?php for($hour = 8; $hour <= 16; $hour++): 
                        $time_label = date('h:i A', strtotime("$hour:00"));
                    ?>
                        <div class="timetable-cell time"><?php echo $time_label; ?></div>
                        <?php foreach($days as $day): 
                            // Find entry for this time and day
                            $entry = null;
                            $timetable->data_seek(0);
                            while($row = $timetable->fetch_assoc()) {
                                $start_hour = intval(date('H', strtotime($row['start_time'])));
                                if($row['day_of_week'] == $day && $start_hour == $hour) {
                                    $entry = $row;
                                    break;
                                }
                            }
                            $timetable->data_seek(0);
                        ?>
                            <div class="timetable-cell">
                                <?php if($entry): ?>
                                    <div class="subject"><?php echo htmlspecialchars($entry['subject_name']); ?></div>
                                    <div class="class-name"><?php echo htmlspecialchars($entry['class_name']); ?></div>
                                    <div class="room">
                                        <i class="fas fa-door-open mr-1"></i> <?php echo htmlspecialchars($entry['classroom'] ?? 'N/A'); ?>
                                    </div>
                                    <div class="text-xs text-gray-400">
                                        <?php echo date('h:i A', strtotime($entry['start_time'])); ?> - 
                                        <?php echo date('h:i A', strtotime($entry['end_time'])); ?>
                                    </div>
                                    <div class="actions">
                                        <a href="?edit=<?php echo $entry['id']; ?>" class="btn-edit" onclick="openEditModal(<?php echo $entry['id']; ?>)">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <a href="?delete=<?php echo $entry['id']; ?>" class="btn-delete" onclick="return confirm('Delete this entry?')">
                                            <i class="fas fa-trash-alt"></i>
                                        </a>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    <?php endfor; ?>
                </div>
            </div>
        </div>

        <!-- Summary Section -->
        <div class="mt-6 grid grid-cols-1 md:grid-cols-3 gap-4">
            <div class="bg-white rounded-xl shadow-sm p-4">
                <h4 class="font-semibold text-gray-700">Total Classes</h4>
                <p class="text-2xl font-bold text-blue-600"><?php echo $timetable->num_rows; ?></p>
                <p class="text-sm text-gray-500">Weekly teaching sessions</p>
            </div>
            <div class="bg-white rounded-xl shadow-sm p-4">
                <h4 class="font-semibold text-gray-700">Classes Taught</h4>
                <p class="text-2xl font-bold text-green-600"><?php echo $classes->num_rows; ?></p>
                <p class="text-sm text-gray-500">Different classes</p>
            </div>
            <div class="bg-white rounded-xl shadow-sm p-4">
                <h4 class="font-semibold text-gray-700">Subjects</h4>
                <p class="text-2xl font-bold text-purple-600"><?php echo $subjects->num_rows; ?></p>
                <p class="text-sm text-gray-500">Subjects taught</p>
            </div>
        </div>
    </div>
</div>

<!-- Add/Edit Modal -->
<div id="timetableModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center">
    <div class="bg-white rounded-xl w-full max-w-md p-6 max-h-[90vh] overflow-y-auto">
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-lg font-semibold" id="modalTitle">Add Timetable Entry</h3>
            <button onclick="closeModal()" class="text-gray-400 hover:text-gray-600">
                <i class="fas fa-times text-xl"></i>
            </button>
        </div>
        <form method="POST">
            <input type="hidden" name="timetable_id" id="timetable_id" value="0">
            
            <div class="mb-3">
                <label class="block text-sm font-medium text-gray-700 mb-1">Class *</label>
                <select name="class_id" required class="w-full border rounded-lg px-3 py-2">
                    <option value="">Select Class</option>
                    <?php 
                    $classes->data_seek(0);
                    while($class = $classes->fetch_assoc()): ?>
                        <option value="<?php echo $class['id']; ?>"><?php echo htmlspecialchars($class['name']); ?></option>
                    <?php endwhile; ?>
                </select>
            </div>
            
            <div class="mb-3">
                <label class="block text-sm font-medium text-gray-700 mb-1">Subject *</label>
                <select name="subject_id" required class="w-full border rounded-lg px-3 py-2">
                    <option value="">Select Subject</option>
                    <?php 
                    $subjects->data_seek(0);
                    while($subject = $subjects->fetch_assoc()): ?>
                        <option value="<?php echo $subject['id']; ?>"><?php echo htmlspecialchars($subject['name']); ?></option>
                    <?php endwhile; ?>
                </select>
            </div>
            
            <div class="mb-3">
                <label class="block text-sm font-medium text-gray-700 mb-1">Day *</label>
                <select name="day_of_week" required class="w-full border rounded-lg px-3 py-2">
                    <option value="">Select Day</option>
                    <?php foreach($day_labels as $index => $label): ?>
                        <option value="<?php echo $days[$index]; ?>"><?php echo $label; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="grid grid-cols-2 gap-3 mb-3">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Start Time *</label>
                    <input type="time" name="start_time" required class="w-full border rounded-lg px-3 py-2">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">End Time *</label>
                    <input type="time" name="end_time" required class="w-full border rounded-lg px-3 py-2">
                </div>
            </div>
            
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">Classroom</label>
                <input type="text" name="classroom" placeholder="e.g., Room 201" class="w-full border rounded-lg px-3 py-2">
            </div>
            
            <div class="flex justify-end space-x-3 pt-3 border-t">
                <button type="button" onclick="closeModal()" class="px-4 py-2 border rounded-lg hover:bg-gray-50">Cancel</button>
                <button type="submit" class="px-6 py-2 bg-gradient-to-r from-blue-500 to-purple-600 text-white rounded-lg hover:shadow-lg">
                    <i class="fas fa-save mr-2"></i> Save
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function openModal() {
    document.getElementById('modalTitle').textContent = 'Add Timetable Entry';
    document.getElementById('timetable_id').value = '0';
    document.querySelector('form').reset();
    document.getElementById('timetableModal').classList.remove('hidden');
}

function closeModal() {
    document.getElementById('timetableModal').classList.add('hidden');
}

function openEditModal(id) {
    // Fetch entry data via AJAX
    fetch(`get-entry.php?id=${id}`)
        .then(response => response.json())
        .then(data => {
            document.getElementById('modalTitle').textContent = 'Edit Timetable Entry';
            document.getElementById('timetable_id').value = data.id;
            document.querySelector('select[name="class_id"]').value = data.class_id;
            document.querySelector('select[name="subject_id"]').value = data.subject_id;
            document.querySelector('select[name="day_of_week"]').value = data.day_of_week;
            document.querySelector('input[name="start_time"]').value = data.start_time;
            document.querySelector('input[name="end_time"]').value = data.end_time;
            document.querySelector('input[name="classroom"]').value = data.classroom || '';
            document.getElementById('timetableModal').classList.remove('hidden');
        })
        .catch(error => console.error('Error:', error));
}

// Close modal on outside click
document.getElementById('timetableModal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeModal();
    }
});
</script>

<?php include '../../includes/footer.php'; ?>