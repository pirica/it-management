<?php
/**
 * Schema Migrations Module - View
 *
 * Read-only detail for one migration audit row; admins may delete the history row.
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

$discovered = itm_database_migrations_resolve_file_row($filename);
$onDisk = $discovered !== null;
$currentChecksum = $onDisk ? (string)($discovered['checksum'] ?? '') : '';
$hasDrift = $onDisk && $recordedChecksum !== '' && $currentChecksum !== '' && $recordedChecksum !== $currentChecksum;

$moduleSlug = basename(dirname($_SERVER['PHP_SELF']));
$crud_title = 'Schema Migrations';
$migrateScriptUrl = BASE_URL . 'scripts/migrate.php?run=1';
$sqlHref = BASE_URL . 'scripts/migrate.php?run=1&sql=' . rawurlencode($filename);
$csrfToken = itm_get_csrf_token();
$flashMessage = trim((string)($_GET['msg'] ?? ''));
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
                <div style="display:flex;gap:8px;flex-wrap:wrap;">
                    <form method="POST" action="delete.php" style="display:inline;margin:0;" onsubmit="return confirm('Remove this migration history row? The live database schema is unchanged — only the audit record is deleted.');">
                        <input type="hidden" name="csrf_token" value="<?php echo sanitize($csrfToken); ?>">
                        <input type="hidden" name="id" value="<?php echo (int)($row['id'] ?? 0); ?>">
                        <input type="hidden" name="redirect" value="view.php">
                        <button type="submit" class="btn btn-sm btn-danger" title="Delete">🗑️</button>
                    </form>
                    <a href="index.php" class="btn" title="Back">🔙</a>
                </div>
            </div>

            <?php if ($flashMessage !== ''): ?>
            <div class="card" style="margin-bottom:16px;padding:12px 14px;border-color:#9eb8ee;background:#eef4ff;">
                <?php echo sanitize($flashMessage); ?>
            </div>
            <?php endif; ?>

            <div class="card" style="margin-bottom:16px;">
                <p style="margin:0;font-size:13px;line-height:1.45;color:var(--text-muted, #6b7280);">
                    Applied state is determined by live schema probes in
                    <a href="<?php echo sanitize($migrateScriptUrl); ?>">migrate.php</a>.
                    This row records when a migration file was satisfied and its checksum at record time.
                    Deleting the row removes audit history only — it does not roll back schema changes or delete the SQL file.
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
                            <?php if (in_array($filename, itm_database_migrations_bootstrap_filenames(), true)): ?>
                                <span class="badge badge-success" style="margin-left:8px;">Bootstrap</span>
                            <?php endif; ?>
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
