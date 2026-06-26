<?php
require_once '../../config.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';
requireRole('student');

$page_title = 'Learning Materials';
include '../../includes/header.php';
include '../../includes/sidebar.php';
include '../../includes/navbar.php';

// Get student info
$student_query = $conn->prepare("
    SELECT s.id, s.class_id, c.name as class_name
    FROM students s
    JOIN classes c ON s.class_id = c.id
    WHERE s.user_id = ?
");
$student_query->bind_param("i", $_SESSION['user_id']);
$student_query->execute();
$student = $student_query->get_result()->fetch_assoc();
$student_id = $student['id'];
$class_id = $student['class_id'];

// Get subjects for this class
$subjects = $conn->prepare("
    SELECT s.id, s.name, s.code
    FROM subjects s
    JOIN class_subject cs ON s.id = cs.subject_id
    WHERE cs.class_id = ?
    ORDER BY s.name
");
$subjects->bind_param("i", $class_id);
$subjects->execute();
$subjects = $subjects->get_result();

// Get filter parameters
$subject_filter = $_GET['subject'] ?? '';
$type_filter = $_GET['type'] ?? '';
$search = $_GET['search'] ?? '';

// Get learning materials
$query = "SELECT lm.*, s.name as subject_name 
          FROM learning_materials lm
          JOIN subjects s ON lm.subject_id = s.id
          WHERE lm.class_id = ?";
$params = [$class_id];
$types = "i";

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
if ($search) {
    $query .= " AND (lm.title LIKE ? OR lm.description LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= "ss";
}

$query .= " ORDER BY lm.uploaded_at DESC";

$stmt = $conn->prepare($query);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$materials = $stmt->get_result();
?>

<div class="ml-64 mt-16 p-6">
    <div class="max-w-full mx-auto">
        <div class="mb-6">
            <h1 class="text-2xl font-bold text-gray-800">Learning Materials</h1>
            <p class="text-gray-500 mt-1">Access your course materials, notes, and resources</p>
        </div>

        <!-- Filters -->
        <div class="bg-white rounded-xl shadow-sm p-4 mb-6">
            <form method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Subject</label>
                    <select name="subject" class="w-full border rounded-lg px-3 py-2" onchange="this.form.submit()">
                        <option value="">All Subjects</option>
                        <?php while($subject = $subjects->fetch_assoc()): ?>
                            <option value="<?php echo $subject['id']; ?>" <?php echo $subject_filter == $subject['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($subject['name']); ?>
                            </option>
                        <?php endwhile; ?>
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
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Search</label>
                    <div class="flex">
                        <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" 
                               placeholder="Search materials..." 
                               class="flex-1 border rounded-l-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded-r-lg hover:bg-blue-700">
                            <i class="fas fa-search"></i>
                        </button>
                    </div>
                </div>
            </form>
        </div>

        <!-- Materials Grid -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            <?php if ($materials && $materials->num_rows > 0): ?>
                <?php while($material = $materials->fetch_assoc()): ?>
                    <div class="bg-white rounded-xl shadow-sm hover:shadow-lg transition-all duration-300 overflow-hidden group">
                        <div class="h-2 <?php echo $material['type'] == 'note' ? 'bg-blue-500' : ($material['type'] == 'video' ? 'bg-red-500' : ($material['type'] == 'presentation' ? 'bg-green-500' : 'bg-purple-500')); ?>"></div>
                        <div class="p-5">
                            <div class="flex items-start justify-between">
                                <div class="flex-1">
                                    <div class="flex items-center space-x-2 mb-2">
                                        <span class="px-2 py-1 text-xs rounded-full 
                                            <?php echo $material['type'] == 'note' ? 'bg-blue-100 text-blue-700' : 
                                                     ($material['type'] == 'video' ? 'bg-red-100 text-red-700' : 
                                                     ($material['type'] == 'presentation' ? 'bg-green-100 text-green-700' : 'bg-purple-100 text-purple-700')); ?>">
                                            <?php echo ucfirst($material['type']); ?>
                                        </span>
                                        <span class="text-xs text-gray-400"><?php echo getTimeAgo($material['uploaded_at']); ?></span>
                                    </div>
                                    <h3 class="text-lg font-semibold text-gray-800 group-hover:text-blue-600 transition">
                                        <?php echo htmlspecialchars($material['title']); ?>
                                    </h3>
                                    <p class="text-sm text-gray-500 mt-1"><?php echo htmlspecialchars($material['subject_name']); ?></p>
                                    <?php if($material['description']): ?>
                                        <p class="text-sm text-gray-600 mt-2 line-clamp-2"><?php echo htmlspecialchars($material['description']); ?></p>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="mt-4 flex justify-between items-center">
                                <a href="../../<?php echo $material['file_url']; ?>" target="_blank" 
                                   class="inline-flex items-center text-blue-600 hover:text-blue-800">
                                    <i class="fas fa-download mr-1"></i> Download
                                </a>
                                <button onclick="bookmark(<?php echo $material['id']; ?>)" 
                                        class="text-gray-400 hover:text-yellow-500 transition">
                                    <i class="far fa-bookmark"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="col-span-full">
                    <div class="bg-white rounded-xl shadow-sm p-12 text-center">
                        <i class="fas fa-folder-open text-6xl text-gray-300 mb-4"></i>
                        <h3 class="text-xl font-semibold text-gray-600">No Materials Found</h3>
                        <p class="text-gray-400 mt-2">Try changing your search criteria</p>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
function bookmark(materialId) {
    fetch('bookmark.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'material_id=' + materialId
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Added to bookmarks!');
        }
    });
}
</script>

<?php include '../../includes/footer.php'; ?>