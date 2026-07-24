(function () {
    'use strict';

    var app = document.getElementById('lc-app');
    if (!app) return;

    var apiUrl = app.getAttribute('data-api');
    var csrf = app.getAttribute('data-csrf') || window.ITM_CSRF_TOKEN;
    var state = {
        conversationId: parseInt(app.getAttribute('data-conversation-id') || '0', 10) || 0,
        sinceId: 0,
        pollTimer: null,
        flow: null
    };

    var el = {
        list: document.getElementById('lc-conversation-list'),
        landing: document.getElementById('lc-landing'),
        options: document.getElementById('lc-options-panel'),
        wizard: document.getElementById('lc-wizard-panel'),
        chatPanel: document.getElementById('lc-chat-panel'),
        messages: document.getElementById('lc-messages'),
        typing: document.getElementById('lc-typing'),
        input: document.getElementById('lc-message-input'),
        title: document.getElementById('lc-chat-title'),
        status: document.getElementById('lc-chat-status'),
        claim: document.getElementById('lc-btn-claim'),
        closeChat: document.getElementById('lc-btn-close-chat'),
        detail: document.getElementById('lc-employee-detail'),
        ratingRow: document.getElementById('lc-rating-row'),
        ratingStars: document.getElementById('lc-rating-stars'),
        ratingBadge: document.getElementById('lc-rating-badge'),
        notifBadge: document.getElementById('lc-notification-badge'),
        fileInput: document.getElementById('lc-file-input')
    };

    function apiGet(action, params) {
        var q = new URLSearchParams(params || {});
        q.set('action', action);
        return fetch(apiUrl + '?' + q.toString(), { credentials: 'same-origin' }).then(function (r) { return r.json(); });
    }

    function apiPost(action, data) {
        var body = Object.assign({ action: action, csrf_token: csrf }, data || {});
        return fetch(apiUrl, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrf },
            body: JSON.stringify(body)
        }).then(function (r) { return r.json(); });
    }

    function apiUpload(formData) {
        formData.append('csrf_token', csrf);
        formData.append('action', 'upload_attachment');
        return fetch(apiUrl, { method: 'POST', credentials: 'same-origin', body: formData }).then(function (r) { return r.json(); });
    }

    function escapeHtml(s) {
        var d = document.createElement('div');
        d.textContent = s;
        return d.innerHTML;
    }

    function formatTime(ts) {
        if (!ts) return '';
        var d = new Date(ts.replace(' ', 'T'));
        return d.toLocaleString();
    }

    function showLanding() {
        el.landing.classList.remove('hidden');
        el.chatPanel.classList.add('hidden');
        el.options.classList.add('hidden');
        el.wizard.classList.add('hidden');
        state.conversationId = 0;
        stopPoll();
    }

    function showChat() {
        el.landing.classList.add('hidden');
        el.chatPanel.classList.remove('hidden');
        startPoll();
    }

    function renderConversations(conversations) {
        el.list.innerHTML = '';
        (conversations || []).forEach(function (c) {
            var li = document.createElement('li');
            li.className = 'lc-conv-item' + (c.id === state.conversationId ? ' active' : '') + (c.status === 'waiting' ? ' waiting' : '');
            li.dataset.id = c.id;
            li.innerHTML = '<div class="lc-conv-name">' + escapeHtml(c.peer_label || ('#' + c.id)) + '</div>' +
                '<div class="lc-conv-meta"><span>' + escapeHtml(c.status) + '</span>' +
                (c.unread_count > 0 ? '<span class="lc-unread-dot" title="Unread"></span>' : '<span>' + formatTime(c.updated_at) + '</span>') +
                '</div>';
            li.addEventListener('click', function () { openConversation(parseInt(c.id, 10)); });
            el.list.appendChild(li);
        });
    }

    function loadConversations() {
        return apiGet('list_conversations').then(function (data) {
            renderConversations(data.conversations);
            updateNotifBadge(data.notification_unread);
        });
    }

    function updateNotifBadge(count) {
        if (!el.notifBadge) return;
        if (count > 0) {
            el.notifBadge.textContent = count;
            el.notifBadge.classList.remove('hidden');
        } else {
            el.notifBadge.classList.add('hidden');
        }
    }

    function renderMessages(messages, append) {
        if (!append) el.messages.innerHTML = '';
        (messages || []).forEach(function (m) {
            if (m.id > state.sinceId) state.sinceId = m.id;
            var div = document.createElement('div');
            div.className = 'lc-msg ' + (m.is_mine ? 'mine' : 'theirs');
            var html = escapeHtml(m.body || '');
            if (m.attachments && m.attachments.length) {
                html += '<div class="lc-msg-attachments">';
                m.attachments.forEach(function (a) {
                    html += '<a href="' + escapeHtml(a.url) + '" target="_blank" rel="noopener noreferrer">' + escapeHtml(a.original_name || a.filename) + '</a>';
                });
                html += '</div>';
            }
            html += '<div class="lc-msg-time">' + escapeHtml(formatTime(m.created_at)) + '</div>';
            div.innerHTML = html;
            el.messages.appendChild(div);
        });
        el.messages.scrollTop = el.messages.scrollHeight;
    }

    function renderRatingStars(current) {
        el.ratingStars.innerHTML = '';
        for (var i = 1; i <= 5; i++) {
            var btn = document.createElement('button');
            btn.type = 'button';
            btn.textContent = '⭐';
            btn.title = 'Rate ' + i + ' star' + (i > 1 ? 's' : '');
            if (current && i <= current) btn.classList.add('active');
            (function (rating) {
                btn.addEventListener('click', function () {
                    apiPost('rate_conversation', { conversation_id: state.conversationId, rating: rating }).then(function () {
                        setRatingBadge(rating);
                    });
                });
            })(i);
            el.ratingStars.appendChild(btn);
        }
    }

    function setRatingBadge(rating) {
        if (!rating) {
            el.ratingBadge.classList.add('hidden');
            return;
        }
        var stars = '';
        for (var i = 1; i <= 5; i++) stars += i <= rating ? '⭐' : '☆';
        el.ratingBadge.textContent = stars;
        el.ratingBadge.title = 'Rated ' + rating + ' of 5 stars';
        el.ratingBadge.classList.remove('hidden');
    }

    function renderDetail(data) {
        var conv = data.conversation;
        var emp = data.employee;
        el.title.textContent = conv.conversation_type === 'live_agent' ? ('Ticket #' + (conv.ticket_id || '')) : 'Chat';
        el.status.textContent = conv.status;
        el.status.className = 'badge ' + (conv.status === 'waiting' ? 'badge-warning' : (conv.status === 'closed' ? 'badge-danger' : 'badge-success'));
        el.claim.classList.toggle('hidden', !conv.can_claim);
        el.ratingRow.classList.toggle('hidden', conv.status === 'closed' && conv.rating);
        renderRatingStars(conv.rating);
        setRatingBadge(conv.rating);

        if (!emp) {
            el.detail.innerHTML = '<p class="lc-empty-state">No details</p>';
            return;
        }
        var email = emp.work_email || emp.personal_email || '';
        el.detail.innerHTML = '<dl>' +
            '<dt>Name</dt><dd>' + escapeHtml((emp.first_name || '') + ' ' + (emp.last_name || '')) + '</dd>' +
            '<dt>Email</dt><dd>' + escapeHtml(email) + '</dd>' +
            '<dt>Department</dt><dd>' + escapeHtml(emp.department_name || '—') + '</dd>' +
            '<dt>Location</dt><dd>' + escapeHtml(emp.location_name || '—') + '</dd>' +
            '<dt>Session</dt><dd>' + escapeHtml(conv.conversation_type) + ' / ' + escapeHtml(conv.status) + '</dd>' +
            (conv.ticket_id ? '<dt>Ticket</dt><dd><a href="' + window.ITM_BASE_URL + 'modules/tickets/view.php?id=' + conv.ticket_id + '">#' + conv.ticket_id + '</a></dd>' : '') +
            '</dl>';
    }

    function openConversation(id) {
        state.conversationId = id;
        state.sinceId = 0;
        showChat();
        loadConversations();
        apiGet('get_conversation', { conversation_id: id }).then(renderDetail);
        apiGet('get_messages', { conversation_id: id }).then(function (data) {
            renderMessages(data.messages, false);
        });
    }

    function poll() {
        if (!state.conversationId || document.hidden) return;
        apiGet('poll', { conversation_id: state.conversationId, since_id: state.sinceId }).then(function (data) {
            if (data.messages && data.messages.length) renderMessages(data.messages, true);
            if (data.typing_employee_ids && data.typing_employee_ids.length) {
                el.typing.classList.remove('hidden');
            } else {
                el.typing.classList.add('hidden');
            }
            updateNotifBadge(data.notification_unread);
        });
        loadConversations();
    }

    function startPoll() {
        stopPoll();
        state.pollTimer = setInterval(poll, 4000);
    }

    function stopPoll() {
        if (state.pollTimer) clearInterval(state.pollTimer);
        state.pollTimer = null;
    }

    function handleLaunchOption(opt) {
        if (opt.open_mode === 'browser_tab' || opt.open_mode === 'browser_window') {
            if (opt.url) window.open(opt.url, '_blank', 'noopener,noreferrer');
            return;
        }
        if (opt.id === 'start_chat') showLiveAgentWizard();
        if (opt.id === 'message_colleague') showChatWithWizard();
    }

    function renderOptions(options) {
        el.options.classList.remove('hidden');
        el.wizard.classList.add('hidden');
        var grid = document.createElement('div');
        grid.className = 'lc-options-grid';
        options.forEach(function (opt) {
            var card = document.createElement('div');
            card.className = 'lc-option-card';
            card.innerHTML = '<div class="lc-option-icon">' + escapeHtml(opt.icon || '•') + '</div>' +
                '<div class="lc-option-label">' + escapeHtml(opt.label) + '</div>' +
                '<div class="lc-option-desc">' + escapeHtml(opt.description || '') + '</div>';
            card.addEventListener('click', function () { handleLaunchOption(opt); });
            grid.appendChild(card);
        });
        el.options.innerHTML = '';
        el.options.appendChild(grid);
    }

    function showLiveAgentWizard() {
        el.options.classList.add('hidden');
        el.wizard.classList.remove('hidden');
        el.wizard.innerHTML = '<div class="card"><p><button type="button" class="btn btn-sm" id="lc-wiz-existing" title="Existing ticket">🎫</button> ' +
            '<button type="button" class="btn btn-sm" id="lc-wiz-new" title="New ticket">➕</button></div>';
        document.getElementById('lc-wiz-existing').addEventListener('click', function () {
            apiGet('list_open_tickets').then(function (data) {
                var html = '<div class="card"><label>Ticket</label><select id="lc-ticket-select" class="form-control">';
                (data.tickets || []).forEach(function (t) {
                    html += '<option value="' + t.id + '">#' + t.id + ' — ' + escapeHtml(t.title) + '</option>';
                });
                html += '</select><button type="button" class="btn btn-primary" id="lc-start-existing" title="Start">▶</button></div>';
                el.wizard.innerHTML = html;
                document.getElementById('lc-start-existing').addEventListener('click', function () {
                    var tid = parseInt(document.getElementById('lc-ticket-select').value, 10);
                    apiPost('start_live_agent', { ticket_mode: 'existing', ticket_id: tid }).then(function (r) {
                        if (r.conversation_id) openConversation(r.conversation_id);
                    });
                });
            });
        });
        document.getElementById('lc-wiz-new').addEventListener('click', function () {
            el.wizard.innerHTML = '<div class="card"><label>Title</label><input type="text" id="lc-ticket-title" class="form-control">' +
                '<label>Description</label><textarea id="lc-ticket-desc" class="form-control"></textarea>' +
                '<button type="button" class="btn btn-primary" id="lc-start-new" title="Start">▶</button></div>';
            document.getElementById('lc-start-new').addEventListener('click', function () {
                apiPost('start_live_agent', {
                    ticket_mode: 'new',
                    title: document.getElementById('lc-ticket-title').value,
                    description: document.getElementById('lc-ticket-desc').value
                }).then(function (r) {
                    if (r.conversation_id) openConversation(r.conversation_id);
                });
            });
        });
    }

    function showChatWithWizard() {
        el.options.classList.add('hidden');
        el.wizard.classList.remove('hidden');
        apiGet('list_employees').then(function (data) {
            var html = '<div class="card"><label>Employee</label><select id="lc-peer-select" class="form-control">';
            (data.employees || []).forEach(function (e) {
                html += '<option value="' + e.id + '">' + escapeHtml(e.label) + '</option>';
            });
            html += '</select><button type="button" class="btn btn-primary" id="lc-start-peer" title="Start">▶</button></div>';
            el.wizard.innerHTML = html;
            document.getElementById('lc-start-peer').addEventListener('click', function () {
                var peerId = parseInt(document.getElementById('lc-peer-select').value, 10);
                apiPost('start_chat_with', { peer_employee_id: peerId }).then(function (r) {
                    if (r.conversation_id) openConversation(r.conversation_id);
                });
            });
        });
    }

    document.getElementById('lc-btn-live-agent').addEventListener('click', function () {
        apiGet('launch_options_live_agent').then(function (data) { renderOptions(data.options || []); });
    });

    document.getElementById('lc-btn-chat-with').addEventListener('click', function () {
        apiGet('launch_options_chat_with').then(function (data) { renderOptions(data.options || []); });
    });

    document.getElementById('lc-btn-send').addEventListener('click', function () {
        var body = el.input.value.trim();
        if (!body || !state.conversationId) return;
        apiPost('send_message', { conversation_id: state.conversationId, body: body }).then(function () {
            el.input.value = '';
            apiGet('get_messages', { conversation_id: state.conversationId, since_id: state.sinceId }).then(function (data) {
                renderMessages(data.messages, true);
            });
        });
    });

    el.input.addEventListener('keydown', function (e) {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            document.getElementById('lc-btn-send').click();
        }
        if (state.conversationId) apiPost('set_typing', { conversation_id: state.conversationId });
    });

    el.claim.addEventListener('click', function () {
        apiPost('claim_conversation', { conversation_id: state.conversationId }).then(function () {
            openConversation(state.conversationId);
        });
    });

    el.closeChat.addEventListener('click', function () {
        if (!confirm('Close this conversation?')) return;
        apiPost('close_conversation', { conversation_id: state.conversationId }).then(function () {
            showLanding();
            loadConversations();
        });
    });

    el.fileInput.addEventListener('change', function () {
        if (!state.conversationId || !el.fileInput.files.length) return;
        var fd = new FormData();
        fd.append('conversation_id', state.conversationId);
        fd.append('file', el.fileInput.files[0]);
        apiUpload(fd).then(function () {
            el.fileInput.value = '';
            apiGet('get_messages', { conversation_id: state.conversationId }).then(function (data) {
                state.sinceId = 0;
                renderMessages(data.messages, false);
            });
        });
    });

    el.messages.addEventListener('dragover', function (e) { e.preventDefault(); });
    el.messages.addEventListener('drop', function (e) {
        e.preventDefault();
        if (!state.conversationId || !e.dataTransfer.files.length) return;
        var fd = new FormData();
        fd.append('conversation_id', state.conversationId);
        fd.append('file', e.dataTransfer.files[0]);
        apiUpload(fd).then(function () {
            apiGet('get_messages', { conversation_id: state.conversationId }).then(function (data) {
                state.sinceId = 0;
                renderMessages(data.messages, false);
            });
        });
    });

    document.addEventListener('paste', function (e) {
        if (!state.conversationId || !e.clipboardData || !e.clipboardData.files.length) return;
        var fd = new FormData();
        fd.append('conversation_id', state.conversationId);
        fd.append('file', e.clipboardData.files[0]);
        apiUpload(fd).then(function () {
            apiGet('get_messages', { conversation_id: state.conversationId }).then(function (data) {
                state.sinceId = 0;
                renderMessages(data.messages, false);
            });
        });
    });

    document.addEventListener('visibilitychange', function () {
        if (document.hidden) stopPoll();
        else if (state.conversationId) startPoll();
    });

    loadConversations();
    if (state.conversationId > 0) openConversation(state.conversationId);
})();
