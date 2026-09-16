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
        var months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
        return String(dt.getDate()).padStart(2, '0') + '/' + months[dt.getMonth()] + '/' + dt.getFullYear();
    }

    function formatUkDatetime(localValue) {
        if (!localValue || localValue.indexOf('T') === -1) {
            return '';
        }
        var parts = String(localValue).split('T');
        var dateText = formatUkDate(parts[0]);
        if (!dateText) {
            return '';
        }
        var timeParts = parts[1].split(':');
        return dateText + ' ' + String(timeParts[0] || '00').padStart(2, '0') + ':' + String(timeParts[1] || '00').padStart(2, '0');
    }

    function parseUkDateText(text) {
        var raw = String(text || '').trim();
        var monMatch = /^(\d{1,2})\/([A-Za-z]{3})\/(\d{4})$/.exec(raw);
        if (monMatch) {
            var months = { jan: 0, feb: 1, mar: 2, apr: 3, may: 4, jun: 5, jul: 6, aug: 7, sep: 8, oct: 9, nov: 10, dec: 11 };
            var monthKey = monMatch[2].toLowerCase();
            if (Object.prototype.hasOwnProperty.call(months, monthKey)) {
                var y = parseInt(monMatch[3], 10);
                var m = months[monthKey];
                var d = parseInt(monMatch[1], 10);
                var dtMon = new Date(y, m, d);
                if (dtMon.getFullYear() === y && dtMon.getMonth() === m && dtMon.getDate() === d) {
                    return formatYmd(dtMon);
                }
            }
        }
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

    function parseUkDatetimeText(text) {
        var raw = String(text || '').trim();
        var monMatch = /^(\d{1,2})\/([A-Za-z]{3})\/(\d{4})\s+(\d{1,2}):(\d{2})$/.exec(raw);
        if (monMatch) {
            var months = { jan: 0, feb: 1, mar: 2, apr: 3, may: 4, jun: 5, jul: 6, aug: 7, sep: 8, oct: 9, nov: 10, dec: 11 };
            var monthKey = monMatch[2].toLowerCase();
            if (Object.prototype.hasOwnProperty.call(months, monthKey)) {
                var y = parseInt(monMatch[3], 10);
                var m = months[monthKey];
                var d = parseInt(monMatch[1], 10);
                var hh = String(parseInt(monMatch[4], 10)).padStart(2, '0');
                var mm = String(parseInt(monMatch[5], 10)).padStart(2, '0');
                var dtMon = new Date(y, m, d);
                if (dtMon.getFullYear() === y && dtMon.getMonth() === m && dtMon.getDate() === d) {
                    return formatYmd(dtMon) + 'T' + hh + ':' + mm;
                }
            }
        }
        var match = /^(\d{1,2})\/(\d{1,2})\/(\d{4})\s+(\d{1,2}):(\d{2})$/.exec(raw);
        if (!match) {
            return '';
        }
        var isoDate = parseUkDateText(match[1] + '/' + match[2] + '/' + match[3]);
        if (!isoDate) {
            return '';
        }
        return isoDate + 'T' + String(parseInt(match[4], 10)).padStart(2, '0') + ':' + String(parseInt(match[5], 10)).padStart(2, '0');
    }

    function syncNativeFromText(field) {
        var text = field.querySelector('.itm-uk-date-text');
        var native = field.querySelector('.itm-uk-date-native');
        if (!text || !native) {
            return;
        }
        var isDatetime = field.classList.contains('itm-uk-datetime-field');
        var iso = isDatetime ? parseUkDatetimeText(text.value) : parseUkDateText(text.value);
        if (iso === '' && /^\d{4}-\d{2}-\d{2}/.test(text.value)) {
            iso = isDatetime ? text.value.replace(' ', 'T').substring(0, 16) : text.value.substring(0, 10);
        }
        native.value = iso;
    }

    function syncTextFromNative(field) {
        var text = field.querySelector('.itm-uk-date-text');
        var native = field.querySelector('.itm-uk-date-native');
        if (!text || !native || native.value === '') {
            return;
        }
        if (field.classList.contains('itm-uk-datetime-field')) {
            text.value = formatUkDatetime(native.value);
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
        if (btn.getAttribute('data-itm-uk-date-open-bound') === '1') {
            return;
        }
        btn.setAttribute('data-itm-uk-date-open-bound', '1');
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
