<?php
/**
 * Explorer Build ZIP Utility (UK English)
 *
 * Packages the explorer module components for distribution.
 * Localised to UK English and updated to respect the new structure.
 */

/* ---------------------------------------------------------
   CONFIG
--------------------------------------------------------- */

$zipName = "explorer_ready_uk.zip";
$tmp = __DIR__ . "/tmp_build";

/* ---------------------------------------------------------
   TEMPORARY CLEANUP
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
   COPY PROJECT FILES
--------------------------------------------------------- */

copyFileSafe(__DIR__ . "/index.php",      "$tmp/index.php");
copyFileSafe(__DIR__ . "/api.php",        "$tmp/api.php");
copyFileSafe(__DIR__ . "/file.php",       "$tmp/file.php");
copyFileSafe(__DIR__ . "/setup.php",      "$tmp/setup.php");

/* ---------------------------------------------------------
   COPY FOLDERS (Note: data/ and recycle_bin/ are now in root /files/)
--------------------------------------------------------- */

// We don't package user data into the module ZIP usually.

/* ---------------------------------------------------------
   CREATE ZIP
--------------------------------------------------------- */

$zip = new ZipArchive();
if ($zip->open($zipName, ZipArchive::CREATE | ZipArchive::OVERWRITE) === TRUE) {

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

} else {
    echo "Could not create ZIP file.";
}

/* ---------------------------------------------------------
   CLEANUP
--------------------------------------------------------- */

if (file_exists($zipName)) unlink($zipName);
rrmdir_build($tmp);
exit;
