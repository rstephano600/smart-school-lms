<?php
require_once '../config.php';
require_once '../includes/auth.php';
requireRole('teacher');

// Get teacher ID
$teacher_query = $conn->prepare("SELECT id FROM teachers WHERE user_id = ?");
$teacher_query->bind_param("i", $_SESSION['user_id']);
$teacher_query->execute();
$teacher = $teacher_query->get_result()->fetch_assoc();
$teacher_id = $teacher['id'] ?? 0;

// Get online students
$online_students = $conn->prepare("
    SELECT u.id, u.first_name, u.last_name, u.last_activity,
           s.admission_number, c.name as class_name,
           TIMESTAMPDIFF(MINUTE, u.last_activity, NOW()) as minutes_ago
    FROM users u
    JOIN students s ON u.id = s.user_id
    JOIN classes c ON s.class_id = c.id
    WHERE u.last_activity > DATE_SUB(NOW(), INTERVAL 5 MINUTE)
    AND u.role = 'student'
    AND c.id IN (SELECT DISTINCT class_id FROM class_subject WHERE teacher_id = ?)
    ORDER BY u.last_activity DESC
");
$online_students->bind_param("i", $teacher_id);
$online_students->execute();
$online_students = $online_students->get_result();

if ($online_students->num_rows > 0): ?>
    <?php while($student = $online_students->fetch_assoc()): ?>
        <div class="p-4 hover:bg-gray-50 flex items-center justify-between">
            <div class="flex items-center space-x-3">
                <div class="w-8 h-8 bg-green-100 rounded-full flex items-center justify-center">
                    <i class="fas fa-user text-green-600"></i>
                </div>
                <div>
                    <p class="font-medium text-sm"><?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?></p>
                    <p class="text-xs text-gray-500"><?php echo htmlspecialchars($student['class_name']); ?></p>
                </div>
            </div>
            <div class="text-right">
                <span class="px-2 py-0.5 bg-green-100 text-green-700 text-xs rounded-full">
                    <i class="fas fa-circle text-green-500 text-[6px] mr-1"></i> Online
                </span>
                <p class="text-xs text-gray-400"><?php echo $student['minutes_ago']; ?> min ago</p>
            </div>
        </div>
    <?php endwhile; ?>
<?php else: ?>
    <div class="p-8 text-center text-gray-500">
        <i class="fas fa-users text-3xl mb-2 block"></i>
        No students currently online
    </div>
<?php endif; ?>