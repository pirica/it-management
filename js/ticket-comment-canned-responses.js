(function () {
    'use strict';
    function getResponses() { var list = window.ITM_TICKET_CANNED_RESPONSES; return Array.isArray(list) ? list : []; }
    function insertAtCursor(textarea, text) {
        var start = textarea.selectionStart, end = textarea.selectionEnd, value = textarea.value;
        var prefix = value.slice(0, start), suffix = value.slice(end), insert = text;
        if (prefix !== '' && !/\s$/.test(prefix)) insert = '\n' + insert;
        if (suffix !== '' && !/^\s/.test(suffix)) insert = insert + '\n';
        textarea.value = prefix + insert + suffix;
        var pos = prefix.length + insert.length;
        textarea.selectionStart = pos; textarea.selectionEnd = pos; textarea.focus();
    }
    function findBodyTextarea() { return document.querySelector('textarea[name="body"]'); }
    function buildPicker(responses, onSelect) {
        var panel = document.createElement('div');
        panel.className = 'itm-ticket-canned-picker';
        responses.forEach(function (row) {
            var title = String(row.title || '').trim(), body = String(row.body || '').trim();
            if (!title || !body) return;
            var btn = document.createElement('button');
            btn.type = 'button'; btn.className = 'itm-ticket-canned-option'; btn.textContent = title;
            btn.addEventListener('click', function () { onSelect(body); panel.remove(); });
            panel.appendChild(btn);
        });
        return panel;
    }
    function showPicker(textarea) {
        document.querySelectorAll('.itm-ticket-canned-picker').forEach(function (el) { el.remove(); });
        var picker = buildPicker(getResponses(), function (body) { insertAtCursor(textarea, body); });
        var rect = textarea.getBoundingClientRect();
        picker.style.position = 'fixed'; picker.style.left = rect.left + 'px'; picker.style.top = (rect.bottom + 4) + 'px';
        picker.style.minWidth = Math.max(260, rect.width) + 'px';
        document.body.appendChild(picker);
    }
    document.addEventListener('DOMContentLoaded', function () {
        var textarea = findBodyTextarea();
        if (textarea) {
            textarea.addEventListener('keydown', function (event) {
                if (event.key === 'F2' && event.shiftKey) { event.preventDefault(); showPicker(textarea); }
            });
        }
        var select = document.getElementById('itm-canned-response-select');
        if (select) {
            select.addEventListener('change', function () {
                var id = String(select.value || ''); if (!id) return;
                var match = getResponses().find(function (row) { return String(row.id) === id; });
                if (match && match.body && textarea) insertAtCursor(textarea, String(match.body));
                select.value = '';
            });
        }
    });
})();
