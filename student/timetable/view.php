<?php
require_once '../../config.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';
requireRole('student');

$page_title = 'My Timetable';
include '../../includes/header.php';
include '../../includes/sidebar.php';
include '../../includes/navbar.php';

// Get student class
$student_query = $conn->prepare("
    SELECT s.id, s.class_id, c.name as class_name
    FROM students s
    JOIN classes c ON s.class_id = c.id
    WHERE s.user_id = ?
");
$student_query->bind_param("i", $_SESSION['user_id']);
$student_query->execute();
$student = $student_query->get_result()->fetch_assoc();

if (!$student) {
    echo "<div class='ml-64 mt-16 p-6'>Student record not found!</div>";
    include '../../includes/footer.php';
    exit();
}

$class_id = $student['class_id'];
$class_name = $student['class_name'];

// Get timetable for this class
$timetable = $conn->prepare("
    SELECT t.*, s.name as subject_name, s.code as subject_code,
           CONCAT(u.first_name, ' ', u.last_name) as teacher_name,
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
    JOIN subjects s ON t.subject_id = s.id
    JOIN teachers te ON t.teacher_id = te.id
    JOIN users u ON te.user_id = u.id
    WHERE t.class_id = ? AND t.is_active = 1
    ORDER BY FIELD(t.day_of_week, 'monday','tuesday','wednesday','thursday','friday','saturday'), t.start_time
");
$timetable->bind_param("i", $class_id);
$timetable->execute();
$timetable = $timetable->get_result();

$days = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'];
$day_labels = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
$today = strtolower(date('l'));
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
.timetable-cell.today {
    background: #eff6ff;
    border: 2px solid #3b82f6;
}
.timetable-cell .subject {
    font-weight: 600;
    color: #1f2937;
}
.timetable-cell .teacher {
    font-size: 11px;
    color: #6b7280;
}
.timetable-cell .room {
    font-size: 10px;
    color: #9ca3af;
}
.timetable-cell .time-slot {
    font-size: 10px;
    color: #6b7280;
    margin-top: 2px;
}
.current-day-badge {
    background: #3b82f6;
    color: white;
    font-size: 10px;
    padding: 2px 8px;
    border-radius: 10px;
    display: inline-block;
}
</style>

<div class="ml-64 mt-16 p-6 bg-gray-50 min-h-screen">
    <div class="max-w-7xl mx-auto">
        <!-- Header -->
        <div class="mb-6">
            <h1 class="text-2xl font-bold text-gray-800">📅 My Timetable</h1>
            <p class="text-gray-500 mt-1">
                <i class="fas fa-graduation-cap mr-1"></i> 
                <?php echo htmlspecialchars($class_name); ?> • 
                <span class="text-blue-600">Today: <?php echo date('l, F j, Y'); ?></span>
                <?php if($timetable->num_rows > 0): ?>
                    <span class="ml-2 text-sm text-green-600">
                        <i class="fas fa-check-circle mr-1"></i> 
                        <?php echo $timetable->num_rows; ?> classes scheduled
                    </span>
                <?php endif; ?>
            </p>
        </div>

        <!-- Legend -->
        <div class="bg-white rounded-xl shadow-sm p-3 mb-4 flex flex-wrap items-center gap-4">
            <span class="text-sm text-gray-600 font-medium">Legend:</span>
            <span class="flex items-center text-sm">
                <span class="w-4 h-4 bg-blue-100 border-2 border-blue-500 rounded mr-1"></span>
                Today's classes
            </span>
            <span class="flex items-center text-sm">
                <span class="w-4 h-4 bg-white border border-gray-300 rounded mr-1"></span>
                Other days
            </span>
        </div>

        <!-- Timetable Grid -->
        <div class="bg-white rounded-xl shadow-sm overflow-hidden">
            <div class="p-4 border-b">
                <h3 class="font-semibold text-lg">Weekly Schedule</h3>
                <p class="text-sm text-gray-500">Academic Year: <?php echo date('Y'); ?></p>
            </div>
            <div class="p-4 overflow-x-auto">
                <div class="timetable-grid min-w-[700px]">
                    <!-- Header -->
                    <div class="timetable-cell header">Time</div>
                    <?php foreach($day_labels as $index => $label): 
                        $is_today = $days[$index] == $today;
                    ?>
                        <div class="timetable-cell header <?php echo $is_today ? 'today' : ''; ?>">
                            <?php echo $label; ?>
                            <?php if($is_today): ?>
                                <span class="current-day-badge ml-1">Today</span>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>

                    <!-- Time slots (8am - 4pm) -->
                    <?php for($hour = 8; $hour <= 16; $hour++): 
                        $time_label = date('h:i A', strtotime("$hour:00"));
                    ?>
                        <div class="timetable-cell time"><?php echo $time_label; ?></div>
                        <?php foreach($days as $day): 
                            $is_today = $day == $today;
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
                            <div class="timetable-cell <?php echo $is_today && $entry ? 'today' : ''; ?>">
                                <?php if($entry): ?>
                                    <div class="subject"><?php echo htmlspecialchars($entry['subject_name']); ?></div>
                                    <div class="teacher">
                                        <i class="fas fa-user mr-1"></i> <?php echo htmlspecialchars($entry['teacher_name']); ?>
                                    </div>
                                    <?php if($entry['classroom']): ?>
                                        <div class="room">
                                            <i class="fas fa-door-open mr-1"></i> <?php echo htmlspecialchars($entry['classroom']); ?>
                                        </div>
                                    <?php endif; ?>
                                    <div class="time-slot">
                                        <?php echo date('h:i A', strtotime($entry['start_time'])); ?> - 
                                        <?php echo date('h:i A', strtotime($entry['end_time'])); ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    <?php endfor; ?>
                </div>
            </div>
        </div>

        <!-- Class List View -->
        <div class="mt-6 bg-white rounded-xl shadow-sm overflow-hidden">
            <div class="p-4 border-b">
                <h3 class="font-semibold text-lg">All Classes</h3>
                <p class="text-sm text-gray-500">Complete list of your weekly classes</p>
            </div>
            <div class="divide-y max-h-80 overflow-y-auto">
                <?php if($timetable && $timetable->num_rows > 0): 
                    $timetable->data_seek(0);
                    while($entry = $timetable->fetch_assoc()): 
                        $is_today = $entry['day_of_week'] == $today;
                ?>
                    <div class="p-4 hover:bg-gray-50 flex justify-between items-center <?php echo $is_today ? 'bg-blue-50' : ''; ?>">
                        <div class="flex items-center gap-4">
                            <div class="w-10 h-10 rounded-full flex items-center justify-center <?php echo $is_today ? 'bg-blue-500 text-white' : 'bg-gray-200 text-gray-600'; ?>">
                                <i class="fas fa-<?php echo $is_today ? 'check-circle' : 'book'; ?>"></i>
                            </div>
                            <div>
                                <p class="font-semibold"><?php echo htmlspecialchars($entry['subject_name']); ?></p>
                                <p class="text-sm text-gray-500">
                                    <i class="fas fa-user mr-1"></i> <?php echo htmlspecialchars($entry['teacher_name']); ?>
                                    <?php if($entry['classroom']): ?>
                                        <span class="mx-1">•</span>
                                        <i class="fas fa-door-open mr-1"></i> <?php echo htmlspecialchars($entry['classroom']); ?>
                                    <?php endif; ?>
                                </p>
                            </div>
                        </div>
                        <div class="text-right">
                            <p class="text-sm font-medium text-blue-600">
                                <?php echo ucfirst($entry['day_of_week']); ?>
                            </p>
                            <p class="text-xs text-gray-400">
                                <?php echo date('h:i A', strtotime($entry['start_time'])); ?> - 
                                <?php echo date('h:i A', strtotime($entry['end_time'])); ?>
                            </p>
                            <?php if($is_today): ?>
                                <span class="text-xs text-green-600 font-medium">Today</span>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endwhile; 
                else: ?>
                    <div class="p-8 text-center text-gray-500">
                        <i class="fas fa-calendar-day text-3xl mb-2 block"></i>
                        No timetable entries found for your class
                        <p class="text-sm text-gray-400 mt-1">Your teacher hasn't set up the timetable yet</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>