(function () {
    var shareQrTimer = null;

    function getCsrfToken() {
        return window.ITM_CSRF_TOKEN || window.CSRF_TOKEN || '';
    }

    window.itmCloseQrShareModal = function () {
        var modal = document.getElementById('itmShareQrModal');
        var backdrop = document.getElementById('itmShareQrBackdrop');
        if (modal) modal.style.display = 'none';
        if (backdrop) backdrop.style.display = 'none';
        if (shareQrTimer) {
            clearInterval(shareQrTimer);
            shareQrTimer = null;
        }
        var mount = document.getElementById('itmShareQrMount');
        if (mount) mount.innerHTML = '';
    };

    window.itmOpenQrShareModal = function (ajaxUrl, recordId, extraFields) {
        var formData = new FormData();
        formData.append('csrf_token', getCsrfToken());
        formData.append('id', recordId);
        if (extraFields && typeof extraFields === 'object') {
            Object.keys(extraFields).forEach(function (key) {
                formData.append(key, extraFields[key]);
            });
        }
        fetch(ajaxUrl, { method: 'POST', body: formData })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (!data || !data.ok) {
                    alert((data && data.error) || 'Could not create share link.');
                    return;
                }
                var codeEl = document.getElementById('itmShareQrCodeText');
                var urlEl = document.getElementById('itmShareQrJoinUrl');
                var mount = document.getElementById('itmShareQrMount');
                if (codeEl) codeEl.textContent = data.share_code || '';
                if (urlEl) urlEl.textContent = data.join_url || '';
                if (mount) {
                    mount.innerHTML = '';
                    if (window.QRCode && data.join_url) {
                        new QRCode(mount, { text: data.join_url, width: 200, height: 200 });
                    }
                }
                var expiryEl = document.getElementById('itmShareQrExpiry');
                if (shareQrTimer) clearInterval(shareQrTimer);
                var expires = new Date(String(data.expires_at || '').replace(' ', 'T'));
                function tickShareExpiry() {
                    if (!expiryEl) return;
                    var diff = expires - new Date();
                    if (diff <= 0) {
                        expiryEl.textContent = 'Session ended.';
                        if (shareQrTimer) clearInterval(shareQrTimer);
                        return;
                    }
                    var mins = Math.floor(diff / 60000);
                    var secs = Math.floor((diff % 60000) / 1000);
                    expiryEl.textContent = 'Session ends in ' + mins + ':' + String(secs).padStart(2, '0');
                }
                tickShareExpiry();
                shareQrTimer = setInterval(tickShareExpiry, 1000);
                var modal = document.getElementById('itmShareQrModal');
                var backdrop = document.getElementById('itmShareQrBackdrop');
                if (modal) modal.style.display = 'block';
                if (backdrop) backdrop.style.display = 'block';
            })
            .catch(function () { alert('Could not create share link.'); });
    };
})();
