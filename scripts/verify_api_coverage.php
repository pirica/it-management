<?php
/**
 * Verification script to detect missing API documentation or behavior.
 */
require_once __DIR__ . '/lib/script_cli_output.php';
require_once __DIR__ . '/../scripts/api.php';

itm_script_output_begin('API documentation coverage');

$nl = itm_script_output_nl();
echo '--- API Documentation Coverage Report ---' . $nl;
echo 'Date: ' . date('Y-m-d H:i:s') . $nl . $nl;

$root = __DIR__ . '/..';
$endpoints = itmDocCollectModuleImportEndpoints($root);
$missing = itmDocCollectModulesWithoutImportEndpoint($root, $endpoints);

echo '1. Modules without Import Endpoint:' . $nl;
if (empty($missing)) {
    echo colorText('   [OK] All modules have import endpoints.', 'pass') . $nl;
} else {
    foreach ($missing as $m) {
        echo '   - ' . $m . $nl;
    }
}

echo $nl . '2. Bespoke Endpoints Validation:' . $nl;
$bespoke = $projectJsonEndpoints;
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
