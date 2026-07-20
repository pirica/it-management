(function () {
    function getCsrfToken() {
        return window.ITM_CSRF_TOKEN || window.CSRF_TOKEN || '';
    }

    function buildWhatsAppMessage(itemLabel, joinUrl, shareCode) {
        var label = (itemLabel || 'item').toString().trim() || 'item';
        var lines = ['View shared ' + label + ':', (joinUrl || '').toString().trim()];
        if (shareCode) {
            lines.push('Code: ' + shareCode);
        }
        lines.push('(Link expires in 30 minutes.)');
        return lines.join('\n');
    }

    function openWhatsAppWithMessage(message) {
        var url = 'https://wa.me/?text=' + encodeURIComponent(message);
        window.open(url, '_blank', 'noopener,noreferrer');
    }

    window.itmOpenWhatsAppShare = function (ajaxUrl, recordId, extraFields, itemLabel) {
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
                    alert((data && (data.error || data.message)) || 'Could not create share link.');
                    return;
                }
                var message = buildWhatsAppMessage(itemLabel, data.join_url, data.share_code);
                var openShare = function () {
                    openWhatsAppWithMessage(message);
                };
                if (typeof window.itmMaybeConfirmShareNoAttachments === 'function') {
                    window.itmMaybeConfirmShareNoAttachments(!!data.has_images, openShare);
                } else {
                    openShare();
                }
            })
            .catch(function () { alert('Could not create share link.'); });
    };
})();
