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
    buttons.forEach((btn) => {
        btn.textContent = theme === 'dark' ? '\u2600\ufe0f' : '\ud83c\udf19';
    });
}

function isMobileViewport() {
    return window.matchMedia('(max-width: 768px)').matches;
}

function getSidebarContainer() {
    return document.querySelector('.container');
}

function getSavedSidebarState() {
    return localStorage.getItem('sidebar') === 'collapsed';
}

function updateSidebarAccessibility(isCollapsed) {
    const sidebarToggleBtn = document.getElementById('sidebarToggleBtn');
    if (sidebarToggleBtn) {
        sidebarToggleBtn.setAttribute('aria-expanded', isCollapsed ? 'false' : 'true');
        sidebarToggleBtn.setAttribute('title', isCollapsed ? 'Show sidebar' : 'Hide sidebar');
    }

    const sidebarCollapseBtn = document.getElementById('sidebarCollapseBtn');
    if (sidebarCollapseBtn) {
        sidebarCollapseBtn.setAttribute('aria-expanded', isCollapsed ? 'false' : 'true');
        sidebarCollapseBtn.setAttribute('title', isCollapsed ? 'Show sidebar' : 'Hide sidebar');
        sidebarCollapseBtn.setAttribute('aria-label', isCollapsed ? 'Show sidebar' : 'Hide sidebar');
    }
}

function ensureSidebarBackdrop() {
    let backdrop = document.querySelector('.sidebar-backdrop');
    if (backdrop) {
        return backdrop;
    }

    backdrop = document.createElement('button');
    backdrop.type = 'button';
    backdrop.className = 'sidebar-backdrop';
    backdrop.setAttribute('aria-label', 'Close navigation menu');
    backdrop.addEventListener('click', () => {
        applySidebarState(true, { persist: false });
    });
    document.body.appendChild(backdrop);
    return backdrop;
}

function applySidebarState(isCollapsed, options) {
    const config = options || {};
    const mobileView = isMobileViewport();

    document.body.classList.toggle('sidebar-collapsed', isCollapsed);
    document.body.classList.toggle('sidebar-mobile-open', mobileView && !isCollapsed);

    const container = getSidebarContainer();
    if (container) {
        container.classList.toggle('sidebar-collapsed', isCollapsed);
    }

    if (config.persist === true && !mobileView) {
        localStorage.setItem('sidebar', isCollapsed ? 'collapsed' : 'open');
    }

    updateSidebarAccessibility(isCollapsed);
    ensureSidebarBackdrop();
}

function closeSidebarForMobile() {
    if (isMobileViewport()) {
        applySidebarState(true, { persist: false });
    }
}

function handleSidebarViewportResize() {
    const mobileView = isMobileViewport();
    if (mobileView) {
        applySidebarState(true, { persist: false });
        return;
    }

    applySidebarState(getSavedSidebarState(), { persist: false });
}

function initSidebar() {
    const mobileView = isMobileViewport();
    const initialCollapsed = mobileView ? true : getSavedSidebarState();
    applySidebarState(initialCollapsed, { persist: false });
    ensureSidebarBackdrop();

    if (window.ITM_SIDEBAR_EVENTS_BOUND === true) {
        return;
    }
    window.ITM_SIDEBAR_EVENTS_BOUND = true;

    const sidebarToggleBtn = document.getElementById('sidebarToggleBtn');
    if (sidebarToggleBtn && sidebarToggleBtn.dataset.sidebarBound !== 'true') {
        sidebarToggleBtn.addEventListener('click', toggleSidebar);
        sidebarToggleBtn.dataset.sidebarBound = 'true';
    }

    const sidebarCollapseBtn = document.getElementById('sidebarCollapseBtn');
    if (sidebarCollapseBtn && sidebarCollapseBtn.dataset.sidebarBound !== 'true') {
        sidebarCollapseBtn.addEventListener('click', () => {
            if (!document.body.classList.contains('sidebar-collapsed')) {
                toggleSidebar();
            }
        });
        sidebarCollapseBtn.dataset.sidebarBound = 'true';
    }

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            closeSidebarForMobile();
        }
    });

    document.addEventListener('click', (event) => {
        const sidebarLink = event.target.closest('.sidebar a[href]');
        if (sidebarLink) {
            closeSidebarForMobile();
        }
    });

    let resizeTimer = null;
    window.addEventListener('resize', () => {
        if (resizeTimer) {
            window.clearTimeout(resizeTimer);
        }
        resizeTimer = window.setTimeout(handleSidebarViewportResize, 120);
    });
}

function toggleSidebar() {
    const isCollapsed = !document.body.classList.contains('sidebar-collapsed');
    applySidebarState(isCollapsed, { persist: true });
}

document.addEventListener('DOMContentLoaded', () => {
    initTheme();
    initSidebar();
});

window.toggleSidebar = toggleSidebar;
