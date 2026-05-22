(function () {
    // Sidebar toggle (mobile)
    const toggle = document.getElementById('sidebar-toggle');
    const sidebar = document.querySelector('.admin-sidebar');
    if (toggle && sidebar) {
        toggle.addEventListener('click', () => sidebar.classList.toggle('open'));
        document.addEventListener('click', (e) => {
            if (window.innerWidth >= 768) return;
            if (!sidebar.contains(e.target) && e.target !== toggle && !toggle.contains(e.target)) {
                sidebar.classList.remove('open');
            }
        });
    }
})();
