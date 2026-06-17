(function () {
    'use strict';

    var selectionMode = false;
    var selectAllBtn = document.getElementById('cma-select-all');
    var cancelSelectBtn = document.getElementById('cma-cancel-select');
    var unselectAllBtn = document.getElementById('cma-unselect-all');
    var enableSelectedBtn = document.getElementById('cma-enable-selected');
    var disableSelectedBtn = document.getElementById('cma-disable-selected');
    var filterInput = document.getElementById('cma-matrix-filter');
    var matrixTable = document.getElementById('cma-access-matrix');

    if (!matrixTable) {
        return;
    }

    function accessToggles() {
        return Array.prototype.slice.call(document.querySelectorAll('.cma-access-toggle:not(:disabled)'));
    }

    function selectionCheckboxes() {
        return Array.prototype.slice.call(document.querySelectorAll('.cma-selection-checkbox'));
    }

    function updateSelectionUi() {
        if (selectAllBtn) {
            selectAllBtn.style.display = selectionMode ? 'none' : '';
        }
        [cancelSelectBtn, unselectAllBtn, enableSelectedBtn, disableSelectedBtn].forEach(function (btn) {
            if (btn) {
                btn.style.display = selectionMode ? '' : 'none';
            }
        });
        document.querySelectorAll('.cma-selection-cell').forEach(function (cell) {
            cell.style.display = selectionMode ? '' : 'none';
        });
    }

    function ensureSelectionColumn() {
        if (document.querySelector('.cma-selection-header')) {
            return;
        }
        var headerRow = matrixTable.querySelector('thead tr');
        if (!headerRow) {
            return;
        }
        var th = document.createElement('th');
        th.className = 'cma-selection-header';
        th.style.display = 'none';
        th.style.width = '36px';
        th.textContent = 'Select';
        headerRow.insertBefore(th, headerRow.firstChild);

        matrixTable.querySelectorAll('tbody tr').forEach(function (row) {
            var td = document.createElement('td');
            td.className = 'cma-selection-cell';
            td.style.display = 'none';
            var moduleId = '';
            var toggle = row.querySelector('.cma-access-toggle');
            if (toggle) {
                moduleId = toggle.getAttribute('data-module-id') || '';
            }
            td.innerHTML = '<input type="checkbox" class="cma-selection-checkbox" data-module-id="' + moduleId + '">';
            row.insertBefore(td, row.firstChild);
        });
    }

    function postToggle(companyId, moduleId, enabled, checkbox) {
        var body = new URLSearchParams();
        body.set('ajax_action', 'toggle_access');
        body.set('csrf_token', window.ITM_CMA_CSRF || '');
        body.set('company_id', String(companyId));
        body.set('module_id', String(moduleId));
        body.set('enabled', enabled ? '1' : '0');

        return fetch(window.ITM_CMA_ENDPOINT, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
            body: body.toString(),
            credentials: 'same-origin'
        }).then(function (response) {
            return response.json();
        }).then(function (payload) {
            if (!payload || !payload.ok) {
                throw new Error((payload && payload.error) || 'Toggle failed');
            }
            if (checkbox) {
                var indicator = checkbox.parentNode ? checkbox.parentNode.querySelector('span[aria-hidden="true"]') : null;
                if (indicator) {
                    indicator.textContent = payload.enabled ? '✓' : '✗';
                }
            }
            return payload;
        });
    }

    function bulkToggle(enabled) {
        var pairs = [];
        matrixTable.querySelectorAll('tbody tr').forEach(function (row) {
            if (row.style.display === 'none') {
                return;
            }
            var selection = row.querySelector('.cma-selection-checkbox');
            if (!selection || !selection.checked) {
                return;
            }
            row.querySelectorAll('.cma-access-toggle:not(:disabled)').forEach(function (toggle) {
                pairs.push({
                    company_id: toggle.getAttribute('data-company-id'),
                    module_id: toggle.getAttribute('data-module-id')
                });
            });
        });

        if (!pairs.length) {
            return;
        }

        var body = new URLSearchParams();
        body.set('ajax_action', 'bulk_toggle_access');
        body.set('csrf_token', window.ITM_CMA_CSRF || '');
        body.set('enabled', enabled ? '1' : '0');
        body.set('pairs_json', JSON.stringify(pairs));

        fetch(window.ITM_CMA_ENDPOINT, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
            body: body.toString(),
            credentials: 'same-origin'
        }).then(function (response) {
            return response.json();
        }).then(function (payload) {
            if (!payload || !payload.ok) {
                throw new Error((payload && payload.error) || 'Bulk toggle failed');
            }
            pairs.forEach(function (pair) {
                var selector = '.cma-access-toggle[data-company-id="' + pair.company_id + '"][data-module-id="' + pair.module_id + '"]';
                var toggle = matrixTable.querySelector(selector);
                if (!toggle) {
                    return;
                }
                toggle.checked = !!enabled;
                var indicator = toggle.parentNode ? toggle.parentNode.querySelector('span[aria-hidden="true"]') : null;
                if (indicator) {
                    indicator.textContent = enabled ? '✓' : '✗';
                }
            });
            exitSelectionMode();
        }).catch(function (error) {
            alert(error.message || 'Bulk toggle failed');
        });
    }

    function exitSelectionMode() {
        selectionMode = false;
        selectionCheckboxes().forEach(function (checkbox) {
            checkbox.checked = false;
        });
        updateSelectionUi();
    }

    accessToggles().forEach(function (toggle) {
        toggle.addEventListener('change', function () {
            if (selectionMode) {
                return;
            }
            var companyId = toggle.getAttribute('data-company-id');
            var moduleId = toggle.getAttribute('data-module-id');
            var desired = toggle.checked;
            toggle.disabled = true;
            postToggle(companyId, moduleId, desired, toggle).catch(function () {
                toggle.checked = !desired;
                var indicator = toggle.parentNode ? toggle.parentNode.querySelector('span[aria-hidden="true"]') : null;
                if (indicator) {
                    indicator.textContent = toggle.checked ? '✓' : '✗';
                }
                alert('Could not update module access.');
            }).finally(function () {
                toggle.disabled = false;
            });
        });
    });

    if (selectAllBtn) {
        selectAllBtn.addEventListener('click', function () {
            ensureSelectionColumn();
            selectionMode = true;
            selectionCheckboxes().forEach(function (checkbox) {
                checkbox.checked = true;
            });
            updateSelectionUi();
        });
    }

    if (cancelSelectBtn) {
        cancelSelectBtn.addEventListener('click', exitSelectionMode);
    }

    if (unselectAllBtn) {
        unselectAllBtn.addEventListener('click', function () {
            selectionCheckboxes().forEach(function (checkbox) {
                checkbox.checked = false;
            });
        });
    }

    if (enableSelectedBtn) {
        enableSelectedBtn.addEventListener('click', function () {
            bulkToggle(true);
        });
    }

    if (disableSelectedBtn) {
        disableSelectedBtn.addEventListener('click', function () {
            bulkToggle(false);
        });
    }

    if (filterInput) {
        filterInput.addEventListener('input', function () {
            var term = filterInput.value.trim().toLowerCase();
            matrixTable.querySelectorAll('tbody tr').forEach(function (row) {
                var haystack = (row.getAttribute('data-cma-search') || '').toLowerCase();
                row.style.display = !term || haystack.indexOf(term) !== -1 ? '' : 'none';
            });
        });
    }
})();
