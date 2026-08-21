<?php
/**
 * Master Tickets — global detail, incident rollup, attach problem, history.
 */

$moduleSlug = 'master_tickets';
$pageTitle = 'Master Tickets';

require_once dirname(__DIR__, 2) . '/config/config.php';
require_once ROOT_PATH . 'includes/itm_problem_management.php';

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
        } elseif ($postAction === 'attach_master_problem') {
            itm_require_crud_role_module_permission($conn, 'edit', $moduleSlug);
            $targetCompanyId = max(0, (int)($_POST['attach_company_id'] ?? 0));
            $targetProblemId = max(0, (int)($_POST['attach_problem_id'] ?? 0));
            if (!itm_master_ticket_can_manage($conn, $masterTicketId, $sessionCompanyId, $employeeId)) {
                $errors[] = 'You cannot attach problems to this master ticket.';
            } else {
                $attachResult = itm_master_ticket_attach_problem(
                    $conn,
                    $masterTicketId,
                    $targetCompanyId,
                    $targetProblemId,
                    $employeeId,
                    $sessionCompanyId
                );
                if (!empty($attachResult['ok'])) {
                    $flash = 'Problem attached — ' . (int)($attachResult['ticket_count'] ?? 0) . ' total incident ticket(s) on master.';
                } else {
                    $errors[] = (string)($attachResult['error'] ?? 'Could not attach problem.');
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

$moduleSlugPath = basename(dirname($_SERVER['PHP_SELF']));
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
                    <div class="card" style="margin-bottom:20px;">
                        <h2 style="margin-top:0;" title="Edit master fields">✏️</h2>
                        <form method="POST" class="form-grid" style="max-width:980px;margin-bottom:20px;">
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
                        <form method="POST" style="display:flex;gap:12px;align-items:flex-end;flex-wrap:wrap;max-width:980px;">
                            <input type="hidden" name="csrf_token" value="<?php echo sanitize($csrfToken); ?>">
                            <input type="hidden" name="master_action" value="attach_master_problem">
                            <div class="form-group" style="margin:0;">
                                <label for="attach-company-id">Attach company ID</label>
                                <input type="number" id="attach-company-id" name="attach_company_id" min="1" required>
                            </div>
                            <div class="form-group" style="margin:0;">
                                <label for="attach-problem-id">Problem ID</label>
                                <input type="number" id="attach-problem-id" name="attach_problem_id" min="1" required>
                            </div>
                            <button type="submit" class="btn btn-primary" title="Attach problem">🔗</button>
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
                                        <a class="btn btn-sm" href="../tickets/view.php?id=<?php echo (int)$masterIncident['id']; ?>" title="View">🔎</a>
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
