<?php
require_once '../../config.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';
requireRole('academic');

$page_title = 'Report Cards Management';
include '../../includes/header.php';
include '../../includes/sidebar.php';
include '../../includes/navbar.php';

// Get filter parameters
$class_id = $_GET['class_id'] ?? '';
$exam_id = $_GET['exam_id'] ?? '';
$student_id = $_GET['student_id'] ?? '';

// Get classes
$classes = $conn->query("SELECT id, name FROM classes ORDER BY name");

// Get exams
$exams = $conn->query("SELECT id, name, term, year FROM exams WHERE is_published = 1 ORDER BY year DESC, term DESC");

// Get students for selected class
$students = [];
if ($class_id) {
    $std_query = $conn->prepare("
        SELECT s.id, s.admission_number, CONCAT(u.first_name, ' ', u.last_name) as name
        FROM students s
        JOIN users u ON s.user_id = u.id
        WHERE s.class_id = ?
        ORDER BY u.first_name
    ");
    $std_query->bind_param("i", $class_id);
    $std_query->execute();
    $students = $std_query->get_result();
}

// Get generated report cards
$report_cards = [];
$rc_query = "SELECT rc.*, 
             CONCAT(u.first_name, ' ', u.last_name) as student_name,
             s.admission_number,
             c.name as class_name,
             e.name as exam_name
             FROM report_cards rc
             JOIN students s ON rc.student_id = s.id
             JOIN users u ON s.user_id = u.id
             JOIN classes c ON s.class_id = c.id
             JOIN exams e ON rc.exam_id = e.id
             WHERE 1=1";

if ($class_id) {
    $rc_query .= " AND s.class_id = $class_id";
}
if ($exam_id) {
    $rc_query .= " AND rc.exam_id = $exam_id";
}
if ($student_id) {
    $rc_query .= " AND rc.student_id = $student_id";
}
$rc_query .= " ORDER BY rc.created_at DESC LIMIT 50";

$report_cards = $conn->query($rc_query);
?>

<div class="ml-64 mt-16 p-6">
    <div class="max-w-6xl mx-auto">
        <div class="mb-6 flex justify-between items-center">
            <div>
                <h1 class="text-2xl font-bold text-gray-800">Report Cards</h1>
                <p class="text-gray-500 mt-1">Generate and manage student report cards</p>
            </div>
            <a href="generate.php" class="bg-gradient-to-r from-blue-500 to-purple-600 text-white px-6 py-2 rounded-lg hover:shadow-lg">
                <i class="fas fa-plus mr-2"></i> Generate New Report Card
            </a>
        </div>

        <!-- Filters -->
        <div class="bg-white rounded-xl shadow-sm p-4 mb-6">
            <form method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Class</label>
                    <select name="class_id" class="w-full border rounded-lg px-3 py-2" onchange="this.form.submit()">
                        <option value="">All Classes</option>
                        <?php while($class = $classes->fetch_assoc()): ?>
                            <option value="<?php echo $class['id']; ?>" <?php echo $class_id == $class['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($class['name']); ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Exam</label>
                    <select name="exam_id" class="w-full border rounded-lg px-3 py-2" onchange="this.form.submit()">
                        <option value="">All Exams</option>
                        <?php while($exam = $exams->fetch_assoc()): ?>
                            <option value="<?php echo $exam['id']; ?>" <?php echo $exam_id == $exam['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($exam['name'] . ' - ' . $exam['term'] . ' ' . $exam['year']); ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Student</label>
                    <select name="student_id" class="w-full border rounded-lg px-3 py-2" onchange="this.form.submit()">
                        <option value="">All Students</option>
                        <?php if ($class_id): ?>
                            <?php while($student = $students->fetch_assoc()): ?>
                                <option value="<?php echo $student['id']; ?>" <?php echo $student_id == $student['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($student['name'] . ' (' . $student['admission_number'] . ')'); ?>
                                </option>
                            <?php endwhile; ?>
                        <?php endif; ?>
                    </select>
                </div>
                <div class="flex items-end">
                    <button type="submit" class="w-full bg-gray-600 text-white px-4 py-2 rounded-lg hover:bg-gray-700">
                        <i class="fas fa-filter mr-2"></i> Filter
                    </button>
                </div>
            </form>
        </div>

        <!-- Report Cards List -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            <?php if ($report_cards && $report_cards->num_rows > 0): ?>
                <?php while($rc = $report_cards->fetch_assoc()): ?>
                    <div class="bg-white rounded-xl shadow-sm hover:shadow-lg transition-all duration-300 overflow-hidden">
                        <div class="bg-gradient-to-r from-green-500 to-teal-600 p-4">
                            <div class="flex justify-between items-center">
                                <div>
                                    <h3 class="text-white font-bold"><?php echo htmlspecialchars($rc['student_name']); ?></h3>
                                    <p class="text-green-100 text-sm"><?php echo $rc['admission_number']; ?></p>
                                </div>
                                <div class="w-12 h-12 bg-white bg-opacity-20 rounded-full flex items-center justify-center">
                                    <i class="fas fa-file-alt text-white text-xl"></i>
                                </div>
                            </div>
                        </div>
                        <div class="p-4">
                            <div class="space-y-2 text-sm">
                                <div class="flex justify-between">
                                    <span class="text-gray-500">Class:</span>
                                    <span class="font-medium"><?php echo $rc['class_name']; ?></span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-500">Exam:</span>
                                    <span class="font-medium"><?php echo htmlspecialchars($rc['exam_name']); ?></span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-500">Generated:</span>
                                    <span class="font-medium"><?php echo date('M d, Y', strtotime($rc['created_at'])); ?></span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-500">Status:</span>
                                    <span class="px-2 py-1 text-xs rounded-full <?php echo $rc['is_downloaded'] ? 'bg-green-100 text-green-700' : 'bg-yellow-100 text-yellow-700'; ?>">
                                        <?php echo $rc['is_downloaded'] ? 'Downloaded' : 'Pending'; ?>
                                    </span>
                                </div>
                            </div>
                            <div class="flex justify-end space-x-2 mt-4 pt-3 border-t">
                                <a href="download.php?id=<?php echo $rc['id']; ?>" class="bg-blue-600 text-white px-3 py-1 rounded-lg text-sm hover:bg-blue-700">
                                    <i class="fas fa-download mr-1"></i> PDF
                                </a>
                                <a href="view.php?id=<?php echo $rc['id']; ?>" class="text-blue-600 hover:text-blue-800 p-1">
                                    <i class="fas fa-eye"></i>
                                </a>
                                <a href="delete.php?id=<?php echo $rc['id']; ?>" onclick="return confirm('Delete this report card?')" class="text-red-600 hover:text-red-800 p-1">
                                    <i class="fas fa-trash-alt"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="col-span-full">
                    <div class="bg-white rounded-xl shadow-sm p-12 text-center">
                        <i class="fas fa-file-alt text-6xl text-gray-300 mb-4"></i>
                        <h3 class="text-xl font-semibold text-gray-600">No Report Cards Found</h3>
                        <p class="text-gray-400 mt-2">Click "Generate New Report Card" to create one</p>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>