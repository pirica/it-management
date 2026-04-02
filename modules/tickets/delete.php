<?php
require '../../config/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method Not Allowed');
}

itm_require_post_csrf();

$id = (int)($_POST['id'] ?? 0);
if ($id > 0) {
    $usageError = '';
    if (!itm_can_delete_record($conn, 'tickets', 'id', $id, $company_id, $usageError)) {
        $_SESSION['crud_error'] = $usageError;
    } else {
        $stmt = mysqli_prepare($conn, 'DELETE FROM tickets WHERE id = ? AND company_id = ?');
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, 'ii', $id, $company_id);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
        }
    }
}

header('Location: index.php');
exit;
