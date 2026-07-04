<?php
/**
 * CLI-only: scan modules directory and save metadata to modules_metadata.json.
 */
if (PHP_SAPI !== 'cli' && PHP_SAPI !== 'phpdbg') {
    require_once __DIR__ . '/lib/script_browser_nav.php';
    header('Content-Type: text/html; charset=utf-8');
    echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><title>CLI only</title></head><body style="font-family:Segoe UI,sans-serif;margin:16px;">';
    itm_script_browser_nav_echo();
    echo '<p><strong>CLI only.</strong> This tool must be run from the terminal.</p><pre>php scripts/identify_modules.php > scripts/modules_metadata.json</pre></body></html>';
    exit(1);
}

define('ITM_CLI_SCRIPT', true);
require_once __DIR__ . '/../config/config.php';

$modulesDir = __DIR__ . '/../modules';
$modules = [];

foreach (scandir($modulesDir) as $module) {
    if ($module === '.' || $module === '..' || !is_dir($modulesDir . '/' . $module)) {
        continue;
    }

    $indexPath = $modulesDir . '/' . $module . '/index.php';
    if (!is_file($indexPath)) {
        continue;
    }

    $content = file_get_contents($indexPath);
    $crudTable = null;
    if (preg_match('/\$crud_table\s*=\s*(?:\$crud_table\s*\?\?\s*)?[\'"]([^\'"]+)[\'"]/', $content, $matches)) {
        $crudTable = $matches[1];
    }

    $modules[$module] = [
        'path' => 'modules/' . $module,
        'crud_table' => $crudTable,
        'is_standard' => ($crudTable !== null),
    ];
}

echo json_encode($modules, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
