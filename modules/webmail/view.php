<?php
/**
 * Webmail — message detail.
 */

require_once dirname(__DIR__, 2) . '/config/config.php';
require_once __DIR__ . '/includes/webmail_helpers.php';

$company_id = (int)($_SESSION['company_id'] ?? 0);
$employee_id = (int)($_SESSION['employee_id'] ?? 0);
$sessionEmail = webmail_session_email();
$id = (int)($_GET['id'] ?? 0);
$folder = webmail_resolve_folder((string)($_GET['folder'] ?? 'inbox'));
$csrfToken = itm_get_csrf_token();
$row = null;

if ($id > 0 && $company_id > 0) {
    $row = webmail_get_row_by_id($conn, $id, $company_id);
    if ($row && !webmail_row_visible_to_user($row, $sessionEmail, $employee_id, $folder === 'trash' ? 'trash' : '')) {
        if ((int)($row['is_deleted'] ?? 0) === 1) {
            if (!webmail_row_visible_to_user($row, $sessionEmail, $employee_id, 'trash')) {
                $row = null;
            }
        } elseif (!webmail_is_recipient($row, $sessionEmail) && !webmail_is_sender($row, $sessionEmail)) {
            $row = null;
        }
    }
}

$crud_title = 'View message';
require_once ROOT_PATH . 'includes/itm_crud_browser_title.php';
$crud_title = itm_crud_apply_module_icon_to_browser_title(
    $conn,
    $company_id,
    $employee_id,
    'webmail',
    (string)$crud_title
);
$currentUiConfig = $ui_config ?? [];
$backUrl = 'index.php?folder=' . rawurlencode($folder);
$bodyHtml = $row ? webmail_render_details_html((string)($row['details'] ?? '')) : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
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
            <h1 title="View message">🔎</h1>
            <div class="card">
                <?php if (!$row): ?>
                    <div class="alert alert-danger">Message not found or not accessible.</div>
                <?php else: ?>
                    <table>
                        <tbody>
                        <tr><th style="width:220px;">From</th><td><?php echo sanitize((string)($row['from_email'] ?? '')); ?></td></tr>
                        <tr><th>To</th><td><?php echo sanitize((string)($row['to_email'] ?? '')); ?></td></tr>
                        <tr><th>CC</th><td><?php echo sanitize((string)($row['cc_email'] ?? '')); ?></td></tr>
                        <tr><th>Subject</th><td><?php echo sanitize((string)($row['subject'] ?? '')); ?></td></tr>
                        <tr><th>Status</th><td><?php echo sanitize((string)($row['status'] ?? '')); ?></td></tr>
                        <tr><th>Starred</th><td><?php echo (int)($row['is_star'] ?? 0) === 1 ? 'Yes' : 'No'; ?></td></tr>
                        <tr><th>Archived</th><td><?php echo (int)($row['is_archived'] ?? 0) === 1 ? 'Yes' : 'No'; ?></td></tr>
                        <tr><th>Deleted</th><td><?php echo (int)($row['is_deleted'] ?? 0) === 1 ? 'Yes' : 'No'; ?></td></tr>
                        <tr><th>Sent at</th><td><?php echo sanitize((string)($row['sent_at'] ?? '')); ?></td></tr>
                        <tr><th>Body</th><td><div class="webmail-body-view"><?php echo $bodyHtml; ?></div></td></tr>
                        <?php itm_crud_render_view_audit_meta_rows($conn, (int)$company_id, $row); ?>
                        </tbody>
                    </table>
                <?php endif; ?>
                <div style="display:flex;gap:10px;margin-top:20px;flex-wrap:wrap;">
                    <a href="<?php echo sanitize($backUrl); ?>" class="btn" title="Back">🔙</a>
                    <?php if ($row && (int)($row['is_deleted'] ?? 0) === 0): ?>
                        <form method="POST" action="delete.php" style="display:inline;">
                            <input type="hidden" name="csrf_token" value="<?php echo sanitize($csrfToken); ?>">
                            <input type="hidden" name="id" value="<?php echo (int)$row['id']; ?>">
                            <input type="hidden" name="webmail_action" value="toggle_star">
                            <input type="hidden" name="folder" value="<?php echo sanitize($folder); ?>">
                            <button type="submit" class="btn btn-sm" title="Star"><?php echo (int)($row['is_star'] ?? 0) === 1 ? '⭐' : '☆'; ?></button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>
<script src="../../js/theme.js"></script>
</body>
</html>
