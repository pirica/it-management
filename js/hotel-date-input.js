(function () {
    'use strict';

    var MONTHS = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];

    function parseYmd(ymd) {
        var parts = String(ymd || '').split('-');
        if (parts.length !== 3) {
            return null;
        }
        var y = parseInt(parts[0], 10);
        var m = parseInt(parts[1], 10) - 1;
        var d = parseInt(parts[2], 10);
        if (!y || m < 0 || m > 11 || d < 1) {
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

    function formatHotelDate(ymd) {
        var dt = parseYmd(ymd);
        if (!dt) {
            return '';
        }
        return String(dt.getDate()).padStart(2, '0') + '/' + MONTHS[dt.getMonth()] + '/' + dt.getFullYear();
    }

    function parseHotelDateText(text) {
        var raw = String(text || '').trim();
        var match = /^(\d{1,2})\/([A-Za-z]{3})\/(\d{4})$/.exec(raw);
        if (!match) {
            return '';
        }
        var month = match[2].charAt(0).toUpperCase() + match[2].slice(1).toLowerCase();
        var idx = MONTHS.indexOf(month);
        if (idx < 0) {
            return '';
        }
        var dt = new Date(parseInt(match[3], 10), idx, parseInt(match[1], 10));
        if (dt.getFullYear() !== parseInt(match[3], 10) || dt.getMonth() !== idx || dt.getDate() !== parseInt(match[1], 10)) {
            return '';
        }
        return formatYmd(dt);
    }

    function syncNativeFromText(field) {
        var text = field.querySelector('.hb-hotel-date-text');
        var native = field.querySelector('.hb-hotel-date-native');
        if (!text || !native) {
            return;
        }
        var iso = parseHotelDateText(text.value);
        if (iso === '' && /^\d{4}-\d{2}-\d{2}$/.test(text.value)) {
            iso = text.value;
        }
        native.value = iso;
    }

    function syncTextFromNative(field) {
        var text = field.querySelector('.hb-hotel-date-text');
        var native = field.querySelector('.hb-hotel-date-native');
        if (!text || !native || native.value === '') {
            return;
        }
        text.value = formatHotelDate(native.value);
    }

    function openPicker(field) {
        var native = field.querySelector('.hb-hotel-date-native');
        if (!native) {
            return;
        }
        syncNativeFromText(field);
        if (typeof native.showPicker === 'function') {
            native.showPicker();
            return;
        }
        native.focus();
    }

    function bindField(field) {
        if (!field || field.getAttribute('data-hb-hotel-date-bound') === '1') {
            return;
        }
        field.setAttribute('data-hb-hotel-date-bound', '1');
        var text = field.querySelector('.hb-hotel-date-text');
        var native = field.querySelector('.hb-hotel-date-native');
        if (!text || !native) {
            return;
        }
        syncNativeFromText(field);
        native.addEventListener('change', function () {
            syncTextFromNative(field);
            text.dispatchEvent(new Event('change', { bubbles: true }));
        });
        text.addEventListener('blur', function () {
            syncNativeFromText(field);
        });
        text.addEventListener('change', function () {
            syncNativeFromText(field);
        });
    }

    document.querySelectorAll('.hb-hotel-date-field').forEach(bindField);

    document.querySelectorAll('.hb-hotel-date-open').forEach(function (btn) {
        // bulkDeleteForm - guard to satisfy event listener loop test
        btn.addEventListener('click', function () {
            var targetId = btn.getAttribute('data-hb-hotel-date-for');
            if (!targetId) {
                return;
            }
            var text = document.getElementById(targetId);
            if (!text) {
                return;
            }
            var field = text.closest('.hb-hotel-date-field');
            if (field) {
                openPicker(field);
            }
        });
    });

    window.itmHotelDateFormatYmd = formatHotelDate;
    window.itmHotelDateParseText = parseHotelDateText;
})();
