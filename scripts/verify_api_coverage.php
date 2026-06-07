<?php
/**
 * Verification script to detect missing API documentation or behavior.
 */

require_once __DIR__ . "/../scripts/api.php";
echo "<pre>";
echo "--- API Documentation Coverage Report ---\n";
echo "Date: " . date("Y-m-d H:i:s") . "\n\n";

$root = __DIR__ . "/..";
$endpoints = itmDocCollectModuleImportEndpoints($root);
$missing = itmDocCollectModulesWithoutImportEndpoint($root, $endpoints);

echo "1. Modules without Import Endpoint:\n";
if (empty($missing)) {
    echo "   [OK] All modules have import endpoints.\n";
} else {
    foreach ($missing as $m) {
        echo "   - $m\n";
    }
}

echo "\n2. Bespoke Endpoints Validation:\n";
$bespoke = $projectJsonEndpoints;
foreach ($bespoke as $b) {
    $path = $root . "/" . ltrim($b["path"], "/");
    // Handle query params in path
    $actualPath = explode("?", $path)[0];
    if (file_exists($actualPath)) {
        echo "   [OK] $actualPath exists.\n";
    } else {
        echo "   [FAIL] $actualPath does NOT exist.\n";
    }
}

echo "\n3. Checking for undocumented module AJAX handlers:\n";
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
            echo "   [WARN] Undocumented JSON handler: $relPath\n";
        }
    }
}

echo "\n--- End of Report ---\n";
