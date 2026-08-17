<?php
/**
 * SLA Command Center — proactive breach dashboard for open tickets with SLA due dates.
 */

$crud_title = 'SLA Command Center';

require_once dirname(__DIR__, 2) . '/config/config.php';

$tsdFlash = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['tsd_escalation_action'])) {
    itm_require_post_csrf();
    $tsdAction = strtolower(trim((string)$_POST['tsd_escalation_action']));
    if ($tsdAction === 'save_rule') {
        $ok = itm_ticket_sla_save_escalation_rule($conn, (int)$company_id, [
            'priority_id' => (int)($_POST['priority_id'] ?? 0),
            'breach_type' => (string)($_POST['breach_type'] ?? 'both'),
            'escalate_to_employee_id' => (int)($_POST['escalate_to_employee_id'] ?? 0),
            'created_by' => (int)($_SESSION['employee_id'] ?? 0),
        ]);
        $tsdFlash = $ok ? 'Escalation rule saved.' : 'Could not save escalation rule.';
    } elseif ($tsdAction === 'delete_rule') {
        $ok = itm_ticket_sla_delete_escalation_rule($conn, (int)$company_id, (int)($_POST['rule_id'] ?? 0), (int)($_SESSION['employee_id'] ?? 0));
        $tsdFlash = $ok ? 'Escalation rule removed.' : 'Could not remove escalation rule.';
    }
}

$escalationRules = itm_ticket_sla_list_escalation_rules($conn, (int)$company_id);
$tsdPriorities = [];
$prioStmt = mysqli_prepare($conn, 'SELECT id, name FROM ticket_priorities WHERE company_id = ? AND deleted_at IS NULL AND active = 1 ORDER BY name ASC');
if ($prioStmt) {
    mysqli_stmt_bind_param($prioStmt, 'i', $company_id);
    mysqli_stmt_execute($prioStmt);
    $prioRes = mysqli_stmt_get_result($prioStmt);
    while ($prioRes && ($prioRow = mysqli_fetch_assoc($prioRes))) {
        $tsdPriorities[] = $prioRow;
    }
    mysqli_stmt_close($prioStmt);
}
$tsdEmployees = [];
$empStmt = mysqli_prepare($conn, 'SELECT id, first_name, last_name, username FROM employees WHERE company_id = ? AND deleted_at IS NULL AND active = 1 ORDER BY first_name ASC, last_name ASC');
if ($empStmt) {
    mysqli_stmt_bind_param($empStmt, 'i', $company_id);
    mysqli_stmt_execute($empStmt);
    $empRes = mysqli_stmt_get_result($empStmt);
    while ($empRes && ($empRow = mysqli_fetch_assoc($empRes))) {
        $tsdEmployees[] = $empRow;
    }
    mysqli_stmt_close($empStmt);
}

$activeTab = strtolower(trim((string)($_GET['tab'] ?? 'at_risk')));
$allowedTabs = ['at_risk', 'breached', 'met', 'all'];
if (!in_array($activeTab, $allowedTabs, true)) {
    $activeTab = 'at_risk';
}

$perPage = itm_resolve_records_per_page($ui_config ?? null);
$page = max(1, (int)($_GET['page'] ?? 1));
$filter = $activeTab === 'all' ? 'all' : $activeTab;
$listData = itm_ticket_sla_list_by_filter($conn, (int)$company_id, $filter, $page, $perPage);
$summary = itm_ticket_sla_count_summary($conn, (int)$company_id);

$moduleSlug = basename(dirname($_SERVER['PHP_SELF']));
$pageTitle = 'SLA Command Center';
$tsdEmployeeId = (int)($_SESSION['employee_id'] ?? 0);
$tsdResolvedIcon = itm_resolve_module_sidebar_icon($conn, (int)$company_id, $tsdEmployeeId, $moduleSlug);
$tsdCleanTitle = itm_module_access_strip_catalog_label_prefix($pageTitle);
$moduleListHeading = trim($tsdResolvedIcon . ' ' . $tsdCleanTitle);

$tabLabels = [
    'at_risk' => 'At Risk',
    'breached' => 'Breached',
    'met' => 'Met',
    'all' => 'All SLA',
];
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
    require_once ROOT_PATH . 'includes/itm_crud_browser_title.php';
    $crud_title = itm_crud_apply_module_icon_to_browser_title($conn, (int)($company_id ?? 0), (int)($_SESSION['employee_id'] ?? 0), $moduleSlug, (string)($crud_title ?? ''));
    ?>
    <title><?= sanitize($crud_title) ?> - <?php echo sanitize($app_name ?? itm_ui_config_app_name($currentUiConfig)); ?></title>
    <?php echo itm_render_head_favicon_link($favicon_url ?? null); ?>
    <link rel="stylesheet" href="../../css/styles.css">
    <style>
        .tsd-summary-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); gap: 12px; margin-bottom: 20px; }
        .tsd-summary-card { background: var(--bg-primary); border: 1px solid var(--border); border-radius: 8px; padding: 14px; text-align: center; }
        .tsd-summary-card strong { display: block; font-size: 1.5rem; }
        .tsd-tabs { display: flex; gap: 8px; flex-wrap: wrap; margin-bottom: 16px; border-bottom: 1px solid var(--border); padding-bottom: 10px; }
        .tsd-tab { padding: 8px 14px; border-radius: 6px; text-decoration: none; color: var(--text-primary); font-weight: 500; }
        .tsd-tab.active { background: var(--accent); color: #fff; }
        .tsd-tab:hover:not(.active) { background: var(--bg-secondary); }
        .tsd-tab-count { opacity: 0.85; font-size: 0.85em; margin-left: 4px; }
        .tsd-muted { color: var(--text-secondary); font-size: 0.875rem; }
        .tsd-escalation-form { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 12px; align-items: end; margin-bottom: 16px; }
    </style>
</head>
<body>
<div class="container">
    <?php include '../../includes/sidebar.php'; ?>
    <div class="main-content">
        <?php include '../../includes/header.php'; ?>
        <div class="content">
            <h1 title="SLA Command Center"><?php echo sanitize($moduleListHeading); ?></h1>
            <p class="tsd-muted">Open tickets with SLA policies — calendar-hour deadlines (24/7). Schedule <code>php scripts/run_ticket_sla_monitor.php</code> every 15 minutes for breach stamps, assignee alerts, and auto-escalation.</p>
            <?php if ($tsdFlash !== ''): ?>
                <p class="tsd-muted"><?php echo sanitize($tsdFlash); ?></p>
            <?php endif; ?>

            <div class="tsd-summary-grid" id="tsd-summary-cards">
                <div class="tsd-summary-card"><span class="tsd-muted">At risk</span><strong id="tsd-count-at-risk"><?php echo (int)$summary['at_risk']; ?></strong></div>
                <div class="tsd-summary-card"><span class="tsd-muted">Breached</span><strong id="tsd-count-breached"><?php echo (int)$summary['breached']; ?></strong></div>
                <div class="tsd-summary-card"><span class="tsd-muted">Met</span><strong id="tsd-count-met"><?php echo (int)$summary['met']; ?></strong></div>
                <div class="tsd-summary-card"><span class="tsd-muted">Total SLA</span><strong id="tsd-count-total"><?php echo (int)$summary['total']; ?></strong></div>
            </div>

            <div class="tsd-tabs" role="tablist">
                <?php foreach ($tabLabels as $tabKey => $tabLabel): ?>
                    <?php
                    $tabCount = $tabKey === 'all' ? (int)$summary['total'] : (int)($summary[$tabKey] ?? 0);
                    $tabHref = '?tab=' . rawurlencode($tabKey) . '&page=1';
                    ?>
                    <a class="tsd-tab<?php echo $activeTab === $tabKey ? ' active' : ''; ?>" href="<?php echo sanitize($tabHref); ?>" title="<?php echo sanitize($tabLabel); ?>"><?php echo sanitize($tabLabel); ?><span class="tsd-tab-count">(<?php echo $tabCount; ?>)</span></a>
                <?php endforeach; ?>
            </div>

            <div class="card">
                <table>
                    <thead>
                    <tr>
                        <th>ID</th>
                        <th>Code</th>
                        <th>Title</th>
                        <th>Status</th>
                        <th>Priority</th>
                        <th>SLA</th>
                        <th>Response due</th>
                        <th>Resolve due</th>
                        <th class="itm-actions-cell" data-itm-actions-origin="1">Actions</th>
                    </tr>
                    </thead>
                    <tbody id="tsd-ticket-rows">
                    <?php if (!empty($listData['rows'])): ?>
                        <?php foreach ($listData['rows'] as $ticketRow): ?>
                            <tr>
                                <td><?php echo (int)$ticketRow['id']; ?></td>
                                <td><?php echo sanitize($ticketRow['ticket_external_code'] ?? '—'); ?></td>
                                <td><?php echo sanitize($ticketRow['title'] ?? ''); ?></td>
                                <td><?php echo sanitize($ticketRow['status_name'] ?? '—'); ?></td>
                                <td><?php echo sanitize($ticketRow['priority_name'] ?? '—'); ?></td>
                                <td><?php echo $ticketRow['sla_badge_html'] ?? itm_ticket_sla_render_badge($ticketRow); ?></td>
                                <td><?php echo sanitize(itm_format_cell_scalar_display($ticketRow['sla_response_due_at'] ?? '')); ?></td>
                                <td><?php echo sanitize(itm_format_cell_scalar_display($ticketRow['sla_resolve_due_at'] ?? '')); ?></td>
                                <td class="itm-actions-cell" data-itm-actions-origin="1">
                                    <div class="itm-actions-wrap">
                                        <a class="btn btn-sm" href="../tickets/view.php?id=<?php echo (int)$ticketRow['id']; ?>" title="View">🔎</a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="9">No tickets in this SLA bucket.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>

                <?php
                $totalPages = (int)($listData['total_pages'] ?? 1);
                if ($totalPages > 1):
                    $paginationBase = '?tab=' . rawurlencode($activeTab) . '&page=';
                    ?>
                    <div style="margin-top:12px;display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
                        <?php if ($page > 1): ?>
                            <a class="btn btn-sm" href="<?php echo sanitize($paginationBase . '1'); ?>" title="First page">⏮️</a>
                            <a class="btn btn-sm" href="<?php echo sanitize($paginationBase . ($page - 1)); ?>" title="Previous page">◀️</a>
                        <?php endif; ?>
                        <span class="btn btn-sm" style="pointer-events:none;opacity:.8;">Page <?php echo $page; ?> of <?php echo $totalPages; ?></span>
                        <?php if ($page < $totalPages): ?>
                            <a class="btn btn-sm" href="<?php echo sanitize($paginationBase . ($page + 1)); ?>" title="Next page">▶️</a>
                            <a class="btn btn-sm" href="<?php echo sanitize($paginationBase . $totalPages); ?>" title="Last page">⏭️</a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>

            <div class="card" style="margin-top:24px;">
                <h2 style="margin-top:0;" title="Escalation rules">Escalation rules</h2>
                <p class="tsd-muted">When a breach is stamped, matching rules reassign the ticket and notify the escalation target (once per breach type).</p>
                <form method="POST" class="tsd-escalation-form">
                    <input type="hidden" name="csrf_token" value="<?php echo sanitize(itm_get_csrf_token()); ?>">
                    <input type="hidden" name="tsd_escalation_action" value="save_rule">
                    <div class="form-group">
                        <label>Priority</label>
                        <select name="priority_id" required>
                            <option value="">— Select —</option>
                            <?php foreach ($tsdPriorities as $prio): ?>
                                <option value="<?php echo (int)$prio['id']; ?>"><?php echo sanitize($prio['name'] ?? ''); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Breach type</label>
                        <select name="breach_type">
                            <?php foreach (itm_ticket_sla_escalation_breach_types() as $breachType): ?>
                                <option value="<?php echo sanitize($breachType); ?>"><?php echo sanitize(ucfirst($breachType)); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Escalate to</label>
                        <select name="escalate_to_employee_id" required>
                            <option value="">— Select —</option>
                            <?php foreach ($tsdEmployees as $emp): ?>
                                <?php
                                $empLabel = trim((string)($emp['first_name'] ?? '') . ' ' . (string)($emp['last_name'] ?? ''));
                                if ($empLabel === '') {
                                    $empLabel = (string)($emp['username'] ?? 'Employee');
                                }
                                ?>
                                <option value="<?php echo (int)$emp['id']; ?>"><?php echo sanitize($empLabel); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <button type="submit" class="btn btn-primary" title="Save">💾</button>
                    </div>
                </form>
                <table>
                    <thead>
                    <tr>
                        <th>Priority</th>
                        <th>Breach</th>
                        <th>Escalate to</th>
                        <th class="itm-actions-cell" data-itm-actions-origin="1">Actions</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php if (!empty($escalationRules)): ?>
                        <?php foreach ($escalationRules as $rule): ?>
                            <tr>
                                <td><?php echo sanitize($rule['priority_name'] ?? '—'); ?></td>
                                <td><?php echo sanitize(ucfirst((string)($rule['breach_type'] ?? ''))); ?></td>
                                <td><?php echo sanitize(trim((string)($rule['escalate_to_name'] ?? '')) ?: '—'); ?></td>
                                <td class="itm-actions-cell" data-itm-actions-origin="1">
                                    <form method="POST" style="display:inline;">
                                        <input type="hidden" name="csrf_token" value="<?php echo sanitize(itm_get_csrf_token()); ?>">
                                        <input type="hidden" name="tsd_escalation_action" value="delete_rule">
                                        <input type="hidden" name="rule_id" value="<?php echo (int)($rule['id'] ?? 0); ?>">
                                        <button type="submit" class="btn btn-sm btn-danger" title="Delete">🗑️</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="4">No escalation rules configured.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<script>
(function () {
    var apiBase = 'api.php';
    function refreshSummary() {
        fetch(apiBase + '?action=summary', { credentials: 'same-origin', headers: { 'Accept': 'application/json' } })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (!data || !data.ok || !data.summary) { return; }
                var s = data.summary;
                var map = { at_risk: 'tsd-count-at-risk', breached: 'tsd-count-breached', met: 'tsd-count-met', total: 'tsd-count-total' };
                Object.keys(map).forEach(function (key) {
                    var el = document.getElementById(map[key]);
                    if (el && typeof s[key] !== 'undefined') { el.textContent = s[key]; }
                });
            })
            .catch(function () {});
    }
    refreshSummary();
    setInterval(refreshSummary, 120000);
})();
</script>
</body>
</html>
