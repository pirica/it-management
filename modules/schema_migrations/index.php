<?php
/**
 * Schema Migrations Module - Index
 *
 * Admin read-only audit history for db/migrations apply records.
 * Live schema probe (migrate.php) is authoritative — this table is history only.
 */

require '../../config/config.php';
require_once ROOT_PATH . 'includes/itm_database_migrations.php';

$employeeId = (int)($_SESSION['employee_id'] ?? 0);
if (!itm_is_admin($conn, $employeeId)) {
    header('Location: ' . BASE_URL . 'dashboard.php');
    exit;
}

/**
 * Build query string for list filters, sort, and pagination.
 */
function itm_schema_migrations_build_query(array $params): string
{
    $normalized = [];
    foreach ($params as $key => $value) {
        if ($value === null || $value === '') {
            continue;
        }
        $normalized[$key] = $value;
    }

    return http_build_query($normalized);
}

$search = trim((string)($_GET['search'] ?? ''));
$sort = (string)($_GET['sort'] ?? 'applied_at');
$dir = strtoupper((string)($_GET['dir'] ?? 'DESC'));
$sortableColumns = [
    'filename' => 'filename',
    'checksum' => 'checksum',
    'applied_at' => 'applied_at',
];
if (!isset($sortableColumns[$sort])) {
    $sort = 'applied_at';
}
if (!in_array($dir, ['ASC', 'DESC'], true)) {
    $dir = 'DESC';
}
$sortSql = $sortableColumns[$sort] . ' ' . $dir;

$perPage = itm_resolve_records_per_page($ui_config ?? null);
$page = max(1, (int)($_GET['page'] ?? 1));

itm_database_migrations_ensure_table($conn);
$migrationStatus = itm_database_migrations_build_status($conn);

$discoveredByFilename = [];
foreach (itm_database_migrations_discover_files() as $fileRow) {
    $discoveredByFilename[(string)$fileRow['filename']] = $fileRow;
}
foreach (itm_database_migrations_bootstrap_filenames() as $bootstrapName) {
    $bootstrapRow = itm_database_migrations_resolve_bootstrap_file($bootstrapName);
    if ($bootstrapRow !== null) {
        $discoveredByFilename[$bootstrapName] = $bootstrapRow;
    }
}
$bootstrapFilenames = array_fill_keys(itm_database_migrations_bootstrap_filenames(), true);

$where = [];
$params = [];
$types = '';
if ($search !== '') {
    $where[] = '(filename LIKE ? OR checksum LIKE ?)';
    $searchEsc = '%' . $search . '%';
    $params[] = $searchEsc;
    $params[] = $searchEsc;
    $types .= 'ss';
}
$whereSql = $where !== [] ? ' WHERE ' . implode(' AND ', $where) : '';

$countSql = 'SELECT COUNT(*) AS total FROM schema_migrations' . $whereSql;
$countStmt = mysqli_prepare($conn, $countSql);
$totalRows = 0;
if ($countStmt) {
    if ($types !== '') {
        mysqli_stmt_bind_param($countStmt, $types, ...$params);
    }
    mysqli_stmt_execute($countStmt);
    $countResult = mysqli_stmt_get_result($countStmt);
    if ($countResult && ($countRow = mysqli_fetch_assoc($countResult))) {
        $totalRows = (int)($countRow['total'] ?? 0);
    }
    mysqli_stmt_close($countStmt);
}

$totalPages = max(1, (int)ceil($totalRows / max(1, $perPage)));
if ($page > $totalPages) {
    $page = $totalPages;
}
$offset = ($page - 1) * $perPage;

$listSql = 'SELECT id, filename, checksum, applied_at FROM schema_migrations'
    . $whereSql
    . ' ORDER BY ' . $sortSql
    . ' LIMIT ' . (int)$perPage . ' OFFSET ' . (int)$offset;
$listStmt = mysqli_prepare($conn, $listSql);
$rows = [];
if ($listStmt) {
    if ($types !== '') {
        mysqli_stmt_bind_param($listStmt, $types, ...$params);
    }
    mysqli_stmt_execute($listStmt);
    $listResult = mysqli_stmt_get_result($listStmt);
    while ($listResult && ($row = mysqli_fetch_assoc($listResult))) {
        $rows[] = $row;
    }
    mysqli_stmt_close($listStmt);
}

$listQueryBase = [
    'search' => $search,
    'sort' => $sort,
    'dir' => $dir,
];

$moduleSlug = basename(dirname($_SERVER['PHP_SELF']));
$moduleListHeading = itm_sidebar_label_for_module($moduleSlug) ?: '📜 Schema Migrations';
$newButtonPosition = itm_resolve_new_button_position($ui_config);
$crud_title = 'Schema Migrations';
$migrateScriptUrl = BASE_URL . 'scripts/migrate.php?run=1';
$verifyScriptUrl = BASE_URL . 'scripts/verify_db_migrations.php?run=1';
$migrateApplyUrl = BASE_URL . 'scripts/migrate.php?run=1&apply=1';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <?php
    require_once ROOT_PATH . 'includes/itm_crud_browser_title.php';
    $crud_title = itm_crud_apply_module_icon_to_browser_title(
        $conn,
        (int)($company_id ?? 0),
        $employeeId,
        $moduleSlug,
        (string)$crud_title
    );
    ?>
    <title><?= sanitize($crud_title) ?> - <?php echo sanitize($app_name ?? itm_ui_config_app_name($ui_config ?? [])); ?></title>
    <?php echo itm_render_head_favicon_link($favicon_url ?? null); ?>
    <link rel="stylesheet" href="../../css/styles.css">
    <style>
        .sm-toolbar { display:flex; align-items:center; justify-content:space-between; gap:12px; margin-bottom:16px; flex-wrap:wrap; position:relative; min-height:40px; }
        .sm-toolbar h1 { position:absolute; left:50%; transform:translateX(-50%); margin:0; text-align:center; font-size:1.5rem; font-weight:700; }
        .sm-actions { display:flex; gap:8px; flex-wrap:wrap; align-items:center; }
        .sm-kpis { display:grid; grid-template-columns:repeat(4,minmax(0,1fr)); gap:10px; margin-bottom:14px; }
        .sm-kpi { border:1px solid var(--border); border-radius:10px; padding:10px 12px; background:var(--input-bg); }
        .sm-kpi .label { font-size:12px; opacity:.8; margin-bottom:4px; }
        .sm-kpi .value { font-size:18px; font-weight:700; }
        .sm-filters form { display:grid; grid-template-columns:2fr auto auto; gap:10px; align-items:end; }
        .sm-checksum { font-family:ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace; font-size:12px; max-width:280px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
        .sm-badge { display:inline-block; padding:3px 8px; border-radius:999px; font-size:11px; font-weight:700; border:1px solid transparent; }
        .sm-badge.removed { background:#f3f4f6; border-color:#d1d5db; color:#4b5563; }
        .sm-badge.drift { background:#fdecec; border-color:#f0b6b6; color:#a52727; }
        .sm-badge.bootstrap { background:#eef4ff; border-color:#9eb8ee; color:#1d4f91; }
        .sm-sort-link { text-decoration:none; color:inherit; }
        @media (max-width:900px) { .sm-kpis { grid-template-columns:1fr 1fr; } .sm-filters form { grid-template-columns:1fr; } }
    </style>
</head>
<body>
<div class="container">
    <?php include '../../includes/sidebar.php'; ?>
    <div class="main-content">
        <?php include '../../includes/header.php'; ?>

        <div class="content">
            <div class="sm-toolbar" data-itm-new-button-managed="server">
                <?php if (in_array($newButtonPosition, ['left', 'left_right'], true)): ?>
                    <div class="sm-actions">
                        <a href="<?php echo sanitize($migrateScriptUrl); ?>" class="btn btn-sm btn-primary" title="Migration runner status">🔄</a>
                    </div>
                <?php else: ?>
                    <span aria-hidden="true"></span>
                <?php endif; ?>
                <h1><?php echo sanitize($moduleListHeading); ?></h1>
                <?php if (in_array($newButtonPosition, ['right', 'left_right'], true)): ?>
                    <div class="sm-actions">
                        <a href="<?php echo sanitize($migrateScriptUrl); ?>" class="btn btn-sm btn-primary" title="Migration runner status">🔄</a>
                    </div>
                <?php else: ?>
                    <span aria-hidden="true"></span>
                <?php endif; ?>
            </div>

            <p style="margin:0 0 16px;color:var(--text-muted, #6b7280);font-size:13px;line-height:1.45;">
                Live database probes in <a href="<?php echo sanitize($migrateScriptUrl); ?>">migrate.php</a> decide Applied vs Pending.
                This list is audit history only — satisfied migrations may be recorded without re-running destructive SQL.
            </p>

            <div class="card" style="margin-bottom:16px;">
                <div class="sm-actions" style="margin-bottom:12px;">
                    <a href="<?php echo sanitize($migrateScriptUrl); ?>" class="btn btn-sm btn-primary" title="Migration runner">migrate.php?run=1</a>
                    <a href="<?php echo sanitize($migrateApplyUrl); ?>" class="btn btn-sm" title="Apply pending migrations">migrate.php?run=1&amp;apply=1</a>
                    <a href="<?php echo sanitize($verifyScriptUrl); ?>" class="btn btn-sm" title="Schema probe report">verify_db_migrations.php?run=1</a>
                </div>
                <div class="sm-kpis">
                    <div class="sm-kpi">
                        <div class="label">Files on disk</div>
                        <div class="value"><?php echo (int)($migrationStatus['file_count'] ?? 0); ?></div>
                    </div>
                    <div class="sm-kpi">
                        <div class="label">Applied (probe)</div>
                        <div class="value"><?php echo (int)($migrationStatus['applied_count'] ?? 0); ?></div>
                    </div>
                    <div class="sm-kpi">
                        <div class="label">Pending</div>
                        <div class="value"><?php echo (int)($migrationStatus['pending_count'] ?? 0); ?></div>
                    </div>
                    <div class="sm-kpi">
                        <div class="label">Checksum drift</div>
                        <div class="value"><?php echo (int)($migrationStatus['drift_count'] ?? 0); ?></div>
                    </div>
                </div>
                <div style="font-size:13px;opacity:.85;">
                    History rows: <strong><?php echo (int)$totalRows; ?></strong>
                    · Database: <code><?php echo sanitize((string)($migrationStatus['database'] ?? '')); ?></code>
                </div>
            </div>

            <div class="card sm-filters" style="margin-bottom:16px;">
                <form method="GET">
                    <div class="form-group" style="margin:0;">
                        <label>Search</label>
                        <input type="text" name="search" value="<?php echo sanitize($search); ?>" placeholder="Filename or checksum">
                    </div>
                    <button type="submit" class="btn btn-primary">Search</button>
                    <a href="index.php" class="btn" title="Clear">🔙</a>
                </form>
            </div>

            <div class="card">
                <table class="table" data-itm-no-import-excel="1" data-itm-no-export-excel="1" data-itm-no-export-pdf="1">
                    <thead>
                        <tr>
                            <th class="itm-actions-cell" data-itm-actions-origin="1">Actions</th>
                            <th>
                                <a class="sm-sort-link" href="?<?php echo sanitize(itm_schema_migrations_build_query(array_merge($listQueryBase, ['sort' => 'filename', 'dir' => ($sort === 'filename' && $dir === 'ASC') ? 'DESC' : 'ASC', 'page' => 1]))); ?>">
                                    Filename <?php echo $sort === 'filename' ? ($dir === 'ASC' ? '▲' : '▼') : ''; ?>
                                </a>
                            </th>
                            <th>
                                <a class="sm-sort-link" href="?<?php echo sanitize(itm_schema_migrations_build_query(array_merge($listQueryBase, ['sort' => 'checksum', 'dir' => ($sort === 'checksum' && $dir === 'ASC') ? 'DESC' : 'ASC', 'page' => 1]))); ?>">
                                    Checksum <?php echo $sort === 'checksum' ? ($dir === 'ASC' ? '▲' : '▼') : ''; ?>
                                </a>
                            </th>
                            <th>
                                <a class="sm-sort-link" href="?<?php echo sanitize(itm_schema_migrations_build_query(array_merge($listQueryBase, ['sort' => 'applied_at', 'dir' => ($sort === 'applied_at' && $dir === 'ASC') ? 'DESC' : 'ASC', 'page' => 1]))); ?>">
                                    Applied at <?php echo $sort === 'applied_at' ? ($dir === 'ASC' ? '▲' : '▼') : ''; ?>
                                </a>
                            </th>
                            <th>File</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if ($rows === []): ?>
                        <tr><td colspan="5">No migration history rows recorded yet.</td></tr>
                    <?php else: ?>
                        <?php foreach ($rows as $row): ?>
                            <?php
                            $rowId = (int)($row['id'] ?? 0);
                            $filename = (string)($row['filename'] ?? '');
                            $recordedChecksum = (string)($row['checksum'] ?? '');
                            $appliedAtDisplay = itm_format_audit_timestamp_display($row['applied_at'] ?? '');
                            $discovered = $discoveredByFilename[$filename] ?? null;
                            $onDisk = $discovered !== null;
                            $currentChecksum = $onDisk ? (string)($discovered['checksum'] ?? '') : '';
                            $hasDrift = $onDisk && $recordedChecksum !== '' && $currentChecksum !== '' && $recordedChecksum !== $currentChecksum;
                            $sqlHref = BASE_URL . 'scripts/migrate.php?run=1&sql=' . rawurlencode($filename);
                            ?>
                            <tr>
                                <td class="itm-actions-cell" data-itm-actions-origin="1">
                                    <div class="itm-actions-wrap">
                                        <a class="btn btn-sm" href="view.php?id=<?php echo $rowId; ?>" title="View">🔎</a>
                                    </div>
                                </td>
                                <td><code><?php echo sanitize($filename); ?></code></td>
                                <td>
                                    <span class="sm-checksum" title="<?php echo sanitize($recordedChecksum); ?>"><?php echo sanitize($recordedChecksum); ?></span>
                                </td>
                                <td><?php echo sanitize($appliedAtDisplay !== '' ? $appliedAtDisplay : '—'); ?></td>
                                <td>
                                    <?php if ($onDisk): ?>
                                        <a href="<?php echo sanitize($sqlHref); ?>" target="_blank" rel="noopener noreferrer" title="Open SQL in new tab">Open SQL</a>
                                        <?php if (isset($bootstrapFilenames[$filename])): ?>
                                            <span class="sm-badge bootstrap" title="Bootstrap file — not in runner apply loop">Bootstrap</span>
                                        <?php endif; ?>
                                        <?php if ($hasDrift): ?>
                                            <span class="sm-badge drift" title="File checksum differs from recorded value">Drift</span>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="sm-badge removed" title="Migration file removed from db/migrations — history only">File removed</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>

                <?php if ($totalRows > $perPage): ?>
                <div style="margin-top:12px;display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
                    <?php
                    $pageQuery = function ($targetPage) {
                        return itm_schema_migrations_build_query(array_merge($listQueryBase, ['page' => $targetPage]));
                    };
                    ?>
                    <?php if ($page > 1): ?>
                        <a class="btn btn-sm" href="?<?php echo sanitize($pageQuery(1)); ?>" title="<?php echo sanitize(itm_ui_pagination_title('first')); ?>"><?php echo itm_ui_pagination_emoji('first'); ?></a>
                        <a class="btn btn-sm" href="?<?php echo sanitize($pageQuery($page - 1)); ?>" title="<?php echo sanitize(itm_ui_pagination_title('previous')); ?>"><?php echo itm_ui_pagination_emoji('previous'); ?></a>
                    <?php endif; ?>
                    <span class="btn btn-sm" style="pointer-events:none;opacity:.8;">Page <?php echo $page; ?> of <?php echo $totalPages; ?></span>
                    <?php if ($page < $totalPages): ?>
                        <a class="btn btn-sm" href="?<?php echo sanitize($pageQuery($page + 1)); ?>" title="<?php echo sanitize(itm_ui_pagination_title('next')); ?>"><?php echo itm_ui_pagination_emoji('next'); ?></a>
                        <a class="btn btn-sm" href="?<?php echo sanitize($pageQuery($totalPages)); ?>" title="<?php echo sanitize(itm_ui_pagination_title('last')); ?>"><?php echo itm_ui_pagination_emoji('last'); ?></a>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
</body>
</html>
