<?php
/**
 * Webmail — message detail (read pane).
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

$wmEmojiBack = itm_ui_action_emoji('back');
$wmEmojiDelete = itm_ui_action_emoji('delete');
$wmEmojiMailboxRead = "\u{1F4ED}";
$wmEmojiEnvelope = "\u{1F4E9}";
$wmEmojiStarOn = "\u{2B50}";
$wmEmojiStarOff = "\u{2606}";
$wmEmojiOutbox = "\u{1F4E4}";
$wmEmojiArchive = "\u{1F5C4}\u{FE0F}";
$wmEmojiRecycle = "\u{267B}\u{FE0F}";

$sentDisplay = "\u{2014}";
if ($row && !empty($row['sent_at'])) {
    $sentAt = (string)$row['sent_at'];
    $sentDisplay = itm_format_date_display(substr($sentAt, 0, 10));
    if (strlen($sentAt) > 10) {
        $sentDisplay .= ' ' . substr($sentAt, 11, 8);
    }
}

$isStar = $row && (int)($row['is_star'] ?? 0) === 1;
$isArchived = $row && (int)($row['is_archived'] ?? 0) === 1;
$isDeleted = $row && (int)($row['is_deleted'] ?? 0) === 1;
$ccField = $row ? trim((string)($row['cc_email'] ?? '')) : '';

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
        .webmail-read-pane { padding: 0; overflow: hidden; }
        .webmail-read-toolbar { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; padding: 12px 16px; border-bottom: 1px solid var(--border); background: var(--bg-secondary, rgba(127,127,127,.06)); }
        .webmail-read-header { padding: 20px 20px 16px; border-bottom: 1px solid var(--border); }
        .webmail-read-subject { margin: 0 0 12px; font-size: 1.35rem; line-height: 1.35; font-weight: 600; color: var(--text-primary); word-break: break-word; }
        .webmail-read-meta { display: grid; grid-template-columns: minmax(0, 1fr) auto; gap: 8px 16px; align-items: start; }
        .webmail-read-addresses { display: flex; flex-direction: column; gap: 6px; min-width: 0; }
        .webmail-read-line { display: flex; gap: 8px; font-size: 0.95rem; line-height: 1.4; min-width: 0; }
        .webmail-read-label { flex: 0 0 3.25rem; color: var(--text-secondary, #6b7280); font-weight: 500; }
        .webmail-read-value { flex: 1; min-width: 0; word-break: break-word; color: var(--text-primary); }
        .webmail-read-date { text-align: right; font-size: 0.875rem; color: var(--text-secondary, #6b7280); white-space: nowrap; }
        .webmail-read-badges { display: flex; flex-wrap: wrap; gap: 6px; margin-top: 12px; }
        .webmail-read-badge { font-size: 0.75rem; padding: 2px 8px; border-radius: 999px; border: 1px solid var(--border); background: var(--bg-primary, #fff); color: var(--text-secondary, #6b7280); }
        .webmail-read-badge-unread { font-weight: 600; color: var(--text-primary); border-color: var(--accent); }
        .webmail-read-body { padding: 20px; min-height: 200px; font-size: 1rem; line-height: 1.6; color: var(--text-primary); }
        .webmail-read-body.webmail-read-body-empty { color: var(--text-secondary, #6b7280); font-style: italic; }
        .webmail-body-view p { margin: 0 0 0.75em; }
        .webmail-body-view p:last-child { margin-bottom: 0; }
        .webmail-read-details { margin: 0 16px 16px; padding: 12px 16px; border: 1px solid var(--border); border-radius: 8px; font-size: 0.875rem; }
        .webmail-read-details summary { cursor: pointer; font-weight: 500; color: var(--text-secondary, #6b7280); }
        .webmail-read-details table { width: 100%; margin-top: 12px; }
        .webmail-read-details th { text-align: left; width: 140px; padding: 4px 8px 4px 0; color: var(--text-secondary, #6b7280); font-weight: 500; vertical-align: top; }
        .webmail-read-details td { padding: 4px 0; word-break: break-word; }
    </style>
</head>
<body>
<div class="container">
    <?php include '../../includes/sidebar.php'; ?>
    <div class="main-content">
        <?php include '../../includes/header.php'; ?>
        <div class="content">
            <div style="position:relative;display:flex;justify-content:center;align-items:center;margin-bottom:16px;min-height:40px;">
                <h1 style="margin:0;text-align:center;font-size:1.25rem;"><?php echo sanitize($moduleListHeading); ?></h1>
            </div>

            <div class="webmail-tabs">
                <?php webmail_render_tabs($folder); ?>
            </div>

            <div class="card webmail-read-pane">
                <?php if (!$row): ?>
                    <div class="alert alert-danger" style="margin:16px;">Message not found or not accessible.</div>
                    <div style="padding:0 16px 16px;">
                        <a href="<?php echo sanitize($backUrl); ?>" class="btn" title="Back"><?php echo $wmEmojiBack; ?></a>
                    </div>
                <?php else: ?>
                    <div class="webmail-read-toolbar">
                        <a href="<?php echo sanitize($backUrl); ?>" class="btn btn-sm" title="Back to <?php echo sanitize($folderLabels[$folder] ?? $folder); ?>"><?php echo $wmEmojiBack; ?></a>
                        <?php if (!$isDeleted): ?>
                            <form class="webmail-actions-form" method="POST" action="delete.php">
                                <?php $renderViewPostFields(true); ?>
                                <input type="hidden" name="webmail_action" value="<?php echo $messageIsRead ? 'mark_unread' : 'mark_read'; ?>">
                                <button type="submit" class="btn btn-sm" title="<?php echo $messageIsRead ? 'Mark as unread' : 'Mark as read'; ?>"><?php echo $messageIsRead ? $wmEmojiMailboxRead : $wmEmojiEnvelope; ?></button>
                            </form>
                            <form class="webmail-actions-form" method="POST" action="delete.php">
                                <?php $renderViewPostFields(true); ?>
                                <input type="hidden" name="webmail_action" value="toggle_star">
                                <button type="submit" class="btn btn-sm <?php echo $isStar ? 'webmail-star-on' : 'webmail-star-off'; ?>" title="Star"><?php echo $isStar ? $wmEmojiStarOn : $wmEmojiStarOff; ?></button>
                            </form>
                            <?php if ($folder === 'archived' || $isArchived): ?>
                                <form class="webmail-actions-form" method="POST" action="delete.php">
                                    <?php $renderViewPostFields(false); ?>
                                    <input type="hidden" name="webmail_action" value="unarchive">
                                    <button type="submit" class="btn btn-sm" title="Unarchive"><?php echo $wmEmojiOutbox; ?></button>
                                </form>
                            <?php else: ?>
                                <form class="webmail-actions-form" method="POST" action="delete.php">
                                    <?php $renderViewPostFields(false); ?>
                                    <input type="hidden" name="webmail_action" value="toggle_archive">
                                    <button type="submit" class="btn btn-sm" title="Archive"><?php echo $wmEmojiArchive; ?></button>
                                </form>
                            <?php endif; ?>
                            <form class="webmail-actions-form" method="POST" action="delete.php" data-itm-webmail-soft-delete="1">
                                <?php $renderViewPostFields(false); ?>
                                <input type="hidden" name="webmail_action" value="soft_delete">
                                <button type="submit" class="btn btn-sm btn-danger" title="Move to Trash" data-itm-auto-tooltip="off"><?php echo $wmEmojiDelete; ?></button>
                            </form>
                        <?php else: ?>
                            <form class="webmail-actions-form" method="POST" action="delete.php">
                                <?php $renderViewPostFields(false); ?>
                                <input type="hidden" name="webmail_action" value="restore">
                                <button type="submit" class="btn btn-sm" title="Restore"><?php echo $wmEmojiRecycle; ?></button>
                            </form>
                            <form class="webmail-actions-form" method="POST" action="delete.php" onsubmit="return confirm('Permanently delete this message? This cannot be undone.');">
                                <?php $renderViewPostFields(false); ?>
                                <input type="hidden" name="webmail_action" value="hard_delete">
                                <button type="submit" class="btn btn-sm btn-danger" title="Delete permanently"><?php echo $wmEmojiDelete; ?></button>
                            </form>
                        <?php endif; ?>
                    </div>

                    <header class="webmail-read-header">
                        <h2 class="webmail-read-subject"><?php echo sanitize((string)($row['subject'] ?? '(No subject)')); ?></h2>
                        <div class="webmail-read-meta">
                            <div class="webmail-read-addresses">
                                <div class="webmail-read-line">
                                    <span class="webmail-read-label">From</span>
                                    <span class="webmail-read-value"><?php echo sanitize((string)($row['from_email'] ?? '')); ?></span>
                                </div>
                                <div class="webmail-read-line">
                                    <span class="webmail-read-label">To</span>
                                    <span class="webmail-read-value"><?php echo sanitize((string)($row['to_email'] ?? '')); ?></span>
                                </div>
                                <?php if ($ccField !== ''): ?>
                                    <div class="webmail-read-line">
                                        <span class="webmail-read-label">CC</span>
                                        <span class="webmail-read-value"><?php echo sanitize($ccField); ?></span>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="webmail-read-date" title="Sent"><?php echo sanitize($sentDisplay); ?></div>
                        </div>
                        <div class="webmail-read-badges">
                            <span class="webmail-read-badge <?php echo $messageIsRead ? '' : 'webmail-read-badge-unread'; ?>"><?php echo $messageIsRead ? 'Read' : 'Unread'; ?></span>
                            <?php if ($isStar): ?>
                                <span class="webmail-read-badge">Starred</span>
                            <?php endif; ?>
                            <?php if ($isArchived): ?>
                                <span class="webmail-read-badge">Archived</span>
                            <?php endif; ?>
                            <?php if ($isDeleted): ?>
                                <span class="webmail-read-badge">In trash</span>
                            <?php endif; ?>
                            <?php
                            $statusLabel = trim((string)($row['status'] ?? ''));
                            if ($statusLabel !== ''):
                                ?>
                                <span class="webmail-read-badge"><?php echo sanitize($statusLabel); ?></span>
                            <?php endif; ?>
                        </div>
                    </header>

                    <div class="webmail-read-body<?php echo $bodyHtml === '' ? ' webmail-read-body-empty' : ''; ?>">
                        <?php if ($bodyHtml === ''): ?>
                            (No message body)
                        <?php else: ?>
                            <div class="webmail-body-view"><?php echo $bodyHtml; ?></div>
                        <?php endif; ?>
                    </div>

                    <details class="webmail-read-details">
                        <summary>Technical details</summary>
                        <table>
                            <tbody>
                            <tr><th>Status</th><td><?php echo sanitize((string)($row['status'] ?? '')); ?></td></tr>
                            <tr><th>Sent at</th><td><?php echo sanitize((string)($row['sent_at'] ?? '')); ?></td></tr>
                            <?php itm_crud_render_view_audit_meta_rows($conn, (int)$company_id, $row); ?>
                            </tbody>
                        </table>
                    </details>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<script src="../../js/theme.js"></script>
</body>
</html>
