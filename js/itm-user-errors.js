/**
 * Themed in-page notifications for AJAX/modal failures (replaces raw window.alert for errors).
 */
(function () {
    'use strict';

    var messageModalBound = false;

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

    function isForbiddenMessage(message) {
        var text = String(message || '').trim();
        if (!text) {
            return false;
        }
        return /^Forbidden:/i.test(text)
            || /insufficient module permissions/i.test(text)
            || /administrator access required/i.test(text)
            || /^Access [Dd]enied\.?$/i.test(text);
    }

    function ensureMessageModal() {
        var overlay = document.getElementById('itmMessageModalOverlay');
        if (overlay) {
            return overlay;
        }

        overlay = document.createElement('div');
        overlay.id = 'itmMessageModalOverlay';
        overlay.className = 'itm-message-modal-overlay';
        overlay.setAttribute('role', 'presentation');
        overlay.hidden = true;
        overlay.innerHTML =
            '<div class="itm-message-modal-card" role="dialog" aria-modal="true" aria-labelledby="itmMessageModalTitle">' +
                '<div class="itm-message-modal-header">' +
                    '<h3 id="itmMessageModalTitle" class="itm-message-modal-title" title="Permission denied">⚠️</h3>' +
                    '<button type="button" class="itm-message-modal-close" title="Close" aria-label="Close">&times;</button>' +
                '</div>' +
                '<div class="itm-message-modal-body">' +
                    '<p class="itm-message-modal-message"></p>' +
                '</div>' +
                '<div class="itm-message-modal-actions">' +
                    '<button type="button" class="btn btn-primary itm-message-modal-ok" title="OK">OK</button>' +
                '</div>' +
            '</div>';

        document.body.appendChild(overlay);

        function closeModal() {
            overlay.hidden = true;
        }

        overlay.querySelector('.itm-message-modal-close').addEventListener('click', closeModal);
        overlay.querySelector('.itm-message-modal-ok').addEventListener('click', closeModal);
        overlay.addEventListener('click', function (event) {
            if (event.target === overlay) {
                closeModal();
            }
        });

        if (!messageModalBound) {
            messageModalBound = true;
            document.addEventListener('keydown', function (event) {
                if (event.key === 'Escape' && overlay && !overlay.hidden) {
                    closeModal();
                }
            });
        }

        return overlay;
    }

    function getOpenIdfModalBackdrop() {
        var backdrops = document.querySelectorAll('.idf-modal-backdrop');
        for (var i = backdrops.length - 1; i >= 0; i--) {
            if (backdrops[i].style.display === 'flex') {
                return backdrops[i];
            }
        }
        return null;
    }

    function getOpenAddOptionOverlay() {
        var overlays = document.querySelectorAll('.itm-add-option-overlay');
        for (var i = overlays.length - 1; i >= 0; i--) {
            if (overlays[i].parentNode) {
                return overlays[i];
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
        var header = modal.querySelector('.idf-modal-header') || modal.querySelector('.itm-add-option-heading');
        if (header && header.nextSibling) {
            modal.insertBefore(region, header.nextSibling);
        } else {
            modal.insertBefore(region, modal.firstChild);
        }
        return region;
    }

    function notifyInOpenModal(message, type) {
        var backdrop = getOpenIdfModalBackdrop();
        var modal = backdrop ? backdrop.querySelector('.idf-modal') : null;
        if (!modal) {
            var addOverlay = getOpenAddOptionOverlay();
            modal = addOverlay ? addOverlay.querySelector('.itm-add-option-card') : null;
        }
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

    function sentenceCaseMessage(message) {
        var text = String(message || '').trim();
        if (!text) {
            return '';
        }
        text = text.replace(/^Forbidden:\s*/i, '').trim() || text;
        return text.charAt(0).toUpperCase() + text.slice(1);
    }

    window.itmShowForbiddenModal = function (message) {
        var text = sentenceCaseMessage(message);
        if (!text) {
            return;
        }
        if (notifyInOpenModal(text, 'error')) {
            return;
        }
        var overlay = ensureMessageModal();
        var messageNode = overlay.querySelector('.itm-message-modal-message');
        if (messageNode) {
            messageNode.textContent = text;
        }
        overlay.hidden = false;
        var okButton = overlay.querySelector('.itm-message-modal-ok');
        if (okButton) {
            okButton.focus();
        }
    };

    window.itmNotifyError = function (message) {
        var text = String(message || '').trim();
        if (!text) {
            return;
        }
        if (isForbiddenMessage(text) && window.itmShowForbiddenModal) {
            window.itmShowForbiddenModal(text);
            return;
        }
        notify(text, 'error');
    };

    window.itmNotifyAjaxError = function (message) {
        var text = String(message || '').trim();
        if (!text) {
            return;
        }
        if (isForbiddenMessage(text) && window.itmShowForbiddenModal) {
            window.itmShowForbiddenModal(text);
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

    function showForbiddenFlash() {
        if (window.ITM_FORBIDDEN_FLASH) {
            window.itmShowForbiddenModal(window.ITM_FORBIDDEN_FLASH);
            window.ITM_FORBIDDEN_FLASH = null;
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', showForbiddenFlash);
    } else {
        showForbiddenFlash();
    }
})();
