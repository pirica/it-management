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
    if (!itm_can_delete_record($conn, 'companies', 'id', $id, 0, $usageError)) {
        $_SESSION['crud_error'] = $usageError;
    } else {
        mysqli_query($conn, "DELETE FROM companies WHERE id = $id");
    }
}

header('Location: index.php');
exit;
