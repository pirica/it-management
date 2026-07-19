<?php
/**
 * Emails Module — soft-delete send log rows (bulk / clear).
 */

require_once dirname(__DIR__, 2) . '/config/config.php';

itm_require_post_csrf();

$company_id = (int)($_SESSION['company_id'] ?? 0);
$employee_id = (int)($_SESSION['employee_id'] ?? 0);
$bulkAction = (string)($_POST['bulk_action'] ?? '');
$redirectQuery = ['tab' => 'send_logs'];
foreach (['status', 'search', 'sort', 'dir', 'page'] as $param) {
    if (isset($_POST[$param]) && (string)$_POST[$param] !== '') {
        $redirectQuery[$param] = (string)$_POST[$param];
    }
}
$redirect = 'index.php?' . http_build_query($redirectQuery);

if ($company_id <= 0 || $employee_id <= 0) {
    header('Location: index.php?tab=send_logs');
    exit;
}

if ($bulkAction === 'bulk_delete') {
    $ids = array_values(array_unique(array_filter(array_map('intval', (array)($_POST['ids'] ?? [])), static function (int $id): bool {
        return $id > 0;
    })));
    if ($ids !== []) {
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $sql = 'UPDATE emails SET active = 0, deleted_by = ?, deleted_at = NOW() WHERE company_id = ? AND active = 1 AND id IN (' . $placeholders . ')';
        $stmt = mysqli_prepare($conn, $sql);
        if ($stmt) {
            $types = 'ii' . str_repeat('i', count($ids));
            $bind = array_merge([$employee_id, $company_id], $ids);
            mysqli_stmt_bind_param($stmt, $types, ...$bind);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
        }
    }
} elseif ($bulkAction === 'clear_table') {
    $stmt = mysqli_prepare(
        $conn,
        'UPDATE emails SET active = 0, deleted_by = ?, deleted_at = NOW() WHERE company_id = ? AND active = 1'
    );
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, 'ii', $employee_id, $company_id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
    }
}

header('Location: ' . $redirect);
exit;
