<?php
// View partial: mutating POST handlers call cr_require_valid_csrf_token() in ../handlers_post.php.
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php
if (!isset($currentUiConfig)) {
    $currentUiConfig = $ui_config ?? [];
}
if (!isset($crud_title)) {
    $crud_title = 'Partials';
}
?>
<title><?= sanitize($crud_title) ?> - <?php echo sanitize($app_name ?? itm_ui_config_app_name($currentUiConfig)); ?></title>
    <?php echo itm_render_head_favicon_link($favicon_url ?? null); ?>
    <link rel="stylesheet" href="../../css/styles.css">
</head>
<body>
<div class="container">
    <?php include '../../includes/sidebar.php'; ?>
    <div class="main-content">
        <?php include '../../includes/header.php'; ?>
        <div class="content">
            <?php echo itm_render_alert_errors($errors); ?>

            <?php if (in_array($crud_action, ['index', 'list_all'], true)): ?>
                <div data-itm-new-button-managed="server" style="position:relative;display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;min-height:40px;">
                    <?php if (in_array($newButtonPosition, ['left', 'left_right'], true)): ?>
                        <a href="create.php" class="btn btn-primary itm-list-new-button" title="Create">➕</a>
                    <?php else: ?>
                        <span></span>
                    <?php endif; ?>
                    <h1 style="position:absolute;left:50%;transform:translateX(-50%);margin:0;text-align:center;"><?php echo sanitize($moduleListHeading); ?></h1>
                    <?php if (in_array($newButtonPosition, ['right', 'left_right'], true)): ?>
                        <a href="create.php" class="btn btn-primary itm-list-new-button" title="Create">➕</a>
                    <?php else: ?>
                        <span></span>
                    <?php endif; ?>
                </div>

                <!-- BULK ACTIONS -->
                <?php if ($showBulkActions): ?>
                <div class="card" style="margin-bottom:16px;">
                    <form id="bulk-delete-form" method="POST" action="delete.php" style="display:flex;gap:8px;" data-itm-bulk-delete-bound="1">
                        <input type="hidden" name="csrf_token" value="<?php echo sanitize($csrfToken); ?>">
                        <button type="submit" name="bulk_action" value="bulk_delete" class="btn btn-sm btn-danger" id="bulk-delete-toggle">Select to Delete</button>
                        <button type="button" class="btn btn-sm" data-itm-bulk-cancel="1">Cancel</button>
                        <button type="submit" name="bulk_action" value="clear_table" class="btn btn-sm btn-danger" onclick="return confirm('Clear all records in this table? This cannot be undone.');">Clear Table</button>
                    </form>
                </div>
                <?php endif; ?>

                <!-- SEARCH BAR -->
                <div class="card" style="margin-bottom:16px;">
                    <form method="GET" style="display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap;">
                        <input type="hidden" name="sort" value="<?php echo sanitize($sort); ?>">
                        <input type="hidden" name="dir" value="<?php echo sanitize($dir); ?>">
                        <input type="hidden" name="page" value="1">
                        <?php if ($itmIpAddressFocusedList): ?>
                            <div class="form-group" style="margin:0;min-width:220px;">
                                <label for="subnetFilter">Subnet</label>
                                <select id="subnetFilter" name="subnet_id">
                                    <option value="">All subnets</option>
                                    <?php foreach ($itmSubnetFilterOptions as $itmSubnetOption): ?>
                                        <option value="<?php echo (int)$itmSubnetOption['id']; ?>"<?php echo $itmSubnetFilterId === (int)$itmSubnetOption['id'] ? ' selected' : ''; ?>>
                                            <?php echo sanitize((string)$itmSubnetOption['label']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        <?php endif; ?>
                        <div class="form-group" style="margin:0;min-width:260px;flex:1;">
                            <label for="moduleSearch">Search<?php echo $itmIpAddressFocusedList ? ' (IP, status, subnet, equipment, IP notes)' : ' (all fields)'; ?></label>
                            <input type="text" id="moduleSearch" name="search" value="<?php echo sanitize($searchRaw); ?>" placeholder="Type to search records...">
                        </div>
                        <div class="form-actions" style="margin:0;display:flex;gap:8px;">
                            <button type="submit" class="btn btn-primary">Search</button>
                            <a href="index.php" class="btn" title="Clear">🔙</a>
                            <?php if ($itmIpAddressFocusedList): ?>
                                <a href="../ip_subnets/index.php" class="btn btn-sm">IP Subnets</a>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>

                <?php if ($itmIpAddressFocusedList && $itmSubnetFilterId > 0): ?>
                    <?php
                        $itmSelectedSubnetLabel = '';
                        foreach ($itmSubnetFilterOptions as $itmSubnetOption) {
                            if ((int)$itmSubnetOption['id'] === $itmSubnetFilterId) {
                                $itmSelectedSubnetLabel = (string)$itmSubnetOption['label'];
                                break;
                            }
                        }
                    ?>
                    <p style="margin:0 0 12px;color:#57606a;">
                        Showing IPs for subnet <strong><?php echo sanitize($itmSelectedSubnetLabel); ?></strong>
                        — <a href="../ip_subnets/view.php?id=<?php echo (int)$itmSubnetFilterId; ?>">subnet details</a>
                    </p>
                <?php endif; ?>

                <!-- DATA TABLE -->
                <div class="card" style="overflow:auto;">
                    <table data-itm-db-import-endpoint="index.php">
                        <thead>
                        <tr>
                            <?php if ($itmIpAddressFocusedList): ?>
                                <th data-itm-actions-origin="1" class="itm-actions-cell">Actions</th>
                                <?php if ($showBulkActions): ?><th style="width:36px;"><input type="checkbox" id="select-all-rows" aria-label="Select all rows"></th><?php endif; ?>
                                <?php
                                    $itmIpListHeaders = [
                                        'ip_text' => 'IP',
                                        'status' => 'Status',
                                        'subnet' => 'Subnet',
                                        'equipment' => 'Equipment',
                                        'hostname' => 'Hostname',
                                        'notes' => 'IP Notes',
                                    ];
                                ?>
                                <?php foreach ($itmIpListHeaders as $itmHeaderField => $itmHeaderLabel): ?>
                                    <?php $nextDir = ($sort === $itmHeaderField && $dir === 'ASC') ? 'DESC' : 'ASC'; ?>
                                    <th>
                                        <a href="?<?php echo $itmIpAddressListQuerySuffix; ?>&sort=<?php echo urlencode($itmHeaderField); ?>&dir=<?php echo $nextDir; ?>&page=<?php echo (int)$page; ?>" style="text-decoration:none;color:inherit;">
                                            <?php echo sanitize($itmHeaderLabel); ?>
                                            <?php if ($sort === $itmHeaderField): ?> <?php echo $dir === 'ASC' ? '▲' : '▼'; ?><?php endif; ?>
                                        </a>
                                    </th>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <?php if ($showBulkActions): ?><th style="width:36px;"><input type="checkbox" id="select-all-rows" aria-label="Select all rows"></th><?php endif; ?>
                                <?php foreach ($uiColumns as $col): ?>
                                    <?php $field = (string)$col['Field']; ?>
                                    <?php $nextDir = ($sort === $field && $dir === 'ASC') ? 'DESC' : 'ASC'; ?>
                                    <th>
                                        <a href="?search=<?php echo urlencode($searchRaw); ?>&sort=<?php echo urlencode($field); ?>&dir=<?php echo $nextDir; ?>&page=<?php echo (int)$page; ?>" style="text-decoration:none;color:inherit;">
                                            <?php echo sanitize(cr_humanize_field($field)); ?>
                                            <?php if ($sort === $field): ?> <?php echo $dir === 'ASC' ? '▲' : '▼'; ?><?php endif; ?>
                                        </a>
                                    </th>
                                <?php endforeach; ?>
                                <th data-itm-actions-origin="1" class="itm-actions-cell">Actions</th>
                            <?php endif; ?>
                        </tr>
                        </thead>
                        <tbody>
                        <?php if ($itmIpAddressFocusedList && $itmIpAddressListRows): ?>
                            <?php foreach ($itmIpAddressListRows as $row): ?>
                                <?php
                                    $itmEquipId = (int)($row['equipment_id'] ?? 0);
                                    $itmEquipLabel = function_exists('itm_ipam_equipment_name_label_from_row')
                                        ? itm_ipam_equipment_name_label_from_row($row)
                                        : (function_exists('itm_ipam_equipment_label_from_row') ? itm_ipam_equipment_label_from_row($row) : '');
                                    $itmHostnameDisplay = function_exists('itm_ipam_hostname_display_from_row')
                                        ? itm_ipam_hostname_display_from_row($row)
                                        : trim((string)($row['hostname'] ?? ''));
                                    $itmStatusDisplay = function_exists('itm_ipam_effective_status_from_row')
                                        ? itm_ipam_effective_status_from_row($row)
                                        : (string)($row['status'] ?? '');
                                ?>
                                <tr>
                                    <td class="itm-actions-cell" data-itm-actions-origin="1">
                                        <div class="itm-actions-wrap">
                                            <a class="btn btn-sm" href="view.php?id=<?php echo (int)$row['id']; ?>" title="View">🔎</a>
                                            <a class="btn btn-sm" href="edit.php?id=<?php echo (int)$row['id']; ?>" title="Edit">✏️</a>
                                            <form method="POST" action="delete.php" style="display:inline;" onsubmit="return confirm('Delete this record?');">
                                                <input type="hidden" name="id" value="<?php echo (int)$row['id']; ?>">
                                                <input type="hidden" name="bulk_action" value="single_delete">
                                                <input type="hidden" name="csrf_token" value="<?php echo sanitize($csrfToken); ?>">
                                                <button class="btn btn-sm btn-danger" type="submit" title="Delete">🗑️</button>
                                            </form>
                                        </div>
                                    </td>
                                    <?php if ($showBulkActions): ?><td><input type="checkbox" name="ids[]" value="<?php echo (int)$row['id']; ?>" form="bulk-delete-form"></td><?php endif; ?>
                                    <td><?php echo sanitize((string)($row['ip_text'] ?? '')); ?></td>
                                    <td><?php echo cr_render_cell_value($crud_table, 'status', $itmStatusDisplay); ?></td>
                                    <td>
                                        <?php if ((int)($row['subnet_id'] ?? 0) > 0): ?>
                                            <a href="../ip_subnets/view.php?id=<?php echo (int)$row['subnet_id']; ?>"><?php echo sanitize((string)($row['subnet_cidr'] ?? '')); ?></a>
                                        <?php else: ?>
                                            —
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($itmEquipId > 0 && $itmEquipLabel !== ''): ?>
                                            <a href="../equipment/view.php?id=<?php echo $itmEquipId; ?>"><?php echo sanitize($itmEquipLabel); ?></a>
                                        <?php else: ?>
                                            —
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo $itmHostnameDisplay !== '' ? sanitize($itmHostnameDisplay) : '—'; ?></td>
                                    <td>
                                        <input
                                            type="text"
                                            class="itm-ip-inline-notes"
                                            data-ip-address-id="<?php echo (int)$row['id']; ?>"
                                            value="<?php echo sanitize((string)($row['notes'] ?? '')); ?>"
                                            maxlength="255"
                                            placeholder="IP-only note…"
                                            style="width:100%;min-width:160px;"
                                            aria-label="IP notes for <?php echo sanitize((string)($row['ip_text'] ?? '')); ?>"
                                        >
                                        <span class="itm-ip-inline-notes-status" style="display:block;font-size:12px;color:#57606a;min-height:16px;"></span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php elseif (!$itmIpAddressFocusedList && $rows && mysqli_num_rows($rows) > 0): while ($row = mysqli_fetch_assoc($rows)): ?>
                            <tr>
                                <?php if ($showBulkActions): ?><td><input type="checkbox" name="ids[]" value="<?php echo (int)$row['id']; ?>" form="bulk-delete-form"></td><?php endif; ?>
                                <?php foreach ($uiColumns as $col): $f = $col['Field']; ?>
                                    <td>
                                        <?php if ($f === 'notes' && trim((string)($row[$f] ?? '')) !== ''): ?>
                                            <span title="<?php echo sanitize((string)$row[$f]); ?>">💬</span>
                                        <?php else: ?>
                                            <?php echo cr_render_cell_value($crud_table, $f, $row[$f] ?? ''); ?>
                                        <?php endif; ?>
                                    </td>
                                <?php endforeach; ?>
                                <td class="itm-actions-cell" data-itm-actions-origin="1">
                                    <div class="itm-actions-wrap">
                                        <a class="btn btn-sm" href="view.php?id=<?php echo (int)$row['id']; ?>" title="View">🔎</a>
                                        <a class="btn btn-sm" href="edit.php?id=<?php echo (int)$row['id']; ?>" title="Edit">✏️</a>
                                        <form method="POST" action="delete.php" style="display:inline;" onsubmit="return confirm('Delete this record?');">
                                            <input type="hidden" name="id" value="<?php echo (int)$row['id']; ?>">
                                            <input type="hidden" name="bulk_action" value="single_delete">
                                            <input type="hidden" name="csrf_token" value="<?php echo sanitize($csrfToken); ?>">
                                            <button class="btn btn-sm btn-danger" type="submit" title="Delete">🗑️</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; else: ?>
                            <tr><td colspan="<?php echo $itmIpAddressFocusedList ? (7 + ($showBulkActions ? 1 : 0)) : count($fieldColumns) + 1 + ($showBulkActions ? 1 : 0); ?>" style="text-align:center;">No records found.<?php if ($itmIpAddressFocusedList && $itmSubnetFilterId > 0): ?> Generate host IPs from the <a href="../ip_subnets/view.php?id=<?php echo (int)$itmSubnetFilterId; ?>">subnet view</a>.<?php endif; ?></td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <?php if ($hasCompany && $company_id > 0 && $totalRows === 0): ?>
                    <div class="card" style="margin-top:12px;">
                        <form method="POST" style="display:flex;justify-content:center;">
                            <input type="hidden" name="csrf_token" value="<?php echo sanitize($csrfToken); ?>">
                            <button type="submit" name="add_sample_data" value="1" class="btn btn-primary">Add sample data</button>
                        </form>
                    </div>
                <?php endif; ?>

                <!-- PAGINATION CONTROLS -->
                <?php if ($totalRows > $perPage): ?>
                    <div style="display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;margin-top:12px;">
                        <div>Showing <?php echo $offset + 1; ?>-<?php echo min($offset + $perPage, $totalRows); ?> of <?php echo $totalRows; ?></div>
                        <div style="display:flex;gap:6px;flex-wrap:wrap;">
                            <?php if ($page > 1): ?>
                                <a class="btn btn-sm" href="?<?php echo $itmIpAddressFocusedList ? $itmIpAddressListQuerySuffix : ('search=' . urlencode($searchRaw) . '&sort=' . urlencode($sort) . '&dir=' . urlencode($dir)); ?>&page=1" title="First page">⏮️</a>
                                <a class="btn btn-sm" href="?<?php echo $itmIpAddressFocusedList ? $itmIpAddressListQuerySuffix : ('search=' . urlencode($searchRaw) . '&sort=' . urlencode($sort) . '&dir=' . urlencode($dir)); ?>&page=<?php echo $page - 1; ?>" title="Previous page">◀️</a>
                            <?php endif; ?>
                            <span class="btn btn-sm" style="pointer-events:none;opacity:.8;">Page <?php echo $page; ?> of <?php echo $totalPages; ?></span>
                            <?php if ($page < $totalPages): ?>
                                <a class="btn btn-sm" href="?<?php echo $itmIpAddressFocusedList ? $itmIpAddressListQuerySuffix : ('search=' . urlencode($searchRaw) . '&sort=' . urlencode($sort) . '&dir=' . urlencode($dir)); ?>&page=<?php echo $page + 1; ?>" title="Next page">▶️</a>
                                <a class="btn btn-sm" href="?<?php echo $itmIpAddressFocusedList ? $itmIpAddressListQuerySuffix : ('search=' . urlencode($searchRaw) . '&sort=' . urlencode($sort) . '&dir=' . urlencode($dir)); ?>&page=<?php echo $totalPages; ?>" title="Last page">⏭️</a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>

            <?php elseif (in_array($crud_action, ['create', 'edit'], true)): ?>
                <!-- FORM VIEW (DELEGATED TO index.php VIA WRAPPERS) -->
                <h1 title="<?php echo $crud_action === 'create' ? 'New IP address' : 'Edit IP address'; ?>"><?php echo $crud_action === 'create' ? '➕' : '✏️'; ?></h1>
                <form method="POST" class="form-grid" style="max-width:980px;">
                    <input type="hidden" name="csrf_token" value="<?php echo sanitize($csrfToken); ?>">
                    <?php foreach ($uiColumns as $col): $name = $col['Field'];
                        $isTinyInt = (bool)preg_match('/^tinyint(\(\d+\))?/i', (string)$col['Type']);
                        $isDate = str_starts_with($col['Type'], 'date');
                        $isDateTime = str_starts_with($col['Type'], 'datetime');
                        $isText = str_contains($col['Type'], 'text');
                        $val = $data[$name] ?? '';
                        $itmPreferPostValues = $_SERVER['REQUEST_METHOD'] === 'POST' && in_array($crud_action, ['create', 'edit'], true);
                        $displayVal = function_exists('itm_ipam_form_display_value')
                            ? itm_ipam_form_display_value($name, $val, $itmPreferPostValues)
                            : (($val === 'NULL') ? '' : (string)$val);
                    ?>
                        <div class="form-group">
                            <label><?php echo sanitize(cr_humanize_field($name)); ?></label>
                            <?php if ($name === 'company_id' && $company_id > 0): ?>
                                <input type="hidden" name="company_id" value="<?php echo (int)$company_id; ?>">
                            <?php elseif ($isTinyInt): ?>
                                <label class="itm-checkbox-control">
                                    <input type="checkbox" name="<?php echo sanitize($name); ?>" value="1" <?php echo ((int)$displayVal === 1) ? 'checked' : ''; ?>>
                                    <span><?php echo sanitize(cr_humanize_field($name)); ?> <span class="itm-check-indicator" aria-hidden="true"><?php echo ((int)$displayVal === 1) ? '✅' : '❌'; ?></span></span>
                                </label>
                            <?php elseif (preg_match('/(_by|_by_user_id)$/', (string)$name)): ?>
                                <?php
                                    $userOpts = cr_user_options($conn, (int)$company_id);
                                    $userOpts = cr_append_selected_user_option($conn, (int)$company_id, $userOpts, $displayVal);
                                ?>
                                <select name="<?php echo sanitize($name); ?>">
                                    <option value="">-- Select --</option>
                                    <?php foreach ($userOpts as $userOpt): ?>
                                        <option value="<?php echo (int)$userOpt['id']; ?>" <?php echo ((string)$displayVal === (string)$userOpt['id']) ? 'selected' : ''; ?>><?php echo sanitize($userOpt['label']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            <?php elseif (isset($fkMap[$name])): ?>
                                <?php
                                    $opts = cr_fk_options($conn, $fkMap[$name], (int)$company_id, $name);
                                    $opts = cr_append_selected_fk_option($conn, $fkMap[$name], (int)$company_id, $opts, $displayVal);
                                    $fkMeta = cr_fk_metadata($conn, $fkMap[$name]['REFERENCED_TABLE_NAME']);
                                    $isCompanyScoped = in_array('company_id', $fkMeta['available'], true) ? 1 : 0;
                                ?>
                                <select
                                    name="<?php echo sanitize($name); ?>"
                                    data-addable-select="1"
                                    data-add-table="<?php echo sanitize($fkMap[$name]['REFERENCED_TABLE_NAME']); ?>"
                                    data-add-id-col="<?php echo sanitize($fkMap[$name]['REFERENCED_COLUMN_NAME']); ?>"
                                    data-add-label-col="<?php echo sanitize($fkMeta['label_col']); ?>"
                                    data-add-company-scoped="<?php echo $isCompanyScoped; ?>"
                                    data-add-friendly="<?php echo sanitize(strtolower(cr_humanize_field($name))); ?>"
                                >
                                    <option value="">-- Select --</option>
                                    <?php foreach ($opts as $opt): ?>
                                        <option value="<?php echo (int)$opt['id']; ?>" <?php echo ((string)$displayVal === (string)$opt['id']) ? 'selected' : ''; ?>><?php echo sanitize($opt['label']); ?></option>
                                    <?php endforeach; ?>
                                    <option value="__add_new__">➕</option>
                                </select>
                            <?php elseif ($isDateTime): ?>
                                <input type="datetime-local" name="<?php echo sanitize($name); ?>" value="<?php echo sanitize(str_replace(' ', 'T', substr($displayVal, 0, 16))); ?>">
                            <?php elseif ($isDate): ?>
                                <input type="date" name="<?php echo sanitize($name); ?>" value="<?php echo sanitize(substr($displayVal, 0, 10)); ?>">
                            <?php elseif ($name === 'status' && ($crud_table ?? '') === 'ip_addresses'): ?>
                                <?php $statusOptions = ['free', 'used', 'reserved', 'gateway', 'dns', 'dhcp', 'other']; ?>
                                <select name="status">
                                    <?php foreach ($statusOptions as $statusOption): ?>
                                        <option value="<?php echo sanitize($statusOption); ?>" <?php echo (strtolower((string)$displayVal) === $statusOption) ? 'selected' : ''; ?>><?php echo sanitize(ucfirst($statusOption)); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            <?php elseif ($isText): ?>
                                <textarea name="<?php echo sanitize($name); ?>" rows="4"><?php echo sanitize($displayVal); ?></textarea>
                            <?php else: ?>
                                <input type="text" name="<?php echo sanitize($name); ?>" value="<?php echo sanitize($displayVal); ?>">
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                    <?php
                    if (function_exists('itm_crud_render_form_hidden_audit_inputs')) {
                        itm_crud_render_form_hidden_audit_inputs(is_array($data) ? $data : [], (string)$crud_action);
                    }
                    ?>
                    <div class="form-actions">
                        <button class="btn btn-primary" type="submit" title="Save">💾</button>
                        <a href="index.php" class="btn" title="Back">🔙</a>
                    </div>
                </form>

            <?php elseif ($crud_action === 'view'): ?>
                <!-- READ-ONLY VIEW -->
                <h1 title="View IP address">🔎</h1>
                <?php $GLOBALS['itm_ipam_view_row'] = is_array($data) ? $data : []; ?>
                <div class="card">
                    <table>
                        <tbody>
                        <?php foreach ($fieldColumns as $col): $f = $col['Field']; ?>
                            <tr>
                                <th style="width:240px;"><?php echo sanitize(cr_humanize_field($f)); ?></th>
                                <td><?php echo cr_render_cell_value($crud_table, $f, $data[$f] ?? ''); ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php itm_crud_render_view_audit_meta_rows($conn, (int)$company_id, $data); ?>
                        </tbody>
                    </table>
                    <p style="margin-top:16px;">
                        <a href="index.php" class="btn" title="Back">🔙</a>
                        <a class="btn btn-primary" href="edit.php?id=<?php echo (int)($data['id'] ?? 0); ?>" title="Edit">✏️</a>
                    </p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- JS FOR BULK ACTIONS AND UI INDICATORS -->
<script src="../../js/theme.js"></script>
<script> window.ITM_CSRF_TOKEN = <?php echo json_encode($csrfToken); ?>; </script>
<script src="../../js/select-add-option.js"></script>
<script>
document.addEventListener('click', function (event) {
    const link = event.target.closest('a[data-outlook-link="1"]');
    if (!link) return;
    const outlookHref = link.getAttribute('data-outlook-href');
    if (outlookHref) { window.location.href = outlookHref; }
});
document.addEventListener('change', function (event) {
    if (!event.target.matches('.itm-checkbox-control input[type="checkbox"]')) return;
    const indicator = event.target.closest('.itm-checkbox-control')?.querySelector('.itm-check-indicator');
    if (indicator) { indicator.textContent = event.target.checked ? '✅' : '❌'; }
});
</script>
<script>
(function () {
    const inlineNotesEndpoint = <?php echo json_encode($modulePath . '/index.php'); ?>;
    const inlineNotesCsrf = <?php echo json_encode($csrfToken); ?>;
    const inlineNotesSaving = new Set();

    function setInlineNotesStatus(input, message, tone) {
        const status = input.parentElement ? input.parentElement.querySelector('.itm-ip-inline-notes-status') : null;
        if (!status) { return; }
        status.textContent = message || '';
        status.style.color = tone === 'ok' ? '#1a7f37' : (tone === 'error' ? '#cf222e' : '#57606a');
    }

    function saveInlineNotes(input) {
        const addressId = parseInt(input.getAttribute('data-ip-address-id') || '0', 10);
        if (!addressId || inlineNotesSaving.has(addressId)) { return; }
        const nextValue = (input.value || '').trim();
        const lastSaved = input.getAttribute('data-last-saved') ?? '';
        if (nextValue === lastSaved) { return; }

        inlineNotesSaving.add(addressId);
        setInlineNotesStatus(input, 'Saving…', 'pending');
        const body = new URLSearchParams();
        body.set('inline_notes_save', '1');
        body.set('id', String(addressId));
        body.set('notes', nextValue);
        body.set('csrf_token', inlineNotesCsrf);

        fetch(inlineNotesEndpoint, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
            body: body.toString(),
            credentials: 'same-origin'
        })
            .then(function (response) { return response.json().then(function (data) { return { ok: response.ok, data: data }; }); })
            .then(function (result) {
                if (!result.ok || !result.data || !result.data.ok) {
                    throw new Error((result.data && result.data.error) ? result.data.error : 'Save failed');
                }
                input.setAttribute('data-last-saved', nextValue);
                setInlineNotesStatus(input, 'Saved', 'ok');
                window.setTimeout(function () {
                    if ((input.value || '').trim() === nextValue) {
                        setInlineNotesStatus(input, '', 'pending');
                    }
                }, 1500);
            })
            .catch(function (error) {
                setInlineNotesStatus(input, error && error.message ? error.message : 'Save failed', 'error');
            })
            .finally(function () {
                inlineNotesSaving.delete(addressId);
            });
    }

    document.querySelectorAll('.itm-ip-inline-notes').forEach(function (input) {
        input.setAttribute('data-last-saved', (input.value || '').trim());
        input.addEventListener('blur', function () { saveInlineNotes(input); });
        input.addEventListener('keydown', function (event) {
            if (event.key === 'Enter') {
                event.preventDefault();
                input.blur();
            }
        });
    });
})();
</script>
</body>
</html>
