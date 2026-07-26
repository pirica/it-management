<?php
/**
 * Webmail — POST actions (delete, restore, star, archive).
 */

require_once dirname(__DIR__, 2) . '/config/config.php';
require_once __DIR__ . '/includes/webmail_helpers.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Location: index.php');
    exit;
}

itm_require_post_csrf();

$company_id = (int)($_SESSION['company_id'] ?? 0);
$employee_id = (int)($_SESSION['employee_id'] ?? 0);
$sessionEmail = webmail_session_email();
$action = trim((string)($_POST['webmail_action'] ?? ''));
$id = (int)($_POST['id'] ?? 0);
$folder = webmail_resolve_folder((string)($_POST['folder'] ?? 'inbox'));

$redirectParams = ['folder' => $folder];
foreach (['status', 'starred', 'archived', 'search', 'sort', 'dir', 'page', 'date_from', 'date_to'] as $param) {
    if (isset($_POST[$param]) && (string)$_POST[$param] !== '') {
        $redirectParams[$param] = (string)$_POST[$param];
    }
}
$redirect = 'index.php?' . http_build_query($redirectParams);
if ($id > 0 && $action === 'soft_delete') {
    if (webmail_soft_delete($conn, $id, $company_id, $employee_id, $sessionEmail)) {
        $_SESSION['webmail_notice'] = 'Message moved to Trash.';
    }
    $redirectParams['folder'] = 'trash';
    unset($redirectParams['page']);
    $redirect = 'index.php?' . http_build_query($redirectParams);
} elseif ($id > 0 && $action === 'restore') {
    webmail_restore($conn, $id, $company_id, $employee_id);
} elseif ($id > 0 && $action === 'hard_delete') {
    webmail_hard_delete($conn, $id, $company_id, $employee_id, $sessionEmail);
} elseif ($id > 0 && $action === 'toggle_star') {
    webmail_toggle_star($conn, $id, $company_id, $employee_id, $sessionEmail);
} elseif ($id > 0 && $action === 'toggle_archive') {
    webmail_toggle_archive($conn, $id, $company_id, $employee_id, $sessionEmail);
} elseif ($id > 0 && $action === 'unarchive') {
    webmail_toggle_archive($conn, $id, $company_id, $employee_id, $sessionEmail, 0);
}

header('Location: ' . $redirect);
exit;
