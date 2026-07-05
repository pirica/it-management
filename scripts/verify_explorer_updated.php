<?php
/**
 * Validation Script: Explorer Whitelisting
 *
 * Verifies that the fixed itm_explorer_paths.php correctly whitelists/blacklists
 * extensions as expected.
 */

require_once __DIR__ . '/../includes/itm_explorer_paths.php';

echo "Explorer Whitelisting Validation\n";
echo "===============================\n";

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
    echo "\nSUMMARY: Explorer extension whitelisting verified successfully.\n";
} else {
    echo "\nSUMMARY: Explorer extension whitelisting failed validation.\n";
    exit(1);
}
