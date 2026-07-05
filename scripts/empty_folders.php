<?php
/**
 * Backfill empty index.html on every folder inside the project tree.
 *
 * Why: Directory listing prevention requires index.html on every directory;
 * upload paths also receive managed .htaccess via itm_ensure_upload_directory().
 */
define('ITM_CLI_SCRIPT', true);
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/lib/script_cli_output.php';
$nl = itm_script_output_nl();



itm_script_output_begin('Empty index.html on project folders');

$nl = (php_sapi_name() === 'cli' ? "\n" : '<br><br>');

if (!function_exists('empty_folders_normalized_path')) {
    function empty_folders_normalized_path($path)
    {
        return rtrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, (string) $path), DIRECTORY_SEPARATOR);
    }
}

if (!function_exists('empty_folders_should_skip')) {
    function empty_folders_should_skip($absolutePath)
    {
        $projectRoot = empty_folders_normalized_path(ROOT_PATH);
        $absolutePath = empty_folders_normalized_path($absolutePath);
        if ($absolutePath === '' || strpos($absolutePath, $projectRoot) !== 0) {
            return true;
        }

        $relative = ltrim(substr($absolutePath, strlen($projectRoot)), DIRECTORY_SEPARATOR);
        if ($relative === '') {
            return false;
        }

        $skipNames = ['.git', '.github', 'node_modules', '.svn', '.hg', '.cursor'];
        foreach (explode(DIRECTORY_SEPARATOR, $relative) as $segment) {
            if ($segment === '' || $segment === '.') {
                continue;
            }
            if (in_array($segment, $skipNames, true)) {
                return true;
            }
            if ($segment[0] === '.') {
                return true;
            }
        }

        return false;
    }
}

if (!function_exists('empty_folders_upload_policy_for_path')) {
    function empty_folders_upload_policy_for_path($absolutePath)
    {
        $absolutePath = empty_folders_normalized_path($absolutePath);
        $roots = [
            [empty_folders_normalized_path(UPLOAD_PATH), 'upload'],
            [empty_folders_normalized_path(TICKET_UPLOAD_PATH), 'upload'],
            [empty_folders_normalized_path(FLOOR_PLAN_UPLOAD_PATH), 'upload'],
            [empty_folders_normalized_path(BACKUP_PATH), 'deny_all'],
            [empty_folders_normalized_path(itm_files_storage_root()), 'deny_http'],
        ];

        foreach ($roots as $rootSpec) {
            $root = $rootSpec[0];
            $policy = $rootSpec[1];
            if ($root === '') {
                continue;
            }
            if ($absolutePath === $root || strpos($absolutePath, $root . DIRECTORY_SEPARATOR) === 0) {
                return $policy;
            }
        }

        return null;
    }
}

if (!function_exists('empty_folders_read_file_content')) {
    function empty_folders_read_file_content($absolutePath)
    {
        if (!is_file($absolutePath)) {
            return null;
        }
        $content = @file_get_contents($absolutePath);
        return $content === false ? null : $content;
    }
}

if (!function_exists('empty_folders_index_is_current')) {
    function empty_folders_index_is_current($absolutePath)
    {
        $absolutePath = empty_folders_normalized_path($absolutePath);
        if ($absolutePath === '') {
            return false;
        }
        $indexPath = $absolutePath . DIRECTORY_SEPARATOR . 'index.html';
        $actual = empty_folders_read_file_content($indexPath);
        return $actual !== null && $actual === itm_upload_directory_empty_index_html();
    }
}

if (!function_exists('empty_folders_upload_is_current')) {
    function empty_folders_upload_is_current($absolutePath, $policy)
    {
        $absolutePath = empty_folders_normalized_path($absolutePath);
        if ($absolutePath === '') {
            return false;
        }
        if (!empty_folders_index_is_current($absolutePath)) {
            return false;
        }
        $htaccessPath = $absolutePath . DIRECTORY_SEPARATOR . '.htaccess';
        $actual = empty_folders_read_file_content($htaccessPath);
        return $actual !== null && $actual === itm_upload_directory_policy_body($policy);
    }
}

if (!function_exists('empty_folders_force_index_html')) {
    function empty_folders_force_index_html($absolutePath)
    {
        $absolutePath = empty_folders_normalized_path($absolutePath);
        if ($absolutePath === '') {
            return false;
        }
        $indexPath = $absolutePath . DIRECTORY_SEPARATOR . 'index.html';
        @file_put_contents($indexPath, itm_upload_directory_empty_index_html(), LOCK_EX);
        return is_file($indexPath);
    }
}

if (!function_exists('empty_folders_relative_index_path')) {
    function empty_folders_relative_index_path($absolutePath)
    {
        $projectRoot = empty_folders_normalized_path(ROOT_PATH);
        $absolutePath = empty_folders_normalized_path($absolutePath);
        $relative = ltrim(substr($absolutePath, strlen($projectRoot)), DIRECTORY_SEPARATOR);
        $relative = str_replace(DIRECTORY_SEPARATOR, '/', $relative);
        if ($relative === '') {
            return 'index.html';
        }
        return $relative . '/index.html';
    }
}

if (!function_exists('empty_folders_collect_project_dirs')) {
    function empty_folders_collect_project_dirs()
    {
        $projectRoot = empty_folders_normalized_path(ROOT_PATH);
        if ($projectRoot === '' || !is_dir($projectRoot)) {
            return [];
        }

        $dirs = [$projectRoot];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($projectRoot, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $fileInfo) {
            if (!$fileInfo->isDir()) {
                continue;
            }
            $dir = $fileInfo->getPathname();
            if (empty_folders_should_skip($dir)) {
                continue;
            }
            $dirs[] = $dir;
        }

        return array_values(array_unique($dirs));
    }
}

$dirs = empty_folders_collect_project_dirs();
if (empty($dirs)) {
    echo colorText('[FAIL] Project root is missing or not readable: ' . empty_folders_normalized_path(ROOT_PATH), 'fail') . $nl;
    exit(1);
}

echo colorText('Scanning project folders for missing or outdated index.html...', 'info') . $nl;

$scannedFolders = 0;
$alreadyCurrent = 0;
$uploadHardenedUpdated = 0;
$failures = 0;
$updatedPaths = [];

foreach ($dirs as $dir) {
    $scannedFolders++;
    $policy = empty_folders_upload_policy_for_path($dir);
    $ok = false;
    $wasUpdated = false;

    if ($policy !== null) {
        if (empty_folders_upload_is_current($dir, $policy)) {
            $alreadyCurrent++;
            continue;
        }
        $ok = itm_ensure_upload_directory($dir, $policy);
        if ($ok) {
            $uploadHardenedUpdated++;
            $wasUpdated = true;
        }
    } else {
        if (empty_folders_index_is_current($dir)) {
            $alreadyCurrent++;
            continue;
        }
        $ok = empty_folders_force_index_html($dir);
        if ($ok) {
            $wasUpdated = true;
        }
    }

    if (!$ok) {
        echo colorText('[FAIL] ' . $dir, 'fail') . $nl;
        $failures++;
        continue;
    }

    if ($wasUpdated) {
        $updatedPaths[] = empty_folders_relative_index_path($dir);
    }
}

if ($failures > 0) {
    echo colorText('[FAIL] ' . $failures . ' folder(s) could not be updated.', 'fail') . $nl;
    exit(1);
}

$updatedCount = count($updatedPaths);
if ($updatedCount === 0) {
    echo colorText('No new or changed folders.', 'info') . $nl;
} else {
    sort($updatedPaths, SORT_STRING);
    foreach ($updatedPaths as $relativeIndexPath) {
        echo $relativeIndexPath . $nl;
    }
}

$summary = '[PASS] Updated ' . $updatedCount . ' folder(s) under '
    . empty_folders_normalized_path(ROOT_PATH)
    . ' (' . $uploadHardenedUpdated . ' upload-hardened). '
    . $alreadyCurrent . ' already current (' . $scannedFolders . ' scanned).';
echo colorText($summary, 'pass') . $nl;
exit(0);
