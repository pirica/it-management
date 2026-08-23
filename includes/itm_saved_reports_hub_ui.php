<?php
/**
 * Reports Hub UI: edit saved view modal, share/schedule helpers, owner schedule list.
 *
 * Expects: $conn, $company_id, $current_user_id, $mySavedReports, $mySavedViewSchedules (array)
 */
if (!isset($conn) || !function_exists('itm_saved_reports_module_config')) {
    return;
}

$hubCompanyId = (int) ($company_id ?? 0);
$hubEmployeeId = (int) ($current_user_id ?? 0);
$hubCsrf = itm_get_csrf_token();
$hubApiUrl = '../saved_report_views/api.php';
$hubExportBase = '../saved_report_views/export.php';
$mySavedViewSchedules = is_array($mySavedViewSchedules ?? null) ? $mySavedViewSchedules : [];

$hubTicketStatuses = [];
$hubTicketPriorities = [];
$hubEmployees = [];
$hubPaidStatuses = [];
$hubSuppliers = [];
$hubEquipmentTypes = [];

if ($hubCompanyId > 0) {
    $tsRes = mysqli_query($conn, 'SELECT id, name FROM ticket_statuses WHERE company_id = ' . $hubCompanyId . ' AND deleted_at IS NULL ORDER BY name');
    while ($tsRes && ($r = mysqli_fetch_assoc($tsRes))) {
        $hubTicketStatuses[] = $r;
    }
    $tpRes = mysqli_query($conn, 'SELECT id, name FROM ticket_priorities WHERE company_id = ' . $hubCompanyId . ' AND deleted_at IS NULL ORDER BY name');
    while ($tpRes && ($r = mysqli_fetch_assoc($tpRes))) {
        $hubTicketPriorities[] = $r;
    }
    $empStmt = mysqli_prepare(
        $conn,
        'SELECT id, first_name, last_name, username FROM employees WHERE company_id = ? AND deleted_at IS NULL ORDER BY first_name, last_name, username'
    );
    if ($empStmt) {
        mysqli_stmt_bind_param($empStmt, 'i', $hubCompanyId);
        mysqli_stmt_execute($empStmt);
        $empRes = mysqli_stmt_get_result($empStmt);
        while ($empRes && ($r = mysqli_fetch_assoc($empRes))) {
            $label = trim((string) ($r['first_name'] ?? '') . ' ' . (string) ($r['last_name'] ?? ''));
            if ($label === '') {
                $label = (string) ($r['username'] ?? '');
            }
            $hubEmployees[] = ['id' => (int) $r['id'], 'label' => $label];
        }
        mysqli_stmt_close($empStmt);
    }
    $psRes = mysqli_query($conn, 'SELECT id, name FROM paid_statuses WHERE company_id = ' . $hubCompanyId . ' AND deleted_at IS NULL ORDER BY name');
    while ($psRes && ($r = mysqli_fetch_assoc($psRes))) {
        $hubPaidStatuses[] = $r;
    }
    $supRes = mysqli_query($conn, 'SELECT id, name FROM suppliers WHERE company_id = ' . $hubCompanyId . ' AND deleted_at IS NULL ORDER BY name LIMIT 500');
    while ($supRes && ($r = mysqli_fetch_assoc($supRes))) {
        $hubSuppliers[] = $r;
    }
    $etRes = mysqli_query($conn, 'SELECT DISTINCT name FROM equipment_types WHERE company_id = ' . $hubCompanyId . ' AND deleted_at IS NULL AND name IS NOT NULL AND name <> \'\' ORDER BY name');
    while ($etRes && ($r = mysqli_fetch_assoc($etRes))) {
        $hubEquipmentTypes[] = (string) ($r['name'] ?? '');
    }
}

$hubModuleConfigs = [];
foreach (itm_saved_reports_supported_modules() as $hubSlug) {
    $hubModuleConfigs[$hubSlug] = itm_saved_reports_module_config($hubSlug);
}

$hubOwnerScheduleCatalog = [];
foreach ($mySavedReports as $hubView) {
    if ((int) ($hubView['employee_id'] ?? 0) !== $hubEmployeeId) {
        continue;
    }
    $hubVid = (int) ($hubView['id'] ?? 0);
    if ($hubVid <= 0) {
        continue;
    }
    $hubSlugKey = itm_saved_reports_scheduled_slug($hubVid);
    $hubModLabel = $hubModuleConfigs[(string) $hubView['module_slug']]['label'] ?? $hubView['module_slug'];
    $hubOwnerScheduleCatalog[$hubSlugKey] = 'Saved: ' . (string) $hubView['name'] . ' (' . $hubModLabel . ')';
}
?>
<div id="itm-edit-saved-view-modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:10000;align-items:flex-start;justify-content:center;overflow:auto;padding:24px 12px;">
    <div class="card" style="max-width:640px;width:92%;margin:12vh auto 24px;">
        <h2 style="margin-top:0;" title="Edit saved view">✏️</h2>
        <form id="itm-edit-saved-view-form">
            <input type="hidden" id="itm-edit-view-id" value="0">
            <input type="hidden" id="itm-edit-module-slug" value="">
            <div class="form-group">
                <label for="itm-edit-view-name">Report name</label>
                <input type="text" id="itm-edit-view-name" maxlength="200" required>
            </div>
            <div class="form-group">
                <label for="itm-edit-view-scope">Share with</label>
                <select id="itm-edit-view-scope">
                    <option value="private">Only me (private)</option>
                    <option value="department">My department (read-only)</option>
                    <option value="company">Whole company (read-only)</option>
                </select>
            </div>
            <fieldset class="form-group" id="itm-edit-filter-fieldset" style="border:1px solid var(--border-color,#ddd);border-radius:6px;padding:12px;">
                <legend style="padding:0 6px;">Filters</legend>
                <div id="itm-edit-filter-fields"></div>
            </fieldset>
            <fieldset class="form-group" id="itm-edit-columns-fieldset" style="border:1px solid var(--border-color,#ddd);border-radius:6px;padding:12px;">
                <legend style="padding:0 6px;">Columns</legend>
                <div id="itm-edit-column-fields" style="display:flex;flex-wrap:wrap;gap:8px 16px;"></div>
            </fieldset>
            <div id="itm-edit-view-flash" style="color:#c0392b;margin-bottom:8px;display:none;"></div>
            <div style="display:flex;gap:8px;justify-content:flex-end;">
                <button type="button" class="btn" id="itm-edit-view-cancel" title="Cancel">🔙</button>
                <button type="submit" class="btn btn-primary" title="Save">💾</button>
            </div>
        </form>
    </div>
</div>

<div id="schedule-saved-view-modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:9999;align-items:center;justify-content:center;">
    <div class="card" style="max-width:520px;width:92%;margin:auto;margin-top:10vh;">
        <h2 style="margin-top:0;">Schedule saved view</h2>
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo sanitize($hubCsrf); ?>">
            <input type="hidden" name="scheduled_report_action" value="save_scheduled_report">
            <input type="hidden" name="scheduled_report_id" id="owner-schedule-report-id" value="0">
            <div class="form-group">
                <label>Saved view</label>
                <select name="report_slug" id="owner-schedule-report-slug" required>
                    <?php foreach ($hubOwnerScheduleCatalog as $slug => $label): ?>
                        <option value="<?php echo sanitize($slug); ?>"><?php echo sanitize($label); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Cron (minute hour dom month dow)</label>
                <input type="text" name="schedule_cron" id="owner-schedule-report-cron" placeholder="0 8 * * 1" required>
            </div>
            <div class="form-group">
                <label>Recipients (comma-separated emails)</label>
                <input type="text" name="recipients" id="owner-schedule-report-recipients" required>
            </div>
            <div class="form-group">
                <label>Format</label>
                <select name="format" id="owner-schedule-report-format">
                    <option value="pdf">PDF (HTML email + attachment)</option>
                    <option value="xlsx">XLSX (CSV attachment)</option>
                </select>
            </div>
            <div class="form-group">
                <label class="itm-checkbox-control">
                    <input type="checkbox" name="enabled" id="owner-schedule-report-enabled" value="1" checked>
                    <span>Enabled</span>
                </label>
            </div>
            <div style="display:flex;gap:8px;">
                <button type="submit" class="btn btn-primary" title="Save">💾</button>
                <button type="button" class="btn" id="close-owner-schedule-modal" title="Cancel">🔙</button>
            </div>
        </form>
    </div>
</div>

<script>
(function () {
    var moduleConfigs = <?php echo json_encode($hubModuleConfigs, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
    var lookups = {
        ticket_statuses: <?php echo json_encode($hubTicketStatuses, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>,
        ticket_priorities: <?php echo json_encode($hubTicketPriorities, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>,
        employees: <?php echo json_encode($hubEmployees, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>,
        paid_statuses: <?php echo json_encode($hubPaidStatuses, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>,
        suppliers: <?php echo json_encode($hubSuppliers, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>,
        equipment_types: <?php echo json_encode($hubEquipmentTypes, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>
    };
    var apiUrl = <?php echo json_encode($hubApiUrl); ?>;
    var csrf = <?php echo json_encode($hubCsrf); ?>;

    function esc(s) {
        var d = document.createElement('div');
        d.textContent = s == null ? '' : String(s);
        return d.innerHTML;
    }

    function buildFilterFields(moduleSlug, filters) {
        filters = filters || {};
        var html = '';
        if (moduleSlug === 'tickets') {
            html += '<div class="form-group"><label>Search</label><input type="text" data-filter-key="search" value="' + esc(filters.search || '') + '"></div>';
            html += '<div class="form-group"><label>Status</label><select data-filter-key="status_id"><option value="">All</option>';
            lookups.ticket_statuses.forEach(function (o) {
                html += '<option value="' + esc(o.id) + '"' + (String(filters.status_id || '') === String(o.id) ? ' selected' : '') + '>' + esc(o.name) + '</option>';
            });
            html += '</select></div>';
            html += '<div class="form-group"><label>Priority</label><select data-filter-key="priority_id"><option value="">All</option>';
            lookups.ticket_priorities.forEach(function (o) {
                html += '<option value="' + esc(o.id) + '"' + (String(filters.priority_id || '') === String(o.id) ? ' selected' : '') + '>' + esc(o.name) + '</option>';
            });
            html += '</select></div>';
            html += '<div class="form-group"><label>Assignee</label><select data-filter-key="assigned_to_employee_id"><option value="">All</option>';
            lookups.employees.forEach(function (o) {
                html += '<option value="' + esc(o.id) + '"' + (String(filters.assigned_to_employee_id || '') === String(o.id) ? ' selected' : '') + '>' + esc(o.label) + '</option>';
            });
            html += '</select></div>';
            html += '<div class="form-group"><label>Due from</label><input type="date" data-filter-key="due_date_from" value="' + esc(filters.due_date_from || '') + '"></div>';
            html += '<div class="form-group"><label>Due to</label><input type="date" data-filter-key="due_date_to" value="' + esc(filters.due_date_to || '') + '"></div>';
            html += '<label class="itm-checkbox-control"><input type="checkbox" data-filter-key="show_archived" value="1"' + (parseInt(filters.show_archived, 10) === 1 ? ' checked' : '') + '><span>Show archived</span></label>';
        } else if (moduleSlug === 'equipment') {
            html += '<div class="form-group"><label>Search</label><input type="text" data-filter-key="search" value="' + esc(filters.search || '') + '"></div>';
            html += '<div class="form-group"><label>Type</label><select data-filter-key="equipment_type_name"><option value="">All</option>';
            lookups.equipment_types.forEach(function (name) {
                html += '<option value="' + esc(name) + '"' + (String(filters.equipment_type_name || '') === String(name) ? ' selected' : '') + '>' + esc(name) + '</option>';
            });
            html += '</select></div>';
        } else if (moduleSlug === 'expenses') {
            html += '<div class="form-group"><label>Search</label><input type="text" data-filter-key="search" value="' + esc(filters.search || '') + '"></div>';
            html += '<div class="form-group"><label>Date from</label><input type="date" data-filter-key="date_from" value="' + esc(filters.date_from || '') + '"></div>';
            html += '<div class="form-group"><label>Date to</label><input type="date" data-filter-key="date_to" value="' + esc(filters.date_to || '') + '"></div>';
            html += '<div class="form-group"><label>Paid status</label><select data-filter-key="paid_status_id"><option value="">All</option>';
            lookups.paid_statuses.forEach(function (o) {
                html += '<option value="' + esc(o.id) + '"' + (String(filters.paid_status_id || '') === String(o.id) ? ' selected' : '') + '>' + esc(o.name) + '</option>';
            });
            html += '</select></div>';
            html += '<div class="form-group"><label>Supplier</label><select data-filter-key="supplier_id"><option value="">All</option>';
            lookups.suppliers.forEach(function (o) {
                html += '<option value="' + esc(o.id) + '"' + (String(filters.supplier_id || '') === String(o.id) ? ' selected' : '') + '>' + esc(o.name) + '</option>';
            });
            html += '</select></div>';
        }
        return html;
    }

    function collectFilters(container) {
        var filters = { sort: 'id', dir: 'DESC' };
        container.querySelectorAll('[data-filter-key]').forEach(function (el) {
            var key = el.getAttribute('data-filter-key');
            if (!key) { return; }
            if (el.type === 'checkbox') {
                if (el.checked) { filters[key] = 1; }
                return;
            }
            var val = (el.value || '').trim();
            if (val !== '') { filters[key] = val; }
        });
        return filters;
    }

    function buildColumnFields(moduleSlug, columns) {
        var cfg = moduleConfigs[moduleSlug] || {};
        var catalog = cfg.columns || {};
        var html = '';
        Object.keys(catalog).forEach(function (key) {
            var checked = !columns || columns.length === 0 || columns.indexOf(key) !== -1;
            html += '<label class="itm-checkbox-control" style="min-width:140px;"><input type="checkbox" class="itm-edit-column" value="' + esc(key) + '"' + (checked ? ' checked' : '') + '><span>' + esc(catalog[key]) + '</span></label>';
        });
        return html;
    }

    var editModal = document.getElementById('itm-edit-saved-view-modal');
    var editForm = document.getElementById('itm-edit-saved-view-form');
    var editFlash = document.getElementById('itm-edit-view-flash');
    function showEditModal(show) {
        if (!editModal) { return; }
        editModal.style.display = show ? 'flex' : 'none';
        if (!show && editFlash) { editFlash.style.display = 'none'; editFlash.textContent = ''; }
    }
    document.querySelectorAll('.js-edit-saved-view').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var id = btn.getAttribute('data-id') || '0';
            var moduleSlug = btn.getAttribute('data-module') || '';
            var filters = {};
            var columns = [];
            try { filters = JSON.parse(btn.getAttribute('data-filters') || '{}'); } catch (e) {}
            try { columns = JSON.parse(btn.getAttribute('data-columns') || '[]'); } catch (e) {}
            document.getElementById('itm-edit-view-id').value = id;
            document.getElementById('itm-edit-module-slug').value = moduleSlug;
            document.getElementById('itm-edit-view-name').value = btn.getAttribute('data-name') || '';
            document.getElementById('itm-edit-view-scope').value = btn.getAttribute('data-scope') || 'private';
            document.getElementById('itm-edit-filter-fields').innerHTML = buildFilterFields(moduleSlug, filters);
            document.getElementById('itm-edit-column-fields').innerHTML = buildColumnFields(moduleSlug, columns);
            showEditModal(true);
        });
    });
    var editCancel = document.getElementById('itm-edit-view-cancel');
    if (editCancel) { editCancel.addEventListener('click', function () { showEditModal(false); }); }
    if (editModal) {
        editModal.addEventListener('click', function (e) { if (e.target === editModal) { showEditModal(false); } });
    }
    if (editForm) {
        editForm.addEventListener('submit', function (e) {
            e.preventDefault();
            var moduleSlug = document.getElementById('itm-edit-module-slug').value;
            var filters = collectFilters(document.getElementById('itm-edit-filter-fields'));
            var columns = [];
            document.querySelectorAll('#itm-edit-column-fields .itm-edit-column:checked').forEach(function (cb) {
                columns.push(cb.value);
            });
            var body = new FormData();
            body.append('action', 'save');
            body.append('csrf_token', csrf);
            body.append('id', document.getElementById('itm-edit-view-id').value);
            body.append('module_slug', moduleSlug);
            body.append('name', document.getElementById('itm-edit-view-name').value);
            body.append('shared_scope', document.getElementById('itm-edit-view-scope').value);
            body.append('filters_json', JSON.stringify(filters));
            body.append('columns_json', JSON.stringify(columns));
            fetch(apiUrl, { method: 'POST', credentials: 'same-origin', body: body })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    if (data && data.ok) {
                        window.location.reload();
                        return;
                    }
                    if (editFlash) {
                        editFlash.textContent = (data && data.error) ? data.error : 'Could not save view.';
                        editFlash.style.display = 'block';
                    }
                });
        });
    }

    document.querySelectorAll('.js-share-saved-view').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var viewId = btn.getAttribute('data-id') || '0';
            var body = new FormData();
            body.append('action', 'share');
            body.append('csrf_token', csrf);
            body.append('id', viewId);
            fetch(apiUrl, { method: 'POST', credentials: 'same-origin', body: body })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    if (!data || !data.ok) {
                        alert((data && data.error) ? data.error : 'Could not create share link.');
                        return;
                    }
                    var msg = 'Share link (expires ' + (data.expires_at || 'soon') + '):\n' + data.url;
                    if (data.code) { msg += '\nCode: ' + data.code; }
                    prompt('Copy this read-only link:', data.url);
                });
        });
    });

    var ownerModal = document.getElementById('schedule-saved-view-modal');
    function showOwnerSchedule(show) {
        if (ownerModal) { ownerModal.style.display = show ? 'flex' : 'none'; }
    }
    document.querySelectorAll('.js-owner-schedule-saved-view').forEach(function (btn) {
        btn.addEventListener('click', function () {
            document.getElementById('owner-schedule-report-id').value = btn.getAttribute('data-schedule-id') || '0';
            document.getElementById('owner-schedule-report-slug').value = btn.getAttribute('data-slug') || '';
            document.getElementById('owner-schedule-report-cron').value = btn.getAttribute('data-cron') || '0 8 * * 1';
            document.getElementById('owner-schedule-report-recipients').value = btn.getAttribute('data-recipients') || '';
            document.getElementById('owner-schedule-report-format').value = btn.getAttribute('data-format') || 'pdf';
            document.getElementById('owner-schedule-report-enabled').checked = (btn.getAttribute('data-enabled') !== '0');
            showOwnerSchedule(true);
        });
    });
    var closeOwner = document.getElementById('close-owner-schedule-modal');
    if (closeOwner) { closeOwner.addEventListener('click', function () { showOwnerSchedule(false); }); }
    document.querySelectorAll('.js-edit-owner-schedule').forEach(function (btn) {
        btn.addEventListener('click', function () {
            document.getElementById('owner-schedule-report-id').value = btn.getAttribute('data-id') || '0';
            document.getElementById('owner-schedule-report-slug').value = btn.getAttribute('data-slug') || '';
            document.getElementById('owner-schedule-report-cron').value = btn.getAttribute('data-cron') || '';
            document.getElementById('owner-schedule-report-recipients').value = btn.getAttribute('data-recipients') || '';
            document.getElementById('owner-schedule-report-format').value = btn.getAttribute('data-format') || 'pdf';
            document.getElementById('owner-schedule-report-enabled').checked = (btn.getAttribute('data-enabled') === '1');
            showOwnerSchedule(true);
        });
    });
})();
</script>
