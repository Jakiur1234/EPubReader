const sidebar = document.getElementById('sidebar');
const menuButton = document.getElementById('menuButton');
const chapterTitle = document.getElementById('chapterTitle');

menuButton.addEventListener('click', () => {
    sidebar.classList.toggle('-translate-x-full');
});

// Handle chapter navigation
document.querySelectorAll('.chapter-item a').forEach((item, index) => {
    item.addEventListener('click', function(e) {
        e.preventDefault(); // Prevent default anchor behavior

        // Remove active states
        document.querySelectorAll('.chapter-item li').forEach(ch => {
            ch.classList.remove('bg-blue-50', 'text-blue-600');
        });

        // Add active state to the clicked item
        const listItem = this.querySelector('li');
        listItem.classList.add('bg-blue-50', 'text-blue-600');

        // Update chapter title with the clicked item's text
        chapterTitle.textContent = listItem.textContent.trim();

        // Close sidebar on mobile
        if (window.innerWidth < 768) {
            sidebar.classList.add('-translate-x-full');
        }
    });

    // Show the first title by default on page load
    if (index === 0) {
        const listItem = item.querySelector('li');
        listItem.classList.add('bg-blue-50', 'text-blue-600');
        chapterTitle.textContent = listItem.textContent.trim();
    }
});

// Close sidebar when clicking outside on mobile
document.addEventListener('click', (e) => {
    if (window.innerWidth < 768 && 
        !sidebar.contains(e.target) && 
        !menuButton.contains(e.target)) {
        sidebar.classList.add('-translate-x-full');
    }
});

// Handle window resize
window.addEventListener('resize', () => {
    if (window.innerWidth >= 768) {
        sidebar.classList.remove('-translate-x-full');
    } else {
        sidebar.classList.add('-translate-x-full');
    }
});