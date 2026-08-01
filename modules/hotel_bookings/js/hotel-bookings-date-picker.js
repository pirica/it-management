(function () {
    'use strict';

    var checkIn = document.getElementById('hb-booking-check-in');
    var checkOut = document.getElementById('hb-booking-check-out');
    if (!checkIn || !checkOut) {
        return;
    }

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
    }

    checkIn.addEventListener('change', syncCheckOutMin);
    checkOut.addEventListener('change', syncCheckOutMin);
    syncCheckOutMin();
})();
