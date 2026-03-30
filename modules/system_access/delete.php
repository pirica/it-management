<?php
require '../../config/config.php';
require '../../includes/employee_system_access.php';

esa_ensure_table($conn);

$id = (int)($_GET['id'] ?? 0);
if ($id > 0) {
    $usageError = '';
    if (!itm_can_delete_record($conn, 'system_access', 'id', $id, $company_id, $usageError)) {
        $_SESSION['crud_error'] = $usageError;
    } else {
        mysqli_query($conn, "DELETE FROM system_access WHERE id=$id AND company_id=$company_id");
    }
}

header('Location: index.php');
exit;
