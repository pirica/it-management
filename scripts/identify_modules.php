<?php
/**
 * Scan modules/ and categorize standard CRUD vs bespoke modules.
 *
 * Browser: Admin JSON preview; optional ?save=1 writes scripts/modules_metadata.json.
 * CLI: php scripts/identify_modules.php > scripts/modules_metadata.json
 */
$itmIsCli = PHP_SAPI === 'cli' || PHP_SAPI === 'phpdbg';
if ($itmIsCli) {
    define('ITM_CLI_SCRIPT', true);
}

require_once __DIR__ . '/lib/script_cli_output.php';

if (!function_exists('itm_identify_modules_collect')) {
    /**
     * @return array<string, array{path:string,crud_table:?string,is_standard:bool}>
     */
    function itm_identify_modules_collect(): array
    {
        $modulesDir = dirname(__DIR__) . '/modules';
        $modules = [];

        foreach (scandir($modulesDir) ?: [] as $module) {
            if ($module === '.' || $module === '..' || !is_dir($modulesDir . '/' . $module)) {
                continue;
            }

            $indexPath = $modulesDir . '/' . $module . '/index.php';
            if (!is_file($indexPath)) {
                continue;
            }

            $content = (string) file_get_contents($indexPath);
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

        return $modules;
    }
}

$modules = itm_identify_modules_collect();
$json = json_encode($modules, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

if ($itmIsCli) {
    echo $json;
    exit(0);
}

require_once __DIR__ . '/../config/config.php';
itm_script_require_admin_script_or_exit($conn, 'Access denied. Administrator privileges required.');

itm_script_output_begin('Identify modules');
$nl = itm_script_output_nl();

$save = isset($_GET['save']) && (string) $_GET['save'] === '1';
if ($save) {
    $metadataPath = __DIR__ . '/modules_metadata.json';
    if (file_put_contents($metadataPath, $json . "\n") === false) {
        echo colorText('[FAIL] Could not write ' . $metadataPath, 'fail') . $nl;
    } else {
        echo colorText('[OK] Wrote ' . $metadataPath, 'pass') . $nl . $nl;
    }
} else {
    echo 'Preview only (dry-run). To write scripts/modules_metadata.json in the browser, open with ?save=1 (Admin).' . $nl;
    echo 'CLI: php scripts/identify_modules.php > scripts/modules_metadata.json' . $nl . $nl;
}

echo $json . $nl;
itm_script_output_end();
