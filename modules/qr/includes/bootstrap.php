<?php
/**
 * QR Generator — bootstrap data for list / wizard / view.
 */

require_once ROOT_PATH . 'includes/itm_qr_generator.php';

$qrCatalog = itm_qr_generator_type_catalog();
$qrEmployeeId = (int) ($_SESSION['employee_id'] ?? 0);
$qrCompanyId = (int) ($company_id ?? 0);
$qrFlashSuccess = $_SESSION['qr_flash_success'] ?? '';
$qrFlashError = $_SESSION['qr_flash_error'] ?? '';
unset($_SESSION['qr_flash_success'], $_SESSION['qr_flash_error']);

$qrRow = null;
$qrPayload = [];
$qrDesign = itm_qr_generator_default_design();
$qrDesignTemplates = [];
$qrScans = [];
$qrId = (int) ($_GET['id'] ?? 0);
$qrSelectedType = trim((string) ($_GET['type'] ?? ''));
$qrStep = max(1, min(3, (int) ($_GET['step'] ?? 1)));

if ($crud_action === 'create' && isset($_GET['type']) && itm_qr_generator_is_valid_type_slug($_GET['type'])) {
    $qrSelectedType = trim((string) $_GET['type']);
    $qrStep = max(2, (int) ($_GET['step'] ?? 2));
}

if ($crud_action === 'view' || $crud_action === 'edit') {
    if ($qrId <= 0) {
        header('Location: index.php');
        exit;
    }
    $qrRow = itm_qr_generator_fetch_by_id($conn, $qrCompanyId, $qrEmployeeId, $qrId);
    if (!$qrRow) {
        $_SESSION['qr_flash_error'] = 'QR code not found.';
        header('Location: index.php');
        exit;
    }
    $qrPayload = itm_qr_generator_decode_json_field($qrRow['payload_json'] ?? '');
    $qrDesign = itm_qr_generator_normalize_design(itm_qr_generator_decode_json_field($qrRow['design_json'] ?? ''));
    $qrSelectedType = (string) ($qrRow['type_slug'] ?? '');
    if ($crud_action === 'view') {
        $scanStmt = mysqli_prepare($conn, 'SELECT scanned_at, ip_hash, user_agent FROM qr_code_scans WHERE qr_code_id = ? AND company_id = ? ORDER BY scanned_at DESC LIMIT 50');
        if ($scanStmt) {
            mysqli_stmt_bind_param($scanStmt, 'ii', $qrId, $qrCompanyId);
            mysqli_stmt_execute($scanStmt);
            $scanRes = mysqli_stmt_get_result($scanStmt);
            while ($scanRes && ($s = mysqli_fetch_assoc($scanRes))) {
                $qrScans[] = $s;
            }
            mysqli_stmt_close($scanStmt);
        }
    }
}

if ($conn && $qrCompanyId > 0 && $qrEmployeeId > 0) {
    $qrDesignTemplates = itm_qr_generator_list_design_templates($conn, $qrCompanyId, $qrEmployeeId);
}

$qrListRows = [];
$search = trim((string) ($_GET['search'] ?? ''));
$qrSearch = $search;
$page = max(1, (int) ($_GET['page'] ?? 1));
$qrPage = $page;
$perPage = function_exists('itm_resolve_records_per_page') ? itm_resolve_records_per_page($ui_config ?? null) : 25;
$qrPerPage = $perPage;
$qrTotalRows = 0;
$qrTotalPages = 1;

if ($crud_action === 'index' || $crud_action === 'list_all') {
  $where = 'company_id = ? AND employee_id = ? AND deleted_at IS NULL';
    $params = [$qrCompanyId, $qrEmployeeId];
    $types = 'ii';
    if ($search !== '') {
        $where .= ' AND (title LIKE ? OR type_slug LIKE ?)';
        $like = '%' . $search . '%';
        $params[] = $like;
        $params[] = $like;
        $types .= 'ss';
    }
    $countSql = 'SELECT COUNT(*) AS c FROM qr_codes WHERE ' . $where;
    $countStmt = mysqli_prepare($conn, $countSql);
    if ($countStmt) {
        mysqli_stmt_bind_param($countStmt, $types, ...$params);
        mysqli_stmt_execute($countStmt);
        $countRes = mysqli_stmt_get_result($countStmt);
        $qrTotalRows = (int) (($countRes ? mysqli_fetch_assoc($countRes) : [])['c'] ?? 0);
        mysqli_stmt_close($countStmt);
    }
    if ($crud_action === 'list_all') {
        $qrPerPage = max($qrTotalRows, 1);
        $qrPage = 1;
    }
    $qrTotalPages = max(1, (int) ceil($qrTotalRows / $qrPerPage));
    $totalRows = $qrTotalRows;
    $totalPages = $qrTotalPages;
    if ($qrPage > $qrTotalPages) {
        $qrPage = $qrTotalPages;
    }
    $offset = ($qrPage - 1) * $qrPerPage;
    $listSql = 'SELECT id, title, type_slug, encoding_mode, scan_count, created_at FROM qr_codes WHERE ' . $where . ' ORDER BY created_at DESC LIMIT ? OFFSET ?';
    $listStmt = mysqli_prepare($conn, $listSql);
    if ($listStmt) {
        $listTypes = $types . 'ii';
        $listParams = array_merge($params, [$qrPerPage, $offset]);
        mysqli_stmt_bind_param($listStmt, $listTypes, ...$listParams);
        mysqli_stmt_execute($listStmt);
        $listRes = mysqli_stmt_get_result($listStmt);
        while ($listRes && ($r = mysqli_fetch_assoc($listRes))) {
            $qrListRows[] = $r;
        }
        mysqli_stmt_close($listStmt);
    }
}

$qrQrText = '';
if ($qrRow) {
    $qrQrText = itm_qr_generator_resolve_qr_text($qrRow);
} elseif ($qrSelectedType !== '' && isset($qrCatalog[$qrSelectedType])) {
    $previewPayload = itm_qr_generator_normalize_payload($qrSelectedType, []);
    $previewMode = 'dynamic';
    if (itm_qr_generator_type_requires_dynamic($qrSelectedType)) {
        $previewMode = 'dynamic';
    }
    if (!empty($qrCatalog[$qrSelectedType]['static_only'])) {
        $previewMode = 'static';
    }
    if ($previewMode === 'static') {
        $qrQrText = itm_qr_generator_build_static_payload($qrSelectedType, $previewPayload);
    }
}
