<?php
require_once '../../config.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';
requireRole('admin');

$subject_id = $_GET['id'] ?? 0;
if (!$subject_id) {
    header('Location: index.php');
    exit();
}

// Get subject info
$query = "SELECT * FROM subjects WHERE id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $subject_id);
$stmt->execute();
$subject = $stmt->get_result()->fetch_assoc();

if (!$subject) {
    header('Location: index.php');
    exit();
}

$page_title = 'Subject Assignments - ' . $subject['name'];
include '../../includes/header.php';
include '../../includes/sidebar.php';
include '../../includes/navbar.php';

// Get assignments for this subject
$assignments = $conn->query("
    SELECT cs.*, 
           c.name as class_name,
           CONCAT(u.first_name, ' ', u.last_name) as teacher_name
    FROM class_subject cs
    JOIN classes c ON cs.class_id = c.id
    JOIN teachers t ON cs.teacher_id = t.id
    JOIN users u ON t.user_id = u.id
    WHERE cs.subject_id = $subject_id
    ORDER BY c.name
");
?>

<div class="ml-64 mt-16 p-6">
    <div class="max-w-4xl mx-auto">
        <!-- Header -->
        <div class="mb-6">
            <div class="flex items-center space-x-3">
                <a href="index.php" class="text-blue-600 hover:text-blue-800">
                    <i class="fas fa-arrow-left"></i> Back to Subjects
                </a>
            </div>
            <div class="mt-4">
                <h1 class="text-2xl font-bold text-gray-800"><?php echo htmlspecialchars($subject['name']); ?></h1>
                <p class="text-gray-500">Code: <?php echo htmlspecialchars($subject['code']); ?> 
                   | Type: <?php echo $subject['is_core'] ? 'Core Subject' : 'Elective Subject'; ?></p>
                <?php if($subject['description']): ?>
                    <p class="text-gray-600 mt-2"><?php echo htmlspecialchars($subject['description']); ?></p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Assignments List -->
        <div class="bg-white rounded-xl shadow-sm overflow-hidden">
            <div class="px-6 py-4 border-b bg-gray-50">
                <h3 class="text-lg font-semibold">Class Assignments</h3>
            </div>
            <div class="divide-y divide-gray-200">
                <?php if ($assignments && $assignments->num_rows > 0): ?>
                    <?php while($assignment = $assignments->fetch_assoc()): ?>
                        <div class="p-6 hover:bg-gray-50">
                            <div class="flex justify-between items-start">
                                <div>
                                    <h4 class="text-lg font-semibold text-gray-800"><?php echo htmlspecialchars($assignment['class_name']); ?></h4>
                                    <div class="mt-2 space-y-1">
                                        <p class="text-sm text-gray-600">
                                            <i class="fas fa-chalkboard-user mr-2 text-blue-500"></i>
                                            Teacher: <?php echo htmlspecialchars($assignment['teacher_name']); ?>
                                        </p>
                                        <p class="text-sm text-gray-600">
                                            <i class="fas fa-calendar mr-2 text-green-500"></i>
                                            Academic Year: <?php echo $assignment['academic_year']; ?>
                                        </p>
                                    </div>
                                </div>
                                <div class="flex space-x-2">
                                    <a href="edit-assignment.php?id=<?php echo $assignment['id']; ?>" 
                                       class="text-blue-600 hover:text-blue-800">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <a href="assign-teacher.php?remove=<?php echo $assignment['id']; ?>" 
                                       onclick="return confirm('Are you sure you want to remove this assignment?')"
                                       class="text-red-600 hover:text-red-800">
                                        <i class="fas fa-trash-alt"></i>
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="p-12 text-center">
                        <i class="fas fa-chalkboard text-6xl text-gray-300 mb-4"></i>
                        <h3 class="text-xl font-semibold text-gray-600">No Assignments Yet</h3>
                        <p class="text-gray-400 mt-2">This subject hasn't been assigned to any class yet</p>
                        <a href="assign-teacher.php" class="inline-block mt-4 text-blue-600 hover:text-blue-800">
                            <i class="fas fa-plus mr-1"></i> Assign to Class
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>