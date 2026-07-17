<?php
/**
 * Browser + CLI script to scan all modules under modules/ and extract their <title> tags.
 *
 * Why: This script grabs all modules files under modules/ and lists their <title> tags.
 */

define('ITM_CLI_SCRIPT', true);
require_once __DIR__ . '/../config/config.php';

// Check for admin permission if accessed via browser SAPI.
itm_script_require_admin_script_or_exit($conn, 'Access denied. Administrator privileges required.');

require_once __DIR__ . '/lib/script_cli_output.php';

$isCli = (PHP_SAPI === 'cli' || PHP_SAPI === 'phpdbg');
if (!$isCli) {
    header('Content-Type: text/html; charset=utf-8');
    require_once __DIR__ . '/lib/script_browser_nav.php';
    itm_script_browser_nav_echo('titles_list.php');
}

itm_script_output_begin('Modules Title List');

$root = dirname(__DIR__);
$modulesDir = $root . DIRECTORY_SEPARATOR . 'modules';

if (!is_dir($modulesDir)) {
    echo "FAIL: modules/ directory does not exist." . ($isCli ? "\n" : "<br>");
    exit(1);
}

$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($modulesDir, FilesystemIterator::SKIP_DOTS)
);

$files = [];
foreach ($iterator as $fileInfo) {
    if (!$fileInfo->isFile() || strtolower($fileInfo->getExtension()) !== 'php') {
        continue;
    }
    $path = $fileInfo->getPathname();
    $files[] = $path;
}

sort($files);

foreach ($files as $path) {
    $relative = str_replace($root . DIRECTORY_SEPARATOR, '', $path);
    $relativeNorm = str_replace('\\', '/', $relative);

    // Ensure path starts with modules/
    if (strpos($relativeNorm, 'modules/') !== 0) {
        $modulePath = 'modules/' . ltrim($relativeNorm, '/');
    } else {
        $modulePath = $relativeNorm;
    }

    $content = file_get_contents($path);
    if ($content === false) {
        continue;
    }

    // Capture the entire <title>...</title> block (case-insensitive, multiline)
    if (preg_match('~<title>.*?</title>~is', $content, $matches)) {
        $outputLine = $modulePath . ' - title = ' . $matches[0];
        $escapedLine = $isCli ? $outputLine : htmlspecialchars($outputLine, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        echo $escapedLine . ($isCli ? "\n" : "<br>");
    }
}

itm_script_output_end();
