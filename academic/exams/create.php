<?php
require_once '../../config.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';
requireRole('academic');

$page_title = 'Create Exam';
include '../../includes/header.php';
include '../../includes/sidebar.php';
include '../../includes/navbar.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = sanitizeInput($_POST['name']);
    $type = $_POST['type'];
    $term = $_POST['term'];
    $year = intval($_POST['year']);
    $start_date = $_POST['start_date'];
    $end_date = $_POST['end_date'];
    
    if (empty($name)) {
        $error = "Exam name is required";
    } else {
        $query = "INSERT INTO exams (name, type, term, year, start_date, end_date) VALUES (?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("sssiss", $name, $type, $term, $year, $start_date, $end_date);
        
        if ($stmt->execute()) {
            logActivity($_SESSION['user_id'], 'created exam', 'exam', $conn->insert_id);
            $success = "Exam created successfully!";
            $_POST = [];
        } else {
            $error = "Failed to create exam: " . $conn->error;
        }
    }
}
?>

<div class="ml-64 mt-16 p-6">
    <div class="max-w-2xl mx-auto">
        <div class="mb-6">
            <h1 class="text-2xl font-bold text-gray-800">Create Examination</h1>
            <p class="text-gray-500 mt-1">Set up a new examination</p>
        </div>

        <?php if($error): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg mb-4"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <?php if($success): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-lg mb-4"><?php echo $success; ?></div>
        <?php endif; ?>

        <div class="bg-white rounded-xl shadow-sm p-6">
            <form method="POST">
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Exam Name *</label>
                    <input type="text" name="name" required value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>"
                           placeholder="e.g., Mid-Term Examinations, Final Examinations"
                           class="w-full border rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>

                <div class="grid grid-cols-2 gap-4 mb-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Exam Type</label>
                        <select name="type" class="w-full border rounded-lg px-3 py-2">
                            <option value="midterm" <?php echo ($_POST['type'] ?? '') == 'midterm' ? 'selected' : ''; ?>>Mid-Term</option>
                            <option value="final" <?php echo ($_POST['type'] ?? '') == 'final' ? 'selected' : ''; ?>>Final</option>
                            <option value="monthly" <?php echo ($_POST['type'] ?? '') == 'monthly' ? 'selected' : ''; ?>>Monthly</option>
                            <option value="quarterly" <?php echo ($_POST['type'] ?? '') == 'quarterly' ? 'selected' : ''; ?>>Quarterly</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Term</label>
                        <select name="term" class="w-full border rounded-lg px-3 py-2">
                            <option value="term1" <?php echo ($_POST['term'] ?? '') == 'term1' ? 'selected' : ''; ?>>Term 1</option>
                            <option value="term2" <?php echo ($_POST['term'] ?? '') == 'term2' ? 'selected' : ''; ?>>Term 2</option>
                            <option value="term3" <?php echo ($_POST['term'] ?? '') == 'term3' ? 'selected' : ''; ?>>Term 3</option>
                        </select>
                    </div>
                </div>

                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Year</label>
                    <select name="year" class="w-full border rounded-lg px-3 py-2">
                        <?php for($y = date('Y') - 1; $y <= date('Y') + 2; $y++): ?>
                            <option value="<?php echo $y; ?>" <?php echo ($_POST['year'] ?? date('Y')) == $y ? 'selected' : ''; ?>><?php echo $y; ?></option>
                        <?php endfor; ?>
                    </select>
                </div>

                <div class="grid grid-cols-2 gap-4 mb-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Start Date</label>
                        <input type="date" name="start_date" value="<?php echo htmlspecialchars($_POST['start_date'] ?? date('Y-m-d')); ?>"
                               class="w-full border rounded-lg px-3 py-2">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">End Date</label>
                        <input type="date" name="end_date" value="<?php echo htmlspecialchars($_POST['end_date'] ?? date('Y-m-d', strtotime('+7 days'))); ?>"
                               class="w-full border rounded-lg px-3 py-2">
                    </div>
                </div>

                <div class="flex justify-end space-x-3 mt-6 pt-4 border-t">
                    <a href="index.php" class="px-4 py-2 border rounded-lg hover:bg-gray-50">Cancel</a>
                    <button type="submit" class="px-6 py-2 bg-gradient-to-r from-blue-500 to-purple-600 text-white rounded-lg hover:shadow-lg">
                        <i class="fas fa-save mr-2"></i> Create Exam
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>