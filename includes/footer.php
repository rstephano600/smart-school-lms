<footer class="bg-white border-t mt-8">
    <div class="px-6 py-4">
        <div class="text-center text-gray-500 text-sm">
            &copy; <?php echo date('Y'); ?> <?php echo SITE_NAME; ?>. All rights reserved.
        </div>
    </div>
</footer>

<!-- Global JS -->
<script>
// Close modals when clicking outside
document.addEventListener('click', function(event) {
    const modal = document.getElementById('searchModal');
    if (event.target === modal) {
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
                    resultsDiv.innerHTML = data.html;
                });
        }
    });
}
</script>
</body>
</html>