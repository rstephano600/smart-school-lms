<?php
require_once 'config/code-executor.php';

echo "<h2>🔧 Testing OneCompiler API Connection</h2>";

// Test code
$test_cases = [
    ['language' => 'python', 'code' => 'print("Hello from Smart School LMS!")'],
    ['language' => 'c', 'code' => '#include <stdio.h>\nint main() { printf("Hello from C!\\n"); return 0; }'],
    ['language' => 'javascript', 'code' => 'console.log("Hello from JavaScript!");'],
];

foreach ($test_cases as $test) {
    $api_data = [
        'language' => $test['language'],
        'files' => [
            [
                'name' => 'main.' . ($language_extensions[$test['language']] ?? 'txt'),
                'content' => $test['code']
            ]
        ]
    ];
    
    echo "<h3>Testing: " . ucfirst($test['language']) . "</h3>";
    echo "<pre style='background:#f0f0f0;padding:10px;border-radius:5px;'>" . htmlspecialchars($test['code']) . "</pre>";
    
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
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($http_code == 200) {
        $result = json_decode($response, true);
        echo "<div style='background:#1a1a2e;color:#10b981;padding:15px;border-radius:5px;'>";
        echo "<strong>Output:</strong><br>";
        echo nl2br(htmlspecialchars($result['stdout'] ?? 'No output'));
        echo "</div>";
    } else {
        echo "<div style='background:#1a1a2e;color:#ef4444;padding:15px;border-radius:5px;'>";
        echo "<strong>Error:</strong> HTTP $http_code<br>";
        echo htmlspecialchars($response);
        echo "</div>";
    }
    echo "<hr>";
}

echo "<br><a href='student/coding/index.php' style='background:blue;color:white;padding:10px 20px;text-decoration:none;border-radius:5px;'>Go to Coding Playground</a>";