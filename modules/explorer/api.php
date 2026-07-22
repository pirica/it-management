<?php
/**
 * Explorer API (Fixed)
 *
 * Handles all file and folder operations with multi-tenant company scoping,
 * department-based access control, and user-private storage.
 * All operations are synchronised with the 'explorer' database table and
 * recorded in the system audit logs.
 *
 * FIX: Added validation to prevent path traversal via 'item' and 'name' parameters.
 */

if (defined('ROOT_PATH')) {
    require_once ROOT_PATH . 'config/config.php';
} else {
    require_once dirname(dirname(dirname(__FILE__))) . '/config/config.php';
}

/* ---------------- SAFE PATH HELPERS ---------------- */

/**
 * Validates and resolves a path relative to the company storage root.
 * Ensures the user has permission to access the requested path.
 */
if (!function_exists('get_full_path')) {
function get_full_path($storage_root, $relative_path, $user_id, $dept_code, $username) {
    $relative_path = explorer_normalize_relative_path($relative_path);
    if ($relative_path === null) {
        return null;
    }

    $full = $storage_root . ($relative_path ? "/$relative_path" : "");

    // Why: Simple check to ensure we are still under the company storage root.
    if (strpos($full, $storage_root) !== 0) return null;

    // Why: Access control logic with segment-boundary checks.
    // Why: Sanitise username for filesystem safety to prevent path traversal or separator issues.
    $safe_username = preg_replace('/[^a-zA-Z0-9_\-\.]/', '', $username);
    $user_private_dir = "{$safe_username}_{$user_id}";

    // Paths starting with 'Private' are restricted to the owner's username_id subfolder.
    if ($relative_path === 'Private' || str_starts_with($relative_path, 'Private/')) {
        // Forbidden to access the 'Private' root itself.
        if ($relative_path === 'Private') return null;

        if (!str_starts_with($relative_path, "Private/$user_private_dir/") &&
            $relative_path !== "Private/$user_private_dir") {
            return null;
        }
    }

    // Paths starting with 'Departments' are restricted to the user's department code subfolder.
    if ($relative_path === 'Departments' || str_starts_with($relative_path, 'Departments/')) {
        if ($dept_code === '') {
            return null;
        }

        // Why: Departments root is listable; list action filters to the user's code folder only.
        if ($relative_path === 'Departments') {
            return $full;
        }

        if (!str_starts_with($relative_path, "Departments/$dept_code/") &&
            $relative_path !== "Departments/$dept_code") {
            return null;
        }
    }

    return $full;
}
}

/**
 * Why: Managed deny_http placeholders (.htaccess, index.html) must stay on disk but never appear in Explorer listings.
 */
if (!function_exists('explorer_is_hidden_system_entry')) {
function explorer_is_hidden_system_entry($name) {
    $base = basename(str_replace('\\', '/', (string)$name));
    if ($base === '.htaccess') {
        return true;
    }
    return strcasecmp($base, 'index.html') === 0;
}
}

/**
 * @return array<int,array{name:string,type:string}>
 */
if (!function_exists('explorer_filter_trash_list_to_leaf_items')) {
function explorer_filter_trash_list_to_leaf_items(array $items)
{
    if ($items === []) {
        return $items;
    }

    $normalized = [];
    foreach ($items as $item) {
        $normalized[] = [
            'name' => str_replace('\\', '/', (string)($item['name'] ?? '')),
            'type' => (string)($item['type'] ?? 'file'),
        ];
    }

    $filtered = [];
    foreach ($normalized as $item) {
        $name = (string)$item['name'];
        if ($name === '') {
            continue;
        }
        $hasDescendant = false;
        foreach ($normalized as $other) {
            $otherName = (string)$other['name'];
            if ($otherName === $name) {
                continue;
            }
            if (strpos($otherName, $name . '/') === 0) {
                $hasDescendant = true;
                break;
            }
        }
        if (!$hasDescendant) {
            $filtered[] = $item;
        }
    }

    return $filtered;
}
}

/**
 * @return array<int,array{name:string,type:string}>
 */
if (!function_exists('explorer_collect_visible_trash_items')) {
function explorer_collect_visible_trash_items($trash_root, $user_id, $dept_code, $username)
{
    $items = [];
    if (!is_dir($trash_root)) {
        return $items;
    }

    $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($trash_root, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );
    foreach ($it as $file) {
        $rel = substr($file->getPathname(), strlen($trash_root) + 1);
        $safe_rel = str_replace('\\', '/', $rel);
        if (get_full_path($trash_root, $safe_rel, $user_id, $dept_code, $username) === null) {
            continue;
        }
        if (explorer_is_hidden_system_entry($rel)) {
            continue;
        }
        $items[] = [
            'name' => $safe_rel,
            'type' => $file->isDir() ? 'folder' : 'file',
        ];
    }

    return explorer_filter_trash_list_to_leaf_items($items);
}
}

/**
 * @return bool
 */
if (!function_exists('explorer_user_has_visible_trash_items')) {
function explorer_user_has_visible_trash_items($trash_root, $user_id, $dept_code, $username)
{
    return explorer_collect_visible_trash_items($trash_root, $user_id, $dept_code, $username) !== [];
}
}

/**
 * Why: Explorer preview must route images/PDFs through file.php instead of reading binary bytes as text.
 */
if (!function_exists('explorer_resolve_preview_mode')) {
function explorer_resolve_preview_mode($filename) {
    $ext = strtolower(pathinfo((string)$filename, PATHINFO_EXTENSION));
    if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'], true)) {
        return 'image';
    }
    if ($ext === 'pdf') {
        return 'pdf';
    }
    if (in_array($ext, ['txt', 'md', 'log', 'json', 'xml', 'csv', 'php', 'js', 'css', 'html', 'htm'], true)) {
        return 'text';
    }
    return 'unsupported';
}
}

/* ---------------- ZIP DOWNLOAD ---------------- */
if (isset($_GET['downloadZip'])) {
    if (!isset($_SESSION['company_id'])) exit("Access denied.");
    $company_id = (int)$_SESSION['company_id'];
    $user_id = (int)$_SESSION['employee_id'];
    $username = $_SESSION['username'] ?? 'unknown';

    // Fetch department for user
    $dept_code = '';
    $dept_res = mysqli_query($conn, "SELECT d.code FROM employees e LEFT JOIN departments d ON d.id = e.department_id WHERE e.id = $user_id AND e.company_id = $company_id LIMIT 1");
    if ($dept_res && $dept_row = mysqli_fetch_assoc($dept_res)) {
        $dept_code = trim((string)($dept_row['code'] ?? ''));
    }

    $path = explorer_normalize_relative_path(trim((string)($_GET['path'] ?? ''), '/'));
    if ($path === null) {
        exit('Invalid path or permission denied.');
    }

    // Why: Employee backup ZIP is limited to the exact own Private/{username}_{employee_id} folder only.
    $safe_username = preg_replace('/[^a-zA-Z0-9_\-\.]/', '', $username);
    $user_private_dir = "{$safe_username}_{$user_id}";
    $allowed_private_path = 'Private/' . $user_private_dir;
    if ($path !== $allowed_private_path) {
        exit('Invalid path or permission denied.');
    }

    require_once ROOT_PATH . 'modules/explorer/explorer_vault_helpers.php';
    $vaultCheck = explorer_enforce_vault_for_private_path($path, $user_private_dir);
    if (!$vaultCheck['ok']) {
        exit($vaultCheck['error']);
    }

    $storage_root = ROOT_PATH . 'files/' . $company_id;
    $full = get_full_path($storage_root, $path, $user_id, $dept_code, $username);

    if (!$full || !is_dir($full)) exit("Invalid path or permission denied.");

    $zipName = "folder.zip";
    $zipPath = sys_get_temp_dir() . "/explorer_" . uniqid() . ".zip";

    $zip = new ZipArchive();
    if ($zip->open($zipPath, ZipArchive::CREATE) === TRUE) {
        $it = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($full, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );
        foreach ($it as $file) {
            $local = substr($file->getPathname(), strlen($full) + 1);
            if ($file->isDir()) $zip->addEmptyDir($local);
            else $zip->addFile($file->getPathname(), $local);
        }
        $zip->close();

        header("Content-Type: application/zip");
        header("Content-Disposition: attachment; filename=\"$zipName\"");
        readfile($zipPath);
        unlink($zipPath);
        exit;
    }
    exit("ZIP error");
}

// Why: Ensure user is logged in and company is selected (handled by config.php).
// Extra security: Re-verify company_id is present.
if (!isset($_SESSION['company_id'])) {
    if (defined('ITM_VERIFY_SKIP_ROUTER') && ITM_VERIFY_SKIP_ROUTER) {
        // Continue for verification
    } else {
        header('Content-Type: application/json');
        echo json_encode(['error' => 'No company selected.']);
        exit;
    }
}

// Why: Enforce CSRF protection for all state-changing API requests.
if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!defined('ITM_VERIFY_SKIP_ROUTER') || !ITM_VERIFY_SKIP_ROUTER) {
        itm_require_post_csrf();
    }
}

$company_id = (int)($_SESSION['company_id'] ?? 0);
$user_id = (int)($_SESSION['employee_id'] ?? 0);
$username = $_SESSION['username'] ?? 'unknown';
// Why: Sanitise username for filesystem safety to prevent path traversal or separator issues.
$safe_username = preg_replace('/[^a-zA-Z0-9_\-\.]/', '', $username);
$user_private_dir = "{$safe_username}_{$user_id}";

require_once ROOT_PATH . 'modules/explorer/explorer_storage_helpers.php';

// Why: Fetch user department code for access control.
$safe_dept_code = explorer_fetch_user_department_code($conn, $user_id, $company_id);

// Why: New storage root is /files/ instead of /modules/explorer/data/
$storage_root = ROOT_PATH . 'files/' . $company_id;
$trash_root = ROOT_PATH . 'files/' . $company_id . '/Trash';

// Why: Auto-create basic structure when missing; skip during verification-only includes.
if ($company_id > 0 && isset($conn) && $conn && !(defined('ITM_VERIFY_SKIP_ROUTER') && ITM_VERIFY_SKIP_ROUTER)) {
    explorer_ensure_tenant_storage_scaffold($conn, $company_id, $user_id, $username);
}

if (!function_exists('explorer_ensure_dir')) {
function explorer_ensure_dir($absolutePath) {
    return itm_ensure_files_storage_directory($absolutePath);
}
}

/**
 * Why: Detect content type from the temporary upload bytes (not the client filename).
 */
if (!function_exists('explorer_detect_upload_mime_type')) {
function explorer_detect_upload_mime_type($tmpName) {
    $tmpName = (string)$tmpName;
    if ($tmpName === '' || !is_file($tmpName)) {
        return '';
    }
    if (function_exists('finfo_open') && defined('FILEINFO_MIME_TYPE')) {
        $finfo = @finfo_open(FILEINFO_MIME_TYPE);
        if ($finfo !== false) {
            $mime = @finfo_file($finfo, $tmpName);
            @finfo_close($finfo);
            if (is_string($mime) && $mime !== '') {
                return strtolower(trim(explode(';', $mime, 2)[0]));
            }
        }
    }
    $imageInfo = @getimagesize($tmpName);
    if (is_array($imageInfo) && isset($imageInfo['mime']) && $imageInfo['mime'] !== '') {
        return strtolower((string)$imageInfo['mime']);
    }
    return '';
}
}

/**
 * Why: Allowed Explorer extensions mapped to acceptable detected MIME types.
 * Office Open XML packages often report as application/zip — accept that for docx/xlsx/pptx.
 */
if (!function_exists('explorer_allowed_mimes_for_extension')) {
function explorer_allowed_mimes_for_extension($ext) {
    $map = [
        'jpg' => ['image/jpeg'],
        'jpeg' => ['image/jpeg'],
        'png' => ['image/png'],
        'gif' => ['image/gif'],
        'webp' => ['image/webp'],
        'pdf' => ['application/pdf'],
        'txt' => ['text/plain'],
        'md' => ['text/plain', 'text/markdown', 'text/x-markdown'],
        'log' => ['text/plain'],
        'json' => ['application/json', 'text/plain', 'text/json'],
        'xml' => ['application/xml', 'text/xml', 'text/plain'],
        'csv' => ['text/csv', 'text/plain', 'application/csv', 'application/vnd.ms-excel'],
        'zip' => ['application/zip', 'application/x-zip-compressed', 'multipart/x-zip'],
        'doc' => ['application/msword', 'application/octet-stream'],
        'docx' => [
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/zip',
            'application/octet-stream',
        ],
        'xls' => ['application/vnd.ms-excel', 'application/octet-stream'],
        'xlsx' => [
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'application/zip',
            'application/octet-stream',
        ],
        'ppt' => ['application/vnd.ms-powerpoint', 'application/octet-stream'],
        'pptx' => [
            'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            'application/zip',
            'application/octet-stream',
        ],
    ];
    $ext = strtolower((string)$ext);
    return isset($map[$ext]) ? $map[$ext] : [];
}
}

/**
 * Why: Enforce whitelist extension + MIME + size before writing under files/{company_id}/.
 */
if (!function_exists('explorer_validate_upload_file')) {
function explorer_validate_upload_file($tmpName, $safeName, $fileSize, &$error) {
    $error = '';
    $maxSize = defined('EXPLORER_MAX_FILE_SIZE') ? (int)EXPLORER_MAX_FILE_SIZE : 20971520;
    if ((int)$fileSize <= 0 || (int)$fileSize > $maxSize) {
        $error = 'File exceeds the maximum allowed size (20MB).';
        return false;
    }
    $safeName = (string)$safeName;
    if ($safeName === '' || $safeName[0] === '.') {
        $error = 'Dotfiles and empty names are not allowed.';
        return false;
    }
    $ext = strtolower(pathinfo($safeName, PATHINFO_EXTENSION));
    $allowedMimes = explorer_allowed_mimes_for_extension($ext);
    if ($allowedMimes === []) {
        $error = 'Unsupported file extension.';
        return false;
    }
    $mime = explorer_detect_upload_mime_type($tmpName);
    if ($mime === '' || !in_array($mime, $allowedMimes, true)) {
        $error = 'File content type does not match the allowed type for this extension.';
        return false;
    }
    return true;
}
}

/**
 * Synchronises a filesystem change to the explorer database table.
 */
if (!function_exists('sync_db')) {
function sync_db($conn, $company_id, $user_id, $safe_dept_code, $path, $name, $type, $action = 'add') {
    if (!isset($conn) || !$conn) return;
    $path = trim((string)$path, '/');
    $name = basename((string)$name);

    if ($action === 'add') {
        $is_private = (str_starts_with($path, 'Private') || $path === 'Private') ? 1 : 0;

        // Find department_id from safe_dept_code
        $db_dept_id = null;
        if ($safe_dept_code !== '') {
            $stmt_d = mysqli_prepare($conn, "SELECT id FROM departments WHERE company_id = ? AND code = ? LIMIT 1");
            mysqli_stmt_bind_param($stmt_d, "is", $company_id, $safe_dept_code);
            mysqli_stmt_execute($stmt_d);
            $res_d = mysqli_stmt_get_result($stmt_d);
            if ($row_d = mysqli_fetch_assoc($res_d)) {
                $db_dept_id = (int)$row_d['id'];
            }
            mysqli_stmt_close($stmt_d);
        }

        $stmt = mysqli_prepare($conn, "INSERT INTO explorer (company_id, employee_id, department_id, folder_path, file_name, file_type, is_private, active, created_by, updated_by) VALUES (?, ?, ?, ?, ?, ?, ?, 1, ?, ?) ON DUPLICATE KEY UPDATE active = 1, deleted_by = NULL, deleted_at = NULL, updated_by = ?, updated_at = CURRENT_TIMESTAMP");
        mysqli_stmt_bind_param($stmt, "iiisssiiii", $company_id, $user_id, $db_dept_id, $path, $name, $type, $is_private, $user_id, $user_id, $user_id);
        mysqli_stmt_execute($stmt);
    } elseif ($action === 'delete') {
        $stmt = mysqli_prepare($conn, "UPDATE explorer SET active = 0, deleted_by = ?, deleted_at = CURRENT_TIMESTAMP WHERE company_id = ? AND folder_path = ? AND file_name = ?");
        mysqli_stmt_bind_param($stmt, "iiss", $user_id, $company_id, $path, $name);
        mysqli_stmt_execute($stmt);
    }
}
}

if (!function_exists('copyDir')) {
function copyDir($src, $dst) {
    if (!is_dir($src)) return false;
    explorer_ensure_dir($dst);
    $dir = opendir($src);
    while(false !== ($file = readdir($dir))) {
        if ($file == "." || $file == "..") continue;
        if (is_dir("$src/$file")) copyDir("$src/$file", "$dst/$file");
        else @copy("$src/$file", "$dst/$file");
    }
    closedir($dir);
    return true;
}
}

if (!function_exists('rrmdir')) {
function rrmdir($dir) {
    if (!is_dir($dir)) return;
    $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($it as $file) {
        if ($file->isDir()) @rmdir($file->getPathname());
        else @unlink($file->getPathname());
    }
    @rmdir($dir);
}
}

/* ---------------- ROUTER ---------------- */

/**
 * Why: Helper to safely extract and validate the 'item' parameter from POST.
 * Prevents path traversal by rejecting '..' and path separators.
 */
if (!function_exists('get_safe_post_item')) {
function get_safe_post_item() {
    $item = trim((string)($_POST['item'] ?? ''));
    if ($item === '..' || strpos($item, '/') !== false || strpos($item, '\\') !== false) {
        return null;
    }
    return basename($item);
}
}

/**
 * Why: Helper to safely extract and validate the 'name' parameter from POST.
 * Prevents path traversal by rejecting '..' and path separators.
 */
if (!function_exists('get_safe_post_name')) {
function get_safe_post_name() {
    $name = trim((string)($_POST['name'] ?? ''));
    if ($name === '..' || strpos($name, '/') !== false || strpos($name, '\\') !== false) {
        return null;
    }
    return basename($name);
}
}

if (defined('ITM_VERIFY_SKIP_ROUTER') && ITM_VERIFY_SKIP_ROUTER) {
    return;
}

// Why: In-process harness includes (explorer_human_test.php) must not poison browser HTML shell Content-Type.
if (!defined('ITM_EXPLORER_API_IN_PROCESS') || !ITM_EXPLORER_API_IN_PROCESS) {
    header("Content-Type: application/json; charset=utf-8");
}

$action = $_POST['action'] ?? '';
// Why: Normalize path to ensure trailing slashes do not bypass root protection guards.
$path = trim((string)($_POST['path'] ?? ''), '/');

require_once ROOT_PATH . 'modules/explorer/explorer_vault_helpers.php';
if ($action !== '') {
    $vaultPaths = [trim((string)$path, '/')];
    if (isset($_POST['src_path'])) {
        $vaultPaths[] = trim((string)$_POST['src_path'], '/');
    }
    if (isset($_POST['dest'])) {
        $vaultPaths[] = trim((string)$_POST['dest'], '/');
    }
    if (isset($_POST['scope_path'])) {
        $vaultPaths[] = trim((string)$_POST['scope_path'], '/');
    }
    foreach (array_unique(array_filter($vaultPaths, static function ($vaultPath) {
        return $vaultPath !== '';
    })) as $vaultPath) {
        $vaultCheck = explorer_enforce_vault_for_private_path($vaultPath, $user_private_dir);
        if (!$vaultCheck['ok']) {
            echo json_encode(['error' => $vaultCheck['error'], 'vault_locked' => 1]);
            exit;
        }
    }
}

switch ($action) {

/* ---------------- LIST ---------------- */
case "list":
    // Why: get_full_path already trims $path, but we keep the local $path trimmed for logic checks.
    $dir = get_full_path($storage_root, $path, $user_id, $safe_dept_code, $username);
    if (!$dir) { echo json_encode(['items' => []]); break; }

    $items = [];
    if (is_dir($dir)) {
        foreach (scandir($dir) as $f) {
            if ($f === "." || $f === ".." || $f === "Trash" || $f === "Recycle") continue;
            if (explorer_is_hidden_system_entry($f)) continue;
            // Why: At Departments root, hide other department code folders on disk.
            if ($path === 'Departments' && $safe_dept_code !== '' && $f !== $safe_dept_code) {
                continue;
            }

            $full = $dir . "/" . $f;
            $type = is_dir($full) ? "folder" : "file";
            $ext = strtolower(pathinfo($f, PATHINFO_EXTENSION));
            if ($ext === "zip") $type = "zip";
            if (in_array($ext, ["txt","md","log"])) $type = "txt";

            $items[] = [
                "name" => $f,
                "type" => $type,
                "size" => is_file($full) ? filesize($full) : 0,
                "mtime"=> filemtime($full)
            ];

            // Sync to DB on list (discovery)
            sync_db($conn, $company_id, $user_id, $safe_dept_code, $path, $f, $type);
        }
    }
    // Why: Home shows Trash only when this user has recoverable items (sidebar link always available).
    if ($path === '' && explorer_user_has_visible_trash_items($trash_root, $user_id, $safe_dept_code, $username)) {
        $items[] = [
            'name' => 'Trash',
            'type' => 'trash',
            'size' => 0,
            'mtime' => is_dir($trash_root) ? (int)filemtime($trash_root) : time(),
        ];
    }
    echo json_encode(["items" => $items]);
    break;

/* ---------------- OPEN ---------------- */
case "open":
    $dir = get_full_path($storage_root, $path, $user_id, $safe_dept_code, $username);
    $item = get_safe_post_item();
    if ($item === null) { echo json_encode(['preview' => 'unsupported', 'message' => 'Invalid item name.']); break; }
    $full = $dir . "/" . $item;

    if (!$dir || !is_file($full)) {
        echo json_encode([
            'preview' => 'unsupported',
            'message' => 'File not found.',
        ]);
        break;
    }

    $relPath = ($path !== '' ? $path . '/' : '') . $item;
    $relPath = str_replace('\\', '/', $relPath);
    $previewMode = explorer_resolve_preview_mode($item);

    if ($previewMode === 'image' || $previewMode === 'pdf') {
        echo json_encode([
            'preview' => $previewMode,
            'url' => 'file.php?path=' . rawurlencode($relPath),
        ]);
        break;
    }

    if ($previewMode === 'text') {
        $content = '';
        if (filesize($full) < 1024 * 1024) {
            $content = file_get_contents($full);
            if ($content !== '' && strpos($content, "\0") !== false) {
                $content = '';
            }
        }
        echo json_encode([
            'preview' => 'text',
            'content' => $content,
        ]);
        break;
    }

    echo json_encode([
        'preview' => 'unsupported',
        'message' => 'Preview is not available for this file type.',
    ]);
    break;

/* ---------------- CREATE FOLDER ---------------- */
case "createFolder":
    $dir = get_full_path($storage_root, $path, $user_id, $safe_dept_code, $username);

    // Why: Block folder creation directly in Home root, Private root, Departments root or Trash.
    if ($path === '' || $path === 'Private' || $path === 'Departments' || $path === 'Trash') {
        echo json_encode(["ok" => 0, "error" => "Folder creation is restricted in this location."]);
        break;
    }

    $name = get_safe_post_name() ?? 'New Folder';
    if ($dir && explorer_ensure_dir($dir . "/" . $name)) {
        sync_db($conn, $company_id, $user_id, $safe_dept_code, $path, $name, 'folder');
        echo json_encode(["ok" => 1]);
    } else {
        echo json_encode(["ok" => 0, "error" => "Unable to create folder."]);
    }
    break;

/* ---------------- DELETE ---------------- */
case "delete":
    $dir = get_full_path($storage_root, $path, $user_id, $safe_dept_code, $username);
    $item = get_safe_post_item();
    if ($item === null) { echo json_encode(["ok" => 0, "error" => "Invalid item name."]); break; }
    $src = $dir . "/" . $item;

    if (!$dir || !file_exists($src)) { echo json_encode(["ok" => 0]); break; }

    // Why: Restrict deletion of system folders and any item directly in sensitive roots.
    $is_restricted = ($path === '' && in_array($item, ['Common', 'Departments', 'Private', 'Trash']));
    $is_sensitive_root = ($path === 'Private' || $path === 'Departments');

    // Why: Protection logic for Trash when it is not empty.
    if ($path === '' && $item === 'Trash') {
        $has_files = false;
        if (is_dir($src)) {
            $fi = new FilesystemIterator($src, FilesystemIterator::SKIP_DOTS);
            foreach ($fi as $finfo) {
                if (!explorer_is_hidden_system_entry($finfo->getFilename())) {
                    $has_files = true;
                    break;
                }
            }
        }
        if ($has_files) {
            echo json_encode(["ok" => 0, "error" => "Trash cannot be deleted while it contains items."]);
            break;
        }
    }

    if ($is_restricted || $is_sensitive_root) {
        echo json_encode(["ok" => 0, "error" => "Deletion of this item is restricted."]);
        break;
    }

    // Why: Move to trash.
    $rel = trim($path ? "$path/$item" : $item, "/");
    $dst = $trash_root . "/" . $rel;
    explorer_ensure_dir(dirname($dst));

    if (@rename($src, $dst)) {
        sync_db($conn, $company_id, $user_id, $safe_dept_code, $path, $item, '', 'delete');
        echo json_encode(["ok" => 1]);
    } else {
        echo json_encode(["ok" => 0]);
    }
    break;

/* ---------------- RENAME ---------------- */
case "rename":
    $dir = get_full_path($storage_root, $path, $user_id, $safe_dept_code, $username);
    $item = get_safe_post_item();
    $name = get_safe_post_name();

    if (!$dir || $item === null || $name === null) { echo json_encode(["ok" => 0, "error" => "Invalid name."]); break; }

    // Why: Restrict renaming of system folders and any item directly in sensitive roots.
    $is_restricted = ($path === '' && in_array($item, ['Common', 'Departments', 'Private', 'Trash']));
    $is_sensitive_root = ($path === 'Private' || $path === 'Departments');

    if ($is_restricted || $is_sensitive_root) {
        echo json_encode(["ok" => 0, "error" => "Renaming of this item is restricted."]);
        break;
    }

    $src = $dir . "/" . $item;
    $dst = $dir . "/" . $name;

    if (@rename($src, $dst)) {
        sync_db($conn, $company_id, $user_id, $safe_dept_code, $path, $item, '', 'delete');
        $type = is_dir($dst) ? 'folder' : 'file';
        sync_db($conn, $company_id, $user_id, $safe_dept_code, $path, $name, $type, 'add');
        echo json_encode(["ok" => 1]);
    } else {
        echo json_encode(["ok" => 0]);
    }
    break;

/* ---------------- COPY ---------------- */
case "copy":
    // Why: Normalize source and destination to prevent root protection bypass with trailing slashes.
    $src_rel = trim((string)($_POST['src_path'] ?? $path), '/');
    $dest_rel = trim((string)($_POST['dest'] ?? $src_rel), '/');

    $dir = get_full_path($storage_root, $src_rel, $user_id, $safe_dept_code, $username);
    $targetDir = get_full_path($storage_root, $dest_rel, $user_id, $safe_dept_code, $username);

    $item = get_safe_post_item();
    if ($item === null) { echo json_encode(["ok" => 0, "error" => "Invalid item name."]); break; }
    $src = $dir . "/" . $item;

    if (!$dir || !$targetDir || !file_exists($src)) {
        echo json_encode(["ok" => 0, "error" => "Invalid source or destination."]);
        break;
    }

    // Why: Restrict zipping/copying of system folders and any item directly in sensitive roots.
    $is_sensitive_src = ($src_rel === '' && in_array($item, ['Common', 'Departments', 'Private', 'Trash']))
                        || $src_rel === 'Private' || $src_rel === 'Departments';

    if ($is_sensitive_src) {
        echo json_encode(["ok" => 0, "error" => "Copying of this item is restricted."]);
        break;
    }

    // Why: Copy must follow the same non-writable root policy as create/upload/move.
    if ($dest_rel === '' || $dest_rel === 'Private' || $dest_rel === 'Departments' || $dest_rel === 'Trash') {
        echo json_encode(["ok" => 0, "error" => "Items cannot be copied directly into Home, Private, Departments or Trash root."]);
        break;
    }

    $new_name = ($src_rel === $dest_rel) ? "copy_of_" . $item : $item;
    $dst = $targetDir . "/" . $new_name;

    $ok = false;
    if (is_dir($src)) $ok = copyDir($src, $dst);
    elseif (is_file($src)) $ok = @copy($src, $dst);

    if ($ok) {
        $type = is_dir($dst) ? 'folder' : 'file';
        sync_db($conn, $company_id, $user_id, $safe_dept_code, $dest_rel, $new_name, $type, 'add');
        echo json_encode(["ok" => 1]);
    } else {
        echo json_encode(["ok" => 0]);
    }
    break;

/* ---------------- MOVE ---------------- */
case "move":
    // Why: Normalize source and destination to prevent root protection bypass with trailing slashes.
    $src_rel = trim((string)($_POST['src_path'] ?? $path), '/');
    $dest_rel = trim((string)($_POST['dest'] ?? ''), '/');

    $dir = get_full_path($storage_root, $src_rel, $user_id, $safe_dept_code, $username);
    $targetDir = get_full_path($storage_root, $dest_rel, $user_id, $safe_dept_code, $username);

    $item = get_safe_post_item();
    if ($item === null) { echo json_encode(["ok" => 0, "error" => "Invalid item name."]); break; }

    if (!$dir || !$targetDir || !file_exists($dir . "/" . $item)) {
        echo json_encode(["ok" => 0, "error" => "Invalid source or destination."]);
        break;
    }

    // Why: Restrict moving of system folders and any item directly in sensitive roots.
    $is_restricted = ($src_rel === '' && in_array($item, ['Common', 'Departments', 'Private', 'Trash']));
    $is_sensitive_root = ($src_rel === 'Private' || $src_rel === 'Departments');

    if ($is_restricted || $is_sensitive_root) {
        echo json_encode(["ok" => 0, "error" => "Moving of this item is restricted."]);
        break;
    }

    // Why: Block moving items into top-level restricted folders.
    if ($dest_rel === '' || $dest_rel === 'Private' || $dest_rel === 'Departments' || $dest_rel === 'Trash') {
        echo json_encode(["ok" => 0, "error" => "Items cannot be moved directly into Home, Private, Departments or Trash root."]);
        break;
    }

    $src = $dir . "/" . $item;
    $dst = $targetDir . "/" . basename($item);

    if (@rename($src, $dst)) {
        sync_db($conn, $company_id, $user_id, $safe_dept_code, $src_rel, $item, '', 'delete');
        $type = is_dir($dst) ? 'folder' : 'file';
        sync_db($conn, $company_id, $user_id, $safe_dept_code, $dest_rel, $item, $type, 'add');
        echo json_encode(["ok" => 1]);
    } else {
        echo json_encode(["ok" => 0]);
    }
    break;

/* ---------------- ZIP ---------------- */
case "zip":
    $dir = get_full_path($storage_root, $path, $user_id, $safe_dept_code, $username);
    $item = get_safe_post_item();
    if ($item === null) { echo json_encode(["ok" => 0, "error" => "Invalid item name."]); break; }
    $src = $dir . "/" . $item;
    $zipFile = $dir . "/" . $item . ".zip";

    if (!$dir || !file_exists($src)) { echo json_encode(["ok" => 0]); break; }

    // Why: Restrict zipping of system folders and any item directly in sensitive roots.
    $is_restricted = ($path === '' && in_array($item, ['Common', 'Departments', 'Private', 'Trash']));
    $is_sensitive_root = ($path === 'Private' || $path === 'Departments');

    if ($is_restricted || $is_sensitive_root) {
        echo json_encode(["ok" => 0, "error" => "Zipping of this item is restricted."]);
        break;
    }

    $zip = new ZipArchive();
    if ($zip->open($zipFile, ZipArchive::CREATE | ZipArchive::OVERWRITE) === TRUE) {
        if (is_dir($src)) {
            $it = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($src, FilesystemIterator::SKIP_DOTS),
                RecursiveIteratorIterator::SELF_FIRST
            );
            foreach ($it as $file) {
                $local = substr($file->getPathname(), strlen($src) + 1);
                if ($file->isDir()) $zip->addEmptyDir($local);
                else $zip->addFile($file->getPathname(), $local);
            }
        } elseif (is_file($src)) {
            $zip->addFile($src, basename($src));
        }
        $zip->close();
        sync_db($conn, $company_id, $user_id, $safe_dept_code, $path, $item . ".zip", 'zip', 'add');
        echo json_encode(["ok" => 1]);
    } else {
        echo json_encode(["ok" => 0]);
    }
    break;

/* ---------------- UNZIP ---------------- */
case "unzip":
    $dir = get_full_path($storage_root, $path, $user_id, $safe_dept_code, $username);
    $item = get_safe_post_item();
    if ($item === null) { echo json_encode(["ok" => 0, "error" => "Invalid item name."]); break; }
    $src = $dir . "/" . $item;

    if (!$dir || !is_file($src)) { echo json_encode(["ok" => 0]); break; }

    $zip = new ZipArchive();
    if ($zip->open($src) === TRUE) {
        $extracted = explorer_extract_zip_safely($zip, $dir);
        $zip->close();
        if (!$extracted) {
            echo json_encode(["ok" => 0, "error" => "Unsafe archive entries blocked."]);
            break;
        }
        // Since we extracted multiple files, we'd need to sync all of them.
        // For simplicity, we can let the next 'list' operation handle the sync.
        echo json_encode(["ok" => 1]);
    } else {
        echo json_encode(["ok" => 0]);
    }
    break;

/* ---------------- UPLOAD ---------------- */
case "upload":
    $dir = get_full_path($storage_root, $path, $user_id, $safe_dept_code, $username);
    if (!$dir) { echo json_encode(["ok" => 0]); break; }

    // Why: Block upload directly in restricted top-level folders.
    if ($path === '' || $path === 'Private' || $path === 'Departments' || $path === 'Trash') {
        echo json_encode(["ok" => 0, "error" => "Upload is restricted in this location."]);
        break;
    }

    $uploadedCount = 0;
    $uploadErrors = [];
    if (!empty($_FILES['files']['name'])) {
        foreach ($_FILES['files']['name'] as $i => $name) {
            $tmp = $_FILES['files']['tmp_name'][$i] ?? '';
            $fileError = (int)($_FILES['files']['error'][$i] ?? UPLOAD_ERR_NO_FILE);
            $fileSize = (int)($_FILES['files']['size'][$i] ?? 0);
            if ($fileError === UPLOAD_ERR_NO_FILE || $tmp === '') {
                continue;
            }
            if ($fileError !== UPLOAD_ERR_OK) {
                $uploadErrors[] = 'Upload failed for ' . basename((string)$name) . '.';
                continue;
            }
            $safe_name = basename((string)$name);
            $fileValidateError = '';
            if (!explorer_validate_upload_file($tmp, $safe_name, $fileSize, $fileValidateError)) {
                $uploadErrors[] = $safe_name . ': ' . $fileValidateError;
                continue;
            }

            if (@move_uploaded_file($tmp, $dir . "/" . $safe_name)) {
                sync_db($conn, $company_id, $user_id, $safe_dept_code, $path, $safe_name, 'file');
                $uploadedCount++;
            } else {
                $uploadErrors[] = 'Could not store ' . $safe_name . '.';
            }
        }
    }
    $ok = ($uploadedCount > 0 && empty($uploadErrors)) ? 1 : (($uploadedCount > 0) ? 1 : 0);
    $payload = ["ok" => $ok, "uploaded" => $uploadedCount];
    if (!empty($uploadErrors)) {
        $payload["error"] = implode(' ', $uploadErrors);
    }
    if ($uploadedCount === 0 && empty($uploadErrors)) {
        $payload["ok"] = 0;
        $payload["error"] = "No files uploaded.";
    }
    echo json_encode($payload);
    break;

/* ---------------- DATE STRUCTURES ---------------- */
case "createYear":
    $dir = get_full_path($storage_root, $path, $user_id, $safe_dept_code, $username);
    if (!$dir || $path === '' || $path === 'Private' || $path === 'Departments' || $path === 'Trash') {
        echo json_encode(["ok" => 0, "error" => "Restricted location."]); break;
    }
    $cur = (int)date('Y');
    foreach ([$cur - 1, $cur, $cur + 1] as $y) {
        if (!is_dir("$dir/$y")) {
            explorer_ensure_dir("$dir/$y");
            sync_db($conn, $company_id, $user_id, $safe_dept_code, $path, (string)$y, 'folder');
        }
    }
    echo json_encode(["ok" => 1]);
    break;

case "createMonths":
    $dir = get_full_path($storage_root, $path, $user_id, $safe_dept_code, $username);
    if (!$dir || $path === '' || $path === 'Private' || $path === 'Departments' || $path === 'Trash') {
        echo json_encode(["ok" => 0, "error" => "Restricted location."]); break;
    }
    $months = [
        "01 - January", "02 - February", "03 - March", "04 - April",
        "05 - May", "06 - June", "07 - July", "08 - August",
        "09 - September", "10 - October", "11 - November", "12 - December"
    ];
    foreach ($months as $m) {
        if (!is_dir("$dir/$m")) {
            explorer_ensure_dir("$dir/$m");
            sync_db($conn, $company_id, $user_id, $safe_dept_code, $path, $m, 'folder');
        }
    }
    echo json_encode(["ok" => 1]);
    break;

case "createDays":
    $dir = get_full_path($storage_root, $path, $user_id, $safe_dept_code, $username);
    if (!$dir || $path === '' || $path === 'Private' || $path === 'Departments' || $path === 'Trash') {
        echo json_encode(["ok" => 0, "error" => "Restricted location."]); break;
    }

    $daysCount = 31;
    $folderName = basename($dir);
    $parentDir = dirname($dir);
    $parentName = basename($parentDir);

    $year = (int)date('Y');
    $month = (int)date('m');

    if (preg_match('/^(\d{2}) - /', $folderName, $matches)) {
        $month = (int)$matches[1];
        if (preg_match('/^\d{4}$/', $parentName)) {
            $year = (int)$parentName;
        }
        $daysCount = cal_days_in_month(CAL_GREGORIAN, $month, $year);
    }

    for ($i = 1; $i <= $daysCount; $i++) {
        $d = str_pad($i, 2, '0', STR_PAD_LEFT);
        if (!is_dir("$dir/$d")) {
            explorer_ensure_dir("$dir/$d");
            sync_db($conn, $company_id, $user_id, $safe_dept_code, $path, $d, 'folder');
        }
    }
    echo json_encode(["ok" => 1]);
    break;

case "createYearMonthDay":
    $dir = get_full_path($storage_root, $path, $user_id, $safe_dept_code, $username);
    if (!$dir || $path === '' || $path === 'Private' || $path === 'Departments' || $path === 'Trash') {
        echo json_encode(["ok" => 0, "error" => "Restricted location."]); break;
    }

    $y = date('Y');
    $m_val = date('m');
    $m_name = date('F');
    $m_folder = "$m_val - $m_name";
    $d = date('d');

    $full_path = "$dir/$y/$m_folder/$d";
    if (!is_dir($full_path)) {
        explorer_ensure_dir($full_path);
        sync_db($conn, $company_id, $user_id, $safe_dept_code, $path, $y, 'folder');
        sync_db($conn, $company_id, $user_id, $safe_dept_code, "$path/$y", $m_folder, 'folder');
        sync_db($conn, $company_id, $user_id, $safe_dept_code, "$path/$y/$m_folder", $d, 'folder');
    }
    echo json_encode(["ok" => 1]);
    break;

/* ---------------- TRASH BIN ---------------- */
case "listRecycle":
    echo json_encode([
        'items' => explorer_collect_visible_trash_items($trash_root, $user_id, $safe_dept_code, $username),
    ]);
    break;

case "restore":
    $item = trim(str_replace('\\', '/', (string)($_POST['item'] ?? '')), '/');
    if ($item === '' || strpos($item, '..') !== false) {
        echo json_encode(["ok" => 0, "error" => "Invalid item path."]);
        break;
    }

    // Why: Ensure user has permission to the item being restored.
    if (get_full_path($trash_root, $item, $user_id, $safe_dept_code, $username) === null) {
        echo json_encode(["ok" => 0, "error" => "Permission denied."]);
        break;
    }

    $src = $trash_root . "/" . $item;
    $dst = $storage_root . "/" . $item;

    explorer_ensure_dir(dirname($dst));
    if (@rename($src, $dst)) {
        $path = dirname($item);
        if ($path === '.') $path = '';
        $name = basename($item);
        $type = is_dir($dst) ? 'folder' : 'file';
        sync_db($conn, $company_id, $user_id, $safe_dept_code, $path, $name, $type, 'add');
        echo json_encode(["ok" => 1]);
    } else {
        echo json_encode(["ok" => 0]);
    }
    break;

case "emptyRecycle":
    if (is_dir($trash_root)) {
        $it = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($trash_root, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($it as $file) {
            $rel = substr($file->getPathname(), strlen($trash_root) + 1);
            $safe_rel = str_replace('\\', '/', $rel);
            if (get_full_path($trash_root, $safe_rel, $user_id, $safe_dept_code, $username) === null) {
                continue;
            }
            if ($file->isDir()) {
                @rmdir($file->getPathname());
            } else {
                @unlink($file->getPathname());
            }
        }
    }
    explorer_ensure_dir($trash_root);
    echo json_encode(["ok" => 1]);
    break;

case "create_share_session":
    require_once __DIR__ . '/explorer_share_helpers.php';
    itm_require_post_csrf();
    $scopePath = trim((string)($_POST['scope_path'] ?? ''), '/');
    if ($scopePath === '' && isset($_POST['path'])) {
        $scopePath = trim((string)$_POST['path'], '/');
    }
    $ownerUsername = (string)($username ?? '');
    $vaultUnlocked = explorer_vault_is_unlocked();
    $result = explorer_share_create_session(
        $conn,
        $company_id,
        $user_id,
        $ownerUsername,
        $scopePath,
        $safe_dept_code,
        $username,
        $vaultUnlocked,
        $storage_root,
        $user_private_dir
    );
    if (!$result['ok'] || empty($result['session'])) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => $result['error'] ?? 'Unable to create share session.'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        break;
    }
    $session = $result['session'];
    echo json_encode([
        'ok' => true,
        'share_code' => (string)$session['share_code'],
        'join_url' => explorer_share_build_join_url((string)$session['access_token']),
        'expires_at' => (string)$session['expires_at'],
        'ttl_seconds' => itm_qr_share_session_ttl_seconds(),
        'has_images' => true,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    break;

default:
    echo json_encode(["error" => "Unknown action"]);
}
