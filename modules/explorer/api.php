<?php
$base    = __DIR__ . "/data";
$recycle = __DIR__ . "/recycle_bin";

if (!is_dir($base))    mkdir($base, 0777, true);
if (!is_dir($recycle)) mkdir($recycle, 0777, true);

/* ---------------- ZIP DOWNLOAD ---------------- */
if (isset($_GET['downloadZip'])) {
    $path = $_GET['path'] ?? "";
    $full = realpath($base . ($path ? "/$path" : ""));

    if (!$full || strpos($full, realpath($base)) !== 0) exit("Invalid path");

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

header("Content-Type: application/json; charset=utf-8");

$action = $_POST['action'] ?? '';
$path   = $_POST['path']   ?? '';

/* ---------------- SAFE PATH ---------------- */
function safePath($base, $path) {
    $full = $base . ($path ? "/$path" : "");
    $real = realpath($full);

    if ($real && strpos($real, realpath($base)) === 0) {
        return $real;
    }

    return $full; // fallback for new folders
}

/* ---------------- ROUTER ---------------- */
switch ($action) {

/* ---------------- LIST ---------------- */
case "list":
    $dir = safePath($base, $path);
    $items = [];

    if (is_dir($dir)) {
        foreach (scandir($dir) as $f) {
            if ($f === "." || $f === "..") continue;

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
        }
    }

    echo json_encode(["items"=>$items]);
    break;

/* ---------------- OPEN ---------------- */
case "open":
    $dir = safePath($base, $path);
    $item = $_POST['item'] ?? '';
    $full = $dir . "/" . $item;

    $content = "";
    if (is_file($full) && filesize($full) < 1024*1024) {
        $content = file_get_contents($full);
    }

    echo json_encode(["content"=>$content]);
    break;

/* ---------------- CREATE FOLDER ---------------- */
case "createFolder":
    $dir = safePath($base, $path);
    $name = $_POST['name'] ?? 'New Folder';
    @mkdir($dir . "/" . $name, 0777, true);
    echo json_encode(["ok"=>1]);
    break;

/* ---------------- DELETE (MOVE TO RECYCLE) ---------------- */
case "delete":
    global $recycle;

    $dir = safePath($base, $path);
    $item = $_POST['item'] ?? '';
    $src  = $dir . "/" . $item;

    if (!file_exists($src)) { echo json_encode(["ok"=>0]); break; }

    $rel = trim($path ? "$path/$item" : $item, "/");
    $dst = $recycle . "/" . $rel;

    @mkdir(dirname($dst), 0777, true);
    $ok = @rename($src, $dst);

    echo json_encode(["ok"=>$ok?1:0]);
    break;

/* ---------------- RENAME ---------------- */
case "rename":
    $dir = safePath($base, $path);
    $item = $_POST['item'] ?? '';
    $name = $_POST['name'] ?? '';

    if (!$item || !$name) { echo json_encode(["ok"=>0]); break; }

    $src = $dir . "/" . $item;
    $dst = $dir . "/" . $name;

    $ok  = @rename($src, $dst);
    echo json_encode(["ok"=>$ok?1:0]);
    break;

/* ---------------- COPY ---------------- */
case "copy":
    $dir = safePath($base, $path);
    $item = $_POST['item'] ?? '';

    $src  = $dir . "/" . $item;
    $dst  = $dir . "/copy_of_" . $item;

    $ok = false;

    if (is_dir($src)) $ok = copyDir($src, $dst);
    elseif (is_file($src)) $ok = @copy($src, $dst);

    echo json_encode(["ok"=>$ok?1:0]);
    break;

/* ---------------- MOVE (CUT + MOVE TO) ---------------- */
case "move":
    $dir  = safePath($base, $path);
    $item = $_POST['item'] ?? '';
    $dest = $_POST['dest'] ?? '';

    $src = $dir . "/" . $item;

    if ($dest) {
        $targetDir = safePath($base, $dest);
    } else {
        $targetDir = $dir;
    }

    if (!file_exists($src)) {
        echo json_encode(["ok" => 0]);
        break;
    }

    @mkdir($targetDir, 0777, true);

    $dst = $targetDir . "/" . basename($item);

    $ok = @rename($src, $dst);

    echo json_encode(["ok" => $ok ? 1 : 0]);
    break;

/* ---------------- ZIP ---------------- */
case "zip":
    $dir = safePath($base, $path);
    $item = $_POST['item'] ?? '';

    $src  = $dir . "/" . $item;
    $zipFile = $dir . "/" . $item . ".zip";

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
        echo json_encode(["ok"=>1]);
    } else {
        echo json_encode(["ok"=>0]);
    }
    break;

/* ---------------- UNZIP ---------------- */
case "unzip":
    $dir = safePath($base, $path);
    $item = $_POST['item'] ?? '';

    $src = $dir . "/" . $item;

    if (!is_file($src)) { echo json_encode(["ok"=>0]); break; }

    $zip = new ZipArchive();

    if ($zip->open($src) === TRUE) {
        $zip->extractTo($dir);
        $zip->close();
        echo json_encode(["ok"=>1]);
    } else {
        echo json_encode(["ok"=>0]);
    }
    break;

/* ---------------- UPLOAD ---------------- */
case "upload":
    $dir = safePath($base, $path);

    if (!empty($_FILES['files']['name'])) {
        foreach ($_FILES['files']['name'] as $i => $name) {
            $tmp = $_FILES['files']['tmp_name'][$i];
            if (!$tmp) continue;
            @move_uploaded_file($tmp, $dir . "/" . basename($name));
        }
    }

    echo json_encode(["ok"=>1]);
    break;

/* ---------------- DATE STRUCTURES ---------------- */
case "createYearMonthDay":
    $dir = safePath($base, $path);
    @mkdir("$dir/" . date("Y") . "/" . date("m") . "/" . date("d"), 0777, true);
    echo json_encode(["ok"=>1]);
    break;

case "createYears":
    $dir = safePath($base, $path);
    $y = date("Y");
    for ($i = $y-5; $i <= $y+1; $i++) @mkdir("$dir/$i", 0777, true);
    echo json_encode(["ok"=>1]);
    break;

case "createMonths":
    $dir = safePath($base, $path);
    for ($m=1; $m<=12; $m++) @mkdir("$dir/" . str_pad($m,2,"0",STR_PAD_LEFT), 0777, true);
    echo json_encode(["ok"=>1]);
    break;

case "createDays":
    $dir = safePath($base, $path);

    // Detectar ano e mês a partir do path
    $parts = explode("/", trim($path, "/"));
    $year  = intval($parts[count($parts)-2] ?? date("Y"));
    $month = intval($parts[count($parts)-1] ?? date("m"));

    // Número correto de dias no mês
    $days = cal_days_in_month(CAL_GREGORIAN, $month, $year);

    for ($d = 1; $d <= $days; $d++) {
        $dd = str_pad($d, 2, "0", STR_PAD_LEFT);
        @mkdir("$dir/$dd", 0777, true);
    }

    echo json_encode(["ok" => 1]);
    break;


/* ---------------- RECYCLE BIN ---------------- */
case "listRecycle":
    global $recycle;

    $items = [];
    $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($recycle, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );

    foreach ($it as $file) {
        $rel = substr($file->getPathname(), strlen($recycle) + 1);
        $items[] = [
            "name" => $rel,
            "type" => $file->isDir() ? "folder" : "file"
        ];
    }

    echo json_encode(["items"=>$items]);
    break;

case "restore":
    global $recycle, $base;

    $item = $_POST['item'] ?? '';
    $src  = $recycle . "/" . $item;
    $dst  = $base    . "/" . $item;

    @mkdir(dirname($dst), 0777, true);
    $ok = @rename($src, $dst);

    echo json_encode(["ok"=>$ok?1:0]);
    break;

case "emptyRecycle":
    global $recycle;

    rrmdir($recycle);
    @mkdir($recycle, 0777, true);

    echo json_encode(["ok"=>1]);
    break;

default:
    echo json_encode(["error"=>"Unknown action"]);
}

/* ---------------- HELPERS ---------------- */
function copyDir($src, $dst) {
    if (!is_dir($src)) return false;

    @mkdir($dst, 0777, true);

    $dir = opendir($src);
    while(false !== ($file = readdir($dir))) {
        if ($file == "." || $file == "..") continue;

        if (is_dir("$src/$file")) {
            copyDir("$src/$file", "$dst/$file");
        } else {
            @copy("$src/$file", "$dst/$file");
        }
    }
    closedir($dir);

    return true;
}

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
