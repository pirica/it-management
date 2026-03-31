<?php
require '../../config/config.php';

$csrfToken = (string)($_POST['csrf_token'] ?? ($_GET['csrf_token'] ?? ''));
if (!itm_validate_csrf_token($csrfToken)) {
    http_response_code(403);
    exit('Invalid CSRF token.');
}

$id = (int)($_POST['id'] ?? ($_GET['id'] ?? 0));
if ($id > 0) {
    $usageError = '';
    if (!itm_can_delete_record($conn, 'inventory_items', 'id', $id, $company_id, $usageError)) {
        $_SESSION['crud_error'] = $usageError;
    } else {
        mysqli_query($conn, "DELETE FROM inventory_items WHERE id=$id AND company_id=$company_id");
    }
}

header('Location: index.php');
exit;
