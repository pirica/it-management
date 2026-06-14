<?php
/**
 * Explorer File Viewer
 *
 * Securely serves files from the /files/{company_id} storage area.
 * Validates session, company scoping, and access permissions for the requested path.
 */

require_once '../../config/config.php';

// Why: Protection Zone - User needs to be logged in and have a company selected.
if (!isset($_SESSION['user_id']) || !isset($_SESSION['company_id'])) {
    http_response_code(403);
    exit("Access denied.");
}

$company_id = (int)$_SESSION['company_id'];
$user_id = (int)$_SESSION['user_id'];
$username = $_SESSION['username'] ?? 'unknown';
$user_private_dir = "{$username}_{$user_id}";

// Why: Fetch user department for access control.
$dept_id = 0;
$dept_res = mysqli_query($conn, "SELECT department_id FROM employees WHERE user_id = $user_id AND company_id = $company_id LIMIT 1");
if ($dept_res && $dept_row = mysqli_fetch_assoc($dept_res)) {
    $dept_id = (int)$dept_row['department_id'];
}

$path = $_GET['path'] ?? '';
$storage_root = ROOT_PATH . 'files/' . $company_id;

// Why: Directory traversal protection.
if (strpos($path, '..') !== false) {
    http_response_code(403);
    exit("Invalid path.");
}

$full_path = $storage_root . ($path ? "/" . trim($path, '/') : "");

// Why: Access control logic (mirroring api.php) with segment-boundary checks.
$relative_path = trim($path, '/');
if ($relative_path === 'Private' || str_starts_with($relative_path, 'Private/')) {
    if ($relative_path !== 'Private' &&
        !str_starts_with($relative_path, "Private/$user_private_dir/") &&
        $relative_path !== "Private/$user_private_dir") {
        http_response_code(403);
        exit("Access denied to private folder.");
    }
}
if ($relative_path === 'Departments' || str_starts_with($relative_path, 'Departments/')) {
    if ($dept_id <= 0 || ($relative_path !== 'Departments' &&
        !str_starts_with($relative_path, "Departments/$dept_id/") &&
        $relative_path !== "Departments/$dept_id")) {
        http_response_code(403);
        exit("Access denied to department folder.");
    }
}

if (!$path || !file_exists($full_path) || is_dir($full_path)) {
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
