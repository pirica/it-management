<?php
/**
 * Browser + CLI script to scan all modules under modules/ and extract their <title> tags showing only the inner title text.
 *
 * Why: This script grabs all modules files under modules/ and lists their inner <title> contents with PHP expressions evaluated/rendered.
 */

define('ITM_CLI_SCRIPT', true);
require_once __DIR__ . '/../config/config.php';

// Check for admin permission if accessed via browser SAPI.
itm_script_require_admin_script_or_exit($conn, 'Access denied. Administrator privileges required.');

require_once __DIR__ . '/lib/script_cli_output.php';
require_once __DIR__ . '/lib/itm_titles_list_audit.php';

$isCli = (PHP_SAPI === 'cli' || PHP_SAPI === 'phpdbg');
if (!$isCli) {
    header('Content-Type: text/html; charset=utf-8');
}

itm_script_output_begin('Modules Title List (Rendered Inner Text)');

$root = dirname(__DIR__);
$modulesDir = $root . DIRECTORY_SEPARATOR . 'modules';

if (!is_dir($modulesDir)) {
    echo "FAIL: modules/ directory does not exist." . ($isCli ? "\n" : "<br>");
    exit(1);
}

$files = itm_titles_list_collect_module_php_files($modulesDir);
$stats = [
    'scanned' => count($files),
    'with_title' => 0,
    'match' => 0,
    'not_match' => 0,
    'no_title' => 0,
];

// Resolve application name dynamically using existing configuration global variable or fallback to constant
$globalAppName = !empty($app_name) ? $app_name : (defined('APP_NAME') ? APP_NAME : 'IT Management System');

$rows = [];

foreach ($files as $path) {
    $modulePath = itm_titles_list_module_path_from_root($root, $path);

    $content = @file_get_contents($path);
    if ($content === false) {
        continue;
    }

    if (!preg_match('~<title>(.*?)</title>~is', $content, $matches)) {
        $stats['no_title']++;
        continue;
    }

    $stats['with_title']++;
    $titleBlock = $matches[0];
    $innerTitle = $matches[1];
    $matchesSuffix = itm_titles_list_title_has_app_name_suffix($titleBlock);
    if ($matchesSuffix) {
        $stats['match']++;
    } else {
        $stats['not_match']++;
    }

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

    $prefix = $matchesSuffix ? '' : '[NOT MATCH] ';
    $rows[] = $prefix . $modulePath . ' - title = ' . $rendered;
}

itm_titles_list_echo_summary($stats, $isCli);

foreach ($rows as $outputLine) {
    $escapedLine = $isCli ? $outputLine : htmlspecialchars($outputLine, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    echo $escapedLine . ($isCli ? "\n" : "<br>");
}

itm_script_output_end();
