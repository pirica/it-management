(function () {
    'use strict';

    var matrixTable = document.getElementById('rp-permission-matrix');
    if (!matrixTable) {
        return;
    }

    var readOnly = matrixTable.getAttribute('data-rp-readonly') === '1';
    var checkAllBtn = document.getElementById('rp-check-all');
    var uncheckAllBtn = document.getElementById('rp-uncheck-all');
    var saveBtn = document.getElementById('rp-save-permissions');
    var filterInput = document.getElementById('rp-matrix-filter');
    var statusEl = document.getElementById('rp-save-status');
    var addRoleForm = document.getElementById('rp-add-role-form');
    var editRoleForm = document.getElementById('rp-edit-role-form');

    function permissionInputs() {
        return Array.prototype.slice.call(document.querySelectorAll('.rp-perm-toggle:not(:disabled)'));
    }

    function setStatus(message, isError) {
        if (!statusEl) {
            return;
        }
        statusEl.textContent = message || '';
        statusEl.style.color = isError ? 'var(--danger, #c0392b)' : 'var(--text-secondary, #666)';
    }

    function collectMatrixPayload() {
        var rows = {};
        permissionInputs().forEach(function (input) {
            var moduleName = input.getAttribute('data-module-name') || '';
            var permKey = input.getAttribute('data-perm') || '';
            if (moduleName === '' || permKey === '') {
                return;
            }
            if (!rows[moduleName]) {
                rows[moduleName] = {
                    module_name: moduleName,
                    can_view: 0,
                    can_create: 0,
                    can_edit: 0,
                    can_delete: 0,
                    can_import: 0,
                    can_export: 0
                };
            }
            rows[moduleName][permKey] = input.checked ? 1 : 0;
        });
        return Object.keys(rows).map(function (key) {
            return rows[key];
        });
    }

    function postJson(action, extraFields) {
        var body = new URLSearchParams();
        body.set('ajax_action', action);
        body.set('csrf_token', window.ITM_RP_CSRF || '');
        Object.keys(extraFields || {}).forEach(function (key) {
            body.set(key, extraFields[key]);
        });

        return fetch(window.ITM_RP_ENDPOINT, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
            body: body.toString(),
            credentials: 'same-origin'
        }).then(function (response) {
            return response.json();
        });
    }

    if (checkAllBtn) {
        checkAllBtn.addEventListener('click', function () {
            permissionInputs().forEach(function (input) {
                input.checked = true;
                var indicator = input.parentNode ? input.parentNode.querySelector('.itm-check-indicator') : null;
                if (indicator) {
                    indicator.textContent = '✅';
                }
            });
            setStatus('');
        });
    }

    if (uncheckAllBtn) {
        uncheckAllBtn.addEventListener('click', function () {
            permissionInputs().forEach(function (input) {
                input.checked = false;
                var indicator = input.parentNode ? input.parentNode.querySelector('.itm-check-indicator') : null;
                if (indicator) {
                    indicator.textContent = '❌';
                }
            });
            setStatus('');
        });
    }

    document.querySelectorAll('.rp-perm-toggle').forEach(function (input) {
        input.addEventListener('change', function () {
            var indicator = input.parentNode ? input.parentNode.querySelector('.itm-check-indicator') : null;
            if (indicator) {
                indicator.textContent = input.checked ? '✅' : '❌';
            }
            setStatus('');
        });
    });

    if (saveBtn && !readOnly) {
        saveBtn.addEventListener('click', function () {
            var roleId = saveBtn.getAttribute('data-role-id') || '';
            if (!roleId) {
                setStatus('Select a role first.', true);
                return;
            }

            saveBtn.disabled = true;
            setStatus('Saving…');

            postJson('save_permissions', {
                role_id: roleId,
                permissions_json: JSON.stringify(collectMatrixPayload())
            }).then(function (payload) {
                if (!payload || !payload.ok) {
                    throw new Error((payload && payload.error) || 'Save failed');
                }
                setStatus('Permissions saved (' + (payload.updated || 0) + ' modules).');
            }).catch(function (error) {
                setStatus(error.message || 'Save failed', true);
            }).finally(function () {
                saveBtn.disabled = false;
            });
        });
    }

    if (filterInput) {
        filterInput.addEventListener('input', function () {
            var term = filterInput.value.trim().toLowerCase();
            matrixTable.querySelectorAll('tbody tr').forEach(function (row) {
                var haystack = (row.getAttribute('data-rp-search') || '').toLowerCase();
                row.style.display = !term || haystack.indexOf(term) !== -1 ? '' : 'none';
            });
        });
    }

    if (addRoleForm) {
        addRoleForm.addEventListener('submit', function (event) {
            event.preventDefault();
            var nameInput = addRoleForm.querySelector('[name="role_name"]');
            var submitBtn = addRoleForm.querySelector('[type="submit"]');
            if (!nameInput || !submitBtn) {
                return;
            }

            submitBtn.disabled = true;
            postJson('create_role', {
                role_name: (nameInput.value || '').trim()
            }).then(function (payload) {
                if (!payload || !payload.ok) {
                    throw new Error((payload && payload.error) || 'Could not create role');
                }
                window.location.href = window.ITM_RP_ENDPOINT + '?role_id=' + encodeURIComponent(String(payload.role_id || ''));
            }).catch(function (error) {
                alert(error.message || 'Could not create role');
            }).finally(function () {
                submitBtn.disabled = false;
            });
        });
    }

    if (editRoleForm) {
        editRoleForm.addEventListener('submit', function (event) {
            event.preventDefault();
            var roleId = editRoleForm.querySelector('[name="role_id"]');
            var nameInput = editRoleForm.querySelector('[name="role_name"]');
            var submitBtn = editRoleForm.querySelector('[type="submit"]');
            if (!roleId || !nameInput || !submitBtn) {
                return;
            }

            submitBtn.disabled = true;
            postJson('update_role', {
                role_id: roleId.value,
                role_name: (nameInput.value || '').trim(),
                sidebar_show: editRoleForm.querySelector('[name="sidebar_show"]') && editRoleForm.querySelector('[name="sidebar_show"]').checked ? '1' : '0'
            }).then(function (payload) {
                if (!payload || !payload.ok) {
                    throw new Error((payload && payload.error) || 'Could not update role');
                }
                window.location.reload();
            }).catch(function (error) {
                alert(error.message || 'Could not update role');
            }).finally(function () {
                submitBtn.disabled = false;
            });
        });
    }
})();
