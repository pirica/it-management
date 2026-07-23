<?php
require '../../config/config.php';

$company_id = (int)($_SESSION['company_id'] ?? 0);
$user_id = (int)($_SESSION['employee_id'] ?? 0);
$is_admin = itm_is_admin($conn, (int)($_SESSION['employee_id'] ?? 0));

if ($company_id <= 0) {
    header('Location: ../../index.php');
    return;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!itm_validate_csrf_token($_POST['csrf_token'] ?? '')) {
        die('CSRF token validation failed.');
    }

    $id = (int)($_POST['id'] ?? 0);
    $delete_contents = (int)($_POST['delete_contents'] ?? 0);

    if ($id > 0) {
        $check_res = mysqli_query($conn, "SELECT employee_id FROM bookmark_folders WHERE id = $id AND company_id = $company_id");
        $data = mysqli_fetch_assoc($check_res);

        if ($data && ($is_admin || (int)$data['employee_id'] === $user_id)) {
            if ($delete_contents) {
                $folders_to_delete = [$id];
                $to_process = [$id];

                while (!empty($to_process)) {
                    $pid = array_pop($to_process);
                    $sub_res = mysqli_query($conn, "SELECT id FROM bookmark_folders WHERE parent_folder_id = $pid AND company_id = $company_id AND active = 1");
                    while ($sub = mysqli_fetch_assoc($sub_res)) {
                        $folders_to_delete[] = (int)$sub['id'];
                        $to_process[] = (int)$sub['id'];
                    }
                }

                $folder_ids_str = implode(',', array_map('intval', $folders_to_delete));
                itm_run_query($conn, "DELETE FROM bookmarks WHERE folder_id IN ($folder_ids_str) AND company_id = $company_id");
                itm_run_query($conn, "DELETE FROM bookmark_folders WHERE id IN ($folder_ids_str) AND company_id = $company_id");
            } else {
                itm_run_query($conn, "UPDATE bookmarks SET folder_id = NULL WHERE folder_id = $id AND company_id = $company_id");
                itm_run_query($conn, "UPDATE bookmark_folders SET parent_folder_id = NULL WHERE parent_folder_id = $id AND company_id = $company_id");
                itm_run_query($conn, "DELETE FROM bookmark_folders WHERE id = $id AND company_id = $company_id");
            }
        }
    }
}

header('Location: index.php');
