<?php
require_once '../../config.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';
requireRole('teacher');

$page_title = 'Import Questions';
include '../../includes/header.php';
include '../../includes/sidebar.php';
include '../../includes/navbar.php';

// Get teacher ID
$teacher_query = $conn->prepare("SELECT id FROM teachers WHERE user_id = ?");
$teacher_query->bind_param("i", $_SESSION['user_id']);
$teacher_query->execute();
$teacher = $teacher_query->get_result()->fetch_assoc();
$teacher_id = $teacher['id'] ?? 0;

$exam_id = $_GET['exam_id'] ?? 0;
$error = '';
$success = '';
$preview_data = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_FILES['csv_file']) && $_FILES['csv_file']['error'] == 0) {
        $file = $_FILES['csv_file']['tmp_name'];
        $handle = fopen($file, 'r');
        
        // Skip header row
        fgetcsv($handle);
        
        $imported = 0;
        $failed = 0;
        
        while (($data = fgetcsv($handle, 1000, ',')) !== FALSE) {
            if (count($data) >= 5) {
                $question_text = sanitizeInput($data[0]);
                $question_type = sanitizeInput($data[1]);
                $options_raw = sanitizeInput($data[2]);
                $correct_answer = sanitizeInput($data[3]);
                $marks = intval($data[4]);
                $topic = sanitizeInput($data[5] ?? '');
                $difficulty = sanitizeInput($data[6] ?? 'medium');
                
                // Parse options for MCQ
                $options = null;
                if ($question_type == 'mcq' && !empty($options_raw)) {
                    $options_array = [];
                    $parts = explode('|', $options_raw);
                    foreach ($parts as $part) {
                        $opt = explode(':', $part);
                        if (count($opt) == 2) {
                            $options_array[$opt[0]] = $opt[1];
                        }
                    }
                    $options = json_encode($options_array);
                }
                
                $insert = $conn->prepare("
                    INSERT INTO question_bank (teacher_id, subject_id, question_text, question_type, options, correct_answer, marks, topic, difficulty)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                
                // Use the subject_id from the form or from CSV
                $subject_id = intval($_POST['subject_id']);
                $insert->bind_param("iisssssis", $teacher_id, $subject_id, $question_text, $question_type, $options, $correct_answer, $marks, $topic, $difficulty);
                
                if ($insert->execute()) {
                    $imported++;
                } else {
                    $failed++;
                }
            }
        }
        fclose($handle);
        
        $success = "Imported $imported questions successfully! Failed: $failed";
    }
}

// Get subjects for dropdown
$subjects = $conn->prepare("
    SELECT DISTINCT s.id, s.name, s.code
    FROM subjects s
    JOIN class_subject cs ON s.id = cs.subject_id
    WHERE cs.teacher_id = ?
    ORDER BY s.name
");
$subjects->bind_param("i", $teacher_id);
$subjects->execute();
$subjects = $subjects->get_result();
?>

<div class="ml-64 mt-16 p-6">
    <div class="max-w-3xl mx-auto">
        <div class="mb-6">
            <h1 class="text-2xl font-bold text-gray-800">Import Questions</h1>
            <p class="text-gray-500 mt-1">Bulk import questions from CSV file</p>
        </div>

        <?php if($error): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg mb-4"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <?php if($success): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-lg mb-4"><?php echo $success; ?></div>
        <?php endif; ?>

        <!-- CSV Template Download -->
        <div class="bg-blue-50 border border-blue-200 rounded-xl p-4 mb-6">
            <div class="flex justify-between items-center">
                <div>
                    <i class="fas fa-file-csv text-blue-600 text-2xl mr-3 float-left"></i>
                    <h3 class="font-semibold">CSV Template</h3>
                    <p class="text-sm text-gray-600">Download the template to see the correct format</p>
                </div>
                <a href="download-template.php" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700">
                    <i class="fas fa-download mr-2"></i> Download Template
                </a>
            </div>
        </div>

        <div class="bg-white rounded-xl shadow-sm p-6">
            <form method="POST" enctype="multipart/form-data">
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Select Subject *</label>
                    <select name="subject_id" required class="w-full border rounded-lg px-3 py-2">
                        <option value="">Choose Subject</option>
                        <?php while($subject = $subjects->fetch_assoc()): ?>
                            <option value="<?php echo $subject['id']; ?>"><?php echo htmlspecialchars($subject['name']); ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>

                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">CSV File *</label>
                    <input type="file" name="csv_file" accept=".csv" required class="w-full border rounded-lg px-3 py-2">
                    <p class="text-xs text-gray-500 mt-1">Max 10MB. Allowed: .csv files only</p>
                </div>

                <!-- CSV Format Instructions -->
                <div class="mt-6 p-4 bg-gray-50 rounded-lg">
                    <h4 class="font-semibold text-sm mb-2">CSV Format Instructions:</h4>
                    <p class="text-xs text-gray-600 mb-2">Your CSV file should have the following columns (in order):</p>
                    <ol class="text-xs text-gray-600 list-decimal list-inside space-y-1">
                        <li><strong>Question Text</strong> - The question content</li>
                        <li><strong>Question Type</strong> - mcq, truefalse, short_answer, essay, fill_blanks</li>
                        <li><strong>Options</strong> - For MCQ: format "A:Option text|B:Option text|C:Option text|D:Option text"</li>
                        <li><strong>Correct Answer</strong> - For MCQ: A/B/C/D, For True/False: true/false</li>
                        <li><strong>Marks</strong> - Number of marks for this question</li>
                        <li><strong>Topic</strong> - (Optional) Topic name</li>
                        <li><strong>Difficulty</strong> - (Optional) easy, medium, hard</li>
                    </ol>
                </div>

                <div class="flex justify-end space-x-3 mt-6 pt-4 border-t">
                    <a href="question-bank.php<?php echo $exam_id ? '?exam_id=' . $exam_id : ''; ?>" class="px-4 py-2 border rounded-lg hover:bg-gray-50">Cancel</a>
                    <button type="submit" class="px-6 py-2 bg-gradient-to-r from-blue-500 to-purple-600 text-white rounded-lg hover:shadow-lg">
                        <i class="fas fa-upload mr-2"></i> Import Questions
                    </button>
                </div>
            </form>
        </div>
        
        <!-- Sample CSV Preview -->
        <div class="mt-6 bg-white rounded-xl shadow-sm p-6">
            <h3 class="text-lg font-semibold mb-4">Sample CSV Content</h3>
            <pre class="bg-gray-100 p-4 rounded-lg text-xs overflow-x-auto">
"Question Text","Question Type","Options","Correct Answer","Marks","Topic","Difficulty"
"What is the capital of Tanzania?","mcq","A:Dodoma|B:Dar es Salaam|C:Arusha|D:Mwanza","A","2","Geography","easy"
"The sun rises in the east.","truefalse","","true","1","Science","easy"
"Explain the process of photosynthesis.","essay","","Photosynthesis is the process...","5","Biology","medium"
"2 + 2 = ___","fill_blanks","","4","1","Mathematics","easy"</pre>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>