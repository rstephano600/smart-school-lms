<?php
require_once 'config.php';
session_start();

// Force login as student
$_SESSION['user_id'] = 5;
$_SESSION['user_name'] = 'CHAMA Student';
$_SESSION['role'] = 'student';

echo "<h2>Test Chat Send</h2>";

$sender_id = 5;
$receiver_id = 4;
$message = "Test message " . date('H:i:s');

echo "Sender ID: $sender_id<br>";
echo "Receiver ID: $receiver_id<br>";
echo "Message: $message<br><br>";

// Insert message
$insert = $conn->prepare("INSERT INTO messages (sender_id, receiver_id, message, created_at) VALUES (?, ?, ?, NOW())");
$insert->bind_param("iis", $sender_id, $receiver_id, $message);

if ($insert->execute()) {
    echo "✅ Message sent! ID: " . $conn->insert_id . "<br>";
} else {
    echo "❌ Error: " . $conn->error . "<br>";
}

// Show last 5 messages
$result = $conn->query("SELECT * FROM messages ORDER BY id DESC LIMIT 5");
echo "<br><h3>Last 5 Messages:</h3>";
while ($row = $result->fetch_assoc()) {
    echo "ID: " . $row['id'] . " | Sender: " . $row['sender_id'] . " | Receiver: " . $row['receiver_id'] . " | Message: " . $row['message'] . "<br>";
}

echo "<br><a href='student/chat/index.php'>Go to Chat</a>";