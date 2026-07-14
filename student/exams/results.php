<?php
require_once '../../config.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';
requireRole('student');

$exam_id = $_GET['id'] ?? 0;

if (!$exam_id) {
    header('Location: index.php');
    exit();
}

// Get student ID
$student_query = $conn->prepare("SELECT id FROM students WHERE user_id = ?");
$student_query->bind_param("i", $_SESSION['user_id']);
$student_query->execute();
$student = $student_query->get_result()->fetch_assoc();
$student_id = $student['id'] ?? 0;

if (!$student_id) {
    header('Location: index.php?error=Student not found');
    exit();
}

// Get submission
$submission_query = $conn->prepare("
    SELECT es.*, te.title as exam_title, te.total_marks as exam_total, te.subject_id,
           s.name as subject_name, te.passing_marks, te.duration_minutes
    FROM exam_submissions es
    JOIN teacher_exams te ON es.exam_id = te.id
    JOIN subjects s ON te.subject_id = s.id
    WHERE es.exam_id = ? AND es.student_id = ?
    ORDER BY es.submitted_at DESC LIMIT 1
");
$submission_query->bind_param("ii", $exam_id, $student_id);
$submission_query->execute();
$submission = $submission_query->get_result()->fetch_assoc();

if (!$submission) {
    header('Location: index.php?error=No submission found');
    exit();
}

// Get all questions
$questions_query = $conn->prepare("
    SELECT * FROM exam_questions WHERE exam_id = ? ORDER BY order_number
");
$questions_query->bind_param("i", $exam_id);
$questions_query->execute();
$all_questions = $questions_query->get_result();

// Build questions data
$questions_data = [];
while ($q = $all_questions->fetch_assoc()) {
    $questions_data[$q['id']] = [
        'question_text' => $q['question_text'],
        'question_type' => $q['question_type'],
        'correct_answer' => $q['correct_answer'] ?? '',
        'marks' => $q['marks'],
        'options' => $q['options'],
        'difficulty' => $q['difficulty'] ?? 'medium',
        'topic' => $q['topic'] ?? ''
    ];
}

// Decode student answers
$answers = !empty($submission['answers']) ? json_decode($submission['answers'], true) : [];

// Calculate results
$results_data = [];
$total_questions = 0;
$correct_count = 0;
$earned_marks = 0;
$total_marks = $submission['exam_total'] ?? 0;

foreach ($questions_data as $q_id => $q_info) {
    $total_questions++;
    $student_answer = $answers[$q_id] ?? 'Not answered';
    $correct_answer = $q_info['correct_answer'];
    $is_correct = false;
    $earned = 0;
    
    // Auto-grade based on question type
    switch ($q_info['question_type']) {
        case 'mcq':
            if (is_array($student_answer)) {
                $student_answer_str = implode(', ', $student_answer);
            } else {
                $student_answer_str = $student_answer;
            }
            $is_correct = strtoupper(trim($student_answer_str)) == strtoupper(trim($correct_answer));
            break;
            
        case 'truefalse':
            if (is_array($student_answer)) {
                $student_answer_str = implode(', ', $student_answer);
            } else {
                $student_answer_str = $student_answer;
            }
            $is_correct = strtolower(trim($student_answer_str)) == strtolower(trim($correct_answer));
            break;
            
        case 'fill_blanks':
        case 'short_answer':
            if (!empty($correct_answer) && !empty($student_answer)) {
                $correct_keywords = array_map('trim', explode(',', strtolower($correct_answer)));
                $student_answer_lower = strtolower(trim($student_answer));
                $match_count = 0;
                foreach ($correct_keywords as $keyword) {
                    if (!empty($keyword) && strpos($student_answer_lower, $keyword) !== false) {
                        $match_count++;
                    }
                }
                $is_correct = ($match_count / count($correct_keywords)) >= 0.5;
            }
            break;
            
        case 'essay':
            if (!empty($correct_answer) && !empty($student_answer)) {
                $correct_keywords = array_map('trim', explode(',', strtolower($correct_answer)));
                $student_answer_lower = strtolower(trim($student_answer));
                $match_count = 0;
                foreach ($correct_keywords as $keyword) {
                    if (!empty($keyword) && strpos($student_answer_lower, $keyword) !== false) {
                        $match_count++;
                    }
                }
                $is_correct = ($match_count / count($correct_keywords)) >= 0.3;
            }
            break;
            
        default:
            $is_correct = false;
    }
    
    if ($is_correct) {
        $correct_count++;
        $earned = $q_info['marks'];
    }
    $earned_marks += $earned;
    
    // Format options for display
    $options_display = [];
    if (!empty($q_info['options'])) {
        $options_display = json_decode($q_info['options'], true);
    }
    
    $results_data[] = [
        'question_id' => $q_id,
        'question_text' => $q_info['question_text'],
        'question_type' => $q_info['question_type'],
        'student_answer' => $student_answer,
        'correct_answer' => $correct_answer,
        'marks' => $q_info['marks'],
        'earned' => $earned,
        'is_correct' => $is_correct,
        'options' => $options_display,
        'difficulty' => $q_info['difficulty'],
        'topic' => $q_info['topic']
    ];
}

// Calculate statistics
$percentage = $submission['percentage'] ?? 0;
$is_pass = $percentage >= 75;
$correct_percentage = $total_questions > 0 ? round(($correct_count / $total_questions) * 100, 1) : 0;

$gradeInfo = getGradeInfo($percentage);

$page_title = 'Exam Results - ' . $submission['exam_title'];
include '../../includes/header.php';
include '../../includes/sidebar.php';
include '../../includes/navbar.php';
?>

<style>
.result-card {
    transition: all 0.3s ease;
}
.result-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 25px rgba(0,0,0,0.08);
}
.correct-answer-badge {
    background: #d1fae5;
    color: #065f46;
    padding: 2px 10px;
    border-radius: 12px;
    font-size: 13px;
    font-weight: 600;
}
.wrong-answer-badge {
    background: #fee2e2;
    color: #991b1b;
    padding: 2px 10px;
    border-radius: 12px;
    font-size: 13px;
    font-weight: 600;
}
.student-answer-correct {
    color: #065f46;
    font-weight: 600;
}
.student-answer-wrong {
    color: #991b1b;
    font-weight: 600;
}
.answer-comparison {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
}
.option-correct {
    background: #d1fae5;
    padding: 2px 8px;
    border-radius: 4px;
    font-weight: 600;
}
.option-wrong {
    background: #fee2e2;
    padding: 2px 8px;
    border-radius: 4px;
    font-weight: 600;
}
</style>

<div class="ml-64 mt-16 p-6 bg-gray-50 min-h-screen">
    <div class="max-w-4xl mx-auto">
        <!-- Back Button -->
        <div class="mb-4">
            <a href="index.php" class="text-blue-600 hover:text-blue-800">
                <i class="fas fa-arrow-left mr-2"></i> Back to Exams
            </a>
        </div>

        <!-- Result Header -->
        <div class="bg-white rounded-2xl shadow-sm overflow-hidden mb-6 result-card">
            <div class="<?php echo $is_pass ? 'bg-gradient-to-r from-green-500 to-teal-600' : 'bg-gradient-to-r from-red-500 to-red-600'; ?> p-6 text-white">
                <div class="flex items-center justify-between flex-wrap">
                    <div>
                        <div class="flex items-center gap-3">
                            <i class="fas <?php echo $is_pass ? 'fa-check-circle' : 'fa-times-circle'; ?> text-4xl"></i>
                            <div>
                                <h1 class="text-2xl font-bold"><?php echo $is_pass ? '✅ PASS' : '❌ FAIL'; ?></h1>
                                <p class="text-white opacity-90"><?php echo htmlspecialchars($submission['exam_title']); ?></p>
                            </div>
                        </div>
                        <p class="text-white opacity-75 text-sm mt-2">
                            <i class="far fa-calendar mr-1"></i> 
                            <?php echo date('M d, Y h:i A', strtotime($submission['submitted_at'] ?? 'now')); ?>
                        </p>
                    </div>
                    <div class="text-right">
                        <p class="text-sm opacity-75">Subject</p>
                        <p class="text-lg font-semibold"><?php echo htmlspecialchars($submission['subject_name']); ?></p>
                    </div>
                </div>
            </div>
            
            <div class="p-6">
                <!-- Score Overview -->
                <div class="grid grid-cols-2 md:grid-cols-5 gap-4">
                    <div class="text-center p-3 bg-gray-50 rounded-xl">
                        <p class="text-gray-500 text-xs">Score</p>
                        <p class="text-xl font-bold text-blue-600">
                            <?php echo $submission['total_score']; ?> / <?php echo $submission['exam_total']; ?>
                        </p>
                    </div>
                    <div class="text-center p-3 bg-gray-50 rounded-xl">
                        <p class="text-gray-500 text-xs">Percentage</p>
                        <p class="text-xl font-bold <?php echo $is_pass ? 'text-green-600' : 'text-red-600'; ?>">
                            <?php echo round($percentage, 1); ?>%
                        </p>
                    </div>
                    <div class="text-center p-3 bg-gray-50 rounded-xl">
                        <p class="text-gray-500 text-xs">Grade</p>
                        <p class="text-2xl font-bold <?php echo $gradeInfo['textColor']; ?>">
                            <?php echo $submission['grade'] ?? '-'; ?>
                        </p>
                    </div>
                    <div class="text-center p-3 bg-gray-50 rounded-xl">
                        <p class="text-gray-500 text-xs">Correct</p>
                        <p class="text-xl font-bold text-green-600">
                            <?php echo $correct_count; ?> / <?php echo $total_questions; ?>
                        </p>
                    </div>
                    <div class="text-center p-3 bg-gray-50 rounded-xl">
                        <p class="text-gray-500 text-xs">Status</p>
                        <span class="px-3 py-1 text-sm rounded-full <?php echo $is_pass ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'; ?>">
                            <?php echo $is_pass ? '✅ PASS' : '❌ FAIL'; ?>
                        </span>
                    </div>
                </div>

                <?php if(!empty($submission['feedback'])): ?>
                    <div class="mt-4 p-4 bg-blue-50 rounded-xl border border-blue-200">
                        <p class="text-sm font-semibold text-blue-800">
                            <i class="fas fa-comment mr-2"></i> Teacher's Feedback
                        </p>
                        <p class="text-sm text-blue-700 mt-1"><?php echo nl2br(htmlspecialchars($submission['feedback'])); ?></p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Question Review -->
        <div class="bg-white rounded-xl shadow-sm overflow-hidden">
            <div class="px-6 py-4 border-b bg-gray-50 flex justify-between items-center">
                <h3 class="text-lg font-semibold">
                    <i class="fas fa-list-check text-blue-500 mr-2"></i>
                    Question Review
                </h3>
                <div class="flex items-center gap-4 text-sm">
                    <span class="text-green-600">✅ <?php echo $correct_count; ?> correct</span>
                    <span class="text-red-600">❌ <?php echo $total_questions - $correct_count; ?> incorrect</span>
                </div>
            </div>
            
            <div class="divide-y divide-gray-200">
                <?php 
                $counter = 1;
                foreach($results_data as $result): 
                    $is_correct = $result['is_correct'];
                    $student_answer = $result['student_answer'];
                    $correct_answer = $result['correct_answer'];
                    
                    if (is_array($student_answer)) {
                        $student_answer_display = implode(', ', $student_answer);
                    } else {
                        $student_answer_display = $student_answer;
                    }
                    
                    $options = $result['options'];
                    $student_option_text = '';
                    if (!empty($options) && isset($options[$student_answer_display])) {
                        $student_option_text = $options[$student_answer_display];
                    }
                    
                    $correct_option_text = '';
                    if (!empty($options) && isset($options[$correct_answer])) {
                        $correct_option_text = $options[$correct_answer];
                    }
                ?>
                    <div class="p-6 hover:bg-gray-50 transition-all">
                        <div class="flex justify-between items-start">
                            <div class="flex-1">
                                <!-- Question Header -->
                                <div class="flex flex-wrap items-center gap-2 mb-2">
                                    <span class="font-semibold text-gray-800">Q<?php echo $counter++; ?>.</span>
                                    <span class="px-2 py-0.5 text-xs rounded-full bg-gray-100 text-gray-600">
                                        <?php echo ucfirst(str_replace('_', ' ', $result['question_type'])); ?>
                                    </span>
                                    <span class="px-2 py-0.5 text-xs rounded-full bg-yellow-100 text-yellow-700">
                                        <?php echo $result['difficulty'] ?? 'medium'; ?>
                                    </span>
                                    <span class="text-xs text-gray-400">Marks: <?php echo $result['marks']; ?></span>
                                    <?php if($result['topic']): ?>
                                        <span class="text-xs text-gray-400">Topic: <?php echo htmlspecialchars($result['topic']); ?></span>
                                    <?php endif; ?>
                                    <?php if($is_correct): ?>
                                        <span class="correct-answer-badge">✅ Correct</span>
                                    <?php else: ?>
                                        <span class="wrong-answer-badge">❌ Incorrect</span>
                                    <?php endif; ?>
                                </div>
                                
                                <!-- Question Text -->
                                <p class="text-gray-800 font-medium mb-3"><?php echo nl2br(htmlspecialchars($result['question_text'])); ?></p>
                                
                                <!-- Options (for MCQ) -->
                                <?php if($result['question_type'] == 'mcq' && !empty($options)): ?>
                                    <div class="ml-4 mb-3 space-y-1">
                                        <p class="text-xs text-gray-400 font-medium">Options:</p>
                                        <?php foreach($options as $key => $opt): ?>
                                            <div class="text-sm py-1 px-2 rounded <?php 
                                                if ($key == $correct_answer && $key == $student_answer_display) {
                                                    echo 'bg-green-100 border border-green-300';
                                                } elseif ($key == $correct_answer) {
                                                    echo 'bg-green-50 border border-green-200';
                                                } elseif ($key == $student_answer_display && $key != $correct_answer) {
                                                    echo 'bg-red-50 border border-red-200';
                                                } else {
                                                    echo 'bg-white';
                                                }
                                            ?>">
                                                <?php echo $key . ': ' . htmlspecialchars($opt); ?>
                                                <?php if($key == $correct_answer): ?>
                                                    <span class="text-green-600 text-xs font-semibold ml-2">✓ Correct Answer</span>
                                                <?php endif; ?>
                                                <?php if($key == $student_answer_display && $key != $correct_answer): ?>
                                                    <span class="text-red-600 text-xs font-semibold ml-2">✗ Your Answer</span>
                                                <?php endif; ?>
                                                <?php if($key == $student_answer_display && $key == $correct_answer): ?>
                                                    <span class="text-green-600 text-xs font-semibold ml-2">✓ Your Answer (Correct)</span>
                                                <?php endif; ?>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                                
                                <!-- Answer Comparison -->
                                <div class="bg-gray-50 rounded-lg p-4 mt-2">
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                        <!-- Student Answer -->
                                        <div>
                                            <p class="text-xs text-gray-400 font-medium">Your Answer:</p>
                                            <p class="<?php echo $is_correct ? 'text-green-600 font-semibold' : 'text-red-600 font-semibold'; ?>">
                                                <?php 
                                                if (!empty($student_option_text) && $result['question_type'] == 'mcq') {
                                                    echo $student_answer_display . ': ' . htmlspecialchars($student_option_text);
                                                } elseif ($result['question_type'] == 'truefalse') {
                                                    echo ucfirst($student_answer_display);
                                                } else {
                                                    echo !empty($student_answer_display) ? htmlspecialchars($student_answer_display) : 'Not answered';
                                                }
                                                ?>
                                                <?php if($is_correct): ?>
                                                    <span class="correct-answer-badge ml-2">✅ Correct</span>
                                                <?php else: ?>
                                                    <span class="wrong-answer-badge ml-2">❌ Incorrect</span>
                                                <?php endif; ?>
                                            </p>
                                        </div>
                                        
                                        <!-- Correct Answer -->
                                        <div>
                                            <p class="text-xs text-gray-400 font-medium">Correct Answer:</p>
                                            <p class="text-green-600 font-semibold">
                                                <?php 
                                                if (!empty($correct_option_text) && $result['question_type'] == 'mcq') {
                                                    echo $correct_answer . ': ' . htmlspecialchars($correct_option_text);
                                                } elseif ($result['question_type'] == 'truefalse') {
                                                    echo ucfirst($correct_answer);
                                                } else {
                                                    echo htmlspecialchars($correct_answer) ?: 'N/A';
                                                }
                                                ?>
                                            </p>
                                        </div>
                                    </div>
                                    
                                    <!-- Score -->
                                    <div class="mt-2 pt-2 border-t border-gray-200 text-right">
                                        <span class="text-sm <?php echo $is_correct ? 'text-green-600 font-bold' : 'text-gray-400'; ?>">
                                            <?php if($is_correct): ?>
                                                ✅ +<?php echo $result['marks']; ?> marks
                                            <?php else: ?>
                                                ❌ 0 marks
                                            <?php endif; ?>
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Performance Summary -->
        <div class="mt-6 bg-white rounded-xl shadow-sm p-6">
            <h4 class="font-semibold text-gray-700 mb-3">📊 Performance Summary</h4>
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-center">
                <div class="p-3 bg-green-50 rounded-xl">
                    <p class="text-xs text-gray-500">Correct</p>
                    <p class="text-2xl font-bold text-green-600"><?php echo $correct_count; ?></p>
                </div>
                <div class="p-3 bg-red-50 rounded-xl">
                    <p class="text-xs text-gray-500">Incorrect</p>
                    <p class="text-2xl font-bold text-red-600"><?php echo $total_questions - $correct_count; ?></p>
                </div>
                <div class="p-3 bg-blue-50 rounded-xl">
                    <p class="text-xs text-gray-500">Accuracy</p>
                    <p class="text-2xl font-bold text-blue-600"><?php echo $correct_percentage; ?>%</p>
                </div>
                <div class="p-3 bg-purple-50 rounded-xl">
                    <p class="text-xs text-gray-500">Marks Earned</p>
                    <p class="text-2xl font-bold text-purple-600">
                        <?php echo $submission['total_score']; ?> / <?php echo $submission['exam_total']; ?>
                    </p>
                </div>
            </div>
            
            <!-- Progress Bar -->
            <div class="mt-4">
                <div class="flex justify-between text-sm text-gray-500 mb-1">
                    <span>Performance</span>
                    <span><?php echo round($percentage, 1); ?>%</span>
                </div>
                <div class="w-full bg-gray-200 rounded-full h-3">
                    <div class="h-3 rounded-full <?php echo $is_pass ? 'bg-green-500' : 'bg-red-500'; ?>" 
                         style="width: <?php echo min($percentage, 100); ?>%"></div>
                </div>
                <div class="flex justify-between text-xs text-gray-400 mt-1">
                    <span>0%</span>
                    <span>Passing: 75%</span>
                    <span>100%</span>
                </div>
            </div>
        </div>

        <!-- Grading Scale -->
        <div class="mt-6 bg-white rounded-xl shadow-sm p-4">
            <h4 class="font-semibold text-gray-700 text-sm mb-3">📊 Grading Scale</h4>
            <div class="grid grid-cols-2 md:grid-cols-5 gap-2 text-center text-xs">
                <div class="p-2 bg-green-100 rounded-lg">
                    <span class="font-bold text-green-700">A</span>
                    <span class="block text-gray-600">75-100%</span>
                    <span class="block text-green-600 font-bold">PASS</span>
                </div>
                <div class="p-2 bg-blue-100 rounded-lg">
                    <span class="font-bold text-blue-700">B</span>
                    <span class="block text-gray-600">65-74%</span>
                    <span class="block text-blue-600 font-bold">PASS</span>
                </div>
                <div class="p-2 bg-cyan-100 rounded-lg">
                    <span class="font-bold text-cyan-700">C</span>
                    <span class="block text-gray-600">45-64%</span>
                    <span class="block text-cyan-600 font-bold">PASS</span>
                </div>
                <div class="p-2 bg-yellow-100 rounded-lg">
                    <span class="font-bold text-yellow-700">D</span>
                    <span class="block text-gray-600">30-44%</span>
                    <span class="block text-yellow-600 font-bold">PASS</span>
                </div>
                <div class="p-2 bg-red-100 rounded-lg">
                    <span class="font-bold text-red-700">F</span>
                    <span class="block text-gray-600">0-29%</span>
                    <span class="block text-red-600 font-bold">FAIL</span>
                </div>
            </div>
            <p class="text-xs text-gray-500 mt-2 text-center">* Passing mark is 75% and above (Grade A)</p>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>