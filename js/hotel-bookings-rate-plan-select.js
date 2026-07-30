(function () {
    var ADD_VALUE = '__add_new__';
    var planSelect = document.getElementById('hb-booking-portal-rate-plan-id');
    var roomSelect = document.getElementById('hb-booking-room-id');
    if (!planSelect) {
        return;
    }

    var viewBtn = document.getElementById('hb-booking-rate-plan-view');
    var editBtn = document.getElementById('hb-booking-rate-plan-edit');
    var modal = document.getElementById('hb-rate-plan-modal');
    var frame = document.getElementById('hb-rate-plan-modal-frame');
    var baseUrl = (frame && frame.getAttribute('data-base')) || (window.ITM_BASE_URL || '/');

    function moduleBase() {
        return baseUrl.replace(/\/?$/, '/') + 'modules/hotel_booking_portal_rate_plans/';
    }

    function selectedRoomHotelId() {
        if (!roomSelect || !roomSelect.selectedOptions.length) {
            return 0;
        }
        return parseInt(roomSelect.selectedOptions[0].getAttribute('data-hotel-id') || '0', 10);
    }

    function filterPlanOptions() {
        var hotelId = selectedRoomHotelId();
        var previous = planSelect.value;
        Array.from(planSelect.options).forEach(function (opt) {
            if (opt.value === '' || opt.value === ADD_VALUE) {
                opt.hidden = false;
                return;
            }
            var optHotel = parseInt(opt.getAttribute('data-hotel-id') || '0', 10);
            opt.hidden = hotelId > 0 && optHotel !== hotelId;
        });
        if (planSelect.value !== ADD_VALUE && planSelect.selectedOptions[0] && planSelect.selectedOptions[0].hidden) {
            planSelect.value = '';
        } else if (previous && previous !== ADD_VALUE) {
            planSelect.value = previous;
        }
        updatePlanActionLinks();
    }

    function updatePlanActionLinks() {
        var planId = parseInt(planSelect.value || '0', 10);
        var show = planId > 0;
        if (viewBtn) {
            viewBtn.hidden = !show;
            if (show) {
                viewBtn.href = moduleBase() + 'view.php?id=' + encodeURIComponent(planId);
                viewBtn.target = '_blank';
                viewBtn.rel = 'noopener noreferrer';
            }
        }
        if (editBtn) {
            editBtn.hidden = !show;
            if (show) {
                editBtn.href = moduleBase() + 'edit.php?id=' + encodeURIComponent(planId);
                editBtn.target = '_blank';
                editBtn.rel = 'noopener noreferrer';
            }
        }
    }

    function openRatePlanModal(hotelId) {
        if (!modal || !frame || hotelId < 1) {
            return;
        }
        frame.src = moduleBase() + 'create.php?embed=1&hotel_id=' + encodeURIComponent(hotelId);
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

    function appendPlanOption(planId, label, hotelId) {
        if (!planId || !label) {
            return;
        }
        var addOpt = planSelect.querySelector('option[value="' + ADD_VALUE + '"]');
        var opt = document.createElement('option');
        opt.value = String(planId);
        opt.textContent = label;
        opt.setAttribute('data-hotel-id', String(hotelId || 0));
        if (addOpt) {
            planSelect.insertBefore(opt, addOpt);
        } else {
            planSelect.appendChild(opt);
        }
        planSelect.value = String(planId);
        filterPlanOptions();
    }

    planSelect.addEventListener('change', function () {
        if (planSelect.value === ADD_VALUE) {
            var hotelId = selectedRoomHotelId();
            planSelect.value = planSelect.dataset.previousValue || '';
            openRatePlanModal(hotelId);
            return;
        }
        planSelect.dataset.previousValue = planSelect.value || '';
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
            appendPlanOption(parseInt(data.id, 10) || 0, data.name || '', parseInt(data.hotel_id, 10) || 0);
            closeRatePlanModal();
        }
    });

    filterPlanOptions();
})();
