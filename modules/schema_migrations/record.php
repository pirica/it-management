<?php
/**
 * Schema Migrations — record one satisfied migration in audit history (Admin only).
 *
 * Does not re-run SQL when the live schema probe already passes.
 */

require '../../config/config.php';
require_once ROOT_PATH . 'includes/itm_database_migrations.php';

$employeeId = (int)($_SESSION['employee_id'] ?? 0);
if (!itm_is_admin($conn, $employeeId)) {
    header('Location: ' . BASE_URL . 'dashboard.php');
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Location: index.php');
    exit;
}

itm_require_post_csrf();

$filename = basename((string)($_POST['filename'] ?? ''));
$redirect = trim((string)($_POST['redirect'] ?? 'index.php'));
if ($redirect !== 'view.php') {
    $redirect = 'index.php';
}

$returnQuery = trim((string)($_POST['return_query'] ?? ''));
$redirectTarget = $redirect;
if ($redirect === 'index.php' && $returnQuery !== '') {
    $redirectTarget .= '?' . ltrim($returnQuery, '?');
} elseif ($redirect === 'view.php' && $filename !== '') {
    $redirectTarget = 'view.php?filename=' . rawurlencode($filename);
}

[$recorded, $message] = itm_database_migrations_record_satisfied_file($conn, $filename);
if ($recorded && $redirect === 'view.php' && $filename !== '') {
    $appliedMap = itm_database_migrations_fetch_applied_map($conn);
    if (isset($appliedMap[$filename])) {
        $stmt = mysqli_prepare($conn, 'SELECT id FROM schema_migrations WHERE filename = ? LIMIT 1');
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, 's', $filename);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            $auditRow = $result ? mysqli_fetch_assoc($result) : null;
            mysqli_stmt_close($stmt);
            if ($auditRow) {
                $redirectTarget = 'view.php?id=' . (int)($auditRow['id'] ?? 0);
            }
        }
    }
}

header('Location: ' . $redirectTarget . (strpos($redirectTarget, '?') === false ? '?' : '&') . 'msg=' . rawurlencode($message));
exit;
