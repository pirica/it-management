(function () {
    'use strict';

    var POLL_MS = 60000;
    var pollTimer = null;
    var eventSource = null;
    var sseFallbackStarted = false;

    function baseUrl() {
        return (window.ITM_BASE_URL || '/').replace(/\/?$/, '/');
    }

    function apiUrl(query) {
        return baseUrl() + 'modules/notifications/api.php' + (query ? '?' + query : '');
    }

    function escapeHtml(value) {
        return String(value == null ? '' : value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function formatWhen(iso) {
        if (!iso) {
            return '';
        }
        var d = new Date(iso.replace(' ', 'T'));
        if (isNaN(d.getTime())) {
            return iso;
        }
        return d.toLocaleString();
    }

    function setBadge(badgeEl, count) {
        if (!badgeEl) {
            return;
        }
        var n = parseInt(count, 10) || 0;
        if (n <= 0) {
            badgeEl.textContent = '';
            badgeEl.classList.add('hidden');
            badgeEl.setAttribute('aria-hidden', 'true');
            return;
        }
        badgeEl.textContent = n > 99 ? '99+' : String(n);
        badgeEl.classList.remove('hidden');
        badgeEl.setAttribute('aria-hidden', 'false');
    }

    function renderList(listEl, emptyEl, notifications) {
        if (!listEl) {
            return;
        }
        listEl.innerHTML = '';
        if (!notifications || !notifications.length) {
            if (emptyEl) {
                emptyEl.style.display = 'block';
            }
            return;
        }
        if (emptyEl) {
            emptyEl.style.display = 'none';
        }
        notifications.forEach(function (row) {
            var li = document.createElement('li');
            li.className = 'itm-notifications-item' + ((parseInt(row.is_read, 10) === 0) ? ' is-unread' : '');
            var btn = document.createElement('button');
            btn.type = 'button';
            btn.dataset.notificationId = String(row.id || '');
            btn.dataset.actionUrl = row.action_url || '';
            btn.innerHTML =
                '<div class="itm-notifications-item-title">' + escapeHtml(row.title || 'Notification') + '</div>' +
                (row.body ? '<div class="itm-notifications-item-body">' + escapeHtml(row.body) + '</div>' : '') +
                '<div class="itm-notifications-item-meta">' + escapeHtml(formatWhen(row.created_at)) + '</div>';
            li.appendChild(btn);
            listEl.appendChild(li);
        });
    }

    function fetchNotifications(root, options) {
        options = options || {};
        var badgeEl = root.querySelector('[data-itm-notifications-badge]');
        var listEl = root.querySelector('[data-itm-notifications-list]');
        var emptyEl = root.querySelector('[data-itm-notifications-empty]');
        var errorEl = root.querySelector('[data-itm-notifications-error]');
        var inboxLink = root.querySelector('[data-itm-notifications-inbox]');

        return fetch(apiUrl('unread=0&limit=20'), {
            credentials: 'same-origin',
            headers: { 'Accept': 'application/json' }
        })
            .then(function (res) { return res.json(); })
            .then(function (data) {
                if (!data || !data.ok) {
                    throw new Error((data && data.error) || 'Failed to load notifications');
                }
                setBadge(badgeEl, data.unread_count);
                if (options.renderList !== false) {
                    renderList(listEl, emptyEl, data.notifications);
                }
                if (inboxLink && data.inbox_url) {
                    inboxLink.href = data.inbox_url;
                }
                if (errorEl) {
                    errorEl.style.display = 'none';
                }
                return data;
            })
            .catch(function () {
                if (errorEl) {
                    errorEl.style.display = 'block';
                }
            });
    }

    function markRead(notificationId) {
        var body = new URLSearchParams();
        body.set('action', 'mark_read');
        body.set('notification_id', String(notificationId));
        body.set('csrf_token', window.ITM_CSRF_TOKEN || '');
        return fetch(apiUrl(), {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
            },
            body: body.toString()
        }).then(function (res) { return res.json(); });
    }

    function markAllRead(root) {
        var body = new URLSearchParams();
        body.set('action', 'mark_all_read');
        body.set('csrf_token', window.ITM_CSRF_TOKEN || '');
        return fetch(apiUrl(), {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
            },
            body: body.toString()
        }).then(function (res) { return res.json(); })
            .then(function () { return fetchNotifications(root); });
    }

    function closePanel(panel) {
        if (panel) {
            panel.classList.remove('is-open');
        }
    }

    function togglePanel(panel) {
        if (!panel) {
            return;
        }
        panel.classList.toggle('is-open');
    }

    function startPolling(root) {
        if (pollTimer) {
            clearInterval(pollTimer);
        }
        pollTimer = setInterval(function () {
            var panel = root.querySelector('[data-itm-notifications-panel]');
            fetchNotifications(root, { renderList: panel && panel.classList.contains('is-open') });
        }, POLL_MS);
    }

    function stopSse() {
        if (eventSource) {
            eventSource.close();
            eventSource = null;
        }
    }

    function startPollingFallback(root) {
        if (sseFallbackStarted) {
            return;
        }
        sseFallbackStarted = true;
        stopSse();
        startPolling(root);
    }

    function startSse(root) {
        if (typeof EventSource === 'undefined') {
            startPollingFallback(root);
            return;
        }
        stopSse();
        eventSource = new EventSource(apiUrl('stream=1'));
        eventSource.addEventListener('unread', function (event) {
            var data;
            try {
                data = JSON.parse(event.data || '{}');
            } catch (e) {
                return;
            }
            if (!data || !data.ok) {
                return;
            }
            var badgeEl = root.querySelector('[data-itm-notifications-badge]');
            setBadge(badgeEl, data.unread_count);
            var panel = root.querySelector('[data-itm-notifications-panel]');
            if (panel && panel.classList.contains('is-open')) {
                fetchNotifications(root);
            }
        });
        eventSource.onerror = function () {
            startPollingFallback(root);
        };
    }

    function bind(root) {
        if (!root || root.dataset.itmNotificationsBound === '1') {
            return;
        }
        root.dataset.itmNotificationsBound = '1';

        var toggleBtn = root.querySelector('[data-itm-notifications-toggle]');
        var panel = root.querySelector('[data-itm-notifications-panel]');
        var listEl = root.querySelector('[data-itm-notifications-list]');
        var markAllBtn = root.querySelector('[data-itm-notifications-mark-all]');

        fetchNotifications(root);
        startSse(root);

        if (toggleBtn) {
            toggleBtn.addEventListener('click', function (event) {
                event.stopPropagation();
                var willOpen = panel && !panel.classList.contains('is-open');
                togglePanel(panel);
                if (willOpen) {
                    fetchNotifications(root);
                }
            });
        }

        if (listEl) {
            listEl.addEventListener('click', function (event) {
                var btn = event.target.closest('button[data-notification-id]');
                if (!btn) {
                    return;
                }
                var notificationId = parseInt(btn.dataset.notificationId, 10);
                var actionUrl = btn.dataset.actionUrl || '';
                if (!notificationId) {
                    return;
                }
                markRead(notificationId).finally(function () {
                    if (actionUrl) {
                        window.location.href = actionUrl;
                        return;
                    }
                    fetchNotifications(root);
                });
            });
        }

        if (markAllBtn) {
            markAllBtn.addEventListener('click', function (event) {
                event.preventDefault();
                markAllRead(root);
            });
        }

        document.addEventListener('click', function (event) {
            if (!panel || !panel.classList.contains('is-open')) {
                return;
            }
            if (root.contains(event.target)) {
                return;
            }
            closePanel(panel);
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        var root = document.getElementById('itm-notifications-root');
        if (root) {
            bind(root);
        }
    });
})();
