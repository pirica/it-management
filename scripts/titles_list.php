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
require_once __DIR__ . '/lib/itm_titles_list_audit.php';

$isCli = (PHP_SAPI === 'cli' || PHP_SAPI === 'phpdbg');
if (!$isCli) {
    header('Content-Type: text/html; charset=utf-8');
}

itm_script_output_begin('Modules Title List');

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

$rows = [];

foreach ($files as $path) {
    $modulePath = itm_titles_list_module_path_from_root($root, $path);

    $content = file_get_contents($path);
    if ($content === false) {
        continue;
    }

    if (!preg_match('~<title>.*?</title>~is', $content, $matches)) {
        $stats['no_title']++;
        continue;
    }

    $stats['with_title']++;
    $titleBlock = $matches[0];
    $matchesSuffix = itm_titles_list_title_matches_canonical($titleBlock);
    if ($matchesSuffix) {
        $stats['match']++;
    } else {
        $stats['not_match']++;
    }

    $prefix = $matchesSuffix ? '' : '[NOT MATCH] ';
    $rows[] = $prefix . $modulePath . ' - title = ' . $titleBlock;
}

itm_titles_list_echo_summary($stats, $isCli);

foreach ($rows as $outputLine) {
    $escapedLine = $isCli ? $outputLine : htmlspecialchars($outputLine, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    echo $escapedLine . ($isCli ? "\n" : "<br>");
}

itm_script_output_end();
