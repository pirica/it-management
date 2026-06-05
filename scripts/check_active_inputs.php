<?php
/**
 * Script to detect non-standard 'active' text inputs in module forms.
 * Searches for exact matches of:
 * <input type="text" name="active" value="1">
 * <input type="text" name="active" value="0">
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

$patterns = [
    '<input type="text" name="active" value="1">',
    '<input type="text" name="active" value="0">'
];

// Scan top-level modules only
$modules = array_filter(glob($modules_dir . '/*'), 'is_dir');

foreach ($modules as $modulePath) {
    $moduleName = basename($modulePath);
    foreach (['create.php', 'edit.php'] as $file) {
        $fullPath = $modulePath . DIRECTORY_SEPARATOR . $file;
        if (file_exists($fullPath)) {
            $content = file_get_contents($fullPath);

            $found = false;
            foreach ($patterns as $pattern) {
                if (strpos($content, $pattern) !== false) {
                    $found = true;
                    break;
                }
            }

            if ($found) {
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
