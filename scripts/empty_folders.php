<?php
/**
 * Backfill empty index.html (and managed .htaccess) on every folder under all upload roots.
 *
 * Why: Directory listing prevention requires index.html on every upload folder segment;
 * this script repairs existing trees without re-uploading files.
 */
define('ITM_CLI_SCRIPT', true);
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/lib/script_cli_output.php';

itm_script_output_begin('Empty index.html on upload folders');

$nl = (php_sapi_name() === 'cli' ? "\n" : '<br>');

if (!function_exists('empty_folders_upload_roots')) {
    function empty_folders_upload_roots()
    {
        return [
            ['path' => UPLOAD_PATH, 'policy' => 'upload', 'label' => 'images'],
            ['path' => TICKET_UPLOAD_PATH, 'policy' => 'upload', 'label' => 'tickets_photos'],
            ['path' => FLOOR_PLAN_UPLOAD_PATH, 'policy' => 'upload', 'label' => 'floor_plans'],
            ['path' => BACKUP_PATH, 'policy' => 'deny_all', 'label' => 'backups'],
            ['path' => itm_files_storage_root(), 'policy' => 'deny_http', 'label' => 'files'],
        ];
    }
}

if (!function_exists('empty_folders_ensure_tree')) {
    function empty_folders_ensure_tree($root, $policy)
    {
        $root = rtrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, (string) $root), DIRECTORY_SEPARATOR);
        $count = 0;
        $failures = 0;

        if ($root === '') {
            return ['count' => 0, 'failures' => 1];
        }

        if (!is_dir($root)) {
            if (!itm_ensure_upload_directory($root, $policy)) {
                return ['count' => 0, 'failures' => 1];
            }
            return ['count' => 1, 'failures' => 0];
        }

        $dirs = [$root];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $fileInfo) {
            if (!$fileInfo->isDir()) {
                continue;
            }
            $dirs[] = $fileInfo->getPathname();
        }

        $dirs = array_values(array_unique($dirs));
        foreach ($dirs as $dir) {
            if (!itm_ensure_upload_directory($dir, $policy)) {
                $failures++;
                continue;
            }
            $count++;
        }

        return ['count' => $count, 'failures' => $failures];
    }
}

$totalFolders = 0;
$totalFailures = 0;

foreach (empty_folders_upload_roots() as $rootSpec) {
    $result = empty_folders_ensure_tree($rootSpec['path'], $rootSpec['policy']);
    $label = $rootSpec['label'];
    $path = rtrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, (string) $rootSpec['path']), DIRECTORY_SEPARATOR);

    if ($result['failures'] > 0 && $result['count'] === 0) {
        echo '[WARN] ' . $label . ' (' . $path . '): root missing or not writable — skipped.' . $nl;
        continue;
    }

    if ($result['failures'] > 0) {
        echo '[FAIL] ' . $label . ': ' . $result['failures'] . ' folder(s) under ' . $path . ' could not be updated.' . $nl;
    } else {
        echo '[PASS] ' . $label . ': empty index.html on ' . $result['count'] . ' folder(s) under ' . $path . '.' . $nl;
    }

    $totalFolders += $result['count'];
    $totalFailures += $result['failures'];
}

if ($totalFailures > 0) {
    echo '[FAIL] ' . $totalFailures . ' folder(s) could not be updated across upload roots.' . $nl;
    exit(1);
}

echo '[PASS] Ensured empty index.html on ' . $totalFolders . ' folder(s) across all upload roots.' . $nl;
exit(0);
