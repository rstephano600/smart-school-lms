<?php
require_once 'config.php';

// If already logged in, redirect to appropriate dashboard
if (isset($_SESSION['user_id'])) {
    $role = $_SESSION['role'];
    switch($role) {
        case 'admin': header('Location: admin/dashboard.php'); break;
        case 'academic': header('Location: academic/dashboard.php'); break;
        case 'teacher': header('Location: teacher/dashboard.php'); break;
        case 'student': header('Location: student/dashboard.php'); break;
        case 'parent': header('Location: parent/dashboard.php'); break;
    }
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Smart School LMS</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .glass-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 24px;
            box-shadow: 0 20px 35px -10px rgba(0, 0, 0, 0.1);
        }
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
    </style>
</head>
<body class="min-h-screen flex items-center justify-center p-4">
    <div class="glass-card max-w-md w-full p-8">
        <div class="text-center mb-8">
            <div class="w-20 h-20 bg-gradient-to-r from-blue-500 to-purple-600 rounded-2xl flex items-center justify-center mx-auto mb-4">
                <i class="fas fa-graduation-cap text-4xl text-white"></i>
            </div>
            <h1 class="text-2xl font-bold text-gray-800">Smart School LMS</h1>
            <p class="text-gray-500 mt-2">Login to your account</p>
        </div>

        <?php if (isset($_GET['error'])): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg mb-4">
                <?php echo htmlspecialchars($_GET['error']); ?>
            </div>
        <?php endif; ?>

        <form action="auth/login.php" method="POST" class="space-y-5">
            <div>
                <label class="block text-gray-700 font-medium mb-2">Email Address</label>
                <div class="relative">
                    <i class="fas fa-envelope absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
                    <input type="email" name="email" required 
                           class="w-full pl-10 pr-4 py-3 border border-gray-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-transparent"
                           placeholder="Enter your email">
                </div>
            </div>

            <div>
                <label class="block text-gray-700 font-medium mb-2">Password</label>
                <div class="relative">
                    <i class="fas fa-lock absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
                    <input type="password" name="password" required 
                           class="w-full pl-10 pr-4 py-3 border border-gray-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-transparent"
                           placeholder="Enter your password">
                </div>
            </div>

            <div class="flex items-center justify-between">
                <label class="flex items-center">
                    <input type="checkbox" name="remember" class="rounded border-gray-300 text-purple-600 focus:ring-purple-500">
                    <span class="ml-2 text-gray-600">Remember me</span>
                </label>
                <a href="auth/forgot-password.php" class="text-purple-600 hover:text-purple-700">Forgot Password?</a>
            </div>

            <button type="submit" 
                    class="w-full bg-gradient-to-r from-blue-500 to-purple-600 text-white py-3 rounded-xl font-semibold hover:shadow-lg transition-all duration-300">
                Login
            </button>
        </form>

        <div class="mt-6 text-center text-gray-500 text-sm">
            <p>Demo Credentials:</p>
            <p>Admin: admin@school.com / Admin@123</p>
        </div>
    </div>
</body>
</html>