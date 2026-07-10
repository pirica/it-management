<?php
/**
 * Browser + CLI script to scan all modules under modules/ and extract their <title> tags showing only the inner title text.
 *
 * Why: This script grabs all modules files under modules/ and lists their inner <title> contents with PHP expressions evaluated/rendered.
 */

define('ITM_CLI_SCRIPT', true);
require_once __DIR__ . '/../config/config.php';

// Check for admin permission if accessed via browser SAPI.
if (PHP_SAPI !== 'cli' && PHP_SAPI !== 'phpdbg' && !itm_is_admin($conn, (int)($_SESSION['employee_id'] ?? 0))) {
    http_response_code(403);
    die('Access denied. Administrator privileges required.');
}

require_once __DIR__ . '/lib/script_cli_output.php';

$isCli = (PHP_SAPI === 'cli' || PHP_SAPI === 'phpdbg');
if (!$isCli) {
    header('Content-Type: text/html; charset=utf-8');
    require_once __DIR__ . '/lib/script_browser_nav.php';
    itm_script_browser_nav_echo('titles_list_show.php');
}

itm_script_output_begin('Modules Title List (Rendered Inner Text)');

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

// Resolve application name dynamically using existing configuration global variable or fallback to constant
$globalAppName = !empty($app_name) ? $app_name : (defined('APP_NAME') ? APP_NAME : 'IT Management System');

foreach ($files as $path) {
    $relative = str_replace($root . DIRECTORY_SEPARATOR, '', $path);
    $relativeNorm = str_replace('\\', '/', $relative);

    // Ensure path starts with modules/
    if (strpos($relativeNorm, 'modules/') !== 0) {
        $modulePath = 'modules/' . ltrim($relativeNorm, '/');
    } else {
        $modulePath = $relativeNorm;
    }

    $content = @file_get_contents($path);
    if ($content === false) {
        continue;
    }

    // Capture the inner content of <title>...</title> block (case-insensitive, multiline)
    if (preg_match('~<title>(.*?)</title>~is', $content, $matches)) {
        $innerTitle = $matches[1];

        // Try to extract $crud_title or $crud_table value from the file content
        $crud_title = null;
        if (preg_match('/\$crud_title\s*=\s*[\'"]([^\'"]+)[\'"]/i', $content, $m)) {
            $crud_title = $m[1];
        } elseif (preg_match('/\$crud_table\s*=\s*[\'"]([^\'"]+)[\'"]/i', $content, $m)) {
            $crud_table = $m[1];
            $crud_title = ucwords(str_replace('_', ' ', $crud_table));
        }

        // Determine fallback folder name
        $dirName = basename(dirname($path));
        $folderTitle = ucwords(str_replace('_', ' ', $dirName));

        $resolvedTitle = ($crud_title !== null && $crud_title !== '') ? $crud_title : $folderTitle;
        $resolvedApp = $globalAppName;

        // Replace the most common patterns directly:
        $rendered = $innerTitle;
        $rendered = preg_replace('/<\?=\s*sanitize\(\$crud_title\)\s*\?>/i', $resolvedTitle, $rendered);
        $rendered = preg_replace('/<\?php\s*echo\s*sanitize\(\$app_name\s*\?\?\s*itm_ui_config_app_name\(\$currentUiConfig\)\);\s*\?>/i', $resolvedApp, $rendered);

        // Replace other dynamic tags with $crud_title or $app_name references
        $rendered = preg_replace('/<\?(?:php|=)\s*(?:echo\s+)?(?:sanitize\()?\$crud_title\b.*?\?>/i', $resolvedTitle, $rendered);
        $rendered = preg_replace('/<\?(?:php|=)\s*(?:echo\s+)?(?:sanitize\()?(?:\$app_name|itm_ui_config_app_name\b).*?\?>/i', $resolvedApp, $rendered);

        // Strip any remaining PHP tags
        $rendered = preg_replace('/<\?(?:php|=).*?\?>/is', '', $rendered);

        // Standardize whitespace
        $rendered = preg_replace('/\s+/', ' ', $rendered);
        $rendered = trim($rendered);

        $outputLine = $modulePath . ' - title = <title>' . $rendered . '</title>';
        $escapedLine = $isCli ? $outputLine : htmlspecialchars($outputLine, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        echo $escapedLine . ($isCli ? "\n" : "<br>");
    }
}

itm_script_output_end();
