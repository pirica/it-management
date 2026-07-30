(function () {
    'use strict';

    var dragState = null;
    var dragDidMove = false;

    function parseYmd(ymd) {
        var parts = String(ymd || '').split('-');
        if (parts.length !== 3) {
            return null;
        }
        var y = parseInt(parts[0], 10);
        var m = parseInt(parts[1], 10) - 1;
        var d = parseInt(parts[2], 10);
        var dt = new Date(y, m, d);
        if (dt.getFullYear() !== y || dt.getMonth() !== m || dt.getDate() !== d) {
            return null;
        }
        return dt;
    }

    function formatYmd(dt) {
        var y = dt.getFullYear();
        var m = ('0' + (dt.getMonth() + 1)).slice(-2);
        var day = ('0' + dt.getDate()).slice(-2);
        return y + '-' + m + '-' + day;
    }

    function addDaysYmd(ymd, delta) {
        var dt = parseYmd(ymd);
        if (!dt) {
            return '';
        }
        dt.setDate(dt.getDate() + delta);
        return formatYmd(dt);
    }

    function dayDiffYmd(fromYmd, toYmd) {
        var a = parseYmd(fromYmd);
        var b = parseYmd(toYmd);
        if (!a || !b) {
            return 0;
        }
        return Math.round((b.getTime() - a.getTime()) / 86400000);
    }

    function getCsrfToken() {
        if (window.HB_PLANNING_DND && window.HB_PLANNING_DND.csrf) {
            return window.HB_PLANNING_DND.csrf;
        }
        var input = document.querySelector('input[name="csrf_token"]');
        return input ? input.value : '';
    }

    function clearDropHighlights() {
        document.querySelectorAll('.hb-plan-day-cell.hb-plan-drop-hover').forEach(function (el) {
            el.classList.remove('hb-plan-drop-hover');
        });
    }

    function readBarDragState(bar, cell, row) {
        var entityType = bar.getAttribute('data-entity-type') || (bar.getAttribute('data-booking-id') ? 'booking' : 'maintenance');
        var grabDay = cell ? cell.getAttribute('data-day-ymd') : '';
        var roomId = bar.getAttribute('data-room-id') || (row ? row.getAttribute('data-room-id') : '');
        if (entityType === 'booking') {
            return {
                entityType: 'booking',
                entityId: bar.getAttribute('data-booking-id'),
                origRoomId: roomId,
                grabDayYmd: grabDay,
                checkIn: bar.getAttribute('data-check-in'),
                checkOut: bar.getAttribute('data-check-out')
            };
        }
        return {
            entityType: 'maintenance',
            entityId: bar.getAttribute('data-maintenance-id'),
            origRoomId: roomId,
            grabDayYmd: grabDay,
            fromDate: bar.getAttribute('data-from-date'),
            throughDate: bar.getAttribute('data-through-date')
        };
    }

    function postPlanningMove(payload) {
        var csrf = getCsrfToken();
        if (!csrf) {
            return Promise.reject(new Error('Missing CSRF token.'));
        }
        var body = new URLSearchParams();
        body.set('csrf_token', csrf);
        Object.keys(payload).forEach(function (key) {
            if (payload[key] !== undefined && payload[key] !== null) {
                body.set(key, String(payload[key]));
            }
        });
        return fetch('index.php?ajax_action=planning_move', {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
            },
            body: body.toString()
        }).then(function (res) {
            return res.json().then(function (data) {
                if (!res.ok && data && !data.error) {
                    data.error = 'Request failed.';
                }
                return data;
            });
        });
    }

    document.addEventListener('dragstart', function (ev) {
        var bar = ev.target.closest('.hb-plan-bar.hb-plan-draggable');
        if (!bar) {
            return;
        }
        var cell = bar.closest('.hb-plan-day-cell');
        var row = bar.closest('tr[data-room-id]');
        dragState = readBarDragState(bar, cell, row);
        dragDidMove = false;
        bar.classList.add('hb-plan-dragging');
        if (ev.dataTransfer) {
            ev.dataTransfer.effectAllowed = 'move';
            try {
                ev.dataTransfer.setData('text/plain', dragState.entityType + ':' + dragState.entityId);
            } catch (e) { /* ignore */ }
        }
    });

    document.addEventListener('dragend', function (ev) {
        var bar = ev.target.closest('.hb-plan-bar.hb-plan-draggable');
        if (bar) {
            bar.classList.remove('hb-plan-dragging');
        }
        clearDropHighlights();
        dragState = null;
    });

    document.addEventListener('dragover', function (ev) {
        if (!dragState) {
            return;
        }
        var cell = ev.target.closest('.hb-plan-day-cell');
        if (!cell) {
            return;
        }
        ev.preventDefault();
        if (ev.dataTransfer) {
            ev.dataTransfer.dropEffect = 'move';
        }
        clearDropHighlights();
        cell.classList.add('hb-plan-drop-hover');
    });

    document.addEventListener('dragleave', function (ev) {
        var cell = ev.target.closest('.hb-plan-day-cell');
        if (cell && !cell.contains(ev.relatedTarget)) {
            cell.classList.remove('hb-plan-drop-hover');
        }
    });

    document.addEventListener('drop', function (ev) {
        if (!dragState) {
            return;
        }
        var cell = ev.target.closest('.hb-plan-day-cell');
        var row = cell ? cell.closest('tr[data-room-id]') : null;
        if (!cell || !row) {
            return;
        }
        ev.preventDefault();
        clearDropHighlights();
        dragDidMove = true;

        var targetDay = cell.getAttribute('data-day-ymd');
        var targetRoomId = row.getAttribute('data-room-id');
        var grabDay = dragState.grabDayYmd;
        if (!targetDay || !targetRoomId || !grabDay) {
            return;
        }

        var delta = dayDiffYmd(grabDay, targetDay);
        var payload = {
            entity_type: dragState.entityType,
            entity_id: dragState.entityId,
            room_id: targetRoomId
        };

        if (dragState.entityType === 'booking') {
            var newCheckIn = addDaysYmd(dragState.checkIn, delta);
            var newCheckOut = addDaysYmd(dragState.checkOut, delta);
            if (!newCheckIn || !newCheckOut) {
                return;
            }
            if (newCheckIn === dragState.checkIn && newCheckOut === dragState.checkOut && String(targetRoomId) === String(dragState.origRoomId)) {
                return;
            }
            payload.check_in = newCheckIn;
            payload.check_out = newCheckOut;
        } else {
            var newFrom = addDaysYmd(dragState.fromDate, delta);
            var newThrough = addDaysYmd(dragState.throughDate, delta);
            if (!newFrom || !newThrough) {
                return;
            }
            if (newFrom === dragState.fromDate && newThrough === dragState.throughDate && String(targetRoomId) === String(dragState.origRoomId)) {
                return;
            }
            payload.from_date = newFrom;
            payload.through_date = newThrough;
        }

        postPlanningMove(payload)
            .then(function (data) {
                if (data && data.ok) {
                    window.location.reload();
                    return;
                }
                var msg = (data && data.error) ? data.error : 'Unable to move item.';
                window.alert(msg);
            })
            .catch(function () {
                window.alert('Unable to move item.');
            });
    });

    document.addEventListener('dblclick', function (ev) {
        if (dragDidMove) {
            dragDidMove = false;
            return;
        }
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
