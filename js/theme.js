// Theme Management
function initTheme() {
    const savedTheme = localStorage.getItem('theme') || 'light';
    document.documentElement.setAttribute('data-theme', savedTheme);
    updateThemeButton();
}

function toggleTheme() {
    const currentTheme = document.documentElement.getAttribute('data-theme');
    const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
    document.documentElement.setAttribute('data-theme', newTheme);
    localStorage.setItem('theme', newTheme);
    updateThemeButton();
}

function updateThemeButton() {
    const buttons = document.querySelectorAll('[onclick="toggleTheme()"]');
    const theme = document.documentElement.getAttribute('data-theme');
    buttons.forEach(btn => {
        btn.textContent = theme === 'dark' ? '☀️' : '🌙';
    });
}

function initSidebar() {
    const savedSidebar = localStorage.getItem('sidebar');
    // Default phones/tablets to collapsed so content remains usable on small screens.
    const fallbackState = window.matchMedia('(max-width: 768px)').matches ? 'collapsed' : 'open';
    const sidebarState = savedSidebar || fallbackState;
    applySidebarState(sidebarState === 'collapsed');
}

function toggleSidebar() {
    const isCollapsed = !document.body.classList.contains('sidebar-collapsed');
    applySidebarState(isCollapsed);
    localStorage.setItem('sidebar', isCollapsed ? 'collapsed' : 'open');
}

function applySidebarState(isCollapsed) {
    document.body.classList.toggle('sidebar-collapsed', isCollapsed);
    const container = document.querySelector('.container');
    if (container) {
        container.classList.toggle('sidebar-collapsed', isCollapsed);
    }
}

document.addEventListener('DOMContentLoaded', () => {
    initTheme();
    initSidebar();
    const sidebarToggleBtn = document.getElementById('sidebarToggleBtn');
    if (sidebarToggleBtn && sidebarToggleBtn.dataset.sidebarBound !== 'true') {
        // Use a click handler once and mark it as bound to avoid duplicate toggles.
        sidebarToggleBtn.addEventListener('click', toggleSidebar);
        sidebarToggleBtn.dataset.sidebarBound = 'true';
    }
});

window.toggleSidebar = toggleSidebar;
