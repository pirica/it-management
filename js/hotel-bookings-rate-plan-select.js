(function () {
    var ADD_VALUE = '__add_new__';

    function init() {
        var planSelect = document.getElementById('hb-booking-portal-rate-plan-id');
        var roomSelect = document.getElementById('hb-booking-room-id');
        if (!planSelect) {
            return;
        }

        var hintEl = document.getElementById('hb-booking-rate-plan-hint');
        var viewBtn = document.getElementById('hb-booking-rate-plan-view');
        var editBtn = document.getElementById('hb-booking-rate-plan-edit');
        var previousPlanValue = planSelect.value && planSelect.value !== ADD_VALUE ? planSelect.value : '';

        function modalEl() {
            return document.getElementById('hb-rate-plan-modal');
        }

        function frameEl() {
            return document.getElementById('hb-rate-plan-modal-frame');
        }

        function modalTitleEl() {
            return document.getElementById('hb-rate-plan-modal-title');
        }

        function moduleBase() {
            var frame = frameEl();
            var baseUrl = (frame && frame.getAttribute('data-base')) || (window.ITM_BASE_URL || '/');
            return baseUrl.replace(/\/?$/, '/') + 'modules/hotel_booking_portal_rate_plans/';
        }

        function addNewOption() {
            return planSelect.querySelector('option[value="' + ADD_VALUE + '"]');
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
            var previous = planSelect.value === ADD_VALUE ? previousPlanValue : planSelect.value;
            var visibleCount = 0;
            Array.from(planSelect.options).forEach(function (opt) {
                if (opt.value === '' || opt.value === ADD_VALUE) {
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
            if (planSelect.selectedOptions[0] && planSelect.selectedOptions[0].hidden) {
                planSelect.value = '';
            } else if (previous) {
                planSelect.value = previous;
            }
            if (planSelect.value !== ADD_VALUE) {
                previousPlanValue = planSelect.value;
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
            var modalTitle = modalTitleEl();
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
            var modal = modalEl();
            var frame = frameEl();
            if (!modal || !frame || !url) {
                return;
            }
            setModalTitle(mode || 'view');
            frame.src = url;
            modal.hidden = false;
            document.body.classList.add('hb-plan-maint-modal-open');
        }

        function closeRatePlanModal() {
            var modal = modalEl();
            var frame = frameEl();
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
                var addOpt = addNewOption();
                if (addOpt) {
                    planSelect.insertBefore(opt, addOpt);
                } else {
                    planSelect.appendChild(opt);
                }
            }
            opt.textContent = label;
            opt.setAttribute('data-hotel-id', String(hotelId || 0));
            planSelect.value = String(planId);
            previousPlanValue = planSelect.value;
            filterPlanOptions();
        }

        function openCreateModal() {
            var hotelId = selectedRoomHotelId();
            if (hotelId < 1) {
                if (hintEl) {
                    hintEl.hidden = false;
                }
                if (roomSelect) {
                    roomSelect.focus();
                }
                return;
            }
            openRatePlanModal(moduleBase() + 'create.php?embed=1&hotel_id=' + encodeURIComponent(hotelId), 'create');
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
            if (planSelect.value === ADD_VALUE) {
                planSelect.value = previousPlanValue || '';
                openCreateModal();
                return;
            }
            previousPlanValue = planSelect.value;
            updatePlanActionLinks();
        });

        if (roomSelect) {
            roomSelect.addEventListener('change', filterPlanOptions);
        }

        document.addEventListener('click', function (ev) {
            if (ev.target.closest('[data-hb-rate-plan-modal-close]')) {
                closeRatePlanModal();
            }
            var modal = modalEl();
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
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
