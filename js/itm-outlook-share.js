(function () {
    function getCsrfToken() {
        return window.ITM_CSRF_TOKEN || window.CSRF_TOKEN || '';
    }

    function buildOutlookSubject(itemLabel) {
        var label = (itemLabel || 'item').toString().trim() || 'item';
        return 'Shared ' + label;
    }

    function buildOutlookBody(itemLabel, joinUrl, shareCode) {
        var label = (itemLabel || 'item').toString().trim() || 'item';
        var lines = ['View shared ' + label + ':', (joinUrl || '').toString().trim()];
        if (shareCode) {
            lines.push('Code: ' + shareCode);
        }
        lines.push('(Link expires in 30 minutes.)');
        return lines.join('\n');
    }

    function openOutlookCompose(subject, body) {
        var mailtoUrl = 'mailto:?subject=' + encodeURIComponent(subject) + '&body=' + encodeURIComponent(body);
        window.location.href = mailtoUrl;
    }

    window.itmOpenOutlookShare = function (ajaxUrl, recordId, extraFields, itemLabel) {
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
                var subject = buildOutlookSubject(itemLabel);
                var body = buildOutlookBody(itemLabel, data.join_url, data.share_code);
                var openShare = function () {
                    openOutlookCompose(subject, body);
                };
                if (typeof window.itmMaybeConfirmShareNoAttachments === 'function') {
                    var promptOptions = data.share_prompt_body ? { bodyText: data.share_prompt_body } : undefined;
                    window.itmMaybeConfirmShareNoAttachments(!!data.has_images, openShare, promptOptions);
                } else {
                    openShare();
                }
            })
            .catch(function () { alert('Could not create share link.'); });
    };
})();
