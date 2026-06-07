<?php
require '../../config/config.php';

$company_id = (int)($_SESSION['company_id'] ?? 0);
$user_id = (int)($_SESSION['user_id'] ?? 0);
$is_admin = (($_SESSION['role_name'] ?? '') === 'admin');

if ($company_id <= 0) {
    header('Location: ../../index.php');
    return;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!itm_verify_csrf_token($_POST['csrf_token'] ?? '')) {
        die('CSRF token validation failed.');
    }

    $id = (int)($_POST['id'] ?? 0);
    if ($id > 0) {
        $check_res = mysqli_query($conn, "SELECT user_id FROM bookmarks WHERE id = $id AND company_id = $company_id");
        $data = mysqli_fetch_assoc($check_res);

        if ($data && ($is_admin || (int)$data['user_id'] === $user_id)) {
            $stmt = mysqli_prepare($conn, "UPDATE bookmarks SET active = 0 WHERE id = ?");
            mysqli_stmt_bind_param($stmt, 'i', $id);
            mysqli_stmt_execute($stmt);
        }
    }
}

header('Location: index.php');
