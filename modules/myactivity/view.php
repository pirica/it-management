<?php
/**
 * My Activity — single audit event detail (employee-scoped).
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

$auditId = (int)($_GET['id'] ?? 0);
$logRow = null;

if ($auditId > 0) {
    $stmt = mysqli_prepare(
        $conn,
        'SELECT al.* FROM audit_logs al '
        . 'WHERE al.id = ? AND al.company_id = ? AND al.employee_id = ? LIMIT 1'
    );
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, 'iii', $auditId, $companyId, $employeeId);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        if ($result && mysqli_num_rows($result) === 1) {
            $logRow = mysqli_fetch_assoc($result);
        }
        mysqli_stmt_close($stmt);
    }
}

$oldValuesDisplay = '—';
$newValuesDisplay = '—';
if ($logRow) {
    $actionValue = (string)($logRow['action'] ?? '');
    $oldValuesDisplay = myactivity_describe_payload($actionValue, myactivity_normalize_payload($logRow['old_values'] ?? ''), true);
    $newValuesDisplay = myactivity_describe_payload($actionValue, myactivity_normalize_payload($logRow['new_values'] ?? ''), false);
}

$tableName = (string)($logRow['table_name'] ?? '');
$moduleHref = myactivity_resolve_module_href($tableName);

$moduleSlug = 'myactivity';
$resolvedEmoji = itm_resolve_module_sidebar_icon($conn, $companyId, $employeeId, $moduleSlug);
$cleanTitle = itm_module_access_strip_catalog_label_prefix('View Activity');
$pageHeading = trim($resolvedEmoji . ' ' . $cleanTitle);
$crud_title = $cleanTitle;

$ipDisplayValue = (string)($logRow['ip_address'] ?? '—');
if ($logRow && function_exists('itm_pick_preferred_ip_for_display')) {
    $ipDisplayValue = itm_pick_preferred_ip_for_display($ipDisplayValue);
} elseif ($logRow) {
    $ipDisplayValue = trim($ipDisplayValue);
}
if ($ipDisplayValue === '') {
    $ipDisplayValue = '—';
}
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
        .myactivity-json { white-space:pre-wrap; word-break:break-word; margin:0; font-size:12px; line-height:1.4; background:var(--input-bg); border:1px solid var(--border); border-radius:8px; padding:10px; }
        .myactivity-row-chip { display:inline-block; padding:3px 8px; border-radius:999px; font-size:11px; font-weight:700; border:1px solid transparent; }
        .myactivity-row-chip.insert { background:#e8f8ee; border-color:#9cd8b1; color:#18794e; }
        .myactivity-row-chip.update { background:#eef4ff; border-color:#9eb8ee; color:#1d4f91; }
        .myactivity-row-chip.delete { background:#fdecec; border-color:#f0b6b6; color:#a52727; }
        .itm-user-config-sidebar-link { color:inherit; text-decoration:none; }
        .itm-user-config-sidebar-link:hover { text-decoration:underline; }
    </style>
</head>
<body>
<div class="container">
    <?php include '../../includes/sidebar.php'; ?>
    <div class="main-content">
        <?php include '../../includes/header.php'; ?>
        <div class="content">
            <h1 title="View activity details">🔎</h1>

            <div class="card">
                <?php if (!$logRow): ?>
                    <div class="alert alert-danger">Activity record not found.</div>
                <?php else: ?>
                    <table>
                        <tbody>
                        <tr><th style="width:220px;">Date &amp; Time</th><td><?php echo sanitize(myactivity_format_display_datetime($logRow['created_at'] ?? '')); ?></td></tr>
                        <tr><th>Action</th><td><span class="myactivity-row-chip <?php echo sanitize(myactivity_action_chip_class($logRow['action'] ?? '')); ?>"><?php echo sanitize((string)($logRow['action'] ?? '')); ?></span></td></tr>
                        <tr><th>Module</th><td>
                            <?php if ($moduleHref !== ''): ?>
                                <a class="itm-user-config-sidebar-link" href="<?php echo sanitize('../../' . ltrim($moduleHref, '/')); ?>" target="_blank" rel="noopener noreferrer"><?php echo sanitize($tableName); ?></a>
                            <?php else: ?>
                                <?php echo sanitize($tableName !== '' ? $tableName : '—'); ?>
                            <?php endif; ?>
                        </td></tr>
                        <?php if (trim((string)($logRow['module_name'] ?? '')) !== ''): ?>
                        <tr><th>Module name</th><td><?php echo sanitize((string)$logRow['module_name']); ?></td></tr>
                        <?php endif; ?>
                        <tr><th>Record ID</th><td><?php echo (int)($logRow['record_id'] ?? 0); ?></td></tr>
                        <?php if (trim((string)($logRow['entity_name'] ?? '')) !== ''): ?>
                        <tr><th>Entity</th><td><?php echo sanitize((string)$logRow['entity_name']); ?></td></tr>
                        <?php endif; ?>
                        <tr><th>IP address</th><td><?php echo sanitize($ipDisplayValue); ?></td></tr>
                        <tr><th>User agent</th><td><?php echo sanitize((string)($logRow['user_agent'] ?? '—')); ?></td></tr>
                        <tr><th>Old values</th><td><pre class="myactivity-json"><?php echo sanitize($oldValuesDisplay); ?></pre></td></tr>
                        <tr><th>New values</th><td><pre class="myactivity-json"><?php echo sanitize($newValuesDisplay); ?></pre></td></tr>
                        </tbody>
                    </table>
                <?php endif; ?>

                <div style="display:flex;gap:10px;margin-top:20px;">
                    <a href="index.php" class="btn" title="Back">🔙</a>
                </div>
            </div>
        </div>
    </div>
</div>
<script src="../../js/theme.js"></script>
</body>
</html>
