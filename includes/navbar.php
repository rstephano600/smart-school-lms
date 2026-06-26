<?php
// Load functions first
require_once dirname(__DIR__) . '/includes/functions.php';

// Get notification counts with safe defaults
$user_id = $_SESSION['user_id'] ?? 0;
$unread_notifications = getNotificationCount($user_id);
$unread_messages = getUnreadMessagesCount($user_id);
?>

<nav class="bg-white shadow-sm fixed top-0 right-0 left-64 z-20">
    <div class="px-6 py-3 flex justify-between items-center">
        <!-- Page Title -->
        <div>
            <h1 class="text-xl font-semibold text-gray-800">
                <?php echo $page_title ?? 'Dashboard'; ?>
            </h1>
        </div>
        
        <!-- Right Side Icons -->
        <div class="flex items-center space-x-4">
            <!-- Search -->
            <button class="text-gray-500 hover:text-gray-700" onclick="toggleSearch()">
                <i class="fas fa-search text-xl"></i>
            </button>
            
            <!-- Dark Mode Toggle -->
            <button class="text-gray-500 hover:text-gray-700" onclick="toggleDarkMode()">
                <i class="fas fa-moon text-xl"></i>
            </button>
            
            <!-- Notifications -->
            <div class="relative">
                <button class="text-gray-500 hover:text-gray-700" onclick="toggleNotifications()">
                    <i class="fas fa-bell text-xl"></i>
                    <?php if($unread_notifications > 0): ?>
                    <span class="absolute -top-1 -right-1 bg-red-500 text-white text-xs rounded-full w-4 h-4 flex items-center justify-center">
                        <?php echo $unread_notifications; ?>
                    </span>
                    <?php endif; ?>
                </button>
            </div>
            
            <!-- Messages -->
            <div class="relative">
                <button class="text-gray-500 hover:text-gray-700" onclick="toggleMessages()">
                    <i class="fas fa-envelope text-xl"></i>
                    <?php if($unread_messages > 0): ?>
                    <span class="absolute -top-1 -right-1 bg-blue-500 text-white text-xs rounded-full w-4 h-4 flex items-center justify-center">
                        <?php echo $unread_messages; ?>
                    </span>
                    <?php endif; ?>
                </button>
            </div>
            
            <!-- Profile Dropdown -->
            <div class="relative">
                <button class="flex items-center space-x-2" onclick="toggleProfileDropdown()">
                    <div class="w-8 h-8 bg-gradient-to-r from-blue-500 to-purple-600 rounded-full flex items-center justify-center text-white">
                        <?php echo strtoupper(substr($_SESSION['user_name'] ?? 'U', 0, 1)); ?>
                    </div>
                    <i class="fas fa-chevron-down text-xs text-gray-500"></i>
                </button>
            </div>
        </div>
    </div>
</nav>

<!-- Search Modal -->
<div id="searchModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 items-center justify-center">
    <div class="bg-white rounded-xl w-full max-w-md p-6">
        <h3 class="text-lg font-semibold mb-4">Search</h3>
        <input type="text" id="searchInput" placeholder="Search users, classes, subjects..." 
               class="w-full border rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
        <div id="searchResults" class="mt-4"></div>
    </div>
</div>

<script>
function toggleSearch() {
    document.getElementById('searchModal').classList.toggle('hidden');
}

function toggleDarkMode() {
    document.body.classList.toggle('dark');
}

function toggleNotifications() {
    fetch('<?php echo SITE_URL; ?>api/get-notifications.php')
        .then(response => response.json())
        .then(data => {
            console.log('Notifications:', data);
        })
        .catch(error => console.error('Error:', error));
}

function toggleMessages() {
    fetch('<?php echo SITE_URL; ?>api/get-messages.php')
        .then(response => response.json())
        .then(data => {
            console.log('Messages:', data);
        })
        .catch(error => console.error('Error:', error));
}

function toggleProfileDropdown() {
    // You can implement profile dropdown menu here
    window.location.href = '<?php echo SITE_URL; ?>auth/logout.php';
}

// Close modal when clicking outside
document.addEventListener('click', function(event) {
    const modal = document.getElementById('searchModal');
    if (event.target === modal) {
        modal.classList.add('hidden');
    }
});

// Auto search
const searchInput = document.getElementById('searchInput');
if (searchInput) {
    searchInput.addEventListener('input', function() {
        const query = this.value;
        if (query.length > 2) {
            fetch('<?php echo SITE_URL; ?>api/search.php?q=' + encodeURIComponent(query))
                .then(response => response.json())
                .then(data => {
                    const resultsDiv = document.getElementById('searchResults');
                    if (data.html) {
                        resultsDiv.innerHTML = data.html;
                    }
                })
                .catch(error => console.error('Search error:', error));
        } else {
            document.getElementById('searchResults').innerHTML = '';
        }
    });
}
</script>