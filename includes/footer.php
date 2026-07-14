<footer class="bg-white border-t mt-8">
    <div class="px-6 py-4">
        <div class="text-center text-gray-500 text-sm">
            &copy; <?php echo date('Y'); ?> <?php echo SITE_NAME; ?>. All rights reserved.
        </div>
    </div>
</footer>

<!-- ===================================================== -->
<!-- GLOBAL JAVASCRIPT -->
<!-- ===================================================== -->
<script>
// ============================================
// 1. MODALS & UI
// ============================================

// Close modals when clicking outside
document.addEventListener('click', function(event) {
    const modal = document.getElementById('searchModal');
    if (modal && event.target === modal) {
        modal.classList.add('hidden');
    }
});

// Auto-hide alerts after 5 seconds
document.querySelectorAll('.alert').forEach(alert => {
    setTimeout(() => alert.remove(), 5000);
});

// Live search
const searchInput = document.getElementById('searchInput');
if (searchInput) {
    searchInput.addEventListener('input', function() {
        const query = this.value;
        if (query.length > 2) {
            fetch(`<?php echo SITE_URL; ?>api/search.php?q=${encodeURIComponent(query)}`)
                .then(response => response.json())
                .then(data => {
                    const resultsDiv = document.getElementById('searchResults');
                    if (data.html) {
                        resultsDiv.innerHTML = data.html;
                    }
                })
                .catch(error => console.error('Search error:', error));
        } else {
            const resultsDiv = document.getElementById('searchResults');
            if (resultsDiv) resultsDiv.innerHTML = '';
        }
    });
}

// ============================================
// 2. USER ACTIVITY TRACKING
// ============================================

// Only track if user is logged in
<?php if(isset($_SESSION['user_id']) && $_SESSION['user_id'] > 0): ?>

// Variables for tracking
let activityInterval;
let activityTimeout;
let isTracking = true;

// Function to update user activity
function updateUserActivity() {
    if (!isTracking) return;
    
    fetch('<?php echo SITE_URL; ?>api/update-activity.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        credentials: 'same-origin'
    })
    .then(response => response.json())
    .then(data => {
        // Silent tracking - no UI updates needed
        if (data.success) {
            // Optional: Update online status badge if exists
            updateOnlineStatus();
        }
    })
    .catch(error => {
        // Silent fail - don't show errors to user
        console.debug('Activity tracking error:', error);
    });
}

// Update online status badge (if exists)
function updateOnlineStatus() {
    const onlineBadge = document.querySelector('.online-status-badge');
    if (onlineBadge) {
        onlineBadge.textContent = '● Online';
        onlineBadge.className = 'online-status-badge text-green-500 text-xs';
    }
}

// Reset activity timer (user is active)
function resetActivityTimer() {
    clearTimeout(activityTimeout);
    activityTimeout = setTimeout(function() {
        updateUserActivity();
    }, 5000);
}

// Start periodic activity tracking (every 30 seconds)
function startActivityTracking() {
    // Initial update
    updateUserActivity();
    
    // Periodic updates every 30 seconds
    activityInterval = setInterval(function() {
        updateUserActivity();
    }, 30000);
}

// Stop activity tracking
function stopActivityTracking() {
    isTracking = false;
    clearInterval(activityInterval);
    clearTimeout(activityTimeout);
}

// ============================================
// 3. USER INTERACTION TRACKING
// ============================================

// Track when user is active (clicks, scrolls, types)
document.addEventListener('click', resetActivityTimer);
document.addEventListener('scroll', resetActivityTimer);
document.addEventListener('keydown', resetActivityTimer);
document.addEventListener('mousemove', function(e) {
    // Only track every few seconds to avoid too many updates
    if (!window._lastMouseMove || Date.now() - window._lastMouseMove > 10000) {
        window._lastMouseMove = Date.now();
        resetActivityTimer();
    }
});

// Track when user touches on mobile
document.addEventListener('touchstart', resetActivityTimer);
document.addEventListener('touchmove', resetActivityTimer);

// Track when user returns to tab
document.addEventListener('visibilitychange', function() {
    if (!document.hidden) {
        // User returned to tab - update activity
        resetActivityTimer();
        updateUserActivity();
    } else {
        // User left tab - track time away
        console.debug('User away from tab');
    }
});

// Track when user focuses on window
window.addEventListener('focus', function() {
    resetActivityTimer();
    updateUserActivity();
});

// Track when user blurs window
window.addEventListener('blur', function() {
    // Don't stop tracking, just note it
    console.debug('User switched to another window');
});

// ============================================
// 4. BEFORE UNLOAD (User leaves page)
// ============================================

// Update activity before user leaves
window.addEventListener('beforeunload', function() {
    // Stop tracking
    stopActivityTracking();
    
    // Final activity update (synchronous, may not complete)
    try {
        navigator.sendBeacon('<?php echo SITE_URL; ?>api/update-activity.php');
    } catch(e) {
        // Ignore errors
    }
});

// ============================================
// 5. START TRACKING
// ============================================

// Start tracking when page loads
document.addEventListener('DOMContentLoaded', function() {
    startActivityTracking();
    
    // Update online status for current user
    updateOnlineStatus();
});

// ============================================
// 6. AUTO-REFRESH FOR TEACHER DASHBOARD
// ============================================

// Auto-refresh online students list on teacher dashboard (every 30 seconds)
const onlineStudentsContainer = document.getElementById('onlineStudents');
if (onlineStudentsContainer) {
    setInterval(function() {
        fetch('<?php echo SITE_URL; ?>teacher/get-online-students.php')
            .then(response => response.text())
            .then(data => {
                onlineStudentsContainer.innerHTML = data;
            })
            .catch(error => console.error('Error refreshing online students:', error));
    }, 30000);
}

// ============================================
// 7. ONLINE STATUS BADGE (Optional)
// ============================================

// Add online status indicator to user avatar if exists
const userAvatar = document.querySelector('.user-avatar-container');
if (userAvatar) {
    const statusDot = document.createElement('div');
    statusDot.className = 'online-status-dot absolute bottom-0 right-0 w-3 h-3 bg-green-500 rounded-full border-2 border-white';
    userAvatar.appendChild(statusDot);
}

<?php else: ?>
// User not logged in - no tracking
console.log('User not logged in - activity tracking disabled');
<?php endif; ?>

// ============================================
// 8. CONSOLE CLEANUP (Optional)
// ============================================

// Disable console.log in production (optional)
// if (window.location.hostname !== 'localhost') {
//     console.log = function() {};
// }

console.log('✅ Smart School LMS - System Loaded');
console.log('📊 Activity tracking ' + (<?php echo isset($_SESSION['user_id']) ? 'enabled' : 'disabled'; ?>));
</script>

<!-- ===================================================== -->
<!-- ADDITIONAL SCRIPTS FOR SPECIFIC PAGES -->
<!-- ===================================================== -->

<!-- Chart.js (if not already loaded) -->
<?php if(strpos($_SERVER['PHP_SELF'], 'dashboard') !== false): ?>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<?php endif; ?>

<!-- Font Awesome (if not already loaded) -->
<!-- Already loaded in header -->

<!-- ===================================================== -->
<!-- CUSTOM STYLES FOR ONLINE STATUS -->
<!-- ===================================================== -->
<style>
.online-status-dot {
    animation: pulse-dot 2s ease-in-out infinite;
}

@keyframes pulse-dot {
    0%, 100% { opacity: 1; transform: scale(1); }
    50% { opacity: 0.5; transform: scale(0.8); }
}

/* Toast notification for online status */
.online-toast {
    position: fixed;
    bottom: 20px;
    right: 20px;
    background: #10b981;
    color: white;
    padding: 12px 20px;
    border-radius: 12px;
    box-shadow: 0 10px 25px rgba(0,0,0,0.1);
    z-index: 9999;
    display: none;
    align-items: center;
    gap: 10px;
    animation: slideUp 0.3s ease-out;
}

.online-toast.show {
    display: flex;
}

@keyframes slideUp {
    from { transform: translateY(20px); opacity: 0; }
    to { transform: translateY(0); opacity: 1; }
}
</style>

</body>
</html>