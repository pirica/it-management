<?php
/**
 * Webmail — session-scoped mailbox (inbox, starred, sent, archived, trash).
 */

require_once dirname(__DIR__, 2) . '/config/config.php';
require_once __DIR__ . '/includes/webmail_helpers.php';

$company_id = (int)($_SESSION['company_id'] ?? 0);
$employee_id = (int)($_SESSION['employee_id'] ?? 0);
$sessionEmail = webmail_session_email();
$csrfToken = itm_get_csrf_token();
$uiConfig = itm_get_ui_configuration($conn, $company_id, $employee_id > 0 ? $employee_id : null);

if (($uiConfig['enable_all_error_reporting'] ?? 0) == 1) {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
} else {
    error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);
    ini_set('display_errors', '0');
}

$folder = webmail_resolve_folder((string)($_GET['folder'] ?? 'inbox'));
$statusFilter = trim((string)($_GET['status'] ?? ''));
if (!in_array($statusFilter, ['', 'sent', 'failed', 'received'], true)) {
    $statusFilter = '';
}
$searchRaw = trim((string)($_GET['search'] ?? ''));
$starredFilter = trim((string)($_GET['starred'] ?? ''));
$archivedFilter = trim((string)($_GET['archived'] ?? ''));
$dateFrom = trim((string)($_GET['date_from'] ?? ''));
$dateTo = trim((string)($_GET['date_to'] ?? ''));
$sort = trim((string)($_GET['sort'] ?? 'sent_at'));
$dir = strtoupper((string)($_GET['dir'] ?? 'DESC')) === 'ASC' ? 'ASC' : 'DESC';
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = itm_resolve_records_per_page($uiConfig ?? null);

$errors = [];
$notices = [];
if (!empty($_SESSION['webmail_notice'])) {
    $notices[] = (string)$_SESSION['webmail_notice'];
    unset($_SESSION['webmail_notice']);
}
if ($sessionEmail === '') {
    $errors[] = 'Your account has no email on file. Add a work or personal email in your profile to use Webmail.';
}

$filters = [
    'status' => $statusFilter,
    'starred' => $starredFilter,
    'archived' => $archivedFilter,
    'date_from' => $dateFrom,
    'date_to' => $dateTo,
    'search' => $searchRaw,
    'sort' => $sort,
    'dir' => $dir,
];

$listResult = ['rows' => [], 'total' => 0, 'page' => 1, 'total_pages' => 1];
if ($sessionEmail !== '') {
    $listResult = webmail_fetch_list($conn, $folder, $company_id, $employee_id, $sessionEmail, $filters, $perPage, $page);
}
$rows = $listResult['rows'];
$totalRows = (int)$listResult['total'];
$totalPages = (int)($listResult['total_pages'] ?? 1);
$page = (int)($listResult['page'] ?? $page);

$urlBase = [
    'folder' => $folder,
];
if ($statusFilter !== '') {
    $urlBase['status'] = $statusFilter;
}
if ($starredFilter !== '') {
    $urlBase['starred'] = $starredFilter;
}
if ($archivedFilter !== '') {
    $urlBase['archived'] = $archivedFilter;
}
if ($searchRaw !== '') {
    $urlBase['search'] = $searchRaw;
}
if ($dateFrom !== '') {
    $urlBase['date_from'] = $dateFrom;
}
if ($dateTo !== '') {
    $urlBase['date_to'] = $dateTo;
}
$urlBase['sort'] = $sort;
$urlBase['dir'] = $dir;

$buildUrl = static function (array $extra = []) use ($urlBase, $page): string {
    $q = array_merge($urlBase, ['page' => $page], $extra);

    return 'index.php?' . http_build_query($q);
};

$folderLabels = [
    'inbox' => 'Inbox',
    'starred' => 'Starred',
    'sent' => 'Sent',
    'archived' => 'Archived',
    'trash' => 'Trash',
];
$folderTitle = $folderLabels[$folder] ?? 'Inbox';

$moduleListHeading = function_exists('itm_sidebar_label_for_module')
    ? itm_sidebar_label_for_module('webmail', 'Webmail')
    : 'Webmail';

$crud_title = $folderTitle;
require_once ROOT_PATH . 'includes/itm_crud_browser_title.php';
$crud_title = itm_crud_apply_module_icon_to_browser_title(
    $conn,
    $company_id,
    $employee_id,
    'webmail',
    (string)$crud_title
);
$currentUiConfig = $uiConfig ?? [];

$hiddenRedirectFields = static function () use ($folder, $statusFilter, $starredFilter, $archivedFilter, $searchRaw, $dateFrom, $dateTo, $sort, $dir, $page, $csrfToken): void {
    echo '<input type="hidden" name="csrf_token" value="' . sanitize($csrfToken) . '">';
    echo '<input type="hidden" name="folder" value="' . sanitize($folder) . '">';
    if ($statusFilter !== '') {
        echo '<input type="hidden" name="status" value="' . sanitize($statusFilter) . '">';
    }
    if ($starredFilter !== '') {
        echo '<input type="hidden" name="starred" value="' . sanitize($starredFilter) . '">';
    }
    if ($archivedFilter !== '') {
        echo '<input type="hidden" name="archived" value="' . sanitize($archivedFilter) . '">';
    }
    if ($searchRaw !== '') {
        echo '<input type="hidden" name="search" value="' . sanitize($searchRaw) . '">';
    }
    if ($dateFrom !== '') {
        echo '<input type="hidden" name="date_from" value="' . sanitize($dateFrom) . '">';
    }
    if ($dateTo !== '') {
        echo '<input type="hidden" name="date_to" value="' . sanitize($dateTo) . '">';
    }
    echo '<input type="hidden" name="sort" value="' . sanitize($sort) . '">';
    echo '<input type="hidden" name="dir" value="' . sanitize($dir) . '">';
    echo '<input type="hidden" name="page" value="' . (int)$page . '">';
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
        .webmail-row-unread td { font-weight: 600; }
    </style>
</head>
<body>
<div class="container">
    <?php include '../../includes/sidebar.php'; ?>
    <div class="main-content">
        <?php include '../../includes/header.php'; ?>
        <div class="content">
            <div data-itm-new-button-managed="server" style="position:relative;display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;gap:12px;flex-wrap:wrap;min-height:40px;">
                <a href="compose.php" class="btn btn-primary itm-list-new-button" title="Compose">➕</a>
                <h1 style="position:absolute;left:50%;transform:translateX(-50%);margin:0;text-align:center;"><?php echo sanitize($moduleListHeading); ?></h1>
                <span></span>
            </div>

            <?php echo itm_render_alert_errors($errors); ?>
            <?php foreach ($notices as $notice): ?>
                <div class="alert alert-success"><?php echo sanitize($notice); ?></div>
            <?php endforeach; ?>

            <div class="webmail-tabs">
                <?php foreach (webmail_folders() as $tabFolder): ?>
                    <a href="index.php?folder=<?php echo sanitize($tabFolder); ?>" class="webmail-tab <?php echo $folder === $tabFolder ? 'active' : ''; ?>"><?php echo sanitize($folderLabels[$tabFolder] ?? $tabFolder); ?></a>
                <?php endforeach; ?>
                <a href="compose.php" class="webmail-tab">Compose</a>
            </div>

            <div class="card" style="margin-bottom:16px;">
                <form method="GET" class="table-search-inline" style="display:flex;flex-wrap:wrap;gap:12px;align-items:flex-end;">
                    <input type="hidden" name="folder" value="<?php echo sanitize($folder); ?>">
                    <div class="form-group" style="margin:0;">
                        <label for="status">Status</label>
                        <select name="status" id="status" class="form-control">
                            <option value="">All</option>
                            <option value="sent" <?php echo $statusFilter === 'sent' ? 'selected' : ''; ?>>Sent</option>
                            <option value="failed" <?php echo $statusFilter === 'failed' ? 'selected' : ''; ?>>Failed</option>
                            <option value="received" <?php echo $statusFilter === 'received' ? 'selected' : ''; ?>>Received</option>
                        </select>
                    </div>
                    <?php if ($folder === 'inbox'): ?>
                    <div class="form-group" style="margin:0;">
                        <label for="starred">Starred</label>
                        <select name="starred" id="starred" class="form-control">
                            <option value="">Any</option>
                            <option value="1" <?php echo $starredFilter === '1' ? 'selected' : ''; ?>>Yes</option>
                            <option value="0" <?php echo $starredFilter === '0' ? 'selected' : ''; ?>>No</option>
                        </select>
                    </div>
                    <div class="form-group" style="margin:0;">
                        <label for="archived">Archived</label>
                        <select name="archived" id="archived" class="form-control">
                            <option value="">No</option>
                            <option value="1" <?php echo $archivedFilter === '1' ? 'selected' : ''; ?>>Yes</option>
                        </select>
                    </div>
                    <?php endif; ?>
                    <div class="form-group" style="margin:0;">
                        <label for="date_from">From</label>
                        <input type="text" name="date_from" id="date_from" class="form-control" placeholder="dd/mm/yyyy" value="<?php echo sanitize($dateFrom); ?>">
                    </div>
                    <div class="form-group" style="margin:0;">
                        <label for="date_to">To</label>
                        <input type="text" name="date_to" id="date_to" class="form-control" placeholder="dd/mm/yyyy" value="<?php echo sanitize($dateTo); ?>">
                    </div>
                    <div class="form-group" style="margin:0;">
                        <label for="search">Search</label>
                        <input type="search" name="search" id="search" class="form-control" value="<?php echo sanitize($searchRaw); ?>" placeholder="Search...">
                    </div>
                    <button type="submit" class="btn btn-primary" title="Search">Search</button>
                    <?php if ($searchRaw !== '' || $statusFilter !== '' || $starredFilter !== '' || $archivedFilter !== '' || $dateFrom !== '' || $dateTo !== ''): ?>
                        <a class="btn" href="index.php?folder=<?php echo sanitize($folder); ?>" title="Clear">🔙</a>
                    <?php endif; ?>
                </form>
            </div>

            <?php
            // Why: Every folder lists the same addressing columns (From, To, CC) across tabs.
            $webmailListColspan = 8;
            ?>
            <div class="card">
                <p><?php echo sanitize($folderTitle); ?> — <?php echo (int)$totalRows; ?> message(s)</p>
                <div class="table-responsive">
                    <table class="data-table" data-itm-no-export-excel="1" data-itm-no-export-pdf="1">
                        <thead>
                        <tr>
                            <th>From</th>
                            <th>To</th>
                            <th>CC</th>
                            <th>Subject</th>
                            <th>Status</th>
                            <th>Read</th>
                            <th>Date</th>
                            <th class="itm-actions-cell" data-itm-actions-origin="1">Actions</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php if ($rows === []): ?>
                            <tr><td colspan="<?php echo (int)$webmailListColspan; ?>">No messages.</td></tr>
                        <?php else: ?>
                            <?php foreach ($rows as $row): ?>
                                <?php
                                $sentDisplay = '—';
                                if (!empty($row['sent_at'])) {
                                    $sentDisplay = itm_format_date_display((string)$row['sent_at']);
                                    if (strlen((string)$row['sent_at']) > 10) {
                                        $sentDisplay = itm_format_date_display(substr((string)$row['sent_at'], 0, 10)) . ' ' . substr((string)$row['sent_at'], 11, 8);
                                    }
                                }
                                $viewUrl = 'view.php?id=' . (int)$row['id'] . '&folder=' . rawurlencode($folder);
                                $isStar = (int)($row['is_star'] ?? 0) === 1;
                                $isRead = (int)($row['is_read'] ?? 0) === 1;
                                ?>
                                <tr class="<?php echo $isRead ? '' : 'webmail-row-unread'; ?>">
                                    <td><?php echo sanitize((string)($row['from_email'] ?? '')); ?></td>
                                    <td><?php echo sanitize((string)($row['to_email'] ?? '')); ?></td>
                                    <td><?php echo sanitize((string)($row['cc_email'] ?? '')); ?></td>
                                    <td><a href="<?php echo sanitize($viewUrl); ?>"><?php echo sanitize((string)($row['subject'] ?? '')); ?></a></td>
                                    <td><?php echo sanitize((string)($row['status'] ?? '')); ?></td>
                                    <td><?php echo $isRead ? 'Read' : 'Unread'; ?></td>
                                    <td><?php echo sanitize($sentDisplay); ?></td>
                                    <td class="itm-actions-cell" data-itm-actions-origin="1">
                                        <div class="itm-actions-wrap" style="display:flex;gap:6px;flex-wrap:wrap;">
                                            <a class="btn btn-sm" href="<?php echo sanitize($viewUrl); ?>" title="View">🔎</a>
                                            <?php if ($folder !== 'trash'): ?>
                                                <form class="webmail-actions-form" method="POST" action="delete.php">
                                                    <input type="hidden" name="id" value="<?php echo (int)$row['id']; ?>">
                                                    <input type="hidden" name="webmail_action" value="<?php echo $isRead ? 'mark_unread' : 'mark_read'; ?>">
                                                    <?php $hiddenRedirectFields(); ?>
                                                    <button type="submit" class="btn btn-sm" title="<?php echo $isRead ? 'Mark as unread' : 'Mark as read'; ?>"><?php echo $isRead ? '📭' : '📩'; ?></button>
                                                </form>
                                                <form class="webmail-actions-form" method="POST" action="delete.php">
                                                    <input type="hidden" name="id" value="<?php echo (int)$row['id']; ?>">
                                                    <input type="hidden" name="webmail_action" value="toggle_star">
                                                    <?php $hiddenRedirectFields(); ?>
                                                    <button type="submit" class="btn btn-sm <?php echo $isStar ? 'webmail-star-on' : 'webmail-star-off'; ?>" title="Star"><?php echo $isStar ? '⭐' : '☆'; ?></button>
                                                </form>
                                            <?php endif; ?>
                                            <?php if ($folder === 'trash'): ?>
                                                <form class="webmail-actions-form" method="POST" action="delete.php">
                                                    <input type="hidden" name="id" value="<?php echo (int)$row['id']; ?>">
                                                    <input type="hidden" name="webmail_action" value="restore">
                                                    <?php $hiddenRedirectFields(); ?>
                                                    <button type="submit" class="btn btn-sm" title="Restore">♻️</button>
                                                </form>
                                                <form class="webmail-actions-form" method="POST" action="delete.php" onsubmit="return confirm('Permanently delete this message? This cannot be undone.');">
                                                    <input type="hidden" name="id" value="<?php echo (int)$row['id']; ?>">
                                                    <input type="hidden" name="webmail_action" value="hard_delete">
                                                    <?php $hiddenRedirectFields(); ?>
                                                    <button type="submit" class="btn btn-sm btn-danger" title="Delete permanently">🗑️</button>
                                                </form>
                                            <?php else: ?>
                                                <?php if ($folder === 'archived'): ?>
                                                    <form class="webmail-actions-form" method="POST" action="delete.php">
                                                        <input type="hidden" name="id" value="<?php echo (int)$row['id']; ?>">
                                                        <input type="hidden" name="webmail_action" value="unarchive">
                                                        <?php $hiddenRedirectFields(); ?>
                                                        <button type="submit" class="btn btn-sm" title="Unarchive">📤</button>
                                                    </form>
                                                <?php else: ?>
                                                    <form class="webmail-actions-form" method="POST" action="delete.php">
                                                        <input type="hidden" name="id" value="<?php echo (int)$row['id']; ?>">
                                                        <input type="hidden" name="webmail_action" value="toggle_archive">
                                                        <?php $hiddenRedirectFields(); ?>
                                                        <button type="submit" class="btn btn-sm" title="Archive">🗄️</button>
                                                    </form>
                                                <?php endif; ?>
                                                <form class="webmail-actions-form" method="POST" action="delete.php" data-itm-webmail-soft-delete="1">
                                                    <input type="hidden" name="id" value="<?php echo (int)$row['id']; ?>">
                                                    <input type="hidden" name="webmail_action" value="soft_delete">
                                                    <?php $hiddenRedirectFields(); ?>
                                                    <button type="submit" class="btn btn-sm btn-danger" title="Move to Trash" data-itm-auto-tooltip="off">🗑️</button>
                                                </form>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <?php if ($totalPages > 1): ?>
                    <div style="margin-top:16px;display:flex;gap:8px;flex-wrap:wrap;align-items:center;">
                        <?php if ($page > 1): ?>
                            <a class="btn btn-sm" href="<?php echo sanitize($buildUrl(['page' => 1])); ?>" title="First page">⏮️</a>
                            <a class="btn btn-sm" href="<?php echo sanitize($buildUrl(['page' => $page - 1])); ?>" title="Previous page">◀️</a>
                        <?php endif; ?>
                        <span class="btn btn-sm" style="pointer-events:none;opacity:.8;">Page <?php echo (int)$page; ?> of <?php echo (int)$totalPages; ?></span>
                        <?php if ($page < $totalPages): ?>
                            <a class="btn btn-sm" href="<?php echo sanitize($buildUrl(['page' => $page + 1])); ?>" title="Next page">▶️</a>
                            <a class="btn btn-sm" href="<?php echo sanitize($buildUrl(['page' => $totalPages])); ?>" title="Last page">⏭️</a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<script src="../../js/theme.js"></script>
</body>
</html>
