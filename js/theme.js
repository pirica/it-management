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
    const savedSidebar = localStorage.getItem('sidebar') || 'open';
    applySidebarState(savedSidebar === 'collapsed');
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
        sidebarToggleBtn.addEventListener('click', toggleSidebar);
        sidebarToggleBtn.dataset.sidebarBound = 'true';
    }
});

window.toggleSidebar = toggleSidebar;
