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
