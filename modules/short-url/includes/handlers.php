<?php
/**
 * Short URLs — POST handlers.
 */

if (!isset($conn) || !($conn instanceof mysqli)) {
    return;
}

require_once ROOT_PATH . 'includes/itm_short_url.php';

$suEmployeeId = (int) ($_SESSION['employee_id'] ?? 0);
$suCompanyId = (int) ($company_id ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_short_url_settings'])) {
    itm_require_post_csrf();
    if (!function_exists('itm_is_admin') || !itm_is_admin($conn, $suEmployeeId)) {
        $_SESSION['su_flash_error'] = 'Only administrators can save short URL settings.';
        header('Location: index.php?tab=configuration');
        exit;
    }
    $baseParse = itm_short_url_parse_public_base_url_input($_POST);
    if (empty($baseParse['ok'])) {
        $_SESSION['su_flash_error'] = $baseParse['error'] !== '' ? $baseParse['error'] : 'Invalid public base URL.';
        header('Location: index.php?tab=configuration');
        exit;
    }
    if (itm_short_url_save_settings($conn, $suCompanyId, $suEmployeeId, $_POST)) {
        $_SESSION['su_flash_success'] = 'Short URL settings saved.';
    } else {
        $_SESSION['su_flash_error'] = 'Could not save settings.';
    }
    header('Location: index.php?tab=configuration');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['short_url_action']) && $_POST['short_url_action'] === 'save') {
    itm_require_post_csrf();
    $id = (int) ($_POST['id'] ?? 0);
    $input = [
        'destination_url' => $_POST['destination_url'] ?? '',
        'title' => $_POST['title'] ?? '',
        'short_code' => $_POST['short_code'] ?? '',
        'expires_at' => $_POST['expires_at'] ?? '',
        'password' => $_POST['link_password'] ?? '',
        'clear_password' => !empty($_POST['clear_password']),
    ];
    $validated = itm_short_url_validate_save($conn, $suCompanyId, $suEmployeeId, $input, $id);
    if (!empty($validated['errors'])) {
        $_SESSION['su_flash_error'] = implode(' ', $validated['errors']);
        $redirect = $id > 0 ? 'edit.php?id=' . $id : 'index.php';
        header('Location: ' . $redirect);
        exit;
    }

    $passwordHash = null;
    if ($id > 0) {
        $existing = itm_short_url_fetch_by_id($conn, $suCompanyId, $suEmployeeId, $id);
        if (!$existing) {
            $_SESSION['su_flash_error'] = 'Short link not found.';
            header('Location: index.php');
            exit;
        }
        $passwordHash = trim((string) ($existing['password_hash'] ?? ''));
        if ($passwordHash === '') {
            $passwordHash = null;
        }
        if (!empty($validated['clear_password'])) {
            $passwordHash = null;
        } elseif ((string) ($validated['password'] ?? '') !== '') {
            $passwordHash = password_hash((string) $validated['password'], PASSWORD_DEFAULT);
        }
        $accessToken = trim((string) ($existing['access_token'] ?? ''));
        if ($accessToken === '') {
            $accessToken = itm_short_url_generate_access_token();
        }
        $sql = 'UPDATE short_urls SET title = ?, destination_url = ?, short_code = ?, access_token = ?, password_hash = ?, expires_at = ?, updated_by = ?, updated_at = NOW() WHERE id = ? AND company_id = ? AND employee_id = ? AND deleted_at IS NULL';
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param(
            $stmt,
            'sssssssiii',
            $validated['title'],
            $validated['destination_url'],
            $validated['short_code'],
            $accessToken,
            $passwordHash,
            $validated['expires_at'],
            $suEmployeeId,
            $id,
            $suCompanyId,
            $suEmployeeId
        );
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        $row = itm_short_url_fetch_by_id($conn, $suCompanyId, $suEmployeeId, $id);
        if (!empty($_POST['generate_qr']) && $row && empty($row['qr_code_id'])) {
            itm_short_url_create_linked_qr($conn, $row);
        }
        $_SESSION['su_flash_success'] = 'Short link updated.';
        header('Location: view.php?id=' . $id);
        exit;
    }

    if ((string) ($validated['password'] ?? '') !== '') {
        $passwordHash = password_hash((string) $validated['password'], PASSWORD_DEFAULT);
    }
    $newId = itm_short_url_insert_row($conn, $suCompanyId, $suEmployeeId, $validated, $passwordHash);
    if ($newId <= 0) {
        $_SESSION['su_flash_error'] = 'Could not create short link.';
        header('Location: index.php');
        exit;
    }
    $row = itm_short_url_fetch_by_id($conn, $suCompanyId, $suEmployeeId, $newId);
    if (!empty($_POST['generate_qr']) && $row) {
        itm_short_url_create_linked_qr($conn, $row);
    }
    $_SESSION['su_flash_success'] = 'Short link created.';
    header('Location: view.php?id=' . $newId);
    exit;
}

if ($crud_action === 'delete' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    itm_require_post_csrf();
    $id = (int) ($_POST['id'] ?? ($_GET['id'] ?? 0));
    if ($id > 0) {
        if (function_exists('itm_crud_build_soft_delete_sql')) {
            $sql = itm_crud_build_soft_delete_sql('short_urls', 'WHERE id = ? AND company_id = ? AND employee_id = ?', $suEmployeeId);
            $stmt = mysqli_prepare($conn, $sql);
            mysqli_stmt_bind_param($stmt, 'iii', $id, $suCompanyId, $suEmployeeId);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
        } else {
            $sql = 'UPDATE short_urls SET active = 0, deleted_by = ?, deleted_at = NOW() WHERE id = ? AND company_id = ? AND employee_id = ? AND deleted_at IS NULL';
            $stmt = mysqli_prepare($conn, $sql);
            mysqli_stmt_bind_param($stmt, 'iiii', $suEmployeeId, $id, $suCompanyId, $suEmployeeId);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
        }
        $_SESSION['su_flash_success'] = 'Short link deleted.';
    }
    header('Location: index.php');
    exit;
}
