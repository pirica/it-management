<?php
/**
 * Schema Migrations — delete one audit history row (Admin only).
 *
 * Does not change live schema or remove files from db/migrations/.
 * Use scripts/migrate.php to delete migration files from disk.
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

$rowId = (int)($_POST['id'] ?? 0);
$redirect = trim((string)($_POST['redirect'] ?? 'index.php'));
if ($redirect !== 'view.php') {
    $redirect = 'index.php';
}

$returnQuery = trim((string)($_POST['return_query'] ?? ''));
$redirectTarget = $redirect;
if ($redirect === 'index.php' && $returnQuery !== '') {
    $redirectTarget .= '?' . ltrim($returnQuery, '?');
} elseif ($redirect === 'view.php' && $rowId > 0) {
    $redirectTarget = 'view.php?id=' . $rowId;
}

[$deleted, $message] = itm_database_migrations_delete_audit_row_by_id($conn, $rowId);
if ($deleted) {
    if ($redirect === 'view.php') {
        $redirectTarget = 'index.php';
    }
    header('Location: ' . $redirectTarget . (strpos($redirectTarget, '?') === false ? '?' : '&') . 'msg=' . rawurlencode($message));
    exit;
}

header('Location: index.php?msg=' . rawurlencode($message));
exit;
