<?php
/**
 * Master Tickets — global detail, incident rollup, attach problem, history.
 */

$moduleSlug = 'master_tickets';
$pageTitle = 'Master Tickets';

require_once dirname(__DIR__, 2) . '/config/config.php';
require_once ROOT_PATH . 'includes/itm_problem_management.php';
require_once ROOT_PATH . 'includes/itm_tickets_view.php';

itm_require_crud_role_module_permission($conn, 'view', $moduleSlug);

$employeeId = (int)($_SESSION['employee_id'] ?? 0);
$sessionCompanyId = (int)$company_id;
$allowedCompanyIds = itm_master_ticket_allowed_company_ids($conn, $employeeId);
$csrfToken = itm_get_csrf_token();
$errors = [];
$flash = '';
$masterTicketId = max(0, (int)($_GET['id'] ?? 0));

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && $masterTicketId > 0) {
    itm_require_post_csrf();
    if (!itm_master_ticket_user_can_view($conn, $masterTicketId, $allowedCompanyIds)) {
        $errors[] = 'Master ticket not found or not visible.';
    } else {
        $postAction = strtolower(trim((string)($_POST['master_action'] ?? '')));
        if ($postAction === 'update_master_ticket') {
            itm_require_crud_role_module_permission($conn, 'edit', $moduleSlug);
            if (!itm_master_ticket_can_manage($conn, $masterTicketId, $sessionCompanyId, $employeeId)) {
                $errors[] = 'You cannot edit this master ticket.';
            } else {
                $updateMaster = itm_master_ticket_update($conn, $masterTicketId, [
                    'title' => trim((string)($_POST['master_title'] ?? '')),
                    'description' => (string)($_POST['master_description'] ?? ''),
                    'root_cause' => trim((string)($_POST['master_root_cause'] ?? '')),
                ], $employeeId, $sessionCompanyId);
                if (!empty($updateMaster['ok'])) {
                    $flash = 'Master ticket updated — ' . (int)($updateMaster['ticket_count'] ?? 0) . ' incident ticket(s) synced.';
                } else {
                    $errors[] = (string)($updateMaster['error'] ?? 'Master ticket update failed.');
                }
            }
        } elseif ($postAction === 'attach_master_problems_bulk') {
            itm_require_crud_role_module_permission($conn, 'edit', $moduleSlug);
            if (!itm_master_ticket_can_manage($conn, $masterTicketId, $sessionCompanyId, $employeeId)) {
                $errors[] = 'You cannot attach problems to this master ticket.';
            } else {
                $targets = [];
                $rawSelections = $_POST['attach_problem_keys'] ?? [];
                if (!is_array($rawSelections)) {
                    $rawSelections = [];
                }
                foreach ($rawSelections as $rawKey) {
                    $parts = explode(':', (string)$rawKey, 2);
                    if (count($parts) !== 2) {
                        continue;
                    }
                    $targets[] = [
                        'company_id' => (int)$parts[0],
                        'problem_id' => (int)$parts[1],
                    ];
                }
                $attachResult = itm_master_ticket_attach_problems_bulk(
                    $conn,
                    $masterTicketId,
                    $targets,
                    $employeeId,
                    $sessionCompanyId
                );
                if (!empty($attachResult['ok'])) {
                    $flash = (int)($attachResult['attached'] ?? 0) . ' problem(s) attached.';
                    if (!empty($attachResult['errors'])) {
                        $flash .= ' Some failed: ' . implode(' ', (array)$attachResult['errors']);
                    }
                } else {
                    $errors[] = (string)($attachResult['error'] ?? 'Could not attach problems.');
                }
            }
        } elseif ($postAction === 'link_master_incidents_bulk') {
            itm_require_crud_role_module_permission($conn, 'edit', $moduleSlug);
            $linkProblemId = max(0, (int)($_POST['link_problem_id'] ?? 0));
            $rawTicketKeys = $_POST['link_ticket_keys'] ?? [];
            if (!is_array($rawTicketKeys)) {
                $rawTicketKeys = [];
            }
            $ticketTargets = [];
            foreach ($rawTicketKeys as $rawKey) {
                $parts = explode(':', (string)$rawKey, 2);
                if (count($parts) !== 2) {
                    continue;
                }
                $ticketTargets[] = [
                    'company_id' => (int)$parts[0],
                    'ticket_id' => (int)$parts[1],
                ];
            }
            if (!itm_master_ticket_can_manage($conn, $masterTicketId, $sessionCompanyId, $employeeId)) {
                $errors[] = 'You cannot link incidents to this master ticket.';
            } else {
                $linkResult = itm_master_ticket_link_incidents_multi_company_bulk(
                    $conn,
                    $masterTicketId,
                    $ticketTargets,
                    $linkProblemId,
                    $allowedCompanyIds,
                    $employeeId,
                    $sessionCompanyId
                );
                if (!empty($linkResult['ok'])) {
                    $flash = (int)($linkResult['linked'] ?? 0) . ' incident ticket(s) linked and synced.';
                    if (!empty($linkResult['errors'])) {
                        $flash .= ' Some failed: ' . implode(' ', (array)$linkResult['errors']);
                    }
                } else {
                    $errors[] = (string)($linkResult['error'] ?? 'Could not link incidents.');
                }
            }
        }
    }
}

$masterTicket = null;
$linkedProblems = [];
$masterIncidents = [];
$masterHistory = [];
$canManageMaster = false;
$masterCompanyCount = 0;
$eligibleProblems = [];
$linkableTicketsAll = [];

if ($masterTicketId > 0 && itm_master_ticket_user_can_view($conn, $masterTicketId, $allowedCompanyIds)) {
    $masterTicket = itm_master_ticket_fetch_row($conn, $masterTicketId);
}
if (!$masterTicket) {
    $errors[] = 'Master ticket not found or not visible for your company access.';
} else {
    $masterIncidents = itm_master_ticket_list_all_incidents($conn, $masterTicketId, $allowedCompanyIds);
    $masterHistory = itm_master_ticket_list_history($conn, $masterTicketId, 30);
    $canManageMaster = itm_master_ticket_can_manage($conn, $masterTicketId, $sessionCompanyId, $employeeId);

    $in = itm_master_ticket_bind_in_clause($allowedCompanyIds);
    if ($in['placeholders'] !== '0') {
        $sql = 'SELECT p.id, p.company_id, p.title, p.status, c.company AS company_name
                FROM problems p
                INNER JOIN companies c ON c.id = p.company_id
                WHERE p.master_ticket_id = ? AND p.deleted_at IS NULL
                  AND p.company_id IN (' . $in['placeholders'] . ')
                ORDER BY c.company ASC, p.title ASC';
        $stmt = mysqli_prepare($conn, $sql);
        if ($stmt) {
            $types = 'i' . $in['types'];
            $params = array_merge([$masterTicketId], $in['params']);
            $bind = [$types];
            foreach ($params as $i => $v) {
                $bind[] = &$params[$i];
            }
            call_user_func_array('mysqli_stmt_bind_param', array_merge([$stmt], $bind));
            mysqli_stmt_execute($stmt);
            $res = mysqli_stmt_get_result($stmt);
            while ($res && ($row = mysqli_fetch_assoc($res))) {
                $linkedProblems[] = $row;
            }
            mysqli_stmt_close($stmt);
        }
    }

    $companySet = [];
    foreach ($masterIncidents as $mi) {
        $companySet[(int)($mi['company_id'] ?? 0)] = true;
    }
    $masterCompanyCount = count($companySet);
}

if ($canManageMaster && $masterTicket) {
    $eligibleProblems = itm_master_ticket_list_eligible_problems($conn, $allowedCompanyIds);
    $linkableTicketsAll = itm_master_ticket_list_linkable_tickets_for_master(
        $conn,
        $masterTicketId,
        $allowedCompanyIds
    );
}

$moduleSlugPath = basename(dirname($_SERVER['PHP_SELF']));
$canEditMaster = itm_user_has_role_module_permission(
    $conn,
    $employeeId,
    $sessionCompanyId,
    itm_resolve_rbac_module_name_for_slug($conn, $moduleSlug),
    'edit'
);
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
    $browserTitle = itm_crud_apply_module_icon_to_browser_title($conn, $sessionCompanyId, $employeeId, $moduleSlugPath, 'View master ticket');
    ?>
    <title><?= sanitize($browserTitle) ?> - <?php echo sanitize($app_name ?? itm_ui_config_app_name($currentUiConfig)); ?></title>
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
            <?php if ($flash !== ''): ?>
                <p style="margin-bottom:16px;color:var(--text-secondary);"><?php echo sanitize($flash); ?></p>
            <?php endif; ?>

            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;flex-wrap:wrap;gap:12px;">
                <h1 title="View master ticket">🔎</h1>
                <div style="display:flex;gap:8px;">
                    <?php if ($canEditMaster && $masterTicket): ?>
                        <a href="#master-edit" class="btn" title="Edit">✏️</a>
                    <?php endif; ?>
                    <a href="index.php" class="btn" title="Back">🔙</a>
                </div>
            </div>

            <?php if ($masterTicket): ?>
                <div class="card" style="margin-bottom:20px;">
                    <p style="margin-top:0;">
                        Master #<?php echo (int)$masterTicketId; ?> —
                        global (no <code>company_id</code>) ·
                        <?php echo (int)$masterCompanyCount; ?> companies ·
                        <?php echo count($masterIncidents); ?> incident(s) visible to you.
                    </p>
                    <table>
                        <tbody>
                        <tr><th style="width:200px;">Title</th><td><?php echo sanitize($masterTicket['title'] ?? ''); ?></td></tr>
                        <tr><th>Description</th><td><?php echo nl2br(sanitize($masterTicket['description'] ?? '')); ?></td></tr>
                        <tr><th>Root Cause</th><td><?php echo nl2br(sanitize($masterTicket['root_cause'] ?? '')); ?></td></tr>
                        <tr><th>Active</th><td><?php echo ((int)($masterTicket['active'] ?? 0) === 1) ? '<span class="badge badge-success">Active</span>' : '<span class="badge badge-danger">Inactive</span>'; ?></td></tr>
                        <tr><th>Created At</th><td><?php echo sanitize(itm_format_audit_timestamp_display($masterTicket['created_at'] ?? '')); ?></td></tr>
                        <tr><th>Updated At</th><td><?php echo sanitize(itm_format_audit_timestamp_display($masterTicket['updated_at'] ?? '')); ?></td></tr>
                        </tbody>
                    </table>
                </div>

                <?php if ($canManageMaster): ?>
                    <div class="card" style="margin-bottom:20px;" id="master-edit">
                        <h2 style="margin-top:0;" title="Edit master fields">✏️</h2>
                        <p class="itm-muted" style="margin-top:0;">
                            Saving title, description, or root cause pushes updates to every linked incident ticket.
                        </p>
                        <form method="POST" class="form-grid" style="max-width:980px;margin-bottom:24px;">
                            <input type="hidden" name="csrf_token" value="<?php echo sanitize($csrfToken); ?>">
                            <input type="hidden" name="master_action" value="update_master_ticket">
                            <div class="form-group">
                                <label for="master-title">Title</label>
                                <input type="text" id="master-title" name="master_title" value="<?php echo sanitize($masterTicket['title'] ?? ''); ?>" required>
                            </div>
                            <div class="form-group">
                                <label for="master-description">Description</label>
                                <textarea id="master-description" name="master_description" rows="4"><?php echo sanitize($masterTicket['description'] ?? ''); ?></textarea>
                            </div>
                            <div class="form-group">
                                <label for="master-root-cause">Root Cause</label>
                                <textarea id="master-root-cause" name="master_root_cause" rows="3"><?php echo sanitize($masterTicket['root_cause'] ?? ''); ?></textarea>
                            </div>
                            <div class="form-actions">
                                <button type="submit" class="btn btn-primary" title="Save">💾</button>
                            </div>
                        </form>

                        <h3 style="margin-top:0;" title="Attach problems">Attach Problems</h3>
                        <p class="itm-muted">Multi-select major problems (each must already have ≥1 linked incident). Hold Ctrl/Cmd to select multiple.</p>
                        <form method="POST" style="max-width:980px;margin-bottom:24px;">
                            <input type="hidden" name="csrf_token" value="<?php echo sanitize($csrfToken); ?>">
                            <input type="hidden" name="master_action" value="attach_master_problems_bulk">
                            <div class="form-group">
                                <label for="attach-problem-keys">Eligible problems</label>
                                <select id="attach-problem-keys" name="attach_problem_keys[]" multiple size="8" class="form-control" style="min-height:160px;">
                                    <?php foreach ($eligibleProblems as $eligibleProblem): ?>
                                        <?php
                                        $optKey = (int)($eligibleProblem['company_id'] ?? 0) . ':' . (int)($eligibleProblem['id'] ?? 0);
                                        $optLabel = sanitize(
                                            ($eligibleProblem['company_name'] ?? '') . ' — '
                                            . ($eligibleProblem['title'] ?? '') . ' (#' . (int)($eligibleProblem['id'] ?? 0) . ', '
                                            . (int)($eligibleProblem['incident_count'] ?? 0) . ' incident(s))'
                                        );
                                        ?>
                                        <option value="<?php echo sanitize($optKey); ?>"><?php echo $optLabel; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <button type="submit" class="btn btn-primary" title="Attach selected problems">🔗</button>
                        </form>

                        <h3 style="margin-top:0;" title="Link incident tickets">Link Incident Tickets</h3>
                        <p class="itm-muted">
                            Multi-select tickets from every company you can access (not already on this master).
                            Hold Ctrl/Cmd to select multiple. When a company has no problem on this master yet, one is created automatically from the master fields, then the ticket is linked.
                            Use the optional problem filter only when a company has multiple linked problems.
                        </p>
                        <form method="POST" style="max-width:980px;" id="master-link-incidents-form">
                            <input type="hidden" name="csrf_token" value="<?php echo sanitize($csrfToken); ?>">
                            <input type="hidden" name="master_action" value="link_master_incidents_bulk">
                            <div class="form-group">
                                <label for="link-problem-id">Problem filter (optional)</label>
                                <select id="link-problem-id" name="link_problem_id" class="form-control">
                                    <option value="">— Auto per company —</option>
                                    <?php foreach ($linkedProblems as $lp): ?>
                                        <option value="<?php echo (int)($lp['id'] ?? 0); ?>">
                                            <?php echo sanitize(($lp['company_name'] ?? '') . ' — ' . ($lp['title'] ?? '') . ' (#' . (int)($lp['id'] ?? 0) . ')'); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="link-ticket-keys">Tickets to link (all companies)</label>
                                <select id="link-ticket-keys" name="link_ticket_keys[]" multiple size="12" class="form-control" style="min-height:220px;" required>
                                    <?php foreach ($linkableTicketsAll as $ticketRow): ?>
                                        <?php
                                        $optKey = (int)($ticketRow['company_id'] ?? 0) . ':' . (int)($ticketRow['id'] ?? 0);
                                        $code = trim((string)($ticketRow['ticket_external_code'] ?? ''));
                                        $codeLabel = $code !== '' ? $code . ' ' : '';
                                        ?>
                                        <option value="<?php echo sanitize($optKey); ?>">
                                            <?php echo sanitize(
                                                ($ticketRow['company_name'] ?? '') . ' — #'
                                                . (int)($ticketRow['id'] ?? 0) . ' '
                                                . $codeLabel
                                                . ($ticketRow['title'] ?? '')
                                            ); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <button type="submit" class="btn btn-primary" title="Link selected tickets">🔗</button>
                        </form>
                    </div>
                <?php endif; ?>

                <div class="card" style="margin-bottom:20px;">
                    <h2 style="margin-top:0;" title="Linked problems">Linked Problems</h2>
                    <table data-itm-no-import-excel="1">
                        <thead>
                        <tr>
                            <th>Company</th>
                            <th>Problem</th>
                            <th>Status</th>
                            <th class="itm-actions-cell" data-itm-actions-origin="1">Actions</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php if (!empty($linkedProblems)): ?>
                            <?php foreach ($linkedProblems as $lp): ?>
                                <tr>
                                    <td><?php echo sanitize($lp['company_name'] ?? ''); ?></td>
                                    <td><?php echo sanitize($lp['title'] ?? ''); ?> (#<?php echo (int)($lp['id'] ?? 0); ?>)</td>
                                    <td><?php echo itm_problem_status_badge($lp['status'] ?? ''); ?></td>
                                    <td class="itm-actions-cell" data-itm-actions-origin="1">
                                        <a class="btn btn-sm" href="../problems/view.php?id=<?php echo (int)$lp['id']; ?>#master-ticket" title="View">🔎</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="4">No linked problems visible.</td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <div class="card" style="margin-bottom:20px;">
                    <h2 style="margin-top:0;" title="All incident tickets on this master">Incidents</h2>
                    <table data-itm-no-import-excel="1">
                        <thead>
                        <tr>
                            <th>Company</th>
                            <th>Ticket</th>
                            <th>Code</th>
                            <th>Title</th>
                            <th>Status</th>
                            <th class="itm-actions-cell" data-itm-actions-origin="1">Actions</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php if (!empty($masterIncidents)): ?>
                            <?php foreach ($masterIncidents as $masterIncident): ?>
                                <tr>
                                    <td><?php echo sanitize($masterIncident['company_name'] ?? ''); ?></td>
                                    <td><?php echo (int)($masterIncident['id'] ?? 0); ?></td>
                                    <td><?php echo sanitize($masterIncident['ticket_external_code'] ?? '—'); ?></td>
                                    <td><?php echo sanitize($masterIncident['title'] ?? ''); ?></td>
                                    <td><?php echo sanitize($masterIncident['status_name'] ?? '—'); ?></td>
                                    <td class="itm-actions-cell" data-itm-actions-origin="1">
                                        <a class="btn btn-sm" href="../tickets/<?php echo sanitize(itm_ticket_master_view_page_href((int)$masterIncident['id'], (int)($masterIncident['company_id'] ?? 0), $masterTicketId)); ?>" title="View">🔎</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="6">No incidents visible for your company access.</td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <div class="card">
                    <h2 style="margin-top:0;" title="Master ticket update history">Update History</h2>
                    <table data-itm-no-import-excel="1">
                        <thead>
                        <tr>
                            <th>When</th>
                            <th>Actor</th>
                            <th>Event</th>
                            <th>Summary</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php if (!empty($masterHistory)): ?>
                            <?php foreach ($masterHistory as $historyRow): ?>
                                <?php
                                $actorLabel = trim((string)($historyRow['actor_name'] ?? ''));
                                if ($actorLabel === '') {
                                    $actorLabel = (string)($historyRow['actor_username'] ?? '—');
                                }
                                ?>
                                <tr>
                                    <td><?php echo sanitize(itm_format_audit_timestamp_display($historyRow['created_at'] ?? '')); ?></td>
                                    <td><?php echo sanitize($actorLabel); ?></td>
                                    <td><?php echo sanitize($historyRow['event_type'] ?? ''); ?></td>
                                    <td><?php echo sanitize($historyRow['summary'] ?? ''); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="4">No history yet.</td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
</body>
</html>
