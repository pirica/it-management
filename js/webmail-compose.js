/**
 * Webmail compose — Quill WYSIWYG; sync HTML to hidden field on submit; server preview modal.
 */
(function () {
    var quillInstance = null;

    function readInitialHtml() {
        var node = document.getElementById('webmail-body-initial');
        if (!node || !node.textContent) {
            return '';
        }
        try {
            return JSON.parse(node.textContent);
        } catch (e) {
            return '';
        }
    }

    function syncBodyHidden() {
        var hidden = document.getElementById('webmail-body-html');
        if (!hidden || !quillInstance) {
            return;
        }
        var html = quillInstance.root.innerHTML;
        if (html === '<p><br></p>' || html === '<p></p>') {
            html = '';
        }
        hidden.value = html;
    }

    function notifyError(message) {
        if (typeof window.itmNotifyError === 'function') {
            window.itmNotifyError(message);
            return;
        }
        window.alert(message);
    }

    function showPreviewModal() {
        var modal = document.getElementById('webmail-compose-preview-modal');
        var backdrop = document.getElementById('webmail-compose-preview-backdrop');
        if (modal) {
            modal.style.display = 'block';
        }
        if (backdrop) {
            backdrop.style.display = 'block';
        }
    }

    function hidePreviewModal() {
        var modal = document.getElementById('webmail-compose-preview-modal');
        var backdrop = document.getElementById('webmail-compose-preview-backdrop');
        if (modal) {
            modal.style.display = 'none';
        }
        if (backdrop) {
            backdrop.style.display = 'none';
        }
    }

    function bindPreviewModal() {
        document.addEventListener('click', function (e) {
            var target = e.target;
            if (!target || !target.closest) {
                return;
            }
            if (target.closest('.webmail-compose-preview-close')) {
                hidePreviewModal();
                return;
            }
            if (target.id === 'webmail-compose-preview-backdrop') {
                hidePreviewModal();
            }
        });
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') {
                hidePreviewModal();
            }
        });
    }

    function openPreview() {
        var form = document.getElementById('webmail-compose-form');
        if (!form) {
            return;
        }
        syncBodyHidden();
        var formData = new FormData(form);
        var subjectInput = document.getElementById('subject');
        if (subjectInput) {
            formData.set('subject', subjectInput.value);
        }
        fetch('compose.php?ajax_action=preview_message', {
            method: 'POST',
            body: formData,
        })
            .then(function (response) {
                return response.json().then(function (data) {
                    return { ok: response.ok, data: data };
                });
            })
            .then(function (result) {
                if (!result.data || !result.data.ok) {
                    notifyError((result.data && result.data.error) || 'Preview failed.');
                    return;
                }
                var data = result.data;
                var subjectEl = document.getElementById('webmail-compose-preview-subject');
                var fromEl = document.getElementById('webmail-compose-preview-from');
                var toEl = document.getElementById('webmail-compose-preview-to');
                var ccRow = document.getElementById('webmail-compose-preview-cc-row');
                var ccEl = document.getElementById('webmail-compose-preview-cc');
                var bodyEl = document.getElementById('webmail-compose-preview-body');
                var bodyWrap = document.getElementById('webmail-compose-preview-body-wrap');
                var subjectText = (data.subject || '').trim();
                if (subjectText === '' && subjectInput) {
                    subjectText = subjectInput.value.trim();
                }
                if (subjectText === '') {
                    subjectText = '(No subject)';
                }
                if (subjectEl) {
                    subjectEl.textContent = subjectText;
                }
                if (fromEl) {
                    fromEl.textContent = data.from || '';
                }
                if (toEl) {
                    toEl.textContent = data.to || '';
                }
                var cc = (data.cc || '').trim();
                if (ccRow && ccEl) {
                    if (cc !== '') {
                        ccEl.textContent = cc;
                        ccRow.style.display = '';
                    } else {
                        ccRow.style.display = 'none';
                        ccEl.textContent = '';
                    }
                }
                if (bodyEl) {
                    bodyEl.innerHTML = data.body_html || '';
                }
                if (bodyWrap) {
                    var emptyBody = !data.body_html || data.body_html === '<p></p>';
                    bodyWrap.classList.toggle('webmail-read-body-empty', emptyBody);
                }
                showPreviewModal();
            })
            .catch(function () {
                notifyError('Preview failed.');
            });
    }

    function init() {
        if (typeof window.Quill !== 'function') {
            return;
        }
        var form = document.getElementById('webmail-compose-form');
        var mount = document.getElementById('webmail-body-editor');
        var hidden = document.getElementById('webmail-body-html');
        if (!form || !mount || !hidden) {
            return;
        }

        quillInstance = new window.Quill(mount, {
            theme: 'snow',
            modules: {
                toolbar: [
                    ['bold', 'italic', 'underline', 'strike'],
                    [{ header: [1, 2, 3, false] }],
                    [{ list: 'ordered' }, { list: 'bullet' }],
                    ['link'],
                    ['clean'],
                ],
            },
            placeholder: 'Write your message…',
        });

        var initial = readInitialHtml();
        if (initial) {
            quillInstance.clipboard.dangerouslyPasteHTML(initial);
        }

        form.addEventListener('submit', function () {
            syncBodyHidden();
        });

        var previewBtn = document.getElementById('webmail-compose-preview');
        if (previewBtn) {
            previewBtn.addEventListener('click', function () {
                openPreview();
            });
        }

        bindPreviewModal();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
