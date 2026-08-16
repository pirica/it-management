<?php
/**
 * Schema Migrations Module - View
 *
 * Read-only detail for one migration audit row.
 */

require '../../config/config.php';
require_once ROOT_PATH . 'includes/itm_database_migrations.php';

$employeeId = (int)($_SESSION['employee_id'] ?? 0);
if (!itm_is_admin($conn, $employeeId)) {
    header('Location: ' . BASE_URL . 'dashboard.php');
    exit;
}

$rowId = (int)($_GET['id'] ?? 0);
if ($rowId <= 0) {
    header('Location: index.php');
    exit;
}

$stmt = mysqli_prepare($conn, 'SELECT id, filename, checksum, applied_at FROM schema_migrations WHERE id = ? LIMIT 1');
$row = null;
if ($stmt) {
    mysqli_stmt_bind_param($stmt, 'i', $rowId);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = $result ? mysqli_fetch_assoc($result) : null;
    mysqli_stmt_close($stmt);
}

if (!$row) {
    header('Location: index.php');
    exit;
}

$filename = (string)($row['filename'] ?? '');
$recordedChecksum = (string)($row['checksum'] ?? '');
$appliedAtDisplay = itm_format_audit_timestamp_display($row['applied_at'] ?? '');

$discovered = itm_database_migrations_resolve_discovered_file($filename);
$onDisk = $discovered !== null;
$currentChecksum = $onDisk ? (string)($discovered['checksum'] ?? '') : '';
$hasDrift = $onDisk && $recordedChecksum !== '' && $currentChecksum !== '' && $recordedChecksum !== $currentChecksum;

$moduleSlug = basename(dirname($_SERVER['PHP_SELF']));
$crud_title = 'Schema Migrations';
$migrateScriptUrl = BASE_URL . 'scripts/migrate.php?run=1';
$sqlHref = BASE_URL . 'scripts/migrate.php?run=1&sql=' . rawurlencode($filename);
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
        .sm-detail dt { font-weight:600; margin-top:12px; }
        .sm-detail dd { margin:4px 0 0; }
        .sm-mono { font-family:ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace; font-size:13px; word-break:break-all; }
    </style>
</head>
<body>
<div class="container">
    <?php include '../../includes/sidebar.php'; ?>
    <div class="main-content">
        <?php include '../../includes/header.php'; ?>

        <div class="content">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;flex-wrap:wrap;gap:8px;">
                <h1 title="View migration history record">🔎</h1>
                <a href="index.php" class="btn" title="Back">🔙</a>
            </div>

            <div class="card" style="margin-bottom:16px;">
                <p style="margin:0;font-size:13px;line-height:1.45;color:var(--text-muted, #6b7280);">
                    Applied state is determined by live schema probes in
                    <a href="<?php echo sanitize($migrateScriptUrl); ?>">migrate.php</a>.
                    This row records when a migration file was satisfied and its checksum at record time.
                </p>
            </div>

            <div class="card sm-detail">
                <dl>
                    <dt>ID</dt>
                    <dd><?php echo (int)($row['id'] ?? 0); ?></dd>

                    <dt>Filename</dt>
                    <dd class="sm-mono"><?php echo sanitize($filename); ?></dd>

                    <dt>Recorded checksum</dt>
                    <dd class="sm-mono"><?php echo sanitize($recordedChecksum); ?></dd>

                    <dt>Applied at</dt>
                    <dd><?php echo sanitize($appliedAtDisplay !== '' ? $appliedAtDisplay : '—'); ?></dd>

                    <dt>File on disk</dt>
                    <dd>
                        <?php if ($onDisk): ?>
                            Present in <code>db/migrations/</code>
                            <?php if ($hasDrift): ?>
                                <span class="badge badge-danger" style="margin-left:8px;">Checksum drift</span>
                            <?php endif; ?>
                        <?php else: ?>
                            Removed (history only)
                        <?php endif; ?>
                    </dd>

                    <?php if ($onDisk): ?>
                    <dt>Current file checksum</dt>
                    <dd class="sm-mono"><?php echo sanitize($currentChecksum); ?></dd>

                    <dt>Open SQL</dt>
                    <dd><a href="<?php echo sanitize($sqlHref); ?>" target="_blank" rel="noopener noreferrer">migrate.php?run=1&amp;sql=<?php echo sanitize(rawurlencode($filename)); ?></a></dd>
                    <?php endif; ?>
                </dl>
            </div>
        </div>
    </div>
</div>
</body>
</html>
