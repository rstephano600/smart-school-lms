<?php
session_start();
$_SESSION['user_id'] = 5;
$_SESSION['user_name'] = 'CHAMA Student';
$_SESSION['role'] = 'student';
?>
<!DOCTYPE html>
<html>
<head>
    <title>Test AJAX</title>
</head>
<body>
    <h2>Test AJAX Chat</h2>
    <input type="text" id="message" placeholder="Type message">
    <button onclick="sendMessage()">Send</button>
    <div id="result"></div>

    <script>
    function sendMessage() {
        const message = document.getElementById('message').value;
        const formData = new FormData();
        formData.append('receiver_id', '4');
        formData.append('message', message);
        
        document.getElementById('result').innerHTML = 'Sending...';
        
        fetch('student/chat/send.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.text())
        .then(text => {
            document.getElementById('result').innerHTML = 'Response: <pre>' + text + '</pre>';
        })
        .catch(error => {
            document.getElementById('result').innerHTML = 'Error: ' + error;
        });
    }
    </script>
</body>
</html>