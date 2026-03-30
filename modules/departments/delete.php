<?php
require '../../config/config.php';

$id = (int)($_GET['id'] ?? 0);
if ($id > 0) {
    $usageError = '';
    if (!itm_can_delete_record($conn, 'departments', 'id', $id, $company_id, $usageError)) {
        $_SESSION['crud_error'] = $usageError;
    } else {
        mysqli_query($conn, "DELETE FROM departments WHERE id=$id AND company_id=$company_id");
    }
}

header('Location: index.php');
exit;
