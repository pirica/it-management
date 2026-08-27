<?php
/**
 * Network Discovery v2 — discovery profiles and staging review queue.
 */

require_once dirname(__DIR__, 2) . '/config/config.php';
require_once ROOT_PATH . 'includes/itm_network_discovery.php';

$companyId = (int)($company_id ?? 0);
$employeeId = (int)($_SESSION['employee_id'] ?? 0);
$isAdmin = function_exists('itm_is_admin') && itm_is_admin();
$csrfToken = itm_get_csrf_token();
$flash = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    itm_require_post_csrf();
    $postAction = trim((string)($_POST['nd_action'] ?? ''));

    if ($postAction === 'save_profile' && $isAdmin) {
        $subnetIds = $_POST['subnet_ids'] ?? [];
        if (!is_array($subnetIds)) {
            $subnetIds = [];
        }
        $result = itm_network_discovery_save_profile($conn, $companyId, [
            'id' => (int)($_POST['profile_id'] ?? 0),
            'name' => $_POST['name'] ?? '',
            'schedule_cron' => $_POST['schedule_cron'] ?? '',
            'snmp_enabled' => $_POST['snmp_enabled'] ?? '',
            'enabled' => $_POST['enabled'] ?? '',
            'auto_create_policy' => $_POST['auto_create_policy'] ?? 'review',
            'subnet_ids' => $subnetIds,
        ], $employeeId);
        $flash = !empty($result['ok']) ? 'Profile saved.' : (string)($result['error'] ?? 'Could not save profile.');
    } elseif ($postAction === 'delete_profile' && $isAdmin) {
        $result = itm_network_discovery_delete_profile($conn, $companyId, (int)($_POST['profile_id'] ?? 0), $employeeId);
        $flash = !empty($result['ok']) ? 'Profile deleted.' : (string)($result['error'] ?? 'Could not delete profile.');
    } elseif ($postAction === 'run_profile' && $isAdmin) {
        $profileId = (int)($_POST['profile_id'] ?? 0);
        $batch = itm_network_discovery_profile_run_batch($conn, $profileId, $employeeId);
        $flash = !empty($batch['ok'])
            ? (string)($batch['detail'] ?? 'Scan batch finished.')
            : (string)($batch['error'] ?? 'Scan failed.');
    }
}

$activeTab = strtolower(trim((string)($_GET['tab'] ?? 'staging')));
if (!in_array($activeTab, ['profiles', 'staging'], true)) {
    $activeTab = 'staging';
}

$profiles = itm_network_discovery_list_profiles($conn, $companyId);
$stagingStatus = trim((string)($_GET['status'] ?? 'pending'));
$profileFilter = (int)($_GET['profile_id'] ?? 0);
$perPage = itm_resolve_records_per_page($ui_config ?? null);
$page = max(1, (int)($_GET['page'] ?? 1));
$stagingData = itm_network_discovery_list_staging($conn, $companyId, $stagingStatus, $profileFilter, $page, $perPage);
$stagingRows = $stagingData['rows'] ?? [];
$stagingTotal = (int)($stagingData['total'] ?? 0);
$stagingPages = max(1, (int)ceil($stagingTotal / $perPage));

$subnetOptions = [];
$subnetStmt = mysqli_prepare(
    $conn,
    'SELECT id, cidr, description FROM ip_subnets WHERE company_id = ? AND deleted_at IS NULL AND active = 1 ORDER BY cidr ASC'
);
if ($subnetStmt) {
    mysqli_stmt_bind_param($subnetStmt, 'i', $companyId);
    mysqli_stmt_execute($subnetStmt);
    $subnetRes = mysqli_stmt_get_result($subnetStmt);
    while ($subnetRes && ($subnetRow = mysqli_fetch_assoc($subnetRes))) {
        $subnetOptions[] = $subnetRow;
    }
    mysqli_stmt_close($subnetStmt);
}

$equipmentOptions = [];
$eqStmt = mysqli_prepare(
    $conn,
    'SELECT id, name, hostname, ip_address FROM equipment WHERE company_id = ? AND deleted_at IS NULL AND active = 1 ORDER BY name ASC LIMIT 500'
);
if ($eqStmt) {
    mysqli_stmt_bind_param($eqStmt, 'i', $companyId);
    mysqli_stmt_execute($eqStmt);
    $eqRes = mysqli_stmt_get_result($eqStmt);
    while ($eqRes && ($eqRow = mysqli_fetch_assoc($eqRes))) {
        $equipmentOptions[] = $eqRow;
    }
    mysqli_stmt_close($eqStmt);
}

$moduleSlug = basename(dirname($_SERVER['PHP_SELF']));
$pageTitle = 'Network Discovery';
$resolvedIcon = itm_resolve_module_sidebar_icon($conn, $companyId, $employeeId, $moduleSlug);
$moduleListHeading = trim($resolvedIcon . ' ' . itm_module_access_strip_catalog_label_prefix($pageTitle));
$currentUiConfig = $ui_config ?? [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php
    require_once ROOT_PATH . 'includes/itm_crud_browser_title.php';
    $crud_title = itm_crud_apply_module_icon_to_browser_title($conn, $companyId, $employeeId, $moduleSlug, $pageTitle);
    ?>
    <title><?php echo sanitize($crud_title); ?> - <?php echo sanitize($app_name ?? itm_ui_config_app_name($currentUiConfig)); ?></title>
    <?php echo itm_render_head_favicon_link($favicon_url ?? null); ?>
    <link rel="stylesheet" href="../../css/styles.css">
</head>
<body>
<div class="container">
    <?php include '../../includes/sidebar.php'; ?>
    <div class="main-content">
        <?php include '../../includes/header.php'; ?>
        <div class="content">
            <?php if ($flash !== ''): ?>
                <div class="alert alert-success"><?php echo sanitize($flash); ?></div>
            <?php endif; ?>

            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;flex-wrap:wrap;gap:12px;">
                <h1 style="margin:0;" title="Network Discovery"><?php echo sanitize($moduleListHeading); ?></h1>
                <div style="display:flex;gap:8px;flex-wrap:wrap;">
                    <a class="btn btn-sm" href="../ip_subnets/index.php" title="IP Subnets">🧭</a>
                </div>
            </div>

            <div class="card" style="margin-bottom:16px;padding:12px;display:flex;gap:8px;flex-wrap:wrap;">
                <a class="btn btn-sm <?php echo $activeTab === 'staging' ? 'btn-primary' : ''; ?>" href="index.php?tab=staging">Staging</a>
                <a class="btn btn-sm <?php echo $activeTab === 'profiles' ? 'btn-primary' : ''; ?>" href="index.php?tab=profiles">Profiles</a>
                <a class="btn btn-sm" href="../ip_subnets/index.php">IP Subnets list</a>
            </div>

            <?php if ($activeTab === 'profiles'): ?>
                <?php if (!$isAdmin): ?>
                    <div class="alert alert-warning">Only administrators can manage discovery profiles.</div>
                <?php endif; ?>

                <div class="card" style="margin-bottom:16px;padding:16px;">
                    <h2 style="margin-top:0;font-size:18px;">New profile</h2>
                    <?php if ($isAdmin): ?>
                    <form method="POST" style="display:grid;gap:12px;max-width:720px;">
                        <input type="hidden" name="csrf_token" value="<?php echo sanitize($csrfToken); ?>">
                        <input type="hidden" name="nd_action" value="save_profile">
                        <input type="hidden" name="profile_id" value="0">
                        <div class="form-group">
                            <label for="nd-name">Name</label>
                            <input type="text" id="nd-name" name="name" required maxlength="120" placeholder="Nightly office LAN">
                        </div>
                        <div class="form-group">
                            <label for="nd-cron">Schedule cron (minute hour dom month dow)</label>
                            <input type="text" id="nd-cron" name="schedule_cron" required placeholder="0 2 * * *" value="0 2 * * *">
                        </div>
                        <div class="form-group">
                            <label>Subnets</label>
                            <div style="display:flex;flex-wrap:wrap;gap:8px;">
                                <?php foreach ($subnetOptions as $subnetOpt): ?>
                                    <label class="itm-checkbox-control" style="margin:0;">
                                        <input type="checkbox" name="subnet_ids[]" value="<?php echo (int)$subnetOpt['id']; ?>">
                                        <span><?php echo sanitize((string)$subnetOpt['cidr']); ?></span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="nd-policy">Auto-create policy</label>
                            <select id="nd-policy" name="auto_create_policy">
                                <option value="review">review — staging queue only</option>
                                <option value="none">none — scan only, no staging auto-promote</option>
                                <option value="equipment">equipment — auto-create assets on discovery</option>
                            </select>
                        </div>
                        <label class="itm-checkbox-control">
                            <input type="checkbox" name="snmp_enabled" value="1">
                            <span>SNMP sysName (optional PHP snmp extension)</span>
                        </label>
                        <label class="itm-checkbox-control">
                            <input type="checkbox" name="enabled" value="1" checked>
                            <span>Enabled</span>
                        </label>
                        <button type="submit" class="btn btn-primary" title="Save">💾</button>
                    </form>
                    <?php endif; ?>
                </div>

                <div class="card" style="overflow:auto;">
                    <table>
                        <thead>
                        <tr>
                            <th>Name</th>
                            <th>Schedule</th>
                            <th>Policy</th>
                            <th>SNMP</th>
                            <th>Enabled</th>
                            <th>Last run</th>
                            <th>Scan</th>
                            <th class="itm-actions-cell" data-itm-actions-origin="1">Actions</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php if ($profiles === []): ?>
                            <tr><td colspan="8" style="text-align:center;">No profiles yet.</td></tr>
                        <?php else: foreach ($profiles as $profile): ?>
                            <?php
                            $subnetIds = itm_network_discovery_decode_subnet_ids((string)($profile['subnet_ids_json'] ?? '[]'));
                            $subnetLabels = [];
                            foreach ($subnetOptions as $subnetOpt) {
                                if (in_array((int)$subnetOpt['id'], $subnetIds, true)) {
                                    $subnetLabels[] = (string)$subnetOpt['cidr'];
                                }
                            }
                            ?>
                            <tr>
                                <td><?php echo sanitize((string)($profile['name'] ?? '')); ?></td>
                                <td><code><?php echo sanitize((string)($profile['schedule_cron'] ?? '')); ?></code></td>
                                <td><?php echo sanitize((string)($profile['auto_create_policy'] ?? '')); ?></td>
                                <td><?php echo (int)($profile['snmp_enabled'] ?? 0) === 1 ? 'Yes' : 'No'; ?></td>
                                <td><?php echo (int)($profile['enabled'] ?? 0) === 1 ? 'Yes' : 'No'; ?></td>
                                <td><?php echo sanitize(itm_format_cell_scalar_display('last_run_at', $profile['last_run_at'] ?? null)); ?></td>
                                <td><?php echo (int)($profile['scan_in_progress'] ?? 0) === 1 ? 'In progress' : '—'; ?></td>
                                <td class="itm-actions-cell" data-itm-actions-origin="1">
                                    <?php if ($isAdmin): ?>
                                    <form method="POST" style="display:inline;">
                                        <input type="hidden" name="csrf_token" value="<?php echo sanitize($csrfToken); ?>">
                                        <input type="hidden" name="nd_action" value="run_profile">
                                        <input type="hidden" name="profile_id" value="<?php echo (int)$profile['id']; ?>">
                                        <button type="submit" class="btn btn-sm" title="Run scan batch">▶️</button>
                                    </form>
                                    <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this profile?');">
                                        <input type="hidden" name="csrf_token" value="<?php echo sanitize($csrfToken); ?>">
                                        <input type="hidden" name="nd_action" value="delete_profile">
                                        <input type="hidden" name="profile_id" value="<?php echo (int)$profile['id']; ?>">
                                        <button type="submit" class="btn btn-sm btn-danger" title="Delete">🗑️</button>
                                    </form>
                                    <?php else: ?>—<?php endif; ?>
                                    <small style="display:block;color:#57606a;margin-top:4px;"><?php echo sanitize(implode(', ', $subnetLabels)); ?></small>
                                </td>
                            </tr>
                        <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="card" style="margin-bottom:16px;padding:12px;">
                    <form method="GET" style="display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end;">
                        <input type="hidden" name="tab" value="staging">
                        <div class="form-group" style="margin:0;">
                            <label for="nd-status">Status</label>
                            <select id="nd-status" name="status">
                                <?php foreach (['pending', 'promoted', 'dismissed', 'all'] as $st): ?>
                                    <option value="<?php echo $st; ?>" <?php echo $stagingStatus === $st ? 'selected' : ''; ?>><?php echo sanitize($st); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group" style="margin:0;">
                            <label for="nd-profile-filter">Profile</label>
                            <select id="nd-profile-filter" name="profile_id">
                                <option value="0">All profiles</option>
                                <?php foreach ($profiles as $profile): ?>
                                    <option value="<?php echo (int)$profile['id']; ?>" <?php echo $profileFilter === (int)$profile['id'] ? 'selected' : ''; ?>>
                                        <?php echo sanitize((string)$profile['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <button type="submit" class="btn btn-primary">Search</button>
                    </form>
                </div>

                <div class="card" style="overflow:auto;">
                    <table data-itm-no-import-excel="1" data-itm-no-export-excel="1" data-itm-no-export-pdf="1">
                        <thead>
                        <tr>
                            <th>IP</th>
                            <th>Hostname guess</th>
                            <th>Profile</th>
                            <th>Reachability</th>
                            <th>Status</th>
                            <th class="itm-actions-cell" data-itm-actions-origin="1">Actions</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php if ($stagingRows === []): ?>
                            <tr><td colspan="6" style="text-align:center;">No staging rows.</td></tr>
                        <?php else: foreach ($stagingRows as $stagingRow): ?>
                            <?php
                            $probe = json_decode((string)($stagingRow['probe_json'] ?? '{}'), true);
                            if (!is_array($probe)) {
                                $probe = [];
                            }
                            $portUsed = $probe['port_used'] ?? '';
                            $responseMs = $probe['response_ms'] ?? '';
                            $httpServer = (string)($probe['http_server'] ?? '');
                            $snmpName = (string)($probe['snmp_sysname'] ?? '');
                            $matchedEq = (int)($probe['equipment_id'] ?? 0);
                            $reachLabel = $portUsed !== '' ? 'TCP ' . $portUsed . ' (' . $responseMs . ' ms)' : '—';
                            if ($httpServer !== '') {
                                $reachLabel .= '; HTTP ' . $httpServer;
                            }
                            if ($snmpName !== '') {
                                $reachLabel .= '; SNMP ' . $snmpName;
                            }
                            $rowStatus = (string)($stagingRow['status'] ?? '');
                            $stagingId = (int)($stagingRow['id'] ?? 0);
                            ?>
                            <tr>
                                <td><code><?php echo sanitize((string)($stagingRow['ip_address'] ?? '')); ?></code></td>
                                <td><?php echo sanitize((string)($stagingRow['hostname_guess'] ?? '')); ?></td>
                                <td><?php echo sanitize((string)($stagingRow['profile_name'] ?? '')); ?></td>
                                <td style="font-size:13px;color:#57606a;"><?php echo sanitize($reachLabel); ?></td>
                                <td><?php echo sanitize($rowStatus); ?></td>
                                <td class="itm-actions-cell" data-itm-actions-origin="1">
                                    <?php if ($rowStatus === 'pending'): ?>
                                        <button type="button" class="btn btn-sm btn-primary nd-promote-btn" data-staging-id="<?php echo $stagingId; ?>" title="Promote to equipment">➕</button>
                                        <button type="button" class="btn btn-sm nd-link-btn" data-staging-id="<?php echo $stagingId; ?>" data-matched-eq="<?php echo $matchedEq; ?>" title="Link existing">🔗</button>
                                        <button type="button" class="btn btn-sm nd-dismiss-btn" data-staging-id="<?php echo $stagingId; ?>" title="Dismiss">🗑️</button>
                                    <?php elseif ($rowStatus === 'promoted' && (int)($stagingRow['promoted_equipment_id'] ?? 0) > 0): ?>
                                        <a class="btn btn-sm" href="../equipment/view.php?id=<?php echo (int)$stagingRow['promoted_equipment_id']; ?>" title="View equipment">🔎</a>
                                    <?php else: ?>—<?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>

                <?php if ($stagingPages > 1): ?>
                <div style="margin-top:12px;display:flex;gap:8px;align-items:center;">
                    <?php
                    $baseQs = 'tab=staging&status=' . urlencode($stagingStatus) . '&profile_id=' . $profileFilter;
                    if ($page > 1): ?>
                        <a class="btn btn-sm" href="?<?php echo $baseQs; ?>&page=1" title="First page">⏮️</a>
                        <a class="btn btn-sm" href="?<?php echo $baseQs; ?>&page=<?php echo $page - 1; ?>" title="Previous page">◀️</a>
                    <?php endif; ?>
                    <span class="btn btn-sm" style="pointer-events:none;opacity:.8;">Page <?php echo $page; ?> of <?php echo $stagingPages; ?></span>
                    <?php if ($page < $stagingPages): ?>
                        <a class="btn btn-sm" href="?<?php echo $baseQs; ?>&page=<?php echo $page + 1; ?>" title="Next page">▶️</a>
                        <a class="btn btn-sm" href="?<?php echo $baseQs; ?>&page=<?php echo $stagingPages; ?>" title="Last page">⏭️</a>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<div id="nd-link-modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:9999;align-items:center;justify-content:center;">
    <div class="card" style="max-width:480px;width:90%;padding:20px;">
        <h3 style="margin-top:0;">Link to equipment</h3>
        <div class="form-group">
            <label for="nd-link-equipment">Equipment</label>
            <select id="nd-link-equipment" style="width:100%;">
                <option value="">— Select —</option>
                <?php foreach ($equipmentOptions as $eqOpt): ?>
                    <option value="<?php echo (int)$eqOpt['id']; ?>">
                        <?php echo sanitize(trim((string)$eqOpt['name'] . ' ' . (string)($eqOpt['hostname'] ?? '') . ' ' . (string)($eqOpt['ip_address'] ?? ''))); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <label class="itm-checkbox-control">
            <input type="checkbox" id="nd-link-ipam" value="1" checked>
            <span>Create IPAM row when missing</span>
        </label>
        <div style="display:flex;gap:8px;margin-top:16px;">
            <button type="button" class="btn btn-primary" id="nd-link-confirm" title="Save">💾</button>
            <button type="button" class="btn" id="nd-link-cancel" title="Cancel">🔙</button>
        </div>
    </div>
</div>

<script>
(function () {
    var csrf = <?php echo json_encode($csrfToken, JSON_UNESCAPED_UNICODE); ?>;
    var apiBase = 'api.php';
    var linkModal = document.getElementById('nd-link-modal');
    var linkStagingId = 0;

    function postAction(action, payload) {
        var body = new FormData();
        body.append('csrf_token', csrf);
        body.append('action', action);
        Object.keys(payload).forEach(function (key) {
            body.append(key, payload[key]);
        });
        return fetch(apiBase, { method: 'POST', body: body, credentials: 'same-origin' })
            .then(function (res) { return res.json(); });
    }

    document.querySelectorAll('.nd-promote-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var id = btn.getAttribute('data-staging-id');
            postAction('promote', { staging_id: id, create_ipam: '1' }).then(function (data) {
                if (data && data.success) {
                    window.location.reload();
                } else {
                    alert((data && data.message) || 'Promote failed.');
                }
            });
        });
    });

    document.querySelectorAll('.nd-dismiss-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var id = btn.getAttribute('data-staging-id');
            postAction('dismiss', { staging_id: id }).then(function (data) {
                if (data && data.success) {
                    window.location.reload();
                } else {
                    alert((data && data.message) || 'Dismiss failed.');
                }
            });
        });
    });

    document.querySelectorAll('.nd-link-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            linkStagingId = btn.getAttribute('data-staging-id');
            var matched = btn.getAttribute('data-matched-eq');
            var select = document.getElementById('nd-link-equipment');
            if (matched && select) {
                select.value = matched;
            }
            linkModal.style.display = 'flex';
        });
    });

    document.getElementById('nd-link-cancel').addEventListener('click', function () {
        linkModal.style.display = 'none';
    });

    document.getElementById('nd-link-confirm').addEventListener('click', function () {
        var eqId = document.getElementById('nd-link-equipment').value;
        var createIpam = document.getElementById('nd-link-ipam').checked ? '1' : '0';
        if (!eqId) {
            alert('Select equipment.');
            return;
        }
        postAction('link', { staging_id: linkStagingId, equipment_id: eqId, create_ipam: createIpam }).then(function (data) {
            if (data && data.success) {
                window.location.reload();
            } else {
                alert((data && data.message) || 'Link failed.');
            }
        });
    });
})();
</script>
</body>
</html>
