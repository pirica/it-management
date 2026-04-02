<?php
require '../../config/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    exit('Method not allowed.');
}

itm_require_post_csrf();

$id = (int)($_POST['id'] ?? 0);
if ($id > 0) {
    $usageError = '';
    if (!itm_can_delete_record($conn, 'companies', 'id', $id, 0, $usageError)) {
        $_SESSION['crud_error'] = $usageError;
    } else {
        $old = itm_fetch_audit_record($conn, 'companies', $id, (int)($_SESSION['company_id'] ?? 0));
        $stmt = mysqli_prepare($conn, 'DELETE FROM companies WHERE id = ? LIMIT 1');
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, 'i', $id);
            if (!mysqli_stmt_execute($stmt)) {
                $_SESSION['crud_error'] = itm_format_db_constraint_error(mysqli_errno($conn), mysqli_error($conn));
            } else {
                itm_log_audit($conn, 'companies', $id, 'DELETE', $old, null);
            }
            mysqli_stmt_close($stmt);
        } else {
            $_SESSION['crud_error'] = 'Failed to delete company.';
        }
    }
}

header('Location: index.php');
exit;
