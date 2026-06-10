/**
 * Themed in-page notifications for AJAX/modal failures (replaces raw window.alert for errors).
 */
(function () {
    'use strict';

    function getRegion() {
        var region = document.getElementById('itm-js-alert-region');
        if (region) {
            return region;
        }
        var content = document.querySelector('.main-content .content') || document.querySelector('.content');
        region = document.createElement('div');
        region.id = 'itm-js-alert-region';
        region.className = 'itm-js-alert-region';
        region.setAttribute('aria-live', 'polite');
        if (content) {
            content.insertBefore(region, content.firstChild);
        } else {
            document.body.insertBefore(region, document.body.firstChild);
        }
        return region;
    }

    function buildAlert(message, type) {
        var typeClass = type === 'success' ? 'itm-alert-success' : 'itm-alert-error';
        var alertClass = type === 'success' ? 'alert-success' : 'alert-error';
        var icon = type === 'success' ? '✓' : '⚠';
        var el = document.createElement('div');
        el.className = 'itm-alert ' + typeClass + ' alert ' + alertClass;
        el.setAttribute('role', 'alert');
        el.innerHTML =
            '<span class="itm-alert-icon" aria-hidden="true">' + icon + '</span>' +
            '<div class="itm-alert-body"><p class="itm-alert-message"></p></div>';
        el.querySelector('.itm-alert-message').textContent = message;
        return el;
    }

    function getOpenIdfModalBackdrop() {
        var backdrops = document.querySelectorAll('.idf-modal-backdrop');
        // Why: Nested quick-add modals (status/cable color) stack on port/link; last open backdrop is topmost.
        for (var i = backdrops.length - 1; i >= 0; i--) {
            if (backdrops[i].style.display === 'flex') {
                return backdrops[i];
            }
        }
        return null;
    }

    function getModalAlertRegion(modal) {
        var region = modal.querySelector('.itm-modal-alert-region');
        if (region) {
            region.innerHTML = '';
            return region;
        }
        region = document.createElement('div');
        region.className = 'itm-modal-alert-region';
        region.setAttribute('aria-live', 'polite');
        var header = modal.querySelector('.idf-modal-header');
        if (header && header.nextSibling) {
            modal.insertBefore(region, header.nextSibling);
        } else {
            modal.insertBefore(region, modal.firstChild);
        }
        return region;
    }

    function notifyInOpenModal(message, type) {
        var backdrop = getOpenIdfModalBackdrop();
        if (!backdrop) {
            return false;
        }
        var modal = backdrop.querySelector('.idf-modal');
        if (!modal) {
            return false;
        }
        var region = getModalAlertRegion(modal);
        region.appendChild(buildAlert(message, type));
        region.lastElementChild.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        return true;
    }

    function notify(message, type) {
        var text = String(message || '').trim();
        if (!text) {
            return;
        }
        if (notifyInOpenModal(text, type)) {
            return;
        }
        var region = getRegion();
        region.appendChild(buildAlert(text, type));
        region.lastElementChild.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }

    window.itmNotifyError = function (message) {
        notify(message, 'error');
    };

    window.itmNotifyAjaxError = function (message) {
        var text = String(message || '').trim();
        if (!text) {
            return;
        }
        if (notifyInOpenModal(text, 'error')) {
            return;
        }
        if (window.itmNotifyError) {
            window.itmNotifyError(text);
            return;
        }
        window.alert(text);
    };

    window.itmNotifySuccess = function (message) {
        notify(message, 'success');
    };

    window.itmNotify = function (message, type) {
        notify(message, type === 'success' ? 'success' : 'error');
    };
})();
