<?php
/**
 * Verification script to detect missing API documentation or behavior.
 *
 * Browser: scripts/verify_api_coverage.php (Admin). CLI: php scripts/verify_api_coverage.php
 */
declare(strict_types=1);

$itmIsCli = PHP_SAPI === 'cli' || PHP_SAPI === 'phpdbg';
if ($itmIsCli) {
    define('ITM_CLI_SCRIPT', true);
}

define('ITM_API_DOC_FUNCTIONS_ONLY', true);
require_once __DIR__ . '/api.php';
require_once __DIR__ . '/lib/script_cli_output.php';

if (!$itmIsCli) {
    require_once __DIR__ . '/../config/config.php';
    itm_script_require_admin_script_or_exit($conn, 'Access denied. Administrator privileges required.');
}

itm_script_output_begin('API documentation coverage');

$nl = itm_script_output_nl();
echo '--- API Documentation Coverage Report ---' . $nl;
echo 'Date: ' . date('Y-m-d H:i:s') . $nl . $nl;

$root = dirname(__DIR__);
$endpoints = itmDocCollectModuleImportEndpoints($root);
$missing = itmDocCollectModulesWithoutImportEndpoint($root, $endpoints);

echo '1. Modules without Import Endpoint:' . $nl;
if ($missing === []) {
    echo colorText('   [OK] All modules have import endpoints.', 'pass') . $nl;
} else {
    foreach ($missing as $m) {
        echo '   - ' . $m . $nl;
    }
}

echo $nl . '2. Bespoke Endpoints Validation:' . $nl;
$bespoke = itmDocProjectJsonEndpoints();
foreach ($bespoke as $b) {
    $path = $root . '/' . ltrim($b['path'], '/');
    $actualPath = explode('?', $path)[0];
    if (file_exists($actualPath)) {
        echo colorText('   [OK] ' . $actualPath . ' exists.', 'pass') . $nl;
    } else {
        echo colorText('   [FAIL] ' . $actualPath . ' does NOT exist.', 'fail') . $nl;
    }
}

echo $nl . '3. Checking for undocumented module AJAX handlers:' . $nl;
$documentedJsonHandlers = itmDocCollectDocumentedJsonHandlerPaths($root);
$undocumented = [];
foreach (glob($root . '/modules/*/*.php') ?: [] as $file) {
    if (basename($file) === 'index.php') {
        continue;
    }
    $content = (string) file_get_contents($file);
    if (!itmDocFileEmitsJsonResponse($content)) {
        continue;
    }
    $relPath = str_replace('\\', '/', substr($file, strlen($root) + 1));
    if (!itmDocJsonHandlerIsDocumented($relPath, $documentedJsonHandlers)) {
        $undocumented[] = $relPath;
    }
}

if ($undocumented === []) {
    echo colorText('   [OK] All module JSON handlers are documented in scripts/api.php.', 'pass') . $nl;
} else {
    foreach ($undocumented as $relPath) {
        echo colorText('   [WARN] Undocumented JSON handler: ' . $relPath, 'warn') . $nl;
    }
}

echo $nl . '--- End of Report ---' . $nl;

itm_script_output_end();
