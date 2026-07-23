<?php
/**
 * Explorer File Viewer
 *
 * Securely serves files from the /files/{company_id} storage area.
 * Validates session, company scoping, and access permissions for the requested path.
 */

require_once '../../config/config.php';

// Why: User must be logged in with a company selected.
if (!isset($_SESSION['employee_id']) || !isset($_SESSION['company_id'])) {
    http_response_code(403);
    exit("Access denied.");
}

$company_id = (int)$_SESSION['company_id'];
$user_id = (int)$_SESSION['employee_id'];
$username = $_SESSION['username'] ?? 'unknown';
$safe_username = preg_replace('/[^a-zA-Z0-9_\-\.]/', '', $username);
$user_private_dir = "{$safe_username}_{$user_id}";

require_once ROOT_PATH . 'modules/explorer/explorer_storage_helpers.php';

// Why: Department ACL uses departments.code (e.g. FNB), matching api.php get_full_path().
$user_dept_codes = explorer_fetch_user_department_codes($conn, $user_id, $company_id);

$path = $_GET['path'] ?? '';
$storage_root = ROOT_PATH . 'files/' . $company_id;

$relative_path = explorer_normalize_relative_path($path);
if ($relative_path === null) {
    http_response_code(403);
    exit("Invalid path.");
}

require_once ROOT_PATH . 'modules/explorer/explorer_vault_helpers.php';
$vaultCheck = explorer_enforce_vault_for_private_path($relative_path, $user_private_dir);
if (!$vaultCheck['ok']) {
    http_response_code(403);
    exit($vaultCheck['error']);
}

// Why: Access control logic (mirroring api.php) with segment-boundary checks.
$isEmployeeProfilePhotoPath = (bool)preg_match('#^Private/[^/]+/profile/#', $relative_path);

// Why: profile photos are stored under the employee's home company_id; session
// company_id is the tenant switcher and often differs for multi-company admins.
if ($isEmployeeProfilePhotoPath && preg_match('#^Private/[^/]+_([0-9]+)/profile/#', $relative_path, $photoOwnerMatch)) {
    $photoOwnerEmployeeId = (int)$photoOwnerMatch[1];
    if ($photoOwnerEmployeeId > 0) {
        $photoCompanyStmt = mysqli_prepare($conn, 'SELECT company_id FROM employees WHERE id = ? LIMIT 1');
        if ($photoCompanyStmt) {
            mysqli_stmt_bind_param($photoCompanyStmt, 'i', $photoOwnerEmployeeId);
            mysqli_stmt_execute($photoCompanyStmt);
            $photoCompanyRow = itm_mysqli_stmt_fetch_assoc($photoCompanyStmt);
            mysqli_stmt_close($photoCompanyStmt);
            $photoHomeCompanyId = (int)($photoCompanyRow['company_id'] ?? 0);
            if ($photoHomeCompanyId > 0) {
                $storage_root = ROOT_PATH . 'files/' . $photoHomeCompanyId;
            }
        }
    }
}

$full_path = $storage_root . ($relative_path !== '' ? '/' . $relative_path : '');

if (!$isEmployeeProfilePhotoPath && ($relative_path === 'Private' || str_starts_with($relative_path, 'Private/'))) {
    // Forbidden to access the 'Private' root itself.
    if ($relative_path === 'Private') {
        http_response_code(403);
        exit("Access denied to private folder.");
    }

    if (!str_starts_with($relative_path, "Private/$user_private_dir/") &&
        $relative_path !== "Private/$user_private_dir") {
        http_response_code(403);
        exit("Access denied to private folder.");
    }
}
if ($relative_path === 'Departments' || str_starts_with($relative_path, 'Departments/')) {
    // Forbidden to access the 'Departments' root itself.
    if ($relative_path === 'Departments') {
        http_response_code(403);
        exit("Access denied to department folder.");
    }

    if (!explorer_department_path_allowed($relative_path, $user_dept_codes)) {
        http_response_code(403);
        exit("Access denied to department folder.");
    }
}

if (!$relative_path || !file_exists($full_path) || is_dir($full_path)) {
    http_response_code(404);
    exit("File not found.");
}

$ext = strtolower(pathinfo($full_path, PATHINFO_EXTENSION));
$forceDownload = isset($_GET['download']) && (string)$_GET['download'] === '1';
$downloadName = basename($full_path);

// Why: Serve every Explorer-whitelisted extension with a sensible Content-Type; attachment when requested.
$contentType = '';
if (function_exists('mime_content_type')) {
    $detected = @mime_content_type($full_path);
    if (is_string($detected) && $detected !== '') {
        $contentType = $detected;
    }
}
if ($contentType === '') {
    $serveMap = [
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'gif' => 'image/gif',
        'webp' => 'image/webp',
        'pdf' => 'application/pdf',
        'txt' => 'text/plain; charset=utf-8',
        'md' => 'text/plain; charset=utf-8',
        'log' => 'text/plain; charset=utf-8',
        'json' => 'application/json; charset=utf-8',
        'xml' => 'application/xml; charset=utf-8',
        'csv' => 'text/csv; charset=utf-8',
        'zip' => 'application/zip',
        'doc' => 'application/msword',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'xls' => 'application/vnd.ms-excel',
        'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'ppt' => 'application/vnd.ms-powerpoint',
        'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
    ];
    $contentType = $serveMap[$ext] ?? 'application/octet-stream';
}

header('Content-Type: ' . $contentType);
if ($forceDownload) {
    header('Content-Disposition: attachment; filename="' . str_replace('"', '', $downloadName) . '"');
}
readfile($full_path);
exit;
