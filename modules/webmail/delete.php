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

$bulkAction = trim((string)($_POST['bulk_action'] ?? ''));
if ($bulkAction === 'bulk_delete' || $bulkAction === 'clear_table') {
    itm_crud_enforce_mutation_access($conn, 'delete', 'webmail');
    $listFilters = [
        'status' => trim((string)($_POST['status'] ?? '')),
        'starred' => trim((string)($_POST['starred'] ?? '')),
        'archived' => trim((string)($_POST['archived'] ?? '')),
        'date_from' => trim((string)($_POST['date_from'] ?? '')),
        'date_to' => trim((string)($_POST['date_to'] ?? '')),
        'search' => trim((string)($_POST['search'] ?? '')),
        'sort' => trim((string)($_POST['sort'] ?? 'sent_at')),
        'dir' => trim((string)($_POST['dir'] ?? 'DESC')),
    ];
    $targetIds = [];
    if ($bulkAction === 'bulk_delete') {
        foreach ((array)($_POST['ids'] ?? []) as $rawId) {
            $targetIds[] = (int)$rawId;
        }
        $targetIds = array_values(array_unique(array_filter($targetIds, static function (int $i): bool {
            return $i > 0;
        })));
    } else {
        $targetIds = webmail_fetch_all_list_ids($conn, $folder, $company_id, $employee_id, $sessionEmail, $listFilters);
    }

    $changed = 0;
    foreach ($targetIds as $bulkId) {
        if ($folder === 'trash') {
            if (webmail_hard_delete($conn, $bulkId, $company_id, $employee_id, $sessionEmail)) {
                $changed++;
            }
        } elseif (webmail_soft_delete($conn, $bulkId, $company_id, $employee_id, $sessionEmail)) {
            $changed++;
        }
    }

    if ($changed > 0) {
        if ($folder === 'trash') {
            $_SESSION['webmail_notice'] = $changed . ' message(s) permanently deleted.';
        } else {
            $_SESSION['webmail_notice'] = $changed . ' message(s) moved to Trash.';
            $redirectParams['folder'] = 'trash';
            unset($redirectParams['page']);
        }
    }

    header('Location: index.php?' . http_build_query($redirectParams));
    exit;
}

if ($id > 0 && $action === 'soft_delete') {
    itm_crud_enforce_mutation_access($conn, 'delete', 'webmail');
    if (webmail_soft_delete($conn, $id, $company_id, $employee_id, $sessionEmail)) {
        $_SESSION['webmail_notice'] = 'Message moved to Trash.';
    }
    $redirectParams['folder'] = 'trash';
    unset($redirectParams['page']);
    $redirect = 'index.php?' . http_build_query($redirectParams);
} elseif ($id > 0 && $action === 'restore') {
    webmail_restore($conn, $id, $company_id, $employee_id);
} elseif ($id > 0 && $action === 'hard_delete') {
    itm_crud_enforce_mutation_access($conn, 'delete', 'webmail');
    webmail_hard_delete($conn, $id, $company_id, $employee_id, $sessionEmail);
} elseif ($id > 0 && $action === 'toggle_star') {
    webmail_toggle_star($conn, $id, $company_id, $employee_id, $sessionEmail);
} elseif ($id > 0 && $action === 'toggle_archive') {
    webmail_toggle_archive($conn, $id, $company_id, $employee_id, $sessionEmail);
} elseif ($id > 0 && $action === 'unarchive') {
    webmail_toggle_archive($conn, $id, $company_id, $employee_id, $sessionEmail, 0);
} elseif ($id > 0 && $action === 'mark_read') {
    webmail_mark_read($conn, $id, $company_id, $employee_id, $sessionEmail);
} elseif ($id > 0 && $action === 'mark_unread') {
    webmail_mark_unread($conn, $id, $company_id, $employee_id, $sessionEmail);
}

$returnTo = trim((string)($_POST['return_to'] ?? ''));
if ($returnTo === 'view' && $id > 0) {
    $redirect = 'view.php?id=' . $id . '&folder=' . rawurlencode($folder);
}

header('Location: ' . $redirect);
exit;
