<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

require_once 'config.php';

// If already logged in, redirect to appropriate dashboard
if (isset($_SESSION['user_id'])) {
    $role = $_SESSION['role'] ?? '';
    $redirect = [
        'admin' => 'admin/dashboard.php',
        'academic' => 'academic/dashboard.php',
        'teacher' => 'teacher/dashboard.php',
        'student' => 'student/dashboard.php',
        'parent' => 'parent/dashboard.php'
    ];
    header('Location: ' . ($redirect[$role] ?? 'index.php'));
    exit();
}

// Get school logo from database
$logo_path = '';
$school_name = SITE_NAME;

$logo_query = $conn->query("SELECT school_logo, school_name FROM school_settings LIMIT 1");
if ($logo_query && $logo_query->num_rows > 0) {
    $logo_row = $logo_query->fetch_assoc();
    if (!empty($logo_row['school_logo'])) {
        $possible_paths = [
            $logo_row['school_logo'],
            '../' . $logo_row['school_logo'],
            '../../' . $logo_row['school_logo']
        ];
        foreach ($possible_paths as $path) {
            if (file_exists($path)) {
                $logo_path = $path;
                break;
            }
        }
        if (empty($logo_path)) {
            $logo_path = $logo_row['school_logo'];
        }
    }
    if (!empty($logo_row['school_name'])) {
        $school_name = $logo_row['school_name'];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - <?php echo htmlspecialchars($school_name); ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        @keyframes float {
            0%, 100% { transform: translateY(0px); }
            50% { transform: translateY(-20px); }
        }
        .float-animation {
            animation: float 6s ease-in-out infinite;
        }
        .glass-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 24px;
            box-shadow: 0 20px 35px -10px rgba(0, 0, 0, 0.1);
        }
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .logo-container {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            overflow: hidden;
            margin: 0 auto 16px;
            border: 4px solid white;
            box-shadow: 0 4px 15px rgba(0,0,0,0.15);
            background: white;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .logo-container img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .logo-container i {
            font-size: 40px;
            color: #667eea;
        }
        .login-btn {
            transition: all 0.3s ease;
        }
        .login-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(118, 75, 162, 0.4);
        }
        input:focus {
            box-shadow: 0 0 0 3px rgba(118, 75, 162, 0.2);
        }
    </style>
</head>
<body>
    <div class="glass-card max-w-md w-full p-8">
        <div class="text-center mb-8">
            <!-- Logo -->
            <div class="logo-container float-animation">
                <?php if (!empty($logo_path) && file_exists($logo_path)): ?>
                    <img src="<?php echo htmlspecialchars($logo_path); ?>" alt="<?php echo htmlspecialchars($school_name); ?> Logo">
                <?php else: ?>
                    <i class="fas fa-graduation-cap"></i>
                <?php endif; ?>
            </div>
            <h1 class="text-2xl font-bold text-gray-800"><?php echo htmlspecialchars($school_name); ?></h1>
            <p class="text-gray-500 mt-2">Login to your account</p>
        </div>

        <!-- Error/Success Messages -->
        <?php if (isset($_GET['error'])): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg mb-4">
                <i class="fas fa-exclamation-circle mr-2"></i> 
                <?php echo htmlspecialchars($_GET['error']); ?>
            </div>
        <?php endif; ?>
        
        <?php if (isset($_GET['success'])): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-lg mb-4">
                <i class="fas fa-check-circle mr-2"></i> 
                <?php echo htmlspecialchars($_GET['success']); ?>
            </div>
        <?php endif; ?>

        <!-- Login Form -->
        <form action="auth/login.php" method="POST" class="space-y-5">
            <div>
                <label class="block text-gray-700 font-medium mb-2">Email Address</label>
                <div class="relative">
                    <i class="fas fa-envelope absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
                    <input type="email" name="email" required 
                           class="w-full pl-10 pr-4 py-3 border border-gray-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-transparent transition-all"
                           placeholder="Enter your email">
                </div>
            </div>

            <div>
                <label class="block text-gray-700 font-medium mb-2">Password</label>
                <div class="relative">
                    <i class="fas fa-lock absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
                    <input type="password" name="password" required 
                           class="w-full pl-10 pr-4 py-3 border border-gray-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-transparent transition-all"
                           placeholder="Enter your password">
                </div>
            </div>

            <div class="flex items-center justify-between">
                <label class="flex items-center cursor-pointer">
                    <input type="checkbox" name="remember" 
                           class="rounded border-gray-300 text-purple-600 focus:ring-purple-500">
                    <span class="ml-2 text-gray-600 text-sm">Remember me</span>
                </label>
                <a href="auth/forgot-password.php" class="text-purple-600 hover:text-purple-700 text-sm transition-colors">
                    Forgot Password?
                </a>
            </div>

            <button type="submit" 
                    class="login-btn w-full bg-gradient-to-r from-blue-500 to-purple-600 text-white py-3 rounded-xl font-semibold shadow-md transition-all duration-300">
                <i class="fas fa-sign-in-alt mr-2"></i> Login
            </button>
        </form>

        <!-- Footer -->
        <div class="mt-6 pt-4 border-t text-center">
            <p class="text-gray-400 text-xs">
                &copy; <?php echo date('Y'); ?> <?php echo htmlspecialchars($school_name); ?> 
                <span class="mx-1">•</span> 
                All rights reserved
            </p>
            <p class="text-gray-400 text-[10px] mt-1">Version 2.0</p>
        </div>
    </div>
</body>
</html>