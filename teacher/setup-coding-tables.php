<?php
require_once 'config.php';

echo "<h1>🔧 Setting Up Coding Tables</h1>";

// Check if tables exist
$check = $conn->query("SHOW TABLES LIKE 'coding_exercises'");

if ($check->num_rows > 0) {
    echo "<p style='color:green;'>✅ Tables already exist!</p>";
    echo "<a href='teacher/coding/exercises.php'>Go to Coding Exercises</a>";
    exit();
}

// Create coding_exercises table
$sql1 = "CREATE TABLE IF NOT EXISTS coding_exercises (
    id INT PRIMARY KEY AUTO_INCREMENT,
    title VARCHAR(200) NOT NULL,
    description TEXT,
    language VARCHAR(50),
    starter_code TEXT,
    solution_code TEXT,
    test_cases JSON,
    difficulty ENUM('easy', 'medium', 'hard') DEFAULT 'easy',
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
)";

if ($conn->query($sql1)) {
    echo "<p style='color:green;'>✅ Table 'coding_exercises' created successfully!</p>";
} else {
    echo "<p style='color:red;'>❌ Error: " . $conn->error . "</p>";
}

// Create code_submissions table
$sql2 = "CREATE TABLE IF NOT EXISTS code_submissions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    student_id INT NOT NULL,
    language VARCHAR(50) NOT NULL,
    code TEXT NOT NULL,
    stdin TEXT,
    stdout TEXT,
    stderr TEXT,
    status VARCHAR(20),
    execution_time INT,
    memory_used INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    INDEX idx_student (student_id),
    INDEX idx_language (language)
)";

if ($conn->query($sql2)) {
    echo "<p style='color:green;'>✅ Table 'code_submissions' created successfully!</p>";
} else {
    echo "<p style='color:red;'>❌ Error: " . $conn->error . "</p>";
}

// Create exercise_results table
$sql3 = "CREATE TABLE IF NOT EXISTS exercise_results (
    id INT PRIMARY KEY AUTO_INCREMENT,
    student_id INT NOT NULL,
    exercise_id INT NOT NULL,
    score INT DEFAULT 0,
    passed_tests INT DEFAULT 0,
    total_tests INT DEFAULT 0,
    code TEXT,
    submitted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    FOREIGN KEY (exercise_id) REFERENCES coding_exercises(id) ON DELETE CASCADE
)";

if ($conn->query($sql3)) {
    echo "<p style='color:green;'>✅ Table 'exercise_results' created successfully!</p>";
} else {
    echo "<p style='color:red;'>❌ Error: " . $conn->error . "</p>";
}

echo "<hr>";
echo "<h3>✅ Setup complete!</h3>";
echo "<a href='teacher/coding/exercises.php' style='background:blue;color:white;padding:10px 20px;text-decoration:none;border-radius:5px;'>Go to Coding Exercises</a>";
?>