<?php
/**
 * Vault Org Recovery — view / complete / reject request.
 */
require_once '../../config/config.php';
require_once ROOT_PATH . 'includes/itm_vault_org_recovery.php';

$company_id = (int)($_SESSION['company_id'] ?? 0);
$employee_id = (int)($_SESSION['employee_id'] ?? 0);
if (!itm_is_admin($conn, $employee_id)) {
    header('Location: ' . BASE_URL . 'dashboard.php');
    exit;
}

$id = (int)($_GET['id'] ?? 0);
$csrfToken = itm_get_csrf_token();
$moduleSlug = basename(dirname($_SERVER['PHP_SELF']));
$resolvedModuleIcon = itm_resolve_module_sidebar_icon($conn, $company_id, $employee_id, $moduleSlug);
$moduleHeading = trim($resolvedModuleIcon . ' Vault recovery request');
$ui_config = itm_get_ui_configuration($conn, $company_id, $employee_id);
$recoveredMasterKey = '';
$successMessage = '';
$errorMessage = '';

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    itm_require_post_csrf();
    $action = (string)($_POST['action'] ?? '');
    if ($action === 'complete') {
        $result = itm_vault_org_recovery_complete_request(
            $conn,
            $company_id,
            $employee_id,
            $id,
            (string)($_POST['admin_passphrase'] ?? ''),
            (string)($_POST['completion_notes'] ?? '')
        );
        if (!empty($result['ok'])) {
            $recoveredMasterKey = (string)($result['master_key'] ?? '');
            $successMessage = (string)($result['message'] ?? 'Recovery completed.');
        } else {
            $errorMessage = (string)($result['message'] ?? 'Recovery failed.');
        }
    } elseif ($action === 'reject') {
        $result = itm_vault_org_recovery_reject_request(
            $conn,
            $company_id,
            $employee_id,
            $id,
            (string)($_POST['completion_notes'] ?? '')
        );
        if (!empty($result['ok'])) {
            $_SESSION['crud_success'] = (string)($result['message'] ?? 'Request rejected.');
            header('Location: index.php');
            exit;
        }
        $errorMessage = (string)($result['message'] ?? 'Reject failed.');
    }
}

$row = itm_vault_org_recovery_fetch_request($conn, $company_id, $id);
if (!is_array($row)) {
    $_SESSION['crud_error'] = 'Recovery request not found.';
    header('Location: index.php');
    exit;
}

require_once ROOT_PATH . 'includes/itm_crud_browser_title.php';
$crud_title = itm_crud_apply_module_icon_to_browser_title($conn, $company_id, $employee_id, $moduleSlug, $moduleHeading);

function vor_requester_label(mysqli $conn, $employeeId)
{
    $employeeId = (int)$employeeId;
    if ($employeeId <= 0) {
        return '';
    }
    $stmt = mysqli_prepare($conn, 'SELECT first_name, last_name, username FROM employees WHERE id = ? LIMIT 1');
    if (!$stmt) {
        return '';
    }
    mysqli_stmt_bind_param($stmt, 'i', $employeeId);
    mysqli_stmt_execute($stmt);
    $emp = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);

    return is_array($emp) ? itm_vault_org_recovery_employee_label($emp) : '';
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?= sanitize($crud_title) ?> - <?php echo sanitize($app_name ?? itm_ui_config_app_name($ui_config ?? null)); ?></title>
    <?php echo itm_render_head_favicon_link($favicon_url ?? null); ?>
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>css/styles.css">
</head>
<body>
<div class="container">
    <?php include '../../includes/sidebar.php'; ?>
    <div class="main-content">
        <?php include '../../includes/header.php'; ?>
        <div class="content">
            <div class="card">
                <h1 title="View vault recovery request">🔎</h1>
                <p><a href="index.php" class="btn btn-sm" title="Back">🔙</a></p>
                <?php if ($successMessage !== ''): ?><div class="alert alert-success"><?php echo sanitize($successMessage); ?></div><?php endif; ?>
                <?php if ($errorMessage !== ''): ?><div class="alert alert-danger"><?php echo sanitize($errorMessage); ?></div><?php endif; ?>

                <table>
                    <tbody>
                    <tr><th>Employee</th><td><?php echo sanitize(itm_vault_org_recovery_employee_label($row)); ?></td></tr>
                    <tr><th>Status</th><td><?php echo sanitize(ucfirst((string)($row['status'] ?? ''))); ?></td></tr>
                    <tr><th>Legal reference</th><td><?php echo sanitize((string)($row['legal_reference'] ?? '')); ?></td></tr>
                    <tr><th>Consent reference</th><td><?php echo sanitize((string)($row['consent_reference'] ?? '')); ?></td></tr>
                    <tr><th>Consent recorded</th><td><?php echo sanitize(itm_format_audit_timestamp_display($row['consent_verified_at'] ?? '')); ?></td></tr>
                    <tr><th>Requester</th><td><?php echo sanitize(vor_requester_label($conn, (int)($row['requester_employee_id'] ?? 0))); ?></td></tr>
                    <tr><th>Request notes</th><td><?php echo nl2br(sanitize((string)($row['request_notes'] ?? ''))); ?></td></tr>
                    <tr><th>Created</th><td><?php echo sanitize(itm_format_audit_timestamp_display($row['created_at'] ?? '')); ?></td></tr>
                    <?php if (!empty($row['completed_at'])): ?>
                        <tr><th>Completed</th><td><?php echo sanitize(itm_format_audit_timestamp_display($row['completed_at'] ?? '')); ?></td></tr>
                        <tr><th>Completed by</th><td><?php echo sanitize(vor_requester_label($conn, (int)($row['completed_by_employee_id'] ?? 0))); ?></td></tr>
                        <tr><th>Completion notes</th><td><?php echo nl2br(sanitize((string)($row['completion_notes'] ?? ''))); ?></td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>

                <?php if ($recoveredMasterKey !== ''): ?>
                    <div class="card" style="margin-top:16px;border:2px solid var(--accent, #0969da);">
                        <h2 style="margin-top:0;">One-time master key</h2>
                        <p>Copy this key now. It is not stored after you leave this page.</p>
                        <div style="display:flex;gap:8px;align-items:center;">
                            <input type="text" id="vor-recovered-key" readonly value="<?php echo sanitize($recoveredMasterKey); ?>" style="flex:1;font-family:monospace;">
                            <button type="button" class="btn btn-sm" onclick="navigator.clipboard.writeText(document.getElementById('vor-recovered-key').value)" title="Copy">🗐</button>
                        </div>
                    </div>
                <?php elseif ((string)($row['status'] ?? '') === 'pending'): ?>
                    <div class="card" style="margin-top:16px;">
                        <h2 style="margin-top:0;">Complete recovery</h2>
                        <form method="POST" style="margin-bottom:16px;">
                            <input type="hidden" name="csrf_token" value="<?php echo sanitize($csrfToken); ?>">
                            <input type="hidden" name="action" value="complete">
                            <div class="form-group">
                                <label>Recovery authorization passphrase</label>
                                <input type="password" name="admin_passphrase" required autocomplete="current-password">
                            </div>
                            <div class="form-group">
                                <label>Completion notes (optional)</label>
                                <textarea name="completion_notes" rows="2"></textarea>
                            </div>
                            <button type="submit" class="btn btn-primary" title="Complete recovery">💾</button>
                        </form>
                        <form method="POST" onsubmit="return confirm('Reject this recovery request?');">
                            <input type="hidden" name="csrf_token" value="<?php echo sanitize($csrfToken); ?>">
                            <input type="hidden" name="action" value="reject">
                            <div class="form-group">
                                <label>Reject notes (optional)</label>
                                <textarea name="completion_notes" rows="2"></textarea>
                            </div>
                            <button type="submit" class="btn btn-danger btn-sm" title="Reject">🗑️</button>
                        </form>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
</body>
</html>
