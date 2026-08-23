<?php
/**
 * Shared Save view modal + button for list modules.
 *
 * Expects: $itmSavedReportsModuleSlug (tickets|equipment|expenses)
 *          $itmSavedReportsFilters (array — fallback when list form is absent)
 *          $itmSavedReportsColumns (array of column keys — default checked)
 */
if (!isset($itmSavedReportsModuleSlug) || !function_exists('itm_saved_reports_module_config')) {
    return;
}
$itmSavedReportsConfig = itm_saved_reports_module_config($itmSavedReportsModuleSlug);
if ($itmSavedReportsConfig === []) {
    return;
}
$itmSavedReportsColumnCatalog = $itmSavedReportsConfig['columns'] ?? [];
$itmSavedReportsDefaultColumns = $itmSavedReportsColumns ?? array_keys($itmSavedReportsColumnCatalog);
$itmSavedReportsDefaultColumns = array_values(array_intersect($itmSavedReportsDefaultColumns, array_keys($itmSavedReportsColumnCatalog)));
if ($itmSavedReportsDefaultColumns === []) {
    $itmSavedReportsDefaultColumns = array_keys($itmSavedReportsColumnCatalog);
}
$itmSavedReportsFiltersJson = json_encode($itmSavedReportsFilters ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
$itmSavedReportsCsrf = itm_get_csrf_token();
$itmSavedReportsApiUrl = '../../modules/saved_report_views/api.php';
?>
<button type="button" class="btn" id="itm-save-view-open" title="Save current filters as a report">💾</button>

<div id="itm-save-view-modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:9999;align-items:center;justify-content:center;overflow:auto;padding:24px 12px;">
    <div class="card" style="max-width:560px;width:92%;margin:auto;">
        <h2 style="margin-top:0;" title="Save view">💾</h2>
        <p>Save the current search, filters, and selected columns as a reusable report in Reports Hub.</p>
        <form id="itm-save-view-form">
            <div class="form-group">
                <label for="itm-save-view-name">Report name</label>
                <input type="text" id="itm-save-view-name" name="name" maxlength="200" required placeholder="e.g. Open tickets — IT">
            </div>
            <div class="form-group">
                <label for="itm-save-view-scope">Share with</label>
                <select id="itm-save-view-scope" name="shared_scope">
                    <option value="private">Only me (private)</option>
                    <option value="department">My department (read-only)</option>
                    <option value="company">Whole company (read-only)</option>
                </select>
            </div>
            <fieldset class="form-group" style="border:1px solid var(--border-color,#ddd);border-radius:6px;padding:12px;margin:0 0 12px;">
                <legend style="padding:0 6px;">Columns to include</legend>
                <div style="display:flex;flex-wrap:wrap;gap:8px 16px;">
                    <?php foreach ($itmSavedReportsColumnCatalog as $colKey => $colLabel): ?>
                        <label class="itm-checkbox-control" style="min-width:140px;">
                            <input type="checkbox" class="itm-save-view-column" name="itm_save_view_columns[]" value="<?php echo sanitize((string) $colKey); ?>"<?php echo in_array($colKey, $itmSavedReportsDefaultColumns, true) ? ' checked' : ''; ?>>
                            <span><?php echo sanitize((string) $colLabel); ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </fieldset>
            <div id="itm-save-view-flash" style="color:#c0392b;margin-bottom:8px;display:none;"></div>
            <div style="display:flex;gap:8px;justify-content:flex-end;">
                <button type="button" class="btn" id="itm-save-view-cancel" title="Cancel">🔙</button>
                <button type="submit" class="btn btn-primary" title="Save">💾</button>
            </div>
        </form>
    </div>
</div>
<script>
(function () {
    var modal = document.getElementById('itm-save-view-modal');
    var openBtn = document.getElementById('itm-save-view-open');
    var cancelBtn = document.getElementById('itm-save-view-cancel');
    var form = document.getElementById('itm-save-view-form');
    var flash = document.getElementById('itm-save-view-flash');
    var fallbackFilters = <?php echo json_encode($itmSavedReportsFilters ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
    if (!modal || !openBtn || !form) { return; }

    function showModal(show) {
        modal.style.display = show ? 'flex' : 'none';
        if (!show && flash) {
            flash.style.display = 'none';
            flash.textContent = '';
        }
    }

    function collectFiltersFromListForm() {
        var filters = {};
        var listForm = document.querySelector('[data-itm-saved-reports-list-form="1"]');
        if (listForm) {
            var fields = listForm.querySelectorAll('[data-itm-saved-report-filter="1"]');
            fields.forEach(function (el) {
                if (!el.name) { return; }
                if (el.type === 'checkbox') {
                    if (el.checked) {
                        filters[el.name] = el.value || '1';
                    }
                    return;
                }
                var value = (el.value || '').trim();
                if (value !== '') {
                    filters[el.name] = value;
                }
            });
        }
        Object.keys(fallbackFilters || {}).forEach(function (key) {
            if (filters[key] === undefined && fallbackFilters[key] !== null && fallbackFilters[key] !== '') {
                filters[key] = fallbackFilters[key];
            }
        });
        return filters;
    }

    function collectSelectedColumns() {
        var cols = [];
        document.querySelectorAll('.itm-save-view-column:checked').forEach(function (el) {
            if (el.value) { cols.push(el.value); }
        });
        return cols;
    }

    openBtn.addEventListener('click', function () { showModal(true); });
    if (cancelBtn) {
        cancelBtn.addEventListener('click', function () { showModal(false); });
    }
    modal.addEventListener('click', function (e) {
        if (e.target === modal) { showModal(false); }
    });

    form.addEventListener('submit', function (e) {
        e.preventDefault();
        var nameInput = document.getElementById('itm-save-view-name');
        var scopeSelect = document.getElementById('itm-save-view-scope');
        var columns = collectSelectedColumns();
        if (!columns.length) {
            if (flash) {
                flash.textContent = 'Select at least one column.';
                flash.style.display = 'block';
            }
            return;
        }
        var body = new FormData();
        body.append('action', 'save');
        body.append('csrf_token', <?php echo json_encode($itmSavedReportsCsrf); ?>);
        body.append('module_slug', <?php echo json_encode($itmSavedReportsModuleSlug); ?>);
        body.append('name', nameInput ? nameInput.value : '');
        body.append('shared_scope', scopeSelect ? scopeSelect.value : 'private');
        body.append('filters_json', JSON.stringify(collectFiltersFromListForm()));
        body.append('columns_json', JSON.stringify(columns));

        fetch(<?php echo json_encode($itmSavedReportsApiUrl); ?>, {
            method: 'POST',
            credentials: 'same-origin',
            body: body
        }).then(function (r) { return r.json(); }).then(function (data) {
            if (data && data.ok) {
                showModal(false);
                if (nameInput) { nameInput.value = ''; }
                alert('Saved view created. Open Reports Hub → My reports to run or schedule it.');
                return;
            }
            if (flash) {
                flash.textContent = (data && data.error) ? data.error : 'Could not save view.';
                flash.style.display = 'block';
            }
        }).catch(function () {
            if (flash) {
                flash.textContent = 'Network error while saving view.';
                flash.style.display = 'block';
            }
        });
    });
})();
</script>
