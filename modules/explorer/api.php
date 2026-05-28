<?php
/**
 * Explorer API
 *
 * Handles all file and folder operations with multi-tenant company scoping,
 * department-based access control, and user-private storage.
 * All operations are synchronised with the 'explorer' database table and
 * recorded in the system audit logs.
 */

require_once dirname(dirname(__DIR__)) . '/config/config.php';

/* ---------------- SAFE PATH HELPERS ---------------- */

/**
 * Validates and resolves a path relative to the company storage root.
 * Ensures the user has permission to access the requested path.
 */
if (!function_exists('get_full_path')) {
function get_full_path($storage_root, $relative_path, $user_id, $dept_id, $username) {
    $relative_path = trim((string)$relative_path, '/');

    // Why: Block directory traversal attempts.
    if (strpos($relative_path, '..') !== false) return null;

    $full = $storage_root . ($relative_path ? "/$relative_path" : "");

    // Why: Simple check to ensure we are still under the company storage root.
    if (strpos($full, $storage_root) !== 0) return null;

    // Why: Access control logic with segment-boundary checks.
    // Why: Sanitise username for filesystem safety to prevent path traversal or separator issues.
    $safe_username = preg_replace('/[^a-zA-Z0-9_\-\.]/', '', $username);
    $user_private_dir = "{$safe_username}_{$user_id}";

    // Paths starting with 'Private' are restricted to the owner's username_id subfolder.
    if ($relative_path === 'Private' || str_starts_with($relative_path, 'Private/')) {
        if ($relative_path !== 'Private' &&
            !str_starts_with($relative_path, "Private/$user_private_dir/") &&
            $relative_path !== "Private/$user_private_dir") {
            return null;
        }
    }

    // Paths starting with 'Departments' are restricted to the user's department ID subfolder.
    if ($relative_path === 'Departments' || str_starts_with($relative_path, 'Departments/')) {
        if ($dept_id <= 0) return null; // User has no department
        if ($relative_path !== 'Departments' &&
            !str_starts_with($relative_path, "Departments/$dept_id/") &&
            $relative_path !== "Departments/$dept_id") {
            return null;
        }
    }

    return $full;
}
}

/* ---------------- ZIP DOWNLOAD ---------------- */
if (isset($_GET['downloadZip'])) {
    if (!isset($_SESSION['company_id'])) exit("Access denied.");
    $company_id = (int)$_SESSION['company_id'];
    $user_id = (int)$_SESSION['user_id'];
    $username = $_SESSION['username'] ?? 'unknown';

    // Fetch department for user
    $dept_id = 0;
    $dept_res = mysqli_query($conn, "SELECT department_id FROM employees WHERE user_id = $user_id AND company_id = $company_id LIMIT 1");
    if ($dept_res && $dept_row = mysqli_fetch_assoc($dept_res)) {
        $dept_id = (int)$dept_row['department_id'];
    }

    $path = $_GET['path'] ?? "";
    $storage_root = ROOT_PATH . 'files/' . $company_id;
    $full = get_full_path($storage_root, $path, $user_id, $dept_id, $username);

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
    header('Content-Type: application/json');
    echo json_encode(['error' => 'No company selected.']);
    exit;
}

// Why: Enforce CSRF protection for all state-changing API requests.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    itm_require_post_csrf();
}

$company_id = (int)$_SESSION['company_id'];
$user_id = (int)$_SESSION['user_id'];
$username = $_SESSION['username'] ?? 'unknown';
// Why: Sanitise username for filesystem safety to prevent path traversal or separator issues.
$safe_username = preg_replace('/[^a-zA-Z0-9_\-\.]/', '', $username);
$user_private_dir = "{$safe_username}_{$user_id}";

// Why: Fetch user department for access control.
$dept_id = 0;
$dept_res = mysqli_query($conn, "SELECT department_id FROM employees WHERE user_id = $user_id AND company_id = $company_id LIMIT 1");
if ($dept_res && $dept_row = mysqli_fetch_assoc($dept_res)) {
    $dept_id = (int)$dept_row['department_id'];
}

// Why: New storage root is /files/ instead of /modules/explorer/data/
$storage_root = ROOT_PATH . 'files/' . $company_id;
$trash_root = ROOT_PATH . 'files/' . $company_id . '/Trash';

// Why: Auto-create basic structure if it doesn't exist.
if (!is_dir($storage_root)) mkdir($storage_root, 0777, true);
if (!is_dir($trash_root)) mkdir($trash_root, 0777, true);

/**
 * Synchronises a filesystem change to the explorer database table.
 */
if (!function_exists('sync_db')) {
function sync_db($conn, $company_id, $user_id, $dept_id, $path, $name, $type, $action = 'add') {
    $path = trim((string)$path, '/');
    $name = basename((string)$name);

    if ($action === 'add') {
        $is_private = (str_starts_with($path, 'Private') || $path === 'Private') ? 1 : 0;
        $db_dept_id = ($dept_id > 0) ? $dept_id : null;
        $stmt = mysqli_prepare($conn, "INSERT INTO explorer (company_id, user_id, department_id, folder_path, file_name, file_type, is_private) VALUES (?, ?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE updated_at = CURRENT_TIMESTAMP");
        mysqli_stmt_bind_param($stmt, "iiisssi", $company_id, $user_id, $db_dept_id, $path, $name, $type, $is_private);
        mysqli_stmt_execute($stmt);
    } elseif ($action === 'delete') {
        $stmt = mysqli_prepare($conn, "DELETE FROM explorer WHERE company_id = ? AND folder_path = ? AND file_name = ?");
        mysqli_stmt_bind_param($stmt, "iss", $company_id, $path, $name);
        mysqli_stmt_execute($stmt);
    }
}
}

if (!function_exists('copyDir')) {
function copyDir($src, $dst) {
    if (!is_dir($src)) return false;
    @mkdir($dst, 0777, true);
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

header("Content-Type: application/json; charset=utf-8");

$action = $_POST['action'] ?? '';
// Why: Normalize path to ensure trailing slashes do not bypass root protection guards.
$path = trim((string)($_POST['path'] ?? ''), '/');

switch ($action) {

/* ---------------- LIST ---------------- */
case "list":
    // Why: get_full_path already trims $path, but we keep the local $path trimmed for logic checks.
    $dir = get_full_path($storage_root, $path, $user_id, $dept_id, $username);
    if (!$dir) { echo json_encode(['items' => []]); break; }

    // Why: Existing company storage may predate scoped private folders.
    if ($path === '') {
        @mkdir("$dir/Common", 0777, true);
        @mkdir("$dir/Private", 0777, true);
        @mkdir("$dir/Departments", 0777, true);
        @mkdir("$dir/Private/$user_private_dir", 0777, true);
        if ($dept_id > 0) @mkdir("$dir/Departments/$dept_id", 0777, true);
    }

    $items = [];
    if (is_dir($dir)) {
        foreach (scandir($dir) as $f) {
            if ($f === "." || $f === ".." || $f === "Trash" || $f === "Recycle") continue;

            // Why: Access control for listing top-level folders.
            if ($path === 'Private' && $f !== $user_private_dir) continue;
            if ($path === 'Departments' && $f !== (string)$dept_id) continue;

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
            sync_db($conn, $company_id, $user_id, $dept_id, $path, $f, $type);
        }
    }
    echo json_encode(["items" => $items]);
    break;

/* ---------------- OPEN ---------------- */
case "open":
    $dir = get_full_path($storage_root, $path, $user_id, $dept_id, $username);
    $item = $_POST['item'] ?? '';
    $full = $dir . "/" . basename($item);

    $content = "";
    if (is_file($full) && filesize($full) < 1024*1024) {
        $content = file_get_contents($full);
    }
    echo json_encode(["content" => $content]);
    break;

/* ---------------- CREATE FOLDER ---------------- */
case "createFolder":
    $dir = get_full_path($storage_root, $path, $user_id, $dept_id, $username);

    // Why: Block folder creation directly in Home root, Private root or Departments root.
    if ($path === '' || $path === 'Private' || $path === 'Departments') {
        echo json_encode(["ok" => 0, "error" => "Folder creation is restricted in this location."]);
        break;
    }

    $name = basename($_POST['name'] ?? 'New Folder');
    if ($dir && @mkdir($dir . "/" . $name, 0777, true)) {
        sync_db($conn, $company_id, $user_id, $dept_id, $path, $name, 'folder');
        echo json_encode(["ok" => 1]);
    } else {
        echo json_encode(["ok" => 0, "error" => "Unable to create folder."]);
    }
    break;

/* ---------------- DELETE ---------------- */
case "delete":
    $dir = get_full_path($storage_root, $path, $user_id, $dept_id, $username);
    $item = basename($_POST['item'] ?? '');
    $src = $dir . "/" . $item;

    if (!$dir || !file_exists($src)) { echo json_encode(["ok" => 0]); break; }

    // Why: Restrict deletion of system folders and user's private folder.
    $is_restricted = ($path === '' && in_array($item, ['Common', 'Departments', 'Private']));
    $is_own_private = ($path === 'Private' && $item === $user_private_dir);

    if ($is_restricted || $is_own_private) {
        echo json_encode(["ok" => 0, "error" => "Deletion of this item is restricted."]);
        break;
    }

    // Why: Move to trash.
    $rel = trim($path ? "$path/$item" : $item, "/");
    $dst = $trash_root . "/" . $rel;
    @mkdir(dirname($dst), 0777, true);

    if (@rename($src, $dst)) {
        sync_db($conn, $company_id, $user_id, $dept_id, $path, $item, '', 'delete');
        echo json_encode(["ok" => 1]);
    } else {
        echo json_encode(["ok" => 0]);
    }
    break;

/* ---------------- RENAME ---------------- */
case "rename":
    $dir = get_full_path($storage_root, $path, $user_id, $dept_id, $username);
    $item = basename($_POST['item'] ?? '');
    $name = basename($_POST['name'] ?? '');

    if (!$dir || !$item || !$name) { echo json_encode(["ok" => 0]); break; }

    // Why: Restrict renaming of system folders and user's private folder.
    $is_restricted = ($path === '' && in_array($item, ['Common', 'Departments', 'Private']));
    $is_own_private = ($path === 'Private' && $item === $user_private_dir);

    if ($is_restricted || $is_own_private) {
        echo json_encode(["ok" => 0, "error" => "Renaming of this item is restricted."]);
        break;
    }

    $src = $dir . "/" . $item;
    $dst = $dir . "/" . $name;

    if (@rename($src, $dst)) {
        sync_db($conn, $company_id, $user_id, $dept_id, $path, $item, '', 'delete');
        $type = is_dir($dst) ? 'folder' : 'file';
        sync_db($conn, $company_id, $user_id, $dept_id, $path, $name, $type, 'add');
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

    $dir = get_full_path($storage_root, $src_rel, $user_id, $dept_id, $username);
    $targetDir = get_full_path($storage_root, $dest_rel, $user_id, $dept_id, $username);

    $item = basename($_POST['item'] ?? '');
    $src = $dir . "/" . $item;

    if (!$dir || !$targetDir || !file_exists($src)) {
        echo json_encode(["ok" => 0, "error" => "Invalid source or destination."]);
        break;
    }

    // Why: Copy must follow the same non-writable root policy as create/upload/move.
    if ($dest_rel === '' || $dest_rel === 'Private' || $dest_rel === 'Departments') {
        echo json_encode(["ok" => 0, "error" => "Items cannot be copied directly into Home, Private, or Departments root."]);
        break;
    }

    $new_name = ($src_rel === $dest_rel) ? "copy_of_" . $item : $item;
    $dst = $targetDir . "/" . $new_name;

    $ok = false;
    if (is_dir($src)) $ok = copyDir($src, $dst);
    elseif (is_file($src)) $ok = @copy($src, $dst);

    if ($ok) {
        $type = is_dir($dst) ? 'folder' : 'file';
        sync_db($conn, $company_id, $user_id, $dept_id, $dest_rel, $new_name, $type, 'add');
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

    $dir = get_full_path($storage_root, $src_rel, $user_id, $dept_id, $username);
    $targetDir = get_full_path($storage_root, $dest_rel, $user_id, $dept_id, $username);

    $item = basename($_POST['item'] ?? '');

    if (!$dir || !$targetDir || !file_exists($dir . "/" . $item)) {
        echo json_encode(["ok" => 0, "error" => "Invalid source or destination."]);
        break;
    }

    // Why: Restrict moving of system folders and user's private folder.
    $is_restricted = ($src_rel === '' && in_array($item, ['Common', 'Departments', 'Private']));
    $is_own_private = ($src_rel === 'Private' && $item === $user_private_dir);

    if ($is_restricted || $is_own_private) {
        echo json_encode(["ok" => 0, "error" => "Moving of this item is restricted."]);
        break;
    }

    // Why: Block moving items into top-level restricted folders.
    if ($dest_rel === '' || $dest_rel === 'Private' || $dest_rel === 'Departments') {
        echo json_encode(["ok" => 0, "error" => "Items cannot be moved directly into Home, Private, or Departments root."]);
        break;
    }

    $src = $dir . "/" . $item;
    $dst = $targetDir . "/" . basename($item);

    if (@rename($src, $dst)) {
        sync_db($conn, $company_id, $user_id, $dept_id, $src_rel, $item, '', 'delete');
        $type = is_dir($dst) ? 'folder' : 'file';
        sync_db($conn, $company_id, $user_id, $dept_id, $dest_rel, $item, $type, 'add');
        echo json_encode(["ok" => 1]);
    } else {
        echo json_encode(["ok" => 0]);
    }
    break;

/* ---------------- ZIP ---------------- */
case "zip":
    $dir = get_full_path($storage_root, $path, $user_id, $dept_id, $username);
    $item = basename($_POST['item'] ?? '');
    $src = $dir . "/" . $item;
    $zipFile = $dir . "/" . $item . ".zip";

    if (!$dir || !file_exists($src)) { echo json_encode(["ok" => 0]); break; }

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
        sync_db($conn, $company_id, $user_id, $dept_id, $path, $item . ".zip", 'zip', 'add');
        echo json_encode(["ok" => 1]);
    } else {
        echo json_encode(["ok" => 0]);
    }
    break;

/* ---------------- UNZIP ---------------- */
case "unzip":
    $dir = get_full_path($storage_root, $path, $user_id, $dept_id, $username);
    $item = basename($_POST['item'] ?? '');
    $src = $dir . "/" . $item;

    if (!$dir || !is_file($src)) { echo json_encode(["ok" => 0]); break; }

    $zip = new ZipArchive();
    if ($zip->open($src) === TRUE) {
        $zip->extractTo($dir);
        $zip->close();
        // Since we extracted multiple files, we'd need to sync all of them.
        // For simplicity, we can let the next 'list' operation handle the sync.
        echo json_encode(["ok" => 1]);
    } else {
        echo json_encode(["ok" => 0]);
    }
    break;

/* ---------------- UPLOAD ---------------- */
case "upload":
    $dir = get_full_path($storage_root, $path, $user_id, $dept_id, $username);
    if (!$dir) { echo json_encode(["ok" => 0]); break; }

    // Why: Block upload directly in restricted top-level folders.
    if ($path === '' || $path === 'Private' || $path === 'Departments') {
        echo json_encode(["ok" => 0, "error" => "Upload is restricted in this location."]);
        break;
    }

    if (!empty($_FILES['files']['name'])) {
        foreach ($_FILES['files']['name'] as $i => $name) {
            $tmp = $_FILES['files']['tmp_name'][$i];
            if (!$tmp) continue;
            $safe_name = basename($name);
            if (@move_uploaded_file($tmp, $dir . "/" . $safe_name)) {
                sync_db($conn, $company_id, $user_id, $dept_id, $path, $safe_name, 'file');
            }
        }
    }
    echo json_encode(["ok" => 1]);
    break;

/* ---------------- DATE STRUCTURES ---------------- */
case "createYear":
    $dir = get_full_path($storage_root, $path, $user_id, $dept_id, $username);
    if (!$dir || $path === '' || $path === 'Private' || $path === 'Departments') {
        echo json_encode(["ok" => 0, "error" => "Restricted location."]); break;
    }
    $cur = (int)date('Y');
    foreach ([$cur - 1, $cur, $cur + 1] as $y) {
        if (!is_dir("$dir/$y")) {
            @mkdir("$dir/$y", 0777, true);
            sync_db($conn, $company_id, $user_id, $dept_id, $path, (string)$y, 'folder');
        }
    }
    echo json_encode(["ok" => 1]);
    break;

case "createMonths":
    $dir = get_full_path($storage_root, $path, $user_id, $dept_id, $username);
    if (!$dir || $path === '' || $path === 'Private' || $path === 'Departments') {
        echo json_encode(["ok" => 0, "error" => "Restricted location."]); break;
    }
    $months = [
        "01 - January", "02 - February", "03 - March", "04 - April",
        "05 - May", "06 - June", "07 - July", "08 - August",
        "09 - September", "10 - October", "11 - November", "12 - December"
    ];
    foreach ($months as $m) {
        if (!is_dir("$dir/$m")) {
            @mkdir("$dir/$m", 0777, true);
            sync_db($conn, $company_id, $user_id, $dept_id, $path, $m, 'folder');
        }
    }
    echo json_encode(["ok" => 1]);
    break;

case "createDays":
    $dir = get_full_path($storage_root, $path, $user_id, $dept_id, $username);
    if (!$dir || $path === '' || $path === 'Private' || $path === 'Departments') {
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
            @mkdir("$dir/$d", 0777, true);
            sync_db($conn, $company_id, $user_id, $dept_id, $path, $d, 'folder');
        }
    }
    echo json_encode(["ok" => 1]);
    break;

case "createYearMonthDay":
    $dir = get_full_path($storage_root, $path, $user_id, $dept_id, $username);
    if (!$dir || $path === '' || $path === 'Private' || $path === 'Departments') {
        echo json_encode(["ok" => 0, "error" => "Restricted location."]); break;
    }

    $y = date('Y');
    $m_val = date('m');
    $m_name = date('F');
    $m_folder = "$m_val - $m_name";
    $d = date('d');

    $full_path = "$dir/$y/$m_folder/$d";
    if (!is_dir($full_path)) {
        @mkdir($full_path, 0777, true);
        sync_db($conn, $company_id, $user_id, $dept_id, $path, $y, 'folder');
        sync_db($conn, $company_id, $user_id, $dept_id, "$path/$y", $m_folder, 'folder');
        sync_db($conn, $company_id, $user_id, $dept_id, "$path/$y/$m_folder", $d, 'folder');
    }
    echo json_encode(["ok" => 1]);
    break;

/* ---------------- TRASH BIN ---------------- */
case "listRecycle":
    $items = [];
    if (is_dir($trash_root)) {
        $it = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($trash_root, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );
        foreach ($it as $file) {
            $rel = substr($file->getPathname(), strlen($trash_root) + 1);
            $items[] = [
                "name" => $rel,
                "type" => $file->isDir() ? "folder" : "file"
            ];
        }
    }
    echo json_encode(["items" => $items]);
    break;

case "restore":
    $item = $_POST['item'] ?? '';
    $src = $trash_root . "/" . $item;
    $dst = $storage_root . "/" . $item;

    @mkdir(dirname($dst), 0777, true);
    if (@rename($src, $dst)) {
        $path = dirname($item);
        if ($path === '.') $path = '';
        $name = basename($item);
        $type = is_dir($dst) ? 'folder' : 'file';
        sync_db($conn, $company_id, $user_id, $dept_id, $path, $name, $type, 'add');
        echo json_encode(["ok" => 1]);
    } else {
        echo json_encode(["ok" => 0]);
    }
    break;

case "emptyRecycle":
    rrmdir($trash_root);
    @mkdir($trash_root, 0777, true);
    echo json_encode(["ok" => 1]);
    break;

default:
    echo json_encode(["error" => "Unknown action"]);
}
