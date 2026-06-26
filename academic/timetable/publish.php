<?php
require_once '../../config.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';
requireRole('academic');

$class_id = $_GET['class_id'] ?? 0;
$error = '';
$success = '';

if (!$class_id) {
    header('Location: index.php');
    exit();
}

// Get class name
$class_query = $conn->prepare("SELECT name FROM classes WHERE id = ?");
$class_query->bind_param("i", $class_id);
$class_query->execute();
$class = $class_query->get_result()->fetch_assoc();

// Get timetable entries
$query = "SELECT te.*, s.name as subject_name, s.code, 
          CONCAT(u.first_name, ' ', u.last_name) as teacher_name
          FROM timetable_entries te
          JOIN subjects s ON te.subject_id = s.id
          JOIN teachers t ON te.teacher_id = t.id
          JOIN users u ON t.user_id = u.id
          WHERE te.class_id = ?
          ORDER BY te.day_of_week, te.start_time";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $class_id);
$stmt->execute();
$timetable = $stmt->get_result();

$days = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'];
$time_slots = [];

// Group by day and time
$schedule = [];
while ($row = $timetable->fetch_assoc()) {
    $schedule[$row['day_of_week']][$row['start_time']] = $row;
    if (!in_array($row['start_time'], $time_slots)) {
        $time_slots[] = $row['start_time'];
    }
}
sort($time_slots);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Timetable - <?php echo htmlspecialchars($class['name']); ?> | Smart School LMS</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        @media print {
            .no-print { display: none; }
            body { background: white; }
            .timetable-container { margin: 0; padding: 0; }
        }
        .timetable-cell {
            transition: all 0.2s ease;
        }
        .timetable-cell:hover {
            transform: scale(1.02);
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }
    </style>
</head>
<body class="bg-gray-100">
    <div class="container mx-auto px-4 py-8">
        <!-- Header -->
        <div class="text-center mb-8">
            <div class="flex justify-between items-center no-print mb-4">
                <a href="index.php" class="text-blue-600 hover:text-blue-800">
                    <i class="fas fa-arrow-left mr-2"></i> Back
                </a>
                <button onclick="window.print()" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700">
                    <i class="fas fa-print mr-2"></i> Print / Download PDF
                </button>
            </div>
            <div class="inline-block p-4 bg-gradient-to-r from-blue-500 to-purple-600 rounded-2xl text-white">
                <i class="fas fa-calendar-alt text-3xl mb-2 block"></i>
                <h1 class="text-2xl font-bold">Class Timetable</h1>
                <p class="text-lg mt-1"><?php echo htmlspecialchars($class['name']); ?></p>
                <p class="text-sm opacity-75">Academic Year: <?php echo date('Y'); ?></p>
            </div>
        </div>

        <!-- Timetable Table -->
        <div class="bg-white rounded-xl shadow-lg overflow-hidden timetable-container">
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead>
                        <tr class="bg-gradient-to-r from-blue-500 to-purple-600 text-white">
                            <th class="px-4 py-3 text-left">Time</th>
                            <?php foreach($days as $day): ?>
                                <th class="px-4 py-3 text-left capitalize"><?php echo $day; ?></th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($time_slots) > 0): ?>
                            <?php foreach($time_slots as $slot): ?>
                                <tr class="border-b hover:bg-gray-50">
                                    <td class="px-4 py-3 font-medium bg-gray-50">
                                        <?php echo date('h:i A', strtotime($slot)); ?>
                                    </td>
                                    <?php foreach($days as $day): ?>
                                        <td class="px-3 py-2">
                                            <?php if(isset($schedule[$day][$slot])): 
                                                $entry = $schedule[$day][$slot];
                                            ?>
                                                <div class="timetable-cell bg-blue-50 rounded-lg p-2 border-l-4 border-blue-500">
                                                    <p class="font-semibold text-sm"><?php echo htmlspecialchars($entry['subject_name']); ?></p>
                                                    <p class="text-xs text-gray-600"><?php echo htmlspecialchars($entry['teacher_name']); ?></p>
                                                    <?php if($entry['classroom']): ?>
                                                        <p class="text-xs text-gray-400 mt-1">
                                                            <i class="fas fa-door-open"></i> <?php echo $entry['classroom']; ?>
                                                        </p>
                                                    <?php endif; ?>
                                                </div>
                                            <?php else: ?>
                                                <div class="text-gray-300 text-center py-2">—</div>
                                            <?php endif; ?>
                                        </td>
                                    <?php endforeach; ?>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" class="px-4 py-12 text-center text-gray-500">
                                    <i class="fas fa-calendar-times text-4xl mb-2 block"></i>
                                    No timetable entries found for this class.
                                    <a href="create.php?class_id=<?php echo $class_id; ?>" class="block text-blue-600 mt-2">Add timetable entries →</a>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Footer -->
        <div class="text-center text-gray-500 text-sm mt-8 no-print">
            <p>Generated on: <?php echo date('F d, Y h:i A'); ?></p>
            <p>Smart School LMS - Academic Management System</p>
        </div>
    </div>
</body>
</html>