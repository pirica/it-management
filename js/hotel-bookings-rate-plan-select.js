(function () {
    var planSelect = document.getElementById('hb-booking-portal-rate-plan-id');
    var roomSelect = document.getElementById('hb-booking-room-id');
    if (!planSelect) {
        return;
    }

    var hintEl = document.getElementById('hb-booking-rate-plan-hint');
    var addBtn = document.getElementById('hb-booking-rate-plan-add');
    var viewBtn = document.getElementById('hb-booking-rate-plan-view');
    var editBtn = document.getElementById('hb-booking-rate-plan-edit');
    var modal = document.getElementById('hb-rate-plan-modal');
    var frame = document.getElementById('hb-rate-plan-modal-frame');
    var modalTitle = document.getElementById('hb-rate-plan-modal-title');
    var baseUrl = (frame && frame.getAttribute('data-base')) || (window.ITM_BASE_URL || '/');

    function moduleBase() {
        return baseUrl.replace(/\/?$/, '/') + 'modules/hotel_booking_portal_rate_plans/';
    }

    function selectedRoomHotelId() {
        if (!roomSelect || !roomSelect.selectedOptions.length || !roomSelect.value) {
            return 0;
        }
        return parseInt(roomSelect.selectedOptions[0].getAttribute('data-hotel-id') || '0', 10);
    }

    function formatPlanLabel(name, slug) {
        var label = String(name || '').trim();
        var s = String(slug || '').trim();
        if (s !== '') {
            label = label !== '' ? label + ' (' + s + ')' : s;
        }
        return label;
    }

    function filterPlanOptions() {
        var hotelId = selectedRoomHotelId();
        var previous = planSelect.value;
        var visibleCount = 0;
        Array.from(planSelect.options).forEach(function (opt) {
            if (opt.value === '') {
                opt.hidden = false;
                return;
            }
            var optHotel = parseInt(opt.getAttribute('data-hotel-id') || '0', 10);
            var show = hotelId <= 0 || optHotel === hotelId;
            opt.hidden = !show;
            if (show) {
                visibleCount++;
            }
        });
        if (hintEl) {
            hintEl.hidden = hotelId > 0;
        }
        if (addBtn) {
            addBtn.disabled = hotelId < 1;
        }
        if (planSelect.selectedOptions[0] && planSelect.selectedOptions[0].hidden) {
            planSelect.value = '';
        } else if (previous) {
            planSelect.value = previous;
        }
        updatePlanActionLinks();
        return visibleCount;
    }

    function updatePlanActionLinks() {
        var planId = parseInt(planSelect.value || '0', 10);
        var show = planId > 0;
        if (viewBtn) {
            viewBtn.hidden = !show;
        }
        if (editBtn) {
            editBtn.hidden = !show;
        }
    }

    function setModalTitle(mode) {
        if (!modalTitle) {
            return;
        }
        if (mode === 'create') {
            modalTitle.textContent = '➕';
            modalTitle.setAttribute('title', 'Create rate plan');
        } else if (mode === 'edit') {
            modalTitle.textContent = '✏️';
            modalTitle.setAttribute('title', 'Edit rate plan');
        } else {
            modalTitle.textContent = '🔎';
            modalTitle.setAttribute('title', 'View rate plan');
        }
    }

    function openRatePlanModal(url, mode) {
        if (!modal || !frame || !url) {
            return;
        }
        setModalTitle(mode || 'view');
        frame.src = url;
        modal.hidden = false;
        document.body.classList.add('hb-plan-maint-modal-open');
    }

    function closeRatePlanModal() {
        if (modal) {
            modal.hidden = true;
        }
        if (frame) {
            frame.src = 'about:blank';
        }
        document.body.classList.remove('hb-plan-maint-modal-open');
    }

    function upsertPlanOption(planId, label, hotelId) {
        if (!planId || !label) {
            return;
        }
        var opt = planSelect.querySelector('option[value="' + String(planId) + '"]');
        if (!opt) {
            opt = document.createElement('option');
            opt.value = String(planId);
            planSelect.appendChild(opt);
        }
        opt.textContent = label;
        opt.setAttribute('data-hotel-id', String(hotelId || 0));
        planSelect.value = String(planId);
        filterPlanOptions();
    }

    if (addBtn) {
        addBtn.addEventListener('click', function () {
            var hotelId = selectedRoomHotelId();
            if (hotelId < 1) {
                return;
            }
            openRatePlanModal(moduleBase() + 'create.php?embed=1&hotel_id=' + encodeURIComponent(hotelId), 'create');
        });
    }

    if (viewBtn) {
        viewBtn.addEventListener('click', function () {
            var planId = parseInt(planSelect.value || '0', 10);
            if (planId < 1) {
                return;
            }
            openRatePlanModal(moduleBase() + 'view.php?id=' + encodeURIComponent(planId) + '&embed=1', 'view');
        });
    }

    if (editBtn) {
        editBtn.addEventListener('click', function () {
            var planId = parseInt(planSelect.value || '0', 10);
            if (planId < 1) {
                return;
            }
            openRatePlanModal(moduleBase() + 'edit.php?id=' + encodeURIComponent(planId) + '&embed=1', 'edit');
        });
    }

    planSelect.addEventListener('change', function () {
        updatePlanActionLinks();
    });

    if (roomSelect) {
        roomSelect.addEventListener('change', filterPlanOptions);
    }

    document.addEventListener('click', function (ev) {
        if (ev.target.closest('[data-hb-rate-plan-modal-close]')) {
            closeRatePlanModal();
        }
        if (modal && !modal.hidden && ev.target === modal) {
            closeRatePlanModal();
        }
    });

    window.addEventListener('message', function (ev) {
        var data = ev.data;
        if (!data || typeof data !== 'object') {
            return;
        }
        if (data.type === 'hb_rate_plan_embed_close') {
            closeRatePlanModal();
        } else if (data.type === 'hb_rate_plan_embed_saved') {
            var label = formatPlanLabel(data.name, data.rate_plan_slug);
            upsertPlanOption(parseInt(data.id, 10) || 0, label, parseInt(data.hotel_id, 10) || 0);
            closeRatePlanModal();
        }
    });

    filterPlanOptions();
})();
