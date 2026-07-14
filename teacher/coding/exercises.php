<?php
require_once '../../config.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';
require_once '../../config/code-executor.php';
requireRole('teacher');

$page_title = 'Coding Exercises';
include '../../includes/header.php';
include '../../includes/sidebar.php';
include '../../includes/navbar.php';

// Check if tables exist
$table_check = $conn->query("SHOW TABLES LIKE 'coding_exercises'");
$tables_exist = $table_check->num_rows > 0;

// Handle exercise creation
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $tables_exist) {
    $title = sanitizeInput($_POST['title']);
    $description = sanitizeInput($_POST['description']);
    $language = sanitizeInput($_POST['language']);
    $starter_code = $_POST['starter_code'];
    $solution_code = $_POST['solution_code'];
    $difficulty = $_POST['difficulty'];
    $test_cases = [];
    
    // Parse test cases
    $inputs = $_POST['test_input'] ?? [];
    $outputs = $_POST['test_output'] ?? [];
    
    for ($i = 0; $i < count($inputs); $i++) {
        if (!empty($inputs[$i]) && !empty($outputs[$i])) {
            $test_cases[] = [
                'input' => $inputs[$i],
                'output' => $outputs[$i]
            ];
        }
    }
    
    $test_cases_json = json_encode($test_cases);
    
    $insert = $conn->prepare("
        INSERT INTO coding_exercises (title, description, language, starter_code, solution_code, test_cases, difficulty, created_by)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $insert->bind_param("sssssssi", $title, $description, $language, $starter_code, $solution_code, $test_cases_json, $difficulty, $_SESSION['user_id']);
    
    if ($insert->execute()) {
        $success = "Exercise created successfully!";
        $_POST = [];
    } else {
        $error = "Failed to create exercise: " . $conn->error;
    }
}

// Get all exercises
$exercises = null;
if ($tables_exist) {
    $exercises = $conn->query("SELECT * FROM coding_exercises ORDER BY created_at DESC");
}
?>

<div class="ml-64 mt-16 p-6 bg-gray-50 min-h-screen">
    <div class="max-w-6xl mx-auto">
        <div class="mb-6 flex justify-between items-center">
            <div>
                <h1 class="text-2xl font-bold text-gray-800">📝 Coding Exercises</h1>
                <p class="text-gray-500 mt-1">Create and manage coding challenges for students</p>
            </div>
        </div>

        <!-- Table Not Found Error -->
        <?php if(!$tables_exist): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg mb-4">
                <i class="fas fa-exclamation-triangle mr-2"></i>
                <strong>Database tables not found!</strong>
                <p class="text-sm mt-1">Please run the SQL setup script to create the required tables:</p>
                <div class="mt-2 p-2 bg-gray-800 text-white rounded text-xs font-mono overflow-x-auto">
                    CREATE TABLE coding_exercises ( ... );
                    CREATE TABLE code_submissions ( ... );
                </div>
                <a href="../../setup-coding-tables.php" class="mt-2 inline-block bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700">
                    <i class="fas fa-database mr-2"></i> Run Setup Script
                </a>
            </div>
        <?php endif; ?>

        <?php if($error): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg mb-4"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <?php if($success): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-lg mb-4"><?php echo $success; ?></div>
        <?php endif; ?>

        <!-- Create Exercise Form -->
        <?php if($tables_exist): ?>
        <div class="bg-white rounded-xl shadow-sm p-6 mb-6">
            <h3 class="text-lg font-semibold mb-4">Create New Exercise</h3>
            <form method="POST">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Title *</label>
                        <input type="text" name="title" required class="w-full border rounded-lg px-3 py-2">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Language</label>
                        <select name="language" class="w-full border rounded-lg px-3 py-2">
                            <option value="">Any Language</option>
                            <?php foreach($supported_languages as $key => $name): ?>
                                <option value="<?php echo $key; ?>"><?php echo $name; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Difficulty</label>
                        <select name="difficulty" class="w-full border rounded-lg px-3 py-2">
                            <option value="easy">Easy</option>
                            <option value="medium">Medium</option>
                            <option value="hard">Hard</option>
                        </select>
                    </div>
                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                        <textarea name="description" rows="3" class="w-full border rounded-lg px-3 py-2" placeholder="Describe the exercise..."></textarea>
                    </div>
                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Starter Code</label>
                        <textarea name="starter_code" rows="6" class="w-full border rounded-lg px-3 py-2 font-mono text-sm">// Write your code here</textarea>
                    </div>
                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Solution Code</label>
                        <textarea name="solution_code" rows="6" class="w-full border rounded-lg px-3 py-2 font-mono text-sm">// Solution code</textarea>
                    </div>
                </div>

                <!-- Test Cases -->
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Test Cases</label>
                    <div id="testCasesContainer" class="space-y-2">
                        <div class="flex gap-2">
                            <input type="text" name="test_input[]" placeholder="Input" class="flex-1 border rounded-lg px-3 py-2">
                            <input type="text" name="test_output[]" placeholder="Expected Output" class="flex-1 border rounded-lg px-3 py-2">
                            <button type="button" onclick="removeTestCase(this)" class="text-red-600 hover:text-red-800">✕</button>
                        </div>
                    </div>
                    <button type="button" onclick="addTestCase()" class="mt-2 text-blue-600 hover:text-blue-800 text-sm">
                        <i class="fas fa-plus mr-1"></i> Add Test Case
                    </button>
                </div>

                <button type="submit" class="bg-gradient-to-r from-blue-500 to-purple-600 text-white px-6 py-2 rounded-lg hover:shadow-lg transition">
                    <i class="fas fa-save mr-2"></i> Create Exercise
                </button>
            </form>
        </div>

        <!-- Exercises List -->
        <div class="bg-white rounded-xl shadow-sm overflow-hidden">
            <div class="px-6 py-4 border-b bg-gray-50">
                <h3 class="text-lg font-semibold">Existing Exercises</h3>
            </div>
            <div class="divide-y max-h-80 overflow-y-auto">
                <?php if ($exercises && $exercises->num_rows > 0): ?>
                    <?php while($ex = $exercises->fetch_assoc()): ?>
                        <div class="p-4 hover:bg-gray-50 flex justify-between items-center">
                            <div>
                                <p class="font-medium"><?php echo htmlspecialchars($ex['title']); ?></p>
                                <p class="text-sm text-gray-500"><?php echo htmlspecialchars($ex['description']); ?></p>
                                <div class="flex gap-2 mt-1">
                                    <span class="text-xs px-2 py-0.5 rounded-full <?php echo $ex['difficulty'] == 'easy' ? 'bg-green-100 text-green-700' : ($ex['difficulty'] == 'medium' ? 'bg-yellow-100 text-yellow-700' : 'bg-red-100 text-red-700'); ?>">
                                        <?php echo ucfirst($ex['difficulty']); ?>
                                    </span>
                                    <?php if($ex['language']): ?>
                                        <span class="text-xs px-2 py-0.5 rounded-full bg-blue-100 text-blue-700"><?php echo ucfirst($ex['language']); ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="flex gap-2">
                                <a href="delete-exercise.php?id=<?php echo $ex['id']; ?>" onclick="return confirm('Delete this exercise?')" class="text-red-600 hover:text-red-800">
                                    <i class="fas fa-trash-alt"></i>
                                </a>
                            </div>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="p-8 text-center text-gray-500">No exercises created yet</div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<script>
function addTestCase() {
    const container = document.getElementById('testCasesContainer');
    const div = document.createElement('div');
    div.className = 'flex gap-2';
    div.innerHTML = `
        <input type="text" name="test_input[]" placeholder="Input" class="flex-1 border rounded-lg px-3 py-2">
        <input type="text" name="test_output[]" placeholder="Expected Output" class="flex-1 border rounded-lg px-3 py-2">
        <button type="button" onclick="removeTestCase(this)" class="text-red-600 hover:text-red-800">✕</button>
    `;
    container.appendChild(div);
}

function removeTestCase(btn) {
    const container = document.getElementById('testCasesContainer');
    if (container.children.length > 1) {
        btn.parentElement.remove();
    } else {
        alert('You need at least one test case');
    }
}
</script>

<?php include '../../includes/footer.php'; ?>