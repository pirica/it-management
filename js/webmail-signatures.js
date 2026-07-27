/**
 * Webmail signatures — modal create/edit/delete; Quill in save modal.
 */
(function () {
    var quillInstance = null;

    function readJsonScript(id) {
        var node = document.getElementById(id);
        if (!node || !node.textContent) {
            return {};
        }
        try {
            return JSON.parse(node.textContent);
        } catch (e) {
            return {};
        }
    }

    function destroyQuill() {
        if (!quillInstance) {
            return;
        }
        var mount = document.getElementById('webmail-signature-editor');
        if (mount) {
            mount.innerHTML = '';
        }
        quillInstance = null;
    }

    function ensureQuill(initialHtml) {
        if (typeof window.Quill !== 'function') {
            return null;
        }
        var mount = document.getElementById('webmail-signature-editor');
        if (!mount) {
            return null;
        }
        destroyQuill();
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
            placeholder: 'Signature content…',
        });
        if (initialHtml) {
            quillInstance.clipboard.dangerouslyPasteHTML(initialHtml);
        }
        return quillInstance;
    }

    function showModal(which) {
        var saveModal = document.getElementById('webmail-signature-save-modal');
        var deleteModal = document.getElementById('webmail-signature-delete-modal');
        var backdrop = document.getElementById('webmail-signature-backdrop');
        if (backdrop) {
            backdrop.style.display = 'block';
        }
        if (which === 'save' && saveModal) {
            saveModal.style.display = 'block';
        }
        if (which === 'delete' && deleteModal) {
            deleteModal.style.display = 'block';
        }
    }

    function hideModals() {
        var saveModal = document.getElementById('webmail-signature-save-modal');
        var deleteModal = document.getElementById('webmail-signature-delete-modal');
        var backdrop = document.getElementById('webmail-signature-backdrop');
        if (saveModal) {
            saveModal.style.display = 'none';
        }
        if (deleteModal) {
            deleteModal.style.display = 'none';
        }
        if (backdrop) {
            backdrop.style.display = 'none';
        }
        destroyQuill();
    }

    function openCreateModal() {
        var form = document.getElementById('webmail-signature-save-form');
        var title = document.getElementById('webmail-signature-save-title');
        var idInput = document.getElementById('webmail-signature-id');
        var nameInput = document.getElementById('webmail-signature-name');
        var submitBtn = document.getElementById('webmail-signature-save-submit');
        if (!form || !title || !idInput || !nameInput || !submitBtn) {
            return;
        }
        idInput.value = '';
        nameInput.value = '';
        title.setAttribute('title', 'Create signature');
        title.textContent = '➕';
        submitBtn.name = 'save_signature';
        submitBtn.value = '1';
        showModal('save');
        ensureQuill('');
        nameInput.focus();
    }

    function openEditModal(id, name, html) {
        var form = document.getElementById('webmail-signature-save-form');
        var title = document.getElementById('webmail-signature-save-title');
        var idInput = document.getElementById('webmail-signature-id');
        var nameInput = document.getElementById('webmail-signature-name');
        var submitBtn = document.getElementById('webmail-signature-save-submit');
        if (!form || !title || !idInput || !nameInput || !submitBtn) {
            return;
        }
        idInput.value = String(id);
        nameInput.value = name || '';
        title.setAttribute('title', 'Edit signature');
        title.textContent = '✏️';
        submitBtn.name = 'update_signature';
        submitBtn.value = '1';
        showModal('save');
        ensureQuill(html || '');
        nameInput.focus();
    }

    function openDeleteModal(id, name) {
        var idInput = document.getElementById('webmail-signature-delete-id');
        var label = document.getElementById('webmail-signature-delete-label');
        if (idInput) {
            idInput.value = String(id);
        }
        if (label) {
            label.textContent = name || '';
        }
        showModal('delete');
    }

    function bindSaveForm() {
        var form = document.getElementById('webmail-signature-save-form');
        if (!form) {
            return;
        }
        form.addEventListener('submit', function () {
            var hidden = document.getElementById('webmail-signature-html-hidden');
            if (!hidden || !quillInstance) {
                return;
            }
            var html = quillInstance.root.innerHTML;
            if (html === '<p><br></p>' || html === '<p></p>') {
                html = '';
            }
            hidden.value = html;
        });
    }

    function bindComposeSignatureSelect() {
        var select = document.getElementById('webmail-compose-signature-id');
        if (!select) {
            return;
        }
        var deleteBtn = document.getElementById('webmail-compose-signature-delete');
        var map = readJsonScript('webmail-signature-html-map');

        function syncDeleteVisibility() {
            if (!deleteBtn) {
                return;
            }
            var val = select.value;
            deleteBtn.style.display = val && val !== '' && val !== '__add_new__' ? '' : 'none';
        }

        select.addEventListener('change', function () {
            if (select.value === '__add_new__') {
                select.value = '';
                openCreateModal();
                syncDeleteVisibility();
                return;
            }
            syncDeleteVisibility();
        });

        if (deleteBtn) {
            deleteBtn.addEventListener('click', function () {
                var val = select.value;
                if (!val || val === '__add_new__') {
                    return;
                }
                var opt = select.options[select.selectedIndex];
                var label = opt ? opt.textContent : '';
                openDeleteModal(val, label);
            });
        }

        syncDeleteVisibility();
    }

    function init() {
        bindSaveForm();

        document.querySelectorAll('[data-webmail-signature-create="1"]').forEach(function (btn) {
            btn.addEventListener('click', function (e) {
                e.preventDefault();
                openCreateModal();
            });
        });

        document.querySelectorAll('[data-webmail-signature-edit]').forEach(function (btn) {
            btn.addEventListener('click', function (e) {
                e.preventDefault();
                var id = btn.getAttribute('data-webmail-signature-edit');
                var name = btn.getAttribute('data-webmail-signature-name') || '';
                var map = readJsonScript('webmail-signature-html-map');
                var html = map[String(id)] || '';
                openEditModal(id, name, html);
            });
        });

        document.querySelectorAll('[data-webmail-signature-delete]').forEach(function (btn) {
            btn.addEventListener('click', function (e) {
                e.preventDefault();
                var id = btn.getAttribute('data-webmail-signature-delete');
                var name = btn.getAttribute('data-webmail-signature-name') || '';
                openDeleteModal(id, name);
            });
        });

        document.querySelectorAll('.webmail-signature-modal-close').forEach(function (btn) {
            btn.addEventListener('click', function () {
                hideModals();
            });
        });

        var backdrop = document.getElementById('webmail-signature-backdrop');
        if (backdrop) {
            backdrop.addEventListener('click', hideModals);
        }

        bindComposeSignatureSelect();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
