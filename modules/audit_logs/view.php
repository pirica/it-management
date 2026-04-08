<?php
/**
 * Audit Logs Module - View
 *
 * Shows a full-detail view of a single audit event so operators can inspect
 * all captured metadata without truncation.
 */

require '../../config/config.php';

$companyId = (int)($_SESSION['company_id'] ?? 0);
if ($companyId <= 0) {
    http_response_code(403);
    exit('Company context is required.');
}

// Respect company-level UI policy so disabled audit logs remain hidden everywhere.
if ((int)($ui_config['enable_audit_logs'] ?? 1) !== 1) {
    http_response_code(403);
    exit('Audit logs are disabled in Settings.');
}

$auditId = (int)($_GET['id'] ?? 0);
$logRow = null;

if ($auditId > 0) {
    $stmt = mysqli_prepare(
        $conn,
        'SELECT al.*, u.username, u.email, u.first_name, u.last_name '
        . 'FROM audit_logs al '
        . 'LEFT JOIN users u ON u.id = al.user_id '
        . 'WHERE al.id = ? AND al.company_id = ? '
        . 'LIMIT 1'
    );

    if ($stmt) {
        mysqli_stmt_bind_param($stmt, 'ii', $auditId, $companyId);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        if ($result && mysqli_num_rows($result) === 1) {
            $logRow = mysqli_fetch_assoc($result);
        }
        mysqli_stmt_close($stmt);
    }
}

$userName = 'System';
$userEmail = '';
if ($logRow) {
    $userName = trim((string)(($logRow['first_name'] ?? '') . ' ' . ($logRow['last_name'] ?? '')));
    $userEmail = trim((string)($logRow['actor_email'] ?? $logRow['email'] ?? ''));

    if ($userName === '') {
        $userName = trim((string)($logRow['actor_username'] ?? ''));
    }
    if ($userName === '' && $userEmail !== '') {
        $userName = $userEmail;
    }
    if ($userName === '') {
        $userName = trim((string)($logRow['username'] ?? ''));
    }
    if ($userName === '') {
        $userName = $logRow['user_id'] ? ('User #' . (int)$logRow['user_id']) : 'System';
    }
}

/**
 * Normalize audit payload text so detail view mirrors index semantics.
 */
function itm_audit_normalize_value($text) {
    $text = trim((string)$text);
    if ($text === '' || strcasecmp($text, 'null') === 0) {
        return '—';
    }

    return $text;
}

/**
 * Provide action-aware empty-state messaging for old/new payload sections.
 */
function itm_audit_describe_payload($action, $normalizedValue, $isOldValue) {
    if ($normalizedValue !== '—') {
        return $normalizedValue;
    }

    $action = strtoupper(trim((string)$action));
    if ($isOldValue && $action === 'INSERT') {
        return '— Not applicable for INSERT events.';
    }
    if (!$isOldValue && $action === 'DELETE') {
        return '— Not applicable for DELETE events.';
    }

    return '—';
}

$oldValuesDisplay = '—';
$newValuesDisplay = '—';
if ($logRow) {
    $actionValue = (string)($logRow['action'] ?? '');
    $oldValuesDisplay = itm_audit_describe_payload($actionValue, itm_audit_normalize_value($logRow['old_values'] ?? ''), true);
    $newValuesDisplay = itm_audit_describe_payload($actionValue, itm_audit_normalize_value($logRow['new_values'] ?? ''), false);
}

$moduleListHeading = '🧾 View Audit Log';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Audit Log</title>
    <link rel="stylesheet" href="../../css/styles.css">
    <style>
        .audit-json { white-space:pre-wrap; word-break:break-word; margin:0; font-size:12px; line-height:1.4; background:var(--input-bg); border:1px solid var(--border); border-radius:8px; padding:10px; }
    </style>
</head>
<body>
<div class="container">
    <?php include '../../includes/sidebar.php'; ?>
    <div class="main-content">
        <?php include '../../includes/header.php'; ?>

        <div class="content">
            <h1><?php echo sanitize($moduleListHeading); ?></h1>
            <div class="card">
                <?php if (!$logRow): ?>
                    <div class="alert alert-danger">Audit log record not found.</div>
                <?php else: ?>
                    <table>
                        <tbody>
                        <tr><th style="width:220px;">ID</th><td><?php echo (int)$logRow['id']; ?></td></tr>
                        <tr><th>Date &amp; Time</th><td><?php echo sanitize((string)$logRow['changed_at']); ?></td></tr>
                        <tr><th>Action</th><td><?php echo sanitize((string)$logRow['action']); ?></td></tr>
                        <tr><th>Module Name</th><td><?php echo sanitize((string)($logRow['module_name'] ?? '—')); ?></td></tr>
                        <tr><th>Table Name</th><td><?php echo sanitize((string)($logRow['table_name'] ?? '—')); ?></td></tr>
                        <tr><th>Record ID</th><td><?php echo (int)($logRow['record_id'] ?? 0); ?></td></tr>
                        <tr>
                            <th>User</th>
                            <td>
                                <?php echo sanitize($userName); ?>
                                <?php if ($userEmail !== ''): ?>
                                    <br><small><?php echo sanitize($userEmail); ?></small>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <tr><th>IP Address</th><td><?php echo sanitize((string)($logRow['ip_address'] ?? '—')); ?></td></tr>
                        <tr><th>User Agent</th><td><?php echo sanitize((string)($logRow['user_agent'] ?? '—')); ?></td></tr>
                        <tr>
                            <th>Old Values</th>
                            <td><pre class="audit-json"><?php echo sanitize($oldValuesDisplay); ?></pre></td>
                        </tr>
                        <tr>
                            <th>New Values</th>
                            <td><pre class="audit-json"><?php echo sanitize($newValuesDisplay); ?></pre></td>
                        </tr>
                        </tbody>
                    </table>
                <?php endif; ?>

                <div style="display:flex;gap:10px;margin-top:20px;">
                    <a href="index.php" class="btn">🔙</a>
                </div>
            </div>
        </div>
    </div>
</div>
<script src="../../js/theme.js"></script>
</body>
</html>
