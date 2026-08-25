<?php
/**
 * Short URLs — bootstrap data.
 */

require_once ROOT_PATH . 'includes/itm_short_url.php';

$suEmployeeId = (int) ($_SESSION['employee_id'] ?? 0);
$suCompanyId = (int) ($company_id ?? 0);
$suSettings = itm_short_url_load_settings($conn, $suCompanyId);
$suFlashSuccess = $_SESSION['su_flash_success'] ?? '';
$suFlashError = $_SESSION['su_flash_error'] ?? '';
unset($_SESSION['su_flash_success'], $_SESSION['su_flash_error']);

$suActiveTab = $_GET['tab'] ?? 'links';
$suAllowedTabs = ['links', 'configuration'];
if (!in_array($suActiveTab, $suAllowedTabs, true)) {
    $suActiveTab = 'links';
}

$suRow = null;
$suClicks = [];
$suId = (int) ($_GET['id'] ?? 0);
$suCanEditSettings = function_exists('itm_is_admin') && itm_is_admin();

if ($crud_action === 'view' || $crud_action === 'edit') {
    if ($suId <= 0) {
        header('Location: index.php');
        exit;
    }
    $suRow = itm_short_url_fetch_by_id($conn, $suCompanyId, $suEmployeeId, $suId);
    if (!$suRow) {
        $_SESSION['su_flash_error'] = 'Short link not found.';
        header('Location: index.php');
        exit;
    }
    if ($crud_action === 'view') {
        $suClicks = itm_short_url_fetch_clicks($conn, $suId, $suCompanyId, 50);
    }
}

$suListRows = [];
$search = trim((string) ($_GET['search'] ?? ''));
$suSearch = $search;
$page = max(1, (int) ($_GET['page'] ?? 1));
$suPage = $page;
$perPage = function_exists('itm_resolve_records_per_page') ? itm_resolve_records_per_page($ui_config ?? null) : 25;
$suPerPage = $perPage;
$suTotalRows = 0;
$suTotalPages = 1;

if ($crud_action === 'index' || $crud_action === 'list_all') {
    $where = 'company_id = ? AND employee_id = ? AND deleted_at IS NULL';
    $params = [$suCompanyId, $suEmployeeId];
    $types = 'ii';
    if ($search !== '') {
        $where .= ' AND (title LIKE ? OR destination_url LIKE ? OR short_code LIKE ?)';
        $like = '%' . $search . '%';
        $params[] = $like;
        $params[] = $like;
        $params[] = $like;
        $types .= 'sss';
    }
    $countSql = 'SELECT COUNT(*) AS c FROM short_urls WHERE ' . $where;
    $countStmt = mysqli_prepare($conn, $countSql);
    if ($countStmt) {
        mysqli_stmt_bind_param($countStmt, $types, ...$params);
        mysqli_stmt_execute($countStmt);
        $countRes = mysqli_stmt_get_result($countStmt);
        $suTotalRows = (int) (($countRes ? mysqli_fetch_assoc($countRes) : [])['c'] ?? 0);
        mysqli_stmt_close($countStmt);
    }
    if ($crud_action === 'list_all') {
        $suPerPage = max($suTotalRows, 1);
        $suPage = 1;
    }
    $suTotalPages = max(1, (int) ceil($suTotalRows / $suPerPage));
    if ($suPage > $suTotalPages) {
        $suPage = $suTotalPages;
    }
    $offset = ($suPage - 1) * $suPerPage;
    $listSql = 'SELECT id, title, destination_url, short_code, click_count, expires_at, qr_code_id, created_at FROM short_urls WHERE ' . $where . ' ORDER BY created_at DESC LIMIT ? OFFSET ?';
    $listStmt = mysqli_prepare($conn, $listSql);
    if ($listStmt) {
        $listTypes = $types . 'ii';
        $listParams = array_merge($params, [$suPerPage, $offset]);
        mysqli_stmt_bind_param($listStmt, $listTypes, ...$listParams);
        mysqli_stmt_execute($listStmt);
        $listRes = mysqli_stmt_get_result($listStmt);
        while ($listRes && ($r = mysqli_fetch_assoc($listRes))) {
            $suListRows[] = $r;
        }
        mysqli_stmt_close($listStmt);
    }
}

$suPublicBase = rtrim((string) BASE_URL, '/') . '/modules/short-url/go.php?c=';
