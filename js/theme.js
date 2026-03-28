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
    document.body.classList.toggle('sidebar-collapsed', savedSidebar === 'collapsed');
}

function toggleSidebar() {
    document.body.classList.toggle('sidebar-collapsed');
    const isCollapsed = document.body.classList.contains('sidebar-collapsed');
    localStorage.setItem('sidebar', isCollapsed ? 'collapsed' : 'open');
}

document.addEventListener('DOMContentLoaded', () => {
    initTheme();
    initSidebar();
});
