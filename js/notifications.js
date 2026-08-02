(function () {
    'use strict';

    // Why: Long-lived SSE ties up Apache/PHP workers (~55s per tab) and slows every save; badge uses light polling instead.
    var POLL_MS_CLOSED = 120000;
    var POLL_MS_OPEN = 60000;
    var INITIAL_DEFER_MS = 2000;
    var pollTimer = null;

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

    function setMarkAllButtonExitMode(markAllBtn) {
        if (!markAllBtn) {
            return;
        }
        markAllBtn.dataset.itmNotificationsMode = 'exit';
        markAllBtn.textContent = 'Exit';
        markAllBtn.setAttribute('title', 'Exit');
        markAllBtn.setAttribute('aria-label', 'Exit notifications');
    }

    function resetMarkAllButton(markAllBtn) {
        if (!markAllBtn) {
            return;
        }
        markAllBtn.dataset.itmNotificationsMode = 'mark_all';
        markAllBtn.textContent = 'Mark all read';
        markAllBtn.setAttribute('title', 'Mark all read');
        markAllBtn.setAttribute('aria-label', 'Mark all read');
    }

    function isMarkAllExitMode(markAllBtn) {
        return !!(markAllBtn && markAllBtn.dataset.itmNotificationsMode === 'exit');
    }

    function syncMarkAllButtonFromUnread(root, unreadCount) {
        var markAllBtn = root.querySelector('[data-itm-notifications-mark-all]');
        if (!markAllBtn) {
            return;
        }
        if ((parseInt(unreadCount, 10) || 0) > 0) {
            resetMarkAllButton(markAllBtn);
        }
    }

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

    function fetchUnreadCount(root) {
        var badgeEl = root.querySelector('[data-itm-notifications-badge]');
        var inboxLink = root.querySelector('[data-itm-notifications-inbox]');
        var errorEl = root.querySelector('[data-itm-notifications-error]');

        return fetch(apiUrl('count_only=1'), {
            credentials: 'same-origin',
            cache: 'no-store',
            headers: { 'Accept': 'application/json' }
        })
            .then(function (res) { return res.json(); })
            .then(function (data) {
                if (!data || !data.ok) {
                    throw new Error((data && data.error) || 'Failed to load notification count');
                }
                setBadge(badgeEl, data.unread_count);
                syncMarkAllButtonFromUnread(root, data.unread_count);
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

    function fetchNotifications(root) {
        var badgeEl = root.querySelector('[data-itm-notifications-badge]');
        var listEl = root.querySelector('[data-itm-notifications-list]');
        var emptyEl = root.querySelector('[data-itm-notifications-empty]');
        var errorEl = root.querySelector('[data-itm-notifications-error]');
        var inboxLink = root.querySelector('[data-itm-notifications-inbox]');

        return fetch(apiUrl('unread=0&limit=20'), {
            credentials: 'same-origin',
            cache: 'no-store',
            headers: { 'Accept': 'application/json' }
        })
            .then(function (res) { return res.json(); })
            .then(function (data) {
                if (!data || !data.ok) {
                    throw new Error((data && data.error) || 'Failed to load notifications');
                }
                setBadge(badgeEl, data.unread_count);
                syncMarkAllButtonFromUnread(root, data.unread_count);
                renderList(listEl, emptyEl, data.notifications);
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
            cache: 'no-store',
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
            },
            body: body.toString()
        }).then(function (res) {
            return res.json().then(function (data) {
                if (!res.ok || !data || !data.ok) {
                    throw new Error((data && data.error) || 'Could not mark notification as read.');
                }
                return data;
            });
        });
    }

    function markAllRead(root) {
        var body = new URLSearchParams();
        body.set('action', 'mark_all_read');
        body.set('csrf_token', window.ITM_CSRF_TOKEN || '');
        return fetch(apiUrl(), {
            method: 'POST',
            credentials: 'same-origin',
            cache: 'no-store',
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
            },
            body: body.toString()
        }).then(function (res) {
            return res.json().then(function (data) {
                if (!res.ok || !data || !data.ok) {
                    throw new Error((data && data.error) || 'Could not mark all notifications as read.');
                }
                return data;
            });
        }).then(function (data) {
            var badgeEl = root.querySelector('[data-itm-notifications-badge]');
            var markAllBtn = root.querySelector('[data-itm-notifications-mark-all]');
            setBadge(badgeEl, data.unread_count);
            setMarkAllButtonExitMode(markAllBtn);
            return fetchNotifications(root);
        }).catch(function () {
            var errorEl = root.querySelector('[data-itm-notifications-error]');
            if (errorEl) {
                errorEl.textContent = 'Could not mark notifications as read.';
                errorEl.style.display = 'block';
            }
        });
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

    function isPanelOpen(root) {
        var panel = root.querySelector('[data-itm-notifications-panel]');
        return !!(panel && panel.classList.contains('is-open'));
    }

    function refreshNotifications(root) {
        if (document.hidden) {
            return;
        }
        if (isPanelOpen(root)) {
            fetchNotifications(root);
            return;
        }
        fetchUnreadCount(root);
    }

    function startPolling(root) {
        if (pollTimer) {
            clearInterval(pollTimer);
        }
        pollTimer = setInterval(function () {
            refreshNotifications(root);
        }, POLL_MS_CLOSED);
    }

    function scheduleDeferredBootstrap(root) {
        window.setTimeout(function () {
            fetchUnreadCount(root);
            startPolling(root);
        }, INITIAL_DEFER_MS);
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

        scheduleDeferredBootstrap(root);

        document.addEventListener('visibilitychange', function () {
            if (!document.hidden) {
                refreshNotifications(root);
            }
        });

        if (toggleBtn) {
            toggleBtn.addEventListener('click', function (event) {
                event.stopPropagation();
                var willOpen = panel && !panel.classList.contains('is-open');
                togglePanel(panel);
                if (willOpen) {
                    resetMarkAllButton(markAllBtn);
                    fetchNotifications(root);
                    if (pollTimer) {
                        clearInterval(pollTimer);
                    }
                    pollTimer = setInterval(function () {
                        refreshNotifications(root);
                    }, POLL_MS_OPEN);
                } else if (pollTimer) {
                    clearInterval(pollTimer);
                    startPolling(root);
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
                event.stopPropagation();
                if (isMarkAllExitMode(markAllBtn)) {
                    closePanel(panel);
                    if (pollTimer) {
                        clearInterval(pollTimer);
                    }
                    startPolling(root);
                    return;
                }
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
            if (pollTimer) {
                clearInterval(pollTimer);
            }
            startPolling(root);
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        var root = document.getElementById('itm-notifications-root');
        if (root) {
            bind(root);
        }
    });
})();
