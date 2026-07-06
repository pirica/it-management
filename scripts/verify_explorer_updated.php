<?php
/**
 * Validation Script: Explorer Whitelisting
 *
 * Verifies that the fixed itm_explorer_paths.php correctly whitelists/blacklists
 * extensions as expected.
 */

require_once __DIR__ . '/../includes/itm_explorer_paths.php';
require_once __DIR__ . '/lib/script_cli_output.php';
itm_script_output_begin('Explorer Whitelisting Validation');
$nl = itm_script_output_nl();


echo "Explorer Whitelisting Validation" . $nl;
echo "===============================" . $nl;

$tests = [
    'image.jpg' => true,
    'document.pdf' => true,
    'shell.php' => false,
    'shell.phtml' => false,
    'archive.zip' => true,
    'backup.sql' => false,
    'script.sh' => false,
    'valid.docx' => true,
    'hidden.htaccess' => false,
    'test.json' => true
];

$allPassed = true;
foreach ($tests as $file => $expected) {
    $result = itm_explorer_is_allowed_extension($file);
    if ($result === $expected) {
        echo itm_script_format_status_line("[PASS] Extension for '$file' was correctly " . ($expected ? "allowed" : "blocked")) . $nl;
    } else {
        echo itm_script_format_status_line("[FAIL] Extension for '$file' was INCORRECTLY " . ($result ? "allowed" : "blocked")) . $nl;
        $allPassed = false;
    }
}

if ($allPassed) {
    echo $nl . itm_script_format_status_line("SUMMARY: Explorer extension whitelisting verified successfully.") . $nl;
} else {
    echo $nl . itm_script_format_status_line("SUMMARY: Explorer extension whitelisting failed validation.") . $nl;
    exit(1);
}

itm_script_output_end();
