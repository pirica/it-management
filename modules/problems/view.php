<?php
/**
 * Problem Management — detail view, incident links, known error publish.
 */

$crud_title = 'Problem Management';
$moduleSlug = 'problems';

require_once dirname(__DIR__, 2) . '/config/config.php';
require_once ROOT_PATH . 'includes/itm_problem_management.php';

itm_require_crud_role_module_permission($conn, 'view', $moduleSlug);

$companyId = (int)$company_id;
$employeeId = (int)($_SESSION['employee_id'] ?? 0);
$errors = [];
$flash = '';
$csrfToken = itm_get_csrf_token();
$problemId = max(0, (int)($_GET['id'] ?? 0));

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && $problemId > 0) {
    itm_require_post_csrf();
    $postAction = strtolower(trim((string)($_POST['problem_action'] ?? '')));

    if ($postAction === 'link_ticket') {
        itm_require_crud_role_module_permission($conn, 'edit', $moduleSlug);
        $ticketId = max(0, (int)($_POST['ticket_id'] ?? 0));
        $linkResult = itm_problem_link_ticket($conn, $companyId, $problemId, $ticketId, $employeeId);
        if (!empty($linkResult['ok'])) {
            $flash = 'Ticket linked.';
        } else {
            $errors[] = (string)($linkResult['error'] ?? 'Could not link ticket.');
        }
    } elseif ($postAction === 'unlink_ticket') {
        itm_require_crud_role_module_permission($conn, 'edit', $moduleSlug);
        $ticketId = max(0, (int)($_POST['ticket_id'] ?? 0));
        $unlinkResult = itm_problem_unlink_ticket($conn, $companyId, $problemId, $ticketId, $employeeId);
        if (!empty($unlinkResult['ok'])) {
            $flash = 'Ticket unlinked.';
        } else {
            $errors[] = (string)($unlinkResult['error'] ?? 'Could not unlink ticket.');
        }
    } elseif ($postAction === 'publish_known_error') {
        itm_require_crud_role_module_permission($conn, 'edit', $moduleSlug);
        $publishKb = !empty($_POST['create_kb']);
        $keResult = itm_known_error_upsert($conn, $companyId, $problemId, [
            'title' => trim((string)($_POST['ke_title'] ?? '')),
            'workaround' => trim((string)($_POST['workaround'] ?? '')),
            'symptom_keywords' => trim((string)($_POST['symptom_keywords'] ?? '')),
        ], $employeeId, $publishKb);
        if (!empty($keResult['ok'])) {
            $flash = $publishKb
                ? 'Known error saved and published to the knowledge base.'
                : 'Known error saved.';
        } else {
            $errors[] = (string)($keResult['error'] ?? 'Could not save known error.');
        }
    } elseif ($postAction === 'create_master_ticket') {
        itm_require_crud_role_module_permission($conn, 'edit', $moduleSlug);
        $createMaster = itm_problem_create_master_ticket($conn, $companyId, $problemId, $employeeId, $companyId);
        if (!empty($createMaster['ok'])) {
            $flash = 'Master ticket #' . (int)($createMaster['master_ticket_id'] ?? 0) . ' created — '
                . (int)($createMaster['ticket_count'] ?? 0) . ' incident ticket(s) synced.';
        } else {
            $errors[] = (string)($createMaster['error'] ?? 'Could not create master ticket.');
        }
    } elseif ($postAction === 'update_master_ticket') {
        itm_require_crud_role_module_permission($conn, 'edit', $moduleSlug);
        $masterTicketId = (int)($_POST['master_ticket_id'] ?? 0);
        if ($masterTicketId <= 0 || !itm_master_ticket_can_manage($conn, $masterTicketId, $companyId, $employeeId)) {
            $errors[] = 'You cannot edit this master ticket.';
        } else {
            $updateMaster = itm_master_ticket_update($conn, $masterTicketId, [
                'title' => trim((string)($_POST['master_title'] ?? '')),
                'description' => (string)($_POST['master_description'] ?? ''),
                'root_cause' => trim((string)($_POST['master_root_cause'] ?? '')),
            ], $employeeId, $companyId);
            if (!empty($updateMaster['ok'])) {
                $flash = 'Master ticket updated — ' . (int)($updateMaster['ticket_count'] ?? 0) . ' incident ticket(s) synced.';
            } else {
                $errors[] = (string)($updateMaster['error'] ?? 'Master ticket update failed.');
            }
        }
    } elseif ($postAction === 'attach_master_problem') {
        itm_require_crud_role_module_permission($conn, 'edit', $moduleSlug);
        $masterTicketId = (int)($_POST['master_ticket_id'] ?? 0);
        $targetCompanyId = max(0, (int)($_POST['attach_company_id'] ?? 0));
        $targetProblemId = max(0, (int)($_POST['attach_problem_id'] ?? 0));
        if ($masterTicketId <= 0 || !itm_master_ticket_can_manage($conn, $masterTicketId, $companyId, $employeeId)) {
            $errors[] = 'You cannot attach problems to this master ticket.';
        } else {
            $attachResult = itm_master_ticket_attach_problem(
                $conn,
                $masterTicketId,
                $targetCompanyId,
                $targetProblemId,
                $employeeId,
                $companyId
            );
            if (!empty($attachResult['ok'])) {
                $flash = 'Problem attached — ' . (int)($attachResult['ticket_count'] ?? 0) . ' total incident ticket(s) on master.';
            } else {
                $errors[] = (string)($attachResult['error'] ?? 'Could not attach problem.');
            }
        }
    }
}

$problem = $problemId > 0 ? itm_problem_fetch_row($conn, $companyId, $problemId) : null;
if (!$problem) {
    $errors[] = 'Problem not found.';
}

$incidents = $problem ? itm_problem_list_incidents($conn, $companyId, $problemId) : [];
$knownError = $problem ? itm_known_error_fetch_for_problem($conn, $companyId, $problemId) : null;

$keForm = [
    'ke_title' => (string)($knownError['title'] ?? $problem['title'] ?? ''),
    'workaround' => (string)($knownError['workaround'] ?? ''),
    'symptom_keywords' => (string)($knownError['symptom_keywords'] ?? ''),
];

$masterTicketId = $problem ? (int)($problem['master_ticket_id'] ?? 0) : 0;
$masterTicket = $masterTicketId > 0 ? itm_master_ticket_fetch_row($conn, $masterTicketId) : null;
$allowedCompanyIds = itm_master_ticket_allowed_company_ids($conn, $employeeId);
$masterIncidents = ($masterTicketId > 0) ? itm_master_ticket_list_all_incidents($conn, $masterTicketId, $allowedCompanyIds) : [];
$masterHistory = ($masterTicketId > 0) ? itm_master_ticket_list_history($conn, $masterTicketId, 30) : [];
$canManageMaster = false;
if ($masterTicketId > 0) {
    $canManageMaster = itm_master_ticket_can_manage($conn, $masterTicketId, $companyId, $employeeId);
} else {
    $rbacModuleName = itm_resolve_rbac_module_name_for_slug($conn, $moduleSlug);
    $canManageMaster = itm_user_has_role_module_permission($conn, $employeeId, $companyId, $rbacModuleName, 'edit');
}
$masterCompanyCount = 0;
if ($masterTicketId > 0) {
    $companySet = [];
    foreach ($masterIncidents as $mi) {
        $companySet[(int)($mi['company_id'] ?? 0)] = true;
    }
    $masterCompanyCount = count($companySet);
}

function problems_view_employee_name($conn, $companyId, $empId)
{
    $empId = (int)$empId;
    if ($empId <= 0) {
        return '—';
    }
    $stmt = mysqli_prepare(
        $conn,
        'SELECT first_name, last_name, username FROM employees WHERE id = ? AND company_id = ? LIMIT 1'
    );
    if (!$stmt) {
        return (string)$empId;
    }
    mysqli_stmt_bind_param($stmt, 'ii', $empId, $companyId);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $row = $res ? mysqli_fetch_assoc($res) : null;
    mysqli_stmt_close($stmt);
    if (!$row) {
        return (string)$empId;
    }
    $label = trim((string)($row['first_name'] ?? '') . ' ' . (string)($row['last_name'] ?? ''));
    if ($label === '') {
        $label = (string)($row['username'] ?? $empId);
    }
    return sanitize($label);
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
    $pageTitle = itm_crud_apply_module_icon_to_browser_title($conn, $companyId, $employeeId, $moduleSlugPath, 'View Problem');
    ?>
    <title><?= sanitize($pageTitle) ?> - <?php echo sanitize($app_name ?? itm_ui_config_app_name($currentUiConfig)); ?></title>
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

            <?php if ($problem): ?>
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;flex-wrap:wrap;gap:12px;">
                    <h1 title="View problem details">🔎</h1>
                    <div style="display:flex;gap:8px;">
                        <a href="edit.php?id=<?php echo $problemId; ?>" class="btn btn-primary" title="Edit">✏️</a>
                        <a href="index.php" class="btn" title="Back">🔙</a>
                    </div>
                </div>

                <div class="card" style="margin-bottom:20px;">
                    <table>
                        <tbody>
                        <tr><th style="width:240px;">Title</th><td><?php echo sanitize($problem['title'] ?? ''); ?></td></tr>
                        <tr><th>Status</th><td><?php echo itm_problem_status_badge($problem['status'] ?? ''); ?></td></tr>
                        <tr><th>Owner</th><td><?php echo problems_view_employee_name($conn, $companyId, (int)($problem['owner_employee_id'] ?? 0)); ?></td></tr>
                        <tr><th>Description</th><td><?php echo nl2br(sanitize($problem['description'] ?? '')); ?></td></tr>
                        <tr><th>Root Cause</th><td><?php echo nl2br(sanitize($problem['root_cause'] ?? '')); ?></td></tr>
                        <tr><th>Active</th><td><?php echo ((int)($problem['active'] ?? 0) === 1) ? '<span class="badge badge-success">Active</span>' : '<span class="badge badge-danger">Inactive</span>'; ?></td></tr>
                        <tr><th>Resolved At</th><td><?php echo sanitize(itm_format_audit_timestamp_display($problem['resolved_at'] ?? '')); ?></td></tr>
                        <tr><th>Knowledge Base</th><td><?php echo (int)($problem['knowledge_base_id'] ?? 0) > 0 ? (int)$problem['knowledge_base_id'] : '—'; ?></td></tr>
                        <tr><th>Created By</th><td><?php echo problems_view_employee_name($conn, $companyId, (int)($problem['created_by'] ?? 0)); ?></td></tr>
                        <tr><th>Created At</th><td><?php echo sanitize(itm_format_audit_timestamp_display($problem['created_at'] ?? '')); ?></td></tr>
                        <tr><th>Updated By</th><td><?php echo problems_view_employee_name($conn, $companyId, (int)($problem['updated_by'] ?? 0)); ?></td></tr>
                        <tr><th>Updated At</th><td><?php echo sanitize(itm_format_audit_timestamp_display($problem['updated_at'] ?? '')); ?></td></tr>
                        </tbody>
                    </table>
                </div>

                <div class="card" style="margin-bottom:20px;" id="master-ticket">
                    <h2 style="margin-top:0;" title="Cross-company master ticket">Master Ticket</h2>
                    <?php if ($masterTicket): ?>
                        <p style="margin-top:0;">
                            Master #<?php echo (int)$masterTicketId; ?> —
                            <?php echo (int)$masterCompanyCount; ?> companies ·
                            <?php echo count($masterIncidents); ?> incident ticket(s) visible to you.
                        </p>
                        <?php if ($canManageMaster): ?>
                            <form method="POST" class="form-grid" style="max-width:980px;margin-bottom:20px;">
                                <input type="hidden" name="csrf_token" value="<?php echo sanitize($csrfToken); ?>">
                                <input type="hidden" name="problem_action" value="update_master_ticket">
                                <input type="hidden" name="master_ticket_id" value="<?php echo (int)$masterTicketId; ?>">
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
                            <form method="POST" style="display:flex;gap:12px;align-items:flex-end;flex-wrap:wrap;max-width:980px;margin-bottom:20px;">
                                <input type="hidden" name="csrf_token" value="<?php echo sanitize($csrfToken); ?>">
                                <input type="hidden" name="problem_action" value="attach_master_problem">
                                <input type="hidden" name="master_ticket_id" value="<?php echo (int)$masterTicketId; ?>">
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
                        <?php endif; ?>
                        <table data-itm-no-import-excel="1" style="margin-bottom:20px;">
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
                        <h3 title="Master ticket update history">Update History</h3>
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
                    <?php elseif (count($incidents) >= 1 && $canManageMaster): ?>
                        <p>Create a cross-company master ticket to roll up all linked incidents. Updates sync to every linked ticket.</p>
                        <form method="POST">
                            <input type="hidden" name="csrf_token" value="<?php echo sanitize($csrfToken); ?>">
                            <input type="hidden" name="problem_action" value="create_master_ticket">
                            <button type="submit" class="btn btn-primary" title="Create master ticket">➕</button>
                        </form>
                    <?php else: ?>
                        <p>Link at least one incident to create a master ticket.</p>
                    <?php endif; ?>
                </div>

                <div class="card" style="margin-bottom:20px;">
                    <h2 style="margin-top:0;" title="Linked incidents">Linked Incidents</h2>
                    <table data-itm-no-import-excel="1">
                        <thead>
                        <tr>
                            <th>ID</th>
                            <th>Code</th>
                            <th>Title</th>
                            <th>Status</th>
                            <th class="itm-actions-cell" data-itm-actions-origin="1">Actions</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php if (!empty($incidents)): ?>
                            <?php foreach ($incidents as $incident): ?>
                                <tr>
                                    <td><?php echo (int)($incident['id'] ?? 0); ?></td>
                                    <td><?php echo sanitize($incident['ticket_external_code'] ?? '—'); ?></td>
                                    <td><?php echo sanitize($incident['title'] ?? ''); ?></td>
                                    <td><?php echo sanitize($incident['status_name'] ?? '—'); ?></td>
                                    <td class="itm-actions-cell" data-itm-actions-origin="1">
                                        <div class="itm-actions-wrap">
                                            <a class="btn btn-sm" href="../tickets/view.php?id=<?php echo (int)$incident['id']; ?>" title="View">🔎</a>
                                            <form method="POST" style="display:inline;">
                                                <input type="hidden" name="csrf_token" value="<?php echo sanitize($csrfToken); ?>">
                                                <input type="hidden" name="problem_action" value="unlink_ticket">
                                                <input type="hidden" name="ticket_id" value="<?php echo (int)$incident['id']; ?>">
                                                <button type="submit" class="btn btn-sm btn-danger" title="Unlink">🗑️</button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="5">No linked incidents.</td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <div class="card" style="margin-bottom:20px;">
                    <h2 style="margin-top:0;" title="Link ticket">Link Ticket</h2>
                    <form method="POST" style="display:flex;gap:12px;align-items:flex-end;flex-wrap:wrap;max-width:640px;">
                        <input type="hidden" name="csrf_token" value="<?php echo sanitize($csrfToken); ?>">
                        <input type="hidden" name="problem_action" value="link_ticket">
                        <div class="form-group" style="margin:0;flex:1;min-width:200px;">
                            <label for="link-ticket-id">Ticket ID</label>
                            <input type="number" id="link-ticket-id" name="ticket_id" min="1" required>
                        </div>
                        <button type="submit" class="btn btn-primary" title="Link">💾</button>
                    </form>
                </div>

                <div class="card">
                    <h2 style="margin-top:0;" title="Known error">Known Error</h2>
                    <form method="POST" class="form-grid" style="max-width:980px;">
                        <input type="hidden" name="csrf_token" value="<?php echo sanitize($csrfToken); ?>">
                        <input type="hidden" name="problem_action" value="publish_known_error">
                        <div class="form-group">
                            <label for="ke-title">Title</label>
                            <input type="text" id="ke-title" name="ke_title" value="<?php echo sanitize($keForm['ke_title']); ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="ke-workaround">Workaround</label>
                            <textarea id="ke-workaround" name="workaround" rows="4" required><?php echo sanitize($keForm['workaround']); ?></textarea>
                        </div>
                        <div class="form-group">
                            <label for="ke-keywords">Symptom Keywords</label>
                            <input type="text" id="ke-keywords" name="symptom_keywords" value="<?php echo sanitize($keForm['symptom_keywords']); ?>" placeholder="comma or space separated">
                        </div>
                        <div class="form-group">
                            <label class="itm-checkbox-control">
                                <input type="checkbox" name="create_kb" value="1">
                                <span>Create / update knowledge base article</span>
                            </label>
                        </div>
                        <div class="form-actions">
                            <button type="submit" class="btn btn-primary" title="Save">💾</button>
                        </div>
                    </form>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
<script src="../../js/theme.js"></script>
</body>
</html>
