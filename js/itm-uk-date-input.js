(function () {
    'use strict';

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

    function formatUkDate(ymd) {
        var dt = parseYmd(ymd);
        if (!dt) {
            return '';
        }
        return String(dt.getDate()).padStart(2, '0') + '/' + String(dt.getMonth() + 1).padStart(2, '0') + '/' + dt.getFullYear();
    }

    function parseUkDateText(text) {
        var raw = String(text || '').trim();
        var match = /^(\d{1,2})\/(\d{1,2})\/(\d{4})$/.exec(raw);
        if (!match) {
            return '';
        }
        var d = parseInt(match[1], 10);
        var m = parseInt(match[2], 10) - 1;
        var y = parseInt(match[3], 10);
        var dt = new Date(y, m, d);
        if (dt.getFullYear() !== y || dt.getMonth() !== m || dt.getDate() !== d) {
            return '';
        }
        return formatYmd(dt);
    }

    function syncNativeFromText(field) {
        var text = field.querySelector('.itm-uk-date-text');
        var native = field.querySelector('.itm-uk-date-native');
        if (!text || !native) {
            return;
        }
        var iso = parseUkDateText(text.value);
        if (iso === '' && /^\d{4}-\d{2}-\d{2}$/.test(text.value)) {
            iso = text.value;
        }
        native.value = iso;
    }

    function syncTextFromNative(field) {
        var text = field.querySelector('.itm-uk-date-text');
        var native = field.querySelector('.itm-uk-date-native');
        if (!text || !native || native.value === '') {
            return;
        }
        text.value = formatUkDate(native.value);
    }

    function openPicker(field) {
        var native = field.querySelector('.itm-uk-date-native');
        if (!native) {
            return;
        }
        syncNativeFromText(field);
        if (typeof native.showPicker === 'function') {
            try {
                native.showPicker();
                return;
            } catch (err) {
                native.focus();
                native.click();
                return;
            }
        }
        native.focus();
        native.click();
    }

    function bindField(field) {
        if (!field || field.getAttribute('data-itm-uk-date-bound') === '1') {
            return;
        }
        field.setAttribute('data-itm-uk-date-bound', '1');
        var text = field.querySelector('.itm-uk-date-text');
        var native = field.querySelector('.itm-uk-date-native');
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

    document.querySelectorAll('.itm-uk-date-field').forEach(bindField);

    document.querySelectorAll('.itm-uk-date-open').forEach(function (btn) {
        btn.addEventListener('click', function (event) {
            event.preventDefault();
            event.stopPropagation();
            var targetId = btn.getAttribute('data-itm-uk-date-for');
            if (!targetId) {
                return;
            }
            var text = document.getElementById(targetId);
            if (!text) {
                return;
            }
            var field = text.closest('.itm-uk-date-field');
            if (field) {
                openPicker(field);
            }
        });
    });

    window.itmUkDateFormatYmd = formatUkDate;
    window.itmUkDateParseText = parseUkDateText;
})();
