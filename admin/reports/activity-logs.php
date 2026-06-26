<?php
require_once '../../config.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';
requireRole('admin');

$page_title = 'Activity Logs';
include '../../includes/header.php';
include '../../includes/sidebar.php';
include '../../includes/navbar.php';

// Get filter parameters
$action_filter = $_GET['action'] ?? '';
$user_filter = $_GET['user_id'] ?? '';
$date_from = $_GET['date_from'] ?? date('Y-m-d', strtotime('-7 days'));
$date_to = $_GET['date_to'] ?? date('Y-m-d');

// Get users for filter
$users = $conn->query("SELECT id, first_name, last_name, email FROM users ORDER BY first_name");

// Build query
$query = "SELECT al.*, 
          CONCAT(u.first_name, ' ', u.last_name) as user_name,
          u.email as user_email,
          u.role as user_role
          FROM activity_logs al
          JOIN users u ON al.user_id = u.id
          WHERE DATE(al.created_at) BETWEEN '$date_from' AND '$date_to'";

if (!empty($action_filter)) {
    $query .= " AND al.action = '$action_filter'";
}

if (!empty($user_filter)) {
    $query .= " AND al.user_id = $user_filter";
}

$query .= " ORDER BY al.created_at DESC LIMIT 500";
$logs = $conn->query($query);

// Get unique actions for filter
$actions = $conn->query("SELECT DISTINCT action FROM activity_logs ORDER BY action");
?>

<div class="ml-64 mt-16 p-6">
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-800">Activity Logs</h1>
        <p class="text-gray-500 mt-1">Monitor all system activities and user actions</p>
    </div>

    <!-- Filters -->
    <div class="bg-white rounded-xl shadow-sm p-4 mb-6">
        <form method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">User</label>
                <select name="user_id" class="w-full border rounded-lg px-3 py-2">
                    <option value="">All Users</option>
                    <?php while($user = $users->fetch_assoc()): ?>
                        <option value="<?php echo $user['id']; ?>" <?php echo $user_filter == $user['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Action</label>
                <select name="action" class="w-full border rounded-lg px-3 py-2">
                    <option value="">All Actions</option>
                    <?php while($action = $actions->fetch_assoc()): ?>
                        <option value="<?php echo $action['action']; ?>" <?php echo $action_filter == $action['action'] ? 'selected' : ''; ?>>
                            <?php echo ucfirst($action['action']); ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Date From</label>
                <input type="date" name="date_from" value="<?php echo $date_from; ?>" 
                       class="w-full border rounded-lg px-3 py-2">
            </div>
            <div class="flex items-end">
                <button type="submit" class="w-full bg-purple-600 text-white px-4 py-2 rounded-lg hover:bg-purple-700">
                    <i class="fas fa-search mr-2"></i> Filter Logs
                </button>
            </div>
        </form>
    </div>

    <!-- Logs Table -->
    <div class="bg-white rounded-xl shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-gray-50 border-b">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Time</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">User</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Role</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Action</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Entity</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">IP Address</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    <?php if ($logs && $logs->num_rows > 0): ?>
                        <?php while($log = $logs->fetch_assoc()): ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4 text-sm text-gray-500">
                                    <?php echo date('M d, Y H:i:s', strtotime($log['created_at'])); ?>
                                 </td>
                                <td class="px-6 py-4">
                                    <div>
                                        <p class="font-medium text-gray-800"><?php echo htmlspecialchars($log['user_name']); ?></p>
                                        <p class="text-xs text-gray-500"><?php echo $log['user_email']; ?></p>
                                    </div>
                                 </td>
                                <td class="px-6 py-4">
                                    <span class="px-2 py-1 text-xs rounded-full 
                                        <?php echo $log['user_role'] == 'admin' ? 'bg-red-100 text-red-700' : 
                                                 ($log['user_role'] == 'teacher' ? 'bg-green-100 text-green-700' :
                                                 ($log['user_role'] == 'student' ? 'bg-blue-100 text-blue-700' : 'bg-purple-100 text-purple-700')); ?>">
                                        <?php echo ucfirst($log['user_role']); ?>
                                    </span>
                                 </td>
                                <td class="px-6 py-4">
                                    <span class="px-2 py-1 text-xs bg-gray-100 text-gray-700 rounded-full">
                                        <?php echo ucfirst($log['action']); ?>
                                    </span>
                                 </td>
                                <td class="px-6 py-4 text-gray-500">
                                    <?php echo $log['entity_type'] ? ucfirst($log['entity_type']) . ' #' . $log['entity_id'] : '-'; ?>
                                 </td>
                                <td class="px-6 py-4 text-gray-500 font-mono text-sm"><?php echo $log['ip_address']; ?> </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" class="px-6 py-12 text-center text-gray-500">
                                <i class="fas fa-history text-4xl mb-2 block"></i>
                                No activity logs found for selected criteria
                             </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>