(function () {
    'use strict';

    document.addEventListener('dblclick', function (ev) {
        var bar = ev.target.closest('.hb-plan-bar[data-booking-id]');
        if (bar) {
            var id = bar.getAttribute('data-booking-id');
            if (id) {
                window.location.href = 'view.php?id=' + encodeURIComponent(id);
            }
            return;
        }
        var hkCell = ev.target.closest('.hb-plan-hk-cell');
        if (!hkCell) {
            return;
        }
        var row = hkCell.closest('tr[data-room-id]');
        if (!row) {
            return;
        }
        var roomId = row.getAttribute('data-room-id');
        if (!roomId) {
            return;
        }
        var badge = hkCell.querySelector('.hb-hk-badge');
        fetch('index.php?ajax_action=hk_rotate&room_id=' + encodeURIComponent(roomId), {
            credentials: 'same-origin',
            headers: { 'Accept': 'application/json' }
        })
            .then(function (res) { return res.json(); })
            .then(function (data) {
                if (!data || !data.ok || !badge) {
                    return;
                }
                badge.textContent = data.hk_name || '—';
                if (data.hk_color) {
                    badge.style.background = data.hk_color;
                }
            })
            .catch(function () { /* ignore */ });
    });
})();
