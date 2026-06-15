<?php
/**
 * Verification script to detect missing API documentation or behavior.
 */

require_once __DIR__ . "/../scripts/api.php";
$nl = (php_sapi_name() === 'cli' ? "\n" : "<br><br>");

if (php_sapi_name() !== 'cli') {
    echo "<pre>";
}
echo "--- API Documentation Coverage Report ---" . $nl;
echo "Date: " . date("Y-m-d H:i:s") . $nl . $nl;

$root = __DIR__ . "/..";
$endpoints = itmDocCollectModuleImportEndpoints($root);
$missing = itmDocCollectModulesWithoutImportEndpoint($root, $endpoints);

echo "1. Modules without Import Endpoint:" . $nl;
if (empty($missing)) {
    echo "   [OK] All modules have import endpoints." . $nl;
} else {
    foreach ($missing as $m) {
        echo "   - $m" . $nl;
    }
}

echo $nl . "2. Bespoke Endpoints Validation:" . $nl;
$bespoke = $projectJsonEndpoints;
foreach ($bespoke as $b) {
    $path = $root . "/" . ltrim($b["path"], "/");
    // Handle query params in path
    $actualPath = explode("?", $path)[0];
    if (file_exists($actualPath)) {
        echo "   [OK] $actualPath exists." . $nl;
    } else {
        echo "   [FAIL] $actualPath does NOT exist." . $nl;
    }
}

echo $nl . "3. Checking for undocumented module AJAX handlers:" . $nl;
// Heuristic: search for $_POST and Content-Type: application/json in modules
foreach (glob($root . "/modules/*/*.php") as $file) {
    if (basename($file) === "index.php") continue;
    $content = file_get_contents($file);
    if (strpos($content, "application/json") !== false) {
        $relPath = str_replace($root . "/", "", $file);
        // Check if documented in bespoke list
        $found = false;
        foreach ($bespoke as $b) {
            if (strpos($b["path"], $relPath) !== false) {
                $found = true;
                break;
            }
        }
        if (!$found) {
            echo "   [WARN] Undocumented JSON handler: $relPath" . $nl;
        }
    }
}

echo $nl . "--- End of Report ---" . $nl;
