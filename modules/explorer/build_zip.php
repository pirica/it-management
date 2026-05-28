<?php

/* ---------------------------------------------------------
   CONFIG
--------------------------------------------------------- */

$zipName = "explorer_ready.zip";
$tmp = __DIR__ . "/tmp_build";

/* ---------------------------------------------------------
   CLEANUP TEMPORÁRIO
--------------------------------------------------------- */

if (is_dir($tmp)) {
    function rrmdir_build($d){
        foreach(array_diff(scandir($d),['.','..']) as $i){
            $p="$d/$i";
            is_dir($p)?rrmdir_build($p):unlink($p);
        }
        rmdir($d);
    }
    rrmdir_build($tmp);
}
mkdir($tmp);

/* ---------------------------------------------------------
   HELPERS
--------------------------------------------------------- */

function copyFileSafe($src, $dst) {
    $dir = dirname($dst);
    if (!is_dir($dir)) mkdir($dir, 0777, true);
    if (!file_exists($src)) {
        file_put_contents($dst, "/* MISSING FILE: $src */");
    } else {
        copy($src, $dst);
    }
}

/* ---------------------------------------------------------
   COPIAR FICHEIROS DO PROJETO
--------------------------------------------------------- */

copyFileSafe(__DIR__ . "/index.php",      "$tmp/index.php");
copyFileSafe(__DIR__ . "/api.php",        "$tmp/api.php");
copyFileSafe(__DIR__ . "/file.php",       "$tmp/file.php");
copyFileSafe(__DIR__ . "/setup.php",      "$tmp/setup.php");
copyFileSafe(__DIR__ . "/folder.png",     "$tmp/folder.png");
copyFileSafe(__DIR__ . "/file.png",       "$tmp/file.png");

/* ---------------------------------------------------------
   COPIAR PASTAS
--------------------------------------------------------- */

function copyFolder($src, $dst) {
    if (!is_dir($src)) return;

    mkdir($dst, 0777, true);

    foreach (array_diff(scandir($src), ['.','..']) as $item) {
        $s = "$src/$item";
        $d = "$dst/$item";

        if (is_dir($s)) {
            copyFolder($s, $d);
        } else {
            copy($s, $d);
        }
    }
}

copyFolder(__DIR__ . "/data",         "$tmp/data");
copyFolder(__DIR__ . "/recycle_bin",  "$tmp/recycle_bin");

/* ---------------------------------------------------------
   CRIAR ZIP
--------------------------------------------------------- */

$zip = new ZipArchive();
$zip->open($zipName, ZipArchive::CREATE | ZipArchive::OVERWRITE);

$files = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($tmp, FilesystemIterator::SKIP_DOTS),
    RecursiveIteratorIterator::SELF_FIRST
);

foreach ($files as $file) {
    $filePath = $file->getRealPath();
    $local = substr($filePath, strlen($tmp) + 1);

    if ($file->isDir()) {
        $zip->addEmptyDir($local);
    } else {
        $zip->addFile($filePath, $local);
    }
}

$zip->close();

/* ---------------------------------------------------------
   DOWNLOAD ZIP
--------------------------------------------------------- */

header("Content-Type: application/zip");
header("Content-Disposition: attachment; filename=$zipName");
header("Content-Length: " . filesize($zipName));
readfile($zipName);

/* ---------------------------------------------------------
   CLEANUP
--------------------------------------------------------- */

unlink($zipName);
rrmdir_build($tmp);
exit;
