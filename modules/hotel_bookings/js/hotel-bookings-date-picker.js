(function () {
    'use strict';

    var checkIn = document.getElementById('hb-booking-check-in');
    var checkOut = document.getElementById('hb-booking-check-out');
    if (!checkIn || !checkOut) {
        return;
    }

    var paymentInput = document.getElementById('hb-booking-payment');
    var roomSelect = document.getElementById('hb-booking-room-id');
    var internalRateSelect = document.getElementById('hb-booking-internal-rate');

    function nativeInput(el) {
        if (!el) {
            return null;
        }
        var field = el.closest('.hb-hotel-date-field');
        if (field) {
            return field.querySelector('.hb-hotel-date-native');
        }
        return el.type === 'date' ? el : null;
    }

    function parseYmd(ymd) {
        var parts = String(ymd || '').split('-');
        if (parts.length !== 3) {
            return null;
        }
        var y = parseInt(parts[0], 10);
        var m = parseInt(parts[1], 10) - 1;
        var d = parseInt(parts[2], 10);
        if (!y || m < 0 || d < 1) {
            return null;
        }
        return new Date(y, m, d);
    }

    function formatYmd(date) {
        var y = date.getFullYear();
        var m = String(date.getMonth() + 1).padStart(2, '0');
        var d = String(date.getDate()).padStart(2, '0');
        return y + '-' + m + '-' + d;
    }

    function stayNights() {
        var checkInNative = nativeInput(checkIn);
        var checkOutNative = nativeInput(checkOut);
        if (!checkInNative || !checkOutNative) {
            return 0;
        }
        var start = parseYmd(checkInNative.value);
        var end = parseYmd(checkOutNative.value);
        if (!start || !end || end <= start) {
            return 0;
        }
        return Math.max(1, Math.round((end - start) / 86400000));
    }

    function selectedRoomPrice() {
        if (!roomSelect) {
            return 0;
        }
        var opt = roomSelect.options[roomSelect.selectedIndex];
        if (!opt) {
            return 0;
        }
        return parseFloat(opt.getAttribute('data-price') || '0') || 0;
    }

    function suggestPaymentAmount() {
        if (!paymentInput) {
            return;
        }
        var internalCode = internalRateSelect ? String(internalRateSelect.value || '').toLowerCase() : '';
        if (internalCode === 'comp') {
            paymentInput.value = '0.00';
            return;
        }
        var nights = stayNights();
        var price = selectedRoomPrice();
        if (nights < 1 || price <= 0) {
            return;
        }
        var total = Math.round(price * nights * 100) / 100;
        if (internalCode === 'use') {
            paymentInput.value = '0.00';
            paymentInput.title = 'Room charges waived (USE); tourist tax is calculated on save.';
            return;
        }
        paymentInput.title = '';
        paymentInput.value = total.toFixed(2);
    }

    function syncCheckOutMin() {
        var checkInNative = nativeInput(checkIn);
        var checkOutNative = nativeInput(checkOut);
        if (!checkInNative || !checkOutNative) {
            return;
        }
        var start = parseYmd(checkInNative.value);
        if (!start) {
            return;
        }
        var minOut = new Date(start.getTime());
        minOut.setDate(minOut.getDate() + 1);
        var minStr = formatYmd(minOut);
        checkOutNative.min = minStr;
        var end = parseYmd(checkOutNative.value);
        if (end && end <= start) {
            checkOutNative.value = minStr;
            if (typeof window.itmHotelDateFormatYmd === 'function') {
                checkOut.value = window.itmHotelDateFormatYmd(minStr);
            }
        }
        suggestPaymentAmount();
    }

    checkIn.addEventListener('change', syncCheckOutMin);
    checkOut.addEventListener('change', syncCheckOutMin);
    if (roomSelect) {
        roomSelect.addEventListener('change', suggestPaymentAmount);
    }
    if (internalRateSelect) {
        internalRateSelect.addEventListener('change', suggestPaymentAmount);
    }
    syncCheckOutMin();
})();
