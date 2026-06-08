<?php
require '../../config/config.php';

$company_id = (int)($_SESSION['company_id'] ?? 0);
$user_id = (int)($_SESSION['user_id'] ?? 0);
$is_admin = (strtolower($_SESSION['role_name'] ?? '') === 'admin');

if ($company_id <= 0) {
    header('Location: ../../index.php');
    return;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!itm_validate_csrf_token($_POST['csrf_token'] ?? '')) {
        die('CSRF token validation failed.');
    }

    $id = (int)($_POST['id'] ?? 0);
    if ($id > 0) {
        $check_res = mysqli_query($conn, "SELECT user_id FROM bookmark_folders WHERE id = $id AND company_id = $company_id");
        $data = mysqli_fetch_assoc($check_res);

        if ($data && ($is_admin || (int)$data['user_id'] === $user_id)) {
            // Update bookmarks in this folder to be root
            itm_run_query($conn, "UPDATE bookmarks SET folder_id = NULL WHERE folder_id = $id");
            // Update subfolders to be root
            itm_run_query($conn, "UPDATE bookmark_folders SET parent_folder_id = NULL WHERE parent_folder_id = $id");
            // Soft delete the folder
            itm_run_query($conn, "UPDATE bookmark_folders SET active = 0 WHERE id = $id");
        }
    }
}

header('Location: index.php');
