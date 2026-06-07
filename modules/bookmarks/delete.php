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

    $bulkAction = (string)($_POST['bulk_action'] ?? '');
    $ids = (array)($_POST['ids'] ?? []);
    if ($bulkAction === 'single_delete' && isset($_POST['id'])) {
        $ids = [(int)$_POST['id']];
    }

    if ($bulkAction === 'clear_table') {
        $stmt = mysqli_prepare($conn, "UPDATE bookmarks SET active = 0 WHERE company_id = ? AND (user_id = ? OR shared = 1)");
        mysqli_stmt_bind_param($stmt, 'ii', $company_id, $user_id);
        mysqli_stmt_execute($stmt);
    } elseif (!empty($ids)) {
        foreach ($ids as $id) {
            $id = (int)$id;
            // Permission check
            $check_res = mysqli_query($conn, "SELECT user_id FROM bookmarks WHERE id = $id AND company_id = $company_id");
            $data = mysqli_fetch_assoc($check_res);
            if ($data && ($is_admin || (int)$data['user_id'] === $user_id)) {
                mysqli_query($conn, "UPDATE bookmarks SET active = 0 WHERE id = $id");
            }
        }
    }
}

header('Location: index.php');
