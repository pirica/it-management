<?php
/**
 * Script to list modules with 'active' input fields and checkboxes.
 * Supports both CLI and Browser.
 */

require_once __DIR__ . '/lib/script_cli_output.php';

itm_script_output_begin('List Active Fields and Checkboxes');

$modules_dir = realpath(__DIR__ . '/../modules');
$modules = array_filter(glob($modules_dir . '/*'), 'is_dir');

$active_results = [];
$checkbox_results = [];
$active_text_results = [];

foreach ($modules as $module_path) {
    $module_name = basename($module_path);
    $files = glob($module_path . '/*.php');

    $module_active_files = [];
    $module_checkbox_files = [];
    $module_active_text_files = [];

    foreach ($files as $file) {
        $basename = basename($file);
        if (!in_array($basename, ['create.php', 'edit.php', 'index.php'])) continue;

        $content = file_get_contents($file);
        $relativePath = 'modules/' . $module_name . '/' . $basename;

        // Check for 'active' input field
        $has_active = false;
        if (strpos($content, 'name="active"') !== false ||
            strpos($content, "name='active'") !== false ||
            strpos($content, '$name === \'active\'') !== false) {
            $has_active = true;
            $module_active_files[] = $relativePath;
        }

        // Check for specific type="text" name="active"
        if ($has_active && (strpos($content, 'type="text"') !== false || strpos($content, "type='text'") !== false)) {
            // This is a naive check. Let's look for both in the same file.
            // A more robust check would use regex to ensure they are on the same tag, but for a debug script this might be enough if we just want to flag files for manual review.
            if (preg_match('/<input[^>]+type=["\']text["\'][^>]+name=["\']active["\']/', $content) ||
                preg_match('/<input[^>]+name=["\']active["\'][^>]+type=["\']text["\']/', $content)) {
                $module_active_text_files[] = $relativePath;
            }
        }

        // Check for checkbox
        if (strpos($content, 'type="checkbox"') !== false ||
            strpos($content, "type='checkbox'") !== false) {
            $module_checkbox_files[] = $relativePath;
        }
    }

    if (!empty($module_active_files)) {
        $active_results[$module_name] = $module_active_files;
    }
    if (!empty($module_checkbox_files)) {
        $checkbox_results[$module_name] = $module_checkbox_files;
    }
    if (!empty($module_active_text_files)) {
        $active_text_results[$module_name] = $module_active_text_files;
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
