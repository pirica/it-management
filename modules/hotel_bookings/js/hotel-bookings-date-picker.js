(function () {
    'use strict';

    var checkIn = document.getElementById('hb-booking-check-in');
    var checkOut = document.getElementById('hb-booking-check-out');
    if (!checkIn || !checkOut) {
        return;
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
        var start = parseYmd(checkIn.value);
        if (!start) {
            return;
        }
        var minOut = new Date(start.getTime());
        minOut.setDate(minOut.getDate() + 1);
        var minStr = formatYmd(minOut);
        checkOut.min = minStr;
        var end = parseYmd(checkOut.value);
        if (end && end <= start) {
            checkOut.value = minStr;
        }
    }

    checkIn.addEventListener('change', syncCheckOutMin);
    syncCheckOutMin();
})();
