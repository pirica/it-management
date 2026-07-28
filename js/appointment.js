(function () {
    'use strict';

    function escapeHtml(text) {
        var div = document.createElement('div');
        div.textContent = text == null ? '' : String(text);
        return div.innerHTML;
    }

    function parseYmd(dateStr) {
        var parts = String(dateStr || '').split('-');
        if (parts.length !== 3) {
            return null;
        }
        return new Date(parseInt(parts[0], 10), parseInt(parts[1], 10) - 1, parseInt(parts[2], 10));
    }

    function formatWeekRange(weekStart, weekEnd) {
        var s = parseYmd(weekStart);
        var e = parseYmd(weekEnd);
        if (!s || !e) {
            return weekStart + ' - ' + weekEnd;
        }
        var opts = { weekday: 'short', month: 'short', day: 'numeric', year: 'numeric' };
        return s.toLocaleDateString(undefined, opts) + ' - ' + e.toLocaleDateString(undefined, opts);
    }

    var app = document.getElementById('appointment-booking-app');
    if (!app) {
        return;
    }

    var apiUrl = app.getAttribute('data-api') || '';
    var csrf = app.getAttribute('data-csrf') || '';
    var anchorInput = document.getElementById('appointment-anchor-date');
    var slotDisplay = document.getElementById('appointment-slot-display');
    var slotHiddenDate = document.getElementById('appointment_date');
    var slotHiddenStart = document.getElementById('start_time');
    var slotHiddenEnd = document.getElementById('end_time');
    var scheduleBtn = document.getElementById('appointment-schedule-btn');
    var reasonSelect = document.getElementById('visit_reason_id');
    var modal = document.getElementById('appointment-slot-modal');
    var weekGrid = document.getElementById('appointment-week-grid');
    var weekLabel = document.getElementById('appointment-week-label');
    var tzLabel = document.getElementById('appointment-timezone-label');
    var openModalBtn = document.getElementById('appointment-open-modal');
    var closeModalBtns = document.querySelectorAll('[data-appointment-close-modal]');
    var prevWeekBtn = document.getElementById('appointment-prev-week');
    var nextWeekBtn = document.getElementById('appointment-next-week');
    var confirmSlotBtn = document.getElementById('appointment-confirm-slot');

    var currentAnchor = anchorInput ? anchorInput.value : '';
    var pendingSelection = null;
    var confirmedSelection = null;
    var modalityConfig = null;
    try {
        modalityConfig = JSON.parse(app.getAttribute('data-modality-config') || '{}');
    } catch (e) {
        modalityConfig = { company: {}, days: {} };
    }
    var modalityBanner = document.getElementById('appointment-modality-banner');
    var typeGroup = app.querySelector('.appointment-type-group');
    var typeCards = app.querySelectorAll('.appointment-type-card[data-appointment-type]');

    function dayOfWeekFromYmd(dateStr) {
        var d = parseYmd(dateStr);
        return d ? d.getDay() : -1;
    }

    function resolveModalityFlags(dateYmd) {
        var company = modalityConfig.company || {};
        var allowInPerson = !!company.in_person;
        var allowRemote = !!company.remote;
        if (dateYmd) {
            var dow = dayOfWeekFromYmd(dateYmd);
            var dayRow = (modalityConfig.days || {})[dow] || (modalityConfig.days || {})[String(dow)];
            if (dayRow) {
                allowInPerson = allowInPerson && !!dayRow.in_person;
                allowRemote = allowRemote && !!dayRow.remote;
            } else {
                allowInPerson = false;
                allowRemote = false;
            }
        }
        return { in_person: allowInPerson, remote: allowRemote };
    }

    function updateAppointmentTypeUi(dateYmd) {
        var flags = resolveModalityFlags(dateYmd || null);
        var firstChecked = null;
        typeCards.forEach(function (card) {
            var typeName = card.getAttribute('data-appointment-type') || '';
            var allowed = typeName === 'remote' ? flags.remote : typeName === 'in_person' ? flags.in_person : false;
            card.classList.toggle('hidden', !allowed);
            var input = card.querySelector('input[name="appointment_type"]');
            if (!input) {
                return;
            }
            input.disabled = !allowed;
            if (!allowed) {
                input.checked = false;
            } else if (!firstChecked) {
                firstChecked = input;
            }
        });
        if (firstChecked) {
            firstChecked.checked = true;
        }
        if (modalityBanner) {
            var msg = '';
            if (flags.in_person && !flags.remote) {
                msg = dateYmd
                    ? 'This day accepts only in-person appointments.'
                    : 'This location accepts only in-person appointments.';
            } else if (flags.remote && !flags.in_person) {
                msg = dateYmd
                    ? 'This day accepts only remote appointments.'
                    : 'This location accepts only remote appointments.';
            }
            modalityBanner.textContent = msg;
            modalityBanner.classList.toggle('hidden', msg === '');
        }
    }

    function syncAppointmentTypeSection() {
        var hasSlot = !!(confirmedSelection && confirmedSelection.date && confirmedSelection.start_time);
        if (typeGroup) {
            typeGroup.classList.toggle('hidden', !hasSlot);
        }
        if (!hasSlot) {
            if (modalityBanner) {
                modalityBanner.textContent = '';
                modalityBanner.classList.add('hidden');
            }
            return;
        }
        updateAppointmentTypeUi(confirmedSelection.date);
    }

    function updateScheduleButton() {
        if (!scheduleBtn || !reasonSelect) {
            return;
        }
        var reasonOk = reasonSelect.value && reasonSelect.value !== '';
        var slotOk = confirmedSelection && confirmedSelection.date && confirmedSelection.start_time;
        scheduleBtn.disabled = !(reasonOk && slotOk);
    }

    function loadWeek(anchor) {
        if (!apiUrl || !weekGrid) {
            return;
        }
        currentAnchor = anchor;
        if (anchorInput) {
            anchorInput.value = anchor;
        }
        weekGrid.innerHTML = '<p class="appointment-day-empty">Loading…</p>';
        fetch(apiUrl + '?action=week_slots&date=' + encodeURIComponent(anchor), { credentials: 'same-origin' })
            .then(function (res) { return res.json(); })
            .then(function (data) {
                if (!data || !data.success) {
                    weekGrid.innerHTML = '<p class="appointment-day-empty">Could not load slots.</p>';
                    return;
                }
                if (weekLabel) {
                    weekLabel.textContent = formatWeekRange(data.week_start, data.week_end);
                }
                if (tzLabel) {
                    tzLabel.textContent = 'Time zone: ' + (data.timezone || 'UTC');
                }
                weekGrid.innerHTML = '';
                (data.days || []).forEach(function (day) {
                    var col = document.createElement('div');
                    col.className = 'appointment-day-col';
                    col.innerHTML = '<div class="appointment-day-head">' + escapeHtml(day.day_label) + ' ' + escapeHtml(String(day.day_number)) + '</div>';
                    if (!day.slots || day.slots.length === 0) {
                        var empty = document.createElement('div');
                        empty.className = 'appointment-day-empty';
                        empty.textContent = 'No appointments';
                        col.appendChild(empty);
                    } else {
                        day.slots.forEach(function (slot) {
                            var btn = document.createElement('button');
                            btn.type = 'button';
                            btn.className = 'appointment-slot-btn';
                            btn.textContent = slot.label;
                            btn.disabled = !slot.available;
                            btn.setAttribute('data-date', day.date);
                            btn.setAttribute('data-start', slot.start_time);
                            btn.setAttribute('data-end', slot.end_time);
                            if (pendingSelection && pendingSelection.date === day.date && pendingSelection.start_time === slot.start_time) {
                                btn.classList.add('selected');
                            }
                            btn.addEventListener('click', function () {
                                if (!slot.available) {
                                    return;
                                }
                                pendingSelection = {
                                    date: day.date,
                                    start_time: slot.start_time,
                                    end_time: slot.end_time,
                                    label: slot.label
                                };
                                weekGrid.querySelectorAll('.appointment-slot-btn.selected').forEach(function (el) {
                                    el.classList.remove('selected');
                                });
                                btn.classList.add('selected');
                            });
                            col.appendChild(btn);
                        });
                    }
                    weekGrid.appendChild(col);
                });
            })
            .catch(function () {
                weekGrid.innerHTML = '<p class="appointment-day-empty">Could not load slots.</p>';
            });
    }

    function shiftAnchor(days) {
        var d = parseYmd(currentAnchor);
        if (!d) {
            return;
        }
        d.setDate(d.getDate() + days);
        var y = d.getFullYear();
        var m = String(d.getMonth() + 1).padStart(2, '0');
        var day = String(d.getDate()).padStart(2, '0');
        loadWeek(y + '-' + m + '-' + day);
    }

    if (openModalBtn) {
        openModalBtn.addEventListener('click', function () {
            pendingSelection = confirmedSelection ? Object.assign({}, confirmedSelection) : null;
            if (modal) {
                modal.classList.remove('hidden');
            }
            loadWeek(currentAnchor || new Date().toISOString().slice(0, 10));
        });
    }
    closeModalBtns.forEach(function (btn) {
        btn.addEventListener('click', function () {
            if (modal) {
                modal.classList.add('hidden');
            }
        });
    });
    if (prevWeekBtn) {
        prevWeekBtn.addEventListener('click', function () { shiftAnchor(-7); });
    }
    if (nextWeekBtn) {
        nextWeekBtn.addEventListener('click', function () { shiftAnchor(7); });
    }
    if (confirmSlotBtn) {
        confirmSlotBtn.addEventListener('click', function () {
            if (!pendingSelection) {
                return;
            }
            confirmedSelection = Object.assign({}, pendingSelection);
            if (slotDisplay) {
                slotDisplay.value = confirmedSelection.label + ' (' + confirmedSelection.date + ')';
            }
            if (slotHiddenDate) {
                slotHiddenDate.value = confirmedSelection.date;
            }
            if (slotHiddenStart) {
                slotHiddenStart.value = confirmedSelection.start_time;
            }
            if (slotHiddenEnd) {
                slotHiddenEnd.value = confirmedSelection.end_time;
            }
            if (modal) {
                modal.classList.add('hidden');
            }
            syncAppointmentTypeSection();
            updateScheduleButton();
        });
    }
    if (reasonSelect) {
        reasonSelect.addEventListener('change', updateScheduleButton);
    }

    if (scheduleBtn) {
        scheduleBtn.addEventListener('click', function () {
            if (scheduleBtn.disabled) {
                return;
            }
            var typeInput = document.querySelector('input[name="appointment_type"]:checked');
            var formData = new FormData();
            formData.append('action', 'schedule');
            formData.append('csrf_token', csrf);
            formData.append('visit_reason_id', reasonSelect.value);
            formData.append('appointment_date', slotHiddenDate.value);
            formData.append('start_time', slotHiddenStart.value);
            formData.append('end_time', slotHiddenEnd.value);
            formData.append('appointment_type', typeInput ? typeInput.value : 'in_person');
            scheduleBtn.disabled = true;
            fetch(apiUrl, { method: 'POST', body: formData, credentials: 'same-origin' })
                .then(function (res) { return res.json().then(function (j) { return { ok: res.ok, body: j }; }); })
                .then(function (result) {
                    if (result.ok && result.body && result.body.success && result.body.view_url) {
                        window.location.href = result.body.view_url;
                        return;
                    }
                    alert((result.body && result.body.message) ? result.body.message : 'Could not schedule appointment.');
                    scheduleBtn.disabled = false;
                    updateScheduleButton();
                })
                .catch(function () {
                    alert('Could not schedule appointment.');
                    scheduleBtn.disabled = false;
                    updateScheduleButton();
                });
        });
    }

    updateScheduleButton();
    syncAppointmentTypeSection();
})();
