<?php
/**
 * Script to detect non-standard 'active' text inputs in module forms.
 * Uses a robust case-insensitive regex to identify:
 * <input ... name="active" ... type="text" ... value="0|1" ...>
 *
 * Supports both CLI and Browser.
 */

if (!defined('ROOT_PATH')) {
    define('ROOT_PATH', realpath(__DIR__ . '/..') . DIRECTORY_SEPARATOR);
}

require_once __DIR__ . '/lib/script_cli_output.php';

itm_script_output_begin('Check Non-Standard Active Inputs');

$modules_dir = ROOT_PATH . 'modules';
$results = [];

// Single case-insensitive regex with lookaheads to find attributes in any order
$regex = '/<input(?=[^>]*name=["\']active["\'])(?=[^>]*type=["\']text["\'])(?=[^>]*value=["\'][01]["\'])[^>]*>/i';

// Scan top-level modules only
$modules = array_filter(glob($modules_dir . '/*'), 'is_dir');

foreach ($modules as $modulePath) {
    $moduleName = basename($modulePath);
    foreach (['create.php', 'edit.php'] as $file) {
        $fullPath = $modulePath . DIRECTORY_SEPARATOR . $file;
        if (file_exists($fullPath)) {
            $content = file_get_contents($fullPath);

            if (preg_match($regex, $content)) {
                $results[] = [
                    'path' => 'modules/' . $moduleName . '/' . $file,
                    'module' => $moduleName
                ];
            }
        }
    }
}

echo "Count: " . count($results) . "\n";

foreach ($results as $res) {
    $moduleName = $res['module'];
    $filePath = $res['path'];

    if (itm_script_is_cli_sapi()) {
        echo "{$filePath} (link modules/{$moduleName} module)\n";
    } else {
        $moduleUrl = "../modules/" . htmlspecialchars($moduleName) . "/index.php";
        echo '<a href="' . $moduleUrl . '" target="_blank">' . htmlspecialchars($filePath) . '</a> ';
        echo '(<a href="' . $moduleUrl . '" target="_blank">link modules/' . htmlspecialchars($moduleName) . ' module</a>)' . "\n";
    }
}

itm_script_output_end();
