<?php
require_once '../config.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
require_once '../config/code-executor.php';

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Not logged in']);
    exit();
}

// Get student ID
$student_query = $conn->prepare("SELECT id FROM students WHERE user_id = ?");
$student_query->bind_param("i", $_SESSION['user_id']);
$student_query->execute();
$student = $student_query->get_result()->fetch_assoc();
$student_id = $student['id'] ?? 0;

// Get request data
$data = json_decode(file_get_contents('php://input'), true);
$language = $data['language'] ?? '';
$code = $data['code'] ?? '';
$stdin = $data['stdin'] ?? '';
$expected_output = $data['expectedOutput'] ?? '';

// Validate
if (empty($language) || empty($code)) {
    echo json_encode(['success' => false, 'error' => 'Language and code are required']);
    exit();
}

// Check if language is supported
if (!isset($supported_languages[$language])) {
    echo json_encode(['success' => false, 'error' => 'Unsupported language: ' . $language]);
    exit();
}

// Get file extension
$extension = $language_extensions[$language] ?? 'txt';
$filename = 'main.' . $extension;

// Prepare API request
$api_data = [
    'language' => $language,
    'files' => [
        [
            'name' => $filename,
            'content' => $code
        ]
    ]
];

// Add stdin if provided
if (!empty($stdin)) {
    // Handle multiple lines of input
    $api_data['stdin'] = $stdin;
}

// Execute via OneCompiler API
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, ONECOMPILER_API_URL);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Authorization: Bearer ' . ONECOMPILER_API_KEY,
    'X-API-Key: ' . ONECOMPILER_API_KEY
]);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($api_data));
curl_setopt($ch, CURLOPT_TIMEOUT, 60);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curl_error = curl_error($ch);
curl_close($ch);

// Parse response
$result = json_decode($response, true);

// Prepare output
$output = [
    'success' => false,
    'stdout' => '',
    'stderr' => '',
    'error' => '',
    'execution_time' => 0,
    'memory_used' => 0,
    'test_passed' => false
];

if ($http_code == 200 && isset($result['stdout'])) {
    $output['success'] = true;
    $output['stdout'] = $result['stdout'] ?? '';
    $output['stderr'] = $result['stderr'] ?? '';
    $output['execution_time'] = $result['executionTime'] ?? 0;
    $output['memory_used'] = $result['memoryUsed'] ?? 0;
    
    // Check if expected output matches
    if (!empty($expected_output)) {
        $output['test_passed'] = trim($output['stdout']) === trim($expected_output);
    }
    
    if (!empty($output['stderr'])) {
        $output['error'] = $output['stderr'];
    }
} else {
    if ($curl_error) {
        $output['error'] = 'CURL Error: ' . $curl_error;
    } elseif ($http_code == 401) {
        $output['error'] = 'Invalid API Key. Please check your API key.';
    } elseif ($http_code == 429) {
        $output['error'] = 'Rate limit exceeded. Please try again later.';
    } else {
        $output['error'] = $result['error'] ?? 'Execution failed. Please try again. (HTTP: ' . $http_code . ')';
    }
}

// Save submission to database
if ($student_id > 0) {
    $insert = $conn->prepare("
        INSERT INTO code_submissions (student_id, language, code, stdin, stdout, stderr, status, execution_time, memory_used)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $status = $output['success'] ? 'success' : 'error';
    $insert->bind_param(
        "issssssii",
        $student_id,
        $language,
        $code,
        $stdin,
        $output['stdout'],
        $output['stderr'],
        $status,
        $output['execution_time'],
        $output['memory_used']
    );
    $insert->execute();
}

// Return response
echo json_encode($output);
exit();
?>