<?php
header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="exam_questions_template.csv"');

$output = fopen('php://output', 'w');

// Add UTF-8 BOM for Excel
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

// Headers
fputcsv($output, ['Question Text', 'Question Type', 'Options', 'Correct Answer', 'Marks', 'Topic', 'Difficulty']);

// Sample data
$samples = [
    ['What is the capital of Tanzania?', 'mcq', 'A:Dodoma|B:Dar es Salaam|C:Arusha|D:Mwanza', 'A', '2', 'Geography', 'easy'],
    ['The sun rises in the east.', 'truefalse', '', 'true', '1', 'Science', 'easy'],
    ['Explain the process of photosynthesis.', 'essay', '', 'Photosynthesis is the process by which plants convert light energy into chemical energy.', '5', 'Biology', 'medium'],
    ['2 + 2 = ___', 'fill_blanks', '', '4', '1', 'Mathematics', 'easy'],
    ['Who wrote "Romeo and Juliet"?', 'short_answer', '', 'William Shakespeare', '2', 'Literature', 'medium'],
];

foreach ($samples as $sample) {
    fputcsv($output, $sample);
}

fclose($output);
exit();
?>