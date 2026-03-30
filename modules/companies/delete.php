<?php
require '../../config/config.php';

$id = (int)($_GET['id'] ?? 0);
if ($id > 0) {
    $usageError = '';
    if (!itm_can_delete_record($conn, 'companies', 'id', $id, 0, $usageError)) {
        $_SESSION['crud_error'] = $usageError;
    } else {
        mysqli_query($conn, "DELETE FROM companies WHERE id = $id");
    }
}

header('Location: index.php');
exit;
