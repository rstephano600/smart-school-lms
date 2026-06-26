<?php
require_once '../../config.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';
requireRole('teacher');

$page_title = 'Learning Materials';
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

// Get filter parameters
$class_filter = $_GET['class_id'] ?? '';
$subject_filter = $_GET['subject_id'] ?? '';
$type_filter = $_GET['type'] ?? '';

// Build materials query
$query = "SELECT lm.*, s.name as subject_name, c.name as class_name,
          CONCAT(u.first_name, ' ', u.last_name) as teacher_name
          FROM learning_materials lm
          JOIN subjects s ON lm.subject_id = s.id
          JOIN classes c ON lm.class_id = c.id
          JOIN users u ON lm.uploaded_by = u.id
          WHERE lm.uploaded_by = ?";
$params = [$_SESSION['user_id']];
$types = "i";

if ($class_filter) {
    $query .= " AND lm.class_id = ?";
    $params[] = $class_filter;
    $types .= "i";
}
if ($subject_filter) {
    $query .= " AND lm.subject_id = ?";
    $params[] = $subject_filter;
    $types .= "i";
}
if ($type_filter) {
    $query .= " AND lm.type = ?";
    $params[] = $type_filter;
    $types .= "s";
}

$query .= " ORDER BY lm.uploaded_at DESC";

$stmt = $conn->prepare($query);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$materials = $stmt->get_result();

// Get subjects for filter based on class
$subjects = [];
if ($class_filter) {
    $subj_query = $conn->prepare("
        SELECT s.id, s.name 
        FROM subjects s
        JOIN class_subject cs ON s.id = cs.subject_id
        WHERE cs.class_id = ? AND cs.teacher_id = ?
    ");
    $subj_query->bind_param("ii", $class_filter, $teacher_id);
    $subj_query->execute();
    $subjects = $subj_query->get_result();
}
?>

<div class="ml-64 mt-16 p-6">
    <div class="mb-6 flex justify-between items-center">
        <div>
            <h1 class="text-2xl font-bold text-gray-800">Learning Materials</h1>
            <p class="text-gray-500 mt-1">Upload and manage teaching materials</p>
        </div>
        <a href="upload.php" class="bg-gradient-to-r from-blue-500 to-purple-600 text-white px-6 py-2 rounded-lg hover:shadow-lg">
            <i class="fas fa-upload mr-2"></i> Upload Material
        </a>
    </div>

    <!-- Filters -->
    <div class="bg-white rounded-xl shadow-sm p-4 mb-6">
        <form method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Class</label>
                <select name="class_id" class="w-full border rounded-lg px-3 py-2" onchange="this.form.submit()">
                    <option value="">All Classes</option>
                    <?php 
                    $classes->data_seek(0);
                    while($class = $classes->fetch_assoc()): ?>
                        <option value="<?php echo $class['id']; ?>" <?php echo $class_filter == $class['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($class['name']); ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Subject</label>
                <select name="subject_id" class="w-full border rounded-lg px-3 py-2" onchange="this.form.submit()">
                    <option value="">All Subjects</option>
                    <?php if ($class_filter && $subjects): ?>
                        <?php while($subject = $subjects->fetch_assoc()): ?>
                            <option value="<?php echo $subject['id']; ?>" <?php echo $subject_filter == $subject['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($subject['name']); ?>
                            </option>
                        <?php endwhile; ?>
                    <?php endif; ?>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Type</label>
                <select name="type" class="w-full border rounded-lg px-3 py-2" onchange="this.form.submit()">
                    <option value="">All Types</option>
                    <option value="note" <?php echo $type_filter == 'note' ? 'selected' : ''; ?>>Notes</option>
                    <option value="video" <?php echo $type_filter == 'video' ? 'selected' : ''; ?>>Videos</option>
                    <option value="presentation" <?php echo $type_filter == 'presentation' ? 'selected' : ''; ?>>Presentations</option>
                    <option value="resource" <?php echo $type_filter == 'resource' ? 'selected' : ''; ?>>Resources</option>
                </select>
            </div>
            <div class="flex items-end">
                <a href="index.php" class="w-full bg-gray-600 text-white px-4 py-2 rounded-lg text-center hover:bg-gray-700">
                    <i class="fas fa-sync-alt mr-2"></i> Reset
                </a>
            </div>
        </form>
    </div>

    <!-- Materials Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        <?php if ($materials && $materials->num_rows > 0): ?>
            <?php while($material = $materials->fetch_assoc()): ?>
                <div class="bg-white rounded-xl shadow-sm hover:shadow-lg transition-all duration-300 overflow-hidden">
                    <div class="p-4 border-b">
                        <div class="flex items-center justify-between">
                            <div>
                                <span class="px-2 py-1 text-xs rounded-full 
                                    <?php echo $material['type'] == 'note' ? 'bg-blue-100 text-blue-700' : 
                                             ($material['type'] == 'video' ? 'bg-red-100 text-red-700' : 
                                             ($material['type'] == 'presentation' ? 'bg-green-100 text-green-700' : 'bg-purple-100 text-purple-700')); ?>">
                                    <?php echo ucfirst($material['type']); ?>
                                </span>
                            </div>
                            <div class="text-xs text-gray-400">
                                <?php echo getTimeAgo($material['uploaded_at']); ?>
                            </div>
                        </div>
                        <h3 class="text-lg font-semibold text-gray-800 mt-2"><?php echo htmlspecialchars($material['title']); ?></h3>
                        <p class="text-sm text-gray-500 mt-1">
                            <?php echo htmlspecialchars($material['class_name']); ?> | <?php echo htmlspecialchars($material['subject_name']); ?>
                        </p>
                        <?php if($material['description']): ?>
                            <p class="text-sm text-gray-600 mt-2 line-clamp-2"><?php echo htmlspecialchars($material['description']); ?></p>
                        <?php endif; ?>
                    </div>
                    <div class="p-4 bg-gray-50 flex justify-between items-center">
                        <a href="../../<?php echo $material['file_url']; ?>" target="_blank" class="text-blue-600 hover:text-blue-800">
                            <i class="fas fa-download mr-1"></i> Download
                        </a>
                        <a href="delete.php?id=<?php echo $material['id']; ?>" 
                           onclick="return confirm('Delete this material?')"
                           class="text-red-600 hover:text-red-800">
                            <i class="fas fa-trash-alt"></i>
                        </a>
                    </div>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <div class="col-span-full">
                <div class="bg-white rounded-xl shadow-sm p-12 text-center">
                    <i class="fas fa-folder-open text-6xl text-gray-300 mb-4"></i>
                    <h3 class="text-xl font-semibold text-gray-600">No Materials Uploaded</h3>
                    <p class="text-gray-400 mt-2">Click "Upload Material" to add teaching resources</p>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>