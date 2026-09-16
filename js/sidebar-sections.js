/**
 * Double-click sidebar section headers to collapse/expand child links (server-persisted).
 */
(function () {
    'use strict';

    function sidebarSectionsEnabled() {
        var sidebar = document.getElementById('appSidebar');
        if (!sidebar) {
            return false;
        }
        return sidebar.getAttribute('data-itm-sidebar-section-collapse-enabled') === '1';
    }

    function getSectionWrapper(node) {
        if (!node || !node.closest) {
            return null;
        }
        return node.closest('.sidebar-section[data-sidebar-section-id]');
    }

    function setSectionCollapsed(sectionEl, collapsed) {
        if (!sectionEl) {
            return;
        }
        sectionEl.classList.toggle('sidebar-section-collapsed', !!collapsed);
        var toggle = sectionEl.querySelector('.sidebar-section-toggle');
        if (toggle) {
            toggle.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
        }
    }

    function postToggle(sectionId) {
        var baseUrl = (window.ITM_BASE_URL || '/');
        var body = new URLSearchParams();
        body.set('action', 'toggle_section_collapse');
        body.set('section_id', sectionId);
        if (window.ITM_CSRF_TOKEN) {
            body.set('csrf_token', window.ITM_CSRF_TOKEN);
        }

        return fetch(baseUrl + 'sidebar_preferences_api.php', {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: body.toString()
        }).then(function (response) {
            return response.json().then(function (payload) {
                if (!response.ok || !payload || !payload.ok) {
                    var err = new Error((payload && payload.error) ? payload.error : 'toggle_failed');
                    err.status = response.status;
                    throw err;
                }
                return payload;
            });
        });
    }

    function handleToggle(sectionEl) {
        if (!sectionEl) {
            return;
        }
        var sectionId = sectionEl.getAttribute('data-sidebar-section-id') || '';
        if (!sectionId) {
            return;
        }

        var willCollapse = !sectionEl.classList.contains('sidebar-section-collapsed');
        setSectionCollapsed(sectionEl, willCollapse);

        postToggle(sectionId).catch(function () {
            setSectionCollapsed(sectionEl, !willCollapse);
        });
    }

    function onDoubleClick(event) {
        if (!sidebarSectionsEnabled()) {
            return;
        }
        var toggle = event.target.closest('.sidebar-section-toggle');
        if (!toggle) {
            return;
        }
        event.preventDefault();
        handleToggle(getSectionWrapper(toggle));
    }

    function onKeyDown(event) {
        if (!sidebarSectionsEnabled()) {
            return;
        }
        if (event.key !== 'Enter' && event.key !== ' ') {
            return;
        }
        var toggle = event.target.closest('.sidebar-section-toggle');
        if (!toggle) {
            return;
        }
        event.preventDefault();
        handleToggle(getSectionWrapper(toggle));
    }

    document.addEventListener('dblclick', onDoubleClick);
    document.addEventListener('keydown', onKeyDown);
})();
