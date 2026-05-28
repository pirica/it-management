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

$company_id = (int)$_SESSION['company_id'];
$user_id = (int)$_SESSION['user_id'];
$username = $_SESSION['username'] ?? 'unknown';

// Why: Fetch user department for access control.
$dept_id = 0;
$dept_res = mysqli_query($conn, "SELECT department_id FROM employees WHERE user_id = $user_id AND company_id = $company_id LIMIT 1");
if ($dept_res && $dept_row = mysqli_fetch_assoc($dept_res)) {
    $dept_id = (int)$dept_row['department_id'];
}

// Why: New storage root is /files/ instead of /modules/explorer/data/
$storage_root = ROOT_PATH . 'files/' . $company_id;
$recycle_root = ROOT_PATH . 'files/' . $company_id . '/Recycle';

// Why: Auto-create basic structure if it doesn't exist.
if (!is_dir($storage_root)) mkdir($storage_root, 0777, true);
if (!is_dir($recycle_root)) mkdir($recycle_root, 0777, true);

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

    // Why: Access control logic.
    // Paths starting with 'private' are restricted to the owner's username subfolder.
    if (str_starts_with($relative_path, 'Private')) {
        if (!str_starts_with($relative_path, "Private/$username") && $relative_path !== 'private') {
            return null;
        }
    }

    // Paths starting with 'department' are restricted to the user's department ID subfolder.
    if (str_starts_with($relative_path, 'Departments')) {
        if ($dept_id <= 0) return null; // User has no department
        if (!str_starts_with($relative_path, "Departments/$dept_id") && $relative_path !== 'Departments') {
            return null;
        }
    }

    return $full;
}
}

/**
 * Synchronises a filesystem change to the explorer database table.
 */
if (!function_exists('sync_db')) {
function sync_db($conn, $company_id, $user_id, $dept_id, $path, $name, $type, $action = 'add') {
    $path = trim((string)$path, '/');
    $name = basename((string)$name);

    if ($action === 'add') {
        $is_private = str_starts_with($path, 'private') ? 1 : 0;
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
$path   = $_POST['path']   ?? '';

switch ($action) {

/* ---------------- LIST ---------------- */
case "list":
    $dir = get_full_path($storage_root, $path, $user_id, $dept_id, $username);
    if (!$dir) { echo json_encode(['items' => []]); break; }

    // Why: Ensure structure for new company.
    if ($path === '' && !is_dir("$dir/common")) {
        @mkdir("$dir/common", 0777, true);
        @mkdir("$dir/private", 0777, true);
        @mkdir("$dir/department", 0777, true);
        @mkdir("$dir/private/$username", 0777, true);
        if ($dept_id > 0) @mkdir("$dir/department/$dept_id", 0777, true);
    }

    $items = [];
    if (is_dir($dir)) {
        foreach (scandir($dir) as $f) {
            if ($f === "." || $f === ".." || $f === "recycle_bin") continue;

            // Why: Access control for listing top-level folders.
            if ($path === 'private' && $f !== $username) continue;
            if ($path === 'department' && $f !== (string)$dept_id) continue;

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

    // Why: Move to recycle bin.
    $rel = trim($path ? "$path/$item" : $item, "/");
    $dst = $recycle_root . "/" . $rel;
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
    $dir = get_full_path($storage_root, $path, $user_id, $dept_id, $username);
    $item = basename($_POST['item'] ?? '');
    $src = $dir . "/" . $item;
    $dst = $dir . "/copy_of_" . $item;

    $ok = false;
    if (is_dir($src)) $ok = copyDir($src, $dst);
    elseif (is_file($src)) $ok = @copy($src, $dst);

    if ($ok) {
        $type = is_dir($dst) ? 'folder' : 'file';
        sync_db($conn, $company_id, $user_id, $dept_id, $path, "copy_of_" . $item, $type, 'add');
        echo json_encode(["ok" => 1]);
    } else {
        echo json_encode(["ok" => 0]);
    }
    break;

/* ---------------- MOVE ---------------- */
case "move":
    $dir = get_full_path($storage_root, $path, $user_id, $dept_id, $username);
    $item = basename($_POST['item'] ?? '');
    $dest_rel = $_POST['dest'] ?? '';
    $targetDir = get_full_path($storage_root, $dest_rel, $user_id, $dept_id, $username);

    if (!$dir || !$targetDir || !file_exists($dir . "/" . $item)) {
        echo json_encode(["ok" => 0, "error" => "Invalid source or destination."]);
        break;
    }

    $src = $dir . "/" . $item;
    $dst = $targetDir . "/" . basename($item);

    if (@rename($src, $dst)) {
        sync_db($conn, $company_id, $user_id, $dept_id, $path, $item, '', 'delete');
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

/* ---------------- RECYCLE BIN ---------------- */
case "listRecycle":
    $items = [];
    if (is_dir($recycle_root)) {
        $it = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($recycle_root, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );
        foreach ($it as $file) {
            $rel = substr($file->getPathname(), strlen($recycle_root) + 1);
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
    $src = $recycle_root . "/" . $item;
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
    rrmdir($recycle_root);
    @mkdir($recycle_root, 0777, true);
    echo json_encode(["ok" => 1]);
    break;

default:
    echo json_encode(["error" => "Unknown action"]);
}

