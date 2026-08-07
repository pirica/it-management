(function () {
    'use strict';

    function getUsers() {
        var users = window.ITM_TICKET_MENTION_USERS;
        return Array.isArray(users) ? users : [];
    }

    function insertAtCursor(textarea, text) {
        var start = textarea.selectionStart;
        var end = textarea.selectionEnd;
        var value = textarea.value;
        textarea.value = value.slice(0, start) + text + value.slice(end);
        var pos = start + text.length;
        textarea.selectionStart = pos;
        textarea.selectionEnd = pos;
        textarea.focus();
    }

    function buildPicker(users, onSelect) {
        var panel = document.createElement('div');
        panel.className = 'itm-ticket-mention-picker';
        panel.setAttribute('role', 'listbox');
        panel.setAttribute('aria-label', 'Mention user');

        users.forEach(function (user) {
            var username = String(user.username || '').trim();
            if (!username) {
                return;
            }
            var label = username;
            var fullName = [user.first_name, user.last_name].filter(Boolean).join(' ').trim();
            if (fullName) {
                label = fullName + ' (@' + username + ')';
            }
            var btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'itm-ticket-mention-option';
            btn.textContent = label;
            btn.addEventListener('click', function () {
                onSelect(username);
                panel.remove();
            });
            panel.appendChild(btn);
        });

        if (!panel.childElementCount) {
            var empty = document.createElement('div');
            empty.className = 'itm-ticket-mention-empty';
            empty.textContent = 'No users available';
            panel.appendChild(empty);
        }

        return panel;
    }

    function bindTextarea(textarea) {
        if (!textarea || textarea.dataset.itmMentionBound === '1') {
            return;
        }
        textarea.dataset.itmMentionBound = '1';

        textarea.addEventListener('keydown', function (event) {
            if (event.key !== 'F2') {
                return;
            }
            event.preventDefault();
            var existing = document.querySelector('.itm-ticket-mention-picker');
            if (existing) {
                existing.remove();
            }
            var users = getUsers();
            var picker = buildPicker(users, function (username) {
                insertAtCursor(textarea, '@' + username + ' ');
            });
            var rect = textarea.getBoundingClientRect();
            picker.style.position = 'fixed';
            picker.style.left = rect.left + 'px';
            picker.style.top = (rect.bottom + 4) + 'px';
            picker.style.minWidth = Math.max(220, rect.width) + 'px';
            picker.style.zIndex = '10050';
            document.body.appendChild(picker);

            var closeOnOutside = function (e) {
                if (!picker.contains(e.target) && e.target !== textarea) {
                    picker.remove();
                    document.removeEventListener('mousedown', closeOnOutside);
                }
            };
            document.addEventListener('mousedown', closeOnOutside);
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('textarea[name="body"]').forEach(bindTextarea);
    });
})();
