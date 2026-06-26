<?php
require_once '../../config.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';
requireRole('academic');

$page_title = 'Syllabus Tracking';
include '../../includes/header.php';
include '../../includes/sidebar.php';
include '../../includes/navbar.php';

$class_id = $_GET['class_id'] ?? 0;
$subject_id = $_GET['subject_id'] ?? 0;

// Get classes
$classes = $conn->query("SELECT id, name FROM classes ORDER BY name");

// Get subjects based on selected class
$subjects = [];
if ($class_id) {
    $subj_query = $conn->prepare("
        SELECT s.id, s.name, s.code 
        FROM subjects s
        JOIN class_subject cs ON s.id = cs.subject_id
        WHERE cs.class_id = ?
    ");
    $subj_query->bind_param("i", $class_id);
    $subj_query->execute();
    $subjects = $subj_query->get_result();
}

// Get syllabus progress
$progress_data = [];
if ($class_id && $subject_id) {
    // Sample data - you would implement actual syllabus tracking table
    $topics = [
        ['topic' => 'Introduction to the subject', 'completed' => 100],
        ['topic' => 'Basic concepts', 'completed' => 85],
        ['topic' => 'Advanced topics', 'completed' => 60],
        ['topic' => 'Practical applications', 'completed' => 45],
        ['topic' => 'Revision and assessment', 'completed' => 30],
    ];
    
    // Calculate overall progress
    $total_progress = 0;
    foreach ($topics as $topic) {
        $total_progress += $topic['completed'];
    }
    $overall_progress = count($topics) > 0 ? round($total_progress / count($topics), 1) : 0;
}
?>

<div class="ml-64 mt-16 p-6">
    <div class="max-w-6xl mx-auto">
        <div class="mb-6">
            <h1 class="text-2xl font-bold text-gray-800">Syllabus Tracking</h1>
            <p class="text-gray-500 mt-1">Monitor syllabus coverage progress by teachers</p>
        </div>

        <!-- Filters -->
        <div class="bg-white rounded-xl shadow-sm p-4 mb-6">
            <form method="GET" class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Select Class</label>
                    <select name="class_id" class="w-full border rounded-lg px-3 py-2" onchange="this.form.submit()">
                        <option value="0">-- Select Class --</option>
                        <?php while($class = $classes->fetch_assoc()): ?>
                            <option value="<?php echo $class['id']; ?>" <?php echo $class_id == $class['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($class['name']); ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Select Subject</label>
                    <select name="subject_id" class="w-full border rounded-lg px-3 py-2" onchange="this.form.submit()">
                        <option value="0">-- Select Subject --</option>
                        <?php if ($class_id): ?>
                            <?php while($subject = $subjects->fetch_assoc()): ?>
                                <option value="<?php echo $subject['id']; ?>" <?php echo $subject_id == $subject['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($subject['name'] . ' (' . $subject['code'] . ')'); ?>
                                </option>
                            <?php endwhile; ?>
                        <?php endif; ?>
                    </select>
                </div>
            </form>
        </div>

        <?php if ($class_id && $subject_id): ?>
            <!-- Overall Progress -->
            <div class="bg-white rounded-xl shadow-sm p-6 mb-6">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-lg font-semibold">Overall Syllabus Progress</h3>
                    <span class="text-2xl font-bold text-blue-600"><?php echo $overall_progress; ?>%</span>
                </div>
                <div class="w-full bg-gray-200 rounded-full h-4">
                    <div class="bg-blue-600 h-4 rounded-full transition-all duration-500" style="width: <?php echo $overall_progress; ?>%"></div>
                </div>
                <p class="text-sm text-gray-500 mt-2">Estimated completion based on topic coverage</p>
            </div>

            <!-- Topic-wise Progress -->
            <div class="bg-white rounded-xl shadow-sm overflow-hidden">
                <div class="px-6 py-4 border-b bg-gray-50">
                    <h3 class="text-lg font-semibold">Topic-wise Coverage</h3>
                </div>
                <div class="divide-y divide-gray-200">
                    <?php foreach($topics as $index => $topic): ?>
                        <div class="p-4 hover:bg-gray-50">
                            <div class="flex justify-between items-center mb-2">
                                <div>
                                    <span class="font-medium"><?php echo ($index + 1) . '. ' . htmlspecialchars($topic['topic']); ?></span>
                                </div>
                                <span class="text-sm font-semibold <?php echo $topic['completed'] >= 80 ? 'text-green-600' : ($topic['completed'] >= 50 ? 'text-yellow-600' : 'text-red-600'); ?>">
                                    <?php echo $topic['completed']; ?>%
                                </span>
                            </div>
                            <div class="w-full bg-gray-200 rounded-full h-2">
                                <div class="h-2 rounded-full transition-all duration-500 <?php echo $topic['completed'] >= 80 ? 'bg-green-600' : ($topic['completed'] >= 50 ? 'bg-yellow-600' : 'bg-red-600'); ?>" 
                                     style="width: <?php echo $topic['completed']; ?>%"></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Teacher Comments -->
            <div class="mt-6 bg-white rounded-xl shadow-sm p-6">
                <h3 class="text-lg font-semibold mb-4">Teacher's Remarks</h3>
                <div class="bg-gray-50 rounded-lg p-4">
                    <div class="flex items-start space-x-3">
                        <div class="w-10 h-10 bg-blue-100 rounded-full flex items-center justify-center">
                            <i class="fas fa-chalkboard-user text-blue-600"></i>
                        </div>
                        <div>
                            <p class="font-medium">Mr. John Teacher</p>
                            <p class="text-sm text-gray-600 mt-1">
                                Syllabus progressing well. Students are engaged and performing satisfactorily.
                                Need to accelerate pace for remaining topics to complete before examinations.
                            </p>
                            <p class="text-xs text-gray-400 mt-2">Last updated: <?php echo date('F d, Y'); ?></p>
                        </div>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <div class="bg-white rounded-xl shadow-sm p-12 text-center">
                <i class="fas fa-book-open text-6xl text-gray-300 mb-4"></i>
                <h3 class="text-xl font-semibold text-gray-600">Select Class and Subject</h3>
                <p class="text-gray-400 mt-2">Choose a class and subject to view syllabus progress</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>