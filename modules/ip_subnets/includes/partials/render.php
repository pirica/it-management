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
    <link rel="stylesheet" href="../../css/styles.css">
</head>
<body>
<div class="container">
    <?php include '../../includes/sidebar.php'; ?>
    <div class="main-content">
        <?php include '../../includes/header.php'; ?>
        <div class="content">
            <?php echo itm_render_alert_errors($errors); ?>
            <?php if ($crudSuccessMessage !== ''): ?>
                <div class="alert alert-success"><?php echo sanitize($crudSuccessMessage); ?></div>
            <?php endif; ?>

            <?php if (in_array($crud_action, ['index', 'list_all'], true)): ?>
                <?php $itmSubnetListBulkGenerateColumn = (($crud_table ?? '') === 'ip_subnets'); ?>
                <div data-itm-new-button-managed="server" style="position:relative;display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;min-height:40px;">
                    <?php if (in_array($newButtonPosition, ['left', 'left_right'], true)): ?>
                        <a href="create.php" class="btn btn-primary">➕</a>
                    <?php else: ?>
                        <span></span>
                    <?php endif; ?>
                    <h1 style="position:absolute;left:50%;transform:translateX(-50%);margin:0;text-align:center;"><?php echo sanitize($moduleListHeading); ?></h1>
                    <?php if (in_array($newButtonPosition, ['right', 'left_right'], true)): ?>
                        <a href="create.php" class="btn btn-primary">➕</a>
                    <?php else: ?>
                        <span></span>
                    <?php endif; ?>
                </div>

                <?php if ($showBulkActions): ?>
                <!-- BULK ACTIONS -->
                <div class="card" style="margin-bottom:16px;">
                    <form id="bulk-delete-form" method="POST" action="delete.php" style="display:flex;gap:8px;">
                        <input type="hidden" name="csrf_token" value="<?php echo sanitize($csrfToken); ?>">
                        <button type="submit" name="bulk_action" value="bulk_delete" class="btn btn-sm btn-danger" id="bulk-delete-toggle">Select to Delete</button>
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
                        <div class="form-group" style="margin:0;min-width:260px;flex:1;">
                            <label for="moduleSearch">Search (all fields)</label>
                            <input type="text" id="moduleSearch" name="search" value="<?php echo sanitize($searchRaw); ?>" placeholder="Type to search records...">
                        </div>
                        <div class="form-actions" style="margin:0;display:flex;gap:8px;">
                            <button type="submit" class="btn btn-primary">Search</button>
                            <a href="index.php" class="btn">🔙</a>
                        </div>
                    </form>
                    <div style="margin-top:14px;padding-top:14px;border-top:1px solid var(--border-color, #d0d7de);display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap;">
                        <div class="form-group" style="margin:0;min-width:180px;">
                            <label for="itmPingIp">Ping IP</label>
                            <input type="text" id="itmPingIp" value="" placeholder="192.168.10.20" inputmode="decimal" autocomplete="off">
                        </div>
                        <div class="form-group" style="margin:0;min-width:100px;">
                            <label for="itmPingPort">Port</label>
                            <input type="number" id="itmPingPort" value="" placeholder="80" min="1" max="65535" step="1" inputmode="numeric">
                        </div>
                        <div class="form-actions" style="margin:0;display:flex;gap:8px;align-items:center;">
                            <button type="button" class="btn btn-primary" id="itmPingRunBtn">Ping</button>
                        </div>
                        <small style="flex:1 1 100%;color:#57606a;">Uses TCP connect (no shell). Empty port tries 80, then 443, 22, 53, 3389.</small>
                        <div id="itmPingResult" style="flex:1 1 100%;min-height:20px;font-size:14px;color:#57606a;" aria-live="polite"></div>
                    </div>
                    <div style="margin-top:14px;padding-top:14px;border-top:1px solid var(--border-color, #d0d7de);">
                        <h3 style="margin:0 0 6px 0;font-size:16px;">Network Discovery</h3>
                        <p style="margin:0 0 12px 0;color:#57606a;font-size:14px;">Network scanning to find connected devices and add them to inventory (TCP connect only, no shell). Responding hosts are enriched with hosted domains from IP2WHOIS when <code>IP2WHOIS_API_KEY</code> is set in <code>.env</code>.</p>
                        <p style="margin:0 0 10px 0;color:#57606a;font-size:13px;"><strong>Scope of Inspection</strong> — select an IP range to search for devices (up to 255 addresses at a time).</p>
                        <div style="display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap;">
                            <div class="form-group" style="margin:0;min-width:180px;">
                                <label for="itmDiscoveryRangeStart">Beginning of the Range</label>
                                <input type="text" id="itmDiscoveryRangeStart" value="192.168.1.1" placeholder="192.168.1.1" inputmode="decimal" autocomplete="off">
                            </div>
                            <div class="form-group" style="margin:0;min-width:180px;">
                                <label for="itmDiscoveryRangeEnd">End of range</label>
                                <input type="text" id="itmDiscoveryRangeEnd" value="192.168.1.50" placeholder="192.168.1.50" inputmode="decimal" autocomplete="off">
                            </div>
                            <div class="form-actions" style="margin:0;display:flex;gap:8px;align-items:center;">
                                <button type="button" class="btn btn-primary" id="itmDiscoveryScanBtn">Start the scan</button>
                                <button type="button" class="btn btn-danger" id="itmDiscoveryStopBtn" style="display:none;">Stop</button>
                                <button type="button" class="btn" id="itmDiscoveryImportBtn" style="display:none;">Add to inventory</button>
                            </div>
                        </div>
                        <div id="itmDiscoveryProgressWrap" style="display:none;margin-top:12px;">
                            <div style="display:flex;justify-content:space-between;align-items:center;gap:8px;margin-bottom:6px;font-size:13px;color:#57606a;">
                                <span id="itmDiscoveryProgressLabel">Preparing scan…</span>
                                <span id="itmDiscoveryProgressPct">0%</span>
                            </div>
                            <div style="height:8px;background:#d0d7de;border-radius:4px;overflow:hidden;" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0" id="itmDiscoveryProgressTrack">
                                <div id="itmDiscoveryProgressBar" style="width:0%;height:100%;background:#0969da;border-radius:4px;transition:width 0.15s ease;"></div>
                            </div>
                        </div>
                        <div id="itmDiscoveryStatus" style="margin-top:10px;font-size:14px;color:#57606a;" aria-live="polite"></div>
                        <div id="itmDiscoveryLog" style="display:none;margin-top:8px;max-height:140px;overflow-y:auto;padding:8px 10px;background:var(--bg-muted, #f6f8fa);border:1px solid var(--border-color, #d0d7de);border-radius:6px;font-family:ui-monospace,Consolas,monospace;font-size:12px;line-height:1.45;"></div>
                        <div id="itmDiscoveryResults" style="margin-top:10px;overflow:auto;"></div>
                    </div>
                </div>

                <!-- DATA TABLE -->
                <div class="card" style="overflow:auto;">
                    <table data-itm-db-import-endpoint="index.php">
                        <thead>
                        <tr>
                            <?php if ($showBulkActions): ?><th style="width:36px;"><input type="checkbox" id="select-all-rows" aria-label="Select all rows"></th><?php endif; ?>
                            <?php foreach ($uiColumns as $col): ?>
                                <?php $field = (string)$col['Field']; ?>
                                <?php if (!empty($itmSubnetListBulkGenerateColumn) && $field === 'active'): ?>
                                    <th>Generate host IPs</th>
                                <?php endif; ?>
                                <?php $nextDir = ($sort === $field && $dir === 'ASC') ? 'DESC' : 'ASC'; ?>
                                <th>
                                    <a href="?search=<?php echo urlencode($searchRaw); ?>&sort=<?php echo urlencode($field); ?>&dir=<?php echo $nextDir; ?>&page=<?php echo (int)$page; ?>" style="text-decoration:none;color:inherit;">
                                        <?php echo sanitize(cr_humanize_field($field)); ?>
                                        <?php if ($sort === $field): ?> <?php echo $dir === 'ASC' ? '▲' : '▼'; ?><?php endif; ?>
                                    </a>
                                </th>
                            <?php endforeach; ?>
                            <th data-itm-actions-origin="1" class="itm-actions-cell">Actions</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php if ($rows && mysqli_num_rows($rows) > 0): while ($row = mysqli_fetch_assoc($rows)): ?>
                            <tr>
                                <?php if ($showBulkActions): ?><td><input type="checkbox" name="ids[]" value="<?php echo (int)$row['id']; ?>" form="bulk-delete-form"></td><?php endif; ?>
                                <?php foreach ($uiColumns as $col): $f = $col['Field']; ?>
                                    <?php if (!empty($itmSubnetListBulkGenerateColumn) && $f === 'active'): ?>
                                        <?php
                                            $itmRowSubnetId = (int)($row['id'] ?? 0);
                                            $itmRowBulkUi = function_exists('itm_ipam_subnet_bulk_generate_ui')
                                                ? itm_ipam_subnet_bulk_generate_ui((int)($row['prefix_length'] ?? 0))
                                                : ['can_generate' => false, 'confirm_message' => '', 'button_label' => 'Generate host IPs'];
                                        ?>
                                        <td>
                                            <?php if (!empty($itmRowBulkUi['can_generate'])): ?>
                                                <form method="POST" style="display:inline;" onsubmit="return confirm(<?php echo json_encode((string)($itmRowBulkUi['confirm_message'] ?? '')); ?>);">
                                                    <input type="hidden" name="csrf_token" value="<?php echo sanitize($csrfToken); ?>">
                                                    <input type="hidden" name="subnet_id" value="<?php echo $itmRowSubnetId; ?>">
                                                    <button type="submit" name="generate_subnet_ips" value="1" class="btn btn-sm"><?php echo sanitize((string)($itmRowBulkUi['button_label'] ?? 'Generate host IPs')); ?></button>
                                                </form>
                                            <?php else: ?>
                                                —
                                            <?php endif; ?>
                                        </td>
                                    <?php endif; ?>
                                    <td>
                                        <?php if ($f === 'comments' && trim((string)($row[$f] ?? '')) !== ''): ?>
                                            <span title="<?php echo sanitize((string)$row[$f]); ?>">💬</span>
                                        <?php else: ?>
                                            <?php echo cr_render_cell_value($crud_table, $f, $row[$f] ?? ''); ?>
                                        <?php endif; ?>
                                    </td>
                                <?php endforeach; ?>
                                <td class="itm-actions-cell" data-itm-actions-origin="1">
                                    <div class="itm-actions-wrap">
                                        <a class="btn btn-sm" href="view.php?id=<?php echo (int)$row['id']; ?>">🔎</a>
                                        <a class="btn btn-sm" href="edit.php?id=<?php echo (int)$row['id']; ?>">✏️</a>
                                        <form method="POST" action="delete.php" style="display:inline;" onsubmit="return confirm('Delete this record?');">
                                            <input type="hidden" name="id" value="<?php echo (int)$row['id']; ?>">
                                            <input type="hidden" name="bulk_action" value="single_delete">
                                            <input type="hidden" name="csrf_token" value="<?php echo sanitize($csrfToken); ?>">
                                            <button class="btn btn-sm btn-danger" type="submit">🗑️</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; else: ?>
                            <tr><td colspan="<?php echo count($uiColumns) + ($showBulkActions ? 2 : 1) + (!empty($itmSubnetListBulkGenerateColumn) ? 1 : 0); ?>" style="text-align:center;">No records found.</td></tr>
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
                                <a class="btn btn-sm" href="?search=<?php echo urlencode($searchRaw); ?>&sort=<?php echo urlencode($sort); ?>&dir=<?php echo urlencode($dir); ?>&page=<?php echo $page - 1; ?>" title="◀️ Previous">Previous</a>
                            <?php endif; ?>
                            <span class="btn btn-sm" style="pointer-events:none;opacity:.8;">Page <?php echo $page; ?> of <?php echo $totalPages; ?></span>
                            <?php if ($page < $totalPages): ?>
                                <a class="btn btn-sm" href="?search=<?php echo urlencode($searchRaw); ?>&sort=<?php echo urlencode($sort); ?>&dir=<?php echo urlencode($dir); ?>&page=<?php echo $page + 1; ?>" title="▶️ Next">Next</a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>

            <?php elseif (in_array($crud_action, ['create', 'edit'], true)): ?>
                <!-- FORM VIEW (DELEGATED TO index.php VIA WRAPPERS) -->
                <h1><?php echo $crud_action === 'create' ? 'New ' : 'Edit '; ?><?php echo sanitize($crud_title); ?></h1>
                <form method="POST" class="form-grid" style="max-width:980px;">
                    <input type="hidden" name="csrf_token" value="<?php echo sanitize($csrfToken); ?>">
                    <?php foreach ($fieldColumns as $col): $name = $col['Field'];
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
                            <?php elseif ($isText): ?>
                                <textarea name="<?php echo sanitize($name); ?>" rows="4"><?php echo sanitize($displayVal); ?></textarea>
                            <?php elseif (($crud_table ?? '') === 'ip_subnets' && $name === 'cidr'): ?>
                                <input
                                    type="text"
                                    name="cidr"
                                    value="<?php echo sanitize($displayVal); ?>"
                                    required
                                    inputmode="decimal"
                                    autocomplete="off"
                                    placeholder="10.0.0.0/24"
                                    pattern="^\d{1,3}(\.\d{1,3}){3}(\/\d{1,2})?$"
                                    title="Enter a valid CIDR, for example 192.168.10.0/24"
                                >
                                <small style="display:block;margin-top:4px;color:#57606a;">Example: 192.168.10.0/24 (a plain IPv4 address defaults to /24)</small>
                            <?php elseif (($crud_table ?? '') === 'ip_subnets' && in_array($name, ['gateway_ip', 'dns1_ip', 'dns2_ip'], true)): ?>
                                <input
                                    type="text"
                                    name="<?php echo sanitize($name); ?>"
                                    value="<?php echo sanitize($displayVal); ?>"
                                    inputmode="decimal"
                                    autocomplete="off"
                                    placeholder="Must be inside the subnet CIDR"
                                    pattern="^\d{1,3}(\.\d{1,3}){3}$"
                                    title="Enter a valid IPv4 address within the subnet"
                                >
                            <?php else: ?>
                                <input type="text" name="<?php echo sanitize($name); ?>" value="<?php echo sanitize($displayVal); ?>">
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                    <div class="form-actions">
                        <button class="btn btn-primary" type="submit">💾</button>
                        <a href="index.php" class="btn">🔙</a>
                    </div>
                </form>

            <?php elseif ($crud_action === 'view'): ?>
                <!-- READ-ONLY VIEW -->
                <h1>View <?php echo sanitize($crud_title); ?></h1>
                <?php if ($crudSuccessMessage !== ''): ?>
                    <div class="alert alert-success"><?php echo sanitize($crudSuccessMessage); ?></div>
                <?php endif; ?>
                <div class="card">
                    <table>
                        <tbody>
                        <?php foreach ($uiColumns as $col): $f = $col['Field']; ?>
                            <tr>
                                <th style="width:240px;"><?php echo sanitize(cr_humanize_field($f)); ?></th>
                                <td><?php echo cr_render_cell_value($crud_table, $f, $data[$f] ?? ''); ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                    <p style="margin-top:16px;">
                        <a href="index.php" class="btn">🔙</a> 
                        <a class="btn btn-primary" href="edit.php?id=<?php echo (int)($data['id'] ?? 0); ?>">✏️</a>
                    </p>
                </div>
                <?php require $ipSubnetsModuleDir . '/subnet_view_stats.php'; ?>
                <?php require $ipSubnetsModuleDir . '/subnet_view_ips.php'; ?>
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
<?php if (($crud_table ?? '') === 'ip_subnets' && in_array($crud_action, ['index', 'list_all'], true)): ?>
<script>
(function () {
    const pingBtn = document.getElementById('itmPingRunBtn');
    const pingIpInput = document.getElementById('itmPingIp');
    const pingPortInput = document.getElementById('itmPingPort');
    const pingResult = document.getElementById('itmPingResult');
    const hasPingUi = !!(pingBtn && pingIpInput && pingResult);

    const pingEndpoint = <?php echo json_encode($modulePath . '/index.php'); ?>;
    const pingCsrf = <?php echo json_encode($csrfToken); ?>;
    let pingBusy = false;

    function setPingMessage(html) {
        pingResult.innerHTML = html;
        pingResult.style.removeProperty('color');
    }

    function pingRow(html, status) {
        var color = '#57606a';
        if (status === 'ok') {
            color = '#1a7f37';
        } else if (status === 'fail') {
            color = '#cf222e';
        } else if (status === 'warn') {
            color = '#9a6700';
        }
        return '<div class="itm-ping-line" style="display:block;margin-top:6px;color:' + color + ' !important;">' + html + '</div>';
    }

    function escapeHtml(value) {
        return String(value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function runPingCheck() {
        if (pingBusy) { return; }
        const ip = (pingIpInput.value || '').trim();
        const port = pingPortInput ? (pingPortInput.value || '').trim() : '';
        if (ip === '') {
            setPingMessage(pingRow('Enter a Ping IP address.', 'fail'));
            pingIpInput.focus();
            return;
        }

        pingBusy = true;
        pingBtn.disabled = true;
        setPingMessage(pingRow('Checking TCP connectivity' + (port !== '' ? ' on port ' + escapeHtml(port) : '') + '…', 'neutral'));

        const body = new URLSearchParams();
        body.set('ping_ip_check', '1');
        body.set('ping_ip', ip);
        body.set('ping_port', port);
        body.set('csrf_token', pingCsrf);

        fetch(pingEndpoint, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
            body: body.toString(),
            credentials: 'same-origin'
        })
            .then(function (response) {
                return response.json().then(function (data) {
                    return { ok: response.ok, data: data };
                });
            })
            .then(function (result) {
                if (!result.ok || !result.data || !result.data.ok) {
                    throw new Error((result.data && result.data.error) ? result.data.error : 'Ping check failed.');
                }

                const lines = [];
                const pingData = result.data.ping || {};
                const pingReachable = !!pingData.reachable;
                const userRequestedPort = port !== '';
                const requestedPortNum = userRequestedPort ? parseInt(port, 10) : 0;
                const portUsedNum = pingData.port_used ? parseInt(String(pingData.port_used), 10) : 0;
                const portData = result.data.port;
                const portOpen = !!(portData && portData.open);
                const usedFallbackPort = userRequestedPort
                    && pingReachable
                    && requestedPortNum > 0
                    && portUsedNum > 0
                    && portUsedNum !== requestedPortNum
                    && !portOpen;
                const probeLabel = (pingData.method === 'tcp') ? 'TCP reachability' : 'Reachability';
                let reachabilityText = pingReachable ? 'Reachable' : 'No response';
                if (usedFallbackPort) {
                    reachabilityText = 'Reachable on port ' + portUsedNum + ' (not port ' + requestedPortNum + ')';
                }

                var reachTone = 'fail';
                if (usedFallbackPort) {
                    reachTone = 'warn';
                } else if (pingReachable) {
                    reachTone = 'ok';
                }
                lines.push(pingRow('<strong>' + probeLabel + ':</strong> ' + escapeHtml(reachabilityText), reachTone));

                if (pingData.port_used) {
                    lines.push(pingRow('Port used: ' + escapeHtml(String(pingData.port_used)), pingReachable ? 'ok' : 'fail'));
                }
                if (pingData.response_ms !== null && pingData.response_ms !== undefined && pingData.response_ms !== '') {
                    lines.push(pingRow('Response time: ' + escapeHtml(String(pingData.response_ms)) + ' ms', pingReachable ? 'ok' : 'fail'));
                }
                if (pingData.message) {
                    var messageTone = 'neutral';
                    if (usedFallbackPort || (pingReachable && !portOpen && userRequestedPort)) {
                        messageTone = 'ok';
                    } else if (!pingReachable) {
                        messageTone = 'fail';
                    } else if (pingReachable) {
                        messageTone = 'ok';
                    }
                    lines.push(pingRow(escapeHtml(pingData.message), messageTone));
                }
                if (Array.isArray(pingData.alternatives_tried) && pingData.alternatives_tried.length > 1 && !pingReachable) {
                    lines.push(pingRow('Tried ports:', 'neutral'));
                    pingData.alternatives_tried.forEach(function (attempt) {
                        const attemptPort = attempt && attempt.port ? String(attempt.port) : '?';
                        const attemptState = attempt && attempt.reachable ? 'open' : 'closed';
                        lines.push(pingRow('• ' + escapeHtml(attemptPort) + ' — ' + escapeHtml(attemptState), attempt && attempt.reachable ? 'ok' : 'fail'));
                    });
                }

                if (portData) {
                    lines.push(pingRow('<strong>Requested port:</strong> ' + (portOpen ? 'Open' : 'Closed / filtered'), portOpen ? 'ok' : 'fail'));
                    if (portData.message) {
                        lines.push(pingRow(escapeHtml(portData.message), portOpen ? 'ok' : 'fail'));
                    }
                }

                setPingMessage(lines.join(''));
            })
            .catch(function (error) {
                setPingMessage(pingRow(escapeHtml(error && error.message ? error.message : 'Ping check failed.'), 'fail'));
            })
            .finally(function () {
                pingBusy = false;
                pingBtn.disabled = false;
            });
    }

    if (hasPingUi) {
        pingBtn.addEventListener('click', runPingCheck);
        pingIpInput.addEventListener('keydown', function (event) {
            if (event.key === 'Enter') {
                event.preventDefault();
                runPingCheck();
            }
        });
        if (pingPortInput) {
            pingPortInput.addEventListener('keydown', function (event) {
                if (event.key === 'Enter') {
                    event.preventDefault();
                    runPingCheck();
                }
            });
        }
    }

    const discoveryScanBtn = document.getElementById('itmDiscoveryScanBtn');
    const discoveryStopBtn = document.getElementById('itmDiscoveryStopBtn');
    const discoveryImportBtn = document.getElementById('itmDiscoveryImportBtn');
    const discoveryRangeStart = document.getElementById('itmDiscoveryRangeStart');
    const discoveryRangeEnd = document.getElementById('itmDiscoveryRangeEnd');
    const discoveryStatus = document.getElementById('itmDiscoveryStatus');
    const discoveryResults = document.getElementById('itmDiscoveryResults');
    const discoveryProgressWrap = document.getElementById('itmDiscoveryProgressWrap');
    const discoveryProgressLabel = document.getElementById('itmDiscoveryProgressLabel');
    const discoveryProgressPct = document.getElementById('itmDiscoveryProgressPct');
    const discoveryProgressBar = document.getElementById('itmDiscoveryProgressBar');
    const discoveryProgressTrack = document.getElementById('itmDiscoveryProgressTrack');
    const discoveryLog = document.getElementById('itmDiscoveryLog');
    const DISCOVERY_BATCH_SIZE = 5;
    const IMPORT_BATCH_SIZE = 5;
    let discoveryBusy = false;
    let discoveryStopRequested = false;
    let discoveryAbortController = null;
    let discoveryHosts = [];

    function escapeHtmlDiscovery(value) {
        return String(value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function setDiscoveryStatus(html, tone) {
        if (!discoveryStatus) { return; }
        discoveryStatus.innerHTML = html;
        discoveryStatus.style.color = tone === 'ok' ? '#1a7f37' : (tone === 'fail' ? '#cf222e' : (tone === 'warn' ? '#9a6700' : '#57606a'));
    }

    function discoveryLogColor(level) {
        if (level === 'ok') { return '#1a7f37'; }
        if (level === 'fail') { return '#cf222e'; }
        if (level === 'warn') { return '#9a6700'; }
        if (level === 'muted') { return '#8b949e'; }
        return '#24292f';
    }

    function clearDiscoveryLog() {
        if (discoveryLog) {
            discoveryLog.innerHTML = '';
            discoveryLog.style.display = 'none';
        }
    }

    function appendDiscoveryLog(activities) {
        if (!discoveryLog || !activities || !activities.length) { return; }
        discoveryLog.style.display = 'block';
        activities.forEach(function (entry) {
            const line = document.createElement('div');
            line.style.color = discoveryLogColor(entry.level || 'info');
            line.textContent = entry.message || '';
            discoveryLog.appendChild(line);
        });
        discoveryLog.scrollTop = discoveryLog.scrollHeight;
    }

    function setDiscoveryProgress(percent, label) {
        const pct = Math.max(0, Math.min(100, Math.round(percent)));
        if (discoveryProgressWrap) { discoveryProgressWrap.style.display = 'block'; }
        if (discoveryProgressBar) { discoveryProgressBar.style.width = pct + '%'; }
        if (discoveryProgressPct) { discoveryProgressPct.textContent = pct + '%'; }
        if (discoveryProgressLabel && label) { discoveryProgressLabel.textContent = label; }
        if (discoveryProgressTrack) { discoveryProgressTrack.setAttribute('aria-valuenow', String(pct)); }
    }

    function hideDiscoveryProgress() {
        if (discoveryProgressWrap) { discoveryProgressWrap.style.display = 'none'; }
        if (discoveryProgressBar) { discoveryProgressBar.style.width = '0%'; }
        if (discoveryProgressPct) { discoveryProgressPct.textContent = '0%'; }
        if (discoveryProgressTrack) { discoveryProgressTrack.setAttribute('aria-valuenow', '0'); }
    }

    function setDiscoveryRunningUi(running) {
        if (discoveryScanBtn) { discoveryScanBtn.disabled = running; }
        if (discoveryImportBtn && running) { discoveryImportBtn.style.display = 'none'; }
        if (discoveryStopBtn) { discoveryStopBtn.style.display = running ? 'inline-block' : 'none'; }
        if (discoveryRangeStart) { discoveryRangeStart.disabled = running; }
        if (discoveryRangeEnd) { discoveryRangeEnd.disabled = running; }
    }

    function requestDiscoveryStop() {
        if (!discoveryBusy) { return; }
        discoveryStopRequested = true;
        if (discoveryAbortController) {
            discoveryAbortController.abort();
        }
        appendDiscoveryLog([{ level: 'warn', message: 'Stop requested — finishing current step…' }]);
        setDiscoveryStatus('Stopping…', 'warn');
        if (discoveryStopBtn) { discoveryStopBtn.disabled = true; }
    }

    function discoveryPost(actionKey, fields) {
        const body = new URLSearchParams();
        body.set(actionKey, '1');
        body.set('csrf_token', pingCsrf);
        Object.keys(fields).forEach(function (key) {
            body.set(key, fields[key]);
        });
        discoveryAbortController = new AbortController();
        return fetch(pingEndpoint, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
            body: body.toString(),
            credentials: 'same-origin',
            signal: discoveryAbortController.signal
        }).then(function (response) {
            return response.json().then(function (data) {
                return { ok: response.ok, data: data };
            });
        });
    }

    function renderDiscoveryResults(hosts) {
        if (!discoveryResults) { return; }
        if (!hosts || !hosts.length) {
            discoveryResults.innerHTML = '<p style="color:#57606a;">No responding hosts were found in this range.</p>';
            if (discoveryImportBtn) { discoveryImportBtn.style.display = 'none'; }
            return;
        }

        let html = '<table style="width:100%;border-collapse:collapse;font-size:14px;"><thead><tr>';
        html += '<th style="text-align:left;padding:6px;border-bottom:1px solid #d0d7de;">IP</th>';
        html += '<th style="text-align:left;padding:6px;border-bottom:1px solid #d0d7de;">Port</th>';
        html += '<th style="text-align:left;padding:6px;border-bottom:1px solid #d0d7de;">Subnet</th>';
        html += '<th style="text-align:left;padding:6px;border-bottom:1px solid #d0d7de;">Equipment</th>';
        html += '<th style="text-align:left;padding:6px;border-bottom:1px solid #d0d7de;">Domains (IP2WHOIS)</th>';
        html += '<th style="text-align:left;padding:6px;border-bottom:1px solid #d0d7de;">Inventory</th>';
        html += '</tr></thead><tbody>';

        hosts.forEach(function (host) {
            const inInventory = !!host.in_inventory;
            const canImport = !inInventory && parseInt(host.subnet_id || '0', 10) > 0;
            html += '<tr>';
            html += '<td style="padding:6px;border-bottom:1px solid #eef1f4;">' + escapeHtmlDiscovery(host.ip || '') + '</td>';
            html += '<td style="padding:6px;border-bottom:1px solid #eef1f4;">' + escapeHtmlDiscovery(host.port_used || '—') + '</td>';
            html += '<td style="padding:6px;border-bottom:1px solid #eef1f4;">' + escapeHtmlDiscovery(host.subnet_cidr || '—') + '</td>';
            html += '<td style="padding:6px;border-bottom:1px solid #eef1f4;">' + escapeHtmlDiscovery(host.equipment_label || '—') + '</td>';
            let domainCell = '—';
            if (host.domains && host.domains.length) {
                domainCell = escapeHtmlDiscovery(host.domains.slice(0, 3).join(', '));
                if (host.domains.length > 3 || (parseInt(host.total_domains || '0', 10) > host.domains.length)) {
                    const totalDomains = parseInt(host.total_domains || String(host.domains.length), 10);
                    domainCell += ' <span style="color:#57606a;">(' + totalDomains + ' total)</span>';
                }
            } else if (host.domain_primary) {
                domainCell = escapeHtmlDiscovery(host.domain_primary);
            }
            html += '<td style="padding:6px;border-bottom:1px solid #eef1f4;">' + domainCell + '</td>';
            html += '<td style="padding:6px;border-bottom:1px solid #eef1f4;color:' + (inInventory ? '#1a7f37' : (canImport ? '#9a6700' : '#cf222e')) + ';">';
            html += inInventory ? 'In inventory' : (canImport ? 'Ready to add' : 'No matching subnet');
            html += '</td></tr>';
        });

        html += '</tbody></table>';
        discoveryResults.innerHTML = html;

        const importable = hosts.filter(function (host) {
            return !host.in_inventory && parseInt(host.subnet_id || '0', 10) > 0;
        });
        if (discoveryImportBtn) {
            discoveryImportBtn.style.display = importable.length ? 'inline-block' : 'none';
            discoveryImportBtn.textContent = 'Add ' + importable.length + ' to inventory';
        }
    }

    function runDiscoveryScanBatch(rangeStart, rangeEnd, offset, totalHosts) {
        if (discoveryStopRequested) {
            return Promise.resolve({ stopped: true });
        }

        return discoveryPost('network_discovery_scan', {
            range_start: rangeStart,
            range_end: rangeEnd,
            batch_offset: String(offset),
            batch_size: String(DISCOVERY_BATCH_SIZE)
        }).then(function (result) {
            if (!result.ok || !result.data || !result.data.ok) {
                throw new Error((result.data && result.data.error) ? result.data.error : 'Scan failed.');
            }
            const data = result.data;
            const batchHosts = data.hosts || [];
            discoveryHosts = discoveryHosts.concat(batchHosts);
            appendDiscoveryLog(data.activities || []);

            const total = parseInt(data.total || String(totalHosts || 0), 10);
            const nextOffset = parseInt(data.next_offset || '0', 10);
            const pct = total > 0 ? (nextOffset / total) * 100 : 100;
            setDiscoveryProgress(pct, data.detail || ('Scanning ' + nextOffset + ' of ' + total + '…'));
            setDiscoveryStatus(data.detail || 'Scanning…', 'neutral');

            if (discoveryStopRequested) {
                return { stopped: true, total: total, found: discoveryHosts.length };
            }
            if (!data.complete && nextOffset < total) {
                return runDiscoveryScanBatch(rangeStart, rangeEnd, nextOffset, total);
            }
            return { stopped: false, total: total, found: discoveryHosts.length };
        });
    }

    function runDiscoveryScan() {
        if (discoveryBusy || !discoveryScanBtn) { return; }
        const rangeStart = (discoveryRangeStart && discoveryRangeStart.value || '').trim();
        const rangeEnd = (discoveryRangeEnd && discoveryRangeEnd.value || '').trim();
        if (rangeStart === '' || rangeEnd === '') {
            setDiscoveryStatus('Enter beginning and end of the IP range.', 'fail');
            return;
        }

        discoveryBusy = true;
        discoveryStopRequested = false;
        discoveryHosts = [];
        setDiscoveryRunningUi(true);
        if (discoveryStopBtn) { discoveryStopBtn.disabled = false; }
        clearDiscoveryLog();
        setDiscoveryProgress(0, 'Preparing scan…');
        setDiscoveryStatus('Starting network discovery (TCP, no shell)…', 'neutral');
        if (discoveryResults) { discoveryResults.innerHTML = ''; }

        runDiscoveryScanBatch(rangeStart, rangeEnd, 0, 0)
            .then(function (outcome) {
                if (outcome && outcome.stopped) {
                    setDiscoveryProgress(outcome.total > 0 ? (discoveryHosts.length / outcome.total) * 100 : 0, 'Scan stopped');
                    setDiscoveryStatus(
                        'Scan stopped. Scanned partial range; found ' + discoveryHosts.length + ' responding host(s).',
                        discoveryHosts.length > 0 ? 'warn' : 'fail'
                    );
                } else {
                    setDiscoveryProgress(100, 'Scan complete');
                    const found = discoveryHosts.length;
                    setDiscoveryStatus(
                        'Scanned ' + (outcome && outcome.total ? outcome.total : 'all') + ' address(es); found ' + found + ' responding host(s).',
                        found > 0 ? 'ok' : 'fail'
                    );
                }
                renderDiscoveryResults(discoveryHosts);
            })
            .catch(function (error) {
                if (error && error.name === 'AbortError') {
                    setDiscoveryStatus('Scan stopped.', 'warn');
                    renderDiscoveryResults(discoveryHosts);
                    return;
                }
                setDiscoveryStatus(escapeHtmlDiscovery(error && error.message ? error.message : 'Scan failed.'), 'fail');
                if (discoveryResults && !discoveryHosts.length) { discoveryResults.innerHTML = ''; }
            })
            .finally(function () {
                discoveryBusy = false;
                discoveryStopRequested = false;
                discoveryAbortController = null;
                setDiscoveryRunningUi(false);
                if (discoveryImportBtn) { discoveryImportBtn.disabled = false; }
            });
    }

    function runDiscoveryImportBatch(importableIps, offset, totals) {
        if (discoveryStopRequested) {
            return Promise.resolve({ stopped: true, added: totals.added, skipped: totals.skipped });
        }

        return discoveryPost('network_discovery_import', {
            host_ips: JSON.stringify(importableIps),
            batch_offset: String(offset),
            batch_size: String(IMPORT_BATCH_SIZE)
        }).then(function (result) {
            if (!result.ok || !result.data || !result.data.ok) {
                throw new Error((result.data && result.data.error) ? result.data.error : 'Import failed.');
            }
            const data = result.data;
            totals.added += parseInt(data.added || '0', 10);
            totals.skipped += parseInt(data.skipped || '0', 10);
            appendDiscoveryLog(data.activities || []);

            const total = parseInt(data.total || String(importableIps.length), 10);
            const nextOffset = parseInt(data.next_offset || '0', 10);
            const pct = total > 0 ? (nextOffset / total) * 100 : 100;
            setDiscoveryProgress(pct, data.detail || ('Importing ' + nextOffset + ' of ' + total + '…'));
            setDiscoveryStatus(data.detail || 'Importing…', 'neutral');

            if (discoveryStopRequested) {
                return { stopped: true, added: totals.added, skipped: totals.skipped };
            }
            if (!data.complete && nextOffset < total) {
                return runDiscoveryImportBatch(importableIps, nextOffset, totals);
            }
            return { stopped: false, added: totals.added, skipped: totals.skipped };
        });
    }

    function runDiscoveryImport() {
        if (discoveryBusy || !discoveryImportBtn) { return; }
        const importableIps = discoveryHosts
            .filter(function (host) {
                return !host.in_inventory && parseInt(host.subnet_id || '0', 10) > 0;
            })
            .map(function (host) { return host.ip; });

        if (!importableIps.length) {
            setDiscoveryStatus('No discovered hosts are ready to import.', 'fail');
            return;
        }

        discoveryBusy = true;
        discoveryStopRequested = false;
        discoveryImportBtn.disabled = true;
        setDiscoveryRunningUi(true);
        if (discoveryStopBtn) { discoveryStopBtn.disabled = false; }
        clearDiscoveryLog();
        setDiscoveryProgress(0, 'Preparing import…');
        setDiscoveryStatus('Adding ' + importableIps.length + ' host(s) to inventory…', 'neutral');

        const totals = { added: 0, skipped: 0 };
        runDiscoveryImportBatch(importableIps, 0, totals)
            .then(function (outcome) {
                const added = outcome ? outcome.added : totals.added;
                const skipped = outcome ? outcome.skipped : totals.skipped;
                if (outcome && outcome.stopped) {
                    setDiscoveryProgress(0, 'Import stopped');
                    setDiscoveryStatus('Import stopped. Added ' + added + ' host(s); skipped ' + skipped + '.', added > 0 ? 'warn' : 'fail');
                } else {
                    setDiscoveryProgress(100, 'Import complete');
                    setDiscoveryStatus('Added ' + added + ' host(s) to inventory. Skipped ' + skipped + '.', added > 0 ? 'ok' : 'fail');
                }
                if (!(outcome && outcome.stopped)) {
                    discoveryHosts.forEach(function (host) {
                        if (importableIps.indexOf(host.ip) !== -1) {
                            host.in_inventory = true;
                        }
                    });
                }
                renderDiscoveryResults(discoveryHosts);
            })
            .catch(function (error) {
                if (error && error.name === 'AbortError') {
                    setDiscoveryStatus('Import stopped.', 'warn');
                    return;
                }
                setDiscoveryStatus(escapeHtmlDiscovery(error && error.message ? error.message : 'Import failed.'), 'fail');
            })
            .finally(function () {
                discoveryBusy = false;
                discoveryStopRequested = false;
                discoveryAbortController = null;
                setDiscoveryRunningUi(false);
                discoveryImportBtn.disabled = false;
                hideDiscoveryProgress();
            });
    }

    if (discoveryScanBtn) {
        discoveryScanBtn.addEventListener('click', runDiscoveryScan);
    }
    if (discoveryStopBtn) {
        discoveryStopBtn.addEventListener('click', requestDiscoveryStop);
    }
    if (discoveryImportBtn) {
        discoveryImportBtn.addEventListener('click', runDiscoveryImport);
    }
})();
</script>
<?php endif; ?>
<?php if (($crud_table ?? '') === 'ip_subnets' && in_array($crud_action, ['create', 'edit'], true)): ?>
<script>
(function () {
    const form = document.querySelector('form.form-grid');
    if (!form) { return; }

    const cidrInput = form.querySelector('input[name="cidr"]');
    if (!cidrInput) { return; }

    const cidrPattern = /^\d{1,3}(\.\d{1,3}){3}(\/\d{1,2})?$/;

    form.addEventListener('submit', function (event) {
        const value = (cidrInput.value || '').trim();
        if (value === '') {
            event.preventDefault();
            window.alert('CIDR is required.');
            cidrInput.focus();
            return;
        }
        if (!cidrPattern.test(value)) {
            event.preventDefault();
            window.alert('CIDR must look like 10.0.0.0/24.');
            cidrInput.focus();
        }
    });
})();
</script>
<?php endif; ?>
</body>
</html>
