<?php
require_once '../../config.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';
requireRole('admin');

$page_title = 'Backup & Maintenance';
include '../../includes/header.php';
include '../../includes/sidebar.php';
include '../../includes/navbar.php';

$error = '';
$success = '';
$backup_files = [];

// Get backup files
$backup_dir = '../../backups/';
if (!is_dir($backup_dir)) {
    mkdir($backup_dir, 0777, true);
}

$files = scandir($backup_dir);
foreach ($files as $file) {
    if ($file != '.' && $file != '..' && pathinfo($file, PATHINFO_EXTENSION) == 'sql') {
        $backup_files[] = [
            'name' => $file,
            'size' => filesize($backup_dir . $file),
            'date' => date('Y-m-d H:i:s', filemtime($backup_dir . $file))
        ];
    }
}
arsort($backup_files);

// Handle backup creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'backup') {
    $backup_name = 'backup_' . date('Y-m-d_H-i-s') . '.sql';
    $backup_path = $backup_dir . $backup_name;
    
    // Create database backup
    $tables = [];
    $result = $conn->query("SHOW TABLES");
    while ($row = $result->fetch_row()) {
        $tables[] = $row[0];
    }
    
    $backup_content = "-- Smart School LMS Database Backup\n";
    $backup_content .= "-- Generated: " . date('Y-m-d H:i:s') . "\n\n";
    
    foreach ($tables as $table) {
        // Get create table syntax
        $create = $conn->query("SHOW CREATE TABLE $table")->fetch_row();
        $backup_content .= "DROP TABLE IF EXISTS `$table`;\n";
        $backup_content .= $create[1] . ";\n\n";
        
        // Get table data
        $rows = $conn->query("SELECT * FROM $table");
        if ($rows->num_rows > 0) {
            $columns = [];
            $col_info = $conn->query("SHOW COLUMNS FROM $table");
            while ($col = $col_info->fetch_assoc()) {
                $columns[] = $col['Field'];
            }
            
            while ($row = $rows->fetch_assoc()) {
                $values = [];
                foreach ($columns as $col) {
                    $val = $row[$col];
                    if ($val === null) {
                        $values[] = "NULL";
                    } else {
                        $values[] = "'" . $conn->real_escape_string($val) . "'";
                    }
                }
                $backup_content .= "INSERT INTO `$table` (`" . implode("`, `", $columns) . "`) VALUES (" . implode(", ", $values) . ");\n";
            }
            $backup_content .= "\n";
        }
    }
    
    if (file_put_contents($backup_path, $backup_content)) {
        logActivity($_SESSION['user_id'], 'created database backup');
        $success = "Database backup created successfully: " . $backup_name;
        
        // Refresh backup list
        $backup_files = [];
        $files = scandir($backup_dir);
        foreach ($files as $file) {
            if ($file != '.' && $file != '..' && pathinfo($file, PATHINFO_EXTENSION) == 'sql') {
                $backup_files[] = [
                    'name' => $file,
                    'size' => filesize($backup_dir . $file),
                    'date' => date('Y-m-d H:i:s', filemtime($backup_dir . $file))
                ];
            }
        }
    } else {
        $error = "Failed to create backup";
    }
}

// Handle backup download
if (isset($_GET['download']) && !empty($_GET['download'])) {
    $file = basename($_GET['download']);
    $file_path = $backup_dir . $file;
    
    if (file_exists($file_path)) {
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . $file . '"');
        header('Content-Length: ' . filesize($file_path));
        readfile($file_path);
        exit();
    }
}

// Handle backup deletion
if (isset($_GET['delete']) && !empty($_GET['delete'])) {
    $file = basename($_GET['delete']);
    $file_path = $backup_dir . $file;
    
    if (file_exists($file_path) && unlink($file_path)) {
        logActivity($_SESSION['user_id'], 'deleted backup file');
        $success = "Backup file deleted: " . $file;
        
        // Refresh backup list
        header('Location: backup.php');
        exit();
    } else {
        $error = "Failed to delete backup file";
    }
}

// Handle cache clear
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'clear_cache') {
    // Clear temporary files
    $temp_files = glob('../../cache/*');
    foreach ($temp_files as $file) {
        if (is_file($file)) {
            unlink($file);
        }
    }
    $success = "Cache cleared successfully!";
}

// Handle system check
$system_status = [];
$system_status['php_version'] = PHP_VERSION;
$system_status['mysql_version'] = $conn->server_info;
$system_status['upload_max_filesize'] = ini_get('upload_max_filesize');
$system_status['memory_limit'] = ini_get('memory_limit');
$system_status['max_execution_time'] = ini_get('max_execution_time') . ' seconds';
?>

<div class="ml-64 mt-16 p-6">
    <div class="max-w-4xl mx-auto">
        <div class="mb-6">
            <h1 class="text-2xl font-bold text-gray-800">Backup & Maintenance</h1>
            <p class="text-gray-500 mt-1">Manage database backups and system maintenance</p>
        </div>

        <!-- Settings Tabs -->
        <div class="bg-white rounded-xl shadow-sm overflow-hidden mb-6">
            <div class="border-b">
                <nav class="flex flex-wrap">
                    <a href="general.php" class="px-6 py-3 text-gray-600 hover:text-gray-800">
                        <i class="fas fa-building mr-2"></i> General
                    </a>
                    <a href="theme.php" class="px-6 py-3 text-gray-600 hover:text-gray-800">
                        <i class="fas fa-palette mr-2"></i> Theme
                    </a>
                    <a href="email.php" class="px-6 py-3 text-gray-600 hover:text-gray-800">
                        <i class="fas fa-envelope mr-2"></i> Email
                    </a>
                    <a href="backup.php" class="px-6 py-3 text-blue-600 border-b-2 border-blue-600 font-medium">
                        <i class="fas fa-database mr-2"></i> Backup
                    </a>
                </nav>
            </div>
            
            <div class="p-6">
                <?php if($error): ?>
                    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg mb-4"><?php echo $error; ?></div>
                <?php endif; ?>
                
                <?php if($success): ?>
                    <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-lg mb-4"><?php echo $success; ?></div>
                <?php endif; ?>

                <!-- Create Backup Section -->
                <div class="mb-8 pb-8 border-b">
                    <div class="flex justify-between items-center">
                        <div>
                            <h3 class="text-lg font-semibold">Database Backup</h3>
                            <p class="text-sm text-gray-500">Create a full backup of your database</p>
                        </div>
                        <form method="POST">
                            <input type="hidden" name="action" value="backup">
                            <button type="submit" class="bg-blue-600 text-white px-6 py-2 rounded-lg hover:bg-blue-700">
                                <i class="fas fa-database mr-2"></i> Create Backup Now
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Backup Files List -->
                <div class="mb-8 pb-8 border-b">
                    <h3 class="text-lg font-semibold mb-4">Available Backups</h3>
                    
                    <?php if (count($backup_files) > 0): ?>
                        <div class="overflow-x-auto">
                            <table class="w-full">
                                <thead class="bg-gray-50 border-b">
                                    <tr>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">File Name</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Size</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200">
                                    <?php foreach($backup_files as $backup): ?>
                                        <tr>
                                            <td class="px-4 py-3 text-sm"><?php echo $backup['name']; ?></td>
                                            <td class="px-4 py-3 text-sm"><?php echo round($backup['size'] / 1024, 2); ?> KB</td>
                                            <td class="px-4 py-3 text-sm"><?php echo $backup['date']; ?></td>
                                            <td class="px-4 py-3">
                                                <a href="?download=<?php echo urlencode($backup['name']); ?>" class="text-blue-600 hover:text-blue-800 mr-3">
                                                    <i class="fas fa-download"></i>
                                                </a>
                                                <a href="?delete=<?php echo urlencode($backup['name']); ?>" 
                                                   onclick="return confirm('Are you sure you want to delete this backup?')"
                                                   class="text-red-600 hover:text-red-800">
                                                    <i class="fas fa-trash-alt"></i>
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-8 bg-gray-50 rounded-lg">
                            <i class="fas fa-database text-4xl text-gray-300 mb-2 block"></i>
                            <p class="text-gray-500">No backups found. Create your first backup!</p>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- System Status -->
                <div class="mb-8 pb-8 border-b">
                    <h3 class="text-lg font-semibold mb-4">System Status</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="flex justify-between p-3 bg-gray-50 rounded-lg">
                            <span class="text-gray-600">PHP Version:</span>
                            <span class="font-semibold"><?php echo $system_status['php_version']; ?></span>
                        </div>
                        <div class="flex justify-between p-3 bg-gray-50 rounded-lg">
                            <span class="text-gray-600">MySQL Version:</span>
                            <span class="font-semibold"><?php echo $system_status['mysql_version']; ?></span>
                        </div>
                        <div class="flex justify-between p-3 bg-gray-50 rounded-lg">
                            <span class="text-gray-600">Max Upload Size:</span>
                            <span class="font-semibold"><?php echo $system_status['upload_max_filesize']; ?></span>
                        </div>
                        <div class="flex justify-between p-3 bg-gray-50 rounded-lg">
                            <span class="text-gray-600">Memory Limit:</span>
                            <span class="font-semibold"><?php echo $system_status['memory_limit']; ?></span>
                        </div>
                        <div class="flex justify-between p-3 bg-gray-50 rounded-lg">
                            <span class="text-gray-600">Max Execution Time:</span>
                            <span class="font-semibold"><?php echo $system_status['max_execution_time']; ?></span>
                        </div>
                    </div>
                </div>

                <!-- Maintenance Actions -->
                <div>
                    <h3 class="text-lg font-semibold mb-4">Maintenance</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <form method="POST" class="flex justify-between items-center p-3 bg-gray-50 rounded-lg">
                            <span class="text-gray-600">Clear System Cache</span>
                            <input type="hidden" name="action" value="clear_cache">
                            <button type="submit" class="bg-yellow-600 text-white px-4 py-2 rounded-lg hover:bg-yellow-700">
                                <i class="fas fa-broom mr-2"></i> Clear Cache
                            </button>
                        </form>
                        
                        <div class="flex justify-between items-center p-3 bg-gray-50 rounded-lg">
                            <span class="text-gray-600">Optimize Database Tables</span>
                            <button onclick="optimizeDatabase()" class="bg-purple-600 text-white px-4 py-2 rounded-lg hover:bg-purple-700">
                                <i class="fas fa-chart-line mr-2"></i> Optimize
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function optimizeDatabase() {
    if (confirm('This will optimize all database tables. Continue?')) {
        fetch('optimize-db.php', {
            method: 'POST'
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Database optimized successfully!');
            } else {
                alert('Error: ' + data.error);
            }
        })
        .catch(error => {
            alert('Error optimizing database');
        });
    }
}
</script>

<?php include '../../includes/footer.php'; ?>