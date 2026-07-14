<?php
require_once 'config.php';

echo "<h2>🔧 Fix Correct Answers</h2>";

// Get all questions with empty correct_answer
$query = $conn->prepare("SELECT id, question_text, options, correct_answer FROM exam_questions WHERE correct_answer = '' OR correct_answer IS NULL");
$query->execute();
$questions = $query->get_result();

if ($questions->num_rows == 0) {
    echo "<p style='color:green;'>✅ All questions have correct answers!</p>";
    echo "<br><a href='index.php'>Go to Login</a>";
    exit();
}

echo "<p>Found <strong>" . $questions->num_rows . "</strong> questions without correct answers.</p>";
echo "<hr>";

$fixed = 0;
while ($q = $questions->fetch_assoc()) {
    $options = json_decode($q['options'], true);
    $correct_answer = '';
    
    // Try to guess correct answer from options
    if (!empty($options) && is_array($options)) {
        // Get first non-empty option as correct
        foreach ($options as $key => $val) {
            if (!empty($val)) {
                $correct_answer = $key;
                break;
            }
        }
    }
    
    // If still empty, set default
    if (empty($correct_answer)) {
        $correct_answer = 'A';
    }
    
    // Update
    $update = $conn->prepare("UPDATE exam_questions SET correct_answer = ? WHERE id = ?");
    $update->bind_param("si", $correct_answer, $q['id']);
    if ($update->execute()) {
        $fixed++;
        echo "<p style='color:green;'>✅ Question ID " . $q['id'] . " - Correct Answer set to: <strong>" . $correct_answer . "</strong></p>";
    } else {
        echo "<p style='color:red;'>❌ Failed to update Question ID " . $q['id'] . "</p>";
    }
}

echo "<hr>";
echo "<p style='color:green;font-size:18px;'>✅ Fixed <strong>$fixed</strong> questions!</p>";
echo "<br><a href='index.php' style='background:blue;color:white;padding:10px 20px;text-decoration:none;border-radius:5px;'>Go to Login</a>";
?>