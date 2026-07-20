(function () {
    var STORAGE_KEY = 'itm_share_no_attachments_dismissed';
    var DEFAULT_BODY = 'WhatsApp and email cannot attach files. Only the join link and 6-digit code will be sent. Images can be viewed on the join page.';

    function ensureStyles() {
        if (document.getElementById('itmShareNoAttachStyles')) {
            return;
        }
        var style = document.createElement('style');
        style.id = 'itmShareNoAttachStyles';
        style.textContent = ''
            + '#itmShareNoAttachBackdrop{display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:1060;}'
            + '#itmShareNoAttachBackdrop.is-open{display:block;}'
            + '#itmShareNoAttachModal{display:none;position:fixed;inset:0;z-index:1070;padding:16px;align-items:center;justify-content:center;}'
            + '#itmShareNoAttachModal.is-open{display:flex;}'
            + '.itm-share-no-attach-panel{background:var(--bg-primary);border:1px solid var(--border);border-radius:8px;padding:24px;width:100%;max-width:480px;box-shadow:0 8px 24px rgba(0,0,0,.18);}'
            + '.itm-share-no-attach-title{margin:0 0 12px;font-size:1.1rem;font-weight:600;}'
            + '.itm-share-no-attach-body{margin:0 0 16px;color:var(--text-secondary);line-height:1.5;}'
            + '.itm-share-no-attach-dismiss{display:flex;align-items:center;gap:10px;margin:0 0 20px;cursor:pointer;user-select:none;}'
            + '.itm-share-no-attach-dismiss input[type=checkbox]{width:16px;height:16px;margin:0;flex:0 0 auto;}'
            + '.itm-share-no-attach-dismiss span{line-height:1.4;}'
            + '.itm-share-no-attach-actions{display:flex;justify-content:flex-end;align-items:center;gap:8px;flex-wrap:nowrap;}';
        document.head.appendChild(style);
    }

    function ensureModal() {
        ensureStyles();
        if (document.getElementById('itmShareNoAttachModal')) {
            return;
        }

        var backdrop = document.createElement('div');
        backdrop.id = 'itmShareNoAttachBackdrop';

        var modal = document.createElement('div');
        modal.id = 'itmShareNoAttachModal';
        modal.setAttribute('role', 'dialog');
        modal.setAttribute('aria-modal', 'true');
        modal.setAttribute('aria-labelledby', 'itmShareNoAttachTitle');
        modal.innerHTML = ''
            + '<div class="itm-share-no-attach-panel">'
            + '  <h5 class="itm-share-no-attach-title" id="itmShareNoAttachTitle">Share link only</h5>'
            + '  <p class="itm-share-no-attach-body" id="itmShareNoAttachBody"></p>'
            + '  <label class="itm-share-no-attach-dismiss" for="itmShareNoAttachDismiss">'
            + '    <input type="checkbox" id="itmShareNoAttachDismiss">'
            + '    <span>Don\u2019t show again</span>'
            + '  </label>'
            + '  <div class="itm-share-no-attach-actions">'
            + '    <button type="button" class="btn" id="itmShareNoAttachCancel" title="Cancel">❌</button>'
            + '    <button type="button" class="btn btn-primary" id="itmShareNoAttachContinue">Continue</button>'
            + '  </div>'
            + '</div>';

        document.body.appendChild(backdrop);
        document.body.appendChild(modal);
    }

    function setBodyText(text) {
        var body = document.getElementById('itmShareNoAttachBody');
        if (body) {
            body.textContent = text || DEFAULT_BODY;
        }
    }

    function openModal() {
        var modal = document.getElementById('itmShareNoAttachModal');
        var backdrop = document.getElementById('itmShareNoAttachBackdrop');
        if (modal) {
            modal.classList.add('is-open');
        }
        if (backdrop) {
            backdrop.classList.add('is-open');
        }
    }

    function closeModal() {
        var modal = document.getElementById('itmShareNoAttachModal');
        var backdrop = document.getElementById('itmShareNoAttachBackdrop');
        if (modal) {
            modal.classList.remove('is-open');
        }
        if (backdrop) {
            backdrop.classList.remove('is-open');
        }
    }

    window.itmMaybeConfirmShareNoAttachments = function (shouldPrompt, onContinue, options) {
        if (typeof onContinue !== 'function') {
            return;
        }
        if (!shouldPrompt || window.localStorage.getItem(STORAGE_KEY) === '1') {
            onContinue();
            return;
        }

        ensureModal();
        setBodyText((options && options.bodyText) ? options.bodyText : DEFAULT_BODY);

        var modal = document.getElementById('itmShareNoAttachModal');
        var backdrop = document.getElementById('itmShareNoAttachBackdrop');
        var dismiss = document.getElementById('itmShareNoAttachDismiss');
        var cancelBtn = document.getElementById('itmShareNoAttachCancel');
        var continueBtn = document.getElementById('itmShareNoAttachContinue');
        if (!modal || !dismiss || !cancelBtn || !continueBtn) {
            onContinue();
            return;
        }

        dismiss.checked = false;

        function cleanup() {
            cancelBtn.removeEventListener('click', handleCancel);
            continueBtn.removeEventListener('click', handleContinue);
            if (backdrop) {
                backdrop.removeEventListener('click', handleCancel);
            }
        }

        function handleCancel() {
            cleanup();
            closeModal();
        }

        function handleContinue() {
            if (dismiss.checked) {
                try {
                    window.localStorage.setItem(STORAGE_KEY, '1');
                } catch (e) {
                    // Private browsing may block storage; continue without persisting.
                }
            }
            cleanup();
            closeModal();
            onContinue();
        }

        cancelBtn.addEventListener('click', handleCancel);
        continueBtn.addEventListener('click', handleContinue);
        if (backdrop) {
            backdrop.addEventListener('click', handleCancel);
        }
        openModal();
        continueBtn.focus();
    };
})();
