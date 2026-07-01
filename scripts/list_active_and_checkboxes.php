<?php
/**
 * Script to list modules with 'active' input fields and checkboxes,
 * constrained to modules that actually have an 'active' database column.
 * Supports both CLI and Browser.
 */

if (!defined('ROOT_PATH')) {
    define('ROOT_PATH', realpath(__DIR__ . '/..') . DIRECTORY_SEPARATOR);
}

require_once __DIR__ . '/lib/script_cli_output.php';
define('ITM_CLI_SCRIPT', true);
require_once __DIR__ . '/../config/config.php';

$nl = (php_sapi_name() === 'cli' ? "\n" : "<br><br>");
itm_script_output_begin('List Active Fields and Checkboxes (Constrained by DB Column)');

$modules_dir = realpath(__DIR__ . '/../modules');
$modules = array_filter(glob($modules_dir . '/*'), 'is_dir');

$active_input_results = [];
$active_text_results = [];
$active_checkbox_results = [];

foreach ($modules as $module_path) {
    $module_name = basename($module_path);

    // Check database schema for 'active' column (case-insensitive)
    $res = mysqli_query($conn, "SHOW TABLES LIKE '{$module_name}'");
    $has_table = ($res && mysqli_num_rows($res) > 0);

    $has_active_column = false;
    if ($has_table) {
        $res = mysqli_query($conn, "SHOW COLUMNS FROM `{$module_name}` LIKE 'active'");
        if ($res && mysqli_num_rows($res) > 0) {
            $has_active_column = true;
        } else {
            // Check for 'Active' (upper case) just in case
            $res = mysqli_query($conn, "SHOW COLUMNS FROM `{$module_name}` LIKE 'Active'");
            if ($res && mysqli_num_rows($res) > 0) {
                $has_active_column = true;
            }
        }
    }

    // ONLY proceed if the module has an 'active' column in the DB
    if (!$has_active_column) {
        continue;
    }

    $files = glob($module_path . '/*.php');
    foreach ($files as $file) {
        $basename = basename($file);
        if (!in_array($basename, ['create.php', 'edit.php', 'index.php'])) continue;

        $content = file_get_contents($file);
        $relativePath = 'modules/' . $module_name . '/' . $basename;

        // 1. Check for ANY 'active' input field in code
        if (preg_match('/name=["\']active["\']/i', $content) ||
            (strpos($content, '$name === \'active\'') !== false && strpos($content, '$col[\'Field\']') !== false)) {
            $active_input_results[$module_name][] = $relativePath;

            // 1a. Specifically detect type="text"
            if (preg_match('/<input[^>]+type=["\']text["\'][^>]+name=["\']active["\']/i', $content) ||
                preg_match('/<input[^>]+name=["\']active["\'][^>]+type=["\']text["\']/i', $content)) {
                $active_text_results[$module_name][] = $relativePath;
            }
        }

        // 2. Check for ANY checkbox in this module (now constrained by having an 'active' DB column)
        if (stripos($content, 'type="checkbox"') !== false) {
            $active_checkbox_results[$module_name][] = $relativePath;
        }
    }
}

function format_module_link_v2($name) {
    if (itm_script_is_cli_sapi()) {
        return " (link modules/$name module)";
    }
    return ' (<a href="../modules/' . htmlspecialchars($name) . '/index.php" target="_blank">link modules/' . htmlspecialchars($name) . ' module</a>)';
}

// Flatten for counting distinct module/file pairs
$count_active = 0; foreach($active_input_results as $files) $count_active += count($files);
$count_text = 0; foreach($active_text_results as $files) $count_text += count($files);
$count_checkbox = 0; foreach($active_checkbox_results as $files) $count_checkbox += count($files);

echo "Count: " . $count_active . $nl;
echo "### Modules with 'active' input field:" . $nl;
foreach ($active_input_results as $name => $files) {
    foreach ($files as $f) {
        echo $f . format_module_link_v2($name) . $nl;
    }
}

echo $nl . "Count: " . $count_text . $nl;
echo "### Modules with <input type=\"text\" name=\"active\">:" . $nl;
if (empty($active_text_results)) {
    echo "None found." . $nl;
} else {
    foreach ($active_text_results as $name => $files) {
        foreach ($files as $f) {
            echo $f . format_module_link_v2($name) . $nl;
        }
    }
}

echo $nl . "Count: " . $count_checkbox . $nl;
echo "### Modules with checkboxes (only if DB has active field):" . $nl;
foreach ($active_checkbox_results as $name => $files) {
    foreach ($files as $f) {
        echo $f . format_module_link_v2($name) . $nl;
    }
}

itm_script_output_end();
