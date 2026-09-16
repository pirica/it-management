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

    function formatSlotDisplayValue(selection) {
        if (!selection) {
            return '';
        }
        var datePart = selection.date_display || selection.date || '';
        if (selection.display_summary) {
            return selection.display_summary;
        }
        if (selection.label && datePart) {
            return selection.label + ' (' + datePart + ')';
        }
        return selection.label || datePart;
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

    function postJson(apiUrl, formData) {
        return fetch(apiUrl, { method: 'POST', body: formData, credentials: 'same-origin' })
            .then(function (res) {
                return res.json().then(function (j) {
                    return { ok: res.ok, body: j };
                });
            });
    }

    function initViewReschedule() {
        var viewActions = document.getElementById('appointment-view-actions');
        if (!viewActions) {
            return;
        }

        var apiUrl = viewActions.getAttribute('data-api') || '';
        var csrf = viewActions.getAttribute('data-csrf') || '';
        var appointmentId = viewActions.getAttribute('data-appointment-id') || '';
        var defaultAppointmentModality = viewActions.getAttribute('data-default-appointment-modality') || 'remote';
        var appointmentType = viewActions.getAttribute('data-appointment-type') || defaultAppointmentModality;

        var cancelBtn = document.getElementById('appointment-view-cancel');
        var rescheduleBtn = document.getElementById('appointment-view-reschedule');
        var modal = document.getElementById('appointment-slot-modal');
        var weekGrid = document.getElementById('appointment-week-grid');
        var weekLabel = document.getElementById('appointment-week-label');
        var tzLabel = document.getElementById('appointment-timezone-label');
        var closeModalBtns = document.querySelectorAll('[data-appointment-close-modal]');
        var prevWeekBtn = document.getElementById('appointment-prev-week');
        var nextWeekBtn = document.getElementById('appointment-next-week');
        var confirmSlotBtn = document.getElementById('appointment-confirm-slot');

        var currentAnchor = new Date().toISOString().slice(0, 10);
        var pendingSelection = null;

        function loadWeek(anchor) {
            if (!apiUrl || !weekGrid) {
                return;
            }
            currentAnchor = anchor;
            weekGrid.innerHTML = '<p class="appointment-day-empty">Loading…</p>';
            var url = apiUrl + '?action=week_slots&date=' + encodeURIComponent(anchor)
                + '&exclude_appointment_id=' + encodeURIComponent(appointmentId);
            fetch(url, { credentials: 'same-origin' })
                .then(function (res) { return res.json(); })
                .then(function (data) {
                    if (!data || !data.success) {
                        weekGrid.innerHTML = '<p class="appointment-day-empty">Could not load slots.</p>';
                        return;
                    }
                    if (data.booking_disabled) {
                        weekGrid.innerHTML = '<p class="appointment-day-empty">' + escapeHtml(data.booking_disabled_message || 'Booking is disabled.') + '</p>';
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
                                        date_display: day.date_display || day.date,
                                        start_time: slot.start_time,
                                        end_time: slot.end_time,
                                        label: slot.label,
                                        display_summary: slot.display_summary || formatSlotDisplayValue({
                                            label: slot.label,
                                            date: day.date,
                                            date_display: day.date_display || day.date
                                        })
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

        if (cancelBtn) {
            cancelBtn.addEventListener('click', function () {
                if (!confirm('Cancel this appointment and release the time slot?')) {
                    return;
                }
                var formData = new FormData();
                formData.append('action', 'cancel');
                formData.append('csrf_token', csrf);
                formData.append('appointment_id', appointmentId);
                cancelBtn.disabled = true;
                postJson(apiUrl, formData)
                    .then(function (result) {
                        if (result.ok && result.body && result.body.success) {
                            window.location.reload();
                            return;
                        }
                        alert((result.body && result.body.message) ? result.body.message : 'Could not cancel appointment.');
                        cancelBtn.disabled = false;
                    })
                    .catch(function () {
                        alert('Could not cancel appointment.');
                        cancelBtn.disabled = false;
                    });
            });
        }

        if (rescheduleBtn) {
            rescheduleBtn.addEventListener('click', function () {
                var formData = new FormData();
                formData.append('action', 'reschedule_prepare');
                formData.append('csrf_token', csrf);
                formData.append('appointment_id', appointmentId);
                rescheduleBtn.disabled = true;
                postJson(apiUrl, formData)
                    .then(function (result) {
                        rescheduleBtn.disabled = false;
                        if (!result.ok || !result.body || !result.body.success) {
                            alert((result.body && result.body.message) ? result.body.message : 'Could not start reschedule.');
                            return;
                        }
                        pendingSelection = null;
                        if (modal) {
                            modal.classList.remove('hidden');
                        }
                        loadWeek(currentAnchor);
                    })
                    .catch(function () {
                        rescheduleBtn.disabled = false;
                        alert('Could not start reschedule.');
                    });
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
                var formData = new FormData();
                formData.append('action', 'reschedule');
                formData.append('csrf_token', csrf);
                formData.append('appointment_id', appointmentId);
                formData.append('appointment_date', pendingSelection.date);
                formData.append('start_time', pendingSelection.start_time);
                formData.append('end_time', pendingSelection.end_time);
                formData.append('appointment_type', appointmentType || defaultAppointmentModality);
                confirmSlotBtn.disabled = true;
                postJson(apiUrl, formData)
                    .then(function (result) {
                        if (result.ok && result.body && result.body.success && result.body.view_url) {
                            window.location.href = result.body.view_url;
                            return;
                        }
                        alert((result.body && result.body.message) ? result.body.message : 'Could not reschedule appointment.');
                        confirmSlotBtn.disabled = false;
                    })
                    .catch(function () {
                        alert('Could not reschedule appointment.');
                        confirmSlotBtn.disabled = false;
                    });
            });
        }
    }

    function initBookingApp() {
        var app = document.getElementById('appointment-booking-app');
        if (!app) {
            return;
        }

        var apiUrl = app.getAttribute('data-api') || '';
        var csrf = app.getAttribute('data-csrf') || '';
        var bookingEnabled = app.getAttribute('data-booking-enabled') === '1';
        var bookingDisabledMessage = app.getAttribute('data-booking-disabled-message') || 'Appointment booking is currently disabled.';
        var defaultAppointmentModality = app.getAttribute('data-default-appointment-modality') || 'remote';
        var appointmentTypeNames = [];
        try {
            appointmentTypeNames = JSON.parse(app.getAttribute('data-appointment-type-names') || '[]');
        } catch (e2) {
            appointmentTypeNames = [];
        }
        if (!Array.isArray(appointmentTypeNames)) {
            appointmentTypeNames = [];
        }
        if (appointmentTypeNames.indexOf(defaultAppointmentModality) < 0) {
            defaultAppointmentModality = appointmentTypeNames.length ? appointmentTypeNames[0] : defaultAppointmentModality;
        }
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
        var lastWeekSlotsPayload = null;

        function dayOfWeekFromYmd(dateStr) {
            var parts = String(dateStr || '').split('-');
            if (parts.length !== 3) {
                return -1;
            }
            var y = parseInt(parts[0], 10);
            var m = parseInt(parts[1], 10) - 1;
            var d = parseInt(parts[2], 10);
            return new Date(y, m, d).getDay();
        }

        function modalityFlagsFromWeekPayload(dateYmd) {
            if (!lastWeekSlotsPayload || !lastWeekSlotsPayload.days || !dateYmd) {
                return null;
            }
            for (var i = 0; i < lastWeekSlotsPayload.days.length; i++) {
                var day = lastWeekSlotsPayload.days[i];
                if (day.date === dateYmd) {
                    if (day.allowed_types && typeof day.allowed_types === 'object') {
                        return day.allowed_types;
                    }
                    return {
                        in_person: !!day.allows_in_person,
                        remote: !!day.allows_remote
                    };
                }
            }
            return null;
        }

        function modalityFlagsFromEmbed(dateYmd) {
            if (!dateYmd) {
                var open = {};
                appointmentTypeNames.forEach(function (name) {
                    open[name] = true;
                });
                return open;
            }
            var dow = dayOfWeekFromYmd(dateYmd);
            var dayRow = (modalityConfig.days || {})[dow] || (modalityConfig.days || {})[String(dow)];
            if (!dayRow) {
                var closed = {};
                appointmentTypeNames.forEach(function (name) {
                    closed[name] = false;
                });
                return closed;
            }
            if (typeof dayRow === 'object' && (dayRow.in_person !== undefined || dayRow.remote !== undefined) && dayRow.allowed_types === undefined) {
                return {
                    in_person: !!dayRow.in_person,
                    remote: !!dayRow.remote
                };
            }
            return dayRow;
        }

        function resolveModalityFlags(dateYmd) {
            var fromWeek = modalityFlagsFromWeekPayload(dateYmd);
            if (fromWeek) {
                return fromWeek;
            }
            return modalityFlagsFromEmbed(dateYmd);
        }

        function countAllowedTypes(flags) {
            var count = 0;
            var onlyName = null;
            Object.keys(flags || {}).forEach(function (key) {
                if (flags[key]) {
                    count += 1;
                    onlyName = key;
                }
            });
            return { count: count, onlyName: onlyName };
        }

        function updateAppointmentTypeUi(dateYmd) {
            var flags = resolveModalityFlags(dateYmd);
            var allowedInputs = [];
            typeCards.forEach(function (card) {
                var typeName = card.getAttribute('data-appointment-type') || '';
                var allowed = !!(flags && flags[typeName]);
                card.classList.toggle('hidden', !allowed);
                var input = card.querySelector('input[name="appointment_type"]');
                if (!input) {
                    return;
                }
                input.disabled = !allowed;
                if (!allowed) {
                    input.checked = false;
                } else {
                    allowedInputs.push({ name: typeName, input: input });
                }
            });
            var pick = null;
            for (var i = 0; i < allowedInputs.length; i++) {
                if (allowedInputs[i].name === defaultAppointmentModality) {
                    pick = allowedInputs[i].input;
                    break;
                }
            }
            if (!pick && allowedInputs.length) {
                pick = allowedInputs[0].input;
            }
            if (pick) {
                pick.checked = true;
            }
            if (modalityBanner) {
                var summary = countAllowedTypes(flags);
                var msg = '';
                if (summary.count === 1 && summary.onlyName) {
                    var labelEl = app.querySelector('.appointment-type-card[data-appointment-type="' + summary.onlyName + '"] .appointment-type-card-title');
                    var typeLabel = labelEl ? labelEl.textContent : summary.onlyName;
                    msg = dateYmd
                        ? 'This day accepts only ' + typeLabel + ' appointments.'
                        : 'This location accepts only ' + typeLabel + ' appointments.';
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

        function refreshWeekSlotsForDate(dateYmd, done) {
            if (!apiUrl || !dateYmd) {
                done();
                return;
            }
            fetch(apiUrl + '?action=week_slots&date=' + encodeURIComponent(dateYmd), { credentials: 'same-origin' })
                .then(function (res) { return res.json(); })
                .then(function (data) {
                    if (data && data.success) {
                        lastWeekSlotsPayload = data;
                    }
                    done();
                })
                .catch(function () {
                    done();
                });
        }

        function hasVisitReasonSelected() {
            return !!(reasonSelect && String(reasonSelect.value || '').trim() !== '');
        }

        function hasAppointmentSlotSelected() {
            if (confirmedSelection && confirmedSelection.date && confirmedSelection.start_time) {
                return true;
            }
            return !!(
                slotHiddenDate && String(slotHiddenDate.value || '').trim() !== ''
                && slotHiddenStart && String(slotHiddenStart.value || '').trim() !== ''
            );
        }

        function updateScheduleButton() {
            if (!scheduleBtn) {
                return;
            }
            if (!bookingEnabled) {
                scheduleBtn.disabled = true;
                return;
            }
            if (scheduleBtn.getAttribute('data-itm-submitting') !== '1') {
                scheduleBtn.disabled = false;
            }
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
                    if (data.booking_disabled) {
                        weekGrid.innerHTML = '<p class="appointment-day-empty">' + escapeHtml(data.booking_disabled_message || bookingDisabledMessage) + '</p>';
                        return;
                    }
                    if (weekLabel) {
                        weekLabel.textContent = formatWeekRange(data.week_start, data.week_end);
                    }
                    if (tzLabel) {
                        tzLabel.textContent = 'Time zone: ' + (data.timezone || 'UTC');
                    }
                    lastWeekSlotsPayload = data;
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
                                        date_display: day.date_display || day.date,
                                        start_time: slot.start_time,
                                        end_time: slot.end_time,
                                        label: slot.label,
                                        display_summary: slot.display_summary || formatSlotDisplayValue({
                                            label: slot.label,
                                            date: day.date,
                                            date_display: day.date_display || day.date
                                        })
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

        if (!bookingEnabled) {
            if (openModalBtn) {
                openModalBtn.disabled = true;
            }
            if (scheduleBtn) {
                scheduleBtn.disabled = true;
            }
        }

        if (openModalBtn) {
            openModalBtn.addEventListener('click', function () {
                if (!bookingEnabled) {
                    alert(bookingDisabledMessage);
                    return;
                }
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
                    slotDisplay.value = formatSlotDisplayValue(confirmedSelection);
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
                refreshWeekSlotsForDate(confirmedSelection.date, function () {
                    syncAppointmentTypeSection();
                    updateScheduleButton();
                });
            });
        }
        if (reasonSelect) {
            reasonSelect.addEventListener('change', updateScheduleButton);
        }

        if (scheduleBtn) {
            scheduleBtn.addEventListener('click', function () {
                if (!bookingEnabled) {
                    alert(bookingDisabledMessage);
                    return;
                }
                if (!hasVisitReasonSelected()) {
                    alert('--Select a reason for your appointment--');
                    if (reasonSelect) {
                        reasonSelect.focus();
                    }
                    return;
                }
                if (!hasAppointmentSlotSelected()) {
                    alert('Select an appointment time.');
                    if (slotDisplay) {
                        slotDisplay.focus();
                    } else if (openModalBtn) {
                        openModalBtn.focus();
                    }
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
                formData.append('appointment_type', typeInput ? typeInput.value : defaultAppointmentModality);
                scheduleBtn.disabled = true;
                scheduleBtn.setAttribute('data-itm-submitting', '1');
                postJson(apiUrl, formData)
                    .then(function (result) {
                        if (result.ok && result.body && result.body.success && result.body.view_url) {
                            window.location.href = result.body.view_url;
                            return;
                        }
                        alert((result.body && result.body.message) ? result.body.message : 'Could not schedule appointment.');
                        scheduleBtn.removeAttribute('data-itm-submitting');
                        scheduleBtn.disabled = false;
                        updateScheduleButton();
                    })
                    .catch(function () {
                        alert('Could not schedule appointment.');
                        scheduleBtn.removeAttribute('data-itm-submitting');
                        scheduleBtn.disabled = false;
                        updateScheduleButton();
                    });
            });
        }

        updateScheduleButton();
        syncAppointmentTypeSection();
    }

    initViewReschedule();
    initBookingApp();
})();
