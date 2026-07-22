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
$safe_dept_code = explorer_fetch_user_department_code($conn, $user_id, $company_id);

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

    if ($safe_dept_code === '' || (!str_starts_with($relative_path, "Departments/$safe_dept_code/") &&
        $relative_path !== "Departments/$safe_dept_code")) {
        http_response_code(403);
        exit("Access denied to department folder.");
    }
}

if (!$relative_path || !file_exists($full_path) || is_dir($full_path)) {
    http_response_code(404);
    exit("File not found.");
}

$ext = strtolower(pathinfo($full_path, PATHINFO_EXTENSION));

/* -------------------------
   IMAGES
------------------------- */
if (in_array($ext, ['jpg','jpeg','png','gif','webp'])) {
    header("Content-Type: " . mime_content_type($full_path));
    readfile($full_path);
    exit;
}

/* -------------------------
   PDF
------------------------- */
if ($ext === 'pdf') {
    header("Content-Type: application/pdf");
    readfile($full_path);
    exit;
}
/* -------------------------
   ZIP
------------------------- */
if ($ext === 'zip') {
    header("Content-Type: application/zip");
    readfile($full_path);
    exit;
}
/* -------------------------
   TEXT / CODE
------------------------- */
header("Content-Type: text/plain; charset=utf-8");
readfile($full_path);
exit;
