<?php
/**
 * Webmail — message detail (plain field list).
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

$messageIsRead = false;
if ($row && $company_id > 0 && $employee_id > 0) {
    if ((int)($row['is_deleted'] ?? 0) === 0) {
        webmail_mark_read($conn, $id, $company_id, $employee_id, $sessionEmail);
    }
    $messageIsRead = webmail_is_email_read($conn, $id, $company_id, $employee_id);
}

$folderLabels = [
    'inbox' => 'Inbox',
    'starred' => 'Starred',
    'sent' => 'Sent',
    'archived' => 'Archived',
    'trash' => 'Trash',
];

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

$isStar = $row && (int)($row['is_star'] ?? 0) === 1;
$isArchived = $row && (int)($row['is_archived'] ?? 0) === 1;
$isDeleted = $row && (int)($row['is_deleted'] ?? 0) === 1;

$moduleListHeading = function_exists('itm_sidebar_label_for_module')
    ? itm_sidebar_label_for_module('webmail', 'Webmail')
    : 'Webmail';

$renderViewPostFields = static function (bool $returnToView = true) use ($csrfToken, $folder, $id): void {
    echo '<input type="hidden" name="csrf_token" value="' . sanitize($csrfToken) . '">';
    echo '<input type="hidden" name="id" value="' . (int)$id . '">';
    echo '<input type="hidden" name="folder" value="' . sanitize($folder) . '">';
    if ($returnToView) {
        echo '<input type="hidden" name="return_to" value="view">';
    }
};

$webmailViewScalar = static function (?array $row, string $key): string {
    if (!$row || !array_key_exists($key, $row) || $row[$key] === null || (string)$row[$key] === '') {
        return '';
    }

    return (string)$row[$key];
};

$sentDisplay = '';
if ($row && !empty($row['sent_at'])) {
    $sentAt = (string)$row['sent_at'];
    $sentDisplay = itm_format_date_display(substr($sentAt, 0, 10));
    if (strlen($sentAt) > 10) {
        $sentDisplay .= ' ' . substr($sentAt, 11, 8);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= sanitize($crud_title) ?> - <?php echo sanitize($app_name ?? itm_ui_config_app_name($currentUiConfig)); ?></title>
    <?php echo itm_render_head_favicon_link($favicon_url ?? null); ?>
    <link rel="stylesheet" href="../../css/styles.css">
    <style>
        .webmail-tabs { display: flex; gap: 10px; margin-bottom: 20px; border-bottom: 1px solid var(--border); padding-bottom: 10px; flex-wrap: wrap; align-items: center; }
        .webmail-tab { padding: 8px 16px; text-decoration: none; color: var(--text-primary); border-radius: 6px; font-weight: 500; }
        .webmail-tab.active { background: var(--accent); color: #fff; font-weight: 600; }
        .webmail-actions-form { display: inline; }
        .webmail-star-on { opacity: 1; }
        .webmail-star-off { opacity: 0.35; }
    </style>
</head>
<body>
<div class="container">
    <?php include '../../includes/sidebar.php'; ?>
    <div class="main-content">
        <?php include '../../includes/header.php'; ?>
        <div class="content">
            <div class="webmail-tabs">
                <?php foreach (webmail_folders() as $tabFolder): ?>
                    <a href="index.php?folder=<?php echo sanitize($tabFolder); ?>" class="webmail-tab <?php echo $folder === $tabFolder ? 'active' : ''; ?>"><?php echo sanitize($folderLabels[$tabFolder] ?? $tabFolder); ?></a>
                <?php endforeach; ?>
                <a href="compose.php" class="webmail-tab">Compose</a>
            </div>

            <div class="card">
                <?php if (!$row): ?>
                    <div class="alert alert-danger">Message not found or not accessible.</div>
                <?php else: ?>
                    <table>
                        <tbody>
                        <tr><th style="width:220px;">ID</th><td><?php echo (int)($row['id'] ?? 0); ?></td></tr>
                        <tr><th>SMTP config ID</th><td><?php echo sanitize($webmailViewScalar($row, 'smtp_config_id')); ?></td></tr>
                        <tr><th>From</th><td><?php echo sanitize($webmailViewScalar($row, 'from_email')); ?></td></tr>
                        <tr><th>To</th><td><?php echo sanitize($webmailViewScalar($row, 'to_email')); ?></td></tr>
                        <tr><th>CC</th><td><?php echo sanitize($webmailViewScalar($row, 'cc_email')); ?></td></tr>
                        <tr><th>Subject</th><td><?php echo sanitize($webmailViewScalar($row, 'subject')); ?></td></tr>
                        <tr><th>Status</th><td><?php echo sanitize($webmailViewScalar($row, 'status')); ?></td></tr>
                        <tr><th>Read</th><td><?php echo $messageIsRead ? 'Read' : 'Unread'; ?></td></tr>
                        <tr><th>Starred</th><td><?php echo $isStar ? 'Yes' : 'No'; ?></td></tr>
                        <tr><th>Archived</th><td><?php echo $isArchived ? 'Yes' : 'No'; ?></td></tr>
                        <tr><th>Deleted</th><td><?php echo $isDeleted ? 'Yes' : 'No'; ?></td></tr>
                        <tr><th>Active</th><td><?php echo (int)($row['active'] ?? 0) === 1 ? 'Yes' : 'No'; ?></td></tr>
                        <tr><th>Sent at</th><td><?php echo sanitize($sentDisplay !== '' ? $sentDisplay : $webmailViewScalar($row, 'sent_at')); ?></td></tr>
                        <tr><th>Body</th><td><div class="webmail-body-view"><?php echo $bodyHtml !== '' ? $bodyHtml : '—'; ?></div></td></tr>
                        <?php itm_crud_render_view_audit_meta_rows($conn, (int)$company_id, $row); ?>
                        </tbody>
                    </table>
                <?php endif; ?>
                <div style="display:flex;gap:10px;margin-top:20px;flex-wrap:wrap;">
                    <a href="<?php echo sanitize($backUrl); ?>" class="btn" title="Back">🔙</a>
                    <?php if ($row && !$isDeleted): ?>
                        <form class="webmail-actions-form" method="POST" action="delete.php">
                            <?php $renderViewPostFields(true); ?>
                            <input type="hidden" name="webmail_action" value="<?php echo $messageIsRead ? 'mark_unread' : 'mark_read'; ?>">
                            <button type="submit" class="btn btn-sm" title="<?php echo $messageIsRead ? 'Mark as unread' : 'Mark as read'; ?>"><?php echo $messageIsRead ? '📭' : '📩'; ?></button>
                        </form>
                        <form class="webmail-actions-form" method="POST" action="delete.php">
                            <?php $renderViewPostFields(true); ?>
                            <input type="hidden" name="webmail_action" value="toggle_star">
                            <button type="submit" class="btn btn-sm <?php echo $isStar ? 'webmail-star-on' : 'webmail-star-off'; ?>" title="Star"><?php echo $isStar ? '⭐' : '☆'; ?></button>
                        </form>
                        <?php if ($folder === 'archived' || $isArchived): ?>
                            <form class="webmail-actions-form" method="POST" action="delete.php">
                                <?php $renderViewPostFields(false); ?>
                                <input type="hidden" name="webmail_action" value="unarchive">
                                <button type="submit" class="btn btn-sm" title="Unarchive">📤</button>
                            </form>
                        <?php else: ?>
                            <form class="webmail-actions-form" method="POST" action="delete.php">
                                <?php $renderViewPostFields(false); ?>
                                <input type="hidden" name="webmail_action" value="toggle_archive">
                                <button type="submit" class="btn btn-sm" title="Archive">🗄️</button>
                            </form>
                        <?php endif; ?>
                        <form class="webmail-actions-form" method="POST" action="delete.php" data-itm-webmail-soft-delete="1">
                            <?php $renderViewPostFields(false); ?>
                            <input type="hidden" name="webmail_action" value="soft_delete">
                            <button type="submit" class="btn btn-sm btn-danger" title="Move to Trash" data-itm-auto-tooltip="off">🗑️</button>
                        </form>
                    <?php elseif ($row): ?>
                        <form class="webmail-actions-form" method="POST" action="delete.php">
                            <?php $renderViewPostFields(false); ?>
                            <input type="hidden" name="webmail_action" value="restore">
                            <button type="submit" class="btn btn-sm" title="Restore">♻️</button>
                        </form>
                        <form class="webmail-actions-form" method="POST" action="delete.php" onsubmit="return confirm('Permanently delete this message? This cannot be undone.');">
                            <?php $renderViewPostFields(false); ?>
                            <input type="hidden" name="webmail_action" value="hard_delete">
                            <button type="submit" class="btn btn-sm btn-danger" title="Delete permanently">🗑️</button>
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
