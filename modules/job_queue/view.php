<?php
/**
 * Job Queue — read-only detail view.
 */

require '../../config/config.php';
require_once ROOT_PATH . 'includes/itm_job_queue.php';

$employeeId = (int)($_SESSION['employee_id'] ?? 0);
if (!itm_is_admin($conn, $employeeId)) {
    header('Location: ' . BASE_URL . 'dashboard.php');
    exit;
}

$jobId = (int)($_GET['id'] ?? 0);
$job = $jobId > 0 ? itm_job_queue_fetch_by_id($conn, $jobId) : null;
if (!$job) {
    header('Location: index.php');
    exit;
}

$moduleSlug = basename(dirname($_SERVER['PHP_SELF']));
$crud_title = 'Job Queue';
$csrfToken = itm_get_csrf_token();
$flashMessage = trim((string)($_GET['msg'] ?? ''));
$viewBackQuery = trim((string)($_GET['return_query'] ?? ''));
$indexHref = 'index.php' . ($viewBackQuery !== '' ? '?' . ltrim($viewBackQuery, '?') : '');
$status = (string)($job['status'] ?? '');
$payloadPretty = (string)($job['payload_json'] ?? '{}');
$decoded = json_decode($payloadPretty, true);
if (is_array($decoded)) {
    $encoded = json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($encoded !== false) {
        $payloadPretty = $encoded;
    }
}
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
        .jq-detail dt { font-weight:600; margin-top:12px; }
        .jq-detail dd { margin:4px 0 0; }
        .jq-mono { font-family:ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace; font-size:13px; white-space:pre-wrap; word-break:break-word; }
    </style>
</head>
<body>
<div class="container">
    <?php include '../../includes/sidebar.php'; ?>
    <div class="main-content">
        <?php include '../../includes/header.php'; ?>
        <div class="content">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;flex-wrap:wrap;gap:8px;">
                <h1 title="View job">🔎</h1>
                <div style="display:flex;gap:8px;flex-wrap:wrap;">
                    <?php if (in_array($status, ['failed', 'done'], true)): ?>
                    <form method="POST" action="index.php" style="display:inline;margin:0;">
                        <input type="hidden" name="csrf_token" value="<?php echo sanitize($csrfToken); ?>">
                        <input type="hidden" name="retry_job_id" value="<?php echo (int)$jobId; ?>">
                        <input type="hidden" name="return_query" value="<?php echo sanitize($viewBackQuery); ?>">
                        <button type="submit" class="btn btn-sm" title="Retry">🔄</button>
                    </form>
                    <?php endif; ?>
                    <a href="<?php echo sanitize($indexHref); ?>" class="btn" title="Back">🔙</a>
                </div>
            </div>

            <?php if ($flashMessage !== ''): ?>
            <div class="card" style="margin-bottom:16px;padding:12px 14px;border-color:#9eb8ee;background:#eef4ff;">
                <?php echo sanitize($flashMessage); ?>
            </div>
            <?php endif; ?>

            <div class="card jq-detail">
                <dl>
                    <dt>ID</dt>
                    <dd><?php echo (int)$jobId; ?></dd>
                    <dt>Type</dt>
                    <dd><?php echo sanitize((string)($job['job_type'] ?? '')); ?></dd>
                    <dt>Status</dt>
                    <dd><?php echo sanitize(ucfirst($status)); ?></dd>
                    <dt>Company</dt>
                    <dd><?php echo sanitize((string)($job['company_name'] ?? '—')); ?></dd>
                    <dt>Priority</dt>
                    <dd><?php echo (int)($job['priority'] ?? 0); ?></dd>
                    <dt>Attempts</dt>
                    <dd><?php echo (int)($job['attempts'] ?? 0); ?> / <?php echo (int)($job['max_attempts'] ?? 0); ?></dd>
                    <dt>Scheduled at</dt>
                    <dd><?php echo sanitize(itm_format_datetime_display($job['scheduled_at'] ?? '')); ?></dd>
                    <dt>Started at</dt>
                    <dd><?php echo sanitize(itm_format_audit_timestamp_display($job['started_at'] ?? '')); ?></dd>
                    <dt>Finished at</dt>
                    <dd><?php echo sanitize(itm_format_audit_timestamp_display($job['finished_at'] ?? '')); ?></dd>
                    <dt>Last error</dt>
                    <dd><?php echo sanitize((string)($job['last_error'] ?? '—')); ?></dd>
                    <dt>Created at</dt>
                    <dd><?php echo sanitize(itm_format_audit_timestamp_display($job['created_at'] ?? '')); ?></dd>
                    <dt>Payload JSON</dt>
                    <dd class="jq-mono"><?php echo sanitize($payloadPretty); ?></dd>
                </dl>
            </div>
        </div>
    </div>
</div>
</body>
</html>
