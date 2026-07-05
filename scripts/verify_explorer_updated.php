<?php
/**
 * Validation Script: Explorer Whitelisting
 *
 * Verifies that the fixed itm_explorer_paths.php correctly whitelists/blacklists
 * extensions as expected." . $nl */

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
        echo "[PASS] Extension for '$file' was correctly " . ($expected ? "allowed" : "blocked") . ".\n";
    } else {
        echo "[FAIL] Extension for '$file' was INCORRECTLY " . ($result ? "allowed" : "blocked") . ".\n";
        $allPassed = false;
    }
}

if ($allPassed) {
    echo "\nSUMMARY: Explorer extension whitelisting verified successfully." . $nl;
} else {
    echo "\nSUMMARY: Explorer extension whitelisting failed validation." . $nl;
    exit(1);
}

itm_script_output_end();
