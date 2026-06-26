<?php
require_once '../../config.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';
requireRole('academic');

$page_title = 'Timetable Management';
include '../../includes/header.php';
include '../../includes/sidebar.php';
include '../../includes/navbar.php';

$class_id = $_GET['class_id'] ?? '';
$classes = $conn->query("SELECT id, name FROM classes ORDER BY name");

$days = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'];
$time_slots = ['08:00:00', '09:00:00', '10:00:00', '11:00:00', '12:00:00', '14:00:00', '15:00:00', '16:00:00'];

// Get timetable entries
$timetable = [];
if ($class_id) {
    $query = "SELECT te.*, s.name as subject_name, s.code, CONCAT(u.first_name, ' ', u.last_name) as teacher_name
              FROM timetable_entries te
              JOIN subjects s ON te.subject_id = s.id
              JOIN teachers t ON te.teacher_id = t.id
              JOIN users u ON t.user_id = u.id
              WHERE te.class_id = ?
              ORDER BY te.day_of_week, te.start_time";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $class_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $timetable[$row['day_of_week']][$row['start_time']] = $row;
    }
}
?>

<div class="ml-64 mt-16 p-6">
    <div class="mb-6 flex justify-between items-center">
        <div>
            <h1 class="text-2xl font-bold text-gray-800">Timetable Management</h1>
            <p class="text-gray-500 mt-1">Create and manage class timetables</p>
        </div>
        <a href="create.php" class="bg-gradient-to-r from-blue-500 to-purple-600 text-white px-6 py-2 rounded-lg hover:shadow-lg">
            <i class="fas fa-plus mr-2"></i> Add Entry
        </a>
    </div>

    <!-- Class Filter -->
    <div class="bg-white rounded-xl shadow-sm p-4 mb-6">
        <form method="GET" class="flex gap-4">
            <div class="flex-1">
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
            <?php if($class_id): ?>
                <div class="flex items-end">
                    <a href="publish.php?class_id=<?php echo $class_id; ?>" class="bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700">
                        <i class="fas fa-print mr-2"></i> Publish Timetable
                    </a>
                </div>
            <?php endif; ?>
        </form>
    </div>

    <?php if($class_id): ?>
        <!-- Timetable Display -->
        <div class="bg-white rounded-xl shadow-sm overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead>
                        <tr class="bg-gray-50 border-b">
                            <th class="px-4 py-3 text-left text-sm font-semibold">Time / Day</th>
                            <?php foreach($days as $day): ?>
                                <th class="px-4 py-3 text-left text-sm font-semibold capitalize"><?php echo $day; ?></th>
                            <?php endforeach; ?>
                            <th class="px-4 py-3 text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($time_slots as $slot): ?>
                            <tr class="border-b hover:bg-gray-50">
                                <td class="px-4 py-3 font-medium text-sm">
                                    <?php echo date('h:i A', strtotime($slot)); ?>
                                </td>
                                <?php foreach($days as $day): ?>
                                    <td class="px-4 py-3">
                                        <?php if(isset($timetable[$day][$slot])): 
                                            $entry = $timetable[$day][$slot];
                                        ?>
                                            <div class="bg-blue-50 rounded-lg p-2">
                                                <p class="font-medium text-sm"><?php echo htmlspecialchars($entry['subject_name']); ?></p>
                                                <p class="text-xs text-gray-500"><?php echo htmlspecialchars($entry['teacher_name']); ?></p>
                                                <?php if($entry['classroom']): ?>
                                                    <p class="text-xs text-gray-400">Rm: <?php echo $entry['classroom']; ?></p>
                                                <?php endif; ?>
                                            </div>
                                        <?php else: ?>
                                            <div class="text-gray-300 text-sm">—</div>
                                        <?php endif; ?>
                                    </td>
                                <?php endforeach; ?>
                                <td class="px-4 py-3 text-center">
                                    <?php if(isset($timetable[$day][$slot])): ?>
                                        <a href="edit.php?id=<?php echo $timetable[$day][$slot]['id']; ?>" class="text-blue-600 hover:text-blue-800 mr-2">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <a href="delete.php?id=<?php echo $timetable[$day][$slot]['id']; ?>&class_id=<?php echo $class_id; ?>" 
                                           onclick="return confirm('Delete this timetable entry?')"
                                           class="text-red-600 hover:text-red-800">
                                            <i class="fas fa-trash-alt"></i>
                                        </a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php else: ?>
        <div class="bg-white rounded-xl shadow-sm p-12 text-center">
            <i class="fas fa-calendar-alt text-6xl text-gray-300 mb-4"></i>
            <p class="text-gray-500">Select a class to view or manage its timetable</p>
        </div>
    <?php endif; ?>
</div>

<?php include '../../includes/footer.php'; ?>