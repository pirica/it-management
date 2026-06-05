<?php
/**
 * Script to list modules with 'active' input fields and checkboxes.
 * Supports both CLI and Browser.
 */

if (!defined('ROOT_PATH')) {
    define('ROOT_PATH', realpath(__DIR__ . '/..') . DIRECTORY_SEPARATOR);
}

require_once __DIR__ . '/lib/script_cli_output.php';
define('ITM_CLI_SCRIPT', true);
require_once __DIR__ . '/../config/config.php';

itm_script_output_begin('List Active Fields and Checkboxes');

$modules_dir = realpath(__DIR__ . '/../modules');
$modules = array_filter(glob($modules_dir . '/*'), 'is_dir');

$active_results = [];
$active_text_results = [];
$checkbox_results = [];

foreach ($modules as $module_path) {
    $module_name = basename($module_path);

    // 1. Check database schema for 'active' column
    $res = mysqli_query($conn, "SHOW TABLES LIKE '{$module_name}'");
    $has_table = ($res && mysqli_num_rows($res) > 0);

    $has_active_column = false;
    if ($has_table) {
        $res = mysqli_query($conn, "SHOW COLUMNS FROM `{$module_name}` LIKE 'active'");
        $has_active_column = ($res && mysqli_num_rows($res) > 0);
    }

    $files = glob($module_path . '/*.php');
    foreach ($files as $file) {
        $basename = basename($file);
        if (!in_array($basename, ['create.php', 'edit.php', 'index.php'])) continue;

        $content = file_get_contents($file);
        $relativePath = 'modules/' . $module_name . '/' . $basename;

        // 2. Check for 'active' input field (case-insensitive)
        if ($has_active_column) {
            if (preg_match('/name=["\']active["\']/i', $content) ||
                (strpos($content, '$name === \'active\'') !== false && strpos($content, '$col[\'Field\']') !== false)) {
                $active_results[$module_name][] = $relativePath;

                // Specifically detect type="text"
                if (preg_match('/<input[^>]+type=["\']text["\'][^>]+name=["\']active["\']/i', $content) ||
                    preg_match('/<input[^>]+name=["\']active["\'][^>]+type=["\']text["\']/i', $content)) {
                    $active_text_results[$module_name][] = $relativePath;
                }
            }
        }

        // 3. Check for checkbox
        if (stripos($content, 'type="checkbox"') !== false) {
            $checkbox_results[$module_name][] = $relativePath;
        }
    }
}

function format_module_link($name) {
    if (itm_script_is_cli_sapi()) {
        return " (link modules/$name module)";
    }
    return ' (<a href="../modules/' . htmlspecialchars($name) . '/index.php" target="_blank">link modules/' . htmlspecialchars($name) . ' module</a>)';
}

echo "Count (Active fields): " . count($active_results) . "\n";
echo "### Modules with 'active' input field:\n";
foreach ($active_results as $name => $files) {
    foreach ($files as $f) {
        echo $f . format_module_link($name) . "\n";
    }
}

echo "\nCount (Active text fields): " . count($active_text_results) . "\n";
echo "### Modules with <input type=\"text\" name=\"active\">:\n";
if (empty($active_text_results)) {
    echo "None found.\n";
} else {
    foreach ($active_text_results as $name => $files) {
        foreach ($files as $f) {
            echo $f . format_module_link($name) . "\n";
        }
    }
}

echo "\nCount (Checkboxes): " . count($checkbox_results) . "\n";
echo "### Modules with checkboxes:\n";
foreach ($checkbox_results as $name => $files) {
    foreach ($files as $f) {
        echo $f . format_module_link($name) . "\n";
    }
}

itm_script_output_end();
