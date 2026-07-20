(function () {
    var STORAGE_KEY = 'itm_share_no_attachments_dismissed';

    function ensureModal() {
        if (document.getElementById('itmShareNoAttachModal')) {
            return;
        }

        var backdrop = document.createElement('div');
        backdrop.id = 'itmShareNoAttachBackdrop';
        backdrop.style.cssText = 'display:none;position:fixed;inset:0;background:#000;opacity:.5;z-index:1060;';

        var modal = document.createElement('div');
        modal.id = 'itmShareNoAttachModal';
        modal.setAttribute('role', 'dialog');
        modal.style.cssText = 'display:none;position:fixed;inset:0;z-index:1070;overflow:auto;';
        modal.innerHTML = ''
            + '<div style="position:relative;width:auto;margin:1.75rem auto;max-width:520px;padding:0 16px;">'
            + '  <div style="background:var(--bg-primary);border:1px solid var(--border);border-radius:8px;padding:24px;">'
            + '    <h5 style="margin:0 0 12px;">Share link only</h5>'
            + '    <p style="margin:0 0 16px;color:var(--text-secondary);">WhatsApp and email cannot attach files. Only the join link and 6-digit code will be sent. Images can be viewed on the join page.</p>'
            + '    <label style="display:flex;align-items:center;gap:8px;margin-bottom:20px;cursor:pointer;">'
            + '      <input type="checkbox" id="itmShareNoAttachDismiss">'
            + '      <span>Don\u2019t show again</span>'
            + '    </label>'
            + '    <div style="display:flex;justify-content:flex-end;gap:8px;">'
            + '      <button type="button" class="btn" id="itmShareNoAttachCancel" title="Cancel">🔙</button>'
            + '      <button type="button" class="btn btn-primary" id="itmShareNoAttachContinue">Continue</button>'
            + '    </div>'
            + '  </div>'
            + '</div>';

        document.body.appendChild(backdrop);
        document.body.appendChild(modal);
    }

    function closeModal() {
        var modal = document.getElementById('itmShareNoAttachModal');
        var backdrop = document.getElementById('itmShareNoAttachBackdrop');
        if (modal) modal.style.display = 'none';
        if (backdrop) backdrop.style.display = 'none';
    }

    window.itmMaybeConfirmShareNoAttachments = function (hasImages, onContinue) {
        if (typeof onContinue !== 'function') {
            return;
        }
        if (!hasImages || window.localStorage.getItem(STORAGE_KEY) === '1') {
            onContinue();
            return;
        }

        ensureModal();
        var modal = document.getElementById('itmShareNoAttachModal');
        var backdrop = document.getElementById('itmShareNoAttachBackdrop');
        var dismiss = document.getElementById('itmShareNoAttachDismiss');
        var cancelBtn = document.getElementById('itmShareNoAttachCancel');
        var continueBtn = document.getElementById('itmShareNoAttachContinue');
        if (!modal || !backdrop || !dismiss || !cancelBtn || !continueBtn) {
            onContinue();
            return;
        }

        dismiss.checked = false;

        function handleCancel() {
            closeModal();
            cancelBtn.removeEventListener('click', handleCancel);
            continueBtn.removeEventListener('click', handleContinue);
        }

        function handleContinue() {
            if (dismiss.checked) {
                try {
                    window.localStorage.setItem(STORAGE_KEY, '1');
                } catch (e) {
                    // Private browsing may block storage; continue without persisting.
                }
            }
            closeModal();
            cancelBtn.removeEventListener('click', handleCancel);
            continueBtn.removeEventListener('click', handleContinue);
            onContinue();
        }

        cancelBtn.addEventListener('click', handleCancel);
        continueBtn.addEventListener('click', handleContinue);
        backdrop.style.display = 'block';
        modal.style.display = 'block';
    };
})();
