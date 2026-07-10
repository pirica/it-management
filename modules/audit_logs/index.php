<?php
/**
 * Audit Logs Module - Index
 *
 * Read-only audit centre: lists system-generated change history for the active company.
 * Records are created by database triggers. Admins may back up, download, or clear all logs.
 */

require '../../config/config.php';
if (!itm_is_admin($conn, $_SESSION['employee_id'] ?? 0)) {
    header('Location: ' . BASE_URL . 'dashboard.php');
    exit;
}

/**
 * Audit Logs - Search and List
 * 
 * Displays a historical record of all database changes (INSERT, UPDATE, DELETE)
 * that occurred within the current company's context. 
 * It allows IT staff to track who changed what and when.
 */

// Ensure the user has a valid company context
$companyId = (int)($_SESSION['company_id'] ?? 0);
if ($companyId <= 0) {
    http_response_code(403);
    exit('Company context is required.');
}

// Respect company-level UI policy so disabled audit logs remain hidden everywhere.
if ((int)($ui_config['enable_audit_logs'] ?? 1) !== 1) {
    http_response_code(403);
    exit('Audit logs are disabled in Settings.');
}

$messages = [];
$errors = [];
$csrfToken = itm_get_csrf_token();

if (!empty($_SESSION['audit_logs_flash_success']) && is_array($_SESSION['audit_logs_flash_success'])) {
    $messages = array_merge($messages, array_map('strval', $_SESSION['audit_logs_flash_success']));
    unset($_SESSION['audit_logs_flash_success']);
}
if (!empty($_SESSION['audit_logs_flash_error']) && is_array($_SESSION['audit_logs_flash_error'])) {
    $errors = array_merge($errors, array_map('strval', $_SESSION['audit_logs_flash_error']));
    unset($_SESSION['audit_logs_flash_error']);
}

// Keep compatibility with modules that pass one-off alerts through the URL.
$alertMessage = trim((string)($_GET['alert'] ?? ''));
if ($alertMessage !== '') {
    $messages[] = $alertMessage;
}

/**
 * Build query string for audit log list filters, sort, and pagination.
 */
function itm_audit_logs_build_query(array $params): string
{
    $normalized = [];
    foreach ($params as $key => $value) {
        if ($value === null || $value === '') {
            continue;
        }
        $normalized[$key] = $value;
    }

    return http_build_query($normalized);
}

/**
 * Why: Audit log volume can be huge; this module caps page size at 1000 even when shared UI config holds ALL or a larger numeric value from another module.
 */
function itm_audit_logs_resolve_records_per_page($uiConfig)
{
    $maxPerPage = 1000;
    $raw = strtolower((string)($uiConfig['records_per_page'] ?? '25'));
    if ($raw === 'all') {
        return $maxPerPage;
    }

    return min(itm_resolve_records_per_page($uiConfig), $maxPerPage);
}

/**
 * @return string Normalized records_per_page token, or empty string when invalid for audit_logs.
 */
function itm_audit_logs_normalize_records_per_page_choice($raw)
{
    $normalized = strtolower(trim((string)$raw));
    if ($normalized === 'all') {
        return 'all';
    }

    if (!ctype_digit($normalized)) {
        return '';
    }

    $numeric = (int)$normalized;
    if ($numeric <= 0 || $numeric > 1000) {
        return '';
    }

    return (string)$numeric;
}

/**
 * Build a timestamped filename for tenant-scoped audit log SQL exports.
 */
function itm_audit_logs_backup_filename($companyId)
{
    return 'audit_logs_company_' . (int)$companyId . '_' . date('d_M_Y') . '_' . date('His') . '.sql';
}

/**
 * Why: Backup and download share one SQL builder so on-disk archives match streamed exports.
 */
function itm_audit_logs_build_sql_backup($conn, $companyId)
{
    $companyId = (int)$companyId;
    if ($companyId <= 0) {
        return false;
    }

    $dump = "-- IT Management Audit Logs Backup\n";
    $dump .= '-- Generated at: ' . date('Y-m-d H:i:s') . " UTC\n";
    $dump .= '-- Data scope: audit_logs.company_id = ' . $companyId . "\n";
    $dump .= "SET NAMES utf8mb4;\n";
    $dump .= "SET FOREIGN_KEY_CHECKS=0;\n\n";

    $createRes = mysqli_query($conn, 'SHOW CREATE TABLE `audit_logs`');
    $createRow = $createRes ? mysqli_fetch_assoc($createRes) : null;
    if (!$createRow || !isset($createRow['Create Table'])) {
        return false;
    }

    $dump .= "-- Table structure for `audit_logs`\n";
    $dump .= 'DROP TABLE IF EXISTS `audit_logs`;' . "\n";
    $dump .= $createRow['Create Table'] . ";\n\n";

    $dataStmt = mysqli_prepare($conn, 'SELECT * FROM audit_logs WHERE company_id = ? ORDER BY id ASC');
    if (!$dataStmt) {
        return false;
    }
    mysqli_stmt_bind_param($dataStmt, 'i', $companyId);
    mysqli_stmt_execute($dataStmt);
    $dataRes = mysqli_stmt_get_result($dataStmt);
    if (!$dataRes) {
        mysqli_stmt_close($dataStmt);
        return false;
    }

    $rowCount = 0;
    if (mysqli_num_rows($dataRes) > 0) {
        $dump .= "-- Data for `audit_logs`\n";
    }

    while ($dataRow = mysqli_fetch_assoc($dataRes)) {
        $columns = array_map(static function ($col) {
            return '`' . $col . '`';
        }, array_keys($dataRow));

        $values = [];
        foreach ($dataRow as $value) {
            if ($value === null) {
                $values[] = 'NULL';
            } else {
                $values[] = "'" . mysqli_real_escape_string($conn, (string)$value) . "'";
            }
        }

        $dump .= 'INSERT INTO `audit_logs` (' . implode(', ', $columns) . ') VALUES (' . implode(', ', $values) . ");\n";
        $rowCount++;
    }
    mysqli_stmt_close($dataStmt);

    if ($rowCount > 0) {
        $dump .= "\n";
    }

    $dump .= "SET FOREIGN_KEY_CHECKS=1;\n";

    return $dump;
}

// Extract filter parameters from the URL for persistent search/filtering
$search = trim((string)($_GET['search'] ?? ''));
$action = strtoupper(trim((string)($_GET['action_filter'] ?? '')));
$dateFrom = trim((string)($_GET['date_from'] ?? ''));
$dateTo = trim((string)($_GET['date_to'] ?? ''));
$sortableColumns = [
    'changed_at' => 'al.changed_at',
    'table_name' => 'al.table_name',
    'record_id' => 'al.record_id',
    'action' => 'al.action',
];
$sort = (string)($_GET['sort'] ?? 'changed_at');
$dir = strtoupper((string)($_GET['dir'] ?? 'DESC'));
if (!isset($sortableColumns[$sort])) {
    $sort = 'changed_at';
}
if (!in_array($dir, ['ASC', 'DESC'], true)) {
    $dir = 'DESC';
}
$sortSql = $sortableColumns[$sort] . ' ' . $dir;

$allowedActions = ['INSERT', 'UPDATE', 'DELETE'];
if (!in_array($action, $allowedActions, true)) {
    $action = '';
}

// Initialize dynamic query components
$where = ['al.company_id = ?'];
$params = [$companyId];
$types = 'i';

// Apply text search across multiple fields
if ($search !== '') {
    $where[] = '(al.table_name LIKE ? OR CAST(al.record_id AS CHAR) LIKE ? OR CONCAT(COALESCE(u.first_name, ""), " ", COALESCE(u.last_name, "")) LIKE ? OR COALESCE(al.actor_username, u.username, "") LIKE ? OR COALESCE(al.actor_email, u.work_email, "") LIKE ?)';
    $searchLike = '%' . $search . '%';
    $params[] = $searchLike;
    $params[] = $searchLike;
    $params[] = $searchLike;
    $params[] = $searchLike;
    $params[] = $searchLike;
    $types .= 'sssss';
}

// Apply action filter (INSERT/UPDATE/DELETE)
if ($action !== '') {
    $where[] = 'al.action = ?';
    $params[] = $action;
    $types .= 's';
}

// Apply date range filters
if ($dateFrom !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom) === 1) {
    $where[] = 'al.changed_at >= ?';
    $params[] = $dateFrom . ' 00:00:00';
    $types .= 's';
}
if ($dateTo !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo) === 1) {
    $where[] = 'al.changed_at <= ?';
    $params[] = $dateTo . ' 23:59:59';
    $types .= 's';
}

$listQueryBase = [
    'search' => $search,
    'action_filter' => $action,
    'date_from' => $dateFrom,
    'date_to' => $dateTo,
    'sort' => $sort,
    'dir' => $dir,
];

$recordsPerPageOptions = [
    '25' => '25',
    '50' => '50',
    '100' => '100',
    'all' => 'ALL',
];
$currentRecordsPerPage = strtolower((string)($ui_config['records_per_page'] ?? '25'));
if (
    !array_key_exists($currentRecordsPerPage, $recordsPerPageOptions)
    && itm_audit_logs_normalize_records_per_page_choice($currentRecordsPerPage) !== ''
) {
    $recordsPerPageOptions[$currentRecordsPerPage] = $currentRecordsPerPage;
}

$isAuditLogsAdmin = itm_is_admin($conn, (int)($_SESSION['employee_id'] ?? 0));

if ($_SERVER['REQUEST_METHOD'] === 'POST' && (string)($_POST['audit_logs_action'] ?? '') !== '') {
    itm_require_post_csrf();

    $postedAuditAction = (string)($_POST['audit_logs_action'] ?? '');
    $postedSearch = trim((string)($_POST['search'] ?? ''));
    $postedActionFilter = strtoupper(trim((string)($_POST['action_filter'] ?? '')));
    $postedDateFrom = trim((string)($_POST['date_from'] ?? ''));
    $postedDateTo = trim((string)($_POST['date_to'] ?? ''));
    $postedSort = (string)($_POST['sort'] ?? 'changed_at');
    $postedDir = strtoupper((string)($_POST['dir'] ?? 'DESC'));
    if (!isset($sortableColumns[$postedSort])) {
        $postedSort = 'changed_at';
    }
    if (!in_array($postedDir, ['ASC', 'DESC'], true)) {
        $postedDir = 'DESC';
    }
    if (!in_array($postedActionFilter, $allowedActions, true)) {
        $postedActionFilter = '';
    }

    $redirectQuery = itm_audit_logs_build_query([
        'search' => $postedSearch,
        'action_filter' => $postedActionFilter,
        'date_from' => $postedDateFrom,
        'date_to' => $postedDateTo,
        'sort' => $postedSort,
        'dir' => $postedDir,
        'page' => 1,
    ]);
    $redirectUrl = 'index.php' . ($redirectQuery !== '' ? '?' . $redirectQuery : '');

    if (!$isAuditLogsAdmin) {
        $_SESSION['audit_logs_flash_error'] = ['Only administrators can manage audit log archives.'];
        header('Location: ' . $redirectUrl);
        exit;
    }

    if ($postedAuditAction === 'clear_all') {
        $clearStmt = mysqli_prepare($conn, 'DELETE FROM audit_logs WHERE company_id = ?');
        if (!$clearStmt) {
            $_SESSION['audit_logs_flash_error'] = ['Unable to clear audit logs.'];
            header('Location: ' . $redirectUrl);
            exit;
        }
        mysqli_stmt_bind_param($clearStmt, 'i', $companyId);
        $clearedOk = mysqli_stmt_execute($clearStmt);
        $clearedCount = $clearedOk ? mysqli_stmt_affected_rows($clearStmt) : -1;
        mysqli_stmt_close($clearStmt);

        if (!$clearedOk) {
            $_SESSION['audit_logs_flash_error'] = ['Unable to clear audit logs.'];
        } else {
            $_SESSION['audit_logs_flash_success'] = ['Cleared ' . max(0, $clearedCount) . ' audit log record(s) for this company.'];
        }
        header('Location: ' . $redirectUrl);
        exit;
    }

    if ($postedAuditAction === 'backup_all' || $postedAuditAction === 'download_all') {
        $dump = itm_audit_logs_build_sql_backup($conn, $companyId);
        if ($dump === false) {
            $_SESSION['audit_logs_flash_error'] = ['Unable to generate audit log backup.'];
            header('Location: ' . $redirectUrl);
            exit;
        }

        $filename = itm_audit_logs_backup_filename($companyId);

        if ($postedAuditAction === 'download_all') {
            header('Content-Type: application/sql; charset=utf-8');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            header('Content-Length: ' . strlen($dump));
            echo $dump;
            exit;
        }

        $fullPath = BACKUP_PATH . $filename;
        if (file_put_contents($fullPath, $dump) === false) {
            $_SESSION['audit_logs_flash_error'] = ['Unable to write audit log backup file.'];
            header('Location: ' . $redirectUrl);
            exit;
        }

        if (defined('DUPLICATE_BACKUP_PATH') && DUPLICATE_BACKUP_PATH !== '') {
            @file_put_contents(DUPLICATE_BACKUP_PATH . $filename, $dump);
        }

        $_SESSION['audit_logs_flash_success'] = ['Audit log backup created: ' . $filename];
        header('Location: ' . $redirectUrl);
        exit;
    }

    $_SESSION['audit_logs_flash_error'] = ['Unknown audit log action.'];
    header('Location: ' . $redirectUrl);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && (string)($_POST['audit_logs_save_records_per_page'] ?? '') === '1') {
    itm_require_post_csrf();

    $postedSearch = trim((string)($_POST['search'] ?? ''));
    $postedAction = strtoupper(trim((string)($_POST['action_filter'] ?? '')));
    $postedDateFrom = trim((string)($_POST['date_from'] ?? ''));
    $postedDateTo = trim((string)($_POST['date_to'] ?? ''));
    $postedSort = (string)($_POST['sort'] ?? 'changed_at');
    $postedDir = strtoupper((string)($_POST['dir'] ?? 'DESC'));
    if (!isset($sortableColumns[$postedSort])) {
        $postedSort = 'changed_at';
    }
    if (!in_array($postedDir, ['ASC', 'DESC'], true)) {
        $postedDir = 'DESC';
    }
    if (!in_array($postedAction, $allowedActions, true)) {
        $postedAction = '';
    }

    $configToSave = is_array($ui_config) ? $ui_config : itm_get_ui_configuration($conn, $companyId);
    $postedRecordsPerPage = itm_audit_logs_normalize_records_per_page_choice($_POST['records_per_page'] ?? '25');
    if ($postedRecordsPerPage === '') {
        $_SESSION['audit_logs_flash_error'] = ['Rows on screen must be a positive number up to 1000, or ALL.'];
        $redirectQuery = itm_audit_logs_build_query([
            'search' => $postedSearch,
            'action_filter' => $postedAction,
            'date_from' => $postedDateFrom,
            'date_to' => $postedDateTo,
            'sort' => $postedSort,
            'dir' => $postedDir,
            'page' => 1,
        ]);
        header('Location: index.php' . ($redirectQuery !== '' ? '?' . $redirectQuery : ''));
        exit;
    }
    $configToSave['records_per_page'] = $postedRecordsPerPage;
    $settingsUserId = (int)($_SESSION['employee_id'] ?? 0);

    if ($settingsUserId <= 0 || !itm_save_ui_configuration($conn, $companyId, $configToSave, $settingsUserId)) {
        $_SESSION['audit_logs_flash_error'] = ['Unable to save rows on screen setting.'];
    }

    $redirectQuery = itm_audit_logs_build_query([
        'search' => $postedSearch,
        'action_filter' => $postedAction,
        'date_from' => $postedDateFrom,
        'date_to' => $postedDateTo,
        'sort' => $postedSort,
        'dir' => $postedDir,
        'page' => 1,
    ]);
    header('Location: index.php' . ($redirectQuery !== '' ? '?' . $redirectQuery : ''));
    exit;
}

// Resolve pagination limit (ALL capped at 1000 rows in this module only).
$perPage = itm_audit_logs_resolve_records_per_page($ui_config ?? null);
$page = max(1, (int)($_GET['page'] ?? 1));
$offset = ($page - 1) * $perPage;

$countSql = 'SELECT COUNT(*) AS total '
          . 'FROM audit_logs al '
          . 'LEFT JOIN employees u ON u.id = al.employee_id '
          . 'WHERE ' . implode(' AND ', $where);
$countStmt = mysqli_prepare($conn, $countSql);
$totalRows = 0;
if ($countStmt) {
    mysqli_stmt_bind_param($countStmt, $types, ...$params);
    mysqli_stmt_execute($countStmt);
    $countResult = mysqli_stmt_get_result($countStmt);
    if ($countResult && ($countRow = mysqli_fetch_assoc($countResult))) {
        $totalRows = (int)($countRow['total'] ?? 0);
    }
    mysqli_stmt_close($countStmt);
}
$totalPages = max(1, (int)ceil($totalRows / max(1, $perPage)));
if ($page > $totalPages) {
    $page = $totalPages;
    $offset = ($page - 1) * $perPage;
}

// Final query construction. 
// Uses JOINs to resolve user details and Prepared Statements for security.
$sql = 'SELECT al.*, u.username, u.work_email, u.first_name, u.last_name '
     . 'FROM audit_logs al '
     . 'LEFT JOIN employees u ON u.id = al.employee_id '
     . 'WHERE ' . implode(' AND ', $where) . ' '
     . 'ORDER BY ' . $sortSql . ' LIMIT ' . (int)$perPage . ' OFFSET ' . (int)$offset;

$stmt = mysqli_prepare($conn, $sql);
if (!$stmt) {
    http_response_code(500);
    exit('Unable to load audit logs.');
}

mysqli_stmt_bind_param($stmt, $types, ...$params);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$rows = [];
while ($result && ($row = mysqli_fetch_assoc($result))) {
    $rows[] = $row;
}
mysqli_stmt_close($stmt);

/**
 * Utility to truncate long JSON strings for cleaner table display.
 */
function itm_audit_preview($text, $limit = 120) {
    $text = trim((string)$text);
    if ($text === '') {
        return '—';
    }

    if (mb_strlen($text) <= $limit) {
        return $text;
    }

    return mb_substr($text, 0, $limit) . '...';
}

/**
 * Normalize audit payload text so empty-ish values render consistently.
 *
 * Why: Audit triggers can store blank strings or literal "null", and showing
 * those as real payloads confuses operators when they expect "no value".
 */
function itm_audit_normalize_value($text) {
    $text = trim((string)$text);
    if ($text === '' || strcasecmp($text, 'null') === 0) {
        return '—';
    }

    return $text;
}

/**
 * Provide action-aware empty-state messaging for old/new payload sections.
 *
 * Why: INSERT events legitimately have no previous values (and DELETE events have
 * no new values), so plain dashes can look like missing data defects.
 */
function itm_audit_describe_payload($action, $normalizedValue, $isOldValue) {
    if ($normalizedValue !== '—') {
        return $normalizedValue;
    }

    $action = strtoupper(trim((string)$action));
    if ($isOldValue && $action === 'INSERT') {
        return '— Not applicable for INSERT events.';
    }
    if (!$isOldValue && $action === 'DELETE') {
        return '— Not applicable for DELETE events.';
    }

    return '—';
}

$moduleListHeading = '🧾 Audit Logs';
$newButtonPosition = (string)($ui_config['new_button_position'] ?? 'left_right');
if (!in_array($newButtonPosition, ['left', 'right', 'left_right'], true)) {
    $newButtonPosition = 'left_right';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <?php
if (!isset($currentUiConfig)) {
    $currentUiConfig = $ui_config ?? [];
}
if (!isset($crud_title)) {
    $crud_title = 'Audit Logs';
}
?>
<title><?= sanitize($crud_title) ?> - <?php echo sanitize($app_name ?? itm_ui_config_app_name($currentUiConfig)); ?></title>
    <link rel="stylesheet" href="../../css/styles.css">
    <style>
        .audit-toolbar { display:flex; align-items:center; justify-content:space-between; gap:12px; margin-bottom:16px; flex-wrap:wrap; }
        .audit-admin-actions { display:flex; gap:8px; flex-wrap:wrap; align-items:center; }
        .audit-toolbar h1 { margin:0; font-size:1.5rem; font-weight:700; }
        .audit-filters form { display:grid; grid-template-columns:2fr 1fr 1fr 1fr auto auto; gap:10px; align-items:end; }
        .audit-kpis { display:grid; grid-template-columns:repeat(4,minmax(0,1fr)); gap:10px; margin-bottom:14px; }
        .audit-kpi { border:1px solid var(--border); border-radius:10px; padding:10px 12px; background:var(--input-bg); }
        .audit-kpi .label { font-size:12px; opacity:.8; margin-bottom:4px; }
        .audit-kpi .value { font-size:18px; font-weight:700; }
        .audit-kpi select { width:100%; margin-top:4px; }
        .audit-table-wrap { overflow-x:auto; }
        .audit-row-chip { display:inline-block; padding:4px 8px; border-radius:999px; font-size:11px; font-weight:700; border:1px solid transparent; white-space:nowrap; }
        .audit-row-chip.insert { background:#e8f8ee; border-color:#9cd8b1; color:#18794e; }
        .audit-row-chip.update { background:#eef4ff; border-color:#9eb8ee; color:#1d4f91; }
        .audit-row-chip.delete { background:#fdecec; border-color:#f0b6b6; color:#a52727; }
        .audit-user { max-width:220px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
        .audit-summary { display:grid; gap:8px; }
        .audit-json { white-space:pre-wrap; word-break:break-word; max-width:520px; margin:0; font-size:12px; line-height:1.4; background:var(--input-bg); border:1px solid var(--border); border-radius:8px; padding:10px; }
        @media (max-width:1080px) { .audit-filters form { grid-template-columns:1fr 1fr; } .audit-kpis { grid-template-columns:1fr 1fr; } }
        @media (max-width:768px) {
            .audit-filters form { grid-template-columns:1fr; }
            .audit-kpis { grid-template-columns:1fr; }
            .audit-user { max-width:none; white-space:normal; }
        }
    </style>
</head>
<body>
<div class="container">
    <?php include '../../includes/sidebar.php'; ?>
    <div class="main-content">
        <?php include '../../includes/header.php'; ?>

        <div class="content">
            <div class="audit-toolbar" data-itm-new-button-managed="server">
                <?php if (in_array($newButtonPosition, ['left', 'left_right'], true)): ?>
                    <a href="index.php" class="btn btn-primary">🔄 Refresh</a>
                <?php else: ?>
                    <span aria-hidden="true"></span>
                <?php endif; ?>
                <h1><?php echo sanitize($moduleListHeading); ?></h1>
                <?php if (in_array($newButtonPosition, ['right', 'left_right'], true)): ?>
                    <a href="index.php" class="btn btn-primary">🔄 Refresh</a>
                <?php else: ?>
                    <span aria-hidden="true"></span>
                <?php endif; ?>
            </div>

            <?php foreach ($messages as $message): ?>
                <div class="alert alert-success" style="margin-bottom:10px;"><?php echo sanitize($message); ?></div>
            <?php endforeach; ?>
            <?php echo itm_render_alert_errors($errors); ?>

            <?php if ($isAuditLogsAdmin): ?>
            <div class="card" style="margin-bottom:16px;">
                <form method="POST" action="index.php" class="audit-admin-actions" data-itm-audit-logs-admin-actions="1">
                    <input type="hidden" name="csrf_token" value="<?php echo sanitize($csrfToken); ?>">
                    <input type="hidden" name="search" value="<?php echo sanitize($search); ?>">
                    <input type="hidden" name="action_filter" value="<?php echo sanitize($action); ?>">
                    <input type="hidden" name="date_from" value="<?php echo sanitize($dateFrom); ?>">
                    <input type="hidden" name="date_to" value="<?php echo sanitize($dateTo); ?>">
                    <input type="hidden" name="sort" value="<?php echo sanitize($sort); ?>">
                    <input type="hidden" name="dir" value="<?php echo sanitize($dir); ?>">
                    <button type="submit" name="audit_logs_action" value="download_all" class="btn btn-sm btn-primary">Download ALL Logs</button>
                    <button type="submit" name="audit_logs_action" value="backup_all" class="btn btn-sm btn-primary">Backup ALL Logs</button>
                    <button type="submit" name="audit_logs_action" value="clear_all" class="btn btn-sm btn-danger"
                        onclick="return confirm('Clear all audit logs for this company? This cannot be undone.');">Clear ALL Logs</button>
                </form>
            </div>
            <?php endif; ?>

            <!-- SEARCH AND FILTER FORM -->
            <div class="card audit-filters" style="margin-bottom:16px;">
                <form method="GET">
                    <div class="form-group" style="margin:0;">
                        <label>Search</label>
                        <input type="text" name="search" value="<?php echo sanitize($search); ?>" placeholder="Table, record id, or user name">
                    </div>
                    <div class="form-group" style="margin:0;">
                        <label>Action</label>
                        <select name="action_filter">
                            <option value="">All</option>
                            <?php foreach ($allowedActions as $opt): ?>
                                <option value="<?php echo sanitize($opt); ?>" <?php echo $action === $opt ? 'selected' : ''; ?>><?php echo sanitize($opt); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group" style="margin:0;">
                        <label>Date From</label>
                        <input type="date" name="date_from" value="<?php echo sanitize($dateFrom); ?>">
                    </div>
                    <div class="form-group" style="margin:0;">
                        <label>Date To</label>
                        <input type="date" name="date_to" value="<?php echo sanitize($dateTo); ?>">
                    </div>
                    <button type="submit" class="btn btn-primary">Search</button>
                    <a href="index.php" class="btn">🔙</a>
                </form>
            </div>

            <div class="audit-kpis">
                <div class="audit-kpi">
                    <form id="audit-logs-records-per-page-form" method="POST" action="index.php" style="margin:0;">
                        <input type="hidden" name="csrf_token" value="<?php echo sanitize($csrfToken); ?>">
                        <input type="hidden" name="audit_logs_save_records_per_page" value="1">
                        <input type="hidden" name="search" value="<?php echo sanitize($search); ?>">
                        <input type="hidden" name="action_filter" value="<?php echo sanitize($action); ?>">
                        <input type="hidden" name="date_from" value="<?php echo sanitize($dateFrom); ?>">
                        <input type="hidden" name="date_to" value="<?php echo sanitize($dateTo); ?>">
                        <input type="hidden" name="sort" value="<?php echo sanitize($sort); ?>">
                        <input type="hidden" name="dir" value="<?php echo sanitize($dir); ?>">
                        <label class="label" for="records_per_page">Rows on Screen</label>
                        <select id="records_per_page" name="records_per_page">
                            <?php foreach ($recordsPerPageOptions as $value => $label): ?>
                                <?php $optionValue = strtolower((string)$value); ?>
                                <option value="<?php echo sanitize($optionValue); ?>" <?php echo $currentRecordsPerPage === $optionValue ? 'selected' : ''; ?>>
                                    <?php echo sanitize($label); ?>
                                </option>
                            <?php endforeach; ?>
                            <option value="__add_new__">➕</option>
                        </select>
                    </form>
                </div>
                <div class="audit-kpi"><div class="label">Insert Events</div><div class="value"><?php echo (int)count(array_filter($rows, static fn($r) => (($r['action'] ?? '') === 'INSERT'))); ?></div></div>
                <div class="audit-kpi"><div class="label">Update Events</div><div class="value"><?php echo (int)count(array_filter($rows, static fn($r) => (($r['action'] ?? '') === 'UPDATE'))); ?></div></div>
                <div class="audit-kpi"><div class="label">Delete Events</div><div class="value"><?php echo (int)count(array_filter($rows, static fn($r) => (($r['action'] ?? '') === 'DELETE'))); ?></div></div>
            </div>

            <!-- LOG DATA TABLE -->
            <div class="card audit-table-wrap" data-itm-no-import-excel="1">
                <table>
                    <thead>
                        <tr>
                            <?php $nextDir = ($sort === 'changed_at' && $dir === 'ASC') ? 'DESC' : 'ASC'; ?>
                            <th><a href="?<?php echo sanitize(itm_audit_logs_build_query(array_merge($listQueryBase, ['sort' => 'changed_at', 'dir' => $nextDir, 'page' => 1]))); ?>" style="text-decoration:none;color:inherit;">Date &amp; Time<?php if ($sort === 'changed_at'): ?> <?php echo $dir === 'ASC' ? '▲' : '▼'; ?><?php endif; ?></a></th>
                            <th>User</th>
                            <?php $nextDir = ($sort === 'table_name' && $dir === 'ASC') ? 'DESC' : 'ASC'; ?>
                            <th><a href="?<?php echo sanitize(itm_audit_logs_build_query(array_merge($listQueryBase, ['sort' => 'table_name', 'dir' => $nextDir, 'page' => 1]))); ?>" style="text-decoration:none;color:inherit;">Table Name<?php if ($sort === 'table_name'): ?> <?php echo $dir === 'ASC' ? '▲' : '▼'; ?><?php endif; ?></a></th>
                            <?php $nextDir = ($sort === 'record_id' && $dir === 'ASC') ? 'DESC' : 'ASC'; ?>
                            <th><a href="?<?php echo sanitize(itm_audit_logs_build_query(array_merge($listQueryBase, ['sort' => 'record_id', 'dir' => $nextDir, 'page' => 1]))); ?>" style="text-decoration:none;color:inherit;">Record ID<?php if ($sort === 'record_id'): ?> <?php echo $dir === 'ASC' ? '▲' : '▼'; ?><?php endif; ?></a></th>
                            <?php $nextDir = ($sort === 'action' && $dir === 'ASC') ? 'DESC' : 'ASC'; ?>
                            <th><a href="?<?php echo sanitize(itm_audit_logs_build_query(array_merge($listQueryBase, ['sort' => 'action', 'dir' => $nextDir, 'page' => 1]))); ?>" style="text-decoration:none;color:inherit;">Action<?php if ($sort === 'action'): ?> <?php echo $dir === 'ASC' ? '▲' : '▼'; ?><?php endif; ?></a></th>
                            <th>Change Summary</th>
                            <th class="itm-actions-cell" data-itm-actions-origin="1">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (!$rows): ?>
                        <tr>
                            <td colspan="7" style="text-align:center;">No records found.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($rows as $row): ?>
                            <?php
                            // Determine the most appropriate user display name
                            $userName = trim((string)(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? '')));
                            $userEmail = trim((string)($row['actor_email'] ?? $row['email'] ?? ''));
                            if ($userName === '') {
                                $userName = trim((string)($row['actor_username'] ?? ''));
                            }
                            if ($userName === '' && $userEmail !== '') {
                                $userName = $userEmail;
                            }
                            if ($userName === '') {
                                $userName = trim((string)($row['username'] ?? ''));
                            }
                            if ($userName === '') {
                                $userName = $row['employee_id'] ? ('User #' . (int)$row['employee_id']) : 'System';
                            }

                            // Styling based on action type
                            $actionClass = 'update';
                            if (($row['action'] ?? '') === 'INSERT') {
                                $actionClass = 'insert';
                            } elseif (($row['action'] ?? '') === 'DELETE') {
                                $actionClass = 'delete';
                            }

                            $oldValues = itm_audit_normalize_value($row['old_values'] ?? '');
                            $newValues = itm_audit_normalize_value($row['new_values'] ?? '');
                            $oldValuesDisplay = itm_audit_describe_payload($row['action'] ?? '', $oldValues, true);
                            $newValuesDisplay = itm_audit_describe_payload($row['action'] ?? '', $newValues, false);
                            $previewText = 'Old: ' . itm_audit_preview($oldValuesDisplay, 80) . ' | New: ' . itm_audit_preview($newValuesDisplay, 80);
                            ?>
                            <tr>
                                <td><?php echo sanitize((string)$row['changed_at']); ?></td>
                                <td class="audit-user" title="<?php echo sanitize($userEmail !== '' ? ($userName . ' <' . $userEmail . '>') : $userName); ?>">
                                    <?php echo sanitize($userName); ?>
                                    <?php if ($userEmail !== ''): ?>
                                        <br><small><?php echo sanitize($userEmail); ?></small>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo sanitize((string)$row['table_name']); ?></td>
                                <td><?php echo (int)$row['record_id']; ?></td>
                                <td><span class="audit-row-chip <?php echo sanitize($actionClass); ?>"><?php echo sanitize((string)$row['action']); ?></span></td>
                                <td>
                                    <div class="audit-summary">
                                        <span><?php echo sanitize($previewText); ?></span>
                                        <details>
                                            <summary class="btn btn-sm btn-primary" style="cursor:pointer;">Click to see more</summary>
                                            <div class="audit-json"><strong>Old Values</strong><br><?php echo sanitize($oldValuesDisplay); ?></div>
                                            <div class="audit-json"><strong>New Values</strong><br><?php echo sanitize($newValuesDisplay); ?></div>
                                        </details>
                                    </div>
                                </td>
                                <td class="itm-actions-cell" data-itm-actions-origin="1">
                                    <div class="itm-actions-wrap">
                                        <a class="btn btn-sm btn-primary" href="view.php?id=<?php echo (int)($row['id'] ?? 0); ?>" title="View audit log">🔎</a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <?php if ($totalRows > $perPage): ?>
                <div style="display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;margin-top:12px;">
                    <div>Showing <?php echo $offset + 1; ?>-<?php echo min($offset + $perPage, $totalRows); ?> of <?php echo (int)$totalRows; ?></div>
                    <div style="display:flex;gap:6px;flex-wrap:wrap;">
                        <?php if ($page > 1): ?>
                            <a class="btn btn-sm" href="?<?php echo sanitize(itm_audit_logs_build_query(array_merge($listQueryBase, ['page' => $page - 1]))); ?>" title="◀️ Previous">Previous</a>
                        <?php endif; ?>
                        <span class="btn btn-sm" style="pointer-events:none;opacity:.8;">Page <?php echo (int)$page; ?> of <?php echo (int)$totalPages; ?></span>
                        <?php if ($page < $totalPages): ?>
                            <a class="btn btn-sm" href="?<?php echo sanitize(itm_audit_logs_build_query(array_merge($listQueryBase, ['page' => $page + 1]))); ?>" title="▶️ Next">Next</a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
<script>
(function () {
    const form = document.getElementById('audit-logs-records-per-page-form');
    const recordsPerPageSelect = document.getElementById('records_per_page');
    if (!form || !recordsPerPageSelect) {
        return;
    }

    const addOptionValue = '__add_new__';

    function isValidRecordsPerPageInput(raw) {
        const normalized = String(raw || '').trim().toLowerCase();
        if (normalized === 'all') {
            return 'all';
        }
        if (!/^\d+$/.test(normalized)) {
            return '';
        }

        const numeric = parseInt(normalized, 10);
        if (!Number.isFinite(numeric) || numeric <= 0 || numeric > 1000) {
            return '';
        }

        return String(numeric);
    }

    function ensureRecordsPerPageOption(value) {
        if (!value || value === addOptionValue) {
            return;
        }

        const exists = Array.from(recordsPerPageSelect.options).find((option) => option.value === value);
        if (exists) {
            return;
        }

        const customOption = document.createElement('option');
        customOption.value = value;
        customOption.textContent = value === 'all' ? 'ALL' : value;

        const addOption = Array.from(recordsPerPageSelect.options).find((option) => option.value === addOptionValue);
        if (addOption) {
            recordsPerPageSelect.insertBefore(customOption, addOption);
        } else {
            recordsPerPageSelect.appendChild(customOption);
        }
    }

    recordsPerPageSelect.dataset.previousValue = recordsPerPageSelect.value;

    recordsPerPageSelect.addEventListener('focus', () => {
        if (recordsPerPageSelect.value !== addOptionValue) {
            recordsPerPageSelect.dataset.previousValue = recordsPerPageSelect.value;
        }
    });

    recordsPerPageSelect.addEventListener('change', () => {
        if (recordsPerPageSelect.value === addOptionValue) {
            const input = window.prompt('Enter records per page (1–1000) or "all" (shows up to 1000 rows):', recordsPerPageSelect.dataset.previousValue || '25');
            if (input === null) {
                recordsPerPageSelect.value = recordsPerPageSelect.dataset.previousValue || '25';
                return;
            }

            const normalized = isValidRecordsPerPageInput(input);
            if (!normalized) {
                window.alert('Please enter a number from 1 to 1000, or "all".');
                recordsPerPageSelect.value = recordsPerPageSelect.dataset.previousValue || '25';
                return;
            }

            ensureRecordsPerPageOption(normalized);
            recordsPerPageSelect.value = normalized;
            recordsPerPageSelect.dataset.previousValue = normalized;
            form.submit();
            return;
        }

        recordsPerPageSelect.dataset.previousValue = recordsPerPageSelect.value;
        form.submit();
    });
})();
</script>
</body>
</html>
