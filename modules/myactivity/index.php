<?php
/**
 * My Activity — employee-scoped read-only audit timeline.
 */

require_once '../../config/config.php';
require_once ROOT_PATH . 'includes/itm_myactivity.php';

$companyId = (int)($_SESSION['company_id'] ?? 0);
$employeeId = (int)($_SESSION['employee_id'] ?? 0);
if ($companyId <= 0 || $employeeId <= 0) {
    http_response_code(403);
    exit('Signed-in employee context is required.');
}

if ((int)($ui_config['enable_audit_logs'] ?? 1) !== 1) {
    http_response_code(403);
    exit('Audit logs are disabled in Settings.');
}

$moduleFilter = trim((string)($_GET['module'] ?? ''));
$actionFilter = strtoupper(trim((string)($_GET['action_filter'] ?? '')));
$dateFrom = trim((string)($_GET['date_from'] ?? ''));
$dateTo = trim((string)($_GET['date_to'] ?? ''));
$allowedActions = myactivity_allowed_actions();
if (!in_array($actionFilter, $allowedActions, true)) {
    $actionFilter = '';
}

if ($moduleFilter !== '' && (!function_exists('itm_is_safe_identifier') || !itm_is_safe_identifier($moduleFilter))) {
    $moduleFilter = '';
}

$where = ['al.company_id = ?', 'al.employee_id = ?'];
$params = [$companyId, $employeeId];
$types = 'ii';

if ($moduleFilter !== '') {
    $where[] = 'al.table_name = ?';
    $params[] = $moduleFilter;
    $types .= 's';
}

if ($actionFilter !== '') {
    $where[] = 'al.action = ?';
    $params[] = $actionFilter;
    $types .= 's';
}

if ($dateFrom !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom) === 1) {
    $where[] = 'al.created_at >= ?';
    $params[] = $dateFrom . ' 00:00:00';
    $types .= 's';
}
if ($dateTo !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo) === 1) {
    $where[] = 'al.created_at <= ?';
    $params[] = $dateTo . ' 23:59:59';
    $types .= 's';
}

$listQueryBase = [
    'module' => $moduleFilter,
    'action_filter' => $actionFilter,
    'date_from' => $dateFrom,
    'date_to' => $dateTo,
];

$perPage = itm_resolve_records_per_page($ui_config ?? null);
$page = max(1, (int)($_GET['page'] ?? 1));
$offset = ($page - 1) * $perPage;

$countSql = 'SELECT COUNT(*) AS total FROM audit_logs al WHERE ' . implode(' AND ', $where);
$countStmt = mysqli_prepare($conn, $countSql);
$totalRows = 0;
if ($countStmt) {
    mysqli_stmt_bind_param($countStmt, $types, ...$params);
    mysqli_stmt_execute($countStmt);
    $countRes = mysqli_stmt_get_result($countStmt);
    if ($countRes && ($countRow = mysqli_fetch_assoc($countRes))) {
        $totalRows = (int)($countRow['total'] ?? 0);
    }
    mysqli_stmt_close($countStmt);
}

$totalPages = max(1, (int)ceil($totalRows / max(1, $perPage)));
if ($page > $totalPages) {
    $page = $totalPages;
    $offset = ($page - 1) * $perPage;
}

$listSql = 'SELECT al.id, al.table_name, al.module_name, al.record_id, al.action, al.old_values, al.new_values, al.created_at '
    . 'FROM audit_logs al WHERE ' . implode(' AND ', $where)
    . ' ORDER BY al.created_at DESC LIMIT ? OFFSET ?';
$listStmt = mysqli_prepare($conn, $listSql);
$rows = [];
if ($listStmt) {
    $limit = $perPage;
    $listTypes = $types . 'ii';
    $listParams = array_merge($params, [$limit, $offset]);
    mysqli_stmt_bind_param($listStmt, $listTypes, ...$listParams);
    mysqli_stmt_execute($listStmt);
    $listRes = mysqli_stmt_get_result($listStmt);
    while ($listRes && ($row = mysqli_fetch_assoc($listRes))) {
        $rows[] = $row;
    }
    mysqli_stmt_close($listStmt);
}

$moduleOptions = [];
$moduleStmt = mysqli_prepare(
    $conn,
    'SELECT DISTINCT table_name FROM audit_logs WHERE company_id = ? AND employee_id = ? ORDER BY table_name ASC'
);
if ($moduleStmt) {
    mysqli_stmt_bind_param($moduleStmt, 'ii', $companyId, $employeeId);
    mysqli_stmt_execute($moduleStmt);
    $moduleRes = mysqli_stmt_get_result($moduleStmt);
    while ($moduleRes && ($moduleRow = mysqli_fetch_assoc($moduleRes))) {
        $name = trim((string)($moduleRow['table_name'] ?? ''));
        if ($name !== '') {
            $moduleOptions[] = $name;
        }
    }
    mysqli_stmt_close($moduleStmt);
}

$moduleSlug = 'myactivity';
$resolvedEmoji = itm_resolve_module_sidebar_icon($conn, $companyId, $employeeId, $moduleSlug);
$cleanTitle = itm_module_access_strip_catalog_label_prefix('My Activity');
$pageHeading = trim($resolvedEmoji . ' ' . $cleanTitle);
$crud_title = $cleanTitle;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo sanitize($pageHeading); ?> - <?php echo sanitize($app_name ?? itm_ui_config_app_name($ui_config ?? [])); ?></title>
    <?php echo itm_render_head_favicon_link($favicon_url ?? null); ?>
    <link rel="stylesheet" href="../../css/styles.css">
    <style>
        .myactivity-filters form { display:grid; grid-template-columns:1.5fr 1fr 1fr 1fr auto auto; gap:10px; align-items:end; }
        .myactivity-timeline { list-style:none; padding:0; margin:0; font-size:13px; }
        .myactivity-timeline-item { padding-bottom:14px; border-left:2px solid var(--border); padding-left:15px; position:relative; color:var(--text-primary); }
        .myactivity-timeline-item::after { content:''; position:absolute; left:-6px; top:4px; width:10px; height:10px; border-radius:50%; background:var(--accent); }
        .myactivity-timeline-meta { color:#586069; font-size:11px; margin-top:4px; }
        .myactivity-row-chip { display:inline-block; padding:3px 8px; border-radius:999px; font-size:11px; font-weight:700; border:1px solid transparent; }
        .myactivity-row-chip.insert { background:#e8f8ee; border-color:#9cd8b1; color:#18794e; }
        .myactivity-row-chip.update { background:#eef4ff; border-color:#9eb8ee; color:#1d4f91; }
        .myactivity-row-chip.delete { background:#fdecec; border-color:#f0b6b6; color:#a52727; }
        .itm-user-config-sidebar-link { color:inherit; text-decoration:none; }
        .itm-user-config-sidebar-link:hover { text-decoration:underline; }
        @media (max-width:900px) { .myactivity-filters form { grid-template-columns:1fr 1fr; } }
        @media (max-width:600px) { .myactivity-filters form { grid-template-columns:1fr; } }
        @media print { .myactivity-filters, .table-tools { display:none !important; } }
    </style>
</head>
<body>
<div class="container">
    <?php include '../../includes/sidebar.php'; ?>
    <div class="main-content">
        <?php include '../../includes/header.php'; ?>
        <div class="content">
            <h1 title="My Activity"><?php echo sanitize($resolvedEmoji !== '' ? $resolvedEmoji : '🕒'); ?></h1>

            <p style="margin:0 0 16px;color:var(--text-muted, #6b7280);font-size:13px;line-height:1.45;">
                Your own change history on shared company data. Private modules (passwords, notes, bookmarks, and similar) are not listed here.
            </p>

            <div class="card myactivity-filters" style="margin-bottom:16px;" data-itm-no-export-pdf="1" data-itm-no-export-excel="1" data-itm-no-import-excel="1">
                <form method="GET">
                    <div class="form-group" style="margin:0;">
                        <label for="module">Module</label>
                        <select name="module" id="module" class="form-control">
                            <option value="">All modules</option>
                            <?php foreach ($moduleOptions as $moduleName): ?>
                                <option value="<?php echo sanitize($moduleName); ?>" <?php echo $moduleFilter === $moduleName ? 'selected' : ''; ?>><?php echo sanitize($moduleName); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group" style="margin:0;">
                        <label for="action_filter">Action</label>
                        <select name="action_filter" id="action_filter" class="form-control">
                            <option value="">All</option>
                            <?php foreach ($allowedActions as $opt): ?>
                                <option value="<?php echo sanitize($opt); ?>" <?php echo $actionFilter === $opt ? 'selected' : ''; ?>><?php echo sanitize($opt); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group" style="margin:0;">
                        <label for="date_from">Date from</label>
                        <input type="date" name="date_from" id="date_from" class="form-control" value="<?php echo sanitize($dateFrom); ?>">
                    </div>
                    <div class="form-group" style="margin:0;">
                        <label for="date_to">Date to</label>
                        <input type="date" name="date_to" id="date_to" class="form-control" value="<?php echo sanitize($dateTo); ?>">
                    </div>
                    <button type="submit" class="btn btn-primary" title="Apply filters">Search</button>
                    <a href="index.php" class="btn" title="Clear filters">🔙</a>
                </form>
            </div>

            <div class="card" style="margin-bottom:16px;">
                <div class="card-header"><strong>🕒 Recent Activity</strong></div>
                <?php if ($rows === []): ?>
                    <p style="margin:12px 0 0;">No activity found for the selected filters.</p>
                <?php else: ?>
                    <ul class="myactivity-timeline">
                        <?php foreach ($rows as $row): ?>
                            <?php
                            $tableName = (string)($row['table_name'] ?? '');
                            $moduleHref = myactivity_resolve_module_href($tableName);
                            $actionClass = myactivity_action_chip_class($row['action'] ?? '');
                            $oldValues = myactivity_normalize_payload($row['old_values'] ?? '');
                            $newValues = myactivity_normalize_payload($row['new_values'] ?? '');
                            $oldDisplay = myactivity_describe_payload($row['action'] ?? '', $oldValues, true);
                            $newDisplay = myactivity_describe_payload($row['action'] ?? '', $newValues, false);
                            $previewText = 'Record #' . (int)($row['record_id'] ?? 0)
                                . ' — Old: ' . myactivity_preview_text($oldDisplay, 60)
                                . ' | New: ' . myactivity_preview_text($newDisplay, 60);
                            ?>
                            <li class="myactivity-timeline-item">
                                <span class="myactivity-row-chip <?php echo sanitize($actionClass); ?>"><?php echo sanitize((string)($row['action'] ?? '')); ?></span>
                                in <?php if ($moduleHref !== ''): ?>
                                    <a class="itm-user-config-sidebar-link" href="<?php echo sanitize('../../' . ltrim($moduleHref, '/')); ?>" target="_blank" rel="noopener noreferrer"><?php echo sanitize($tableName); ?></a>
                                <?php else: ?>
                                    <?php echo sanitize($tableName); ?>
                                <?php endif; ?>
                                <div class="myactivity-timeline-meta">
                                    <?php echo sanitize(myactivity_format_display_datetime($row['created_at'] ?? '')); ?>
                                    · <a href="view.php?id=<?php echo (int)($row['id'] ?? 0); ?>" title="View details">🔎</a>
                                </div>
                                <div class="myactivity-timeline-meta" title="<?php echo sanitize($previewText); ?>"><?php echo sanitize(myactivity_preview_text($previewText, 140)); ?></div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>

            <div class="card" data-itm-no-import-excel="1">
                <div class="card-header"><strong>Activity list</strong></div>
                <table class="table">
                    <thead>
                        <tr>
                            <th>Date &amp; Time</th>
                            <th>Action</th>
                            <th>Module</th>
                            <th>Record ID</th>
                            <th>Summary</th>
                            <th class="itm-actions-cell" data-itm-actions-origin="1">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if ($rows === []): ?>
                        <tr><td colspan="6">No activity found.</td></tr>
                    <?php else: ?>
                    <?php foreach ($rows as $row): ?>
                        <?php
                        $oldValues = myactivity_normalize_payload($row['old_values'] ?? '');
                        $newValues = myactivity_normalize_payload($row['new_values'] ?? '');
                        $oldDisplay = myactivity_describe_payload($row['action'] ?? '', $oldValues, true);
                        $newDisplay = myactivity_describe_payload($row['action'] ?? '', $newValues, false);
                        $summary = 'Old: ' . myactivity_preview_text($oldDisplay, 200) . ' | New: ' . myactivity_preview_text($newDisplay, 200);
                        ?>
                        <tr>
                            <td><?php echo sanitize((string)($row['created_at'] ?? '')); ?></td>
                            <td><?php echo sanitize((string)($row['action'] ?? '')); ?></td>
                            <td><?php echo sanitize((string)($row['table_name'] ?? '')); ?></td>
                            <td><?php echo (int)($row['record_id'] ?? 0); ?></td>
                            <td><?php echo sanitize($summary); ?></td>
                            <td class="itm-actions-cell" data-itm-actions-origin="1">
                                <div class="itm-actions-wrap">
                                    <a class="btn btn-sm" href="view.php?id=<?php echo (int)($row['id'] ?? 0); ?>" title="View">🔎</a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <?php if ($totalRows > $perPage): ?>
                <div style="display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;margin-top:12px;">
                    <div>Showing <?php echo $offset + 1; ?>-<?php echo min($offset + $perPage, $totalRows); ?> of <?php echo (int)$totalRows; ?></div>
                    <div style="display:flex;gap:6px;flex-wrap:wrap;">
                        <?php if ($page > 1): ?>
                            <a class="btn btn-sm" href="?<?php echo sanitize(myactivity_build_query(array_merge($listQueryBase, ['page' => 1]))); ?>" title="First page">⏮️</a>
                            <a class="btn btn-sm" href="?<?php echo sanitize(myactivity_build_query(array_merge($listQueryBase, ['page' => $page - 1]))); ?>" title="Previous page">◀️</a>
                        <?php endif; ?>
                        <span class="btn btn-sm" style="pointer-events:none;opacity:.8;">Page <?php echo (int)$page; ?> of <?php echo (int)$totalPages; ?></span>
                        <?php if ($page < $totalPages): ?>
                            <a class="btn btn-sm" href="?<?php echo sanitize(myactivity_build_query(array_merge($listQueryBase, ['page' => $page + 1]))); ?>" title="Next page">▶️</a>
                            <a class="btn btn-sm" href="?<?php echo sanitize(myactivity_build_query(array_merge($listQueryBase, ['page' => $totalPages]))); ?>" title="Last page">⏭️</a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>

            <div style="margin-top:16px;">
                <a href="../../user-config.php" class="btn" title="Back to profile">🔙</a>
            </div>
        </div>
    </div>
</div>
<script src="../../js/theme.js"></script>
</body>
</html>
